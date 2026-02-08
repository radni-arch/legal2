<?php

namespace Tests\Unit\Services;

use App\Events\ResearchCompleted;
use App\Events\ResearchFailed;
use App\Models\AgentRun;
use App\Services\ResearchOrchestrator;
use App\Services\ResearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ResearchServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_creates_agent_run_and_executes_research(): void
    {
        // Arrange
        $mockOrchestrator = Mockery::mock(ResearchOrchestrator::class);
        $mockOrchestrator->shouldReceive('research')
            ->once()
            ->with('Find case law on home searches', Mockery::type('array'))
            ->andReturn([
                'success' => true,
                'answer' => 'Based on research...',
                'quality_score' => 85,
                'iterations' => 3,
                'stopped_reason' => 'quality_threshold',
                'total_tokens' => 5000,
                'total_time_s' => 12.5,
                'search_results' => [],
            ]);

        $service = new ResearchService($mockOrchestrator);

        // Act
        $run = $service->research(
            'Find case law on home searches',
            ['max_iterations' => 5, 'quality_threshold' => 80]
        );

        // Assert
        $this->assertInstanceOf(AgentRun::class, $run);
        $this->assertEquals('completed', $run->status);
        $this->assertEquals(85, $run->score);
        $this->assertEquals(3, $run->current_iteration);
        $this->assertEquals(5000, $run->tokens_used);
    }

    #[Test]
    public function it_handles_research_failure_gracefully(): void
    {
        // Arrange
        $mockOrchestrator = Mockery::mock(ResearchOrchestrator::class);
        $mockOrchestrator->shouldReceive('research')
            ->once()
            ->andReturn([
                'success' => false,
                'answer' => null,
                'quality_score' => 0,
                'iterations' => 1,
                'stopped_reason' => 'error',
                'total_tokens' => 100,
                'total_time_s' => 1.0,
                'search_results' => [],
            ]);

        $service = new ResearchService($mockOrchestrator);

        // Act
        $run = $service->research('Invalid query', []);

        // Assert
        $this->assertEquals('failed', $run->status);
        $this->assertEquals(0, $run->score);
    }

    #[Test]
    public function it_fires_research_completed_event(): void
    {
        // Arrange
        Event::fake();

        $mockOrchestrator = Mockery::mock(ResearchOrchestrator::class);
        $mockOrchestrator->shouldReceive('research')
            ->andReturn([
                'success' => true,
                'answer' => 'Result',
                'quality_score' => 90,
                'iterations' => 2,
                'stopped_reason' => 'quality_threshold',
                'total_tokens' => 3000,
                'total_time_s' => 8.0,
                'search_results' => [],
            ]);

        $service = new ResearchService($mockOrchestrator);

        // Act
        $service->research('Test query', []);

        // Assert
        Event::assertDispatched(ResearchCompleted::class);
    }

    #[Test]
    public function it_fires_research_failed_event_on_failure(): void
    {
        // Arrange
        Event::fake();

        $mockOrchestrator = Mockery::mock(ResearchOrchestrator::class);
        $mockOrchestrator->shouldReceive('research')
            ->andReturn([
                'success' => false,
                'answer' => null,
                'quality_score' => 40,
                'iterations' => 5,
                'stopped_reason' => 'max_iterations',
                'total_tokens' => 10000,
                'total_time_s' => 30.0,
                'search_results' => [],
            ]);

        $service = new ResearchService($mockOrchestrator);

        // Act
        $service->research('Low quality query', []);

        // Assert
        Event::assertDispatched(ResearchFailed::class);
    }

    #[Test]
    public function it_handles_orchestrator_exception(): void
    {
        // Arrange
        Event::fake();

        $mockOrchestrator = Mockery::mock(ResearchOrchestrator::class);
        $mockOrchestrator->shouldReceive('research')
            ->andThrow(new \RuntimeException('Orchestrator error'));

        $service = new ResearchService($mockOrchestrator);

        // Act
        $run = $service->research('Query that causes error', []);

        // Assert
        $this->assertEquals('failed', $run->status);
        $this->assertEquals('Orchestrator error', $run->error);
        Event::assertDispatched(ResearchFailed::class);
    }

    #[Test]
    public function it_stores_search_results_in_iterations_array(): void
    {
        // Arrange
        $searchResults = [
            ['iteration' => 1, 'results' => [['success' => true, 'result' => ['doc1', 'doc2']]]],
            ['iteration' => 2, 'results' => [['success' => true, 'result' => ['doc3']]]],
        ];

        $mockOrchestrator = Mockery::mock(ResearchOrchestrator::class);
        $mockOrchestrator->shouldReceive('research')
            ->andReturn([
                'success' => true,
                'answer' => 'Found results',
                'quality_score' => 88,
                'iterations' => 2,
                'stopped_reason' => 'quality_threshold',
                'total_tokens' => 2000,
                'total_time_s' => 5.0,
                'search_results' => $searchResults,
            ]);

        $service = new ResearchService($mockOrchestrator);

        // Act
        $run = $service->research('Test query', []);

        // Assert
        $this->assertIsArray($run->iterations);
        $this->assertCount(2, $run->iterations);
    }

    #[Test]
    public function it_passes_options_to_orchestrator_correctly(): void
    {
        // Arrange
        $mockOrchestrator = Mockery::mock(ResearchOrchestrator::class);
        $mockOrchestrator->shouldReceive('research')
            ->once()
            ->with('Test query', Mockery::on(function ($options) {
                return isset($options['limits'])
                    && $options['limits']['max_iterations'] === 10
                    && $options['limits']['quality_threshold'] === 90;
            }))
            ->andReturn([
                'success' => true,
                'answer' => 'Result',
                'quality_score' => 90,
                'iterations' => 1,
                'stopped_reason' => 'quality_threshold',
                'total_tokens' => 1000,
                'total_time_s' => 2.0,
                'search_results' => [],
            ]);

        $service = new ResearchService($mockOrchestrator);

        // Act
        $service->research('Test query', [
            'max_iterations' => 10,
            'quality_threshold' => 90,
        ]);

        // Assert - expectations verified by Mockery
        $this->assertTrue(true);
    }

    #[Test]
    public function it_sets_agent_name_on_new_runs(): void
    {
        // Arrange
        $mockOrchestrator = Mockery::mock(ResearchOrchestrator::class);
        $mockOrchestrator->shouldReceive('research')
            ->andReturn([
                'success' => true,
                'answer' => 'Result',
                'quality_score' => 90,
                'iterations' => 1,
                'stopped_reason' => 'quality_threshold',
                'total_tokens' => 1000,
                'total_time_s' => 2.0,
                'search_results' => [],
            ]);

        $service = new ResearchService($mockOrchestrator);

        // Act
        $run = $service->research('Test query', []);

        // Assert
        $this->assertEquals('research_service', $run->agent_name);
    }

    #[Test]
    public function execute_run_operates_on_existing_run(): void
    {
        // Arrange
        $existingRun = AgentRun::create([
            'agent_name' => 'research_service',
            'objective' => 'Existing run objective',
            'status' => 'pending',
            'max_iterations' => 5,
            'threshold' => 80,
        ]);

        $mockOrchestrator = Mockery::mock(ResearchOrchestrator::class);
        $mockOrchestrator->shouldReceive('research')
            ->once()
            ->with('Existing run objective', Mockery::type('array'))
            ->andReturn([
                'success' => true,
                'answer' => 'Completed',
                'quality_score' => 85,
                'iterations' => 3,
                'stopped_reason' => 'quality_threshold',
                'total_tokens' => 2000,
                'total_time_s' => 5.0,
                'search_results' => [],
            ]);

        $service = new ResearchService($mockOrchestrator);

        // Act
        $completedRun = $service->executeRun($existingRun);

        // Assert - same run was updated, not a new one created
        $this->assertEquals($existingRun->id, $completedRun->id);
        $this->assertEquals('completed', $completedRun->status);
        $this->assertEquals(85, $completedRun->score);

        // Verify no duplicate runs were created
        $runCount = AgentRun::where('objective', 'Existing run objective')->count();
        $this->assertEquals(1, $runCount);
    }

    #[Test]
    public function execute_run_marks_status_as_running(): void
    {
        // Arrange
        $existingRun = AgentRun::create([
            'agent_name' => 'research_service',
            'objective' => 'Test run',
            'status' => 'pending',
        ]);

        $mockOrchestrator = Mockery::mock(ResearchOrchestrator::class);
        $mockOrchestrator->shouldReceive('research')
            ->andReturn([
                'success' => true,
                'answer' => 'Done',
                'quality_score' => 90,
                'iterations' => 1,
                'stopped_reason' => 'quality_threshold',
                'total_tokens' => 500,
                'total_time_s' => 1.0,
                'search_results' => [],
            ]);

        $service = new ResearchService($mockOrchestrator);

        // Act
        $service->executeRun($existingRun);

        // Assert - run should have started_at set
        $existingRun->refresh();
        $this->assertNotNull($existingRun->started_at);
    }
}
