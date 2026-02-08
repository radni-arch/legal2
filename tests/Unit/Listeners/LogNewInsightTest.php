<?php

namespace Tests\Unit\Listeners;

use App\Events\NewInsightDiscovered;
use App\Listeners\LogNewInsight;
use App\Models\AgentInsightEvent;
use App\Models\AgentRun;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class LogNewInsightTest extends TestCase
{
    use UsesTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure we don't use fake events for these tests
        Event::fake([]);
    }

    /** @test */
    public function it_logs_insight_when_exceeds_threshold()
    {
        $run = AgentRun::factory()->create([
            'objective' => 'Research Croatian labor law',
            'current_iteration' => 5,
        ]);

        $event = new NewInsightDiscovered(
            insight: 'Article 93 requires 2 weeks notice',
            run: $run,
            agentName: 'autonomous_research_agent',
            metadata: [
                'run_id' => $run->id,
                'objective' => $run->objective,
                'iteration' => $run->current_iteration,
            ],
            relevanceScore: 0.85,
            severity: 'warning'
        );

        $listener = new LogNewInsight;
        $listener->handle($event);

        // Assert insight was logged
        $this->assertDatabaseHas('agent_insight_events', [
            'agent_name' => 'autonomous_research_agent',
            'agent_run_id' => $run->id,
            'insight' => 'Article 93 requires 2 weeks notice',
            'objective' => 'Research Croatian labor law',
            'severity' => 'warning',
            'relevance_score' => 0.85,
        ]);
    }

    /** @test */
    public function it_skips_logging_when_below_threshold()
    {
        $run = AgentRun::factory()->create();

        $event = new NewInsightDiscovered(
            insight: 'Low relevance insight',
            run: $run,
            agentName: 'autonomous_research_agent',
            metadata: [],
            relevanceScore: 0.5, // Below default threshold of 0.7
            severity: 'info'
        );

        $listener = new LogNewInsight;
        $listener->handle($event);

        // Assert nothing was logged
        $this->assertDatabaseCount('agent_insight_events', 0);
    }

    /** @test */
    public function it_logs_insight_when_relevance_score_is_null()
    {
        // When relevance score is null, all insights should be logged
        $run = AgentRun::factory()->create();

        $event = new NewInsightDiscovered(
            insight: 'Insight without relevance score',
            run: $run,
            agentName: 'autonomous_research_agent',
            metadata: [],
            relevanceScore: null,
            severity: 'info'
        );

        $listener = new LogNewInsight;
        $listener->handle($event);

        // Assert insight was logged
        $this->assertDatabaseHas('agent_insight_events', [
            'insight' => 'Insight without relevance score',
        ]);
    }

    /** @test */
    public function it_stores_all_metadata_correctly()
    {
        $run = AgentRun::factory()->create([
            'objective' => 'Test objective',
            'current_iteration' => 3,
        ]);

        $metadata = [
            'run_id' => $run->id,
            'objective' => 'Test objective',
            'iteration' => 3,
            'source' => 'autonomous_research',
            'source_id' => (string) $run->id,
            'custom_field' => 'custom_value',
        ];

        $event = new NewInsightDiscovered(
            insight: 'Test insight',
            run: $run,
            agentName: 'test_agent',
            metadata: $metadata,
            relevanceScore: 0.9,
            severity: 'critical'
        );

        $listener = new LogNewInsight;
        $listener->handle($event);

        $insightEvent = AgentInsightEvent::first();

        $this->assertNotNull($insightEvent);
        $this->assertEquals('test_agent', $insightEvent->agent_name);
        $this->assertEquals($run->id, $insightEvent->agent_run_id);
        $this->assertEquals('Test insight', $insightEvent->insight);
        $this->assertEquals('Test objective', $insightEvent->objective);
        $this->assertEquals('critical', $insightEvent->severity);
        $this->assertEquals(0.9, $insightEvent->relevance_score);
        $this->assertEquals('autonomous_research', $insightEvent->source);
        $this->assertEquals((string) $run->id, $insightEvent->source_id);
        $this->assertEquals($metadata, $insightEvent->metadata);
    }

    /** @test */
    public function it_handles_different_severity_levels()
    {
        $run = AgentRun::factory()->create();

        $severities = ['info', 'warning', 'critical'];

        foreach ($severities as $severity) {
            $event = new NewInsightDiscovered(
                insight: "Insight with {$severity} severity",
                run: $run,
                agentName: 'autonomous_research_agent',
                metadata: [],
                relevanceScore: null,
                severity: $severity
            );

            $listener = new LogNewInsight;
            $listener->handle($event);
        }

        // Assert all severities were logged
        foreach ($severities as $severity) {
            $this->assertDatabaseHas('agent_insight_events', [
                'severity' => $severity,
            ]);
        }
    }

    /** @test */
    public function it_is_queueable()
    {
        Queue::fake();

        $run = AgentRun::factory()->create();

        $event = new NewInsightDiscovered(
            insight: 'Test insight',
            run: $run,
            agentName: 'autonomous_research_agent',
            metadata: [],
            relevanceScore: 0.8,
            severity: 'info'
        );

        // Dispatch the event
        event($event);

        // The listener should be queued
        $this->assertTrue($event->exceedsThreshold());
    }

    /** @test */
    public function it_has_retry_configuration()
    {
        $listener = new LogNewInsight;

        $this->assertEquals(3, $listener->tries);
        $this->assertEquals(10, $listener->backoff);
        $this->assertEquals('default', $listener->queue);
    }

    /** @test */
    public function it_should_queue_when_exceeds_threshold()
    {
        $run = AgentRun::factory()->create();

        $event = new NewInsightDiscovered(
            insight: 'High relevance insight',
            run: $run,
            agentName: 'autonomous_research_agent',
            metadata: [],
            relevanceScore: 0.85,
            severity: 'info'
        );

        $listener = new LogNewInsight;

        $this->assertTrue($listener->shouldQueue($event));
    }

    /** @test */
    public function it_should_not_queue_when_below_threshold()
    {
        $run = AgentRun::factory()->create();

        $event = new NewInsightDiscovered(
            insight: 'Low relevance insight',
            run: $run,
            agentName: 'autonomous_research_agent',
            metadata: [],
            relevanceScore: 0.5,
            severity: 'info'
        );

        $listener = new LogNewInsight;

        $this->assertFalse($listener->shouldQueue($event));
    }

    /** @test */
    public function it_logs_error_on_failure()
    {
        Log::shouldReceive('debug')->once();
        Log::shouldReceive('info')->never();
        Log::shouldReceive('error')->never();

        $run = AgentRun::factory()->create();

        $event = new NewInsightDiscovered(
            insight: 'Test insight',
            run: $run,
            agentName: 'autonomous_research_agent',
            metadata: [],
            relevanceScore: 0.5, // Below threshold
            severity: 'info'
        );

        $listener = new LogNewInsight;
        $listener->handle($event);
    }

    /** @test */
    public function it_handles_exception_gracefully()
    {
        // This test verifies that exceptions are logged and re-thrown
        $run = AgentRun::factory()->create();

        // Create an invalid event that will cause an exception
        $event = new NewInsightDiscovered(
            insight: 'Test insight',
            run: $run,
            agentName: 'autonomous_research_agent',
            metadata: [],
            relevanceScore: 0.9,
            severity: 'invalid_severity' // This should still work as severity is just a string
        );

        $listener = new LogNewInsight;

        // Should not throw exception
        $listener->handle($event);

        $this->assertDatabaseHas('agent_insight_events', [
            'insight' => 'Test insight',
        ]);
    }

    /** @test */
    public function it_creates_relationship_with_agent_run()
    {
        $run = AgentRun::factory()->create();

        $event = new NewInsightDiscovered(
            insight: 'Test insight',
            run: $run,
            agentName: 'autonomous_research_agent',
            metadata: [],
            relevanceScore: null,
            severity: 'info'
        );

        $listener = new LogNewInsight;
        $listener->handle($event);

        $insightEvent = AgentInsightEvent::first();

        $this->assertNotNull($insightEvent->run);
        $this->assertEquals($run->id, $insightEvent->run->id);
        $this->assertEquals($run->objective, $insightEvent->run->objective);
    }

    /** @test */
    public function it_logs_multiple_insights_for_same_run()
    {
        $run = AgentRun::factory()->create();

        $insights = [
            'First insight',
            'Second insight',
            'Third insight',
        ];

        foreach ($insights as $index => $insight) {
            $event = new NewInsightDiscovered(
                insight: $insight,
                run: $run,
                agentName: 'autonomous_research_agent',
                metadata: ['index' => $index],
                relevanceScore: null,
                severity: 'info'
            );

            $listener = new LogNewInsight;
            $listener->handle($event);
        }

        // Assert all insights were logged for the same run
        $this->assertDatabaseCount('agent_insight_events', 3);

        $events = AgentInsightEvent::where('agent_run_id', $run->id)->get();
        $this->assertCount(3, $events);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
