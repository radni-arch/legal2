<?php

namespace App\Pipelines\Concerns;

use Illuminate\Support\Facades\Log;

trait RetryableStep
{
    public int $maxRetries = 3;
    public int $retryDelayMs = 1000;

    /**
     * Execute a callable with retry logic and exponential backoff.
     *
     * @throws \Throwable After exhausting all retries
     */
    protected function withRetry(callable $operation, string $operationName): mixed
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                return $operation();
            } catch (\Throwable $e) {
                $lastException = $e;

                if ($attempt >= $this->maxRetries) {
                    Log::error("RetryableStep: {$operationName} failed after {$attempt} attempts", [
                        'error' => $e->getMessage(),
                        'step' => static::class,
                    ]);
                    break;
                }

                $delayMs = $this->retryDelayMs * (2 ** ($attempt - 1));
                Log::warning("RetryableStep: {$operationName} attempt {$attempt} failed, retrying in {$delayMs}ms", [
                    'error' => $e->getMessage(),
                    'step' => static::class,
                ]);

                if ($delayMs > 0) {
                    usleep($delayMs * 1000);
                }
            }
        }

        throw $lastException;
    }
}
