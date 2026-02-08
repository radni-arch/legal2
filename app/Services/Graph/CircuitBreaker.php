<?php

namespace App\Services\Graph;

use Illuminate\Support\Facades\Cache;

class CircuitBreaker
{
    private string $name;
    private int $failureThreshold;
    private int $recoveryTimeout;
    private int $failureCount = 0;
    private ?int $lastFailureTime = null;
    private string $state = 'closed'; // closed, open, half-open
    private bool $useCache;

    /**
     * Create a new circuit breaker instance.
     *
     * @param string $name Unique identifier for this circuit breaker
     * @param int $failureThreshold Number of consecutive failures before opening
     * @param int $recoveryTimeout Seconds to wait before attempting recovery
     * @param bool $useCache Whether to persist state in cache (default: false, memory-only)
     */
    public function __construct(
        string $name,
        int $failureThreshold = 5,
        int $recoveryTimeout = 30,
        bool $useCache = false
    ) {
        $this->name = $name;
        $this->failureThreshold = $failureThreshold;
        $this->recoveryTimeout = $recoveryTimeout;
        $this->useCache = $useCache;

        // Load state from cache if enabled
        if ($this->useCache) {
            $this->loadStateFromCache();
        }
    }

    /**
     * Execute an operation through the circuit breaker.
     *
     * @param callable $operation The operation to execute
     * @return mixed The result of the operation
     * @throws CircuitBreakerOpenException If circuit is open
     * @throws \Exception If the operation fails
     */
    public function call(callable $operation)
    {
        if ($this->isOpen()) {
            throw new CircuitBreakerOpenException(
                "Circuit breaker '{$this->name}' is open"
            );
        }

        try {
            $result = $operation();
            $this->recordSuccess();
            return $result;
        } catch (\Exception $e) {
            $this->recordFailure();
            throw $e;
        }
    }

    /**
     * Get the current state of the circuit breaker.
     *
     * @return string One of: closed, open, half-open
     */
    public function getState(): string
    {
        // Check if we should transition from open to half-open
        if ($this->state === 'open' && $this->shouldAttemptRecovery()) {
            $this->state = 'half-open';
        }

        return $this->state;
    }

    /**
     * Get the circuit breaker name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the failure threshold.
     *
     * @return int
     */
    public function getFailureThreshold(): int
    {
        return $this->failureThreshold;
    }

    /**
     * Get the recovery timeout in seconds.
     *
     * @return int
     */
    public function getRecoveryTimeout(): int
    {
        return $this->recoveryTimeout;
    }

    /**
     * Check if the circuit is currently open.
     *
     * @return bool
     */
    private function isOpen(): bool
    {
        if ($this->state === 'open') {
            // Check if we should transition to half-open
            if ($this->shouldAttemptRecovery()) {
                $this->state = 'half-open';
                return false; // Allow the next request through
            }
            return true;
        }

        return false;
    }

    /**
     * Determine if enough time has passed to attempt recovery.
     *
     * @return bool
     */
    private function shouldAttemptRecovery(): bool
    {
        if ($this->lastFailureTime === null) {
            return false;
        }

        $elapsedTime = time() - $this->lastFailureTime;
        return $elapsedTime >= $this->recoveryTimeout;
    }

    /**
     * Record a successful operation.
     */
    private function recordSuccess(): void
    {
        // If we were in half-open state, successful request closes the circuit
        if ($this->state === 'half-open') {
            $this->state = 'closed';
        }

        // Reset failure tracking
        $this->failureCount = 0;
        $this->lastFailureTime = null;

        // Persist state if cache enabled
        if ($this->useCache) {
            $this->saveStateToCache();
        }
    }

    /**
     * Record a failed operation.
     */
    private function recordFailure(): void
    {
        $this->failureCount++;
        $this->lastFailureTime = time();

        // If in half-open state, any failure reopens the circuit
        if ($this->state === 'half-open') {
            $this->state = 'open';
            if ($this->useCache) {
                $this->saveStateToCache();
            }
            return;
        }

        // If threshold reached, open the circuit
        if ($this->failureCount >= $this->failureThreshold) {
            $this->state = 'open';
        }

        // Persist state if cache enabled
        if ($this->useCache) {
            $this->saveStateToCache();
        }
    }

    /**
     * Get cache key for this circuit breaker's state.
     *
     * @return string
     */
    private function getCacheKey(): string
    {
        return "circuit_breaker:{$this->name}:state";
    }

    /**
     * Load state from cache.
     */
    private function loadStateFromCache(): void
    {
        $cached = Cache::get($this->getCacheKey());

        if ($cached === null) {
            return;
        }

        $this->state = $cached['state'] ?? 'closed';
        $this->failureCount = $cached['failure_count'] ?? 0;
        $this->lastFailureTime = $cached['last_failure_time'] ?? null;
    }

    /**
     * Save state to cache.
     */
    private function saveStateToCache(): void
    {
        $ttl = max($this->recoveryTimeout * 2, 300); // At least 5 minutes

        Cache::put($this->getCacheKey(), [
            'state' => $this->state,
            'failure_count' => $this->failureCount,
            'last_failure_time' => $this->lastFailureTime,
        ], $ttl);
    }
}
