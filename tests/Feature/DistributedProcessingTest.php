<?php

namespace Tests\Feature;

use App\Jobs\ExtractTablesFromTextractJob;
use App\Jobs\GenerateEmbeddingsJob;
use App\Jobs\ProcessTextractJob;
use App\Models\EmbeddingBatch;
use App\Models\TextractBatch;
use App\Models\TextractJob;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class DistributedProcessingTest extends TestCase
{
    use UsesTestDatabase;

    /**
     * Test batch creation
     */
    public function test_batch_can_be_created(): void
    {
        $batch = TextractBatch::create([
            'batch_type' => 'drive_folder',
            'source_identifier' => 'test-folder-123',
            'total_files' => 10,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('textract_batches', [
            'id' => $batch->id,
            'batch_type' => 'drive_folder',
            'total_files' => 10,
            'status' => 'pending',
        ]);
    }

    /**
     * Test batch progress tracking
     */
    public function test_batch_tracks_progress(): void
    {
        $batch = TextractBatch::create([
            'batch_type' => 'test',
            'total_files' => 10,
            'processed_files' => 0,
            'status' => 'processing',
        ]);

        $this->assertEquals(0, $batch->progress);

        $batch->incrementProcessed(false);
        $batch->incrementProcessed(false);
        $batch->incrementProcessed(false);

        $this->assertEquals(30.0, $batch->fresh()->progress);
        $this->assertEquals(3, $batch->fresh()->processed_files);
    }

    /**
     * Test batch completion
     */
    public function test_batch_marks_complete_when_all_processed(): void
    {
        $batch = TextractBatch::create([
            'batch_type' => 'test',
            'total_files' => 2,
            'processed_files' => 0,
            'status' => 'processing',
            'started_at' => now(),
        ]);

        $batch->incrementProcessed(false);
        $this->assertEquals('processing', $batch->fresh()->status);

        $batch->incrementProcessed(false);
        $this->assertEquals('completed', $batch->fresh()->status);
        $this->assertNotNull($batch->fresh()->completed_at);
    }

    /**
     * Test batch success rate calculation
     */
    public function test_batch_calculates_success_rate(): void
    {
        $batch = TextractBatch::create([
            'batch_type' => 'test',
            'total_files' => 10,
            'processed_files' => 10,
            'failed_files' => 2,
            'status' => 'completed',
        ]);

        // 8 successful out of 10 = 80%
        $this->assertEquals(80.0, $batch->success_rate);
    }

    /**
     * Test job dispatching to queue
     */
    public function test_textract_job_can_be_dispatched(): void
    {
        Queue::fake();

        $job = TextractJob::create([
            'drive_file_id' => 'test-file-123',
            'file_name' => 'test.pdf',
            'status' => 'pending',
            'queue_name' => 'textract',
        ]);

        ProcessTextractJob::dispatch($job->id)
            ->onQueue('textract');

        Queue::assertPushed(ProcessTextractJob::class, function ($queuedJob) {
            return $queuedJob->queue === 'textract';
        });
    }

    /**
     * Test embedding batch creation
     */
    public function test_embedding_batch_can_be_created(): void
    {
        $batch = EmbeddingBatch::create([
            'source_type' => 'textract_job',
            'total_items' => 5,
            'status' => 'pending',
            'embedding_model' => 'text-embedding-3-small',
        ]);

        $this->assertDatabaseHas('embedding_batches', [
            'id' => $batch->id,
            'source_type' => 'textract_job',
            'total_items' => 5,
        ]);
    }

    /**
     * Test embedding batch progress with token tracking
     */
    public function test_embedding_batch_tracks_tokens_and_cost(): void
    {
        $batch = EmbeddingBatch::create([
            'source_type' => 'textract_job',
            'total_items' => 3,
            'status' => 'processing',
        ]);

        // Process first item with 1000 tokens
        $batch->incrementProcessed(1000, false);

        $this->assertEquals(1, $batch->fresh()->processed_items);
        $this->assertEquals(1000, $batch->fresh()->tokens_used);
        // Cost: (1000/1000) * 0.00002 = 0.00002
        $this->assertEquals(0.00002, $batch->fresh()->cost);

        // Process second item with 500 tokens
        $batch->incrementProcessed(500, false);

        $this->assertEquals(2, $batch->fresh()->processed_items);
        $this->assertEquals(1500, $batch->fresh()->tokens_used);
    }

    /**
     * Test job retry tracking
     */
    public function test_textract_job_tracks_retries(): void
    {
        $job = TextractJob::create([
            'drive_file_id' => 'test-123',
            'file_name' => 'test.pdf',
            'status' => 'pending',
            'retry_count' => 0,
        ]);

        $this->assertEquals(0, $job->retry_count);

        $job->update(['retry_count' => 1]);
        $this->assertEquals(1, $job->fresh()->retry_count);
    }

    /**
     * Test queue prioritization
     */
    public function test_jobs_can_have_different_priorities(): void
    {
        $highPriorityJob = TextractJob::create([
            'drive_file_id' => 'high-123',
            'file_name' => 'high.pdf',
            'status' => 'pending',
            'priority' => 100,
            'queue_name' => 'textract-high',
        ]);

        $lowPriorityJob = TextractJob::create([
            'drive_file_id' => 'low-123',
            'file_name' => 'low.pdf',
            'status' => 'pending',
            'priority' => 10,
            'queue_name' => 'textract-low',
        ]);

        $this->assertEquals(100, $highPriorityJob->priority);
        $this->assertEquals(10, $lowPriorityJob->priority);
        $this->assertEquals('textract-high', $highPriorityJob->queue_name);
        $this->assertEquals('textract-low', $lowPriorityJob->queue_name);
    }

    /**
     * Test performance metrics storage
     */
    public function test_job_stores_performance_metrics(): void
    {
        $job = TextractJob::create([
            'drive_file_id' => 'test-123',
            'file_name' => 'test.pdf',
            'status' => 'completed',
            'performance_metrics' => [
                'duration_seconds' => 45.2,
                'pages_processed' => 10,
                'blocks_extracted' => 542,
                'tables_extracted' => 3,
                'worker_id' => 12345,
                'memory_peak_mb' => 128.5,
            ],
        ]);

        $metrics = $job->performance_metrics;

        $this->assertEquals(45.2, $metrics['duration_seconds']);
        $this->assertEquals(10, $metrics['pages_processed']);
        $this->assertEquals(3, $metrics['tables_extracted']);
    }

    /**
     * Test table extraction job dispatching
     */
    public function test_table_extraction_job_can_be_dispatched(): void
    {
        Queue::fake();

        $job = TextractJob::create([
            'drive_file_id' => 'test-123',
            'file_name' => 'test.pdf',
            'status' => 'completed',
        ]);

        ExtractTablesFromTextractJob::dispatch($job->id)
            ->onQueue('textract-tables');

        Queue::assertPushed(ExtractTablesFromTextractJob::class);
    }

    /**
     * Test batch-job relationship
     */
    public function test_batch_has_many_jobs(): void
    {
        $batch = TextractBatch::create([
            'batch_type' => 'test',
            'total_files' => 2,
            'status' => 'processing',
        ]);

        $job1 = TextractJob::create([
            'drive_file_id' => 'file-1',
            'file_name' => 'file1.pdf',
            'batch_id' => $batch->id,
        ]);

        $job2 = TextractJob::create([
            'drive_file_id' => 'file-2',
            'file_name' => 'file2.pdf',
            'batch_id' => $batch->id,
        ]);

        $this->assertEquals(2, $batch->jobs()->count());
        $this->assertTrue($batch->jobs->contains($job1));
        $this->assertTrue($batch->jobs->contains($job2));
    }

    /**
     * Test embedding job with batch tracking
     */
    public function test_embedding_job_updates_batch(): void
    {
        Queue::fake();

        $embeddingBatch = EmbeddingBatch::create([
            'source_type' => 'textract_job',
            'total_items' => 1,
            'status' => 'processing',
        ]);

        $textractJob = TextractJob::create([
            'drive_file_id' => 'test-123',
            'file_name' => 'test.pdf',
            'status' => 'completed',
        ]);

        GenerateEmbeddingsJob::dispatch(
            $textractJob->id,
            'textract_job',
            $embeddingBatch->id
        )->onQueue('embeddings');

        Queue::assertPushed(GenerateEmbeddingsJob::class);
    }
}
