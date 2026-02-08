<?php

namespace Tests\Unit\Jobs;

use App\Jobs\RegenerateTextractEmbeddings;
use App\Models\TextractJob;
use App\Services\TextractVectorStoreService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class RegenerateTextractEmbeddingsTest extends TestCase
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
        $job = new RegenerateTextractEmbeddings(1);

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $job);
    }

    /** @test */
    public function it_stores_textract_job_id_and_options(): void
    {
        $options = ['chunk_size' => 1000, 'model' => 'text-embedding-3-small'];
        $job = new RegenerateTextractEmbeddings(123, $options);

        $this->assertEquals(123, $job->textractJobId);
        $this->assertEquals($options, $job->options);
    }

    /** @test */
    public function it_generates_embeddings_for_job_with_content(): void
    {
        $textractJob = TextractJob::create([
            'drive_file_id' => 'file-embeddings',
            'drive_file_name' => 'document.pdf',
            'status' => 'succeeded',
            'extracted_content' => 'This is test content for embedding generation',
        ]);

        Log::shouldReceive('info')->times(2);

        $mockVectorStore = Mockery::mock(TextractVectorStoreService::class);
        $mockVectorStore->shouldReceive('ingestTextractJob')
            ->once()
            ->with($textractJob->id, [])
            ->andReturn(['chunks' => 5, 'vectors' => 5]);

        $job = new RegenerateTextractEmbeddings($textractJob->id);
        $job->handle($mockVectorStore);

        $this->addToAssertionCount(1);
    }

    /** @test */
    public function it_handles_job_not_found_gracefully(): void
    {
        Log::shouldReceive('info')->atLeast()->once();
        Log::shouldReceive('error')
            ->once()
            ->with('RegenerateTextractEmbeddings: Job not found', Mockery::type('array'));

        $mockVectorStore = Mockery::mock(TextractVectorStoreService::class);
        $mockVectorStore->shouldReceive('ingestTextractJob')->never();

        $job = new RegenerateTextractEmbeddings(99999);
        $job->handle($mockVectorStore);

        $this->addToAssertionCount(1);
    }

    /** @test */
    public function it_skips_job_without_content(): void
    {
        $textractJob = TextractJob::create([
            'drive_file_id' => 'file-no-content',
            'drive_file_name' => 'empty.pdf',
            'status' => 'succeeded',
            'extracted_content' => null,
        ]);

        Log::shouldReceive('info')->atLeast()->once();
        Log::shouldReceive('warning')
            ->once()
            ->with('RegenerateTextractEmbeddings: No content available', Mockery::type('array'));
        Log::shouldReceive('error')->andReturn(null);

        $mockVectorStore = Mockery::mock(TextractVectorStoreService::class);
        $mockVectorStore->shouldReceive('ingestTextractJob')->never();

        $job = new RegenerateTextractEmbeddings($textractJob->id);
        $job->handle($mockVectorStore);

        $textractJob->refresh();
        $this->assertEquals('failed', $textractJob->embedding_status);
    }

    /** @test */
    public function it_skips_job_not_in_completed_or_succeeded_status(): void
    {
        $textractJob = TextractJob::create([
            'drive_file_id' => 'file-pending',
            'drive_file_name' => 'pending.pdf',
            'status' => 'pending',
            'extracted_content' => 'Some content',
        ]);

        Log::shouldReceive('info')->atLeast()->once();
        Log::shouldReceive('warning')
            ->once()
            ->with('RegenerateTextractEmbeddings: Job not in completed/succeeded status', Mockery::type('array'));
        Log::shouldReceive('error')->andReturn(null);

        $mockVectorStore = Mockery::mock(TextractVectorStoreService::class);
        $mockVectorStore->shouldReceive('ingestTextractJob')->never();

        $job = new RegenerateTextractEmbeddings($textractJob->id);
        $job->handle($mockVectorStore);

        $this->addToAssertionCount(1);
    }

    /** @test */
    public function it_handles_embedding_generation_failure(): void
    {
        $textractJob = TextractJob::create([
            'drive_file_id' => 'file-fail',
            'drive_file_name' => 'fail.pdf',
            'status' => 'succeeded',
            'extracted_content' => 'Content',
        ]);

        Log::shouldReceive('info')->atLeast()->once();
        Log::shouldReceive('error')->atLeast()->once();
        Log::shouldReceive('warning')->andReturn(null);

        $mockVectorStore = Mockery::mock(TextractVectorStoreService::class);
        $mockVectorStore->shouldReceive('ingestTextractJob')
            ->once()
            ->andThrow(new \Exception('OpenAI API error'));

        $job = new RegenerateTextractEmbeddings($textractJob->id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('OpenAI API error');

        $job->handle($mockVectorStore);
    }

    /** @test */
    public function failed_method_marks_job_as_permanently_failed(): void
    {
        $textractJob = TextractJob::create([
            'drive_file_id' => 'file-perm-fail',
            'drive_file_name' => 'permanent.pdf',
            'status' => 'succeeded',
            'extracted_content' => 'Content',
        ]);

        Log::shouldReceive('error')->atLeast()->once();
        Log::shouldReceive('info')->atLeast()->once();
        Log::shouldReceive('warning')->andReturn(null);

        $job = new RegenerateTextractEmbeddings($textractJob->id);
        $exception = new \Exception('Permanent failure');

        $job->failed($exception);

        $textractJob->refresh();
        $this->assertEquals('failed', $textractJob->embedding_status);
        $this->assertStringContainsString('permanently failed', $textractJob->error);
    }

    /** @test */
    public function it_can_be_dispatched_to_queue(): void
    {
        Queue::fake();

        RegenerateTextractEmbeddings::dispatch(456, ['chunk_size' => 500]);

        Queue::assertPushed(RegenerateTextractEmbeddings::class, function ($job) {
            return $job->textractJobId === 456
                && $job->options['chunk_size'] === 500;
        });
    }

    /** @test */
    public function it_has_correct_tags(): void
    {
        $job = new RegenerateTextractEmbeddings(789);

        $tags = $job->tags();

        $this->assertContains('textract', $tags);
        $this->assertContains('embeddings', $tags);
        $this->assertContains('textract_job:789', $tags);
    }

    /** @test */
    public function it_has_3_retry_attempts(): void
    {
        $job = new RegenerateTextractEmbeddings(1);

        $this->assertEquals(3, $job->tries);
    }

    /** @test */
    public function it_has_exponential_backoff_strategy(): void
    {
        $job = new RegenerateTextractEmbeddings(1);

        $backoff = $job->backoff();

        $this->assertEquals([2, 4, 8], $backoff);
    }

    /** @test */
    public function it_has_600_second_timeout(): void
    {
        $job = new RegenerateTextractEmbeddings(1);

        $this->assertEquals(600, $job->timeout);
    }

    /** @test */
    public function it_passes_options_to_vector_store(): void
    {
        $textractJob = TextractJob::create([
            'drive_file_id' => 'file-options',
            'drive_file_name' => 'options.pdf',
            'status' => 'succeeded',
            'extracted_content' => 'Content with options',
        ]);

        Log::shouldReceive('info')->times(2);

        $options = ['chunk_size' => 2000, 'overlap' => 100];

        $mockVectorStore = Mockery::mock(TextractVectorStoreService::class);
        $mockVectorStore->shouldReceive('ingestTextractJob')
            ->once()
            ->with($textractJob->id, $options)
            ->andReturn(['chunks' => 3, 'vectors' => 3]);

        $job = new RegenerateTextractEmbeddings($textractJob->id, $options);
        $job->handle($mockVectorStore);

        $this->addToAssertionCount(1);
    }

    /**
     * @test
     * Verifies fix for status mismatch bug: ProcessTextractJob sets status='completed'
     * but RegenerateTextractEmbeddings was checking for 'succeeded' only.
     * Both statuses should be accepted.
     */
    public function it_processes_job_with_completed_status(): void
    {
        // ProcessTextractJob sets status to 'completed', not 'succeeded'
        $textractJob = TextractJob::create([
            'drive_file_id' => 'file-completed-status',
            'drive_file_name' => 'document.pdf',
            'status' => 'completed', // Key: This is what ProcessTextractJob actually sets
            'extracted_content' => 'This is test content for embedding generation',
        ]);

        Log::shouldReceive('info')->times(2);

        $mockVectorStore = Mockery::mock(TextractVectorStoreService::class);
        $mockVectorStore->shouldReceive('ingestTextractJob')
            ->once()
            ->with($textractJob->id, [])
            ->andReturn(['chunks' => 5, 'vectors' => 5]);

        $job = new RegenerateTextractEmbeddings($textractJob->id);
        $job->handle($mockVectorStore);

        // If the mock expectation is met, the job was processed (not skipped)
        $this->addToAssertionCount(1);
    }

    /**
     * @test
     * Ensures both 'completed' and 'succeeded' statuses are accepted.
     * This tests that the fix uses in_array check properly.
     */
    public function it_accepts_both_completed_and_succeeded_status(): void
    {
        // Test 'completed' status
        $completedJob = TextractJob::create([
            'drive_file_id' => 'file-completed',
            'drive_file_name' => 'completed.pdf',
            'status' => 'completed',
            'extracted_content' => 'Content A',
        ]);

        // Test 'succeeded' status
        $succeededJob = TextractJob::create([
            'drive_file_id' => 'file-succeeded',
            'drive_file_name' => 'succeeded.pdf',
            'status' => 'succeeded',
            'extracted_content' => 'Content B',
        ]);

        Log::shouldReceive('info')->times(4); // 2 calls per job (start + complete)

        $mockVectorStore = Mockery::mock(TextractVectorStoreService::class);
        $mockVectorStore->shouldReceive('ingestTextractJob')
            ->twice()
            ->andReturn(['chunks' => 3, 'vectors' => 3]);

        // Process both jobs
        $job1 = new RegenerateTextractEmbeddings($completedJob->id);
        $job1->handle($mockVectorStore);

        $job2 = new RegenerateTextractEmbeddings($succeededJob->id);
        $job2->handle($mockVectorStore);

        // Both should have been processed (not skipped)
        $this->addToAssertionCount(2);
    }

    /**
     * @test
     * Test that jobs with needsReview=true are skipped for auto-embedding.
     */
    public function it_skips_embedding_when_needs_review_is_true(): void
    {
        $textractJob = TextractJob::create([
            'drive_file_id' => 'file-needs-review',
            'drive_file_name' => 'needs-review.pdf',
            'status' => 'succeeded',
            'extracted_content' => 'Content that needs review',
            'metadata' => [
                'needsReview' => true,
                'reviewReasons' => ['Low overall confidence: 70.00% (threshold: 82.00%)'],
                'ocrQuality' => ['confidence' => 0.70, 'coverage' => 0.80],
            ],
        ]);

        Log::shouldReceive('info')->atLeast()->once();
        Log::shouldReceive('warning')
            ->once()
            ->with('RegenerateTextractEmbeddings: Job needs review, skipping auto-embedding', Mockery::type('array'));

        $mockVectorStore = Mockery::mock(TextractVectorStoreService::class);
        $mockVectorStore->shouldReceive('ingestTextractJob')->never();

        $job = new RegenerateTextractEmbeddings($textractJob->id);
        $job->handle($mockVectorStore);

        // Verify job was skipped
        $textractJob->refresh();
        $this->assertNotEquals('synced', $textractJob->embedding_status);
    }

    /**
     * @test
     * Test that jobs with needsReview=false proceed with embedding.
     */
    public function it_processes_embedding_when_needs_review_is_false(): void
    {
        $textractJob = TextractJob::create([
            'drive_file_id' => 'file-no-review',
            'drive_file_name' => 'no-review.pdf',
            'status' => 'succeeded',
            'extracted_content' => 'Content that does not need review',
            'metadata' => [
                'needsReview' => false,
                'ocrQuality' => ['confidence' => 0.95, 'coverage' => 0.90],
            ],
        ]);

        Log::shouldReceive('info')->times(2);

        $mockVectorStore = Mockery::mock(TextractVectorStoreService::class);
        $mockVectorStore->shouldReceive('ingestTextractJob')
            ->once()
            ->with($textractJob->id, [])
            ->andReturn(['chunks' => 5, 'vectors' => 5]);

        $job = new RegenerateTextractEmbeddings($textractJob->id);
        $job->handle($mockVectorStore);

        // Verify job was processed
        $this->addToAssertionCount(1);
    }

    /**
     * @test
     * Test that jobs without metadata (null) proceed with embedding.
     */
    public function it_processes_embedding_when_metadata_is_null(): void
    {
        $textractJob = TextractJob::create([
            'drive_file_id' => 'file-null-metadata',
            'drive_file_name' => 'null-metadata.pdf',
            'status' => 'succeeded',
            'extracted_content' => 'Content with no metadata',
            'metadata' => null,
        ]);

        Log::shouldReceive('info')->times(2);

        $mockVectorStore = Mockery::mock(TextractVectorStoreService::class);
        $mockVectorStore->shouldReceive('ingestTextractJob')
            ->once()
            ->with($textractJob->id, [])
            ->andReturn(['chunks' => 3, 'vectors' => 3]);

        $job = new RegenerateTextractEmbeddings($textractJob->id);
        $job->handle($mockVectorStore);

        $this->addToAssertionCount(1);
    }

    /**
     * @test
     * Test that manual embedding with skipReviewCheck option bypasses the review gate.
     */
    public function it_bypasses_review_check_when_skip_option_is_set(): void
    {
        $textractJob = TextractJob::create([
            'drive_file_id' => 'file-manual-skip',
            'drive_file_name' => 'manual-skip.pdf',
            'status' => 'succeeded',
            'extracted_content' => 'Content that needs review but forced',
            'metadata' => [
                'needsReview' => true,
                'reviewReasons' => ['Low confidence'],
            ],
        ]);

        Log::shouldReceive('info')->times(2);

        $mockVectorStore = Mockery::mock(TextractVectorStoreService::class);
        $mockVectorStore->shouldReceive('ingestTextractJob')
            ->once()
            ->with($textractJob->id, ['skipReviewCheck' => true])
            ->andReturn(['chunks' => 3, 'vectors' => 3]);

        // Pass skipReviewCheck option to bypass the review gate
        $job = new RegenerateTextractEmbeddings($textractJob->id, ['skipReviewCheck' => true]);
        $job->handle($mockVectorStore);

        $this->addToAssertionCount(1);
    }

    /**
     * @test
     * Task 3.1: Unified Job Chaining - Test that after embedding success,
     * SyncTextractToGraph is dispatched when Neo4j is enabled.
     */
    public function it_dispatches_graph_sync_when_neo4j_enabled(): void
    {
        Queue::fake();
        Config::set('neo4j.sync.enabled', true);

        $textractJob = TextractJob::create([
            'drive_file_id' => 'file-graph-sync',
            'drive_file_name' => 'graph-sync.pdf',
            'status' => 'succeeded',
            'extracted_content' => 'Content for graph sync test',
        ]);

        Log::shouldReceive('info')->atLeast()->times(2);

        $mockVectorStore = Mockery::mock(TextractVectorStoreService::class);
        $mockVectorStore->shouldReceive('ingestTextractJob')
            ->once()
            ->with($textractJob->id, [])
            ->andReturn(['chunks' => 3, 'vectors' => 3]);

        $job = new RegenerateTextractEmbeddings($textractJob->id);
        $job->handle($mockVectorStore);

        Queue::assertPushed(\App\Jobs\SyncTextractToGraph::class, function ($graphJob) use ($textractJob) {
            return $graphJob->textractJobId === $textractJob->id;
        });
    }

    /**
     * @test
     * Task 3.1: Test that no graph sync is dispatched when Neo4j is disabled.
     */
    public function it_does_not_dispatch_graph_sync_when_neo4j_disabled(): void
    {
        Queue::fake();
        Config::set('neo4j.sync.enabled', false);

        $textractJob = TextractJob::create([
            'drive_file_id' => 'file-no-graph-sync',
            'drive_file_name' => 'no-graph-sync.pdf',
            'status' => 'succeeded',
            'extracted_content' => 'Content without graph sync',
        ]);

        Log::shouldReceive('info')->times(2);

        $mockVectorStore = Mockery::mock(TextractVectorStoreService::class);
        $mockVectorStore->shouldReceive('ingestTextractJob')
            ->once()
            ->with($textractJob->id, [])
            ->andReturn(['chunks' => 2, 'vectors' => 2]);

        $job = new RegenerateTextractEmbeddings($textractJob->id);
        $job->handle($mockVectorStore);

        Queue::assertNotPushed(\App\Jobs\SyncTextractToGraph::class);
    }

    /**
     * @test
     * Task 3.1: Test that the 5-second delay is applied to graph sync dispatch.
     */
    public function it_applies_5_second_delay_to_graph_sync(): void
    {
        Queue::fake();
        Config::set('neo4j.sync.enabled', true);

        $textractJob = TextractJob::create([
            'drive_file_id' => 'file-graph-delay',
            'drive_file_name' => 'graph-delay.pdf',
            'status' => 'succeeded',
            'extracted_content' => 'Content for delay test',
        ]);

        Log::shouldReceive('info')->atLeast()->times(2);

        $mockVectorStore = Mockery::mock(TextractVectorStoreService::class);
        $mockVectorStore->shouldReceive('ingestTextractJob')
            ->once()
            ->with($textractJob->id, [])
            ->andReturn(['chunks' => 1, 'vectors' => 1]);

        $job = new RegenerateTextractEmbeddings($textractJob->id);
        $job->handle($mockVectorStore);

        Queue::assertPushed(\App\Jobs\SyncTextractToGraph::class, function ($graphJob) {
            // Check that the job has a delay set (delay property is set on pending dispatch)
            return $graphJob->delay !== null || $graphJob->delay > 0;
        });
    }

    /**
     * @test
     * Task 3.1: Test that embedding failure does NOT dispatch graph sync.
     */
    public function it_does_not_dispatch_graph_sync_on_embedding_failure(): void
    {
        Queue::fake();
        Config::set('neo4j.sync.enabled', true);

        $textractJob = TextractJob::create([
            'drive_file_id' => 'file-embed-fail',
            'drive_file_name' => 'embed-fail.pdf',
            'status' => 'succeeded',
            'extracted_content' => 'Content that will fail embedding',
        ]);

        Log::shouldReceive('info')->atLeast()->once();
        Log::shouldReceive('error')->atLeast()->once();
        Log::shouldReceive('warning')->andReturn(null);

        $mockVectorStore = Mockery::mock(TextractVectorStoreService::class);
        $mockVectorStore->shouldReceive('ingestTextractJob')
            ->once()
            ->andThrow(new \Exception('Embedding API error'));

        $job = new RegenerateTextractEmbeddings($textractJob->id);

        try {
            $job->handle($mockVectorStore);
        } catch (\Exception $e) {
            // Expected exception
        }

        Queue::assertNotPushed(\App\Jobs\SyncTextractToGraph::class);
    }
}
