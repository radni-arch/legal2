<?php

namespace App\Contracts\AI;

/**
 * Contract for AI response caching
 */
interface CacheServiceInterface
{
    /**
     * Get cached response
     *
     * @param  string  $key  Cache key
     * @return mixed|null Cached value or null
     */
    public function get(string $key): mixed;

    /**
     * Store response in cache
     *
     * @param  int  $ttl  Time to live in seconds
     */
    public function put(string $key, mixed $value, int $ttl): bool;

    /**
     * Generate cache key for request
     *
     * @param  string  $operation  Operation name (chat, embeddings, etc.)
     * @param  array  $params  Request parameters
     * @return string Cache key
     */
    public function generateKey(string $operation, array $params): string;

    /**
     * Clear cache for specific operation or all
     */
    public function clear(?string $operation = null): bool;
}
