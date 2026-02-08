<?php

namespace App\Services\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Monitor PostgreSQL connection pool health and statistics.
 *
 * Provides real-time metrics about database connections including:
 * - Active connection count
 * - Maximum connections available
 * - Pool utilization percentage
 * - Connection state breakdown
 * - Long-running query detection
 */
class ConnectionPoolMonitor
{
    /**
     * Get the number of active connections to the current database.
     *
     * @return int Number of active connections
     */
    public function getActiveConnections(): int
    {
        try {
            $result = DB::select('
                SELECT count(*) as connections
                FROM pg_stat_activity
                WHERE datname = current_database()
                AND state IS NOT NULL
            ');

            return (int) $result[0]->connections;
        } catch (\Exception $e) {
            Log::error('Failed to get active connections', [
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Get the maximum number of connections PostgreSQL allows.
     *
     * @return int Maximum connections configured in PostgreSQL
     */
    public function getMaxConnections(): int
    {
        try {
            $result = DB::select('SHOW max_connections');

            return (int) $result[0]->max_connections;
        } catch (\Exception $e) {
            Log::error('Failed to get max connections', [
                'error' => $e->getMessage(),
            ]);

            return 100; // Default fallback
        }
    }

    /**
     * Calculate pool utilization as a percentage.
     *
     * @return float Utilization percentage (0-100)
     */
    public function getPoolUtilization(): float
    {
        $active = $this->getActiveConnections();
        $max = $this->getMaxConnections();

        if ($max === 0) {
            return 0.0;
        }

        return round(($active / $max) * 100, 2);
    }

    /**
     * Get detailed connection statistics grouped by state.
     *
     * Returns breakdown of connections by their current state:
     * - active: Currently executing queries
     * - idle: Connected but not executing
     * - idle in transaction: In transaction but not executing
     * - waiting: Waiting for lock
     *
     * @return array Array of objects with state, count, and max_duration
     */
    public function getConnectionStats(): array
    {
        try {
            $stats = DB::select("
                SELECT
                    COALESCE(state, 'unknown') as state,
                    count(*) as count,
                    EXTRACT(EPOCH FROM max(COALESCE(now() - state_change, interval '0'))) as max_duration_seconds
                FROM pg_stat_activity
                WHERE datname = current_database()
                GROUP BY state
                ORDER BY count DESC
            ");

            return array_map(function ($stat) {
                return [
                    'state' => $stat->state,
                    'count' => (int) $stat->count,
                    'max_duration' => round((float) $stat->max_duration_seconds, 2),
                ];
            }, $stats);
        } catch (\Exception $e) {
            Log::error('Failed to get connection stats', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get list of idle connections.
     *
     * @return array Array of idle connection details
     */
    public function getIdleConnections(): array
    {
        try {
            $connections = DB::select("
                SELECT
                    pid,
                    usename as username,
                    application_name,
                    client_addr as client_address,
                    state,
                    EXTRACT(EPOCH FROM (now() - state_change)) as idle_duration_seconds
                FROM pg_stat_activity
                WHERE datname = current_database()
                AND state = 'idle'
                ORDER BY state_change ASC
            ");

            return array_map(function ($conn) {
                return [
                    'pid' => $conn->pid,
                    'username' => $conn->username,
                    'application_name' => $conn->application_name,
                    'client_address' => $conn->client_address,
                    'state' => $conn->state,
                    'idle_duration' => round((float) $conn->idle_duration_seconds, 2),
                ];
            }, $connections);
        } catch (\Exception $e) {
            Log::error('Failed to get idle connections', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get list of long-running queries.
     *
     * @param  int  $thresholdSeconds  Queries running longer than this are considered long
     * @return array Array of long-running query details
     */
    public function getLongRunningQueries(int $thresholdSeconds = 60): array
    {
        try {
            $queries = DB::select("
                SELECT
                    pid,
                    usename as username,
                    application_name,
                    client_addr as client_address,
                    state,
                    EXTRACT(EPOCH FROM (now() - query_start)) as duration_seconds,
                    LEFT(query, 200) as query_preview
                FROM pg_stat_activity
                WHERE datname = current_database()
                AND state = 'active'
                AND query NOT LIKE '%pg_stat_activity%'
                AND (now() - query_start) > interval '{$thresholdSeconds} seconds'
                ORDER BY query_start ASC
            ");

            return array_map(function ($query) {
                return [
                    'pid' => $query->pid,
                    'username' => $query->username,
                    'application_name' => $query->application_name,
                    'client_address' => $query->client_address,
                    'state' => $query->state,
                    'duration' => round((float) $query->duration_seconds, 2),
                    'query_preview' => $query->query_preview,
                ];
            }, $queries);
        } catch (\Exception $e) {
            Log::error('Failed to get long-running queries', [
                'error' => $e->getMessage(),
                'threshold' => $thresholdSeconds,
            ]);

            return [];
        }
    }

    /**
     * Get comprehensive pool health summary.
     *
     * @return array Complete health summary including all metrics
     */
    public function getHealthSummary(): array
    {
        return [
            'active_connections' => $this->getActiveConnections(),
            'max_connections' => $this->getMaxConnections(),
            'utilization_percentage' => $this->getPoolUtilization(),
            'connection_stats' => $this->getConnectionStats(),
            'idle_connections_count' => count($this->getIdleConnections()),
            'long_running_queries_count' => count($this->getLongRunningQueries()),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Check if the connection pool is healthy.
     *
     * @return array Health status with details
     */
    public function checkHealth(): array
    {
        $utilization = $this->getPoolUtilization();
        $longRunning = $this->getLongRunningQueries(300); // 5 minute threshold

        if ($utilization > 90) {
            return [
                'status' => 'unhealthy',
                'message' => "Connection pool critically high at {$utilization}%",
                'utilization' => $utilization,
                'long_running_queries' => count($longRunning),
            ];
        }

        if ($utilization > 70 || count($longRunning) > 5) {
            return [
                'status' => 'degraded',
                'message' => "Connection pool elevated at {$utilization}%",
                'utilization' => $utilization,
                'long_running_queries' => count($longRunning),
            ];
        }

        return [
            'status' => 'healthy',
            'message' => 'Connection pool operating normally',
            'utilization' => $utilization,
            'long_running_queries' => count($longRunning),
        ];
    }
}
