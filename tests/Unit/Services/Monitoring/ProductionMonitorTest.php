<?php

namespace Tests\Unit\Services\Monitoring;

use App\Models\AgentRun;
use App\Services\Monitoring\AlertManager;
use App\Services\Monitoring\MetricsCollector;
use App\Services\Monitoring\ProductionMonitor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Sprint 6.7: Production Monitoring Setup - Production Monitor Tests
 *
 * These tests verify that production monitoring checks work correctly
 * and trigger alerts when thresholds are exceeded.
 */
class ProductionMonitorTest extends TestCase
{
    use DatabaseTransactions;

    protected ProductionMonitor $monitor;

    protected AlertManager $alertManager;

    protected MetricsCollector $metricsCollector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->metricsCollector = app(MetricsCollector::class);
        $this->alertManager = app(AlertManager::class);
        $this->monitor = new ProductionMonitor(
            $this->metricsCollector,
            $this->alertManager
        );

        // Clear existing alerts
        $this->alertManager->clearAllAlerts();

        // Mock Sentry to prevent actual API calls
        config(['sentry.dsn' => null]);

        // Mock HTTP for Slack
        Http::fake();
    }

    /**
     * Test agent failure rate check when below threshold
     */
    public function test_agent_failure_rate_below_threshold(): void
    {
        // Create successful agent runs (95% success rate)
        for ($i = 0; $i < 19; $i++) {
            AgentRun::create([
                'agent_name' => 'AutonomousResearchAgent',
                'objective' => "Test objective {$i}",
                'context' => [],
                'status' => 'completed',
                'created_at' => now(),
            ]);
        }

        // Create 1 failed run (5% failure rate)
        AgentRun::create([
            'agent_name' => 'AutonomousResearchAgent',
            'objective' => 'Test objective',
            'context' => [],
            'status' => 'failed',
            'created_at' => now(),
        ]);

        $result = $this->monitor->checkAgentFailureRate();

        $this->assertEquals('ok', $result['status']);
        $this->assertEquals(5.0, $result['failure_rate']);
        $this->assertEquals(20, $result['total_runs']);
        $this->assertEquals(1, $result['failed_runs']);

        // No alert should be triggered
        $activeAlerts = $this->alertManager->getActiveAlerts();
        $this->assertArrayNotHasKey('agent_high_failure_rate', $activeAlerts);
    }

    /**
     * Test agent failure rate check when above threshold
     */
    public function test_agent_failure_rate_above_threshold(): void
    {
        // Create agent runs with 15% failure rate (above 10% threshold)
        for ($i = 0; $i < 17; $i++) {
            AgentRun::create([
                'agent_name' => 'AutonomousResearchAgent',
                'objective' => "Test objective {$i}",
                'context' => [],
                'status' => 'completed',
                'created_at' => now(),
            ]);
        }

        for ($i = 0; $i < 3; $i++) {
            AgentRun::create([
                'agent_name' => 'AutonomousResearchAgent',
                'objective' => "Failed objective {$i}",
                'context' => [],
                'status' => 'failed',
                'error_message' => 'Test error',
                'created_at' => now(),
            ]);
        }

        $result = $this->monitor->checkAgentFailureRate();

        $this->assertEquals('critical', $result['status']);
        $this->assertEquals(15.0, $result['failure_rate']);
        $this->assertEquals(20, $result['total_runs']);
        $this->assertEquals(3, $result['failed_runs']);

        // Alert should be triggered
        $activeAlerts = $this->alertManager->getActiveAlerts();
        $this->assertArrayHasKey('agent_high_failure_rate', $activeAlerts);
    }

    /**
     * Test all production checks run successfully
     */
    public function test_all_production_checks_run_successfully(): void
    {
        // Setup data for all checks
        // Agent runs (5% failure - OK)
        for ($i = 0; $i < 19; $i++) {
            AgentRun::create([
                'agent_name' => 'AutonomousResearchAgent',
                'objective' => "Test {$i}",
                'context' => [],
                'status' => 'completed',
                'created_at' => now(),
            ]);
        }

        AgentRun::create([
            'agent_name' => 'AutonomousResearchAgent',
            'objective' => 'Test failed',
            'context' => [],
            'status' => 'failed',
            'created_at' => now(),
        ]);

        // API error rate (3% - OK)
        Cache::put('monitoring:total_requests', 1000, 3600);
        Cache::put('monitoring:error_requests', 30, 3600);

        $results = $this->monitor->runProductionChecks();

        $this->assertArrayHasKey('agent_failure_rate', $results);
        $this->assertArrayHasKey('response_time', $results);
        $this->assertArrayHasKey('api_error_rate', $results);
        $this->assertArrayHasKey('queue_backlog', $results);
        $this->assertArrayHasKey('timestamp', $results);

        // Agent failure rate and API error rate should be OK
        $this->assertEquals('ok', $results['agent_failure_rate']['status']);
        $this->assertEquals('ok', $results['api_error_rate']['status']);
    }
}
