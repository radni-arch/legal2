<?php

namespace App\Services\Monitoring;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Metrics Collection Service
 *
 * Collects and stores application metrics for monitoring and alerting.
 * Uses cache for fast writes and periodic aggregation.
 */
class MetricsCollector
{
    protected const CACHE_PREFIX = 'metrics:';

    protected const CACHE_TTL = 3600; // 1 hour

    /**
     * Record a metric value
     */
    public function record(string $metric, float $value, array $tags = []): void
    {
        try {
            $key = $this->buildKey($metric, $tags);
            $timestamp = now()->timestamp;

            // Store in cache as time series
            $data = Cache::get($key, []);
            $data[] = [
                'value' => $value,
                'timestamp' => $timestamp,
            ];

            // Keep only last 1000 data points
            if (count($data) > 1000) {
                $data = array_slice($data, -1000);
            }

            Cache::put($key, $data, self::CACHE_TTL);

            // Also update aggregates
            $this->updateAggregates($metric, $value, $tags);
        } catch (\Throwable $e) {
            // Don't let metrics collection break the app
            Log::warning('Failed to record metric', [
                'metric' => $metric,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Increment a counter metric
     */
    public function increment(string $metric, int $amount = 1, array $tags = []): void
    {
        try {
            $key = $this->buildCounterKey($metric, $tags);
            Cache::increment($key, $amount);
            Cache::expire($key, self::CACHE_TTL);
        } catch (\Throwable $e) {
            Log::warning('Failed to increment metric', [
                'metric' => $metric,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Record a timing metric (in milliseconds)
     */
    public function timing(string $metric, float $milliseconds, array $tags = []): void
    {
        $this->record($metric, $milliseconds, array_merge($tags, ['type' => 'timing']));
    }

    /**
     * Record a gauge metric (current value)
     */
    public function gauge(string $metric, float $value, array $tags = []): void
    {
        $this->record($metric, $value, array_merge($tags, ['type' => 'gauge']));
    }

    /**
     * Get metric values
     */
    public function get(string $metric, array $tags = []): array
    {
        $key = $this->buildKey($metric, $tags);

        return Cache::get($key, []);
    }

    /**
     * Get counter value
     */
    public function getCounter(string $metric, array $tags = []): int
    {
        $key = $this->buildCounterKey($metric, $tags);

        return (int) Cache::get($key, 0);
    }

    /**
     * Get aggregated statistics for a metric
     */
    public function getStats(string $metric, array $tags = []): array
    {
        $data = $this->get($metric, $tags);

        if (empty($data)) {
            return [
                'count' => 0,
                'min' => 0,
                'max' => 0,
                'avg' => 0,
                'sum' => 0,
                'p50' => 0,
                'p95' => 0,
                'p99' => 0,
            ];
        }

        $values = array_column($data, 'value');
        sort($values);

        $count = count($values);
        $sum = array_sum($values);

        return [
            'count' => $count,
            'min' => min($values),
            'max' => max($values),
            'avg' => $sum / $count,
            'sum' => $sum,
            'p50' => $this->percentile($values, 50),
            'p95' => $this->percentile($values, 95),
            'p99' => $this->percentile($values, 99),
        ];
    }

    /**
     * Get all metrics matching a pattern
     */
    public function getAll(string $pattern = '*'): array
    {
        try {
            $keys = Cache::getStore()->getRedis()->keys(self::CACHE_PREFIX.$pattern);
            $metrics = [];

            foreach ($keys as $key) {
                $metricName = str_replace(self::CACHE_PREFIX, '', $key);
                $metrics[$metricName] = Cache::get($key);
            }

            return $metrics;
        } catch (\Throwable $e) {
            // Fallback for non-Redis cache drivers
            return [];
        }
    }

    /**
     * Clear old metrics
     */
    public function cleanup(int $olderThanSeconds = 7200): void
    {
        try {
            $cutoff = now()->subSeconds($olderThanSeconds)->timestamp;
            $keys = Cache::getStore()->getRedis()->keys(self::CACHE_PREFIX.'*');

            foreach ($keys as $key) {
                $data = Cache::get($key, []);
                $filtered = array_filter($data, fn ($point) => $point['timestamp'] > $cutoff);

                if (empty($filtered)) {
                    Cache::forget($key);
                } else {
                    Cache::put($key, array_values($filtered), self::CACHE_TTL);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to cleanup metrics', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build cache key for metric
     */
    protected function buildKey(string $metric, array $tags): string
    {
        $tagString = empty($tags) ? '' : ':'.http_build_query($tags, '', ':');

        return self::CACHE_PREFIX.$metric.$tagString;
    }

    /**
     * Build cache key for counter
     */
    protected function buildCounterKey(string $metric, array $tags): string
    {
        return $this->buildKey($metric.':counter', $tags);
    }

    /**
     * Update aggregate statistics
     */
    protected function updateAggregates(string $metric, float $value, array $tags): void
    {
        $aggKey = $this->buildKey($metric.':agg', $tags);
        $agg = Cache::get($aggKey, [
            'count' => 0,
            'sum' => 0,
            'min' => PHP_FLOAT_MAX,
            'max' => PHP_FLOAT_MIN,
        ]);

        $agg['count']++;
        $agg['sum'] += $value;
        $agg['min'] = min($agg['min'], $value);
        $agg['max'] = max($agg['max'], $value);

        Cache::put($aggKey, $agg, self::CACHE_TTL);
    }

    /**
     * Calculate percentile from sorted array
     */
    protected function percentile(array $sortedValues, int $percentile): float
    {
        $count = count($sortedValues);
        if ($count === 0) {
            return 0;
        }

        $index = ($percentile / 100) * ($count - 1);
        $lower = floor($index);
        $upper = ceil($index);

        if ($lower === $upper) {
            return $sortedValues[$lower];
        }

        $fraction = $index - $lower;

        return $sortedValues[$lower] + $fraction * ($sortedValues[$upper] - $sortedValues[$lower]);
    }
}
