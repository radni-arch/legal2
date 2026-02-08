<?php

namespace App\Pipelines\Textract;

use App\Actions\Textract\StartTextractAnalysis;
use App\Pipelines\Concerns\RetryableStep;
use Closure;

/**
 * Step: StartAnalysisStep
 * Start Textract analysis job.
 * Adds: jobId
 */
class StartAnalysisStep
{
    use RetryableStep;

    public function handle(array $payload, Closure $next): mixed
    {
        // Skip Textract analysis when using local OCR route (skip_textract)
        if ($payload['skip_textract'] ?? false) {
            return $next($payload);
        }

        $featureTypes = $payload['featureTypes'] ?? ['LAYOUT', 'FORMS', 'TABLES', 'SIGNATURES'];
        $payload['jobId'] = $this->withRetry(
            fn () => StartTextractAnalysis::run($payload['s3Key'], $payload['driveFileName'], $featureTypes),
            'textract_start_analysis'
        );
        $payload['job']->update(['job_id' => $payload['jobId'], 'status' => 'analyzing']);

        return $next($payload);
    }
}
