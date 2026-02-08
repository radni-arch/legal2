<?php

namespace App\Services\AI;

use App\Contracts\AI\EmbeddingServiceInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * OpenAI Embedding Service
 *
 * Extracted from OpenAIService.php to handle embedding generation.
 * Implements EmbeddingServiceInterface for standardized embedding operations.
 *
 * Responsibilities:
 * - Single text embedding generation
 * - Batch text embedding generation
 * - Model dimension mapping
 * - Retry logic for transient failures
 * - Request/response logging
 */
class OpenAIEmbeddingService implements EmbeddingServiceInterface
{
    protected string $apiKey;

    protected ?string $organization;

    protected ?string $project;

    protected string $baseUrl;

    protected int $timeout;

    protected int $connectTimeout;

    protected int $retryTimes;

    protected int $retrySleepMs;

    protected string $defaultModel;

    /**
     * Model dimension mapping
     */
    protected array $modelDimensions = [
        'text-embedding-3-small' => 1536,
        'text-embedding-3-large' => 3072,
        'text-embedding-ada-002' => 1536,
    ];

    public function __construct()
    {
        $this->apiKey = (string) config('openai.api_key');
        $this->organization = config('openai.organization');
        $this->project = config('openai.project');
        $this->baseUrl = rtrim((string) config('openai.base_url', 'https://api.openai.com/v1'), '/');
        $this->timeout = (int) config('openai.timeout', 60);
        $this->connectTimeout = (int) config('openai.connect_timeout', 10);
        $this->retryTimes = (int) config('openai.retry.times', 2);
        $this->retrySleepMs = (int) config('openai.retry.sleep_ms', 200);
        $this->defaultModel = (string) config('openai.models.embeddings', 'text-embedding-3-small');

        if (! $this->apiKey) {
            throw new \RuntimeException('OPENAI_API_KEY is not configured.');
        }
    }

    /**
     * Generate embedding for text
     *
     * @param  string  $text  Text to embed
     * @param  string  $model  Embedding model
     * @return array Embedding vector
     *
     * @throws Throwable
     */
    public function embed(string $text, string $model = 'text-embedding-3-small'): array
    {
        if ($model === 'text-embedding-3-small' && $model !== $this->defaultModel) {
            $model = $this->defaultModel;
        }

        $payload = [
            'model' => $model,
            'input' => $text,
        ];

        $response = $this->sendRequest($payload);

        // Extract embedding from response
        if (! isset($response['data'][0]['embedding'])) {
            throw new \Exception('Invalid embedding response structure');
        }

        $embedding = $response['data'][0]['embedding'];

        if (empty($embedding)) {
            throw new \Exception('Embedding vector is empty');
        }

        return $embedding;
    }

    /**
     * Generate embeddings for multiple texts
     *
     * @param  array  $texts  Array of texts
     * @return array Array of embedding vectors
     *
     * @throws Throwable
     */
    public function batchEmbed(array $texts, string $model = 'text-embedding-3-small'): array
    {
        if (empty($texts)) {
            return [];
        }

        if ($model === 'text-embedding-3-small' && $model !== $this->defaultModel) {
            $model = $this->defaultModel;
        }

        $payload = [
            'model' => $model,
            'input' => $texts,
        ];

        $response = $this->sendRequest($payload);

        // Extract embeddings from response, preserving order
        if (! isset($response['data'])) {
            throw new \Exception('Invalid embedding response structure');
        }

        $embeddings = [];
        foreach ($response['data'] as $item) {
            if (! isset($item['embedding']) || ! isset($item['index'])) {
                throw new \Exception('Invalid embedding item structure');
            }
            $embeddings[$item['index']] = $item['embedding'];
        }

        // Sort by index to preserve order
        ksort($embeddings);

        return array_values($embeddings);
    }

    /**
     * Get embedding dimensions for a model
     *
     * @return int Dimension count (e.g., 1536)
     */
    public function getEmbeddingDimensions(string $model): int
    {
        return $this->modelDimensions[$model] ?? 1536;
    }

    /**
     * Send request to OpenAI embeddings endpoint with retry logic
     *
     *
     * @throws Throwable
     */
    protected function sendRequest(array $payload): array
    {
        $reqId = (string) Str::uuid();
        $start = microtime(true);

        $this->log('info', 'openai.embeddings.request', [
            'request_id' => $reqId,
            'model' => $payload['model'],
            'input_type' => is_array($payload['input']) ? 'batch' : 'single',
            'input_count' => is_array($payload['input']) ? count($payload['input']) : 1,
        ]);

        $attempt = 0;
        $lastException = null;

        while ($attempt <= $this->retryTimes) {
            try {
                $response = $this->client()
                    ->post('/embeddings', $payload);

                $duration = (int) round((microtime(true) - $start) * 1000);

                if ($response->failed()) {
                    $this->log('error', 'openai.embeddings.error', [
                        'request_id' => $reqId,
                        'attempt' => $attempt + 1,
                        'duration_ms' => $duration,
                        'status' => $response->status(),
                        'error' => $response->json()['error'] ?? $response->body(),
                    ]);

                    $response->throw();
                }

                $json = $response->json();

                if (! $json) {
                    throw new \Exception('Malformed response from OpenAI API: unable to parse JSON');
                }

                $this->log('info', 'openai.embeddings.response', [
                    'request_id' => $reqId,
                    'attempt' => $attempt + 1,
                    'duration_ms' => $duration,
                    'status' => $response->status(),
                    'model' => $json['model'] ?? null,
                    'usage' => $json['usage'] ?? null,
                ]);

                return $json;
            } catch (Throwable $e) {
                $lastException = $e;
                $attempt++;

                $duration = (int) round((microtime(true) - $start) * 1000);

                $this->log('error', 'openai.embeddings.error', [
                    'request_id' => $reqId,
                    'attempt' => $attempt,
                    'duration_ms' => $duration,
                    'error' => [
                        'message' => $e->getMessage(),
                        'code' => method_exists($e, 'getCode') ? $e->getCode() : 0,
                        'class' => get_class($e),
                    ],
                ]);

                // Don't retry on certain errors
                if ($e instanceof RequestException && $e->response) {
                    $status = $e->response->status();
                    // Don't retry on 4xx errors except 429 (rate limit)
                    if ($status >= 400 && $status < 500 && $status !== 429) {
                        throw $e;
                    }
                }

                // If we have retries left and this is a retryable error, sleep and continue
                if ($attempt <= $this->retryTimes) {
                    usleep($this->retrySleepMs * 1000);

                    continue;
                }

                // No retries left, throw
                throw $e;
            }
        }

        // This should never be reached, but just in case
        throw $lastException ?? new \Exception('Unknown error in embedding request');
    }

    /**
     * Create HTTP client with proper headers
     */
    protected function client(): PendingRequest
    {
        $headers = [
            'Authorization' => 'Bearer '.$this->apiKey,
            'Content-Type' => 'application/json',
        ];

        if ($this->organization) {
            $headers['OpenAI-Organization'] = $this->organization;
        }

        if ($this->project) {
            $headers['OpenAI-Project'] = $this->project;
        }

        return Http::withHeaders($headers)
            ->baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->connectTimeout($this->connectTimeout);
    }

    /**
     * Log message to openai channel
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        try {
            // Try to use openai channel, fallback to default if not available
            Log::channel('openai')->{$level}($message, $context);
        } catch (\Throwable $e) {
            // Fallback to default logger if openai channel not available
            try {
                Log::{$level}($message, $context);
            } catch (\Throwable $e2) {
                // Silently fail in tests if logging is not available
            }
        }
    }
}
