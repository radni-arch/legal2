<?php

namespace Tests\Unit\Jobs;

use App\Jobs\RegenerateTextractEmbeddings;
use App\Models\TextractJob;
use App\Services\TextractVectorStoreService;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Dedicated tests for the status value mismatch fix in RegenerateTextractEmbeddings.
 *
 * Bug context: ProcessTextractJob sets status to 'completed', but RegenerateTextractEmbeddings
 * originally checked for 'succeeded' only. The fix uses in_array() to accept both values.
 *
 * @see \App\Jobs\RegenerateTextractEmbeddings::handle()
 * @see \App\Jobs\ProcessTextractJob
 */
class RegenerateTextractEmbeddingsStatusTest extends TestCase
{
    use UsesTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Use Log::spy() to permit all log method calls (info, error, debug, warning)
        // without strict expectations. This avoids issues with CircuitBreaker and
        // other services that call Log::debug() during initialization.
        Log::spy();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @test
     * ProcessTextractJob sets status to 'completed'. This MUST NOT cause early-return.
     * Before the fix, this test would fail because the job would skip processing.
     */
    public function completed_status_triggers_embedding_regeneration(): void
    {
        $textractJob = TextractJob::create([
            'drive_file_id' => 'status-test-completed',
            'drive_file_name' => 'completed-doc.pdf',
            'status' => 'completed',
            'extracted_content' => 'Legal document content for embedding with completed status.',
        ]);

        $mockVectorStore = Mockery::mock(TextractVectorStoreService::class);
        $mockVectorStore->shouldReceive('ingestTextractJob')
            ->once()
            ->with($textractJob->id, [])
            ->andReturn(['chunks' => 3, 'vectors' => 3]);

        $job = new RegenerateTextractEmbeddings($textractJob->id);
        $job->handle($mockVectorStore);

        // Verify the success log message was emitted -- proves the job was processed
        Log::shouldHaveReceived('info')
            ->with('RegenerateTextractEmbeddings: Completed successfully', Mockery::type('array'))
            ->once();

        // Mockery also enforces: ingestTextractJob was called exactly once
        $this->assertTrue(true, 'Job with completed status was processed successfully');
    }

    /**
     * @test
     * Legacy/backward-compat: 'succeeded' status must continue to work.
     */
    public function succeeded_status_triggers_embedding_regeneration(): void
    {
        $textractJob = TextractJob::create([
            'drive_file_id' => 'status-test-succeeded',
            'drive_file_name' => 'succeeded-doc.pdf',
            'status' => 'succeeded',
            'extracted_content' => 'Legal document content for embedding with succeeded status.',
        ]);

        $mockVectorStore = Mockery::mock(TextractVectorStoreService::class);
        $mockVectorStore->shouldReceive('ingestTextractJob')
            ->once()
            ->with($textractJob->id, [])
            ->andReturn(['chunks' => 4, 'vectors' => 4]);

        $job = new RegenerateTextractEmbeddings($textractJob->id);
        $job->handle($mockVectorStore);

        Log::shouldHaveReceived('info')
            ->with('RegenerateTextractEmbeddings: Completed successfully', Mockery::type('array'))
            ->once();

        $this->assertTrue(true, 'Job with succeeded status was processed successfully');
    }

    /**
     * @test
     * Jobs with 'failed' status must be skipped -- embedding should not be attempted.
     */
    public function failed_status_is_correctly_skipped(): void
    {
        $textractJob = TextractJob::create([
            'drive_file_id' => 'status-test-failed',
            'drive_file_name' => 'failed-doc.pdf',
            'status' => 'failed',
            'extracted_content' => 'Content that should not be processed because job failed.',
        ]);

        $mockVectorStore = Mockery::mock(TextractVectorStoreService::class);
        $mockVectorStore->shouldReceive('ingestTextractJob')->never();

        $job = new RegenerateTextractEmbeddings($textractJob->id);
        $job->handle($mockVectorStore);

        // Verify the warning was logged indicating status rejection
        Log::shouldHaveReceived('warning')
            ->with('RegenerateTextractEmbeddings: Job not in completed/succeeded status', Mockery::type('array'))
            ->once();

        // Mockery enforces: ingestTextractJob was NEVER called
        $this->assertTrue(true, 'Job with failed status was correctly skipped');
    }

    /**
     * @test
     * Jobs with 'pending' status must be skipped -- they are not yet processed by Textract.
     */
    public function pending_status_is_correctly_skipped(): void
    {
        $textractJob = TextractJob::create([
            'drive_file_id' => 'status-test-pending',
            'drive_file_name' => 'pending-doc.pdf',
            'status' => 'pending',
            'extracted_content' => 'Content that should not be processed because job is still pending.',
        ]);

        $mockVectorStore = Mockery::mock(TextractVectorStoreService::class);
        $mockVectorStore->shouldReceive('ingestTextractJob')->never();

        $job = new RegenerateTextractEmbeddings($textractJob->id);
        $job->handle($mockVectorStore);

        // Verify the warning was logged indicating status rejection
        Log::shouldHaveReceived('warning')
            ->with('RegenerateTextractEmbeddings: Job not in completed/succeeded status', Mockery::type('array'))
            ->once();

        // Mockery enforces: ingestTextractJob was NEVER called
        $this->assertTrue(true, 'Job with pending status was correctly skipped');
    }
}
