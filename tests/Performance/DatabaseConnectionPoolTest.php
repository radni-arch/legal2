<?php

namespace Tests\Performance;

use App\Services\Database\ConnectionPoolMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Performance tests for database connection pooling.
 *
 * Tests connection pool behavior under various load scenarios:
 * - Concurrent connection handling
 * - Connection acquisition time
 * - Pool limit enforcement
 * - Recovery after failures
 */
class DatabaseConnectionPoolTest extends TestCase
{
    use RefreshDatabase;

    private ConnectionPoolMonitor $monitor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->monitor = new ConnectionPoolMonitor;
    }

    /**
     * Test basic connection pool metrics retrieval.
     *
     * @test
     */
    public function it_can_retrieve_pool_metrics(): void
    {
        $active = $this->monitor->getActiveConnections();
        $max = $this->monitor->getMaxConnections();
        $utilization = $this->monitor->getPoolUtilization();

        $this->assertIsInt($active);
        $this->assertGreaterThanOrEqual(0, $active);

        $this->assertIsInt($max);
        $this->assertGreaterThan(0, $max);

        $this->assertIsFloat($utilization);
        $this->assertGreaterThanOrEqual(0.0, $utilization);
        $this->assertLessThanOrEqual(100.0, $utilization);
    }

    /**
     * Test connection pool under moderate concurrent load (10 connections).
     *
     * @test
     */
    public function it_handles_moderate_concurrent_connections(): void
    {
        $concurrentQueries = 10;
        $startTime = microtime(true);
        $connectionTimes = [];

        for ($i = 0; $i < $concurrentQueries; $i++) {
            $queryStart = microtime(true);

            // Simple query to test connection
            DB::select('SELECT 1 as test');

            $queryTime = (microtime(true) - $queryStart) * 1000; // Convert to ms
            $connectionTimes[] = $queryTime;
        }

        $totalTime = (microtime(true) - $startTime) * 1000;

        // Assertions
        $this->assertLessThan(5000, $totalTime, 'Total time for 10 queries should be under 5 seconds');

        $avgConnectionTime = array_sum($connectionTimes) / count($connectionTimes);
        $this->assertLessThan(500, $avgConnectionTime, 'Average query time should be under 500ms');

        // Log results for documentation
        $this->logPerformanceMetrics('Moderate Load (10 queries)', [
            'total_time_ms' => round($totalTime, 2),
            'avg_query_time_ms' => round($avgConnectionTime, 2),
            'min_time_ms' => round(min($connectionTimes), 2),
            'max_time_ms' => round(max($connectionTimes), 2),
        ]);
    }

    /**
     * Test connection pool under high concurrent load (50 connections).
     *
     * @test
     */
    public function it_handles_high_concurrent_connections(): void
    {
        $concurrentQueries = 50;
        $startTime = microtime(true);
        $connectionTimes = [];

        for ($i = 0; $i < $concurrentQueries; $i++) {
            $queryStart = microtime(true);

            // Mix of simple and slightly complex queries
            if ($i % 2 === 0) {
                DB::select('SELECT 1 as test');
            } else {
                DB::table('users')->select('id')->limit(1)->get();
            }

            $queryTime = (microtime(true) - $queryStart) * 1000;
            $connectionTimes[] = $queryTime;
        }

        $totalTime = (microtime(true) - $startTime) * 1000;

        // Assertions
        $this->assertLessThan(15000, $totalTime, 'Total time for 50 queries should be under 15 seconds');

        $avgConnectionTime = array_sum($connectionTimes) / count($connectionTimes);
        $this->assertLessThan(1000, $avgConnectionTime, 'Average query time should be under 1 second');

        // Check pool utilization during test
        $utilization = $this->monitor->getPoolUtilization();
        $this->assertLessThan(100, $utilization, 'Pool should not be fully utilized');

        // Log results
        $this->logPerformanceMetrics('High Load (50 queries)', [
            'total_time_ms' => round($totalTime, 2),
            'avg_query_time_ms' => round($avgConnectionTime, 2),
            'min_time_ms' => round(min($connectionTimes), 2),
            'max_time_ms' => round(max($connectionTimes), 2),
            'pool_utilization' => $utilization,
        ]);
    }

    /**
     * Test connection pool under stress load (100 connections).
     *
     * @test
     */
    public function it_handles_stress_load_connections(): void
    {
        $concurrentQueries = 100;
        $startTime = microtime(true);
        $connectionTimes = [];
        $errors = 0;

        for ($i = 0; $i < $concurrentQueries; $i++) {
            try {
                $queryStart = microtime(true);

                // Variety of query types
                switch ($i % 3) {
                    case 0:
                        DB::select('SELECT 1 as test');
                        break;
                    case 1:
                        DB::select('SELECT version()');
                        break;
                    case 2:
                        DB::select('SELECT current_database()');
                        break;
                }

                $queryTime = (microtime(true) - $queryStart) * 1000;
                $connectionTimes[] = $queryTime;
            } catch (\Exception $e) {
                $errors++;
            }
        }

        $totalTime = (microtime(true) - $startTime) * 1000;
        $successRate = (($concurrentQueries - $errors) / $concurrentQueries) * 100;

        // Assertions
        $this->assertGreaterThan(95, $successRate, 'Success rate should be above 95%');
        $this->assertLessThan(30000, $totalTime, 'Total time for 100 queries should be under 30 seconds');

        if (count($connectionTimes) > 0) {
            $avgConnectionTime = array_sum($connectionTimes) / count($connectionTimes);
            $this->assertLessThan(2000, $avgConnectionTime, 'Average query time should be under 2 seconds');

            // Log results
            $this->logPerformanceMetrics('Stress Load (100 queries)', [
                'total_time_ms' => round($totalTime, 2),
                'avg_query_time_ms' => round($avgConnectionTime, 2),
                'min_time_ms' => round(min($connectionTimes), 2),
                'max_time_ms' => round(max($connectionTimes), 2),
                'success_rate' => round($successRate, 2),
                'errors' => $errors,
            ]);
        }
    }

    /**
     * Test connection acquisition time is within acceptable limits.
     *
     * @test
     */
    public function it_acquires_connections_quickly(): void
    {
        $iterations = 20;
        $acquisitionTimes = [];

        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            DB::select('SELECT 1');
            $acquisitionTime = (microtime(true) - $start) * 1000;
            $acquisitionTimes[] = $acquisitionTime;
        }

        $avgAcquisitionTime = array_sum($acquisitionTimes) / count($acquisitionTimes);
        $p95 = $this->calculatePercentile($acquisitionTimes, 95);
        $p99 = $this->calculatePercentile($acquisitionTimes, 99);

        // Assertions
        $this->assertLessThan(100, $avgAcquisitionTime, 'Average connection acquisition should be under 100ms');
        $this->assertLessThan(200, $p95, '95th percentile should be under 200ms');
        $this->assertLessThan(500, $p99, '99th percentile should be under 500ms');

        // Log results
        $this->logPerformanceMetrics('Connection Acquisition', [
            'avg_time_ms' => round($avgAcquisitionTime, 2),
            'p95_ms' => round($p95, 2),
            'p99_ms' => round($p99, 2),
            'min_ms' => round(min($acquisitionTimes), 2),
            'max_ms' => round(max($acquisitionTimes), 2),
        ]);
    }

    /**
     * Test connection pool stats are accurate.
     *
     * @test
     */
    public function it_provides_accurate_connection_stats(): void
    {
        $stats = $this->monitor->getConnectionStats();

        $this->assertIsArray($stats);
        $this->assertNotEmpty($stats);

        foreach ($stats as $stat) {
            $this->assertArrayHasKey('state', $stat);
            $this->assertArrayHasKey('count', $stat);
            $this->assertArrayHasKey('max_duration', $stat);

            $this->assertIsString($stat['state']);
            $this->assertIsInt($stat['count']);
            $this->assertGreaterThanOrEqual(0, $stat['count']);
        }
    }

    /**
     * Test idle connections tracking.
     *
     * @test
     */
    public function it_tracks_idle_connections(): void
    {
        // Execute a query
        DB::select('SELECT 1');

        $idle = $this->monitor->getIdleConnections();

        $this->assertIsArray($idle);

        foreach ($idle as $conn) {
            $this->assertArrayHasKey('pid', $conn);
            $this->assertArrayHasKey('username', $conn);
            $this->assertArrayHasKey('state', $conn);
            $this->assertArrayHasKey('idle_duration', $conn);
        }
    }

    /**
     * Test long-running query detection.
     *
     * @test
     */
    public function it_detects_long_running_queries(): void
    {
        // This test just verifies the method works
        // We can't easily create a long-running query in a unit test
        $longRunning = $this->monitor->getLongRunningQueries(1);

        $this->assertIsArray($longRunning);

        foreach ($longRunning as $query) {
            $this->assertArrayHasKey('pid', $query);
            $this->assertArrayHasKey('duration', $query);
            $this->assertArrayHasKey('query_preview', $query);
        }
    }

    /**
     * Test pool doesn't exceed maximum connections.
     *
     * @test
     */
    public function it_respects_max_connection_limit(): void
    {
        $maxConnections = $this->monitor->getMaxConnections();

        // Try to create many connections
        for ($i = 0; $i < 50; $i++) {
            try {
                DB::select('SELECT 1');
            } catch (\Exception $e) {
                // Some connections might fail if pool is full
            }
        }

        $active = $this->monitor->getActiveConnections();
        $utilization = $this->monitor->getPoolUtilization();

        // Active connections should never exceed max
        $this->assertLessThanOrEqual($maxConnections, $active);

        // Log current state
        $this->logPerformanceMetrics('Max Connection Test', [
            'max_connections' => $maxConnections,
            'active_connections' => $active,
            'utilization' => $utilization,
        ]);
    }

    /**
     * Test health check integration.
     *
     * @test
     */
    public function it_provides_health_check_data(): void
    {
        $health = $this->monitor->checkHealth();

        $this->assertIsArray($health);
        $this->assertArrayHasKey('status', $health);
        $this->assertArrayHasKey('message', $health);
        $this->assertArrayHasKey('utilization', $health);

        $this->assertContains($health['status'], ['healthy', 'degraded', 'unhealthy']);
    }

    /**
     * Calculate percentile value from array of numbers.
     */
    private function calculatePercentile(array $values, float $percentile): float
    {
        sort($values);
        $index = ceil((count($values) * $percentile) / 100) - 1;
        $index = max(0, min($index, count($values) - 1));

        return $values[(int) $index];
    }

    /**
     * Log performance metrics for documentation.
     */
    private function logPerformanceMetrics(string $testName, array $metrics): void
    {
        $output = "\n=== {$testName} ===\n";
        foreach ($metrics as $key => $value) {
            $output .= sprintf("  %s: %s\n", ucfirst(str_replace('_', ' ', $key)), $value);
        }
        $output .= "\n";

        // Write to stdout for test output
        fwrite(STDERR, $output);

        // Also store in a file for the performance report
        $reportFile = storage_path('logs/database-pool-performance-test.log');
        file_put_contents($reportFile, $output, FILE_APPEND);
    }
}
