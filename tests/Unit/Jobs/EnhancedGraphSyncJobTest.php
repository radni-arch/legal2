<?php

namespace Tests\Unit\Jobs;

use Tests\TestCase;
use App\Jobs\EnhancedGraphSyncJob;
use App\Events\Graph\BatchStarted;
use App\Events\Graph\BatchCompleted;
use App\Events\Graph\SyncFinished;
use App\Models\CourtDecision;
use App\Services\Graph\DecisionGraphSyncService;
use App\Services\Graph\DocumentTypeDetector;
use App\Services\Graph\ParallelExtractionService;
use App\Services\GraphDatabaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;

class EnhancedGraphSyncJobTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function mockDocumentTypeDetector()
    {
        $mock = Mockery::mock(DocumentTypeDetector::class);
        $mock->shouldReceive('detect')->andReturn('court_decision');
        $mock->shouldReceive('getExtractorsForType')->andReturn([]);
        return $mock;
    }

    protected function mockParallelExtractionService()
    {
        $mock = Mockery::mock(ParallelExtractionService::class);
        $mock->shouldReceive('extractInParallel')->andReturn([]);
        return $mock;
    }

    protected function mockGraphDatabaseService()
    {
        $mock = Mockery::mock(GraphDatabaseService::class);
        $mock->shouldReceive('batchUpsertNodes')->andReturn(0);
        $mock->shouldReceive('batchUpsertRelationships')->andReturn(0);
        return $mock;
    }

    /** @test */
    public function it_dispatches_batch_events(): void
    {
        Event::fake([BatchStarted::class, BatchCompleted::class, SyncFinished::class]);

        $decision = CourtDecision::factory()->create();

        $syncService = Mockery::mock(DecisionGraphSyncService::class);
        $syncService->shouldReceive('sync')
            ->once()
            ->with($decision->id)
            ->andReturn(['nodes' => ['CourtDecisionDocument' => 5], 'relationships' => ['DECIDED_BY' => 3], 'errors' => [], 'duration_ms' => 100]);

        $job = new EnhancedGraphSyncJob([$decision->id], batchSize: 10);
        $job->handle(
            $syncService,
            $this->mockDocumentTypeDetector(),
            $this->mockParallelExtractionService(),
            $this->mockGraphDatabaseService()
        );

        Event::assertDispatched(BatchStarted::class);
        Event::assertDispatched(BatchCompleted::class);
        Event::assertDispatched(SyncFinished::class);
    }

    /** @test */
    public function it_processes_in_batches(): void
    {
        Event::fake([BatchStarted::class, BatchCompleted::class, SyncFinished::class]);

        CourtDecision::factory()->count(5)->create();

        $syncService = Mockery::mock(DecisionGraphSyncService::class);
        $syncService->shouldReceive('sync')
            ->times(5)
            ->andReturn(['nodes' => ['CourtDecisionDocument' => 1], 'relationships' => ['DECIDED_BY' => 1], 'errors' => [], 'duration_ms' => 50]);

        $job = new EnhancedGraphSyncJob(allDecisions: true, batchSize: 2);
        $job->handle(
            $syncService,
            $this->mockDocumentTypeDetector(),
            $this->mockParallelExtractionService(),
            $this->mockGraphDatabaseService()
        );

        // 5 decisions with batch size 2 = 3 batches
        Event::assertDispatchedTimes(BatchStarted::class, 3);
        Event::assertDispatchedTimes(BatchCompleted::class, 3);
        Event::assertDispatched(SyncFinished::class, function ($event) {
            return $event->totalDecisions === 5 && $event->status === 'completed';
        });
    }

    /** @test */
    public function it_handles_sync_failures_gracefully(): void
    {
        Event::fake([SyncFinished::class]);

        $decision = CourtDecision::factory()->create();

        $syncService = Mockery::mock(DecisionGraphSyncService::class);
        $syncService->shouldReceive('sync')
            ->once()
            ->with($decision->id)
            ->andThrow(new \Exception('Sync failed'));

        $job = new EnhancedGraphSyncJob([$decision->id]);
        $job->handle(
            $syncService,
            $this->mockDocumentTypeDetector(),
            $this->mockParallelExtractionService(),
            $this->mockGraphDatabaseService()
        );

        Event::assertDispatched(SyncFinished::class, function ($event) {
            return $event->totalErrors === 1 && $event->status === 'failed';
        });
    }

    /** @test */
    public function it_reports_partial_status_on_some_failures(): void
    {
        Event::fake([SyncFinished::class]);

        CourtDecision::factory()->count(3)->create();

        $syncService = Mockery::mock(DecisionGraphSyncService::class);
        $syncService->shouldReceive('sync')
            ->times(3)
            ->andReturnUsing(function ($decisionId) {
                static $call = 0;
                $call++;
                if ($call === 2) {
                    throw new \Exception('One failure');
                }
                return ['nodes' => ['CourtDecisionDocument' => 1], 'relationships' => ['DECIDED_BY' => 1], 'errors' => [], 'duration_ms' => 50];
            });

        $job = new EnhancedGraphSyncJob(allDecisions: true, batchSize: 10);
        $job->handle(
            $syncService,
            $this->mockDocumentTypeDetector(),
            $this->mockParallelExtractionService(),
            $this->mockGraphDatabaseService()
        );

        Event::assertDispatched(SyncFinished::class, function ($event) {
            return $event->totalErrors === 1 && $event->status === 'partial';
        });
    }
}
