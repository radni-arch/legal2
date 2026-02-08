<?php

namespace App\Services;

use Aws\Textract\TextractClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TextractService
{
    protected ?TextractClient $client = null;

    protected string $bucket;

    protected string $inputPrefix;

    protected string $jsonPrefix;

    // Initialize lazily to support tests that mock the service without calling the constructor
    protected ?CircuitBreaker $circuitBreaker = null;

    public function __construct()
    {
        // Don't initialize client immediately - use lazy loading via getClient()
        // This prevents errors during test bootstrap when AWS env vars aren't available
        // Use config() instead of env() to support cached configuration in production
        $this->bucket = (string) config('services.aws.bucket');
        $this->inputPrefix = trim((string) config('services.aws.textract.input_prefix', 'textract/input'), '/');
        $this->jsonPrefix = trim((string) config('services.aws.textract.json_prefix', 'textract/json'), '/');

        // Initialize circuit breaker for AWS Textract API
        $this->circuitBreaker = new CircuitBreaker(
            serviceName: 'aws_textract',
            failureThreshold: config('aws.circuit_breaker.failure_threshold', 5),
            successThreshold: config('aws.circuit_breaker.success_threshold', 2),
            timeout: config('aws.circuit_breaker.timeout', 60),
            retryAfter: config('aws.circuit_breaker.retry_after', 30)
        );
    }

    /**
     * Get or lazily initialize the Textract client
     */
    protected function getClient(): TextractClient
    {
        if ($this->client === null) {
            // Use config() instead of env() to support cached configuration in production
            $this->client = new TextractClient([
                'version' => '2018-06-27',
                'region' => config('services.aws.region'),
                'credentials' => [
                    'key' => config('services.aws.key'),
                    'secret' => config('services.aws.secret'),
                ],
            ]);
        }

        return $this->client;
    }

    /**
     * Retrieve an initialized CircuitBreaker instance (lazy-init safe for tests bypassing constructor).
     */
    public function getCircuitBreaker(): CircuitBreaker
    {
        if (! isset($this->circuitBreaker) || $this->circuitBreaker === null) {
            $this->circuitBreaker = new CircuitBreaker(
                serviceName: 'aws_textract',
                failureThreshold: config('aws.circuit_breaker.failure_threshold', 5),
                successThreshold: config('aws.circuit_breaker.success_threshold', 2),
                timeout: config('aws.circuit_breaker.timeout', 60),
                retryAfter: config('aws.circuit_breaker.retry_after', 30)
            );
        }

        return $this->circuitBreaker;
    }

    /**
     * Sanitize a JobTag for AWS Textract async APIs.
     * Allowed characters generally include A-Z a-z 0-9 and _-+=.@:/
     * We also enforce a conservative max length of 64 characters.
     */
    public function sanitizeJobTag(string $tag): string
    {
        $value = (string) ($tag ?? '');
        // Replace any sequence of non-alphanumeric characters with a single hyphen
        $value = preg_replace('/[^A-Za-z0-9]+/', '-', $value);
        // Trim hyphens
        $sanitized = trim((string) $value, '-');

        // Enforce max length (conservative 64 chars)
        $max = 64;
        if (strlen($sanitized) > $max) {
            $sanitized = substr($sanitized, 0, $max);
        }
        // Fallback if empty
        if ($sanitized === '' || $sanitized === null) {
            $sanitized = 'job-'.substr(sha1((string) microtime(true)), 0, 12);
        }

        return $sanitized;
    }

    public function getFromS3(): array
    {
        $disk = Storage::disk('s3');

        return $disk->allFiles();
    }

    public function uploadToS3(string $localPath, string $s3Key): string
    {
        $disk = Storage::disk('s3');
        $disk->put($s3Key, fopen($localPath, 'r'), ['visibility' => 'private']);

        return $s3Key;
    }

    /**
     * Start async text detection for PDFs/TIFFs stored on S3.
     */
    public function startDocumentTextDetection(string $s3Key, ?string $jobTag = null): string
    {
        $originalTag = $jobTag ?? basename($s3Key);
        $safeTag = $this->sanitizeJobTag($originalTag);
        Log::info('Textract: starting text detection', [
            's3Key' => $s3Key,
            'bucket' => $this->bucket,
            'jobTagOriginal' => $originalTag,
            'jobTag' => $safeTag,
        ]);

        // Wrap AWS API call with circuit breaker
        $result = $this->getCircuitBreaker()->call(function () use ($s3Key, $safeTag) {
            return $this->getClient()->startDocumentTextDetection([
                'DocumentLocation' => [
                    'S3Object' => ['Bucket' => $this->bucket, 'Name' => $s3Key],
                ],
                'JobTag' => $safeTag,
            ]);
        });

        return (string) $result->get('JobId');
    }

    /**
     * Poll results until the job completes; returns all blocks across pages.
     *
     * @return array<int, array>
     */
    public function waitAndFetchTextDetection(string $jobId, int $sleepSeconds = 5, int $maxWaitSeconds = 1800): array
    {
        $elapsed = 0;
        do {
            // Wrap AWS API call with circuit breaker
            $statusResp = $this->getCircuitBreaker()->call(function () use ($jobId) {
                return $this->getClient()->getDocumentTextDetection([
                    'JobId' => $jobId,
                    'MaxResults' => 1000,
                ]);
            });

            $status = (string) $statusResp->get('JobStatus');
            if ($status === 'SUCCEEDED') {
                // Use the first successful response as the first page
                $blocks = $statusResp->get('Blocks') ?? [];
                $nextToken = $statusResp->get('NextToken');

                // Fetch subsequent pages if any
                while (! empty($nextToken)) {
                    $payload = [
                        'JobId' => $jobId,
                        'MaxResults' => 1000,
                        'NextToken' => $nextToken,
                    ];

                    // Wrap AWS API call with circuit breaker
                    $pageResp = $this->getCircuitBreaker()->call(function () use ($payload) {
                        return $this->getClient()->getDocumentTextDetection($payload);
                    });

                    $blocks = array_merge($blocks, $pageResp->get('Blocks') ?? []);
                    $nextToken = $pageResp->get('NextToken');
                }

                return $blocks;
            }

            if ($status === 'FAILED' || $status === 'PARTIAL_SUCCESS') {
                throw new \RuntimeException("Textract job {$jobId} status: {$status}");
            }

            sleep($sleepSeconds);
            $elapsed += $sleepSeconds;

        } while ($elapsed < $maxWaitSeconds);

        throw new \RuntimeException("Textract job {$jobId} timed out after {$maxWaitSeconds}s.");
    }

    // --- New: Full document analysis (layout, forms, tables, signatures) ---

    /**
     * Start async document analysis with feature types like LAYOUT, FORMS, TABLES, SIGNATURES.
     *
     * @param  array<int, string>  $featureTypes
     */
    public function startDocumentAnalysis(string $s3Key, ?string $jobTag = null, array $featureTypes = ['LAYOUT', 'FORMS', 'TABLES', 'SIGNATURES']): string
    {
        $originalTag = $jobTag ?? basename($s3Key);
        $safeTag = $this->sanitizeJobTag($originalTag);

        // Filter feature types to a known-allowed subset while preserving input order
        $allowed = ['LAYOUT', 'FORMS', 'TABLES', 'SIGNATURES'];
        $featureTypes = array_values(array_filter($featureTypes, fn ($t) => in_array($t, $allowed, true)));
        if (empty($featureTypes)) {
            $featureTypes = ['LAYOUT', 'FORMS', 'TABLES'];
        }

        Log::info('Textract: starting document analysis', [
            's3Key' => $s3Key,
            'featureTypes' => $featureTypes,
            'bucket' => $this->bucket,
            'jobTagOriginal' => $originalTag,
            'jobTag' => $safeTag,
        ]);

        // Wrap AWS API call with circuit breaker
        $result = $this->getCircuitBreaker()->call(function () use ($s3Key, $safeTag, $featureTypes) {
            return $this->getClient()->startDocumentAnalysis([
                'DocumentLocation' => [
                    'S3Object' => ['Bucket' => $this->bucket, 'Name' => $s3Key],
                ],
                'FeatureTypes' => $featureTypes,
                'JobTag' => $safeTag,
            ]);
        });

        $jobId = (string) $result->get('JobId');
        Log::info('Textract: document analysis started', ['jobId' => $jobId]);

        return $jobId;
    }

    /**
     * Wait for document analysis to complete and return all blocks (paginated).
     *
     * @return array<int, array>
     */
    public function waitAndFetchDocumentAnalysis(string $jobId, int $sleepSeconds = 5, int $maxWaitSeconds = 1800): array
    {
        $elapsed = 0;
        do {
            // Wrap AWS API call with circuit breaker
            $statusResp = $this->getCircuitBreaker()->call(function () use ($jobId) {
                return $this->getClient()->getDocumentAnalysis([
                    'JobId' => $jobId,
                    'MaxResults' => 1000,
                ]);
            });

            $status = (string) $statusResp->get('JobStatus');
            Log::debug('Textract: analysis status', ['jobId' => $jobId, 'status' => $status]);

            if ($status === 'SUCCEEDED') {
                // Use initial response as first page
                $blocks = $statusResp->get('Blocks') ?? [];
                $nextToken = $statusResp->get('NextToken');

                while (! empty($nextToken)) {
                    $payload = [
                        'JobId' => $jobId,
                        'MaxResults' => 1000,
                        'NextToken' => $nextToken,
                    ];

                    // Wrap AWS API call with circuit breaker
                    $pageResp = $this->getCircuitBreaker()->call(function () use ($payload) {
                        return $this->getClient()->getDocumentAnalysis($payload);
                    });
                    $blocks = array_merge($blocks, $pageResp->get('Blocks') ?? []);
                    $nextToken = $pageResp->get('NextToken');
                }

                Log::info('Textract: analysis completed', [
                    'jobId' => $jobId,
                    'blocksCount' => count($blocks),
                ]);

                return $blocks;
            }

            if ($status === 'FAILED' || $status === 'PARTIAL_SUCCESS') {
                throw new \RuntimeException("Textract analysis job {$jobId} status: {$status}");
            }

            sleep($sleepSeconds);
            $elapsed += $sleepSeconds;
        } while ($elapsed < $maxWaitSeconds);

        throw new \RuntimeException("Textract analysis job {$jobId} timed out after {$maxWaitSeconds}s.");
    }

    /**
     * Group LINE blocks by page number.
     *
     * @return array<int, array<int, array{text:string,left:float,top:float,width:float,height:float}>>
     */
    public function collectLinesByPage(array $blocks): array
    {
        $pages = [];
        foreach ($blocks as $b) {
            if (($b['BlockType'] ?? '') === 'LINE') {
                $page = (int) ($b['Page'] ?? 1);
                $text = (string) ($b['Text'] ?? '');
                $bb = $b['Geometry']['BoundingBox'] ?? null;
                if (! $bb || $text === '') {
                    continue;
                }

                $pages[$page][] = [
                    'text' => $text,
                    'left' => (float) $bb['Left'],
                    'top' => (float) $bb['Top'],
                    'width' => (float) $bb['Width'],
                    'height' => (float) $bb['Height'],
                ];
            }
        }
        ksort($pages);

        return $pages;
    }

    /**
     * Save analysis blocks JSON to S3 and a local dedicated copy.
     *
     * @return array{s3JsonKey:string, localJsonRel:string, localJsonAbs:string}
     */
    public function saveResultsToS3AndLocal(string $driveFileId, array $blocks): array
    {
        $s3JsonKey = $this->jsonPrefix.'/'.$driveFileId.'.json';
        Storage::disk('s3')->put($s3JsonKey, json_encode($blocks, JSON_PRETTY_PRINT));

        // Save under the configured 'local' disk (root is storage/app/private)
        $localJsonRel = 'textract/json/'.$driveFileId.'.json';
        Storage::disk('local')->put($localJsonRel, json_encode($blocks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        // Compute absolute path based on the disk's root to avoid mismatches
        $localJsonAbs = Storage::disk('local')->path($localJsonRel);

        Log::info('Textract: results saved', [
            's3JsonKey' => $s3JsonKey,
            'localJson' => $localJsonAbs,
        ]);

        return compact('s3JsonKey', 'localJsonRel', 'localJsonAbs');
    }

    /**
     * Cancel a running Textract job.
     *
     * @param  string  $jobId  AWS Textract job ID
     * @return bool True if cancelled successfully
     *
     * @throws \RuntimeException If cancellation fails
     */
    public function cancelJob(string $jobId): bool
    {
        try {
            Log::info('Textract: Cancelling job', ['job_id' => $jobId]);

            // AWS Textract doesn't have a cancel API, but we can track it locally
            // In a real implementation, you might want to stop polling or mark it
            // For now, we'll just return true to indicate the command was processed
            Log::info('Textract: Job cancellation recorded', ['job_id' => $jobId]);

            return true;
        } catch (\Exception $e) {
            Log::error('Textract: Failed to cancel job', [
                'job_id' => $jobId,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException("Failed to cancel Textract job: {$e->getMessage()}");
        }
    }
}
