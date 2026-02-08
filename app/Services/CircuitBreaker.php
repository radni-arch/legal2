<?php

namespace App\Services;

use App\Contracts\CircuitBreakerInterface;
use App\Events\CircuitBreaker\CircuitBreakerClosed;
use App\Events\CircuitBreaker\CircuitBreakerFailure;
use App\Events\CircuitBreaker\CircuitBreakerHalfOpened;
use App\Events\CircuitBreaker\CircuitBreakerOpened;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Circuit Breaker Pattern Implementation
 *
 * Prevents cascading failures by monitoring external API calls and
 * temporarily blocking requests when a service is failing.
 *
 * States:
 * - CLOSED: Normal operation, requests pass through
 * - OPEN: Service is failing, requests are blocked immediately
 * - HALF_OPEN: Testing if service has recovered
 *
 * Hardened with comprehensive error handling and performance monitoring
 */
class CircuitBreaker implements CircuitBreakerInterface
{
    private const STATE_CLOSED = 'closed';

    private const STATE_OPEN = 'open';

    private const STATE_HALF_OPEN = 'half_open';

    private string $serviceName;

    private int $failureThreshold;

    private int $successThreshold;

    private int $timeout;

    private int $retryAfter;

    private ?array $lastError = null;

    /**
     * Create a new Circuit Breaker instance
     *
     * @param  string  $serviceName  Unique identifier for the service
     * @param  int  $failureThreshold  Number of failures before opening circuit (default: 5)
     * @param  int  $successThreshold  Number of successes in half-open to close circuit (default: 2)
     * @param  int  $timeout  Seconds to wait before trying again (default: 60)
     * @param  int  $retryAfter  Seconds to retry after in half-open state (default: 30)
     */
    public function __construct(
        string $serviceName,
        int $failureThreshold = 5,
        int $successThreshold = 2,
        int $timeout = 60,
        int $retryAfter = 30
    ) {
        $this->serviceName = $serviceName;
        $this->failureThreshold = $failureThreshold;
        $this->successThreshold = $successThreshold;
        $this->timeout = $timeout;
        $this->retryAfter = $retryAfter;

        Log::debug('Circuit breaker initialized', [
            'service' => $serviceName,
            'failure_threshold' => $failureThreshold,
            'success_threshold' => $successThreshold,
            'timeout' => $timeout,
            'retry_after' => $retryAfter,
        ]);
    }

