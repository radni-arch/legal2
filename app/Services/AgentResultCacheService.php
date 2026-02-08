<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Agent Result Cache Service
 *
 * Caches agent execution results to improve performance for repeated queries
 *
 * Sprint 6.4: Performance Optimization
 */
class AgentResultCacheService
{
    protected string $cachePrefix = 'agent_result';

    protected int $defaultTtl = 3600; // 1 hour default TTL

    /**
     * Get cached agent result
     */
    public function get(string $agentType, string $cacheKey): ?array
    {
        $fullKey = $this->buildCacheKey($agentType, $cacheKey);

        try {
            $cached = Cache::get($fullKey);

            if ($cached) {
                Log::debug('Agent result cache hit', [
                    'agent_type' => $agentType,
                    'cache_key' => $cacheKey,
                ]);

                return $cached;
            }
        } catch (\Exception $e) {
            Log::warning('Agent result cache read failed', [
                'error' => $e->getMessage(),
                'agent_type' => $agentType,
            ]);
        }

        return null;
    }

    /**
     * Store agent result in cache
     */
    public function put(string $agentType, string $cacheKey, array $result, ?int $ttl = null): bool
    {
        $fullKey = $this->buildCacheKey($agentType, $cacheKey);
        $ttl = $ttl ?? $this->defaultTtl;

        try {
            Cache::put($fullKey, $result, $ttl);

            Log::debug('Agent result cached', [
                'agent_type' => $agentType,
                'cache_key' => $cacheKey,
                'ttl' => $ttl,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::warning('Agent result cache write failed', [
                'error' => $e->getMessage(),
                'agent_type' => $agentType,
            ]);

            return false;
        }
    }

    /**
     * Check if result exists in cache
     */
    public function has(string $agentType, string $cacheKey): bool
    {
        $fullKey = $this->buildCacheKey($agentType, $cacheKey);

        try {
            return Cache::has($fullKey);
        } catch (\Exception $e) {
            Log::warning('Agent result cache check failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Invalidate cached result
     */
    public function forget(string $agentType, string $cacheKey): bool
    {
        $fullKey = $this->buildCacheKey($agentType, $cacheKey);

        try {
            return Cache::forget($fullKey);
        } catch (\Exception $e) {
            Log::warning('Agent result cache forget failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Invalidate all cached results for an agent type
     */
    public function forgetAgentType(string $agentType): bool
    {
        try {
            // This is Redis/Memcached specific - for file/database cache, it's not efficient
            // For production, consider using tags if cache driver supports it
            $pattern = $this->buildCacheKey($agentType, '*');

            // Note: This only works with Redis driver
            if (config('cache.default') === 'redis') {
                $keys = Cache::getStore()->getRedis()->keys($pattern);
                foreach ($keys as $key) {
                    Cache::forget($key);
                }

                return true;
            }

            Log::info('Cache invalidation not supported for current driver', [
                'driver' => config('cache.default'),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::warning('Agent type cache invalidation failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Generate cache key from problem statement and parameters
     */
    public function generateCacheKey(string $problemStatement, array $parameters = []): string
    {
        // Create deterministic cache key from problem + parameters
        $data = [
            'problem' => $this->normalizeProblemStatement($problemStatement),
            'params' => $parameters,
        ];

        return hash('sha256', json_encode($data));
    }

    /**
     * Execute agent with caching
     *
     * @param  callable  $executor  Function that executes the agent and returns result
     */
    public function remember(
        string $agentType,
        string $cacheKey,
        callable $executor,
        ?int $ttl = null
    ): array {
        // Try to get from cache
        $cached = $this->get($agentType, $cacheKey);

        if ($cached !== null) {
            $cached['from_cache'] = true;

            return $cached;
        }

        // Execute agent
        $result = $executor();

        // Store in cache
        $this->put($agentType, $cacheKey, $result, $ttl);

        $result['from_cache'] = false;

        return $result;
    }

    /**
     * Build full cache key
     */
    protected function buildCacheKey(string $agentType, string $cacheKey): string
    {
        return "{$this->cachePrefix}:{$agentType}:{$cacheKey}";
    }

    /**
     * Normalize problem statement for consistent caching
     */
    protected function normalizeProblemStatement(string $problem): string
    {
        // Convert to lowercase, remove extra whitespace, trim
        return trim(preg_replace('/\s+/', ' ', mb_strtolower($problem)));
    }

    /**
     * Get cache statistics
     */
    public function getStats(): array
    {
        // This would require tracking hits/misses - placeholder for now
        return [
            'cache_enabled' => config('cache.default') !== 'array',
            'cache_driver' => config('cache.default'),
            'default_ttl' => $this->defaultTtl,
        ];
    }

    /**
     * Warm up cache with common queries
     */
    public function warmup(array $commonQueries, callable $executor): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        foreach ($commonQueries as $agentType => $queries) {
            foreach ($queries as $query) {
                $cacheKey = $this->generateCacheKey($query['problem'], $query['params'] ?? []);

                // Skip if already cached
                if ($this->has($agentType, $cacheKey)) {
                    $results['skipped']++;

                    continue;
                }

                try {
                    // Execute and cache
                    $result = $executor($agentType, $query);
                    $this->put($agentType, $cacheKey, $result);
                    $results['success']++;
                } catch (\Exception $e) {
                    Log::warning('Cache warmup failed for query', [
                        'agent_type' => $agentType,
                        'error' => $e->getMessage(),
                    ]);
                    $results['failed']++;
                }
            }
        }

        return $results;
    }

    /**
     * Clear all agent result cache
     */
    public function flush(): bool
    {
        try {
            // This is a nuclear option - use with caution
            if (config('cache.default') === 'redis') {
                $pattern = "{$this->cachePrefix}:*";
                $keys = Cache::getStore()->getRedis()->keys($pattern);

                foreach ($keys as $key) {
                    Cache::forget($key);
                }

                return true;
            }

            Log::warning('Cache flush not supported for current driver', [
                'driver' => config('cache.default'),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Cache flush failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
