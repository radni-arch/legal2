<?php

namespace Tests\Feature\Console;

use App\Jobs\RegenerateTextractEmbeddings;
use App\Models\TextractDocument;
use App\Models\TextractJob;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class BackfillChunkedEmbeddingsCommandTest extends TestCase
{
    use UsesTestDatabase;

    /** @test */
    public function dry_run_mode_lists_jobs_without_dispatching(): void
    {
        Queue::fake();

        // Create a job that needs backfill (synced, no documents, completed)
        $job = TextractJob::factory()
            ->completed()
            ->embeddingsSynced()
            ->create();

        $this->artisan('textract:backfill-embeddings', ['--dry-run' => true])
            ->expectsOutput('Found 1 jobs needing chunked embedding backfill')
            ->expectsOutput('Dry run mode - no jobs will be dispatched')
            ->expectsOutputToContain("Would process: TextractJob #{$job->id}")
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    /** @test */
    public function limit_option_restricts_number_of_jobs(): void
    {
        Queue::fake();

        // Create 5 jobs that need backfill
        TextractJob::factory()
            ->completed()
            ->embeddingsSynced()
            ->count(5)
            ->create();

        $this->artisan('textract:backfill-embeddings', ['--limit' => 2])
            ->expectsOutput('Found 2 jobs needing chunked embedding backfill')
            ->assertExitCode(0);

        Queue::assertPushed(RegenerateTextractEmbeddings::class, 2);
    }

    /** @test */
    public function command_dispatches_jobs_with_delay(): void
    {
        Queue::fake();

        // Create 3 jobs that need backfill
        $jobs = TextractJob::factory()
            ->completed()
            ->embeddingsSynced()
            ->count(3)
            ->create();

        $this->artisan('textract:backfill-embeddings', ['--delay' => 60])
            ->expectsOutput('Found 3 jobs needing chunked embedding backfill')
            ->expectsOutput('Dispatched 3 embedding regeneration jobs')
            ->assertExitCode(0);

        Queue::assertPushed(RegenerateTextractEmbeddings::class, 3);

        // Verify the jobs were dispatched with delays
        foreach ($jobs as $index => $job) {
            Queue::assertPushed(RegenerateTextractEmbeddings::class, function ($dispatchedJob) use ($job) {
                return $dispatchedJob->textractJobId === $job->id;
            });
        }
    }

    /** @test */
    public function command_filters_by_synced_status(): void
    {
        Queue::fake();

        // Create a job with synced status (should be included)
        $syncedJob = TextractJob::factory()
            ->completed()
            ->embeddingsSynced()
            ->create();

        // Create a job with pending status (should be excluded)
        $pendingJob = TextractJob::factory()
            ->completed()
            ->create(['embedding_status' => 'pending']);

        // Create a job with failed status (should be excluded)
        $failedJob = TextractJob::factory()
            ->completed()
            ->create(['embedding_status' => 'failed']);

        $this->artisan('textract:backfill-embeddings')
            ->expectsOutput('Found 1 jobs needing chunked embedding backfill')
            ->assertExitCode(0);

        Queue::assertPushed(RegenerateTextractEmbeddings::class, 1);
        Queue::assertPushed(RegenerateTextractEmbeddings::class, function ($job) use ($syncedJob) {
            return $job->textractJobId === $syncedJob->id;
        });
    }

    /** @test */
    public function command_excludes_jobs_with_documents(): void
    {
        Queue::fake();

        // Create a job WITH documents (should be excluded - already has chunked embeddings)
        $jobWithDocuments = TextractJob::factory()
            ->completed()
            ->embeddingsSynced()
            ->create();

        TextractDocument::factory()
            ->create(['textract_job_id' => $jobWithDocuments->id]);

        // Create a job WITHOUT documents (should be included)
        $jobWithoutDocuments = TextractJob::factory()
            ->completed()
            ->embeddingsSynced()
            ->create();

        $this->artisan('textract:backfill-embeddings')
            ->expectsOutput('Found 1 jobs needing chunked embedding backfill')
            ->assertExitCode(0);

        Queue::assertPushed(RegenerateTextractEmbeddings::class, 1);
        Queue::assertPushed(RegenerateTextractEmbeddings::class, function ($job) use ($jobWithoutDocuments) {
            return $job->textractJobId === $jobWithoutDocuments->id;
        });
    }

    /** @test */
    public function command_filters_by_completed_or_succeeded_status(): void
    {
        Queue::fake();

        // Create a job with 'completed' status (should be included)
        $completedJob = TextractJob::factory()
            ->completed()
            ->embeddingsSynced()
            ->create();

        // Create a job with 'succeeded' status (should be included)
        $succeededJob = TextractJob::factory()
            ->succeeded()
            ->embeddingsSynced()
            ->create();

        // Create a job with 'queued' status (should be excluded)
        $queuedJob = TextractJob::factory()
            ->queued()
            ->embeddingsSynced()
            ->create();

        // Create a job with 'failed' processing status (should be excluded)
        $failedJob = TextractJob::factory()
            ->failed()
            ->embeddingsSynced()
            ->create();

        $this->artisan('textract:backfill-embeddings')
            ->expectsOutput('Found 2 jobs needing chunked embedding backfill')
            ->assertExitCode(0);

        Queue::assertPushed(RegenerateTextractEmbeddings::class, 2);
    }

    /** @test */
    public function command_shows_zero_jobs_message_when_none_found(): void
    {
        Queue::fake();

        $this->artisan('textract:backfill-embeddings')
            ->expectsOutput('Found 0 jobs needing chunked embedding backfill')
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    /** @test */
    public function dry_run_with_limit_shows_limited_jobs(): void
    {
        Queue::fake();

        // Create 5 jobs
        $jobs = TextractJob::factory()
            ->completed()
            ->embeddingsSynced()
            ->count(5)
            ->create();

        $this->artisan('textract:backfill-embeddings', ['--dry-run' => true, '--limit' => 2])
            ->expectsOutput('Found 2 jobs needing chunked embedding backfill')
            ->expectsOutput('Dry run mode - no jobs will be dispatched')
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    }
}
