<?php

namespace Tests\Unit\Traits;

use App\Logging\CorrelationIdProcessor;
use App\Traits\StructuredAgentLogging;
use Tests\TestCase;

/**
 * Tests for StructuredAgentLogging trait
 *
 * Sprint 1.7: Observability Setup
 */
class StructuredAgentLoggingTest extends TestCase
{
    use StructuredAgentLogging;

    protected string $name = 'test_agent';

    protected function setUp(): void
    {
        parent::setUp();

        // Reset correlation ID before each test
        CorrelationIdProcessor::resetCorrelationId();
    }

    /** @test */
    public function it_logs_agent_start(): void
    {
        $startTime = $this->logAgentStart('test_operation', ['extra' => 'data']);

        $this->assertIsFloat($startTime);
        $this->assertGreaterThan(0, $startTime);
    }

    /** @test */
    public function it_logs_agent_success(): void
    {
        $this->logAgentSuccess('test_operation', ['result' => 'success']);

        // Verify no exception thrown
        $this->assertTrue(true);
    }

    /** @test */
    public function it_logs_agent_success_with_duration(): void
    {
        $startTime = microtime(true);
        usleep(1000); // Sleep for 1ms

        $this->logAgentSuccess('test_operation', [], $startTime);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_logs_agent_failure(): void
    {
        $exception = new \RuntimeException('Test error', 500);
        $startTime = microtime(true);

        $this->logAgentFailure('test_operation', $exception, $startTime);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_logs_agent_iteration(): void
    {
        $this->logAgentIteration('test_operation', 5, ['score' => 0.85]);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_logs_agent_metrics(): void
    {
        $this->logAgentMetrics('test_operation', [
            'tokens_used' => 1250,
            'cost_usd' => 0.05,
        ]);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_logs_agent_warning(): void
    {
        $this->logAgentWarning('test_operation', 'Low score detected', [
            'current_score' => 0.3,
        ]);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_logs_agent_debug(): void
    {
        $this->logAgentDebug('test_operation', 'Processing step 3', [
            'step' => 3,
        ]);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_creates_child_correlation_id(): void
    {
        $parentId = CorrelationIdProcessor::getCorrelationId();

        $childId = $this->createChildCorrelationId('sub-task-1');

        $this->assertStringStartsWith($parentId, $childId);
        $this->assertStringEndsWith(':sub-task-1', $childId);
    }

    /** @test */
    public function it_logs_to_custom_channel(): void
    {
        $this->logToChannel('custom', 'info', 'Custom message');

        $this->assertTrue(true);
    }

    /** @test */
    public function it_returns_agent_name_from_property(): void
    {
        $this->assertEquals('test_agent', $this->getAgentName());
    }

    /** @test */
    public function it_returns_class_basename_when_name_property_not_set(): void
    {
        $anonymousClass = new class
        {
            use StructuredAgentLogging;
        };

        // Use reflection to call protected method
        $reflection = new \ReflectionClass($anonymousClass);
        $method = $reflection->getMethod('getAgentName');
        $method->setAccessible(true);

        $agentName = $method->invoke($anonymousClass);

        // Should return the class basename (will be an anonymous class identifier)
        $this->assertNotEmpty($agentName);
    }

    /** @test */
    public function it_gets_correlation_id(): void
    {
        $customId = 'test-correlation-id-123';
        CorrelationIdProcessor::setCorrelationId($customId);

        $correlationId = $this->getCorrelationId();

        $this->assertEquals($customId, $correlationId);
    }
}
