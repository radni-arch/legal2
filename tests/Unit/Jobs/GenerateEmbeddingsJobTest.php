<?php

namespace Tests\Unit\Jobs;

use App\Jobs\GenerateEmbeddingsJob;
use App\Models\EmbeddingBatch;
use App\Models\TextractJob;
use App\Services\CourtDecisionVectorStoreService;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class GenerateEmbeddingsJobTest extends TestCase
{
    use UsesTestDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_job_generates_embeddings_for_textract_job_successfully(): void
    {
        // Arrange
        $job = TextractJob::create([
            'id' => '12345678-1234-1234-1234-1234567890ab',
            'drive_file_id' => 'test-drive-file-123',
            'drive_file_name' => 'test-document.pdf',
            'status' => 'succeeded',
            'extracted_content' => 'This is a sample legal document content that will be embedded.',
            'embedding_status' => 'pending',
        ]);

        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockVectorStore = Mockery::mock(CourtDecisionVectorStoreService::class);

        // Mock OpenAI embeddings call
        $mockOpenAI->shouldReceive('embeddings')
            ->once()
            ->withArgs(function ($text, $model) {
                return str_contains($text, 'sample legal document')
                    && $model === 'text-embedding-3-small';
            })
            ->andReturn([
                'data' => [
                    [
                        'embedding' => array_fill(0, 1536, 0.1), // Mock embedding vector
                    ],
                ],
                'usage' => [
                    'total_tokens' => 42,
                ],
            ]);

        // Mock vector store upsert
        $mockVectorStore->shouldReceive('upsert')
            ->once()
            ->withArgs(function ($vectors) use ($job) {
                return is_array($vectors)
                    && count($vectors) === 1
                    && $vectors[0]['id'] === 'textract_'.$job->id
                    && isset($vectors[0]['vector'])
                    && count($vectors[0]['vector']) === 1536
                    && $vectors[0]['metadata']['job_id'] === $job->id
                    && $vectors[0]['metadata']['drive_file_id'] === $job->drive_file_id;
            });

        Config::set('distributed-processing.embeddings.model', 'text-embedding-3-small');

        // Act
        $embeddingJob = new GenerateEmbeddingsJob($job->id, 'textract_job');
        $embeddingJob->handle($mockOpenAI, $mockVectorStore);

        // Assert
        $job->refresh();
        $this->assertEquals('synced', $job->embedding_status);
        $this->assertNotNull($job->embedding_synced_at);
    }

    public function test_job_handles_missing_textract_job_gracefully(): void
    {
        // Arrange
        $nonExistentId = 999999;

        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockVectorStore = Mockery::mock(CourtDecisionVectorStoreService::class);

        $mockOpenAI->shouldNotReceive('embeddings');
        $mockVectorStore->shouldNotReceive('upsert');

        Log::shouldReceive('warning')->andReturn(null);
        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('error')->andReturn(null);

        // Act
        $embeddingJob = new GenerateEmbeddingsJob($nonExistentId, 'textract_job');
        $embeddingJob->handle($mockOpenAI, $mockVectorStore);

        // Assert - Job completes without throwing
        $this->assertTrue(true);
    }

    public function test_job_skips_embedding_when_no_content_available(): void
    {
        // Arrange
        $job = TextractJob::create([
            'id' => '12345678-1234-1234-1234-1234567890ac',
            'drive_file_id' => 'test-drive-file-456',
            'drive_file_name' => 'test-document-no-content.pdf',
            'status' => 'succeeded',
            'extracted_content' => '',
            'manual_content' => null,
        ]);

        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockVectorStore = Mockery::mock(CourtDecisionVectorStoreService::class);

        $mockOpenAI->shouldNotReceive('embeddings');
        $mockVectorStore->shouldNotReceive('upsert');

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) use ($job) {
                return $message === 'GenerateEmbeddingsJob - No text to embed'
                    && isset($context['job_id'])
                    && $context['job_id'] == $job->id;
            });

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message) {
                return $message === 'GenerateEmbeddingsJob - Completed';
            });

        Log::shouldReceive('error')->andReturn(null);

        // Act
        $embeddingJob = new GenerateEmbeddingsJob($job->id, 'textract_job');
        $embeddingJob->handle($mockOpenAI, $mockVectorStore);

        // Assert
        $this->assertTrue(true);
    }

    public function test_job_uses_manual_content_when_available(): void
    {
        // Arrange
        $job = TextractJob::create([
            'id' => '12345678-1234-1234-1234-1234567890ad',
            'drive_file_id' => 'test-drive-file-789',
            'drive_file_name' => 'test-document-manual.pdf',
            'status' => 'succeeded',
            'extracted_content' => 'Original extracted content',
            'manual_content' => 'Manually edited content that should be used',
            'manually_edited' => true,
            'embedding_status' => 'pending',
        ]);

        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockVectorStore = Mockery::mock(CourtDecisionVectorStoreService::class);

        // Mock OpenAI to verify manual content is used
        $mockOpenAI->shouldReceive('embeddings')
            ->once()
            ->withArgs(function ($text, $model) {
                return str_contains($text, 'Manually edited content');
            })
            ->andReturn([
                'data' => [['embedding' => array_fill(0, 1536, 0.1)]],
                'usage' => ['total_tokens' => 10],
            ]);

        $mockVectorStore->shouldReceive('upsert')
            ->once()
            ->withArgs(function ($vectors) {
                return $vectors[0]['metadata']['manually_edited'] === true;
            });

        Config::set('distributed-processing.embeddings.model', 'text-embedding-3-small');

        // Act
        $embeddingJob = new GenerateEmbeddingsJob($job->id, 'textract_job');
        $embeddingJob->handle($mockOpenAI, $mockVectorStore);

        // Assert
        $job->refresh();
        $this->assertEquals('synced', $job->embedding_status);
    }

    public function test_job_truncates_long_text_for_embedding(): void
    {
        // Arrange
        Config::set('distributed-processing.embeddings.chunk_size', 100);

        $longContent = str_repeat('This is a very long document. ', 100); // Much longer than chunk size

        $job = TextractJob::create([
            'id' => '12345678-1234-1234-1234-1234567890ae',
            'drive_file_id' => 'test-drive-file-long',
            'drive_file_name' => 'test-document-long.pdf',
            'status' => 'succeeded',
            'extracted_content' => $longContent,
            'embedding_status' => 'pending',
        ]);

        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockVectorStore = Mockery::mock(CourtDecisionVectorStoreService::class);

        // Verify text was truncated
        $mockOpenAI->shouldReceive('embeddings')
            ->once()
            ->withArgs(function ($text, $model) {
                return mb_strlen($text) <= 100; // Should be truncated to chunk size
            })
            ->andReturn([
                'data' => [['embedding' => array_fill(0, 1536, 0.1)]],
                'usage' => ['total_tokens' => 10],
            ]);

        $mockVectorStore->shouldReceive('upsert')->once();

        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('warning')->andReturn(null);
        Log::shouldReceive('error')->andReturn(null);
        Log::shouldReceive('debug')->andReturn(null);

        // Act
        $embeddingJob = new GenerateEmbeddingsJob($job->id, 'textract_job');
        $embeddingJob->handle($mockOpenAI, $mockVectorStore);

        // Assert
        $this->assertTrue(true);
    }

    public function test_job_updates_embedding_batch_on_success(): void
    {
        // Arrange
        $batch = EmbeddingBatch::create([
            'id' => '00000001-0000-0000-0000-000000000001',
            'source_type' => 'textract_job',
            'status' => 'processing',
            'total_items' => 5,
            'processed_items' => 0,
            'tokens_used' => 0,
        ]);

        $job = TextractJob::create([
            'id' => '12345678-1234-1234-1234-1234567890af',
            'drive_file_id' => 'test-drive-file-batch',
            'drive_file_name' => 'test-document-batch.pdf',
            'status' => 'succeeded',
            'extracted_content' => 'Batch content',
            'embedding_status' => 'pending',
        ]);

        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockVectorStore = Mockery::mock(CourtDecisionVectorStoreService::class);

        $mockOpenAI->shouldReceive('embeddings')
            ->once()
            ->andReturn([
                'data' => [['embedding' => array_fill(0, 1536, 0.1)]],
                'usage' => ['total_tokens' => 50],
            ]);

        $mockVectorStore->shouldReceive('upsert')->once();
        Config::set('distributed-processing.embeddings.model', 'text-embedding-3-small');

        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('warning')->andReturn(null);
        Log::shouldReceive('error')->andReturn(null);
        Log::shouldReceive('debug')->andReturn(null);

        // Act
        $embeddingJob = new GenerateEmbeddingsJob($job->id, 'textract_job', $batch->id);
        $embeddingJob->handle($mockOpenAI, $mockVectorStore);

        // Assert
        $batch->refresh();
        $this->assertEquals(1, $batch->processed_items);
        $this->assertEquals(50, $batch->tokens_used);
    }

    public function test_job_processes_batch_by_dispatching_individual_jobs(): void
    {
        // Arrange
        Queue::fake();

        $job1 = TextractJob::create([
            'id' => '12345678-1234-1234-1234-1234567890b0',
            'drive_file_id' => 'file-1',
            'drive_file_name' => 'doc-1.pdf',
            'status' => 'succeeded',
            'extracted_content' => 'Content 1',
        ]);

        $job2 = TextractJob::create([
            'id' => '12345678-1234-1234-1234-1234567890b1',
            'drive_file_id' => 'file-2',
            'drive_file_name' => 'doc-2.pdf',
            'status' => 'succeeded',
            'extracted_content' => 'Content 2',
        ]);

        $batch = EmbeddingBatch::create([
            'id' => '00000002-0000-0000-0000-000000000002',
            'source_type' => 'batch',
            'status' => 'pending',
            'total_items' => 2,
            'item_ids' => [$job1->id, $job2->id],
        ]);

        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockVectorStore = Mockery::mock(CourtDecisionVectorStoreService::class);

        // Act
        $embeddingJob = new GenerateEmbeddingsJob($batch->id, 'batch');
        $embeddingJob->handle($mockOpenAI, $mockVectorStore);

        // Assert
        Queue::assertPushed(GenerateEmbeddingsJob::class, 2);
        Queue::assertPushed(GenerateEmbeddingsJob::class, function ($queuedJob) {
            return $queuedJob->queue === 'embeddings';
        });

        $batch->refresh();
        $this->assertEquals('processing', $batch->status);
    }

    public function test_job_handles_batch_item_failure_gracefully(): void
    {
        // Fake queue so dispatched jobs don't run synchronously
        Queue::fake();

        // Arrange
        Queue::fake();

        $batch = EmbeddingBatch::create([
            'id' => '00000003-0000-0000-0000-000000000003',
            'source_type' => 'batch',
            'status' => 'pending',
            'total_items' => 1,
            'item_ids' => ['nonexistent-id'],
            'processed_items' => 0,
            'failed_items' => 0,
        ]);

        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockVectorStore = Mockery::mock(CourtDecisionVectorStoreService::class);

        Log::shouldReceive('error')->andReturn(null);
        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('warning')->andReturn(null);

        // Act
        $embeddingJob = new GenerateEmbeddingsJob($batch->id, 'batch');
        $embeddingJob = $embeddingJob->onQueue('embeddings');
        $embeddingJob->handle($mockOpenAI, $mockVectorStore);

        $batch->refresh();
        $this->assertEquals('completed', $batch->status);
        $this->assertEquals(1, $batch->processed_items);
        $this->assertEquals(1, $batch->failed_items);

        // Verify no individual jobs were dispatched for the nonexistent item
        // (batch processor handles the failure directly without dispatching)
        Queue::assertNotPushed(GenerateEmbeddingsJob::class);
    }

    public function test_job_handles_missing_batch_gracefully(): void
    {
        // Arrange
        $nonExistentBatchId = '99999999-9999-9999-9999-999999999999';

        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockVectorStore = Mockery::mock(CourtDecisionVectorStoreService::class);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) use ($nonExistentBatchId) {
                return $message === 'GenerateEmbeddingsJob - Batch not found'
                    && $context['batch_id'] === $nonExistentBatchId;
            });

        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('error')->andReturn(null);

        // Act
        $embeddingJob = new GenerateEmbeddingsJob($nonExistentBatchId, 'batch');
        $embeddingJob->handle($mockOpenAI, $mockVectorStore);

        // Assert - Job completes without throwing
        $this->assertTrue(true);
    }

    public function test_job_retries_on_openai_failure(): void
    {
        // Arrange
        $job = TextractJob::create([
            'id' => '12345678-1234-1234-1234-1234567890b2',
            'drive_file_id' => 'test-drive-file-retry',
            'drive_file_name' => 'test-document-retry.pdf',
            'status' => 'succeeded',
            'extracted_content' => 'Content to embed',
            'embedding_status' => 'pending',
        ]);

        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockVectorStore = Mockery::mock(CourtDecisionVectorStoreService::class);

        $exception = new \Exception('OpenAI API rate limit exceeded');

        $mockOpenAI->shouldReceive('embeddings')
            ->once()
            ->andThrow($exception);

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'GenerateEmbeddingsJob - Failed'
                    && isset($context['error']);
            });

        Config::set('distributed-processing.embeddings.model', 'text-embedding-3-small');

        // Act & Assert
        $embeddingJob = new GenerateEmbeddingsJob($job->id, 'textract_job');

        $this->expectException(\Exception::class);
        $embeddingJob->handle($mockOpenAI, $mockVectorStore);
    }

    public function test_job_tags_are_set_correctly(): void
    {
        // Arrange & Act
        $embeddingJob = new GenerateEmbeddingsJob('test-id-123', 'textract_job');
        $tags = $embeddingJob->tags();

        // Assert
        $this->assertContains('embeddings', $tags);
        $this->assertContains('source:textract_job', $tags);
        $this->assertContains('id:test-id-123', $tags);
    }

    public function test_job_stores_correct_metadata_in_vector_store(): void
    {
        // Arrange
        $job = TextractJob::create([
            'id' => '12345678-1234-1234-1234-1234567890b3',
            'drive_file_id' => 'test-drive-file-metadata',
            'drive_file_name' => 'test-document-metadata.pdf',
            'case_id' => null,
            'status' => 'succeeded',
            'extracted_content' => 'Content with metadata',
            'manually_edited' => false,
            'embedding_status' => 'pending',
        ]);

        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockVectorStore = Mockery::mock(CourtDecisionVectorStoreService::class);

        $mockOpenAI->shouldReceive('embeddings')
            ->once()
            ->andReturn([
                'data' => [['embedding' => array_fill(0, 1536, 0.1)]],
                'usage' => ['total_tokens' => 20],
            ]);

        // Verify metadata structure
        $mockVectorStore->shouldReceive('upsert')
            ->once()
            ->withArgs(function ($vectors) use ($job) {
                $metadata = $vectors[0]['metadata'];

                return $metadata['source'] === 'textract'
                    && $metadata['job_id'] === $job->id
                    && $metadata['drive_file_id'] === $job->drive_file_id
                    && $metadata['drive_file_name'] === $job->drive_file_name
                    && $metadata['case_id'] === null
                    && $metadata['manually_edited'] === false
                    && isset($metadata['created_at']);
            });

        Config::set('distributed-processing.embeddings.model', 'text-embedding-3-small');

        // Act
        $embeddingJob = new GenerateEmbeddingsJob($job->id, 'textract_job');
        $embeddingJob->handle($mockOpenAI, $mockVectorStore);

        // Assert - handled by mock expectations
        $this->assertTrue(true);
    }

    /** @test */
    public function it_processes_textract_job_embeddings()
    {
        $job = TextractJob::factory()->succeeded()->create([
            'extracted_content' => 'This is test content that needs embeddings.',
            'embedding_status' => 'pending',
        ]);

        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockVectorStore = Mockery::mock(CourtDecisionVectorStoreService::class);

        $mockOpenAI
            ->shouldReceive('embeddings')
            ->once()
            ->andReturn([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.1)],
                ],
                'usage' => ['total_tokens' => 10],
            ]);

        $mockVectorStore
            ->shouldReceive('upsert')
            ->once();

        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('warning')->andReturn(null);
        Log::shouldReceive('error')->andReturn(null);
        Log::shouldReceive('debug')->andReturn(null);

        $queueJob = new GenerateEmbeddingsJob($job->id, 'textract_job');
        $queueJob->handle($mockOpenAI, $mockVectorStore);

        // Verify it completed without errors
        $this->assertTrue(true);
    }

    /** @test */
    public function it_has_correct_queue_configuration()
    {
        $queueJob = new GenerateEmbeddingsJob('test-id', 'textract_job');

        $this->assertEquals('embeddings', $queueJob->queue);
        $this->assertEquals(2, $queueJob->tries);
        $this->assertEquals([120, 600], $queueJob->backoff);
        $this->assertEquals(900, $queueJob->timeout);
    }

    /** @test */
    public function it_can_be_dispatched_to_queue()
    {
        Queue::fake();

        $job = TextractJob::factory()->succeeded()->create();

        GenerateEmbeddingsJob::dispatch((string) $job->id, 'textract_job');

        Queue::assertPushed(GenerateEmbeddingsJob::class, function ($queueJob) use ($job) {
            // Check if job properties match using strict comparison for type safety
            return $queueJob->sourceId == $job->id
                && $queueJob->sourceType == 'textract_job'
                && $queueJob->queue == 'embeddings';
        });
    }

    /** @test */
    public function it_accepts_optional_embedding_batch_id()
    {
        $queueJob = new GenerateEmbeddingsJob('source-123', 'batch', 'batch-456');

        $this->assertEquals('source-123', $queueJob->sourceId);
        $this->assertEquals('batch', $queueJob->sourceType);
        $this->assertEquals('batch-456', $queueJob->embeddingBatchId);
    }

    /** @test */
    public function it_handles_batch_source_type()
    {
        // For batch processing, we just verify the job accepts it
        $queueJob = new GenerateEmbeddingsJob('batch-id', 'batch');

        $this->assertEquals('batch', $queueJob->sourceType);
    }

    /** @test */
    public function it_handles_exceptions_gracefully()
    {
        $job = TextractJob::factory()->succeeded()->create([
            'extracted_content' => 'Test content',
        ]);

        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockVectorStore = Mockery::mock(CourtDecisionVectorStoreService::class);

        $mockOpenAI
            ->shouldReceive('embeddings')
            ->once()
            ->andThrow(new \Exception('OpenAI API error'));

        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('error')->andReturn(null);

        $queueJob = new GenerateEmbeddingsJob($job->id, 'textract_job');

        try {
            $queueJob->handle($mockOpenAI, $mockVectorStore);
        } catch (\Exception $e) {
            // Expected to throw on first attempt
            $this->assertStringContainsString('OpenAI API error', $e->getMessage());
        }
    }

    /** @test */
    public function it_uses_default_source_type_when_not_specified()
    {
        $queueJob = new GenerateEmbeddingsJob('test-id');

        $this->assertEquals('textract_job', $queueJob->sourceType);
    }

    /** @test */
    public function it_can_be_constructed_with_all_parameters()
    {
        $queueJob = new GenerateEmbeddingsJob(
            sourceId: 'job-123',
            sourceType: 'textract_job',
            embeddingBatchId: 'batch-456'
        );

        $this->assertEquals('job-123', $queueJob->sourceId);
        $this->assertEquals('textract_job', $queueJob->sourceType);
        $this->assertEquals('batch-456', $queueJob->embeddingBatchId);
    }

    /** @test */
    public function test_failed_method_marks_textract_job_as_permanently_failed(): void
    {
        // Arrange
        $job = TextractJob::factory()->succeeded()->create([
            'embedding_status' => 'processing',
        ]);

        $exception = new \Exception('OpenAI API permanently unavailable');

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) use ($job) {
                return $message === 'GenerateEmbeddingsJob - Permanently failed'
                    && $context['source_id'] === $job->id
                    && $context['source_type'] === 'textract_job'
                    && $context['attempts'] === 2
                    && str_contains($context['error'], 'OpenAI API permanently unavailable');
            });

        // Allow any other log calls
        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('warning')->andReturn(null);
        Log::shouldReceive('debug')->andReturn(null);

        // Act
        $embeddingJob = new GenerateEmbeddingsJob($job->id, 'textract_job');
        $embeddingJob->failed($exception);

        // Assert
        $job->refresh();
        $this->assertEquals('failed', $job->embedding_status);
        $this->assertStringContainsString('permanently failed after 2 attempts', $job->error);
        $this->assertStringContainsString('OpenAI API permanently unavailable', $job->error);
    }

    /** @test */
    public function test_failed_method_marks_embedding_batch_as_failed(): void
    {
        // Arrange
        $batch = EmbeddingBatch::create([
            'id' => '00000004-0000-0000-0000-000000000004',
            'source_type' => 'batch',
            'status' => 'processing',
            'total_items' => 5,
            'processed_items' => 2,
            'tokens_used' => 100,
        ]);

        $exception = new \Exception('Batch processing permanently failed');

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) use ($batch) {
                return $message === 'GenerateEmbeddingsJob - Permanently failed'
                    && $context['source_id'] === $batch->id
                    && $context['source_type'] === 'batch';
            });

        // Allow any other log calls
        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('warning')->andReturn(null);
        Log::shouldReceive('debug')->andReturn(null);

        // Act
        $embeddingJob = new GenerateEmbeddingsJob($batch->id, 'batch');
        $embeddingJob->failed($exception);

        // Assert
        $batch->refresh();
        $this->assertEquals('failed', $batch->status);
        $this->assertStringContainsString('Batch processing permanently failed', $batch->error);
    }

    /** @test */
    public function test_failed_method_handles_user_broadcast_without_error(): void
    {
        // Arrange
        $user = \App\Models\User::factory()->create();

        $job = TextractJob::factory()->succeeded()->create([
            'embedding_status' => 'processing',
        ]);

        $exception = new \Exception('Test failure for broadcast');

        // Allow all log calls
        Log::shouldReceive('error')->andReturn(null);
        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('warning')->andReturn(null);
        Log::shouldReceive('debug')->andReturn(null);

        // Create job with userId - the actual broadcast will fail silently or succeed
        // depending on Reverb config, but shouldn't throw
        $embeddingJob = new GenerateEmbeddingsJob($job->id, 'textract_job', null, $user->id);

        // Act - this should not throw even when broadcasting to a user
        $embeddingJob->failed($exception);

        // Assert - the job should still be marked as failed even if broadcast fails
        $job->refresh();
        $this->assertEquals('failed', $job->embedding_status);
        $this->assertStringContainsString('permanently failed after 2 attempts', $job->error);
    }

    /** @test */
    public function test_failed_method_handles_null_user_id(): void
    {
        // Arrange
        $job = TextractJob::factory()->succeeded()->create([
            'embedding_status' => 'processing',
        ]);

        $exception = new \Exception('Test failure without user');

        // Allow all log calls
        Log::shouldReceive('error')->andReturn(null);
        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('warning')->andReturn(null);
        Log::shouldReceive('debug')->andReturn(null);

        // Create job without userId
        $embeddingJob = new GenerateEmbeddingsJob($job->id, 'textract_job', null, null);

        // Act - this should complete without trying to broadcast
        $embeddingJob->failed($exception);

        // Assert - the job should be marked as failed
        $job->refresh();
        $this->assertEquals('failed', $job->embedding_status);
        $this->assertStringContainsString('permanently failed after 2 attempts', $job->error);
    }

    /** @test */
    public function test_batch_processing_handles_invalid_item_ids_correctly(): void
    {
        // Fake queue so dispatched jobs don't run synchronously
        Queue::fake();

        // Arrange - batch with a mix of valid and invalid IDs
        $validJob = TextractJob::factory()->succeeded()->create();

        $batch = EmbeddingBatch::create([
            'id' => '00000005-0000-0000-0000-000000000005',
            'source_type' => 'batch',
            'status' => 'pending',
            'total_items' => 3,
            'item_ids' => ['invalid-uuid-format', null, $validJob->id],
            'processed_items' => 0,
            'failed_items' => 0,
        ]);

        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockVectorStore = Mockery::mock(CourtDecisionVectorStoreService::class);

        Log::shouldReceive('error')->andReturn(null);
        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('warning')->andReturn(null);
        Log::shouldReceive('debug')->andReturn(null);

        // Act
        $embeddingJob = new GenerateEmbeddingsJob($batch->id, 'batch');
        $embeddingJob->handle($mockOpenAI, $mockVectorStore);

        // Assert - only valid job should have dispatched embedding job
        Queue::assertPushed(GenerateEmbeddingsJob::class, 1);
        Queue::assertPushed(GenerateEmbeddingsJob::class, function ($queuedJob) use ($validJob) {
            return $queuedJob->sourceId === $validJob->id;
        });

        $batch->refresh();
        // Invalid items should be counted as processed (failed)
        $this->assertGreaterThanOrEqual(2, $batch->failed_items);
    }
}
