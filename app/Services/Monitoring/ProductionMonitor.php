<?php

namespace App\Services\Monitoring;

use App\Models\AgentRun;
use App\Models\OrchestrationLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Sentry\State\Scope;

/**
 * Production Monitoring Service
 *
 * Sprint 6.7: Production Monitoring Setup
 *
 * Monitors production metrics, sends to Sentry, and triggers alerts based on
 * configured thresholds.
 *
 * Key monitoring checks:
 * - Agent failure rate >10%
 * - Response time >30 seconds
 * - API error rate >5%
 * - Queue backlog >100 jobs
 */
class ProductionMonitor
{
    public function __construct(
        protected MetricsCollector $metricsCollector,
        protected AlertManager $alertManager
    ) {}

    /**
     * Record a metric value
     */
    public function recordMetric(string $metric, float $value, array $tags = []): void
    {
        // Record to internal metrics
        $this->metricsCollector->record($metric, $value, $tags);

        // Send to Sentry (if configured)
        if (config('sentry.dsn')) {
            try {
                \Sentry\metrics()->increment($metric, $value, $tags);
            } catch (\Throwable $e) {
                Log::debug('Failed to send metric to Sentry', [
                    'metric' => $metric,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Check for alerts
        $this->checkAlertThresholds($metric, $value);
    }

    /**
     * Check service health
     */
    public function checkServiceHealth(): array
    {
        return [
            'database' => $this->checkDatabaseHealth(),
            'cache' => $this->checkCacheHealth(),
            'queue' => $this->checkQueueHealth(),
            'api_response_time' => $this->getAverageResponseTime(),
            'error_rate' => $this->getErrorRate(),
        ];
    }

    /**
     * Check alert thresholds
     */
    protected function checkAlertThresholds(string $metric, float $value): void
    {
        $thresholds = config('monitoring.alert_thresholds', []);

        if (isset($thresholds[$metric]) && $value > $thresholds[$metric]) {
            $this->alertManager->alert($metric, "Metric '{$metric}' exceeded threshold", [
                'value' => $value,
                'threshold' => $thresholds[$metric],
                'timestamp' => now()->toIso8601String(),
            ]);
        }
    }

    /**
     * Check database health
     */
    protected function checkDatabaseHealth(): array
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            $latency = (microtime(true) - $start) * 1000;

            return [
                'status' => 'healthy',
                'latency_ms' => round($latency, 2),
                'connection' => DB::connection()->getName(),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check cache health
     */
    protected function checkCacheHealth(): array
    {
        try {
            $testKey = 'health_check_'.now()->timestamp;
            $testValue = 'test';

            $start = microtime(true);
            Cache::put($testKey, $testValue, 10);
            $retrieved = Cache::get($testKey);
            Cache::forget($testKey);
            $latency = (microtime(true) - $start) * 1000;

            $status = $retrieved === $testValue ? 'healthy' : 'degraded';

            return [
                'status' => $status,
                'latency_ms' => round($latency, 2),
                'driver' => config('cache.default'),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check queue health
     */
    protected function checkQueueHealth(): array
    {
        try {
            $size = Queue::size('default');
            $failedJobs = DB::table('failed_jobs')->count();

            $status = match (true) {
                $failedJobs > 100 => 'critical',
                $failedJobs > 10 => 'degraded',
                $size > 1000 => 'degraded',
                default => 'healthy',
            };

            return [
                'status' => $status,
                'queue_size' => $size,
                'failed_jobs' => $failedJobs,
                'connection' => config('queue.default'),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'unknown',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get average response time
     */
    protected function getAverageResponseTime(): float
    {
        $cacheKey = 'monitoring:avg_response_time';

        return Cache::get($cacheKey, 0);
    }

    /**
     * Get error rate
     */
    protected function getErrorRate(): float
    {
        $totalRequests = Cache::get('monitoring:total_requests', 0);
        $errorRequests = Cache::get('monitoring:error_requests', 0);

        if ($totalRequests === 0) {
            return 0;
        }

        return ($errorRequests / $totalRequests) * 100;
    }

    /**
     * Update response time metrics
     */
    public function updateResponseTime(float $ms): void
    {
        $cacheKey = 'monitoring:avg_response_time';
        $countKey = 'monitoring:total_requests';

        $currentAvg = Cache::get($cacheKey, 0);
        $currentCount = Cache::get($countKey, 0);

        // Calculate rolling average
        $newCount = $currentCount + 1;
        $newAvg = (($currentAvg * $currentCount) + $ms) / $newCount;

        Cache::put($cacheKey, $newAvg, 3600);
        Cache::put($countKey, $newCount, 3600);

        // Record metric
        $this->recordMetric('api.response_time', $ms);
    }

    /**
     * Record error
     */
    public function recordError(): void
    {
        $errorKey = 'monitoring:error_requests';
        $currentErrors = Cache::get($errorKey, 0);
        Cache::put($errorKey, $currentErrors + 1, 3600);
    }

    /**
     * Reset metrics
     */
    public function resetMetrics(): void
    {
        Cache::forget('monitoring:avg_response_time');
        Cache::forget('monitoring:total_requests');
        Cache::forget('monitoring:error_requests');
    }

    /**
     * Run all Sprint 6.7 production monitoring checks
     */
    public function runProductionChecks(): array
    {
        $results = [
            'agent_failure_rate' => $this->checkAgentFailureRate(),
            'response_time' => $this->checkResponseTime(),
            'api_error_rate' => $this->checkApiErrorRate(),
            'queue_backlog' => $this->checkQueueBacklog(),
            'timestamp' => now()->toIso8601String(),
        ];

        Log::info('Production monitoring checks completed', $results);

        return $results;
    }

    /**
     * Check agent failure rate >10%
     *
     * Sprint 6.7: Agent failure rate monitoring
     */
    public function checkAgentFailureRate(): array
    {
        $windowMinutes = 15;
        $threshold = 10.0; // 10% failure rate

        // Get recent agent runs from last 15 minutes
        $since = now()->subMinutes($windowMinutes);

        $totalRuns = AgentRun::where('created_at', '>=', $since)->count();

        if ($totalRuns === 0) {
            return [
                'status' => 'ok',
                'failure_rate' => 0,
                'total_runs' => 0,
                'failed_runs' => 0,
            ];
        }

        $failedRuns = AgentRun::where('created_at', '>=', $since)
            ->where('status', 'failed')
            ->count();

        $failureRate = ($failedRuns / $totalRuns) * 100;

        // Record metric
        $this->metricsCollector->gauge('agent.failure_rate_percent', $failureRate);

        // Trigger alert if threshold exceeded
        if ($failureRate > $threshold) {
            $this->alertManager->alert(
                'agent_high_failure_rate',
                "Agent failure rate is {$failureRate}% (threshold: {$threshold}%)",
                [
                    'failure_rate' => $failureRate,
                    'threshold' => $threshold,
                    'total_runs' => $totalRuns,
                    'failed_runs' => $failedRuns,
                    'window_minutes' => $windowMinutes,
                ]
            );

            // Send to Sentry
            if (config('sentry.dsn')) {
                \Sentry\captureMessage("High agent failure rate: {$failureRate}%", \Sentry\Severity::error());
                \Sentry\withScope(function (Scope $scope) use ($failureRate, $totalRuns, $failedRuns) {
                    $scope->setContext('agent_failure_rate', [
                        'failure_rate' => $failureRate,
                        'total_runs' => $totalRuns,
                        'failed_runs' => $failedRuns,
                    ]);
                });
            }

            return [
                'status' => 'critical',
                'failure_rate' => round($failureRate, 2),
                'total_runs' => $totalRuns,
                'failed_runs' => $failedRuns,
                'threshold' => $threshold,
            ];
        }

        return [
            'status' => 'ok',
            'failure_rate' => round($failureRate, 2),
            'total_runs' => $totalRuns,
            'failed_runs' => $failedRuns,
        ];
    }

    /**
     * Check response time >30 seconds
     *
     * Sprint 6.7: Response time monitoring
     */
    public function checkResponseTime(): array
    {
        $threshold = 30000; // 30 seconds in milliseconds

        // Get p95 response time from metrics
        $stats = $this->metricsCollector->getStats('http.request.duration');

        if (! $stats || ! isset($stats['p95'])) {
            return [
                'status' => 'ok',
                'p95_response_time_ms' => 0,
                'threshold_ms' => $threshold,
            ];
        }

        $p95ResponseTime = $stats['p95'];

        // Record metric
        $this->metricsCollector->gauge('api.response_time_p95_ms', $p95ResponseTime);

        // Trigger alert if threshold exceeded
        if ($p95ResponseTime > $threshold) {
            $this->alertManager->alert(
                'high_response_time',
                "P95 response time is {$p95ResponseTime}ms (threshold: {$threshold}ms)",
                [
                    'p95_response_time_ms' => $p95ResponseTime,
                    'threshold_ms' => $threshold,
                    'p50' => $stats['p50'] ?? null,
                    'p99' => $stats['p99'] ?? null,
                    'avg' => $stats['avg'] ?? null,
                ]
            );

            // Send to Sentry
            if (config('sentry.dsn')) {
                \Sentry\captureMessage("High response time: {$p95ResponseTime}ms", \Sentry\Severity::warning());
            }

            return [
                'status' => 'warning',
                'p95_response_time_ms' => round($p95ResponseTime, 2),
                'threshold_ms' => $threshold,
                'stats' => $stats,
            ];
        }

        return [
            'status' => 'ok',
            'p95_response_time_ms' => round($p95ResponseTime, 2),
            'threshold_ms' => $threshold,
        ];
    }

    /**
     * Check API error rate >5%
     *
     * Sprint 6.7: API error rate monitoring
     */
    public function checkApiErrorRate(): array
    {
        $threshold = 5.0; // 5% error rate

        $errorRate = $this->getErrorRate();
        $totalRequests = Cache::get('monitoring:total_requests', 0);
        $errorRequests = Cache::get('monitoring:error_requests', 0);

        // Record metric
        $this->metricsCollector->gauge('api.error_rate_percent', $errorRate);

        // Trigger alert if threshold exceeded
        if ($errorRate > $threshold) {
            $this->alertManager->alert(
                'high_api_error_rate',
                "API error rate is {$errorRate}% (threshold: {$threshold}%)",
                [
                    'error_rate' => $errorRate,
                    'threshold' => $threshold,
                    'total_requests' => $totalRequests,
                    'error_requests' => $errorRequests,
                ]
            );

            // Send to Sentry
            if (config('sentry.dsn')) {
                \Sentry\captureMessage("High API error rate: {$errorRate}%", \Sentry\Severity::error());
                \Sentry\withScope(function (Scope $scope) use ($errorRate, $totalRequests, $errorRequests) {
                    $scope->setContext('api_error_rate', [
                        'error_rate' => $errorRate,
                        'total_requests' => $totalRequests,
                        'error_requests' => $errorRequests,
                    ]);
                });
            }

            return [
                'status' => 'critical',
                'error_rate' => round($errorRate, 2),
                'total_requests' => $totalRequests,
                'error_requests' => $errorRequests,
                'threshold' => $threshold,
            ];
        }

        return [
            'status' => 'ok',
            'error_rate' => round($errorRate, 2),
            'total_requests' => $totalRequests,
            'error_requests' => $errorRequests,
        ];
    }

    /**
     * Check queue backlog >100 jobs
     *
     * Sprint 6.7: Queue backlog monitoring
     */
    public function checkQueueBacklog(): array
    {
        $threshold = 100; // 100 jobs

        // Get queue sizes for all queues
        $queues = ['default', 'agents', 'textract'];
        $totalBacklog = 0;
        $queueSizes = [];

        foreach ($queues as $queue) {
            try {
                $size = $this->getQueueSizeForQueue($queue);
                $queueSizes[$queue] = $size;
                $totalBacklog += $size;
            } catch (\Exception $e) {
                Log::warning("Failed to get queue size for {$queue}", [
                    'error' => $e->getMessage(),
                ]);
                $queueSizes[$queue] = 0;
            }
        }

        // Record metrics
        $this->metricsCollector->gauge('queue.total_backlog', $totalBacklog);
        foreach ($queueSizes as $queue => $size) {
            $this->metricsCollector->gauge("queue.{$queue}.size", $size);
        }

        // Trigger alert if threshold exceeded
        if ($totalBacklog > $threshold) {
            $this->alertManager->alert(
                'high_queue_backlog',
                "Queue backlog is {$totalBacklog} jobs (threshold: {$threshold})",
                [
                    'total_backlog' => $totalBacklog,
                    'threshold' => $threshold,
                    'queue_sizes' => $queueSizes,
                ]
            );

            // Send to Sentry
            if (config('sentry.dsn')) {
                \Sentry\captureMessage("High queue backlog: {$totalBacklog} jobs", \Sentry\Severity::warning());
                \Sentry\withScope(function (Scope $scope) use ($totalBacklog, $queueSizes) {
                    $scope->setContext('queue_backlog', [
                        'total_backlog' => $totalBacklog,
                        'queue_sizes' => $queueSizes,
                    ]);
                });
            }

            return [
                'status' => 'warning',
                'total_backlog' => $totalBacklog,
                'threshold' => $threshold,
                'queue_sizes' => $queueSizes,
            ];
        }

        return [
            'status' => 'ok',
            'total_backlog' => $totalBacklog,
            'threshold' => $threshold,
            'queue_sizes' => $queueSizes,
        ];
    }

    /**
     * Get queue size for a specific queue
     */
    protected function getQueueSizeForQueue(string $queue): int
    {
        try {
            // For database queue driver
            if (config('queue.default') === 'database') {
                return DB::table('jobs')
                    ->where('queue', $queue)
                    ->whereNull('reserved_at')
                    ->count();
            }

            // For Redis queue driver
            try {
                $connection = Queue::connection();
                if (method_exists($connection, 'size')) {
                    return $connection->size($queue);
                }
            } catch (\Exception $e) {
                Log::debug('Queue size method not available', ['error' => $e->getMessage()]);
            }

            return 0;
        } catch (\Exception $e) {
            Log::warning('Failed to get queue size', [
                'queue' => $queue,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Track agent execution error with Sentry context
     *
     * Sprint 6.7: Enhanced agent error tracking
     */
    public function trackAgentError(\Throwable $exception, AgentRun $run): void
    {
        if (! config('sentry.dsn')) {
            return;
        }

        \Sentry\withScope(function (Scope $scope) use ($exception, $run) {
            $scope->setTag('agent_name', $run->agent_name);
            $scope->setTag('agent_run_id', (string) $run->id);
            $scope->setContext('agent_run', [
                'id' => $run->id,
                'agent_name' => $run->agent_name,
                'objective' => $run->objective,
                'status' => $run->status,
                'iteration' => $run->iteration ?? 0,
            ]);
            $scope->setLevel(\Sentry\Severity::error());

            \Sentry\captureException($exception);
        });

        Log::error('Agent execution error tracked in Sentry', [
            'agent_name' => $run->agent_name,
            'run_id' => $run->id,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Track orchestration error with Sentry context
     *
     * Sprint 6.7: Enhanced orchestration error tracking
     */
    public function trackOrchestrationError(\Throwable $exception, OrchestrationLog $log): void
    {
        if (! config('sentry.dsn')) {
            return;
        }

        \Sentry\withScope(function (Scope $scope) use ($exception, $log) {
            $scope->setTag('orchestration_id', $log->orchestration_id);
            $scope->setContext('orchestration', [
                'id' => $log->orchestration_id,
                'task_description' => $log->task_description,
                'agent_pipeline' => $log->agent_pipeline,
                'status' => $log->status,
                'completed_agents' => $log->completed_agents ?? 0,
                'failed_agents' => $log->failed_agents ?? 0,
            ]);
            $scope->setLevel(\Sentry\Severity::error());

            \Sentry\captureException($exception);
        });

        Log::error('Orchestration error tracked in Sentry', [
            'orchestration_id' => $log->orchestration_id,
            'error' => $exception->getMessage(),
        ]);
    }
}
