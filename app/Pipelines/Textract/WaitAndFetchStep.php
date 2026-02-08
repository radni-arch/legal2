<?php

namespace App\Pipelines\Textract;

use App\Actions\Textract\WaitAndFetchTextract;
use App\Pipelines\Concerns\RetryableStep;
use Closure;
use Illuminate\Support\Facades\Log;

/**
 * Step: WaitAndFetchStep
 * Wait for Textract analysis and collect blocks.
 * Adds: blocks
 *
 * Includes timeout enforcement and retry logic.
 */
class WaitAndFetchStep
{
    use RetryableStep;

    public function handle(array $payload, Closure $next): mixed
    {
        // Skip Textract wait when using local OCR route (skip_textract)
        if ($payload['skip_textract'] ?? false) {
            return $next($payload);
        }

        $maxWaitSeconds = (int) config('textract.max_wait_seconds', 600);
        $startTime = time();

        $payload['blocks'] = $this->withRetry(function () use ($payload, $maxWaitSeconds, $startTime) {
            $elapsed = time() - $startTime;
            if ($elapsed >= $maxWaitSeconds) {
                throw new \RuntimeException(
                    "Textract analysis timed out after {$elapsed}s (limit: {$maxWaitSeconds}s)"
                );
            }

            // Also check overall pipeline timeout if set
            $pipelineStart = $payload['pipeline_start'] ?? null;
            $pipelineTimeout = $payload['pipeline_timeout'] ?? null;
            if ($pipelineStart && $pipelineTimeout) {
                $pipelineElapsed = time() - $pipelineStart;
                if ($pipelineElapsed >= $pipelineTimeout) {
                    throw new \RuntimeException(
                        "Pipeline timeout exceeded: {$pipelineElapsed}s (limit: {$pipelineTimeout}s)"
                    );
                }
            }

            return WaitAndFetchTextract::run($payload['jobId']);
        }, 'textract_wait_and_fetch');

        Log::info('WaitAndFetchStep: blocks received', [
            'jobId' => $payload['jobId'],
            'block_count' => count($payload['blocks']),
            'elapsed_seconds' => time() - $startTime,
        ]);

        return $next($payload);
    }
}
