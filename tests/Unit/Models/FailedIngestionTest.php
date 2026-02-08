<?php

namespace Tests\Unit\Models;

use App\Models\FailedIngestion;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class FailedIngestionTest extends TestCase
{
    use UsesTestDatabase;

    /** @test */
    public function it_creates_failed_ingestion_with_defaults()
    {
        $failure = FailedIngestion::create([
            'decision_id' => 'test-123',
            'source_type' => 'odluke',
            'failure_reason' => FailedIngestion::REASON_NETWORK_ERROR,
            'last_error_message' => 'Connection timeout',
            'attempt_count' => 0,
            'max_attempts' => 5,
            'status' => FailedIngestion::STATUS_PENDING,
            'retry_delay_seconds' => 60,
        ]);

        $this->assertEquals('test-123', $failure->decision_id);
        $this->assertEquals('odluke', $failure->source_type);
        $this->assertEquals(0, $failure->attempt_count);
        $this->assertEquals(5, $failure->max_attempts);
        $this->assertEquals(FailedIngestion::STATUS_PENDING, $failure->status);
        $this->assertEquals(60, $failure->retry_delay_seconds);
    }

    /** @test */
    public function it_records_failure_using_static_helper()
    {
        $failure = FailedIngestion::recordFailure(
            decisionId: 'test-456',
            failureReason: FailedIngestion::REASON_EMPTY_TEXT,
            errorMessage: 'No text extracted',
            errorDetails: ['html_length' => 0, 'pdf_length' => 0],
            ingestionOptions: ['force' => true],
            sourceType: 'odluke',
            maxAttempts: 3
        );

        $this->assertInstanceOf(FailedIngestion::class, $failure);
        $this->assertEquals('test-456', $failure->decision_id);
        $this->assertEquals(FailedIngestion::REASON_EMPTY_TEXT, $failure->failure_reason);
        $this->assertEquals('No text extracted', $failure->last_error_message);
        $this->assertArrayHasKey('attempt_1', $failure->error_details);
        $this->assertEquals('No text extracted', $failure->error_details['attempt_1']['error']);
        $this->assertEquals(['html_length' => 0, 'pdf_length' => 0], $failure->error_details['attempt_1']['details']);
        $this->assertEquals(['force' => true], $failure->ingestion_options);
        $this->assertEquals(3, $failure->max_attempts);
        $this->assertEquals(1, $failure->attempt_count);
    }

    /** @test */
    public function it_updates_existing_failure_when_recording_same_decision()
    {
        $existing = FailedIngestion::create([
            'decision_id' => 'test-789',
            'failure_reason' => FailedIngestion::REASON_NETWORK_ERROR,
            'last_error_message' => 'First error',
            'attempt_count' => 1,
        ]);

        $updated = FailedIngestion::recordFailure(
            decisionId: 'test-789',
            failureReason: FailedIngestion::REASON_NETWORK_ERROR,
            errorMessage: 'Second error',
            errorDetails: ['retry' => 2]
        );

        $this->assertEquals($existing->id, $updated->id);
        $this->assertEquals(2, $updated->attempt_count);
        $this->assertEquals('Second error', $updated->last_error_message);
        $this->assertNotNull($updated->last_attempted_at);
        $this->assertNotNull($updated->next_retry_at);
    }

    /** @test */
    public function it_calculates_exponential_backoff_correctly()
    {
        $failure = FailedIngestion::create([
            'decision_id' => 'test-backoff',
            'source_type' => 'odluke',
            'failure_reason' => FailedIngestion::REASON_NETWORK_ERROR,
            'last_error_message' => 'Error',
            'retry_delay_seconds' => 60,
            'attempt_count' => 0,
            'max_attempts' => 15,
            'status' => FailedIngestion::STATUS_RETRYING,
        ]);

        // Attempt 1: 60 * 2^0 = 60 seconds (attempt_count starts at 0, becomes 1 after increment)
        $failure->attempt_count = 0;
        $failure->save();
        $failure->recordFailedAttempt('Error 1', []);
        $this->assertEqualsWithDelta(60, now()->diffInSeconds($failure->next_retry_at, false), 5);

        // Attempt 2: 60 * 2^1 = 120 seconds
        $failure->attempt_count = 1;
        $failure->save();
        $failure->recordFailedAttempt('Error 2', []);
        $this->assertEqualsWithDelta(120, now()->diffInSeconds($failure->next_retry_at, false), 5);

        // Attempt 3: 60 * 2^2 = 240 seconds
        $failure->attempt_count = 2;
        $failure->save();
        $failure->recordFailedAttempt('Error 3', []);
        $this->assertEqualsWithDelta(240, now()->diffInSeconds($failure->next_retry_at, false), 5);

        // Attempt 10: would be 60 * 2^9 = 30720, but capped at 3600 (1 hour)
        $failure->attempt_count = 9;
        $failure->save();
        $failure->recordFailedAttempt('Error 10', []);
        $this->assertEqualsWithDelta(3600, now()->diffInSeconds($failure->next_retry_at, false), 5);
    }

    /** @test */
    public function it_detects_max_attempts_reached()
    {
        $failure = FailedIngestion::create([
            'decision_id' => 'test-max',
            'failure_reason' => FailedIngestion::REASON_NETWORK_ERROR,
            'last_error_message' => 'Error',
            'max_attempts' => 3,
            'attempt_count' => 2,
        ]);

        $this->assertFalse($failure->hasReachedMaxAttempts());

        $failure->attempt_count = 3;
        $this->assertTrue($failure->hasReachedMaxAttempts());

        $failure->attempt_count = 4;
        $this->assertTrue($failure->hasReachedMaxAttempts());
    }

    /** @test */
    public function it_marks_as_succeeded()
    {
        $failure = FailedIngestion::create([
            'decision_id' => 'test-success',
            'failure_reason' => FailedIngestion::REASON_NETWORK_ERROR,
            'last_error_message' => 'Error',
            'status' => FailedIngestion::STATUS_RETRYING,
        ]);

        $failure->markSucceeded([
            'chunks_inserted' => 5,
            'graph_synced' => true,
        ]);

        $this->assertEquals(FailedIngestion::STATUS_SUCCEEDED, $failure->status);
        $this->assertNotNull($failure->succeeded_at);
        $this->assertEquals(['chunks_inserted' => 5, 'graph_synced' => true], $failure->success_details);
    }

    /** @test */
    public function it_marks_as_permanently_failed()
    {
        $failure = FailedIngestion::create([
            'decision_id' => 'test-failed',
            'source_type' => 'odluke',
            'failure_reason' => FailedIngestion::REASON_EMPTY_TEXT,
            'last_error_message' => 'Empty',
            'attempt_count' => 5,
            'max_attempts' => 5,
            'status' => FailedIngestion::STATUS_RETRYING,
        ]);

        // When max attempts is reached, recordFailedAttempt will mark it as failed
        $failure->recordFailedAttempt('Max attempts reached');

        $this->assertEquals(FailedIngestion::STATUS_FAILED, $failure->status);
        $this->assertEquals('Max attempts reached', $failure->last_error_message);
    }

    /** @test */
    public function it_marks_as_abandoned()
    {
        $failure = FailedIngestion::create([
            'decision_id' => 'test-abandon',
            'failure_reason' => FailedIngestion::REASON_UNKNOWN,
            'last_error_message' => 'Unknown error',
        ]);

        $failure->markAbandoned('Manually abandoned by admin');

        $this->assertEquals(FailedIngestion::STATUS_ABANDONED, $failure->status);
        $this->assertEquals('Manually abandoned by admin', $failure->last_error_message);
    }

    /** @test */
    public function it_resets_retry_counter()
    {
        $failure = FailedIngestion::create([
            'decision_id' => 'test-reset',
            'source_type' => 'odluke',
            'failure_reason' => FailedIngestion::REASON_NETWORK_ERROR,
            'last_error_message' => 'Error',
            'attempt_count' => 5,
            'max_attempts' => 5,
            'status' => FailedIngestion::STATUS_FAILED,
        ]);

        $failure->resetRetryCounter();

        $this->assertEquals(0, $failure->attempt_count);
        $this->assertEquals(FailedIngestion::STATUS_PENDING, $failure->status);
        $this->assertNotNull($failure->next_retry_at);
        $this->assertNull($failure->last_error_message);
    }

    /** @test */
    public function pending_retries_scope_filters_correctly()
    {
        // Clear any existing records from previous tests
        FailedIngestion::query()->delete();

        // Create various failures
        FailedIngestion::create([
            'decision_id' => 'pending-1',
            'failure_reason' => FailedIngestion::REASON_NETWORK_ERROR,
            'last_error_message' => 'Error',
            'status' => FailedIngestion::STATUS_PENDING,
            'attempt_count' => 1,
            'max_attempts' => 5,
        ]);

        FailedIngestion::create([
            'decision_id' => 'retrying-1',
            'failure_reason' => FailedIngestion::REASON_NETWORK_ERROR,
            'last_error_message' => 'Error',
            'status' => FailedIngestion::STATUS_RETRYING,
            'attempt_count' => 3,
            'max_attempts' => 5,
        ]);

        FailedIngestion::create([
            'decision_id' => 'failed-1',
            'failure_reason' => FailedIngestion::REASON_NETWORK_ERROR,
            'last_error_message' => 'Error',
            'status' => FailedIngestion::STATUS_FAILED,
            'attempt_count' => 5,
            'max_attempts' => 5,
        ]);

        FailedIngestion::create([
            'decision_id' => 'maxed-out',
            'failure_reason' => FailedIngestion::REASON_NETWORK_ERROR,
            'last_error_message' => 'Error',
            'status' => FailedIngestion::STATUS_PENDING,
            'attempt_count' => 5,
            'max_attempts' => 5,
        ]);

        $pending = FailedIngestion::pendingRetries()->get();

        $this->assertCount(2, $pending);
        $this->assertTrue($pending->contains('decision_id', 'pending-1'));
        $this->assertTrue($pending->contains('decision_id', 'retrying-1'));
    }

    /** @test */
    public function ready_for_retry_scope_filters_by_time()
    {
        // Ready now (no next_retry_at)
        FailedIngestion::create([
            'decision_id' => 'ready-now',
            'failure_reason' => FailedIngestion::REASON_NETWORK_ERROR,
            'last_error_message' => 'Error',
            'status' => FailedIngestion::STATUS_PENDING,
            'next_retry_at' => null,
        ]);

        // Ready (past time)
        FailedIngestion::create([
            'decision_id' => 'ready-past',
            'failure_reason' => FailedIngestion::REASON_NETWORK_ERROR,
            'last_error_message' => 'Error',
            'status' => FailedIngestion::STATUS_RETRYING,
            'next_retry_at' => now()->subMinutes(5),
        ]);

        // Not ready yet (future time)
        FailedIngestion::create([
            'decision_id' => 'not-ready',
            'failure_reason' => FailedIngestion::REASON_NETWORK_ERROR,
            'last_error_message' => 'Error',
            'status' => FailedIngestion::STATUS_RETRYING,
            'next_retry_at' => now()->addMinutes(10),
        ]);

        $ready = FailedIngestion::readyForRetry()->get();

        $this->assertCount(2, $ready);
        $this->assertTrue($ready->contains('decision_id', 'ready-now'));
        $this->assertTrue($ready->contains('decision_id', 'ready-past'));
    }

    /** @test */
    public function permanently_failed_scope_filters_correctly()
    {
        // Clear any existing records from previous tests
        FailedIngestion::query()->delete();

        FailedIngestion::create([
            'decision_id' => 'perm-failed-1',
            'failure_reason' => FailedIngestion::REASON_EMPTY_TEXT,
            'last_error_message' => 'Error',
            'status' => FailedIngestion::STATUS_FAILED,
            'attempt_count' => 5,
            'max_attempts' => 5,
        ]);

        FailedIngestion::create([
            'decision_id' => 'perm-failed-2',
            'failure_reason' => FailedIngestion::REASON_NETWORK_ERROR,
            'last_error_message' => 'Error',
            'status' => FailedIngestion::STATUS_FAILED,
            'attempt_count' => 3,
            'max_attempts' => 3,
        ]);

        // This should NOT be included (abandoned is different from failed)
        FailedIngestion::create([
            'decision_id' => 'abandoned-1',
            'failure_reason' => FailedIngestion::REASON_UNKNOWN,
            'last_error_message' => 'Error',
            'status' => FailedIngestion::STATUS_ABANDONED,
        ]);

        // This should NOT be included (pending, not failed)
        FailedIngestion::create([
            'decision_id' => 'pending-1',
            'failure_reason' => FailedIngestion::REASON_NETWORK_ERROR,
            'last_error_message' => 'Error',
            'status' => FailedIngestion::STATUS_PENDING,
        ]);

        // This should NOT be included (failed but hasn't reached max attempts)
        FailedIngestion::create([
            'decision_id' => 'failed-not-maxed',
            'failure_reason' => FailedIngestion::REASON_NETWORK_ERROR,
            'last_error_message' => 'Error',
            'status' => FailedIngestion::STATUS_FAILED,
            'attempt_count' => 2,
            'max_attempts' => 5,
        ]);

        $permanentlyFailed = FailedIngestion::permanentlyFailed()->get();

        $this->assertCount(2, $permanentlyFailed);
        $this->assertTrue($permanentlyFailed->contains('decision_id', 'perm-failed-1'));
        $this->assertTrue($permanentlyFailed->contains('decision_id', 'perm-failed-2'));
    }

    /** @test */
    public function it_provides_statistics()
    {
        // Clear any existing records from previous tests
        FailedIngestion::query()->delete();

        FailedIngestion::create([
            'decision_id' => 'stat-pending',
            'status' => FailedIngestion::STATUS_PENDING,
            'failure_reason' => FailedIngestion::REASON_NETWORK_ERROR,
            'last_error_message' => 'Error',
        ]);

        FailedIngestion::create([
            'decision_id' => 'stat-retrying',
            'status' => FailedIngestion::STATUS_RETRYING,
            'failure_reason' => FailedIngestion::REASON_NETWORK_ERROR,
            'last_error_message' => 'Error',
            'next_retry_at' => now()->subMinutes(1),
        ]);

        FailedIngestion::create([
            'decision_id' => 'stat-failed',
            'status' => FailedIngestion::STATUS_FAILED,
            'failure_reason' => FailedIngestion::REASON_EMPTY_TEXT,
            'last_error_message' => 'Error',
            'attempt_count' => 5,
            'max_attempts' => 5,
        ]);

        FailedIngestion::create([
            'decision_id' => 'stat-succeeded',
            'status' => FailedIngestion::STATUS_SUCCEEDED,
            'failure_reason' => FailedIngestion::REASON_NETWORK_ERROR,
            'last_error_message' => 'Error',
        ]);

        FailedIngestion::create([
            'decision_id' => 'stat-abandoned',
            'status' => FailedIngestion::STATUS_ABANDONED,
            'failure_reason' => FailedIngestion::REASON_UNKNOWN,
            'last_error_message' => 'Error',
        ]);

        $stats = FailedIngestion::getStatistics();

        $this->assertEquals(5, $stats['total']);
        $this->assertEquals(1, $stats['pending']);
        $this->assertEquals(1, $stats['retrying']);
        $this->assertEquals(1, $stats['failed']);
        $this->assertEquals(1, $stats['succeeded']);
        $this->assertEquals(1, $stats['abandoned']);
        $this->assertEquals(2, $stats['ready_for_retry']); // pending + retrying with past time
    }

    /** @test */
    public function status_label_accessor_returns_correct_values()
    {
        $failure = FailedIngestion::create([
            'decision_id' => 'test-label',
            'failure_reason' => FailedIngestion::REASON_NETWORK_ERROR,
            'last_error_message' => 'Error',
            'status' => FailedIngestion::STATUS_PENDING,
            'attempt_count' => 2,
            'max_attempts' => 5,
        ]);

        $this->assertEquals('Pending', $failure->status_label);

        $failure->status = FailedIngestion::STATUS_RETRYING;
        $this->assertEquals('Retrying (Attempt 2/5)', $failure->status_label);

        $failure->status = FailedIngestion::STATUS_FAILED;
        $this->assertEquals('Failed', $failure->status_label);

        $failure->status = FailedIngestion::STATUS_SUCCEEDED;
        $this->assertEquals('Succeeded', $failure->status_label);

        $failure->status = FailedIngestion::STATUS_ABANDONED;
        $this->assertEquals('Abandoned', $failure->status_label);
    }
}
