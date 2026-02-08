<?php

namespace App\Http\Controllers;

use App\Services\Monitoring\AlertManager;
use App\Services\Monitoring\ApplicationMonitor;
use App\Services\Monitoring\MetricsCollector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Monitoring Dashboard Controller
 *
 * Provides endpoints for monitoring application health and performance.
 */
class MonitoringController extends Controller
{
    public function __construct(
        protected ApplicationMonitor $monitor,
        protected MetricsCollector $metrics,
        protected AlertManager $alertManager
    ) {}

    /**
     * Get application health status
     *
     * GET /api/monitoring/health
     */
    public function health(Request $request): JsonResponse
    {
        $health = $this->monitor->getHealthStatus();

        // Return appropriate HTTP status code based on health
        $statusCode = match ($health['status']) {
            'healthy' => 200,
            'degraded' => 503,
            'critical' => 503,
            default => 200,
        };

        return response()->json($health, $statusCode);
    }

    /**
     * Get detailed performance metrics
     *
     * GET /api/monitoring/performance?minutes=60
     */
    public function performance(Request $request): JsonResponse
    {
        $minutes = $request->integer('minutes', 60);

        $report = $this->monitor->getPerformanceReport($minutes);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * Get specific metric data
     *
     * GET /api/monitoring/metrics/{metric}
     */
    public function metric(Request $request, string $metric): JsonResponse
    {
        $tags = $request->input('tags', []);

        $data = $this->metrics->get($metric, $tags);
        $stats = $this->metrics->getStats($metric, $tags);

        return response()->json([
            'success' => true,
            'metric' => $metric,
            'tags' => $tags,
            'data' => $data,
            'stats' => $stats,
        ]);
    }

    /**
     * Get all active alerts
     *
     * GET /api/monitoring/alerts
     */
    public function alerts(Request $request): JsonResponse
    {
        $active = $this->alertManager->getActiveAlerts();
        $history = $this->alertManager->getAlertHistory(
            $request->integer('limit', 50)
        );

        return response()->json([
            'success' => true,
            'active_count' => count($active),
            'active' => array_values($active),
            'history' => $history,
        ]);
    }

    /**
     * Clear specific alert
     *
     * DELETE /api/monitoring/alerts/{type}
     */
    public function clearAlert(string $type): JsonResponse
    {
        $this->alertManager->clearAlert($type);

        return response()->json([
            'success' => true,
            'message' => "Alert '{$type}' cleared",
        ]);
    }

    /**
     * Clear all alerts
     *
     * DELETE /api/monitoring/alerts
     */
    public function clearAllAlerts(): JsonResponse
    {
        $this->alertManager->clearAllAlerts();

        return response()->json([
            'success' => true,
            'message' => 'All alerts cleared',
        ]);
    }

    /**
     * Get system metrics
     *
     * GET /api/monitoring/system
     */
    public function system(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'php' => [
                    'version' => PHP_VERSION,
                    'memory_limit' => ini_get('memory_limit'),
                    'memory_usage' => $this->formatBytes(memory_get_usage(true)),
                    'peak_memory' => $this->formatBytes(memory_get_peak_usage(true)),
                ],
                'database' => $this->getDatabaseMetrics(),
                'cache' => $this->getCacheMetrics(),
                'queue' => $this->getQueueMetrics(),
            ],
        ]);
    }

    /**
     * Get error rate over time
     *
     * GET /api/monitoring/error-rate
     */
    public function errorRate(Request $request): JsonResponse
    {
        $minutes = $request->integer('minutes', 60);

        $errors = $this->metrics->get('error.count');
        $requests = $this->metrics->get('http.request.count');

        // Calculate error rate over time
        $timeSeriesData = $this->calculateErrorRateTimeSeries($errors, $requests);

        return response()->json([
            'success' => true,
            'period_minutes' => $minutes,
            'data' => $timeSeriesData,
        ]);
    }

    /**
     * Get response time percentiles
     *
     * GET /api/monitoring/response-times
     */
    public function responseTimes(Request $request): JsonResponse
    {
        $stats = $this->metrics->getStats('http.request.duration');

        return response()->json([
            'success' => true,
            'data' => [
                'avg' => round($stats['avg'], 2),
                'min' => round($stats['min'], 2),
                'max' => round($stats['max'], 2),
                'p50' => round($stats['p50'], 2),
                'p95' => round($stats['p95'], 2),
                'p99' => round($stats['p99'], 2),
            ],
            'unit' => 'milliseconds',
        ]);
    }

    /**
     * Cleanup old metrics
     *
     * POST /api/monitoring/cleanup
     */
    public function cleanup(Request $request): JsonResponse
    {
        $olderThan = $request->integer('older_than_seconds', 7200);

        $this->metrics->cleanup($olderThan);

        return response()->json([
            'success' => true,
            'message' => "Metrics older than {$olderThan} seconds cleaned up",
        ]);
    }

    /**
     * Get comprehensive system health report
     *
     * GET /api/monitoring/health/system
     */
    public function systemHealth(Request $request): JsonResponse
    {
        $report = $this->monitor->getSystemHealthReport();

        // Return appropriate HTTP status code based on health
        $statusCode = match ($report['status']) {
            'healthy' => 200,
            'degraded' => 503,
            'unhealthy' => 503,
            default => 200,
        };

        return response()->json([
            'success' => true,
            'data' => $report,
        ], $statusCode);
    }

    /**
     * Get database health check
     *
     * GET /api/monitoring/health/database
     */
    public function databaseHealth(Request $request): JsonResponse
    {
        $health = $this->monitor->checkDatabaseHealth();

        $statusCode = $health['status'] === 'healthy' ? 200 : 503;

        return response()->json([
            'success' => $health['status'] === 'healthy',
            'data' => $health,
        ], $statusCode);
    }

    /**
     * Get Neo4j graph database health check
     *
     * GET /api/monitoring/health/neo4j
     */
    public function neo4jHealth(Request $request): JsonResponse
    {
        $health = $this->monitor->checkNeo4jHealth();

        $statusCode = match ($health['status']) {
            'healthy' => 200,
            'disabled' => 200,
            default => 503,
        };

        return response()->json([
            'success' => in_array($health['status'], ['healthy', 'disabled']),
            'data' => $health,
        ], $statusCode);
    }

    /**
     * Get OpenAI API health check
     *
     * GET /api/monitoring/health/openai
     */
    public function openaiHealth(Request $request): JsonResponse
    {
        $health = $this->monitor->checkOpenAIHealth();

        $statusCode = $health['status'] === 'healthy' ? 200 : 503;

        return response()->json([
            'success' => $health['status'] === 'healthy',
            'data' => $health,
        ], $statusCode);
    }

    /**
     * Get cache driver health check
     *
     * GET /api/monitoring/health/cache
     */
    public function cacheHealth(Request $request): JsonResponse
    {
        $health = $this->monitor->checkCacheHealth();

        $statusCode = $health['status'] === 'healthy' ? 200 : 503;

        return response()->json([
            'success' => $health['status'] === 'healthy',
            'data' => $health,
        ], $statusCode);
    }

    /**
     * Get queue worker health check
     *
     * GET /api/monitoring/health/queue
     */
    public function queueHealth(Request $request): JsonResponse
    {
        $health = $this->monitor->checkQueueHealth();

        $statusCode = $health['status'] === 'healthy' ? 200 : 503;

        return response()->json([
            'success' => $health['status'] === 'healthy',
            'data' => $health,
        ], $statusCode);
    }

    /**
     * Get rate limit metrics for all limiters
     *
     * GET /api/monitoring/metrics/rate-limits
     */
    public function rateLimitMetrics(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;
        $ip = $request->ip();

        $limiters = [
            'openai' => [
                'name' => 'OpenAI API',
                'limit' => 30,
                'period' => 'minute',
                'key' => $userId ?? $ip,
            ],
            'agents' => [
                'name' => 'Agent Execution',
                'limit' => 10,
                'period' => 'minute',
                'key' => $userId ?? $ip,
            ],
            'search' => [
                'name' => 'Search Operations',
                'limit' => 60,
                'period' => 'minute',
                'key' => $userId ?? $ip,
            ],
            'api' => [
                'name' => 'General API',
                'limit' => 120,
                'period' => 'minute',
                'key' => $userId ?? $ip,
            ],
        ];

        $metrics = [];

        foreach ($limiters as $limiterName => $limiterConfig) {
            $key = $limiterConfig['key'];

            // Get available attempts (Laravel returns remaining attempts)
            $available = RateLimiter::remaining($limiterName.'|'.$key, $limiterConfig['limit']);
            $used = $limiterConfig['limit'] - $available;

            // Get retry after if throttled
            $retryAfter = RateLimiter::availableIn($limiterName.'|'.$key);

            $metrics[$limiterName] = [
                'name' => $limiterConfig['name'],
                'limit' => $limiterConfig['limit'],
                'period' => $limiterConfig['period'],
                'used' => $used,
                'remaining' => $available,
                'usage_percent' => round(($used / $limiterConfig['limit']) * 100, 2),
                'throttled' => $retryAfter > 0,
                'retry_after_seconds' => $retryAfter,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'identifier' => $userId ? "user:{$userId}" : "ip:{$ip}",
                'limiters' => $metrics,
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get token usage analytics
     *
     * GET /api/monitoring/metrics/tokens
     */
    public function tokenMetrics(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;
        $ip = $request->ip();
        $key = 'tokens:'.($userId ?? $ip);

        // Get total token usage
        $totalUsage = Cache::get($key.':usage', 0);

        // Get hourly breakdown (last 24 hours)
        $hourlyData = [];
        $now = now();

        for ($i = 23; $i >= 0; $i--) {
            $timestamp = $now->copy()->subHours($i);
            $hourKey = $key.':hourly:'.$timestamp->format('Y-m-d-H');
            $usage = Cache::get($hourKey, 0);

            $hourlyData[] = [
                'hour' => $timestamp->format('Y-m-d H:00:00'),
                'timestamp' => $timestamp->timestamp,
                'tokens_used' => $usage,
            ];
        }

        // Get daily budget info
        $userBudget = $request->user()?->token_budget_daily ?? 50000;
        $dailyKey = 'tokens:'.($userId ?? $ip);
        $dailyHits = RateLimiter::attempts($dailyKey);

        return response()->json([
            'success' => true,
            'data' => [
                'identifier' => $userId ? "user:{$userId}" : "ip:{$ip}",
                'total_tokens_used' => $totalUsage,
                'daily_budget' => $userBudget,
                'budget_used_percent' => round(($totalUsage / $userBudget) * 100, 2),
                'budget_remaining' => max(0, $userBudget - $totalUsage),
                'hourly_breakdown' => $hourlyData,
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get database metrics
     */
    protected function getDatabaseMetrics(): array
    {
        try {
            $connection = DB::connection();

            return [
                'driver' => $connection->getDriverName(),
                'database' => $connection->getDatabaseName(),
                'connected' => true,
            ];
        } catch (\Throwable $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get cache metrics
     */
    protected function getCacheMetrics(): array
    {
        try {
            $hits = $this->metrics->getCounter('cache.hit');
            $misses = $this->metrics->getCounter('cache.miss');
            $total = $hits + $misses;

            return [
                'driver' => config('cache.default'),
                'hits' => $hits,
                'misses' => $misses,
                'hit_rate' => $total > 0 ? round(($hits / $total) * 100, 2) : 0,
            ];
        } catch (\Throwable $e) {
            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get queue metrics
     */
    protected function getQueueMetrics(): array
    {
        try {
            return [
                'driver' => config('queue.default'),
                'size' => \Illuminate\Support\Facades\Queue::size('default'),
                'failed_jobs' => DB::table('failed_jobs')->count(),
            ];
        } catch (\Throwable $e) {
            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Calculate error rate time series
     */
    protected function calculateErrorRateTimeSeries(array $errors, array $requests): array
    {
        $timeSeries = [];
        $buckets = [];

        // Group by 5-minute buckets
        $bucketSize = 300; // 5 minutes in seconds

        foreach ($requests as $request) {
            $bucket = floor($request['timestamp'] / $bucketSize) * $bucketSize;
            if (! isset($buckets[$bucket])) {
                $buckets[$bucket] = ['requests' => 0, 'errors' => 0];
            }
            $buckets[$bucket]['requests']++;
        }

        foreach ($errors as $error) {
            $bucket = floor($error['timestamp'] / $bucketSize) * $bucketSize;
            if (isset($buckets[$bucket])) {
                $buckets[$bucket]['errors']++;
            }
        }

        foreach ($buckets as $timestamp => $data) {
            $errorRate = $data['requests'] > 0
                ? round(($data['errors'] / $data['requests']) * 100, 2)
                : 0;

            $timeSeries[] = [
                'timestamp' => $timestamp,
                'time' => date('Y-m-d H:i:s', $timestamp),
                'error_rate' => $errorRate,
                'errors' => $data['errors'],
                'requests' => $data['requests'],
            ];
        }

        return $timeSeries;
    }

    /**
     * Format bytes
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2).' '.$units[$i];
    }
}
