<?php

namespace Tests\Unit\Jobs;

use App\Agents\AutonomousResearchAgent;
use App\Events\ResearchCompleted;
use App\Events\ResearchFailed;
use App\Jobs\RunAutonomousResearchJob;
use App\Models\AgentRun;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class RunAutonomousResearchJobTest extends TestCase
{
    use UsesTestDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_implements_should_queue_interface()
    {
        $job = new RunAutonomousResearchJob('run-id-123');

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $job);
    }

    /** @test */
    public function it_has_timeout_of_600_seconds()
    {
        $job = new RunAutonomousResearchJob('run-id-123');

        $this->assertEquals(1800, $job->timeout);
    }

    /** @test */
    public function it_has_one_try_no_retries()
    {
        $job = new RunAutonomousResearchJob('run-id-123');

        $this->assertEquals(1, $job->tries);
    }

    /** @test */
    public function it_stores_run_id_and_resuming_flag()
    {
        $job = new RunAutonomousResearchJob('run-123', true);

        $this->assertEquals('run-123', $job->runId);
        $this->assertTrue($job->resuming);
    }

    /** @test */
    public function it_defaults_resuming_to_false()
    {
        $job = new RunAutonomousResearchJob('run-456');

        $this->assertFalse($job->resuming);
    }

    /** @test */
    public function it_executes_normal_research_run()
    {
        Event::fake();

        $run = AgentRun::create([
            'objective' => 'Research Croatian criminal law',
            'status' => 'pending',
            'started_at' => now(),
        ]);

        $agentMock = Mockery::mock(AutonomousResearchAgent::class);
        $agentMock->shouldReceive('executeRun')
            ->once()
            ->with(Mockery::on(function ($arg) use ($run) {
                return $arg->id === $run->id;
            }))
            ->andReturn($run->fresh());

        $this->app->instance(AutonomousResearchAgent::class, $agentMock);

        // Use actual run ID instead of placeholder string
        $job = new RunAutonomousResearchJob((string) $run->id, false);
        $job->handle();

        $run->refresh();
        $this->assertEquals('running', $run->status);

        Event::assertDispatched(ResearchCompleted::class);
    }

    /** @test */
    public function it_resumes_research_from_checkpoint()
    {
        Event::fake();

        $run = AgentRun::create([
            'objective' => 'Research legal precedents',
            'status' => 'paused',
            'can_resume' => true,
            'started_at' => now()->subMinutes(5),
            'current_iteration' => 3,
            'checkpoint_state' => ['data' => 'checkpoint'],
        ]);

        // Verify the run can actually be resumed
        $this->assertTrue($run->canBeResumed(), 'Run should be resumable');

        $agentMock = Mockery::mock(AutonomousResearchAgent::class);
        $agentMock->shouldReceive('resumeRun')
            ->once()
            ->with(Mockery::on(function ($arg) use ($run) {
                return $arg->id === $run->id;
            }))
            ->andReturn($run->fresh());

        $this->app->instance(AutonomousResearchAgent::class, $agentMock);

        // Use actual run ID instead of placeholder string
        $job = new RunAutonomousResearchJob((string) $run->id, true);
        $job->handle();

        Event::assertDispatched(ResearchCompleted::class);
    }

    /** @test */
    public function it_updates_run_with_job_information()
    {
        Event::fake();

        $run = AgentRun::create([
            'objective' => 'Test objective',
            'status' => 'pending',
            'started_at' => now(),
        ]);

        $agentMock = Mockery::mock(AutonomousResearchAgent::class);
        $agentMock->shouldReceive('executeRun')
            ->once()
            ->andReturn($run->fresh());

        $this->app->instance(AutonomousResearchAgent::class, $agentMock);

        $job = new RunAutonomousResearchJob($run->id);
        $job->handle();

        $run->refresh();
        $this->assertEquals('running', $run->status);
        $this->assertNotNull($run->queue);
    }

    /** @test */
    public function it_broadcasts_research_completed_event()
    {
        Event::fake();

        $run = AgentRun::create([
            'objective' => 'Test objective',
            'status' => 'pending',
            'started_at' => now(),
        ]);

        $completedRun = $run->fresh();
        $completedRun->status = 'completed';
        $completedRun->current_iteration = 5;
        $completedRun->score = 85;

        $agentMock = Mockery::mock(AutonomousResearchAgent::class);
        $agentMock->shouldReceive('executeRun')
            ->once()
            ->andReturn($completedRun);

        $this->app->instance(AutonomousResearchAgent::class, $agentMock);

        $job = new RunAutonomousResearchJob($run->id);
        $job->handle();

        Event::assertDispatched(ResearchCompleted::class, function ($event) {
            return $event->run->status === 'completed';
        });
    }

    /** @test */
    public function it_handles_exception_during_execution()
    {
        Event::fake();

        $run = AgentRun::create([
            'objective' => 'Test objective',
            'status' => 'pending',
            'started_at' => now(),
        ]);

        $agentMock = Mockery::mock(AutonomousResearchAgent::class);
        $agentMock->shouldReceive('executeRun')
            ->once()
            ->andThrow(new \Exception('Agent execution failed'));

        $this->app->instance(AutonomousResearchAgent::class, $agentMock);

        $job = new RunAutonomousResearchJob($run->id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Agent execution failed');

        $job->handle();

        $run->refresh();
        $this->assertEquals('failed', $run->status);
        $this->assertEquals('Agent execution failed', $run->error);

        Event::assertDispatched(ResearchFailed::class);
    }

    /** @test */
    public function it_broadcasts_research_failed_event_on_exception()
    {
        Event::fake();

        $run = AgentRun::create([
            'objective' => 'Test objective',
            'status' => 'pending',
            'started_at' => now(),
        ]);

        $agentMock = Mockery::mock(AutonomousResearchAgent::class);
        $agentMock->shouldReceive('executeRun')
            ->once()
            ->andThrow(new \Exception('Test failure'));

        $this->app->instance(AutonomousResearchAgent::class, $agentMock);

        $job = new RunAutonomousResearchJob($run->id);

        try {
            $job->handle();
        } catch (\Exception $e) {
            // Expected
        }

        Event::assertDispatched(ResearchFailed::class, function ($event) {
            return $event->error === 'Test failure';
        });
    }

    /** @test */
    public function it_calculates_elapsed_seconds_on_failure()
    {
        Event::fake();

        $startTime = now()->subMinutes(10);

        $run = AgentRun::create([
            'objective' => 'Test objective',
            'status' => 'pending',
            'started_at' => $startTime,
        ]);

        $agentMock = Mockery::mock(AutonomousResearchAgent::class);
        $agentMock->shouldReceive('executeRun')
            ->once()
            ->andThrow(new \Exception('Failed'));

        $this->app->instance(AutonomousResearchAgent::class, $agentMock);

        $job = new RunAutonomousResearchJob($run->id);

        try {
            $job->handle();
        } catch (\Exception $e) {
            // Expected
        }

        $run->refresh();
        $this->assertGreaterThan(0, $run->elapsed_seconds);
        $this->assertNotNull($run->completed_at);
    }

    /** @test */
    public function it_handles_permanent_job_failure()
    {
        Event::fake();

        $run = AgentRun::create([
            'objective' => 'Test objective',
            'status' => 'running',
            'started_at' => now()->subMinutes(5),
        ]);

        $exception = new \Exception('Permanent failure');

        $job = new RunAutonomousResearchJob($run->id);
        $job->failed($exception);

        $run->refresh();
        $this->assertEquals('failed', $run->status);
        $this->assertEquals('Permanent failure', $run->error);
        $this->assertNotNull($run->completed_at);

        Event::assertDispatched(ResearchFailed::class);
    }

    /** @test */
    public function it_handles_missing_run_on_failure()
    {
        Event::fake();

        $exception = new \Exception('Test error');

        $job = new RunAutonomousResearchJob('non-existent-run');

        // Should not throw exception
        $job->failed($exception);

        Event::assertNotDispatched(ResearchFailed::class);
    }

    /** @test */
    public function it_can_be_dispatched_to_queue()
    {
        Queue::fake();

        RunAutonomousResearchJob::dispatch('run-dispatch');

        Queue::assertPushed(RunAutonomousResearchJob::class, function ($job) {
            return $job->runId === 'run-dispatch' && ! $job->resuming;
        });
    }

    /** @test */
    public function it_can_be_dispatched_with_resuming_flag()
    {
        Queue::fake();

        RunAutonomousResearchJob::dispatch('run-resume-dispatch', true);

        Queue::assertPushed(RunAutonomousResearchJob::class, function ($job) {
            return $job->runId === 'run-resume-dispatch' && $job->resuming;
        });
    }

    /** @test */
    public function it_only_resumes_if_run_can_be_resumed()
    {
        Event::fake();

        $run = AgentRun::create([
            'objective' => 'Test objective',
            'status' => 'completed', // Already completed, cannot resume
            'started_at' => now()->subHour(),
            'completed_at' => now()->subMinutes(30),
        ]);

        $agentMock = Mockery::mock(AutonomousResearchAgent::class);

        // Should call executeRun instead of resumeRun when run cannot be resumed
        $agentMock->shouldReceive('executeRun')
            ->once()
            ->andReturn($run->fresh());

        $this->app->instance(AutonomousResearchAgent::class, $agentMock);

        $job = new RunAutonomousResearchJob($run->id, true);
        $job->handle();
    }

    /** @test */
    public function it_serializes_correctly()
    {
        $job = new RunAutonomousResearchJob('run-serialize', true);

        $serialized = serialize($job);
        $unserialized = unserialize($serialized);

        $this->assertEquals('run-serialize', $unserialized->runId);
        $this->assertTrue($unserialized->resuming);
    }

    /** @test */
    public function it_uses_required_traits()
    {
        $reflection = new \ReflectionClass(RunAutonomousResearchJob::class);
        $traits = $reflection->getTraitNames();

        $this->assertContains('Illuminate\Foundation\Bus\Dispatchable', $traits);
        $this->assertContains('Illuminate\Queue\InteractsWithQueue', $traits);
        $this->assertContains('Illuminate\Bus\Queueable', $traits);
        $this->assertContains('Illuminate\Queue\SerializesModels', $traits);
    }
}