    /**
     * Execute a callable with circuit breaker protection
     *
     * @param  callable  $callback  The operation to execute
     * @return mixed The result of the callback
     *
     * @throws CircuitBreakerException If circuit is open
     * @throws \Throwable If the callback throws an exception in closed/half-open state
     */
    public function call(callable $callback)
    {
        $startTime = microtime(true);
        $callbackException = null;

        Log::info('Circuit breaker call initiated', [
            'service' => $this->serviceName,
        ]);

        try {
            $state = $this->getState();

            Log::debug('Circuit breaker state checked', [
                'service' => $this->serviceName,
                'state' => $state,
            ]);

            if ($state === self::STATE_OPEN) {
                $resetCheckStart = microtime(true);

                if ($this->shouldAttemptReset()) {
                    $resetCheckDuration = microtime(true) - $resetCheckStart;

                    try {
                        $this->setState(self::STATE_HALF_OPEN);

                        Log::info('Circuit breaker entering half-open state', [
                            'service' => $this->serviceName,
                            'reset_check_duration_ms' => round($resetCheckDuration * 1000, 2),
                        ]);
                    } catch (\Throwable $e) {
                        Log::error('Failed to transition circuit to half-open', [
                            'service' => $this->serviceName,
                            'error' => $e->getMessage(),
                            'error_class' => get_class($e),
                            'trace' => $e->getTraceAsString(),
                        ]);

                        throw new CircuitBreakerException(
                            "Circuit state transition failed for {$this->serviceName}: {$e->getMessage()}",
                            CircuitBreakerException::STATE_TRANSITION_FAILED,
                            $e
                        );
                    }
                } else {
                    $resetCheckDuration = microtime(true) - $resetCheckStart;

                    $this->logCircuitOpen();
                    $totalDuration = microtime(true) - $startTime;

                    Log::warning('Circuit breaker blocked call', [
                        'service' => $this->serviceName,
                        'state' => 'open',
                        'reset_check_duration_ms' => round($resetCheckDuration * 1000, 2),
                        'total_duration_ms' => round($totalDuration * 1000, 2),
                    ]);

                    throw new CircuitBreakerException(
                        "Circuit breaker is OPEN for service: {$this->serviceName}. Service is currently unavailable.",
                        CircuitBreakerException::CIRCUIT_OPEN
                    );
                }
            }

            // Execute callback
            $callbackStart = microtime(true);

            try {
                $result = $callback();
                $callbackDuration = microtime(true) - $callbackStart;

                Log::info('Circuit breaker callback executed successfully', [
                    'service' => $this->serviceName,
                    'callback_duration_ms' => round($callbackDuration * 1000, 2),
                ]);

                $this->onSuccess();
                $totalDuration = microtime(true) - $startTime;

                Log::info('Circuit breaker call completed successfully', [
                    'service' => $this->serviceName,
                    'total_duration_ms' => round($totalDuration * 1000, 2),
                ]);

                return $result;
            } catch (\Throwable $e) {
                $callbackDuration = microtime(true) - $callbackStart;
                $callbackException = $e;

                // Client errors (4xx) are not service failures - don't count
                // them toward the circuit breaker failure threshold
                if ($this->isClientError($e)) {
                    Log::debug('Circuit breaker ignoring client error', [
                        'service' => $this->serviceName,
                        'error' => $e->getMessage(),
                        'error_class' => get_class($e),
                        'callback_duration_ms' => round($callbackDuration * 1000, 2),
                    ]);

                    throw $e;
                }

                Log::error('Circuit breaker callback failed', [
                    'service' => $this->serviceName,
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'callback_duration_ms' => round($callbackDuration * 1000, 2),
                    'trace' => $e->getTraceAsString(),
                ]);

                $this->onFailure($e);

                throw $e;
            }
        } catch (CircuitBreakerException $e) {
            // Re-throw circuit breaker exceptions
            throw $e;
        } catch (\Throwable $e) {
            // If this exception originated from the callback, re-throw it
            // directly to preserve the original exception type for callers
            if ($callbackException === $e) {
                throw $e;
            }

            $totalDuration = microtime(true) - $startTime;

            Log::error('Circuit breaker call failed with unexpected error', [
                'service' => $this->serviceName,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'total_duration_ms' => round($totalDuration * 1000, 2),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new CircuitBreakerException(
                "Unexpected error in circuit breaker for {$this->serviceName}: {$e->getMessage()}",
                CircuitBreakerException::UNEXPECTED_ERROR,
                $e
            );
        }
    }

    /**
     * Handle successful request
     */
    private function onSuccess(): void
    {
        $startTime = microtime(true);

        try {
            $state = $this->getState();

            if ($state === self::STATE_HALF_OPEN) {
                $successCount = $this->incrementSuccessCount();

                if ($successCount >= $this->successThreshold) {
                    $this->setState(self::STATE_CLOSED);
                    $this->resetCounts();

                    $duration = microtime(true) - $startTime;

                    Log::info('Circuit breaker closed after successful recovery', [
                        'service' => $this->serviceName,
                        'success_count' => $successCount,
                        'success_threshold' => $this->successThreshold,
                        'duration_ms' => round($duration * 1000, 2),
                    ]);
                } else {
                    Log::debug('Circuit breaker success in half-open state', [
                        'service' => $this->serviceName,
                        'success_count' => $successCount,
                        'success_threshold' => $this->successThreshold,
                    ]);
                }
            } elseif ($state === self::STATE_CLOSED) {
                // Reset failure count on success in closed state
                $this->resetFailureCount();

                Log::debug('Circuit breaker success in closed state, failure count reset', [
                    'service' => $this->serviceName,
                ]);
            }
        } catch (\Throwable $e) {
            $duration = microtime(true) - $startTime;

            Log::error('Circuit breaker onSuccess handler failed', [
                'service' => $this->serviceName,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'duration_ms' => round($duration * 1000, 2),
                'trace' => $e->getTraceAsString(),
            ]);

            // Don't throw - success handler failure shouldn't block the request
        }
    }

    /**
     * Handle failed request
     */
    private function onFailure(\Throwable $e): void
    {
        $startTime = microtime(true);

        try {
            // Capture error details for event dispatching
            $this->lastError = [
                'message' => $e->getMessage(),
                'class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];

            $failureCount = $this->incrementFailureCount();
            $state = $this->getState();

            Log::warning('Circuit breaker recorded failure', [
                'service' => $this->serviceName,
                'failure_count' => $failureCount,
                'failure_threshold' => $this->failureThreshold,
                'state' => $state,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
            ]);

            // State transitions MUST happen before event dispatch to ensure
            // circuit breaker opens even if event listeners throw
            if ($state === self::STATE_HALF_OPEN) {
                // Failure in half-open state immediately opens circuit
                $this->setState(self::STATE_OPEN);
                $this->setLastFailureTime();

                $duration = microtime(true) - $startTime;

                Log::error('Circuit breaker opened from half-open state', [
                    'service' => $this->serviceName,
                    'error' => $e->getMessage(),
                    'duration_ms' => round($duration * 1000, 2),
                ]);
            } elseif ($state === self::STATE_CLOSED && $failureCount >= $this->failureThreshold) {
                // Too many failures in closed state, open circuit
                $this->setState(self::STATE_OPEN);
                $this->setLastFailureTime();

                $duration = microtime(true) - $startTime;

                Log::error('Circuit breaker opened due to failure threshold', [
                    'service' => $this->serviceName,
                    'failure_count' => $failureCount,
                    'failure_threshold' => $this->failureThreshold,
                    'duration_ms' => round($duration * 1000, 2),
                ]);
            }

            // Dispatch failure event after state transition (non-critical)
            // Use positional args - named args may not be forwarded by Dispatchable::dispatch()
            try {
                CircuitBreakerFailure::dispatch(
                    $this->serviceName,
                    $failureCount,
                    $this->failureThreshold,
                    $state,
                    $this->lastError,
                    now()
                );
            } catch (\Throwable $eventEx) {
                Log::warning('Circuit breaker failure event dispatch failed', [
                    'service' => $this->serviceName,
                    'error' => $eventEx->getMessage(),
                ]);
            }
        } catch (\Throwable $ex) {
            $duration = microtime(true) - $startTime;

            Log::error('Circuit breaker onFailure handler failed', [
                'service' => $this->serviceName,
                'original_error' => $e->getMessage(),
                'handler_error' => $ex->getMessage(),
                'handler_error_class' => get_class($ex),
                'duration_ms' => round($duration * 1000, 2),
                'trace' => $ex->getTraceAsString(),
            ]);

            // Don't throw - failure handler failure shouldn't block error propagation
        }
    }

    /**
     * Check if circuit should attempt to reset from open to half-open
     */
    private function shouldAttemptReset(): bool
    {
        try {
            $lastFailureTime = $this->getLastFailureTime();

            if ($lastFailureTime === null) {
                Log::debug('No last failure time, allowing reset attempt', [
                    'service' => $this->serviceName,
                ]);

                return true;
            }

            $elapsedTime = time() - $lastFailureTime;
            $shouldReset = $elapsedTime >= $this->timeout;

            Log::debug('Reset attempt check', [
                'service' => $this->serviceName,
                'elapsed_seconds' => $elapsedTime,
                'timeout_seconds' => $this->timeout,
                'should_reset' => $shouldReset,
            ]);

            return $shouldReset;
        } catch (\Throwable $e) {
            Log::error('Failed to check if circuit should reset', [
                'service' => $this->serviceName,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            // Default to not attempting reset on error
            return false;
        }
    }

    /**
     * Get current circuit state
     */
    private function getState(): string
    {
        try {
            return Cache::get($this->getStateKey(), self::STATE_CLOSED);
        } catch (\Throwable $e) {
            Log::error('Failed to get circuit breaker state from cache', [
                'service' => $this->serviceName,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            // Default to closed state on cache failure
            return self::STATE_CLOSED;
        }
    }

    /**
     * Set circuit state
     */
    private function setState(string $state): void
    {
        try {
            $oldState = $this->getState();

            $ttl = match ($state) {
                self::STATE_OPEN => $this->timeout + 3600, // Keep open state for timeout + 1 hour
                self::STATE_HALF_OPEN => $this->retryAfter + 600, // Keep half-open for retry + 10 minutes
                default => 3600, // Keep closed state for 1 hour
            };

            Cache::put($this->getStateKey(), $state, $ttl);

            Log::debug('Circuit breaker state updated', [
                'service' => $this->serviceName,
                'old_state' => $oldState,
                'new_state' => $state,
                'ttl_seconds' => $ttl,
            ]);

            // Dispatch events for state transitions
            $this->dispatchStateChangeEvent($state, $oldState);
        } catch (\Throwable $e) {
            Log::error('Failed to set circuit breaker state in cache', [
                'service' => $this->serviceName,
                'attempted_state' => $state,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new CircuitBreakerException(
                "Failed to update circuit state for {$this->serviceName}: {$e->getMessage()}",
                CircuitBreakerException::CACHE_ACCESS_FAILED,
                $e
            );
        }
    }

    /**
     * Dispatch event for state change
     */
    private function dispatchStateChangeEvent(string $newState, string $oldState): void
    {
        try {
            // Only dispatch if state actually changed
            if ($newState === $oldState) {
                return;
            }

            // Use positional args - named args may not be forwarded by Dispatchable::dispatch()
            match ($newState) {
                self::STATE_OPEN => CircuitBreakerOpened::dispatch(
                    $this->serviceName,
                    (int) Cache::get($this->getFailureCountKey(), 0),
                    now(),
                    $this->lastError ?? [],
                    $this->timeout
                ),
                self::STATE_CLOSED => CircuitBreakerClosed::dispatch(
                    $this->serviceName,
                    (int) Cache::get($this->getSuccessCountKey(), 0),
                    now()
                ),
                self::STATE_HALF_OPEN => CircuitBreakerHalfOpened::dispatch(
                    $this->serviceName,
                    now(),
                    $this->successThreshold
                ),
                default => null
            };

            Log::debug('Circuit breaker state change event dispatched', [
                'service' => $this->serviceName,
                'old_state' => $oldState,
                'new_state' => $newState,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch circuit breaker state change event', [
                'service' => $this->serviceName,
                'new_state' => $newState,
                'old_state' => $oldState,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
            ]);
            // Don't throw - event dispatch failure shouldn't block state transition
        }
    }

    /**
     * Increment failure count
     */
    private function incrementFailureCount(): int
    {
        try {
            $key = $this->getFailureCountKey();
            $count = (int) Cache::get($key, 0) + 1;
            Cache::put($key, $count, 3600); // Keep for 1 hour

            return $count;
        } catch (\Throwable $e) {
            Log::error('Failed to increment failure count', [
                'service' => $this->serviceName,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return 1 to indicate at least this failure
            return 1;
        }
    }

    /**
     * Reset failure count
     */
    private function resetFailureCount(): void
    {
        try {
            Cache::forget($this->getFailureCountKey());
        } catch (\Throwable $e) {
            Log::error('Failed to reset failure count', [
                'service' => $this->serviceName,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Increment success count (in half-open state)
     */
    private function incrementSuccessCount(): int
    {
        try {
            $key = $this->getSuccessCountKey();
            $count = (int) Cache::get($key, 0) + 1;
            Cache::put($key, $count, 600); // Keep for 10 minutes

            return $count;
        } catch (\Throwable $e) {
            Log::error('Failed to increment success count', [
                'service' => $this->serviceName,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return 1 to indicate at least this success
            return 1;
        }
    }

    /**
     * Reset all counts
     */
    private function resetCounts(): void
    {
        try {
            Cache::forget($this->getFailureCountKey());
            Cache::forget($this->getSuccessCountKey());

            Log::debug('Circuit breaker counts reset', [
                'service' => $this->serviceName,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to reset counts', [
                'service' => $this->serviceName,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Set last failure timestamp
     */
    private function setLastFailureTime(): void
    {
        try {
            Cache::put($this->getLastFailureTimeKey(), time(), $this->timeout + 3600);
        } catch (\Throwable $e) {
            Log::error('Failed to set last failure time', [
                'service' => $this->serviceName,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Get last failure timestamp
     */
    private function getLastFailureTime(): ?int
    {
        try {
            return Cache::get($this->getLastFailureTimeKey());
        } catch (\Throwable $e) {
            Log::error('Failed to get last failure time', [
                'service' => $this->serviceName,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Determine if an exception represents an HTTP client error (4xx).
     *
     * Client errors indicate a problem with the request, not the service,
     * so they should not count toward the circuit breaker failure threshold.
     */
    private function isClientError(\Throwable $e): bool
    {
        if ($e instanceof RequestException) {
            $status = $e->response?->status();

            return $status !== null && $status >= 400 && $status < 500;
        }

        return false;
    }

    /**
     * Log circuit open event
     */
    private function logCircuitOpen(): void
    {
        try {
            $lastFailureTime = $this->getLastFailureTime();
            $waitTime = $lastFailureTime ? ($this->timeout - (time() - $lastFailureTime)) : 0;

            Log::warning('Circuit breaker blocked request', [
                'service' => $this->serviceName,
                'state' => 'open',
                'wait_seconds' => max(0, $waitTime),
                'timeout_seconds' => $this->timeout,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to log circuit open event', [
                'service' => $this->serviceName,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
            ]);
        }
    }

    /**
     * Get cache key for state
     */
    private function getStateKey(): string
    {
        return "circuit_breaker:{$this->serviceName}:state";
    }

    /**
     * Get cache key for failure count
     */
    private function getFailureCountKey(): string
    {
        return "circuit_breaker:{$this->serviceName}:failures";
    }

    /**
     * Get cache key for success count
     */
    private function getSuccessCountKey(): string
    {
        return "circuit_breaker:{$this->serviceName}:successes";
    }

    /**
     * Get cache key for last failure time
     */
    private function getLastFailureTimeKey(): string
    {
        return "circuit_breaker:{$this->serviceName}:last_failure";
    }

    /**
     * Get current circuit breaker status
     *
     * @return array Status information
     */
    public function getStatus(): array
    {
        $startTime = microtime(true);

        Log::info('Retrieving circuit breaker status', [
            'service' => $this->serviceName,
        ]);

        try {
            $state = $this->getState();
            $failureCount = (int) Cache::get($this->getFailureCountKey(), 0);
            $successCount = (int) Cache::get($this->getSuccessCountKey(), 0);
            $lastFailureTime = $this->getLastFailureTime();

            $status = [
                'service' => $this->serviceName,
                'state' => $state,
                'failure_count' => $failureCount,
                'success_count' => $successCount,
                'failure_threshold' => $this->failureThreshold,
                'success_threshold' => $this->successThreshold,
                'last_failure' => $lastFailureTime ? date('Y-m-d H:i:s', $lastFailureTime) : null,
                'can_retry' => $state === self::STATE_CLOSED || $this->shouldAttemptReset(),
            ];

            $duration = microtime(true) - $startTime;

            Log::info('Circuit breaker status retrieved', [
                'service' => $this->serviceName,
                'state' => $state,
                'duration_ms' => round($duration * 1000, 2),
            ]);

            return $status;
        } catch (\Throwable $e) {
            $duration = microtime(true) - $startTime;

            Log::error('Failed to retrieve circuit breaker status', [
                'service' => $this->serviceName,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'duration_ms' => round($duration * 1000, 2),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new CircuitBreakerException(
                "Failed to retrieve status for {$this->serviceName}: {$e->getMessage()}",
                CircuitBreakerException::STATUS_RETRIEVAL_FAILED,
                $e
            );
        }
    }

    /**
     * Manually reset the circuit breaker to closed state
     */
    public function reset(): void
    {
        $startTime = microtime(true);

        Log::info('Manually resetting circuit breaker', [
            'service' => $this->serviceName,
        ]);

        try {
            $this->setState(self::STATE_CLOSED);
            $this->resetCounts();
            Cache::forget($this->getLastFailureTimeKey());

            $duration = microtime(true) - $startTime;

            Log::info('Circuit breaker manually reset', [
                'service' => $this->serviceName,
                'duration_ms' => round($duration * 1000, 2),
            ]);
        } catch (\Throwable $e) {
            $duration = microtime(true) - $startTime;

            Log::error('Failed to manually reset circuit breaker', [
                'service' => $this->serviceName,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'duration_ms' => round($duration * 1000, 2),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new CircuitBreakerException(
                "Failed to reset circuit breaker for {$this->serviceName}: {$e->getMessage()}",
                CircuitBreakerException::RESET_FAILED,
                $e
            );
        }
    }
}

/**
 * Exception thrown when circuit breaker is open or encounters errors
 */
class CircuitBreakerException extends \Exception
{
    public const CIRCUIT_OPEN = 4001;

    public const STATE_TRANSITION_FAILED = 4002;

    public const CACHE_ACCESS_FAILED = 4003;

    public const STATUS_RETRIEVAL_FAILED = 4004;

    public const RESET_FAILED = 4005;

    public const UNEXPECTED_ERROR = 4999;
}
