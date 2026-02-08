<?php

namespace Tests\Unit\Jobs;

use App\Jobs\IngestOdlukeDecision;
use App\Models\FailedIngestion;
use App\Services\Odluke\OdlukeIngestService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class IngestOdlukeDecisionTest extends TestCase
{
    use UsesTestDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_implements_should_queue_interface(): void
    {
        $job = new IngestOdlukeDecision('decision-123');

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $job);
    }

    /** @test */
    public function it_stores_decision_id_and_options(): void
    {
        $options = ['sync_graph' => true, 'force' => false];
        $job = new IngestOdlukeDecision('decision-456', $options, 'failed-id-789');

        $this->assertEquals('decision-456', $job->decisionId);
        $this->assertEquals($options, $job->options);
        $this->assertEquals('failed-id-789', $job->failedIngestionId);
    }

    /** @test */
    public function it_handles_successful_ingestion(): void
    {
        Log::shouldReceive('info')->times(2);

        $mockIngestService = Mockery::mock(OdlukeIngestService::class);
        $mockIngestService->shouldReceive('ingestByIds')
            ->once()
            ->with(['decision-123'], [])
            ->andReturn([
                'inserted' => 1,
                'errors' => 0,
                'skipped' => 0,
                'graph_synced' => 1,
            ]);

        $job = new IngestOdlukeDecision('decision-123');
        $job->handle($mockIngestService);

        $this->addToAssertionCount(1);
    }

    /** @test */
    public function it_marks_failed_ingestion_as_succeeded_when_provided(): void
    {
        Log::shouldReceive('info')->times(2);

        $failedIngestion = FailedIngestion::create([
            'decision_id' => 'decision-success',
            'source_type' => 'odluke',
            'failure_reason' => 'network_error',
            'error_message' => 'Connection timeout',
            'status' => FailedIngestion::STATUS_RETRYING,
            'ingestion_options' => [],
            'max_attempts' => 5,
            'attempts' => 1,
        ]);

        $mockIngestService = Mockery::mock(OdlukeIngestService::class);
        $mockIngestService->shouldReceive('ingestByIds')
            ->once()
            ->andReturn([
                'inserted' => 1,
                'errors' => 0,
                'skipped' => 0,
            ]);

        $job = new IngestOdlukeDecision('decision-success', [], $failedIngestion->id);
        $job->handle($mockIngestService);

        $failedIngestion->refresh();
        $this->assertEquals(FailedIngestion::STATUS_SUCCEEDED, $failedIngestion->status);
    }

    /** @test */
    public function it_handles_empty_text_failure(): void
    {
        Log::shouldReceive('info')->once();
        Log::shouldReceive('warning')->once();

        $mockIngestService = Mockery::mock(OdlukeIngestService::class);
        $mockIngestService->shouldReceive('ingestByIds')
            ->once()
            ->andReturn([
                'inserted' => 0,
                'errors' => 0,
                'skipped' => 1, // Empty text
            ]);

        $job = new IngestOdlukeDecision('decision-empty');
        $job->handle($mockIngestService);

        // Should create a failed ingestion record
        $this->assertDatabaseHas('failed_ingestions', [
            'decision_id' => 'decision-empty',
            'failure_reason' => FailedIngestion::REASON_EMPTY_TEXT,
        ]);
    }

    /** @test */
    public function it_handles_extraction_error_failure(): void
    {
        Log::shouldReceive('info')->once();
        Log::shouldReceive('warning')->once();

        $mockIngestService = Mockery::mock(OdlukeIngestService::class);
        $mockIngestService->shouldReceive('ingestByIds')
            ->once()
            ->andReturn([
                'inserted' => 0,
                'errors' => 1, // Extraction failed
                'skipped' => 0,
            ]);

        $job = new IngestOdlukeDecision('decision-extract-error');
        $job->handle($mockIngestService);

        $this->assertDatabaseHas('failed_ingestions', [
            'decision_id' => 'decision-extract-error',
            'failure_reason' => FailedIngestion::REASON_EXTRACTION_ERROR,
        ]);
    }

    /** @test */
    public function it_handles_network_error_exception(): void
    {
        Log::shouldReceive('info')->once();
        Log::shouldReceive('warning')->once();

        $mockIngestService = Mockery::mock(OdlukeIngestService::class);
        $mockIngestService->shouldReceive('ingestByIds')
            ->once()
            ->andThrow(new \Exception('HTTP 500 Server Error'));

        $job = new IngestOdlukeDecision('decision-network');
        $job->handle($mockIngestService);

        $this->assertDatabaseHas('failed_ingestions', [
            'decision_id' => 'decision-network',
            'failure_reason' => FailedIngestion::REASON_NETWORK_ERROR,
        ]);
    }

    /** @test */
    public function it_retries_with_backoff_on_failure(): void
    {
        Log::shouldReceive('info')->once();
        Log::shouldReceive('warning')->once();

        $mockIngestService = Mockery::mock(OdlukeIngestService::class);
        $mockIngestService->shouldReceive('ingestByIds')
            ->once()
            ->andReturn([
                'inserted' => 0,
                'errors' => 1,
                'skipped' => 0,
            ]);

        $job = Mockery::mock(IngestOdlukeDecision::class.'[attempts,release]', ['decision-retry']);
        $job->shouldReceive('attempts')
            ->andReturn(1);
        $job->shouldReceive('release')
            ->once()
            ->with(60); // First backoff is 60 seconds

        $job->handle($mockIngestService);
    }

    /** @test */
    public function it_can_be_dispatched_to_queue(): void
    {
        Queue::fake();

        IngestOdlukeDecision::dispatch('decision-queue');

        Queue::assertPushed(IngestOdlukeDecision::class, function ($job) {
            return $job->decisionId === 'decision-queue';
        });
    }

    /** @test */
    public function it_can_be_dispatched_to_custom_queue(): void
    {
        $job = new IngestOdlukeDecision('decision-custom', ['queue' => 'high-priority']);

        // The job should be configured to use the custom queue
        $this->assertEquals('high-priority', $job->queue);
    }

    /** @test */
    public function it_has_correct_tags(): void
    {
        $job = new IngestOdlukeDecision('decision-tags');

        $tags = $job->tags();

        $this->assertContains('odluke', $tags);
        $this->assertContains('ingestion', $tags);
        $this->assertContains('decision:decision-tags', $tags);
    }

    /** @test */
    public function it_has_5_retry_attempts(): void
    {
        $job = new IngestOdlukeDecision('decision-retry');

        $this->assertEquals(5, $job->tries);
    }

    /** @test */
    public function it_has_exponential_backoff_strategy(): void
    {
        $job = new IngestOdlukeDecision('decision-backoff');

        $this->assertEquals([60, 120, 240, 480, 960], $job->backoff);
    }

    /** @test */
    public function it_has_300_second_timeout(): void
    {
        $job = new IngestOdlukeDecision('decision-timeout');

        $this->assertEquals(300, $job->timeout);
    }

    /** @test */
    public function failed_method_records_failure(): void
    {
        Log::shouldReceive('error')->once();

        $job = new IngestOdlukeDecision('decision-failed');
        $exception = new \Exception('Final failure');

        $job->failed($exception);

        $this->assertDatabaseHas('failed_ingestions', [
            'decision_id' => 'decision-failed',
        ]);
    }

    /** @test */
    public function it_detects_graph_sync_error_from_exception_message(): void
    {
        Log::shouldReceive('info')->once();
        Log::shouldReceive('warning')->once();

        $mockIngestService = Mockery::mock(OdlukeIngestService::class);
        $mockIngestService->shouldReceive('ingestByIds')
            ->once()
            ->andThrow(new \Exception('Neo4j graph sync failed'));

        $job = new IngestOdlukeDecision('decision-graph-error');
        $job->handle($mockIngestService);

        $this->assertDatabaseHas('failed_ingestions', [
            'decision_id' => 'decision-graph-error',
            'failure_reason' => FailedIngestion::REASON_GRAPH_SYNC_ERROR,
        ]);
    }

    /** @test */
    public function it_detects_embedding_error_from_exception_message(): void
    {
        Log::shouldReceive('info')->once();
        Log::shouldReceive('warning')->once();

        $mockIngestService = Mockery::mock(OdlukeIngestService::class);
        $mockIngestService->shouldReceive('ingestByIds')
            ->once()
            ->andThrow(new \Exception('Failed to generate embeddings'));

        $job = new IngestOdlukeDecision('decision-embed-error');
        $job->handle($mockIngestService);

        $this->assertDatabaseHas('failed_ingestions', [
            'decision_id' => 'decision-embed-error',
            'failure_reason' => FailedIngestion::REASON_EMBEDDING_ERROR,
        ]);
    }

    /** @test */
    public function it_logs_permanent_failure_after_max_attempts(): void
    {
        Log::shouldReceive('info')->once();
        Log::shouldReceive('error')->once();

        $mockIngestService = Mockery::mock(OdlukeIngestService::class);
        $mockIngestService->shouldReceive('ingestByIds')
            ->once()
            ->andReturn([
                'inserted' => 0,
                'errors' => 1,
                'skipped' => 0,
            ]);

        $job = Mockery::mock(IngestOdlukeDecision::class.'[attempts,fail]', ['decision-perm-fail']);
        $job->shouldReceive('attempts')
            ->andReturn(5); // Max attempts reached
        $job->shouldReceive('fail')
            ->once()
            ->with(Mockery::type(\Exception::class));

        $job->handle($mockIngestService);
    }

    /** @test */
    public function it_passes_options_to_ingest_service(): void
    {
        Log::shouldReceive('info')->times(2);

        $options = ['sync_graph' => true, 'force_reprocess' => true];

        $mockIngestService = Mockery::mock(OdlukeIngestService::class);
        $mockIngestService->shouldReceive('ingestByIds')
            ->once()
            ->with(['decision-options'], $options)
            ->andReturn([
                'inserted' => 1,
                'errors' => 0,
                'skipped' => 0,
            ]);

        $job = new IngestOdlukeDecision('decision-options', $options);
        $job->handle($mockIngestService);

        $this->addToAssertionCount(1);
    }
}
