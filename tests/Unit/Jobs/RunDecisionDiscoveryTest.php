<?php

namespace Tests\Unit\Jobs;

use App\Agents\DecisionDiscoveryAgent;
use App\Jobs\RunDecisionDiscovery;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class RunDecisionDiscoveryTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_implements_should_queue_interface(): void
    {
        $job = new RunDecisionDiscovery;

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $job);
    }

    /** @test */
    public function it_stores_configuration_parameters(): void
    {
        $job = new RunDecisionDiscovery(10, 100, 20, 80.5);

        $reflection = new \ReflectionClass($job);
        $topics = $reflection->getProperty('topics');
        $topics->setAccessible(true);
        $perTopic = $reflection->getProperty('perTopic');
        $perTopic->setAccessible(true);
        $ingest = $reflection->getProperty('ingest');
        $ingest->setAccessible(true);
        $threshold = $reflection->getProperty('threshold');
        $threshold->setAccessible(true);

        $this->assertEquals(10, $topics->getValue($job));
        $this->assertEquals(100, $perTopic->getValue($job));
        $this->assertEquals(20, $ingest->getValue($job));
        $this->assertEquals(80.5, $threshold->getValue($job));
    }

    /** @test */
    public function it_has_default_configuration(): void
    {
        $job = new RunDecisionDiscovery;

        $reflection = new \ReflectionClass($job);
        $topics = $reflection->getProperty('topics');
        $topics->setAccessible(true);
        $perTopic = $reflection->getProperty('perTopic');
        $perTopic->setAccessible(true);
        $ingest = $reflection->getProperty('ingest');
        $ingest->setAccessible(true);
        $threshold = $reflection->getProperty('threshold');
        $threshold->setAccessible(true);

        $this->assertEquals(5, $topics->getValue($job));
        $this->assertEquals(50, $perTopic->getValue($job));
        $this->assertEquals(10, $ingest->getValue($job));
        $this->assertEquals(70.0, $threshold->getValue($job));
    }

    /** @test */
    public function it_configures_agent_and_executes_discovery(): void
    {
        Log::shouldReceive('info')->times(2);

        $mockAgent = Mockery::mock(DecisionDiscoveryAgent::class);
        $mockAgent->shouldReceive('setTopicsPerRun')
            ->once()
            ->with(3)
            ->andReturnSelf();
        $mockAgent->shouldReceive('setDecisionsPerTopic')
            ->once()
            ->with(25)
            ->andReturnSelf();
        $mockAgent->shouldReceive('setIngestPerTopic')
            ->once()
            ->with(5)
            ->andReturnSelf();
        $mockAgent->shouldReceive('setRelevanceThreshold')
            ->once()
            ->with(75.0)
            ->andReturnSelf();
        $mockAgent->shouldReceive('discover')
            ->once()
            ->andReturn(['discovered' => 15, 'ingested' => 5]);

        $job = new RunDecisionDiscovery(3, 25, 5, 75.0);
        $job->handle($mockAgent);

        $this->addToAssertionCount(1);
    }

    /** @test */
    public function it_logs_successful_completion_with_stats(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->with('RunDecisionDiscovery - Starting', Mockery::type('array'));

        Log::shouldReceive('info')
            ->once()
            ->with('RunDecisionDiscovery - Completed successfully', Mockery::on(function ($context) {
                return isset($context['stats']) && $context['stats']['discovered'] === 20;
            }));

        $mockAgent = Mockery::mock(DecisionDiscoveryAgent::class);
        $mockAgent->shouldReceive('setTopicsPerRun')
            ->andReturnSelf();
        $mockAgent->shouldReceive('setDecisionsPerTopic')
            ->andReturnSelf();
        $mockAgent->shouldReceive('setIngestPerTopic')
            ->andReturnSelf();
        $mockAgent->shouldReceive('setRelevanceThreshold')
            ->andReturnSelf();
        $mockAgent->shouldReceive('discover')
            ->once()
            ->andReturn(['discovered' => 20, 'ingested' => 10]);

        $job = new RunDecisionDiscovery;
        $job->handle($mockAgent);

        $this->addToAssertionCount(1);
    }

    /** @test */
    public function it_logs_error_and_rethrows_on_exception(): void
    {
        Log::shouldReceive('info')->once();
        Log::shouldReceive('error')
            ->once()
            ->with('RunDecisionDiscovery - Failed', Mockery::type('array'));

        $mockAgent = Mockery::mock(DecisionDiscoveryAgent::class);
        $mockAgent->shouldReceive('setTopicsPerRun')
            ->andReturnSelf();
        $mockAgent->shouldReceive('setDecisionsPerTopic')
            ->andReturnSelf();
        $mockAgent->shouldReceive('setIngestPerTopic')
            ->andReturnSelf();
        $mockAgent->shouldReceive('setRelevanceThreshold')
            ->andReturnSelf();
        $mockAgent->shouldReceive('discover')
            ->once()
            ->andThrow(new \Exception('Discovery failed'));

        $job = new RunDecisionDiscovery;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Discovery failed');

        $job->handle($mockAgent);
    }

    /** @test */
    public function failed_method_logs_permanent_failure(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->with('RunDecisionDiscovery - Job failed permanently after all retries', Mockery::type('array'));

        $job = new RunDecisionDiscovery;
        $exception = new \Exception('Permanent failure');

        $job->failed($exception);
    }

    /** @test */
    public function it_can_be_dispatched_to_queue(): void
    {
        Queue::fake();

        RunDecisionDiscovery::dispatch(7, 75, 15, 85.0);

        Queue::assertPushed(RunDecisionDiscovery::class);
    }

    /** @test */
    public function it_has_correct_tags(): void
    {
        $job = new RunDecisionDiscovery(8, 50, 10, 70.0);

        $tags = $job->tags();

        $this->assertContains('agent:decision-discovery', $tags);
        $this->assertContains('scheduled:daily', $tags);
        $this->assertContains('topics:8', $tags);
    }

    /** @test */
    public function it_has_3_retry_attempts(): void
    {
        $job = new RunDecisionDiscovery;

        $this->assertEquals(3, $job->tries);
    }

    /** @test */
    public function it_has_1800_second_timeout(): void
    {
        $job = new RunDecisionDiscovery;

        $this->assertEquals(1800, $job->timeout);
    }

    /** @test */
    public function it_has_exponential_backoff_strategy(): void
    {
        $job = new RunDecisionDiscovery;

        $this->assertEquals([60, 300, 900], $job->backoff);
    }

    /** @test */
    public function it_has_retry_until_timeout_of_2_hours(): void
    {
        $job = new RunDecisionDiscovery;

        $retryUntil = $job->retryUntil();

        $this->assertInstanceOf(\DateTime::class, $retryUntil);
        // Should be approximately 2 hours from now
        $expectedTime = now()->addHours(2);
        $this->assertEqualsWithDelta($expectedTime->timestamp, $retryUntil->getTimestamp(), 5);
    }
}
