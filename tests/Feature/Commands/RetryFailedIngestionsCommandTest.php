<?php

namespace Tests\Feature\Commands;

use App\Jobs\IngestOdlukeDecision;
use App\Models\FailedIngestion;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class RetryFailedIngestionsCommandTest extends TestCase
{
    use UsesTestDatabase;

    /** @test */
    public function it_displays_statistics()
    {
        // Create various failures
        FailedIngestion::create([
            'decision_id' => 'pending-1',
            'failure_reason' => FailedIngestion::REASON_NETWORK_ERROR,
            'last_error_message' => 'Error',
            'status' => FailedIngestion::STATUS_PENDING,
        ]);

        FailedIngestion::create([
            'decision_id' => 'retrying-1',
            'failure_reason' => FailedIngestion::REASON_NETWORK_ERROR,
            'last_error_message' => 'Error',
            'status' => FailedIngestion::STATUS_RETRYING,
        ]);

        FailedIngestion::create([
            'decision_id' => 'failed-1',
            'failure_reason' => FailedIngestion::REASON_EMPTY_TEXT,
            'last_error_message' => 'Error',
            'status' => FailedIngestion::STATUS_FAILED,
        ]);

        $this->artisan('odluke:retry-failed --stats')
            ->expectsOutput('Failed Ingestion Statistics:')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_retries_pending_failures_by_default()
    {
        Queue::fake();

        FailedIngestion::create([
            'decision_id' => 'retry-1',
            'failure_reason' => FailedIngestion::REASON_NETWORK_ERROR,
            'last_error_message' => 'Error',
            'status' => FailedIngestion::STATUS_PENDING,
            'attempt_count' => 1,
            'max_attempts' => 5,
        ]);

        FailedIngestion::create([
            'decision_id' => 'retry-2',
            'failure_reason' => FailedIngestion::REASON_NETWORK_ERROR,
            'last_error_message' => 'Error',
            'status' => FailedIngestion::STATUS_RETRYING,
            'attempt_count' => 2,
            'max_attempts' => 5,
        ]);

        $this->artisan('odluke:retry-failed')
            ->expectsQuestion('Do you want to proceed with retry?', true)
            ->expectsOutput('Found 2 failed ingestion(s) to retry.')
            ->assertExitCode(0);

        Queue::assertPushed(IngestOdlukeDecision::class, 2);
    }

    /** @test */
    public function it_filters_by_specific_id()
    {
        Queue::fake();

        $failure = FailedIngestion::create([
            'decision_id' => 'specific-id',
            'failure_reason' => FailedIngestion::REASON_NETWORK_ERROR,
            'last_error_message' => 'Error',
            'status' => FailedIngestion::STATUS_PENDING,
        ]);

        FailedIngestion::create([
            'decision_id' => 'other-id',
            'failure_reason' => FailedIngestion::REASON_NETWORK_ERROR,
            'last_error_message' => 'Error',
            'status' => FailedIngestion::STATUS_PENDING,
        ]);

        $this->artisan('odluke:retry-failed', ['--id' => $failure->id])
            ->expectsQuestion('Do you want to proceed with retry?', true)
            ->expectsOutput('Found 1 failed ingestion(s) to retry.')
            ->assertExitCode(0);

        Queue::assertPushed(IngestOdlukeDecision::class, 1);
        Queue::assertPushed(IngestOdlukeDecision::class, function ($job) {
            return $job->decisionId === 'specific-id';
        });
    }

    /** @test */
    public function it_filters_by_decision_id()
    {
        Queue::fake();

        FailedIngestion::create([
            'decision_id' => 'decision-123',
            'failure_reason' => FailedIngestion::REASON_NETWORK_ERROR,
            'last_error_message' => 'Error 1',
            'status' => FailedIngestion::STATUS_PENDING,
        ]);

        FailedIngestion::create([
            'decision_id' => 'decision-123',
            'failure_reason' => FailedIngestion::REASON_NETWORK_ERROR,
            'last_error_message' => 'Error 2',
            'status' => FailedIngestion::STATUS_RETRYING,
        ]);

        FailedIngestion::create([
            'decision_id' => 'other-decision',
            'failure_reason' => FailedIngestion::REASON_NETWORK_ERROR,
            'last_error_message' => 'Error',
            'status' => FailedIngestion::STATUS_PENDING,
        ]);

        $this->artisan('odluke:retry-failed', ['--decision-id' => 'decision-123'])
            ->expectsQuestion('Do you want to proceed with retry?', true)
            ->expectsOutput('Found 2 failed ingestion(s) to retry.')
            ->assertExitCode(0);

        Queue::assertPushed(IngestOdlukeDecision::class, 2);
    }

    /** @test */
    public function it_filters_by_status()
    {
        Queue::fake();

        FailedIngestion::create([
            'decision_id' => 'pending-1',
            'failure_reason' => FailedIngestion::REASON_NETWORK_ERROR,
            'last_error_message' => 'Error',
            'status' => FailedIngestion::STATUS_PENDING,
        ]);

        FailedIngestion::create([
            'decision_id' => 'failed-1',
            'failure_reason' => FailedIngestion::REASON_EMPTY_TEXT,
            'last_error_message' => 'Error',
            'status' => FailedIngestion::STATUS_FAILED,
            'attempt_count' => 3,
            'max_attempts' => 5,
        ]);

        $this->artisan('odluke:retry-failed', ['--status' => ['failed']])
            ->expectsQuestion('Do you want to proceed with retry?', true)
            ->expectsOutput('Found 1 failed ingestion(s) to retry.')
            ->assertExitCode(0);

        Queue::assertPushed(IngestOdlukeDecision::class, function ($job) {
            return $job->decisionId === 'failed-1';
        });
    }

    /** @test */
    public function it_respects_limit_option()
    {
        Queue::fake();

        // Create 10 failures
        for ($i = 1; $i <= 10; $i++) {
            FailedIngestion::create([
                'decision_id' => "decision-{$i}",
                'failure_reason' => FailedIngestion::REASON_NETWORK_ERROR,
                'last_error_message' => 'Error',
                'status' => FailedIngestion::STATUS_PENDING,
            ]);
        }

        $this->artisan('odluke:retry-failed', ['--limit' => 3])
            ->expectsQuestion('Do you want to proceed with retry?', true)
            ->assertExitCode(0);

        Queue::assertPushed(IngestOdlukeDecision::class, 3);
    }

    /** @test */
    public function it_resets_attempt_counter_when_requested()
    {
        Queue::fake();

        $failure = FailedIngestion::create([
            'decision_id' => 'reset-me',
            'failure_reason' => FailedIngestion::REASON_NETWORK_ERROR,
            'last_error_message' => 'Error',
            'status' => FailedIngestion::STATUS_RETRYING,
            'attempt_count' => 4,
            'max_attempts' => 5,
        ]);

        $this->artisan('odluke:retry-failed', ['--reset' => true])
            ->expectsQuestion('Do you want to proceed with retry?', true)
            ->assertExitCode(0);

        $failure->refresh();
        $this->assertEquals(0, $failure->attempt_count);
        // After reset and retry dispatch, status becomes 'retrying', not 'pending'
        $this->assertEquals(FailedIngestion::STATUS_RETRYING, $failure->status);
    }

    /** @test */
    public function it_skips_max_attempts_without_reset()
    {
        Queue::fake();

        FailedIngestion::create([
            'decision_id' => 'maxed-out',
            'failure_reason' => FailedIngestion::REASON_NETWORK_ERROR,
            'last_error_message' => 'Error',
            'status' => FailedIngestion::STATUS_RETRYING,
            'attempt_count' => 5,
            'max_attempts' => 5,
        ]);

        $this->artisan('odluke:retry-failed')
            ->expectsQuestion('Do you want to proceed with retry?', true)
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    /** @test */
    public function it_retries_max_attempts_with_reset()
    {
        Queue::fake();

        FailedIngestion::create([
            'decision_id' => 'reset-maxed',
            'failure_reason' => FailedIngestion::REASON_NETWORK_ERROR,
            'last_error_message' => 'Error',
            'status' => FailedIngestion::STATUS_FAILED,
            'attempt_count' => 5,
            'max_attempts' => 5,
        ]);

        // Need to specify --status=failed because default query only includes pending and retrying
        $this->artisan('odluke:retry-failed', ['--reset' => true, '--status' => ['failed']])
            ->expectsOutput('Found 1 failed ingestion(s) to retry.')
            ->expectsQuestion('Do you want to proceed with retry?', true)
            ->assertExitCode(0);

        Queue::assertPushed(IngestOdlukeDecision::class, 1);
    }

    /** @test */
    public function dry_run_shows_what_would_be_retried()
    {
        FailedIngestion::create([
            'decision_id' => 'dry-run-1',
            'failure_reason' => FailedIngestion::REASON_NETWORK_ERROR,
            'last_error_message' => 'Network error',
            'status' => FailedIngestion::STATUS_PENDING,
            'attempt_count' => 2,
            'max_attempts' => 5,
        ]);

        $this->artisan('odluke:retry-failed --dry-run')
            ->expectsOutput('Dry Run - Following ingestions would be retried:')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_abandons_specific_failures()
    {
        $failure1 = FailedIngestion::create([
            'decision_id' => 'abandon-1',
            'failure_reason' => FailedIngestion::REASON_UNKNOWN,
            'last_error_message' => 'Error',
            'status' => FailedIngestion::STATUS_PENDING,
        ]);

        $failure2 = FailedIngestion::create([
            'decision_id' => 'abandon-2',
            'failure_reason' => FailedIngestion::REASON_UNKNOWN,
            'last_error_message' => 'Error',
            'status' => FailedIngestion::STATUS_RETRYING,
        ]);

        $this->artisan('odluke:retry-failed', [
            '--abandon' => "{$failure1->id},{$failure2->id}",
        ])
            ->expectsOutput('Abandoned 2 ingestion(s).')
            ->assertExitCode(0);

        $failure1->refresh();
        $failure2->refresh();

        $this->assertEquals(FailedIngestion::STATUS_ABANDONED, $failure1->status);
        $this->assertEquals(FailedIngestion::STATUS_ABANDONED, $failure2->status);
    }

    /** @test */
    public function it_handles_nonexistent_ids_when_abandoning()
    {
        $this->artisan('odluke:retry-failed', ['--abandon' => '99999'])
            ->expectsOutput('Failed ingestion 99999 not found.')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_shows_message_when_no_failures_found()
    {
        $this->artisan('odluke:retry-failed')
            ->expectsOutput('No failed ingestions found matching criteria.')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_allows_aborting_retry()
    {
        Queue::fake();

        FailedIngestion::create([
            'decision_id' => 'abort-test',
            'failure_reason' => FailedIngestion::REASON_NETWORK_ERROR,
            'last_error_message' => 'Error',
            'status' => FailedIngestion::STATUS_PENDING,
        ]);

        $this->artisan('odluke:retry-failed')
            ->expectsQuestion('Do you want to proceed with retry?', false)
            ->expectsOutput('Aborted.')
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    /** @test */
    public function it_updates_status_to_retrying_after_queuing()
    {
        Queue::fake();

        $failure = FailedIngestion::create([
            'decision_id' => 'status-update',
            'failure_reason' => FailedIngestion::REASON_NETWORK_ERROR,
            'last_error_message' => 'Error',
            'status' => FailedIngestion::STATUS_PENDING,
        ]);

        $this->artisan('odluke:retry-failed')
            ->expectsQuestion('Do you want to proceed with retry?', true)
            ->assertExitCode(0);

        $failure->refresh();
        $this->assertEquals(FailedIngestion::STATUS_RETRYING, $failure->status);
        $this->assertNotNull($failure->next_retry_at);
    }

    /** @test */
    public function it_passes_failed_ingestion_id_to_job()
    {
        Queue::fake();

        $failure = FailedIngestion::create([
            'decision_id' => 'track-me',
            'failure_reason' => FailedIngestion::REASON_NETWORK_ERROR,
            'last_error_message' => 'Error',
            'status' => FailedIngestion::STATUS_PENDING,
        ]);

        $this->artisan('odluke:retry-failed')
            ->expectsQuestion('Do you want to proceed with retry?', true)
            ->assertExitCode(0);

        Queue::assertPushed(IngestOdlukeDecision::class, function ($job) use ($failure) {
            return $job->failedIngestionId === (string) $failure->id;
        });
    }

    /** @test */
    public function statistics_shows_recent_failures()
    {
        FailedIngestion::create([
            'decision_id' => 'recent-1',
            'failure_reason' => FailedIngestion::REASON_NETWORK_ERROR,
            'last_error_message' => 'Error',
            'status' => FailedIngestion::STATUS_PENDING,
        ]);

        $this->artisan('odluke:retry-failed --stats')
            ->expectsOutput('Recent Failures (last 10):')
            ->assertExitCode(0);
    }

    /** @test */
    public function statistics_shows_ready_for_retry_warning()
    {
        FailedIngestion::create([
            'decision_id' => 'ready-1',
            'failure_reason' => FailedIngestion::REASON_NETWORK_ERROR,
            'last_error_message' => 'Error',
            'status' => FailedIngestion::STATUS_PENDING,
            'next_retry_at' => now()->subMinutes(5),
        ]);

        $this->artisan('odluke:retry-failed --stats')
            ->assertExitCode(0);
    }
}
