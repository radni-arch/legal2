<?php

namespace App\HealthChecks;

use App\Services\Database\ConnectionPoolMonitor;
use Illuminate\Support\Facades\Log;

/**
 * Health check for database connection pool.
 *
 * Monitors connection pool utilization and returns health status:
 * - Unhealthy: >90% utilization
 * - Degraded: >70% utilization
 * - Healthy: <70% utilization
 */
class DatabaseConnectionPoolHealthCheck
{
    private ConnectionPoolMonitor $monitor;

    public function __construct(ConnectionPoolMonitor $monitor)
    {
        $this->monitor = $monitor;
    }

    /**
     * Execute the health check.
     */
    public function __invoke(): HealthCheckResult
    {
        try {
            $utilization = $this->monitor->getPoolUtilization();
            $active = $this->monitor->getActiveConnections();
            $max = $this->monitor->getMaxConnections();
            $longRunning = $this->monitor->getLongRunningQueries(300); // 5 min threshold

            $data = [
                'utilization' => $utilization,
                'active' => $active,
                'max' => $max,
                'long_running_queries' => count($longRunning),
            ];

            // Critical: >90% utilization
            if ($utilization > 90) {
                Log::warning('Database connection pool unhealthy', $data);

                return HealthCheckResult::unhealthy(
                    "Connection pool at critical capacity: {$utilization}%",
                    $data
                );
            }

            // Warning: >70% utilization or too many long-running queries
            if ($utilization > 70 || count($longRunning) > 10) {
                Log::info('Database connection pool degraded', $data);

                return HealthCheckResult::degraded(
                    "Connection pool elevated: {$utilization}%",
                    $data
                );
            }

            // Healthy
            return HealthCheckResult::healthy($data, 'Connection pool operating normally');

        } catch (\Exception $e) {
            Log::error('Health check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return HealthCheckResult::unhealthy(
                'Failed to check database connection pool',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Get detailed health information.
     */
    public function getDetailedHealth(): array
    {
        $result = $this->__invoke();

        return [
            'status' => $result->status,
            'message' => $result->message,
            'metrics' => $result->data,
            'connection_stats' => $this->monitor->getConnectionStats(),
            'idle_connections' => count($this->monitor->getIdleConnections()),
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
