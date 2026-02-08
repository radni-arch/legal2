<?php

namespace Tests\Feature\Console;

use App\Jobs\RegenerateTextractEmbeddings;
use App\Models\LegalCase;
use App\Models\TextractJob;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Tests for the textract:batch-regenerate-embeddings command.
 *
 * This command dispatches multiple RegenerateTextractEmbeddings jobs
 * with staggered delays for bulk embedding regeneration.
 */
class BatchRegenerateEmbeddingsTest extends TestCase
{
    use UsesTestDatabase;

    /** @test */
    public function dry_run_shows_jobs_without_dispatching(): void
    {
        Queue::fake();

        // Create completed jobs that should be listed
        $job1 = TextractJob::factory()->completed()->create();
        $job2 = TextractJob::factory()->succeeded()->create();

        $this->artisan('textract:batch-regenerate-embeddings', ['--dry-run' => true])
            ->expectsOutput('Found 2 jobs to process')
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    /** @test */
    public function dispatches_jobs_with_correct_delays(): void
    {
        Queue::fake();

        // Create 3 completed jobs
        $jobs = TextractJob::factory()
            ->completed()
            ->count(3)
            ->create();

        $this->artisan('textract:batch-regenerate-embeddings', ['--delay' => 10])
            ->expectsOutput('Found 3 jobs to process')
            ->expectsOutput('Dispatched 3 jobs with 10s stagger')
            ->assertExitCode(0);

        Queue::assertPushed(RegenerateTextractEmbeddings::class, 3);

        // Verify each job was dispatched
        foreach ($jobs as $job) {
            Queue::assertPushed(RegenerateTextractEmbeddings::class, function ($dispatchedJob) use ($job) {
                return $dispatchedJob->textractJobId === $job->id;
            });
        }
    }

    /** @test */
    public function filters_by_case_id(): void
    {
        Queue::fake();

        // Create two cases
        $case1 = LegalCase::factory()->create();
        $case2 = LegalCase::factory()->create();

        // Create jobs for each case
        $job1 = TextractJob::factory()->completed()->create(['case_id' => $case1->id]);
        $job2 = TextractJob::factory()->completed()->create(['case_id' => $case1->id]);
        $job3 = TextractJob::factory()->completed()->create(['case_id' => $case2->id]);

        $this->artisan('textract:batch-regenerate-embeddings', ['--case' => $case1->id])
            ->expectsOutput('Found 2 jobs to process')
            ->expectsOutput('Dispatched 2 jobs with 5s stagger')
            ->assertExitCode(0);

        Queue::assertPushed(RegenerateTextractEmbeddings::class, 2);

        // Verify only case1 jobs were dispatched
        Queue::assertPushed(RegenerateTextractEmbeddings::class, function ($dispatchedJob) use ($job1) {
            return $dispatchedJob->textractJobId === $job1->id;
        });
        Queue::assertPushed(RegenerateTextractEmbeddings::class, function ($dispatchedJob) use ($job2) {
            return $dispatchedJob->textractJobId === $job2->id;
        });
        Queue::assertNotPushed(RegenerateTextractEmbeddings::class, function ($dispatchedJob) use ($job3) {
            return $dispatchedJob->textractJobId === $job3->id;
        });
    }

    /** @test */
    public function filters_by_status(): void
    {
        Queue::fake();

        // Create jobs with different statuses
        $completedJob = TextractJob::factory()->completed()->create();
        $succeededJob = TextractJob::factory()->succeeded()->create();
        $queuedJob = TextractJob::factory()->queued()->create();
        $failedJob = TextractJob::factory()->failed()->create();

        // Default filter: completed and succeeded
        $this->artisan('textract:batch-regenerate-embeddings')
            ->expectsOutput('Found 2 jobs to process')
            ->assertExitCode(0);

        Queue::assertPushed(RegenerateTextractEmbeddings::class, 2);

        // Verify correct jobs were dispatched
        Queue::assertPushed(RegenerateTextractEmbeddings::class, function ($dispatchedJob) use ($completedJob) {
            return $dispatchedJob->textractJobId === $completedJob->id;
        });
        Queue::assertPushed(RegenerateTextractEmbeddings::class, function ($dispatchedJob) use ($succeededJob) {
            return $dispatchedJob->textractJobId === $succeededJob->id;
        });
    }

    /** @test */
    public function filters_by_custom_status(): void
    {
        Queue::fake();

        // Create jobs with different statuses
        $completedJob = TextractJob::factory()->completed()->create();
        $queuedJob = TextractJob::factory()->queued()->create();

        // Filter for queued status only
        $this->artisan('textract:batch-regenerate-embeddings', ['--status' => ['queued']])
            ->expectsOutput('Found 1 jobs to process')
            ->assertExitCode(0);

        Queue::assertPushed(RegenerateTextractEmbeddings::class, 1);
        Queue::assertPushed(RegenerateTextractEmbeddings::class, function ($dispatchedJob) use ($queuedJob) {
            return $dispatchedJob->textractJobId === $queuedJob->id;
        });
    }

    /** @test */
    public function respects_limit(): void
    {
        Queue::fake();

        // Create 10 completed jobs
        TextractJob::factory()
            ->completed()
            ->count(10)
            ->create();

        $this->artisan('textract:batch-regenerate-embeddings', ['--limit' => 3])
            ->expectsOutput('Found 3 jobs to process')
            ->expectsOutput('Dispatched 3 jobs with 5s stagger')
            ->assertExitCode(0);

        Queue::assertPushed(RegenerateTextractEmbeddings::class, 3);
    }

    /** @test */
    public function dry_run_with_limit_shows_limited_jobs(): void
    {
        Queue::fake();

        // Create 5 jobs
        TextractJob::factory()
            ->completed()
            ->count(5)
            ->create();

        $this->artisan('textract:batch-regenerate-embeddings', [
            '--dry-run' => true,
            '--limit' => 2,
        ])
            ->expectsOutput('Found 2 jobs to process')
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    /** @test */
    public function shows_zero_jobs_message_when_none_found(): void
    {
        Queue::fake();

        $this->artisan('textract:batch-regenerate-embeddings')
            ->expectsOutput('Found 0 jobs to process')
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    /** @test */
    public function combines_multiple_filters(): void
    {
        Queue::fake();

        $case = LegalCase::factory()->create();

        // Create 5 completed jobs for the case
        TextractJob::factory()
            ->completed()
            ->count(5)
            ->create(['case_id' => $case->id]);

        // Create 3 completed jobs for another case
        TextractJob::factory()
            ->completed()
            ->count(3)
            ->create();

        // Filter by case and limit
        $this->artisan('textract:batch-regenerate-embeddings', [
            '--case' => $case->id,
            '--limit' => 2,
        ])
            ->expectsOutput('Found 2 jobs to process')
            ->assertExitCode(0);

        Queue::assertPushed(RegenerateTextractEmbeddings::class, 2);
    }
}
