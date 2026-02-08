<?php

namespace Tests\Unit\Jobs;

use App\Agents\AutonomousResearchAgent;
use App\Jobs\ExecuteAgentResearch;
use App\Models\AgentRun;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class ExecuteAgentResearchTest extends TestCase
{
    use UsesTestDatabase;

    protected $agentMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agentMock = Mockery::mock(AutonomousResearchAgent::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_job_executes_agent_research_successfully(): void
    {
        // Arrange
        $run = AgentRun::create([
            'agent_name' => 'research',
            'objective' => 'Research case law on contract disputes',
            'status' => 'running',
            'max_iterations' => 10,
            'current_iteration' => 0,
            'threshold' => 0.8,
            'time_limit_seconds' => 300,
            'started_at' => now(),
        ]);

        $mockAgent = Mockery::mock(AutonomousResearchAgent::class);
        $mockAgent->shouldReceive('executeRun')
            ->once()
            ->with(Mockery::on(function ($passedRun) use ($run) {
                return $passedRun->id === $run->id;
            }))
            ->andReturnUsing(function ($passedRun) {
                // Simulate successful execution
                $passedRun->update([
                    'status' => 'completed',
                    'score' => 0.85,
                    'current_iteration' => 5,
                    'completed_at' => now(),
                    'final_output' => 'Research findings...',
                ]);

                return $passedRun;
            });

        $this->app->instance(AutonomousResearchAgent::class, $mockAgent);

        Log::shouldReceive('info')->andReturn(null);

        // Act
        $job = new ExecuteAgentResearch($run);
        $job->handle($mockAgent);

        // Assert
        $run->refresh();
        $this->assertEquals('completed', $run->status);
        $this->assertEquals(0.85, $run->score);
        $this->assertNotNull($run->final_output);
    }

    public function test_job_skips_execution_if_run_not_in_running_status(): void
    {
        // Arrange
        $run = AgentRun::create([
            'agent_name' => 'research',
            'objective' => 'Research case law',
            'status' => 'paused', // Not running
            'max_iterations' => 10,
        ]);

        $mockAgent = Mockery::mock(AutonomousResearchAgent::class);
        $mockAgent->shouldNotReceive('executeRun');

        $this->app->instance(AutonomousResearchAgent::class, $mockAgent);

        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) use ($run) {
                return $message === 'Agent run is not in running status, skipping'
                    && $context['run_id'] === $run->id
                    && $context['status'] === 'paused';
            });

        // Act
        $job = new ExecuteAgentResearch($run);
        $job->handle($mockAgent);

        // Assert - Job completes without executing agent
        $run->refresh();
        $this->assertEquals('paused', $run->status);
    }

    public function test_job_handles_agent_execution_failure(): void
    {
        // Arrange
        $run = AgentRun::create([
            'agent_name' => 'research',
            'objective' => 'Research case law',
            'status' => 'running',
            'max_iterations' => 10,
            'started_at' => now(),
        ]);

        $exception = new \Exception('Agent execution failed: API timeout');

        $mockAgent = Mockery::mock(AutonomousResearchAgent::class);
        $mockAgent->shouldReceive('executeRun')
            ->once()
            ->andThrow($exception);

        $this->app->instance(AutonomousResearchAgent::class, $mockAgent);

        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'Async agent research failed'
                    && isset($context['error'])
                    && str_contains($context['error'], 'API timeout');
            });

        // Act & Assert
        $job = new ExecuteAgentResearch($run);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Agent execution failed: API timeout');

        $job->handle($mockAgent);

        // Verify run was marked as failed
        $run->refresh();
        $this->assertEquals('failed', $run->status);
        $this->assertNotNull($run->error);
        $this->assertStringContainsString('API timeout', $run->error);
        $this->assertNotNull($run->completed_at);
        $this->assertGreaterThan(0, $run->elapsed_seconds);
    }

    public function test_job_updates_run_with_error_on_failure(): void
    {
        // Arrange
        $run = AgentRun::create([
            'agent_name' => 'research',
            'objective' => 'Research case law',
            'status' => 'running',
            'max_iterations' => 10,
            'started_at' => now()->subSeconds(30),
        ]);

        $exception = new \Exception('Database connection lost');

        $mockAgent = Mockery::mock(AutonomousResearchAgent::class);
        $mockAgent->shouldReceive('executeRun')
            ->once()
            ->andThrow($exception);

        $this->app->instance(AutonomousResearchAgent::class, $mockAgent);

        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('error')->andReturn(null);

        // Act
        $job = new ExecuteAgentResearch($run);

        try {
            $job->handle($mockAgent);
        } catch (\Exception $e) {
            // Expected
        }

        // Assert
        $run->refresh();
        $this->assertEquals('failed', $run->status);
        $this->assertStringContainsString('Job execution failed', $run->error);
        $this->assertStringContainsString('Database connection lost', $run->error);
        $this->assertNotNull($run->completed_at);
        $this->assertGreaterThanOrEqual(30, $run->elapsed_seconds);
    }

    public function test_job_failed_method_marks_run_as_failed(): void
    {
        // Arrange
        $run = AgentRun::create([
            'agent_name' => 'research',
            'objective' => 'Research case law',
            'status' => 'running',
            'max_iterations' => 10,
            'started_at' => now()->subMinute(),
        ]);

        $exception = new \Exception('Permanent failure');

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) use ($run) {
                return $message === 'Agent research job failed permanently'
                    && $context['run_id'] === $run->id;
            });

        // Act
        $job = new ExecuteAgentResearch($run);
        $job->failed($exception);

        // Assert
        $run->refresh();
        $this->assertEquals('failed', $run->status);
        $this->assertStringContainsString('Job failed', $run->error);
        $this->assertStringContainsString('Permanent failure', $run->error);
        $this->assertNotNull($run->completed_at);
        $this->assertGreaterThanOrEqual(60, $run->elapsed_seconds);
    }

    public function test_job_sets_correct_timeout_based_on_run_time_limit(): void
    {
        // Arrange
        $run = AgentRun::create([
            'agent_name' => 'research',
            'objective' => 'Research case law',
            'status' => 'running',
            'max_iterations' => 10,
            'time_limit_seconds' => 600,
        ]);

        // Act
        $job = new ExecuteAgentResearch($run);

        // Assert
        $this->assertEquals(660, $job->timeout); // 600 + 60 safety buffer
    }

    public function test_job_uses_default_timeout_when_no_time_limit_set(): void
    {
        // Arrange
        Config::set('agent.safety.max_time_per_run', 3600);

        $run = AgentRun::create([
            'agent_name' => 'research',
            'objective' => 'Research case law',
            'status' => 'running',
            'max_iterations' => 10,
            'time_limit_seconds' => null,
        ]);

        // Act
        $job = new ExecuteAgentResearch($run);

        // Assert
        $this->assertEquals(3660, $job->timeout); // 3600 + 60 safety buffer
    }

    public function test_job_uses_correct_queue_from_config(): void
    {
        // Arrange
        Config::set('agent.queue.name', 'agent-research-queue');

        $run = AgentRun::create([
            'agent_name' => 'research',
            'objective' => 'Research case law',
            'status' => 'running',
            'max_iterations' => 10,
        ]);

        // Act
        $job = new ExecuteAgentResearch($run);

        // Assert
        $this->assertEquals('agent-research-queue', $job->queue);
    }

    public function test_job_tags_are_set_correctly(): void
    {
        // Arrange
        $run = AgentRun::create([
            'agent_name' => 'custom-agent',
            'objective' => 'Research case law',
            'status' => 'running',
            'max_iterations' => 10,
        ]);

        // Act
        $job = new ExecuteAgentResearch($run);
        $tags = $job->tags();

        // Assert
        $this->assertContains('agent:research', $tags);
        $this->assertContains('run:'.$run->id, $tags);
        $this->assertContains('agent:custom-agent', $tags);
    }

    public function test_job_logs_start_and_completion_correctly(): void
    {
        // Arrange
        $run = AgentRun::create([
            'agent_name' => 'research',
            'objective' => 'Research contract disputes',
            'status' => 'running',
            'max_iterations' => 10,
            'started_at' => now(),
        ]);

        $mockAgent = Mockery::mock(AutonomousResearchAgent::class);
        $mockAgent->shouldReceive('executeRun')
            ->once()
            ->andReturnUsing(function ($passedRun) {
                $passedRun->update([
                    'status' => 'completed',
                    'score' => 0.9,
                ]);

                return $passedRun;
            });

        $this->app->instance(AutonomousResearchAgent::class, $mockAgent);

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) use ($run) {
                return $message === 'Starting async agent research'
                    && $context['run_id'] === $run->id
                    && $context['objective'] === 'Research contract disputes';
            });

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'Async agent research completed'
                    && isset($context['status'])
                    && isset($context['score']);
            });

        // Act
        $job = new ExecuteAgentResearch($run);
        $job->handle($mockAgent);

        // Assert - verified by log expectations
        $this->assertTrue(true);
    }

    public function test_job_refreshes_run_before_execution(): void
    {
        // Arrange
        $run = AgentRun::create([
            'agent_name' => 'research',
            'objective' => 'Research case law',
            'status' => 'running',
            'max_iterations' => 10,
        ]);

        // Simulate external status change
        AgentRun::where('id', $run->id)->update(['status' => 'cancelled']);

        $mockAgent = Mockery::mock(AutonomousResearchAgent::class);
        $mockAgent->shouldNotReceive('executeRun');

        $this->app->instance(AutonomousResearchAgent::class, $mockAgent);

        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'Agent run is not in running status, skipping'
                    && $context['status'] === 'cancelled';
            });

        // Act
        $job = new ExecuteAgentResearch($run);
        $job->handle($mockAgent);

        // Assert - verified by mock expectations
        $this->assertTrue(true);
    }

    public function test_job_calculates_elapsed_seconds_correctly_on_failure(): void
    {
        // Arrange
        $startTime = now()->subSeconds(45);

        $run = AgentRun::create([
            'agent_name' => 'research',
            'objective' => 'Research case law',
            'status' => 'running',
            'max_iterations' => 10,
            'started_at' => $startTime,
        ]);

        $exception = new \Exception('Test failure');

        $mockAgent = Mockery::mock(AutonomousResearchAgent::class);
        $mockAgent->shouldReceive('executeRun')
            ->once()
            ->andThrow($exception);

        $this->app->instance(AutonomousResearchAgent::class, $mockAgent);

        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('error')->andReturn(null);

        // Act
        $job = new ExecuteAgentResearch($run);

        try {
            $job->handle($mockAgent);
        } catch (\Exception $e) {
            // Expected
        }

        // Assert
        $run->refresh();
        $this->assertGreaterThanOrEqual(45, $run->elapsed_seconds);
        $this->assertLessThanOrEqual(50, $run->elapsed_seconds); // Allow small variance
    }

    public function test_job_handles_run_with_context_and_topics(): void
    {
        // Arrange
        $run = AgentRun::create([
            'agent_name' => 'research',
            'objective' => 'Research employment law',
            'status' => 'running',
            'max_iterations' => 10,
            'context' => [
                'jurisdiction' => 'HR',
                'case_type' => 'employment',
            ],
            'topics' => [
                'wrongful termination',
                'severance pay',
            ],
            'started_at' => now(),
        ]);

        $mockAgent = Mockery::mock(AutonomousResearchAgent::class);
        $mockAgent->shouldReceive('executeRun')
            ->once()
            ->with(Mockery::on(function ($passedRun) {
                return is_array($passedRun->context)
                    && is_array($passedRun->topics)
                    && count($passedRun->topics) === 2;
            }))
            ->andReturnUsing(function ($passedRun) {
                $passedRun->update([
                    'status' => 'completed',
                    'score' => 0.88,
                ]);

                return $passedRun;
            });

        $this->app->instance(AutonomousResearchAgent::class, $mockAgent);

        Log::shouldReceive('info')->andReturn(null);

        // Act
        $job = new ExecuteAgentResearch($run);
        $job->handle($mockAgent);

        // Assert
        $run->refresh();
        $this->assertEquals('completed', $run->status);
        $this->assertIsArray($run->context);
        $this->assertIsArray($run->topics);
    }

    /** @test */
    public function it_executes_agent_research_run()
    {
        $run = AgentRun::create([
            'agent_id' => 1,
            'objective' => 'Research Croatian criminal law precedents',
            'status' => 'running',
            'started_at' => now(),
            'time_limit_seconds' => 3600,
        ]);

        $this->agentMock
            ->shouldReceive('executeRun')
            ->once()
            ->with(Mockery::on(fn ($r) => $r->id === $run->id));

        $job = new ExecuteAgentResearch($run);
        $job->handle($this->agentMock);

        // Verify it completed without errors
        $this->assertTrue(true);
    }

    /** @test */
    public function it_skips_execution_if_run_not_in_running_status()
    {
        $run = AgentRun::create([
            'agent_id' => 1,
            'objective' => 'Test objective',
            'status' => 'completed', // Not running
            'started_at' => now(),
        ]);

        $this->agentMock
            ->shouldNotReceive('executeRun');

        $job = new ExecuteAgentResearch($run);
        $job->handle($this->agentMock);

        // Should skip execution without error
        $this->assertTrue(true);
    }

    /** @test */
    public function it_sets_timeout_based_on_run_time_limit()
    {
        $run = AgentRun::create([
            'agent_id' => 1,
            'objective' => 'Test',
            'status' => 'running',
            'time_limit_seconds' => 1800, // 30 minutes
        ]);

        $job = new ExecuteAgentResearch($run);

        // Timeout should be time_limit + 60 second buffer
        $this->assertEquals(1860, $job->timeout);
    }

    /** @test */
    public function it_uses_default_timeout_when_no_time_limit()
    {
        config(['agent.safety.max_time_per_run' => 3600]);

        $run = AgentRun::create([
            'agent_id' => 1,
            'objective' => 'Test',
            'status' => 'running',
            'time_limit_seconds' => null, // No limit
        ]);

        $job = new ExecuteAgentResearch($run);

        // Should use config default + 60 second buffer
        $this->assertEquals(3660, $job->timeout);
    }

    /** @test */
    public function it_has_correct_retry_configuration()
    {
        $run = AgentRun::create([
            'agent_id' => 1,
            'objective' => 'Test',
            'status' => 'running',
        ]);

        $job = new ExecuteAgentResearch($run);

        // Agent jobs should only try once (no retries)
        $this->assertEquals(1, $job->tries);
    }

    /** @test */
    public function it_sets_queue_from_config()
    {
        config(['agent.queue.name' => 'agent-research']);

        $run = AgentRun::create([
            'agent_id' => 1,
            'objective' => 'Test',
            'status' => 'running',
        ]);

        $job = new ExecuteAgentResearch($run);

        $this->assertEquals('agent-research', $job->queue);
    }

    /** @test */
    public function it_can_be_dispatched_to_queue()
    {
        Queue::fake();

        $run = AgentRun::create([
            'agent_id' => 1,
            'objective' => 'Test research',
            'status' => 'running',
        ]);

        ExecuteAgentResearch::dispatch($run);

        Queue::assertPushed(ExecuteAgentResearch::class, function ($job) use ($run) {
            return $job->run->id === $run->id;
        });
    }

    /** @test */
    public function it_refreshes_run_status_before_execution()
    {
        $run = AgentRun::create([
            'agent_id' => 1,
            'objective' => 'Test',
            'status' => 'running',
        ]);

        // Change status before job executes
        AgentRun::where('id', $run->id)->update(['status' => 'cancelled']);

        $this->agentMock
            ->shouldNotReceive('executeRun');

        $job = new ExecuteAgentResearch($run);
        $job->handle($this->agentMock);

        // Should skip because status changed to cancelled
        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_exceptions_during_execution()
    {
        $run = AgentRun::create([
            'agent_id' => 1,
            'objective' => 'Test',
            'status' => 'running',
        ]);

        $this->agentMock
            ->shouldReceive('executeRun')
            ->once()
            ->andThrow(new \Exception('Agent execution failed'));

        $job = new ExecuteAgentResearch($run);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Agent execution failed');

        $job->handle($this->agentMock);
    }
}
