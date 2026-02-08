<?php

namespace Tests\Unit\Jobs;

use App\Agents\DecisionDiscoveryAgent;
use App\Jobs\ExecuteDecisionDiscoveryJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class ExecuteDecisionDiscoveryJobTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_implements_should_queue_interface(): void
    {
        $job = new ExecuteDecisionDiscoveryJob;

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $job);
    }

    /** @test */
    public function it_stores_max_decisions_and_topic_parameters(): void
    {
        $job = new ExecuteDecisionDiscoveryJob(10, 'criminal-law');

        $reflection = new \ReflectionClass($job);
        $maxDecisions = $reflection->getProperty('maxDecisions');
        $maxDecisions->setAccessible(true);
        $topic = $reflection->getProperty('topic');
        $topic->setAccessible(true);

        $this->assertEquals(10, $maxDecisions->getValue($job));
        $this->assertEquals('criminal-law', $topic->getValue($job));
    }

    /** @test */
    public function it_executes_discovery_with_topic(): void
    {
        Log::shouldReceive('info')->times(3);

        $mockAgent = Mockery::mock(DecisionDiscoveryAgent::class);
        $mockAgent->shouldReceive('setMaxDecisionsGlobal')
            ->once()
            ->with(5);
        $mockAgent->shouldReceive('discover')
            ->once()
            ->with('drug-charges')
            ->andReturn(['discovered' => 5, 'ingested' => 5]);
        $mockAgent->shouldReceive('discoverSingleTopic')
            ->once()
            ->with('drug-charges')
            ->andReturn(['discovered' => 5, 'ingested' => 5]);

        $job = new ExecuteDecisionDiscoveryJob(5, 'drug-charges');
        $job->handle($mockAgent);

        $this->addToAssertionCount(1);
    }

    /** @test */
    public function it_executes_discovery_without_topic(): void
    {
        Log::shouldReceive('info')->times(3);

        $mockAgent = Mockery::mock(DecisionDiscoveryAgent::class);
        $mockAgent->shouldReceive('setMaxDecisionsGlobal')
            ->once()
            ->with(10);
        $mockAgent->shouldReceive('discover')
            ->twice()
            ->withNoArgs()
            ->andReturn(['discovered' => 10, 'ingested' => 10]);

        $job = new ExecuteDecisionDiscoveryJob(10, null);
        $job->handle($mockAgent);

        $this->addToAssertionCount(1);
    }

    /** @test */
    public function it_sets_max_decisions_global_on_agent(): void
    {
        Log::shouldReceive('info')->times(3);

        $mockAgent = Mockery::mock(DecisionDiscoveryAgent::class);
        $mockAgent->shouldReceive('setMaxDecisionsGlobal')
            ->once()
            ->with(20)
            ->andReturnSelf();
        $mockAgent->shouldReceive('discover')
            ->twice()
            ->andReturn(['discovered' => 20, 'ingested' => 20]);

        $job = new ExecuteDecisionDiscoveryJob(20);
        $job->handle($mockAgent);

        $this->addToAssertionCount(1);
    }

    /** @test */
    public function it_logs_error_and_rethrows_on_exception(): void
    {
        Log::shouldReceive('info')->once();
        Log::shouldReceive('error')->once();

        $mockAgent = Mockery::mock(DecisionDiscoveryAgent::class);
        $mockAgent->shouldReceive('setMaxDecisionsGlobal')
            ->once();
        $mockAgent->shouldReceive('discover')
            ->once()
            ->andThrow(new \Exception('Discovery failed'));

        $job = new ExecuteDecisionDiscoveryJob;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Discovery failed');

        $job->handle($mockAgent);
    }

    /** @test */
    public function failed_method_logs_permanent_failure(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->with('ExecuteDecisionDiscoveryJob - Job failed permanently after all retries', Mockery::type('array'));

        $job = new ExecuteDecisionDiscoveryJob(15, 'test-topic');
        $exception = new \Exception('Permanent failure');

        $job->failed($exception);
    }

    /** @test */
    public function it_can_be_dispatched_to_queue(): void
    {
        Queue::fake();

        ExecuteDecisionDiscoveryJob::dispatch(25, 'procedural-law');

        Queue::assertPushed(ExecuteDecisionDiscoveryJob::class);
    }

    /** @test */
    public function it_has_correct_tags_with_topic(): void
    {
        $job = new ExecuteDecisionDiscoveryJob(10, 'constitutional-law');

        $tags = $job->tags();

        $this->assertContains('agent:decision-discovery', $tags);
        $this->assertContains('topic:constitutional-law', $tags);
    }

    /** @test */
    public function it_has_correct_tags_without_topic(): void
    {
        $job = new ExecuteDecisionDiscoveryJob(10, null);

        $tags = $job->tags();

        $this->assertContains('agent:decision-discovery', $tags);
        $this->assertContains('topic:all', $tags);
    }

    /** @test */
    public function it_has_3_retry_attempts(): void
    {
        $job = new ExecuteDecisionDiscoveryJob;

        $this->assertEquals(3, $job->tries);
    }

    /** @test */
    public function it_has_exponential_backoff_strategy(): void
    {
        $job = new ExecuteDecisionDiscoveryJob;

        $backoff = $job->backoff();

        $this->assertEquals([60, 180], $backoff);
    }

    /** @test */
    public function it_has_900_second_timeout(): void
    {
        $job = new ExecuteDecisionDiscoveryJob;

        $this->assertEquals(900, $job->timeout);
    }
}
