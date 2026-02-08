<?php

namespace App\Services\Monitoring;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Laudis\Neo4j\ClientBuilder;

/**
 * Application Monitoring Service
 *
 * Tracks application health metrics, error rates, and performance.
 */
class ApplicationMonitor
{
    public function __construct(
        protected MetricsCollector $metrics,
        protected AlertManager $alertManager
    ) {}

    /**
     * Record request performance
     */
    public function recordRequest(
        string $method,
        string $uri,
        int $statusCode,
        float $durationMs,
        int $queryCount = 0,
        int $queryTimeMs = 0
    ): void {
        try {
            // Record response time
            $this->metrics->timing('http.request.duration', $durationMs, [
                'method' => $method,
                'status' => $statusCode,
            ]);

            // Record request count
            $this->metrics->increment('http.request.count', 1, [
                'method' => $method,
                'status' => $statusCode,
            ]);

            // Record error if status >= 500
            if ($statusCode >= 500) {
                $this->recordError('http', $statusCode, [
                    'method' => $method,
                    'uri' => $uri,
                ]);
            }

            // Record slow requests (> 2 seconds)
            if ($durationMs > 2000) {
                $this->metrics->increment('http.request.slow', 1, [
                    'method' => $method,
                    'uri' => $uri,
                ]);

                Log::warning('Slow request detected', [
                    'method' => $method,
                    'uri' => $uri,
                    'duration_ms' => $durationMs,
                    'query_count' => $queryCount,
                ]);
            }

            // Record query metrics
            if ($queryCount > 0) {
                $this->metrics->gauge('http.request.queries', $queryCount);
                $this->metrics->timing('http.request.query_time', $queryTimeMs);

                // Alert on excessive queries (N+1 detection)
                if ($queryCount > 50) {
                    $this->alertManager->alert(
                        'high_query_count',
                        "High query count detected: {$queryCount} queries on {$method} {$uri}",
                        [
                            'query_count' => $queryCount,
                            'uri' => $uri,
                            'method' => $method,
                        ]
                    );
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to record request metrics', [
                'method' => $method,
                'uri' => $uri,
                'error' => $e->getMessage(),
            ]);
            // Don't throw - metrics recording should never break the application
        }
    }

    /**
     * Record an error occurrence
     */
    public function recordError(string $type, int $code, array $context = []): void
    {
        try {
            $this->metrics->increment('error.count', 1, [
                'type' => $type,
                'code' => $code,
            ]);

            // Check error rate and alert if high
            $this->checkErrorRate($type);
        } catch (\Exception $e) {
            Log::error('Failed to record error metrics', [
                'type' => $type,
                'code' => $code,
                'error' => $e->getMessage(),
            ]);
            // Don't throw - metrics recording should never break the application
        }
    }

    /**
     * Record exception
     */
    public function recordException(\Throwable $exception, array $context = []): void
    {
        try {
            $this->metrics->increment('exception.count', 1, [
                'class' => get_class($exception),
            ]);

            // Record critical exceptions immediately
            if ($this->isCriticalException($exception)) {
                $this->alertManager->alert(
                    'critical_exception',
                    'Critical exception: '.get_class($exception).' - '.$exception->getMessage(),
                    array_merge($context, [
                        'exception_class' => get_class($exception),
                        'message' => $exception->getMessage(),
                        'file' => $exception->getFile(),
                        'line' => $exception->getLine(),
                    ])
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to record exception metrics', [
                'exception_class' => get_class($exception),
                'error' => $e->getMessage(),
            ]);
            // Don't throw - metrics recording should never break the application
        }
    }

    /**
     * Record queue job metrics
     */
    public function recordJobProcessed(string $jobClass, float $durationMs, bool $failed = false): void
    {
        try {
            $this->metrics->timing('queue.job.duration', $durationMs, [
                'job' => $jobClass,
            ]);

            $this->metrics->increment('queue.job.processed', 1, [
                'job' => $jobClass,
                'status' => $failed ? 'failed' : 'success',
            ]);

            if ($failed) {
                $this->metrics->increment('queue.job.failed', 1, [
                    'job' => $jobClass,
                ]);

                // Check job failure rate
                $this->checkJobFailureRate($jobClass);
            }
        } catch (\Exception $e) {
            Log::error('Failed to record job metrics', [
                'job_class' => $jobClass,
                'error' => $e->getMessage(),
            ]);
            // Don't throw - metrics recording should never break the application
        }
    }

