<?php

namespace App\Services\Graph;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Graph Query Cache Service (Sprint 4.7)
 *
 * Provides caching layer for Neo4j graph queries to improve performance.
 *
 * Features:
 * - Redis-based caching with configurable TTL
 * - Consistent cache key generation
 * - Cache statistics tracking (hits/misses)
 * - Cache warming for common queries
 * - Selective invalidation
 *
 * Performance Target:
 * - >80% reduction in repeated query time
 *
 * Usage:
 *   $cache = app(GraphQueryCacheService::class);
 *
 *   $cached = $cache->get($query, $params);
 *   if (!$cached) {
 *       $result = executeQuery($query, $params);
 *       $cache->put($query, $params, $result);
 *   }
 */
class GraphQueryCacheService
{
    /**
     * Cache key prefix for graph queries
     */
    protected const CACHE_PREFIX = 'graph:query:';

    /**
     * Cache key for statistics
     */
    protected const STATS_KEY = 'graph:query:stats';

    /**
     * Default TTL in seconds (5 minutes)
     */
    protected const DEFAULT_TTL = 300;

    /**
     * Maximum TTL in seconds (1 hour)
     */
    protected const MAX_TTL = 3600;

    /**
     * Generate consistent cache key from query and parameters
     *
     * @param  string  $query  Cypher query
     * @param  array  $parameters  Query parameters
     * @return string Cache key
     */
    public function getCacheKey(string $query, array $parameters = []): string
    {
        // Normalize query (remove extra whitespace)
        $normalizedQuery = preg_replace('/\s+/', ' ', trim($query));

        // Sort parameters for consistency
        ksort($parameters);

        // Create hash from query + parameters
        $hash = md5($normalizedQuery.json_encode($parameters));

        return self::CACHE_PREFIX.$hash;
    }

    /**
     * Get cached query result
     *
     * @param  string  $query  Cypher query
     * @param  array  $parameters  Query parameters
     * @return mixed|null Cached result or null if miss
     */
    public function get(string $query, array $parameters = []): mixed
    {
        $key = $this->getCacheKey($query, $parameters);

        $result = Cache::get($key);

        // Track statistics
        if ($result !== null) {
            $this->recordHit();
        } else {
            $this->recordMiss();
        }

        return $result;
    }

    /**
     * Store query result in cache
     *
     * @param  string  $query  Cypher query
     * @param  array  $parameters  Query parameters
     * @param  mixed  $result  Query result to cache
     * @param  int|null  $ttl  TTL in seconds (null = default)
     * @return bool Success
     */
    public function put(string $query, array $parameters, mixed $result, ?int $ttl = null): bool
    {
        $key = $this->getCacheKey($query, $parameters);
        $ttl = $ttl ?? self::DEFAULT_TTL;

        // Cap TTL at maximum
        $ttl = min($ttl, self::MAX_TTL);

        try {
            Cache::put($key, $result, $ttl);

            Log::debug('GraphQueryCacheService - Cached query result', [
                'key' => $key,
                'ttl' => $ttl,
                'result_size' => is_array($result) ? count($result) : null,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('GraphQueryCacheService - Failed to cache result', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Check if query result is cached
     *
     * @param  string  $query  Cypher query
     * @param  array  $parameters  Query parameters
     * @return bool True if cached
     */
    public function has(string $query, array $parameters = []): bool
    {
        $key = $this->getCacheKey($query, $parameters);

        return Cache::has($key);
    }

    /**
     * Invalidate specific query from cache
     *
     * @param  string  $query  Cypher query
     * @param  array  $parameters  Query parameters
     * @return bool Success
     */
    public function invalidate(string $query, array $parameters = []): bool
    {
        $key = $this->getCacheKey($query, $parameters);

        return Cache::forget($key);
    }

    /**
     * Invalidate all graph query caches
     *
     * @return bool Success
     */
    public function invalidateAll(): bool
    {
        try {
            // Use Cache::tags if Redis is available, otherwise fallback to flush
            if (config('cache.default') === 'redis') {
                // Redis supports tags
                Cache::tags(['graph-queries'])->flush();
            } else {
                // Fallback: Clear all caches starting with prefix
                // This is less efficient but works with all cache drivers
                Cache::flush();
            }

            Log::info('GraphQueryCacheService - Invalidated all cached queries');

            return true;
        } catch (\Exception $e) {
            Log::error('GraphQueryCacheService - Failed to invalidate all caches', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Warm cache with common queries
     *
     * @param  array  $queries  Array of ['query' => string, 'params' => array]
     * @param  callable  $executor  Function to execute query: fn($query, $params) => result
     * @return int Number of queries cached
     */
    public function warmCache(array $queries, callable $executor): int
    {
        $cached = 0;

        foreach ($queries as $queryData) {
            $query = $queryData['query'] ?? null;
            $params = $queryData['params'] ?? [];
            $ttl = $queryData['ttl'] ?? self::DEFAULT_TTL;

            if (! $query) {
                continue;
            }

            // Skip if already cached
            if ($this->has($query, $params)) {
                $cached++;

                continue;
            }

            try {
                // Execute query to get result
                $result = $executor($query, $params);

                // Cache result
                $this->put($query, $params, $result, $ttl);

                $cached++;
            } catch (\Exception $e) {
                Log::error('GraphQueryCacheService - Failed to warm cache for query', [
                    'query' => substr($query, 0, 100),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('GraphQueryCacheService - Cache warming complete', [
            'queries_cached' => $cached,
            'total_queries' => count($queries),
        ]);

        return $cached;
    }

    /**
     * Get cache statistics
     *
     * @return array Statistics
     */
    public function getStatistics(): array
    {
        $stats = Cache::get(self::STATS_KEY, [
            'hits' => 0,
            'misses' => 0,
        ]);

        $total = $stats['hits'] + $stats['misses'];
        $hitRate = $total > 0 ? round(($stats['hits'] / $total) * 100, 2) : 0;

        return [
            'hits' => $stats['hits'],
            'misses' => $stats['misses'],
            'total_requests' => $total,
            'hit_rate' => $hitRate,
        ];
    }

    /**
     * Reset cache statistics
     */
    public function resetStatistics(): void
    {
        Cache::forget(self::STATS_KEY);
    }

    /**
     * Get default TTL
     *
     * @return int TTL in seconds
     */
    public function getDefaultTTL(): int
    {
        return self::DEFAULT_TTL;
    }

    /**
     * Record cache hit
     */
    protected function recordHit(): void
    {
        $stats = Cache::get(self::STATS_KEY, ['hits' => 0, 'misses' => 0]);
        $stats['hits']++;
        Cache::put(self::STATS_KEY, $stats, 86400); // 24 hours
    }

    /**
     * Record cache miss
     */
    protected function recordMiss(): void
    {
        $stats = Cache::get(self::STATS_KEY, ['hits' => 0, 'misses' => 0]);
        $stats['misses']++;
        Cache::put(self::STATS_KEY, $stats, 86400); // 24 hours
    }
}
