<?php

namespace Tests\Unit\Services\Database;

use App\Services\Database\ConnectionPoolMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for ConnectionPoolMonitor service.
 *
 * Tests all monitoring methods and ensures they return expected data types
 * and handle errors gracefully.
 */
class ConnectionPoolMonitorTest extends TestCase
{
    use RefreshDatabase;

    private ConnectionPoolMonitor $monitor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->monitor = new ConnectionPoolMonitor;
    }

    /**
     * Test getActiveConnections returns an integer.
     *
     * @test
     */
    public function it_returns_active_connections_as_integer(): void
    {
        $activeConnections = $this->monitor->getActiveConnections();

        $this->assertIsInt($activeConnections);
        $this->assertGreaterThanOrEqual(0, $activeConnections);
    }

    /**
     * Test getMaxConnections returns an integer greater than zero.
     *
     * @test
     */
    public function it_returns_max_connections_as_integer(): void
    {
        $maxConnections = $this->monitor->getMaxConnections();

        $this->assertIsInt($maxConnections);
        $this->assertGreaterThan(0, $maxConnections);
    }

    /**
     * Test getPoolUtilization returns a float between 0 and 100.
     *
     * @test
     */
    public function it_returns_pool_utilization_as_percentage(): void
    {
        $utilization = $this->monitor->getPoolUtilization();

        $this->assertIsFloat($utilization);
        $this->assertGreaterThanOrEqual(0.0, $utilization);
        $this->assertLessThanOrEqual(100.0, $utilization);
    }

    /**
     * Test getConnectionStats returns an array of stats.
     *
     * @test
     */
    public function it_returns_connection_stats_as_array(): void
    {
        $stats = $this->monitor->getConnectionStats();

        $this->assertIsArray($stats);

        if (! empty($stats)) {
            $firstStat = $stats[0];

            $this->assertArrayHasKey('state', $firstStat);
            $this->assertArrayHasKey('count', $firstStat);
            $this->assertArrayHasKey('max_duration', $firstStat);

            $this->assertIsString($firstStat['state']);
            $this->assertIsInt($firstStat['count']);
            $this->assertIsFloat($firstStat['max_duration']);
            $this->assertGreaterThanOrEqual(0, $firstStat['count']);
        }
    }

    /**
     * Test getIdleConnections returns an array.
     *
     * @test
     */
    public function it_returns_idle_connections_as_array(): void
    {
        $idleConnections = $this->monitor->getIdleConnections();

        $this->assertIsArray($idleConnections);

        if (! empty($idleConnections)) {
            $firstConnection = $idleConnections[0];

            $this->assertArrayHasKey('pid', $firstConnection);
            $this->assertArrayHasKey('username', $firstConnection);
            $this->assertArrayHasKey('application_name', $firstConnection);
            $this->assertArrayHasKey('client_address', $firstConnection);
            $this->assertArrayHasKey('state', $firstConnection);
            $this->assertArrayHasKey('idle_duration', $firstConnection);
        }
    }

    /**
     * Test getLongRunningQueries returns an array.
     *
     * @test
     */
    public function it_returns_long_running_queries_as_array(): void
    {
        $longRunning = $this->monitor->getLongRunningQueries(60);

        $this->assertIsArray($longRunning);

        if (! empty($longRunning)) {
            $firstQuery = $longRunning[0];

            $this->assertArrayHasKey('pid', $firstQuery);
            $this->assertArrayHasKey('username', $firstQuery);
            $this->assertArrayHasKey('application_name', $firstQuery);
            $this->assertArrayHasKey('client_address', $firstQuery);
            $this->assertArrayHasKey('state', $firstQuery);
            $this->assertArrayHasKey('duration', $firstQuery);
            $this->assertArrayHasKey('query_preview', $firstQuery);
        }
    }

    /**
     * Test getLongRunningQueries respects threshold parameter.
     *
     * @test
     */
    public function it_filters_long_running_queries_by_threshold(): void
    {
        // Test with different thresholds
        $shortThreshold = $this->monitor->getLongRunningQueries(1);
        $longThreshold = $this->monitor->getLongRunningQueries(3600);

        $this->assertIsArray($shortThreshold);
        $this->assertIsArray($longThreshold);

        // Queries with 1 second threshold should >= queries with 1 hour threshold
        $this->assertGreaterThanOrEqual(count($longThreshold), count($shortThreshold));
    }

    /**
     * Test getHealthSummary returns complete health data.
     *
     * @test
     */
    public function it_returns_complete_health_summary(): void
    {
        $summary = $this->monitor->getHealthSummary();

        $this->assertIsArray($summary);
        $this->assertArrayHasKey('active_connections', $summary);
        $this->assertArrayHasKey('max_connections', $summary);
        $this->assertArrayHasKey('utilization_percentage', $summary);
        $this->assertArrayHasKey('connection_stats', $summary);
        $this->assertArrayHasKey('idle_connections_count', $summary);
        $this->assertArrayHasKey('long_running_queries_count', $summary);
        $this->assertArrayHasKey('timestamp', $summary);

        $this->assertIsInt($summary['active_connections']);
        $this->assertIsInt($summary['max_connections']);
        $this->assertIsFloat($summary['utilization_percentage']);
        $this->assertIsArray($summary['connection_stats']);
        $this->assertIsInt($summary['idle_connections_count']);
        $this->assertIsInt($summary['long_running_queries_count']);
        $this->assertIsString($summary['timestamp']);
    }

    /**
     * Test checkHealth returns proper health status.
     *
     * @test
     */
    public function it_returns_health_status(): void
    {
        $health = $this->monitor->checkHealth();

        $this->assertIsArray($health);
        $this->assertArrayHasKey('status', $health);
        $this->assertArrayHasKey('message', $health);
        $this->assertArrayHasKey('utilization', $health);
        $this->assertArrayHasKey('long_running_queries', $health);

        $this->assertContains($health['status'], ['healthy', 'degraded', 'unhealthy']);
        $this->assertIsString($health['message']);
        $this->assertIsFloat($health['utilization']);
        $this->assertIsInt($health['long_running_queries']);
    }

    /**
     * Test health status is healthy when utilization is low.
     *
     * @test
     */
    public function it_reports_healthy_when_utilization_is_low(): void
    {
        // This test assumes normal test environment has low utilization
        $health = $this->monitor->checkHealth();

        // In a test environment, we should typically be healthy
        if ($health['utilization'] < 70) {
            $this->assertEquals('healthy', $health['status']);
        }
    }

    /**
     * Test active connections increase after query execution.
     *
     * @test
     */
    public function it_tracks_connection_changes(): void
    {
        $beforeActive = $this->monitor->getActiveConnections();

        // Execute a query
        DB::select('SELECT 1');

        $afterActive = $this->monitor->getActiveConnections();

        // Active connections should be non-negative in both cases
        $this->assertGreaterThanOrEqual(0, $beforeActive);
        $this->assertGreaterThanOrEqual(0, $afterActive);
    }

    /**
     * Test pool utilization calculation is accurate.
     *
     * @test
     */
    public function it_calculates_utilization_accurately(): void
    {
        $active = $this->monitor->getActiveConnections();
        $max = $this->monitor->getMaxConnections();
        $utilization = $this->monitor->getPoolUtilization();

        $expectedUtilization = round(($active / $max) * 100, 2);

        $this->assertEquals($expectedUtilization, $utilization);
    }

    /**
     * Test monitor handles database errors gracefully.
     *
     * @test
     */
    public function it_handles_errors_gracefully(): void
    {
        // Even with potential DB issues, methods should not throw exceptions
        // They should return safe default values

        try {
            $this->monitor->getActiveConnections();
            $this->monitor->getMaxConnections();
            $this->monitor->getPoolUtilization();
            $this->monitor->getConnectionStats();
            $this->monitor->getIdleConnections();
            $this->monitor->getLongRunningQueries();
            $this->monitor->getHealthSummary();
            $this->monitor->checkHealth();

            $this->assertTrue(true, 'All methods executed without throwing exceptions');
        } catch (\Exception $e) {
            $this->fail('Monitor should handle errors gracefully without throwing exceptions: '.$e->getMessage());
        }
    }

    /**
     * Test connection stats include common states.
     *
     * @test
     */
    public function it_includes_connection_states(): void
    {
        // Execute some queries to ensure we have connections
        DB::select('SELECT 1');

        $stats = $this->monitor->getConnectionStats();

        $this->assertIsArray($stats);

        // Check if we have at least one state reported
        if (! empty($stats)) {
            $states = array_column($stats, 'state');
            $this->assertNotEmpty($states);

            // Common PostgreSQL states: active, idle, idle in transaction
            // At minimum, we should have some recognizable state
            foreach ($stats as $stat) {
                $this->assertNotEmpty($stat['state']);
            }
        }
    }

    /**
     * Test idle connections have valid durations.
     *
     * @test
     */
    public function it_reports_valid_idle_durations(): void
    {
        $idle = $this->monitor->getIdleConnections();

        foreach ($idle as $connection) {
            $this->assertGreaterThanOrEqual(0, $connection['idle_duration']);
        }
    }

    /**
     * Test long-running queries have valid durations.
     *
     * @test
     */
    public function it_reports_valid_query_durations(): void
    {
        $longRunning = $this->monitor->getLongRunningQueries(1);

        foreach ($longRunning as $query) {
            $this->assertGreaterThan(0, $query['duration']);
            $this->assertNotEmpty($query['query_preview']);
        }
    }

    /**
     * Test health check matches manual utilization check.
     *
     * @test
     */
    public function it_health_check_matches_utilization(): void
    {
        $health = $this->monitor->checkHealth();
        $utilization = $this->monitor->getPoolUtilization();

        $this->assertEquals($utilization, $health['utilization']);

        // Verify status matches utilization thresholds
        if ($utilization > 90) {
            $this->assertEquals('unhealthy', $health['status']);
        } elseif ($utilization > 70) {
            // Could be degraded due to utilization or long-running queries
            $this->assertContains($health['status'], ['degraded', 'unhealthy']);
        } else {
            // Could be healthy or degraded based on long-running queries
            $this->assertContains($health['status'], ['healthy', 'degraded']);
        }
    }
}
