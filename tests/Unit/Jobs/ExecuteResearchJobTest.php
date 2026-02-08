<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ExecuteResearchJob;
use App\Models\AgentRun;
use App\Services\ResearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExecuteResearchJobTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_executes_research_for_pending_run(): void
    {
        // Arrange
        $run = AgentRun::create([
            'objective' => 'Test research',
            'status' => 'pending',
            'context' => ['max_iterations' => 3],
        ]);

        $mockService = Mockery::mock(ResearchService::class);
        $mockService->shouldReceive('executeRun')
            ->once()
            ->with(Mockery::on(fn ($arg) => $arg instanceof AgentRun && $arg->id === $run->id))
            ->andReturn($run);

        $this->app->instance(ResearchService::class, $mockService);

        // Act
        $job = new ExecuteResearchJob($run->id);
        $job->handle(app(ResearchService::class));

        // Assert - job completed without exception
        $this->assertTrue(true);
    }

    #[Test]
    public function it_skips_non_pending_runs(): void
    {
        // Arrange
        $run = AgentRun::create([
            'objective' => 'Already completed',
            'status' => 'completed',
            'context' => [],
        ]);

        $mockService = Mockery::mock(ResearchService::class);
        $mockService->shouldNotReceive('executeRun');

        $this->app->instance(ResearchService::class, $mockService);

        // Act
        $job = new ExecuteResearchJob($run->id);
        $job->handle(app(ResearchService::class));

        // Assert - executeRun was not called
        $this->assertTrue(true);
    }

    #[Test]
    public function it_skips_running_runs(): void
    {
        // Arrange
        $run = AgentRun::create([
            'objective' => 'Already running',
            'status' => 'running',
            'context' => [],
        ]);

        $mockService = Mockery::mock(ResearchService::class);
        $mockService->shouldNotReceive('executeRun');

        $this->app->instance(ResearchService::class, $mockService);

        // Act
        $job = new ExecuteResearchJob($run->id);
        $job->handle(app(ResearchService::class));

        // Assert - executeRun was not called
        $this->assertTrue(true);
    }

    #[Test]
    public function it_can_be_queued(): void
    {
        Queue::fake();

        $run = AgentRun::create([
            'objective' => 'Async research',
            'status' => 'pending',
            'context' => [],
        ]);

        ExecuteResearchJob::dispatch($run->id);

        Queue::assertPushed(ExecuteResearchJob::class, function ($job) use ($run) {
            return $job->runId === $run->id;
        });
    }

    #[Test]
    public function it_handles_missing_run_gracefully(): void
    {
        // Arrange
        $mockService = Mockery::mock(ResearchService::class);
        $mockService->shouldNotReceive('executeRun');

        $this->app->instance(ResearchService::class, $mockService);

        // Act - use a non-existent run ID
        $job = new ExecuteResearchJob(99999);
        $job->handle(app(ResearchService::class));

        // Assert - job completed without exception
        $this->assertTrue(true);
    }

    #[Test]
    public function it_marks_run_as_failed_on_job_failure(): void
    {
        // Arrange
        $run = AgentRun::create([
            'objective' => 'Will fail',
            'status' => 'pending',
            'context' => [],
        ]);

        // Act
        $job = new ExecuteResearchJob($run->id);
        $job->failed(new \RuntimeException('Job failed'));

        // Assert
        $run->refresh();
        $this->assertEquals('failed', $run->status);
        $this->assertEquals('Job failed', $run->error);
    }
}