    /**
     * Record database query
     */
    public function recordQuery(string $sql, float $timeMs, array $bindings = []): void
    {
        try {
            $this->metrics->timing('database.query.duration', $timeMs);
            $this->metrics->increment('database.query.count');

            // Alert on slow queries (> 1 second)
            if ($timeMs > 1000) {
                $this->metrics->increment('database.query.slow');

                Log::warning('Slow database query detected', [
                    'duration_ms' => $timeMs,
                    'sql' => substr($sql, 0, 200),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to record query metrics', [
                'error' => $e->getMessage(),
            ]);
            // Don't throw - metrics recording should never break the application
        }
    }

    /**
     * Record cache operation
     */
    public function recordCacheOperation(string $operation, ?bool $hit = null): void
    {
        try {
            $this->metrics->increment('cache.operation', 1, [
                'operation' => $operation,
            ]);

            if ($operation === 'get' && $hit !== null) {
                $this->metrics->increment('cache.hit', $hit ? 1 : 0);
                $this->metrics->increment('cache.miss', $hit ? 0 : 1);
            }
        } catch (\Exception $e) {
            Log::error('Failed to record cache operation metrics', [
                'operation' => $operation,
                'error' => $e->getMessage(),
            ]);
            // Don't throw - metrics recording should never break the application
        }
    }

    /**
     * Get current application health status
     */
    public function getHealthStatus(): array
    {
        try {
            return [
                'status' => $this->determineHealthStatus(),
                'timestamp' => now()->toIso8601String(),
                'metrics' => [
                    'error_rate' => $this->getErrorRate(),
                    'avg_response_time' => $this->getAverageResponseTime(),
                    'slow_requests' => $this->getSlowRequestCount(),
                    'query_count' => $this->getAverageQueryCount(),
                    'queue_size' => $this->getQueueSize(),
                    'cache_hit_rate' => $this->getCacheHitRate(),
                ],
                'alerts' => $this->alertManager->getActiveAlerts(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get health status', [
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'unknown',
                'timestamp' => now()->toIso8601String(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get detailed performance report
     */
    public function getPerformanceReport(int $minutes = 60): array
    {
        try {
            return [
                'period_minutes' => $minutes,
                'http' => [
                    'total_requests' => $this->metrics->getCounter('http.request.count'),
                    'response_time' => $this->metrics->getStats('http.request.duration'),
                    'slow_requests' => $this->metrics->getCounter('http.request.slow'),
                    'errors' => $this->metrics->getCounter('error.count', ['type' => 'http']),
                ],
                'database' => [
                    'total_queries' => $this->metrics->getCounter('database.query.count'),
                    'query_time' => $this->metrics->getStats('database.query.duration'),
                    'slow_queries' => $this->metrics->getCounter('database.query.slow'),
                ],
                'cache' => [
                    'operations' => $this->metrics->getCounter('cache.operation'),
                    'hits' => $this->metrics->getCounter('cache.hit'),
                    'misses' => $this->metrics->getCounter('cache.miss'),
                    'hit_rate' => $this->getCacheHitRate(),
                ],
                'queue' => [
                    'processed' => $this->metrics->getCounter('queue.job.processed'),
                    'failed' => $this->metrics->getCounter('queue.job.failed'),
                    'avg_duration' => $this->metrics->getStats('queue.job.duration')['avg'] ?? 0,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get performance report', [
                'error' => $e->getMessage(),
            ]);

            return [
                'period_minutes' => $minutes,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check database health
     */
    public function checkDatabaseHealth(): array
    {
        try {
            $start = microtime(true);
            $connection = DB::connection();

            // Test basic connectivity
            $connection->getPdo();

            // Test query execution
            $result = DB::select('SELECT 1 as test');

            $duration = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'healthy',
                'driver' => $connection->getDriverName(),
                'database' => $connection->getDatabaseName(),
                'host' => config('database.connections.'.config('database.default').'.host'),
                'port' => config('database.connections.'.config('database.default').'.port'),
                'connected' => true,
                'query_test' => 'passed',
                'response_time_ms' => $duration,
                'timestamp' => now()->toIso8601String(),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'unhealthy',
                'connected' => false,
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'timestamp' => now()->toIso8601String(),
            ];
        }
    }

    /**
     * Check Neo4j graph database health
     */
    public function checkNeo4jHealth(): array
    {
        if (! config('neo4j.enabled', false)) {
            return [
                'status' => 'disabled',
                'enabled' => false,
                'message' => 'Neo4j is disabled in configuration',
                'timestamp' => now()->toIso8601String(),
            ];
        }

        try {
            $start = microtime(true);

            // Build Neo4j client
            $client = ClientBuilder::create()
                ->withDriver('default', config('neo4j.uri'),
                    \Laudis\Neo4j\Authentication\Authenticate::basic(
                        config('neo4j.username', 'neo4j'),
                        config('neo4j.password', '')
                    )
                )
                ->build();

            // Test query
            $result = $client->run('RETURN 1 as test');

            // Get node/relationship counts
            $stats = $client->run('MATCH (n) RETURN count(n) as node_count')->first();
            $relStats = $client->run('MATCH ()-[r]->() RETURN count(r) as rel_count')->first();

            $duration = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'healthy',
                'enabled' => true,
                'connected' => true,
                'uri' => config('neo4j.uri'),
                'query_test' => 'passed',
                'node_count' => $stats->get('node_count'),
                'relationship_count' => $relStats->get('rel_count'),
                'response_time_ms' => $duration,
                'timestamp' => now()->toIso8601String(),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'unhealthy',
                'enabled' => true,
                'connected' => false,
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'timestamp' => now()->toIso8601String(),
            ];
        }
    }

    /**
     * Check OpenAI API health
     */
    public function checkOpenAIHealth(): array
    {
        $apiKey = config('openai.api_key');

        if (! $apiKey) {
            return [
                'status' => 'unconfigured',
                'configured' => false,
                'error' => 'OpenAI API key not configured',
                'timestamp' => now()->toIso8601String(),
            ];
        }

        try {
            $start = microtime(true);

            // Simple models list call (lightweight, doesn't cost tokens)
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$apiKey,
                ])
                ->get('https://api.openai.com/v1/models');

            $duration = round((microtime(true) - $start) * 1000, 2);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'status' => 'healthy',
                    'configured' => true,
                    'connected' => true,
                    'api_test' => 'passed',
                    'models_available' => count($data['data'] ?? []),
                    'response_time_ms' => $duration,
                    'timestamp' => now()->toIso8601String(),
                ];
            } else {
                return [
                    'status' => 'unhealthy',
                    'configured' => true,
                    'connected' => false,
                    'http_status' => $response->status(),
                    'error' => $response->json()['error']['message'] ?? 'Unknown error',
                    'timestamp' => now()->toIso8601String(),
                ];
            }
        } catch (\Throwable $e) {
            return [
                'status' => 'unhealthy',
                'configured' => true,
                'connected' => false,
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'timestamp' => now()->toIso8601String(),
            ];
        }
    }

    /**
     * Check cache driver health
     */
    public function checkCacheHealth(): array
    {
        try {
            $start = microtime(true);
            $testKey = 'health_check_'.uniqid();
            $testValue = 'test_'.time();

            // Test write
            Cache::put($testKey, $testValue, 10);

            // Test read
            $retrieved = Cache::get($testKey);

            // Test delete
            Cache::forget($testKey);

            $duration = round((microtime(true) - $start) * 1000, 2);

            $writeSuccess = $retrieved === $testValue;

            return [
                'status' => $writeSuccess ? 'healthy' : 'unhealthy',
                'driver' => config('cache.default'),
                'write_test' => $writeSuccess ? 'passed' : 'failed',
                'read_test' => $writeSuccess ? 'passed' : 'failed',
                'delete_test' => 'passed',
                'response_time_ms' => $duration,
                'timestamp' => now()->toIso8601String(),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'unhealthy',
                'driver' => config('cache.default'),
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'timestamp' => now()->toIso8601String(),
            ];
        }
    }

    /**
     * Check queue worker health
     */
    public function checkQueueHealth(): array
    {
        try {
            $driver = config('queue.default');

            // Get queue sizes
            $defaultSize = Queue::size('default');
            $textractSize = Queue::size('textract');
            $agentsSize = Queue::size('agents');

            // Get failed jobs count
            $failedJobs = DB::table('failed_jobs')->count();

            // Determine health based on queue backlog
            $totalBacklog = $defaultSize + $textractSize + $agentsSize;
            $status = 'healthy';

            if ($totalBacklog > 1000) {
                $status = 'unhealthy';
            } elseif ($totalBacklog > 100) {
                $status = 'degraded';
            }

            // Check for too many failed jobs
            if ($failedJobs > 50) {
                $status = 'degraded';
            } elseif ($failedJobs > 200) {
                $status = 'unhealthy';
            }

            return [
                'status' => $status,
                'driver' => $driver,
                'queues' => [
                    'default' => $defaultSize,
                    'textract' => $textractSize,
                    'agents' => $agentsSize,
                ],
                'total_backlog' => $totalBacklog,
                'failed_jobs' => $failedJobs,
                'timestamp' => now()->toIso8601String(),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'timestamp' => now()->toIso8601String(),
            ];
        }
    }

    /**
     * Get comprehensive system health report
     */
    public function getSystemHealthReport(): array
    {
        try {
            Log::info('Generating system health report');
            $startTime = microtime(true);

            $checks = [
                'database' => $this->checkDatabaseHealth(),
                'neo4j' => $this->checkNeo4jHealth(),
                'openai' => $this->checkOpenAIHealth(),
                'cache' => $this->checkCacheHealth(),
                'queue' => $this->checkQueueHealth(),
            ];

            // Determine overall status
            $statuses = array_column($checks, 'status');
            $overallStatus = 'healthy';

            if (in_array('unhealthy', $statuses)) {
                $overallStatus = 'unhealthy';
            } elseif (in_array('degraded', $statuses)) {
                $overallStatus = 'degraded';
            } elseif (in_array('unconfigured', $statuses) || in_array('disabled', $statuses)) {
                // Only services that are enabled and configured count toward health
                $enabledStatuses = array_filter($statuses, fn ($s) => ! in_array($s, ['unconfigured', 'disabled']));
                if (in_array('unhealthy', $enabledStatuses)) {
                    $overallStatus = 'unhealthy';
                } elseif (in_array('degraded', $enabledStatuses)) {
                    $overallStatus = 'degraded';
                }
            }

            Log::info('System health report generated', [
                'overall_status' => $overallStatus,
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return [
                'status' => $overallStatus,
                'timestamp' => now()->toIso8601String(),
                'services' => $checks,
                'summary' => [
                    'healthy' => count(array_filter($statuses, fn ($s) => $s === 'healthy')),
                    'unhealthy' => count(array_filter($statuses, fn ($s) => $s === 'unhealthy')),
                    'degraded' => count(array_filter($statuses, fn ($s) => $s === 'degraded')),
                    'disabled' => count(array_filter($statuses, fn ($s) => $s === 'disabled')),
                    'unconfigured' => count(array_filter($statuses, fn ($s) => $s === 'unconfigured')),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to generate system health report', [
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'unknown',
                'timestamp' => now()->toIso8601String(),
                'error' => $e->getMessage(),
                'services' => [],
                'summary' => [
                    'healthy' => 0,
                    'unhealthy' => 0,
                    'degraded' => 0,
                    'disabled' => 0,
                    'unconfigured' => 0,
                ],
            ];
        }
    }

    /**
     * Check error rate and alert if high
     */
    protected function checkErrorRate(string $type): void
    {
        try {
            $errorCount = $this->metrics->getCounter('error.count', ['type' => $type]);
            $totalCount = $this->metrics->getCounter('http.request.count');

            if ($totalCount > 0) {
                $errorRate = ($errorCount / $totalCount) * 100;

                if ($errorRate > 10) { // > 10% error rate
                    $this->alertManager->alert(
                        'high_error_rate',
                        "High error rate detected: {$errorRate}% for type {$type}",
                        [
                            'type' => $type,
                            'error_rate' => $errorRate,
                            'error_count' => $errorCount,
                            'total_count' => $totalCount,
                        ]
                    );
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to check error rate', [
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check job failure rate
     */
    protected function checkJobFailureRate(string $jobClass): void
    {
        try {
            $failed = $this->metrics->getCounter('queue.job.failed', ['job' => $jobClass]);
            $total = $this->metrics->getCounter('queue.job.processed', ['job' => $jobClass]);

            if ($total > 0) {
                $failureRate = ($failed / $total) * 100;

                if ($failureRate > 20) { // > 20% failure rate
                    $this->alertManager->alert(
                        'high_job_failure_rate',
                        "High job failure rate: {$failureRate}% for {$jobClass}",
                        [
                            'job_class' => $jobClass,
                            'failure_rate' => $failureRate,
                            'failed_count' => $failed,
                            'total_count' => $total,
                        ]
                    );
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to check job failure rate', [
                'job_class' => $jobClass,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Determine if exception is critical
     */
    protected function isCriticalException(\Throwable $exception): bool
    {
        $criticalExceptions = [
            \ErrorException::class,
            \RuntimeException::class,
            \PDOException::class,
        ];

        foreach ($criticalExceptions as $class) {
            if ($exception instanceof $class) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine overall health status
     */
    protected function determineHealthStatus(): string
    {
        $errorRate = $this->getErrorRate();
        $avgResponseTime = $this->getAverageResponseTime();

        if ($errorRate > 25 || $avgResponseTime > 5000) {
            return 'critical';
        }

        if ($errorRate > 10 || $avgResponseTime > 2000) {
            return 'degraded';
        }

        return 'healthy';
    }

    /**
     * Get current error rate percentage
     */
    protected function getErrorRate(): float
    {
        try {
            $errors = $this->metrics->getCounter('error.count');
            $total = $this->metrics->getCounter('http.request.count');

            return $total > 0 ? round(($errors / $total) * 100, 2) : 0;
        } catch (\Exception $e) {
            Log::debug('Failed to get error rate', [
                'error' => $e->getMessage(),
            ]);

            return 0.0;
        }
    }

    /**
     * Get average response time
     */
    protected function getAverageResponseTime(): float
    {
        try {
            $stats = $this->metrics->getStats('http.request.duration');

            return round($stats['avg'] ?? 0, 2);
        } catch (\Exception $e) {
            Log::debug('Failed to get average response time', [
                'error' => $e->getMessage(),
            ]);

            return 0.0;
        }
    }

    /**
     * Get slow request count
     */
    protected function getSlowRequestCount(): int
    {
        try {
            return $this->metrics->getCounter('http.request.slow');
        } catch (\Exception $e) {
            Log::debug('Failed to get slow request count', [
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Get average query count per request
     */
    protected function getAverageQueryCount(): float
    {
        try {
            $stats = $this->metrics->getStats('http.request.queries');

            return round($stats['avg'] ?? 0, 2);
        } catch (\Exception $e) {
            Log::debug('Failed to get average query count', [
                'error' => $e->getMessage(),
            ]);

            return 0.0;
        }
    }

    /**
     * Get queue size
     */
    protected function getQueueSize(): int
    {
        try {
            return Queue::size('default');
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Get cache hit rate percentage
     */
    protected function getCacheHitRate(): float
    {
        try {
            $hits = $this->metrics->getCounter('cache.hit');
            $misses = $this->metrics->getCounter('cache.miss');
            $total = $hits + $misses;

            return $total > 0 ? round(($hits / $total) * 100, 2) : 0;
        } catch (\Exception $e) {
            Log::debug('Failed to get cache hit rate', [
                'error' => $e->getMessage(),
            ]);

            return 0.0;
        }
    }
}
