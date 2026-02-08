<?php

namespace Tests\Feature\Jobs;

use App\Jobs\IngestOdlukeDecision;
use App\Models\FailedIngestion;
use App\Services\Odluke\OdlukeIngestService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class IngestOdlukeDecisionTest extends TestCase
{
    use UsesTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Log::spy();
    }

    /** @test */
    public function it_ingests_successfully_and_marks_as_succeeded()
    {
        // Create a failed ingestion to track
        $failedIngestion = FailedIngestion::create([
            'decision_id' => 'test-123',
            'failure_reason' => FailedIngestion::REASON_NETWORK_ERROR,
            'last_error_message' => 'Previous error',
            'status' => FailedIngestion::STATUS_RETRYING,
        ]);

        // Mock successful ingestion
        $mockService = $this->mock(OdlukeIngestService::class);
        $mockService->shouldReceive('ingestByIds')
            ->once()
            ->with(['test-123'], [])
            ->andReturn([
                'inserted' => 5,
                'errors' => 0,
                'skipped' => 0,
                'graph_synced' => 3,
            ]);

        $job = new IngestOdlukeDecision('test-123', [], (string) $failedIngestion->id);
        $job->handle($mockService);

        // Verify failed ingestion was marked as succeeded
        $failedIngestion->refresh();
        $this->assertEquals(FailedIngestion::STATUS_SUCCEEDED, $failedIngestion->status);
        $this->assertNotNull($failedIngestion->succeeded_at);
        $this->assertArrayHasKey('result', $failedIngestion->success_details);
    }

    /** @test */
    public function it_marks_existing_failure_as_succeeded_without_explicit_tracking()
    {
        // Create a failed ingestion without passing ID to job
        $failedIngestion = FailedIngestion::create([
            'decision_id' => 'test-456',
            'source_type' => 'odluke',
            'failure_reason' => FailedIngestion::REASON_NETWORK_ERROR,
            'last_error_message' => 'Previous error',
            'status' => FailedIngestion::STATUS_PENDING,
        ]);

        // Mock successful ingestion
        $mockService = $this->mock(OdlukeIngestService::class);
        $mockService->shouldReceive('ingestByIds')
            ->once()
            ->andReturn([
                'inserted' => 3,
                'errors' => 0,
                'skipped' => 0,
            ]);

        $job = new IngestOdlukeDecision('test-456', []);
        $job->handle($mockService);

        // Verify existing failure was found and marked as succeeded
        $failedIngestion->refresh();
        $this->assertEquals(FailedIngestion::STATUS_SUCCEEDED, $failedIngestion->status);
    }

    /** @test */
    public function it_records_failure_for_empty_text()
    {
        $mockService = $this->mock(OdlukeIngestService::class);
        $mockService->shouldReceive('ingestByIds')
            ->once()
            ->andReturn([
                'inserted' => 0,
                'errors' => 0,
                'skipped' => 1,
            ]);

        $job = new IngestOdlukeDecision('test-empty', []);

        // Simulate the job has attempted once
        $this->setJobAttempts($job, 1);

        $job->handle($mockService);

        // Verify failure was recorded
        $failure = FailedIngestion::where('decision_id', 'test-empty')->first();
        $this->assertNotNull($failure);
        $this->assertEquals(FailedIngestion::REASON_EMPTY_TEXT, $failure->failure_reason);
        $this->assertStringContainsString('Empty text', $failure->last_error_message);
    }

    /** @test */
    public function it_records_failure_for_extraction_error()
    {
        $mockService = $this->mock(OdlukeIngestService::class);
        $mockService->shouldReceive('ingestByIds')
            ->once()
            ->andReturn([
                'inserted' => 0,
                'errors' => 1,
                'skipped' => 0,
            ]);

        $job = new IngestOdlukeDecision('test-extraction', []);
        $this->setJobAttempts($job, 1);

        $job->handle($mockService);

        $failure = FailedIngestion::where('decision_id', 'test-extraction')->first();
        $this->assertNotNull($failure);
        $this->assertEquals(FailedIngestion::REASON_EXTRACTION_ERROR, $failure->failure_reason);
    }

    /** @test */
    public function it_categorizes_network_errors()
    {
        $mockService = $this->mock(OdlukeIngestService::class);
        $mockService->shouldReceive('ingestByIds')
            ->once()
            ->andThrow(new \Exception('HTTP connection timeout'));

        $job = new IngestOdlukeDecision('test-network', []);
        $this->setJobAttempts($job, 1);

        $job->handle($mockService);

        $failure = FailedIngestion::where('decision_id', 'test-network')->first();
        $this->assertNotNull($failure);
        $this->assertEquals(FailedIngestion::REASON_NETWORK_ERROR, $failure->failure_reason);
        $this->assertStringContainsString('HTTP', $failure->last_error_message);
    }

    /** @test */
    public function it_categorizes_graph_sync_errors()
    {
        $mockService = $this->mock(OdlukeIngestService::class);
        $mockService->shouldReceive('ingestByIds')
            ->once()
            ->andThrow(new \Exception('Graph sync failed: Neo4j unavailable'));

        $job = new IngestOdlukeDecision('test-graph', []);
        $this->setJobAttempts($job, 1);

        $job->handle($mockService);

        $failure = FailedIngestion::where('decision_id', 'test-graph')->first();
        $this->assertNotNull($failure);
        $this->assertEquals(FailedIngestion::REASON_GRAPH_SYNC_ERROR, $failure->failure_reason);
    }

    /** @test */
    public function it_categorizes_embedding_errors()
    {
        $mockService = $this->mock(OdlukeIngestService::class);
        $mockService->shouldReceive('ingestByIds')
            ->once()
            ->andThrow(new \Exception('Embedding generation failed'));

        $job = new IngestOdlukeDecision('test-embed', []);
        $this->setJobAttempts($job, 1);

        $job->handle($mockService);

        $failure = FailedIngestion::where('decision_id', 'test-embed')->first();
        $this->assertNotNull($failure);
        $this->assertEquals(FailedIngestion::REASON_EMBEDDING_ERROR, $failure->failure_reason);
    }

    /** @test */
    public function it_updates_attempt_count_on_retry()
    {
        $failedIngestion = FailedIngestion::create([
            'decision_id' => 'test-retry',
            'failure_reason' => FailedIngestion::REASON_NETWORK_ERROR,
            'last_error_message' => 'First error',
            'attempt_count' => 1,
        ]);

        $mockService = $this->mock(OdlukeIngestService::class);
        $mockService->shouldReceive('ingestByIds')
            ->once()
            ->andThrow(new \Exception('Second error'));

        $job = new IngestOdlukeDecision('test-retry', [], (string) $failedIngestion->id);
        $this->setJobAttempts($job, 2);

        $job->handle($mockService);

        $failedIngestion->refresh();
        $this->assertEquals(2, $failedIngestion->attempt_count);
        $this->assertEquals('Second error', $failedIngestion->last_error_message);
    }

    /** @test */
    public function it_respects_max_attempts_configuration()
    {
        $job = new IngestOdlukeDecision('test-max', []);

        $this->assertEquals(5, $job->tries);
    }

    /** @test */
    public function it_uses_exponential_backoff()
    {
        $job = new IngestOdlukeDecision('test-backoff', []);

        $this->assertEquals([60, 120, 240, 480, 960], $job->backoff);
    }

    /** @test */
    public function it_has_appropriate_timeout()
    {
        $job = new IngestOdlukeDecision('test-timeout', []);

        $this->assertEquals(300, $job->timeout);
    }

    /** @test */
    public function it_dispatches_to_specified_queue()
    {
        Queue::fake();

        $job = new IngestOdlukeDecision('test-queue', ['queue' => 'ingestion']);

        // Check the queue was set in constructor
        $this->assertEquals('ingestion', $job->queue);
    }

    /** @test */
    public function it_provides_job_tags_for_monitoring()
    {
        $job = new IngestOdlukeDecision('test-tags', []);

        $tags = $job->tags();

        $this->assertContains('odluke', $tags);
        $this->assertContains('ingestion', $tags);
        $this->assertContains('decision:test-tags', $tags);
    }

    /** @test */
    public function failed_callback_records_permanent_failure()
    {
        $failedIngestion = FailedIngestion::create([
            'decision_id' => 'test-failed-callback',
            'failure_reason' => FailedIngestion::REASON_NETWORK_ERROR,
            'last_error_message' => 'Error',
            'status' => FailedIngestion::STATUS_RETRYING,
        ]);

        $job = new IngestOdlukeDecision('test-failed-callback', [], (string) $failedIngestion->id);
        $this->setJobAttempts($job, 5);

        $exception = new \Exception('Permanent failure');
        $job->failed($exception);

        // Verify error was logged
        Log::shouldHaveReceived('error')
            ->with('IngestOdlukeDecision job failed permanently', \Mockery::on(function ($context) {
                return $context['decision_id'] === 'test-failed-callback'
                    && $context['error'] === 'Permanent failure';
            }));

        // Verify failure was recorded
        $failedIngestion->refresh();
        $this->assertNotNull($failedIngestion->error_details);
    }

    /** @test */
    public function it_logs_job_start()
    {
        $mockService = $this->mock(OdlukeIngestService::class);
        $mockService->shouldReceive('ingestByIds')
            ->andReturn(['inserted' => 1, 'errors' => 0]);

        $job = new IngestOdlukeDecision('test-log', []);
        $this->setJobAttempts($job, 1);

        $job->handle($mockService);

        Log::shouldHaveReceived('info')
            ->with('IngestOdlukeDecision job starting', \Mockery::on(function ($context) {
                return $context['decision_id'] === 'test-log'
                    && $context['attempt'] === 1
                    && $context['max_tries'] === 5;
            }));
    }

    /**
     * Helper to set job attempts via reflection
     */
    protected function setJobAttempts($job, int $attempts): void
    {
        $reflection = new \ReflectionClass($job);
        $property = $reflection->getProperty('job');
        $property->setAccessible(true);

        // Create a mock job instance
        $mockJob = \Mockery::mock();
        $mockJob->shouldReceive('attempts')->andReturn($attempts);

        $property->setValue($job, $mockJob);
    }
}
