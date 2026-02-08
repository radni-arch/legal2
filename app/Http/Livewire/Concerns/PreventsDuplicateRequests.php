<?php

namespace App\Http\Livewire\Concerns;

use Illuminate\Support\Facades\Cache;

/**
 * PreventsDuplicateRequests Trait
 *
 * Prevents race conditions by detecting and blocking duplicate concurrent requests.
 *
 * Usage:
 * ```php
 * use PreventsDuplicateRequests;
 *
 * public function expensiveAction($param1, $param2)
 * {
 *     if ($this->isDuplicateRequest('expensiveAction', [$param1, $param2])) {
 *         return; // Block duplicate request
 *     }
 *
 *     try {
 *         // Your action logic here
 *     } finally {
 *         $this->clearRequestFingerprint('expensiveAction', [$param1, $param2]);
 *     }
 * }
 * ```
 *
 * Or use the convenience method:
 * ```php
 * public function expensiveAction($param1, $param2)
 * {
 *     return $this->preventDuplicate('expensiveAction', [$param1, $param2], function() use ($param1, $param2) {
 *         // Your action logic here
 *         return $result;
 *     });
 * }
 * ```
 */
trait PreventsDuplicateRequests
{
    /**
     * TTL for request fingerprints (in seconds)
     * Adjust based on expected operation duration
     */
    protected int $requestTtl = 30;

    /**
     * Check if a request is a duplicate (already in progress)
     *
     * @param  string  $method  The method name being called
     * @param  array  $params  The parameters passed to the method
     * @param  int|null  $ttl  Optional custom TTL in seconds
     * @return bool True if this is a duplicate request
     */
    protected function isDuplicateRequest(string $method, array $params = [], ?int $ttl = null): bool
    {
        $fingerprint = $this->generateRequestFingerprint($method, $params);
        $ttl = $ttl ?? $this->requestTtl;

        // Try to acquire a lock
        $acquired = Cache::add($fingerprint, true, $ttl);

        if (! $acquired) {
            // Lock already exists - this is a duplicate request
            logger()->debug('Duplicate request blocked', [
                'component' => static::class,
                'method' => $method,
                'params' => $params,
                'fingerprint' => $fingerprint,
            ]);

            return true;
        }

        return false;
    }

    /**
     * Clear the request fingerprint (call this after operation completes)
     *
     * @param  string  $method  The method name
     * @param  array  $params  The parameters
     */
    protected function clearRequestFingerprint(string $method, array $params = []): void
    {
        $fingerprint = $this->generateRequestFingerprint($method, $params);
        Cache::forget($fingerprint);
    }

    /**
     * Generate a unique fingerprint for a request
     *
     * @param  string  $method  The method name
     * @param  array  $params  The parameters
     * @return string The cache key
     */
    protected function generateRequestFingerprint(string $method, array $params = []): string
    {
        // Include component ID to scope to this specific instance
        $componentId = $this->getId();

        // Serialize params for consistent hashing
        $paramHash = md5(serialize($params));

        return "livewire:request:{$componentId}:{$method}:{$paramHash}";
    }

    /**
     * Execute a callback while preventing duplicate concurrent requests
     *
     * This is a convenience method that handles the try-finally pattern
     *
     * @param  string  $method  The method identifier
     * @param  array  $params  The parameters for fingerprinting
     * @param  callable  $callback  The action to execute
     * @param  int|null  $ttl  Optional custom TTL
     * @return mixed The result of the callback, or null if duplicate
     */
    protected function preventDuplicate(string $method, array $params, callable $callback, ?int $ttl = null)
    {
        if ($this->isDuplicateRequest($method, $params, $ttl)) {
            logger()->info('Duplicate request prevented', [
                'component' => static::class,
                'method' => $method,
            ]);

            return null; // Or throw exception, depending on requirements
        }

        try {
            return $callback();
        } finally {
            $this->clearRequestFingerprint($method, $params);
        }
    }

    /**
     * Check if a request with specific params is currently in progress
     *
     * Useful for conditional UI rendering
     */
    protected function isRequestInProgress(string $method, array $params = []): bool
    {
        $fingerprint = $this->generateRequestFingerprint($method, $params);

        return Cache::has($fingerprint);
    }

    /**
     * Set a custom TTL for specific operations
     *
     * @return $this
     */
    protected function setRequestTtl(int $seconds): self
    {
        $this->requestTtl = $seconds;

        return $this;
    }

    /**
     * Clear all request fingerprints for this component instance
     *
     * Useful for cleanup or error recovery
     */
    protected function clearAllRequestFingerprints(): void
    {
        $componentId = $this->getId();
        $pattern = "livewire:request:{$componentId}:*";

        // Note: This requires a cache driver that supports pattern deletion (Redis, Memcached)
        // For simpler implementations, you might track keys separately
        if (method_exists(Cache::getStore(), 'deleteMultiple')) {
            $keys = Cache::getStore()->keys($pattern);
            foreach ($keys as $key) {
                Cache::forget($key);
            }
        }
    }
}
