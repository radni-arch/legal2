<?php

namespace Tests\Unit\Listeners;

use App\Events\CaseReadyForReview;
use App\Listeners\NotifyCaseReadyForReview;
use App\Models\LegalCase;
use App\Models\TextractJob;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class NotifyCaseReadyForReviewTest extends TestCase
{
    use UsesTestDatabase;

    /**
     * Test: Logs case ready message with correct data.
     */
    public function test_logs_case_ready_message_with_correct_data(): void
    {
        // Arrange
        $user = User::factory()->create();
        $case = LegalCase::factory()->create([
            'title' => 'Test Case Title',
            'user_id' => $user->id,
        ]);

        // Create some textract jobs for the case
        TextractJob::factory()->create(['case_id' => $case->id, 'status' => 'succeeded']);
        TextractJob::factory()->create(['case_id' => $case->id, 'status' => 'succeeded']);

        $event = new CaseReadyForReview((string) $case->id);

        // Set up log spy
        Log::shouldReceive('info')
            ->once()
            ->with(
                'Case ready for review - all documents processed',
                \Mockery::on(function ($context) use ($case) {
                    // case_id might be ULID object, compare as strings
                    return (string) $context['case_id'] === (string) $case->id
                        && $context['case_name'] === 'Test Case Title'
                        && $context['document_count'] === 2;
                })
            );

        // Act
        $listener = new NotifyCaseReadyForReview();
        $listener->handle($event);

        // Assert - Log::shouldReceive handles assertion
    }

    /**
     * Test: Handles missing case gracefully by logging warning.
     */
    public function test_handles_missing_case_gracefully(): void
    {
        // Arrange
        $nonExistentCaseId = 'non-existent-case-id';
        $event = new CaseReadyForReview($nonExistentCaseId);

        // Set up log spy - expect warning, not info
        Log::shouldReceive('warning')
            ->once()
            ->with(
                'CaseReadyForReview: Case not found',
                ['case_id' => $nonExistentCaseId]
            );

        Log::shouldReceive('info')->never();

        // Act
        $listener = new NotifyCaseReadyForReview();
        $listener->handle($event);

        // Assert - Log::shouldReceive handles assertion
    }

    /**
     * Test: Logs notification intent when notifications enabled and user exists.
     */
    public function test_logs_notification_intent_when_enabled(): void
    {
        // Arrange
        config(['textract.notifications.enabled' => true]);

        $user = User::factory()->create();
        $case = LegalCase::factory()->create([
            'title' => 'Test Notification Case',
            'user_id' => $user->id,
        ]);

        $event = new CaseReadyForReview((string) $case->id);

        // Set up log spy - expect both info calls
        Log::shouldReceive('info')
            ->once()
            ->with(
                'Case ready for review - all documents processed',
                \Mockery::any()
            );

        Log::shouldReceive('info')
            ->once()
            ->with(
                'Would notify user about case ready',
                ['user_id' => $user->id]
            );

        // Act
        $listener = new NotifyCaseReadyForReview();
        $listener->handle($event);

        // Assert - Log::shouldReceive handles assertion
    }

    /**
     * Test: Does not log notification intent when notifications disabled.
     */
    public function test_does_not_log_notification_when_disabled(): void
    {
        // Arrange
        config(['textract.notifications.enabled' => false]);

        $user = User::factory()->create();
        $case = LegalCase::factory()->create([
            'title' => 'Test No Notification Case',
            'user_id' => $user->id,
        ]);

        $event = new CaseReadyForReview((string) $case->id);

        // Set up log spy - expect only the case ready log, not the notification log
        Log::shouldReceive('info')
            ->once()
            ->with(
                'Case ready for review - all documents processed',
                \Mockery::any()
            );

        // Should NOT receive the notification log
        Log::shouldReceive('info')
            ->with('Would notify user about case ready', \Mockery::any())
            ->never();

        // Act
        $listener = new NotifyCaseReadyForReview();
        $listener->handle($event);

        // Assert - Log::shouldReceive handles assertion
    }

    /**
     * Test: Does not log notification when case has no user.
     */
    public function test_does_not_log_notification_when_no_user(): void
    {
        // Arrange
        config(['textract.notifications.enabled' => true]);

        $case = LegalCase::factory()->create([
            'title' => 'Case Without User',
            'user_id' => null,
        ]);

        $event = new CaseReadyForReview((string) $case->id);

        // Set up log spy - expect only the case ready log
        Log::shouldReceive('info')
            ->once()
            ->with(
                'Case ready for review - all documents processed',
                \Mockery::any()
            );

        // Should NOT receive the notification log
        Log::shouldReceive('info')
            ->with('Would notify user about case ready', \Mockery::any())
            ->never();

        // Act
        $listener = new NotifyCaseReadyForReview();
        $listener->handle($event);

        // Assert - Log::shouldReceive handles assertion
    }
}
