<?php

namespace Tests\Unit\Jobs;

use App\Actions\Textract\ProcessDrivePdf;
use App\Jobs\ExtractTablesFromTextractJob;
use App\Jobs\GenerateEmbeddingsJob;
use App\Jobs\ProcessTextractJob;
use App\Jobs\RegenerateTextractEmbeddings;
use App\Models\TextractBatch;
use App\Models\TextractJob;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class ProcessTextractJobTest extends TestCase
{
    use UsesTestDatabase;

    // Helper to bind a simple stub for ProcessDrivePdf into the container
    private function bindProcessDrivePdfStub(callable $handler): void
    {
        $this->app->instance(ProcessDrivePdf::class, new class($handler)
        {
            private $handler;

            public function __construct(callable $handler)
            {
                $this->handler = $handler;
            }

            public function handle(string $driveFileId, string $driveFileName, bool $forceTextract = false): void
            {
                ($this->handler)($driveFileId, $driveFileName, $forceTextract);
            }
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_job_processes_textract_successfully(): void
    {
        // Arrange
        Queue::fake();

        $job = TextractJob::create([
            'drive_file_id' => 'test-drive-file-123',
            'drive_file_name' => 'test-document.pdf',
            'status' => 'pending',
            'queue_name' => 'textract',
        ]);

        // Stub ProcessDrivePdf action
        $this->bindProcessDrivePdfStub(function ($driveFileId, $driveFileName, $forceTextract) use ($job) {
            if (! ($driveFileId === $job->drive_file_id && $driveFileName === $job->drive_file_name && $forceTextract === true)) {
                throw new \RuntimeException('ProcessDrivePdf received unexpected arguments');
            }
            // Simulate what ProcessDrivePdf does - update the job
            $job->update([
                'status' => 'succeeded',
                'extracted_content' => 'Sample extracted text content from the PDF',
                'metadata' => [
                    'page_count' => 5,
                    'block_count' => 150,
                    'tables' => [],
                ],
            ]);
        });

        // Act
        $processorJob = new ProcessTextractJob($job->id);
        $processorJob->handle();

        // Assert
        $job->refresh();
        $this->assertEquals('completed', $job->status);
        $this->assertNotNull($job->metadata);
        $this->assertArrayHasKey('s3_json_key', $job->metadata);
        $this->assertArrayHasKey('s3_input_key', $job->metadata);
        $this->assertArrayHasKey('s3_output_key', $job->metadata);
        $this->assertArrayHasKey('processed_at', $job->metadata);

        // Verify performance metrics
        $this->assertNotNull($job->performance_metrics);
        $this->assertArrayHasKey('duration_seconds', $job->performance_metrics);
        $this->assertArrayHasKey('pages_processed', $job->performance_metrics);
        $this->assertArrayHasKey('blocks_extracted', $job->performance_metrics);
        $this->assertArrayHasKey('worker_id', $job->performance_metrics);
    }

    public function test_job_dispatches_follow_up_jobs_when_enabled(): void
    {
        // Arrange
        Queue::fake();
        Config::set('distributed-processing.textract.auto_extract_tables', true);
        Config::set('distributed-processing.textract.auto_generate_embeddings', true);

        $job = TextractJob::create([
            'drive_file_id' => 'test-drive-file-456',
            'drive_file_name' => 'test-document-2.pdf',
            'status' => 'pending',
        ]);

        // Stub ProcessDrivePdf
        $this->bindProcessDrivePdfStub(function () use ($job) {
            $job->update([
                'status' => 'succeeded',
                'extracted_content' => 'Content',
                'metadata' => ['page_count' => 1],
            ]);
        });

        // Act
        $processorJob = new ProcessTextractJob($job->id);
        $processorJob->handle();

        // Assert - Verify follow-up jobs were dispatched
        Queue::assertPushed(ExtractTablesFromTextractJob::class, function ($queuedJob) {
            return $queuedJob->queue === 'textract-tables';
        });

        // ADR-001: Use RegenerateTextractEmbeddings as canonical embedding job
        Queue::assertPushed(RegenerateTextractEmbeddings::class);

        // ADR-001: GenerateEmbeddingsJob (System C) must NOT be dispatched
        Queue::assertNotPushed(GenerateEmbeddingsJob::class);
    }

    public function test_job_skips_follow_up_jobs_when_disabled(): void
    {
        // Arrange
        Queue::fake();
        Config::set('distributed-processing.textract.auto_extract_tables', false);
        Config::set('distributed-processing.textract.auto_generate_embeddings', false);
        // Also disable model-level auto-sync to prevent the TextractJob model event
        // from dispatching RegenerateTextractEmbeddings when extracted_content changes
        Config::set('textract.auto_sync', false);

        $job = TextractJob::create([
            'drive_file_id' => 'test-drive-file-789',
            'drive_file_name' => 'test-document-3.pdf',
            'status' => 'pending',
        ]);

        // Stub ProcessDrivePdf
        $this->bindProcessDrivePdfStub(function () use ($job) {
            $job->update([
                'status' => 'succeeded',
                'extracted_content' => 'Content',
            ]);
        });

        // Act
        $processorJob = new ProcessTextractJob($job->id);
        $processorJob->handle();

        // Assert
        Queue::assertNotPushed(ExtractTablesFromTextractJob::class);
        // ADR-001: Use RegenerateTextractEmbeddings as canonical embedding job
        Queue::assertNotPushed(RegenerateTextractEmbeddings::class);
    }

    public function test_job_handles_missing_job_gracefully(): void
    {
        // Arrange
        $nonExistentId = 999999;

        Log::shouldReceive('error')
            ->once()
            ->with('ProcessTextractJob - Job not found', ['job_id' => (string) $nonExistentId]);

        // Act
        $processorJob = new ProcessTextractJob($nonExistentId);
        $processorJob->handle();

        // Assert - Job completes without throwing
        $this->assertTrue(true);
    }

    public function test_job_updates_status_to_processing_before_execution(): void
    {
        // Arrange
        Queue::fake();

        $job = TextractJob::create([
            'drive_file_id' => 'test-drive-file-abc',
            'drive_file_name' => 'test-document-4.pdf',
            'status' => 'pending',
        ]);

        // Stub ProcessDrivePdf
        $this->bindProcessDrivePdfStub(function () use ($job) {
            // Verify status was set to processing before this call
            $currentJob = TextractJob::find($job->id);
            if ($currentJob->status !== 'processing') {
                throw new \Exception('Job status was not set to processing');
            }

            $job->update([
                'status' => 'succeeded',
                'extracted_content' => 'Content',
            ]);
        });

        // Act
        $processorJob = new ProcessTextractJob($job->id);
        $processorJob->handle();

        // Assert
        $job->refresh();
        $this->assertEquals('completed', $job->status);
        $this->assertNotNull($job->processing_started_at);
        $this->assertNotNull($job->worker_id);
    }

    public function test_job_handles_processing_failure_and_marks_as_failed(): void
    {
        // Arrange
        Queue::fake();

        $job = TextractJob::create([
            'drive_file_id' => 'test-drive-file-xyz',
            'drive_file_name' => 'test-document-5.pdf',
            'status' => 'pending',
        ]);

        $exception = new \Exception('Textract processing failed');

        // Stub ProcessDrivePdf to throw exception
        $this->bindProcessDrivePdfStub(function () use ($exception) {
            throw $exception;
        });

        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'ProcessTextractJob - Failed'
                    && isset($context['error']);
            });

        // Act & Assert
        $processorJob = new ProcessTextractJob($job->id);

        try {
            $processorJob->handle();
        } catch (\Exception $e) {
            // Expected on first attempt
        }

        $job->refresh();
        $this->assertEquals('failed', $job->status);
        $this->assertNotNull($job->error);
        $this->assertStringContainsString('Textract processing failed', $job->error);
    }

    public function test_job_updates_batch_on_completion(): void
    {
        // Arrange
        Queue::fake();

        $batch = TextractBatch::create([
            'id' => '12345678-1234-1234-1234-123456789001',
            'batch_type' => 'folder',
            'status' => 'processing',
            'total_files' => 5,
            'processed_files' => 0,
            'failed_files' => 0,
        ]);

        $job = TextractJob::create([
            'drive_file_id' => 'test-drive-file-batch-1',
            'drive_file_name' => 'test-document-batch.pdf',
            'status' => 'pending',
            'batch_id' => $batch->id,
            'queue_name' => 'textract',
        ]);

        // Stub ProcessDrivePdf
        $this->bindProcessDrivePdfStub(function () use ($job) {
            $job->update([
                'status' => 'succeeded',
                'extracted_content' => 'Content',
            ]);
        });

        // Act
        $processorJob = new ProcessTextractJob($job->id, $batch->id);
        $processorJob->handle();

        // Assert
        $batch->refresh();
        $this->assertEquals(1, $batch->processed_files);
        $this->assertEquals(0, $batch->failed_files);
    }

    public function test_job_updates_batch_on_failure(): void
    {
        // Arrange
        Queue::fake();

        $batch = TextractBatch::create([
            'id' => '12345678-1234-1234-1234-123456789002',
            'batch_type' => 'folder',
            'status' => 'processing',
            'total_files' => 5,
            'processed_files' => 0,
            'failed_files' => 0,
        ]);

        $job = TextractJob::create([
            'drive_file_id' => 'test-drive-file-batch-2',
            'drive_file_name' => 'test-document-batch-fail.pdf',
            'status' => 'pending',
            'batch_id' => $batch->id,
            'queue_name' => 'textract',
        ]);

        // Stub ProcessDrivePdf to fail
        $this->bindProcessDrivePdfStub(function () {
            throw new \Exception('Processing failed');
        });

        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('error')->andReturn(null);

        // Act
        $processorJob = new ProcessTextractJob($job->id, $batch->id);

        try {
            $processorJob->handle();
        } catch (\Exception $e) {
            // Expected
        }

        // Assert
        $batch->refresh();
        $this->assertEquals(1, $batch->processed_files);
        $this->assertEquals(1, $batch->failed_files);
    }

    public function test_failed_method_updates_job_correctly(): void
    {
        // Arrange
        $job = TextractJob::create([
            'drive_file_id' => 'test-drive-file-perm-fail',
            'drive_file_name' => 'test-document-perm-fail.pdf',
            'status' => 'processing',
            'queue_name' => 'textract',
        ]);

        $exception = new \Exception('Permanent failure');

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message) {
                return $message === 'ProcessTextractJob - Permanently failed';
            });

        // Act
        $processorJob = new ProcessTextractJob($job->id);
        $processorJob->failed($exception);

        // Assert
        $job->refresh();
        $this->assertEquals('failed', $job->status);
        $this->assertStringContainsString('Failed after 3 attempts', $job->error);
        $this->assertStringContainsString('Permanent failure', $job->error);
    }

    public function test_job_tags_are_set_correctly(): void
    {
        // Arrange
        $batchId = '12345678-1234-1234-1234-123456789999';
        $job = TextractJob::create([
            'drive_file_id' => 'test-drive-file-tags',
            'drive_file_name' => 'test-document-tags.pdf',
            'queue_name' => 'textract',
        ]);

        // Act
        $processorJob = new ProcessTextractJob($job->id, $batchId);
        $tags = $processorJob->tags();

        // Assert
        $this->assertContains('textract', $tags);
        $this->assertContains('job:'.$job->id, $tags);
        $this->assertContains('batch:'.$batchId, $tags);
    }

    public function test_job_uses_correct_queue_from_textract_job(): void
    {
        // Arrange
        $job = TextractJob::create([
            'drive_file_id' => 'test-drive-file-queue',
            'drive_file_name' => 'test-document-queue.pdf',
            'queue_name' => 'high-priority',
        ]);

        // Act
        $processorJob = new ProcessTextractJob($job->id);

        // Assert
        $this->assertEquals('high-priority', $processorJob->queue);
    }

    public function test_job_stores_s3_keys_with_environment_prefixes(): void
    {
        // Arrange
        Queue::fake();
        Config::set('distributed-processing.textract.auto_extract_tables', false);
        Config::set('distributed-processing.textract.auto_generate_embeddings', false);

        // Set env vars in $_SERVER so Laravel's env() helper picks them up
        $_SERVER['S3_JSON_PREFIX'] = 'custom-json';
        $_SERVER['S3_INPUT_PREFIX'] = 'custom-input';
        $_SERVER['S3_OUTPUT_PREFIX'] = 'custom-output';

        $job = TextractJob::create([
            'drive_file_id' => 'test-file-s3-keys',
            'drive_file_name' => 'test-s3.pdf',
            'status' => 'pending',
            'queue_name' => 'textract',
        ]);

        // Stub ProcessDrivePdf
        $this->bindProcessDrivePdfStub(function () use ($job) {
            $job->update([
                'status' => 'succeeded',
                'extracted_content' => 'Content',
            ]);
        });

        // Mock Log to prevent actual logging
        $mockChannel = Mockery::mock(\Psr\Log\LoggerInterface::class);
        $mockChannel->shouldReceive('info', 'error', 'warning')->andReturnNull();
        Log::shouldReceive('channel')->andReturn($mockChannel);
        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('error')->andReturn(null);
        Log::shouldReceive('withContext')->andReturnSelf();

        // Act
        $processorJob = new ProcessTextractJob($job->id);
        $processorJob->handle();

        // Assert
        $job->refresh();
        $this->assertStringContainsString('custom-json', $job->metadata['s3_json_key']);
        $this->assertStringContainsString('custom-input', $job->metadata['s3_input_key']);
        $this->assertStringContainsString('custom-output', $job->metadata['s3_output_key']);
        $this->assertStringContainsString($job->drive_file_id, $job->metadata['s3_json_key']);

        // Cleanup env vars
        unset($_SERVER['S3_JSON_PREFIX'], $_SERVER['S3_INPUT_PREFIX'], $_SERVER['S3_OUTPUT_PREFIX']);
    }

    /** @test */
    public function it_processes_textract_job_successfully()
    {
        Queue::fake();

        $job = TextractJob::factory()->queued()->create([
            'drive_file_id' => 'test-file-123',
            'drive_file_name' => 'document.pdf',
        ]);

        // Stub ProcessDrivePdf
        $this->bindProcessDrivePdfStub(function ($driveFileId, $driveFileName, $forceTextract) use ($job) {
            if (! ($driveFileId === 'test-file-123' && $driveFileName === 'document.pdf' && $forceTextract === true)) {
                throw new \RuntimeException('Unexpected args');
            }
            // Simulate ProcessDrivePdf updating the job
            $job->update([
                'status' => 'succeeded',
                'extracted_content' => 'Extracted text content',
            ]);
        });

        $mockChannel = Mockery::mock(\Psr\Log\LoggerInterface::class);
        $mockChannel->shouldReceive('info')->andReturnNull();
        $mockChannel->shouldReceive('error')->andReturnNull();

        Log::shouldReceive('channel')
            ->andReturn($mockChannel);
        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('error')->andReturn(null);

        $queueJob = new ProcessTextractJob($job->id);
        $queueJob->handle();

        $job->refresh();
        $this->assertEquals('completed', $job->status); // ProcessTextractJob updates to 'completed'
        $this->assertNotNull($job->extracted_content);
    }

    /** @test */
    public function it_updates_job_status_to_processing_when_starting()
    {
        Queue::fake();

        $job = TextractJob::factory()->queued()->create();

        // Stub ProcessDrivePdf
        $this->bindProcessDrivePdfStub(function () use ($job) {
            $job->update(['status' => 'succeeded']);
        });

        $queueJob = new ProcessTextractJob($job->id);
        $queueJob->handle();

        // Should have been updated to processing, then completed
        $job->refresh();
        $this->assertEquals('completed', $job->status);
        $this->assertNotNull($job->processing_started_at);
    }

    /** @test */
    public function it_stores_s3_keys_in_metadata()
    {
        Queue::fake();

        $job = TextractJob::factory()->queued()->create([
            'drive_file_id' => 'file-456',
        ]);

        // Stub ProcessDrivePdf
        $this->bindProcessDrivePdfStub(function () use ($job) {
            $job->update(['status' => 'succeeded']);
        });

        $queueJob = new ProcessTextractJob($job->id);
        $queueJob->handle();

        $job->refresh();
        $this->assertNotNull($job->metadata);
        $this->assertArrayHasKey('s3_json_key', $job->metadata);
        $this->assertArrayHasKey('s3_input_key', $job->metadata);
        $this->assertArrayHasKey('s3_output_key', $job->metadata);
        $this->assertArrayHasKey('processed_at', $job->metadata);
    }

    /** @test */
    public function it_handles_missing_job_gracefully()
    {
        // Try to process a non-existent job with a bigint ID
        $nonExistentId = 999999999;
        $queueJob = new ProcessTextractJob($nonExistentId);

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) use ($nonExistentId) {
                return $message === 'ProcessTextractJob - Job not found'
                    && $context['job_id'] === (string) $nonExistentId;
            });

        // Should not throw exception, just log and return
        $queueJob->handle();

        // No assertion needed, just verify it doesn't crash
        $this->assertTrue(true);
    }

    /** @test */
    public function it_sets_worker_id_during_processing()
    {
        Queue::fake();

        $job = TextractJob::factory()->queued()->create();

        // Stub ProcessDrivePdf
        $this->bindProcessDrivePdfStub(function () use ($job) {
            $job->update(['status' => 'succeeded']);
        });

        $queueJob = new ProcessTextractJob($job->id);
        $queueJob->handle();

        $job->refresh();
        $this->assertNotNull($job->worker_id);
        $this->assertEquals(getmypid(), $job->worker_id);
    }

    /** @test */
    public function it_tracks_retry_count()
    {
        Queue::fake();

        $job = TextractJob::factory()->queued()->create();

        // Stub ProcessDrivePdf
        $this->bindProcessDrivePdfStub(function () use ($job) {
            $job->update(['status' => 'succeeded']);
        });

        $queueJob = new ProcessTextractJob($job->id);

        // Simulate first attempt
        $queueJob->handle();

        $job->refresh();
        // First attempt should have retry_count = 0
        $this->assertEquals(0, $job->retry_count);
    }

    /** @test */
    public function it_sets_correct_queue_name()
    {
        $job = TextractJob::factory()->queued()->create([
            'queue_name' => 'high-priority',
        ]);

        $queueJob = new ProcessTextractJob($job->id);

        // Verify the queue is set correctly
        $this->assertEquals('high-priority', $queueJob->queue);
    }

    /** @test */
    public function it_uses_default_queue_when_not_specified()
    {
        // Create job with 'textract' as the default queue
        $job = TextractJob::factory()->queued()->create([
            'queue_name' => 'textract',
        ]);

        $queueJob = new ProcessTextractJob($job->id);

        // Should use the queue_name from the job
        $this->assertEquals('textract', $queueJob->queue);
    }

    /** @test */
    public function it_has_correct_retry_configuration()
    {
        $job = TextractJob::factory()->queued()->create();
        $queueJob = new ProcessTextractJob($job->id);

        $this->assertEquals(3, $queueJob->tries);
        $this->assertEquals([60, 300, 900], $queueJob->backoff);
        $this->assertEquals(1800, $queueJob->timeout);
    }

    /** @test */
    public function it_can_be_dispatched_to_queue()
    {
        Queue::fake();

        $job = TextractJob::factory()->queued()->create();

        ProcessTextractJob::dispatch($job->id);

        // Just verify the job was pushed
        Queue::assertPushed(ProcessTextractJob::class);
    }

    /** @test */
    public function it_can_be_dispatched_with_batch_id()
    {
        // Create a TextractBatch first
        $batch = TextractBatch::create([
            'id' => '12345678-1234-1234-1234-123456789998',
            'batch_type' => 'folder',
            'status' => 'processing',
            'total_files' => 1,
            'processed_files' => 0,
            'failed_files' => 0,
        ]);

        $job = TextractJob::factory()->queued()->create([
            'batch_id' => $batch->id,
        ]);

        $queueJob = new ProcessTextractJob($job->id, $batch->id);

        // Use reflection to access protected properties
        $reflection = new \ReflectionClass($queueJob);

        $jobIdProperty = $reflection->getProperty('jobId');
        $jobIdProperty->setAccessible(true);
        $this->assertEquals($job->id, $jobIdProperty->getValue($queueJob));

        $batchIdProperty = $reflection->getProperty('batchId');
        $batchIdProperty->setAccessible(true);
        $this->assertEquals($batch->id, $batchIdProperty->getValue($queueJob));
    }
}
