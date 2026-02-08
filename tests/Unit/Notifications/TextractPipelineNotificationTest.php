<?php

namespace Tests\Unit\Notifications;

use App\Models\TextractJob;
use App\Models\User;
use App\Notifications\TextractPipelineNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Task 3.2: External Notifications
 *
 * Tests for TextractPipelineNotification class.
 * Verifies notification is sent on pipeline events and respects configuration.
 */
class TextractPipelineNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected TextractJob $textractJob;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->textractJob = TextractJob::factory()->create([
            'case_id' => null,
            'status' => 'completed',
        ]);
    }

    /**
     * Test notification is sent on pipeline completion event.
     */
    public function test_notification_sent_on_completion(): void
    {
        Notification::fake();

        $notification = new TextractPipelineNotification(
            $this->textractJob,
            'completed'
        );

        $this->user->notify($notification);

        Notification::assertSentTo(
            $this->user,
            TextractPipelineNotification::class,
            function ($notification) {
                return $notification->event === 'completed';
            }
        );
    }

    /**
     * Test notification is sent on embedding complete event.
     */
    public function test_notification_sent_on_embedding_complete(): void
    {
        Notification::fake();

        $notification = new TextractPipelineNotification(
            $this->textractJob,
            'embedding_complete'
        );

        $this->user->notify($notification);

        Notification::assertSentTo(
            $this->user,
            TextractPipelineNotification::class,
            function ($notification) {
                return $notification->event === 'embedding_complete';
            }
        );
    }

    /**
     * Test notification is sent on graph synced event.
     */
    public function test_notification_sent_on_graph_synced(): void
    {
        Notification::fake();

        $notification = new TextractPipelineNotification(
            $this->textractJob,
            'graph_synced'
        );

        $this->user->notify($notification);

        Notification::assertSentTo(
            $this->user,
            TextractPipelineNotification::class,
            function ($notification) {
                return $notification->event === 'graph_synced';
            }
        );
    }

    /**
     * Test notification is sent on failure event.
     */
    public function test_notification_sent_on_failure(): void
    {
        Notification::fake();

        $notification = new TextractPipelineNotification(
            $this->textractJob,
            'failed',
            'Processing failed due to invalid document format'
        );

        $this->user->notify($notification);

        Notification::assertSentTo(
            $this->user,
            TextractPipelineNotification::class,
            function ($notification) {
                return $notification->event === 'failed'
                    && $notification->message === 'Processing failed due to invalid document format';
            }
        );
    }

    /**
     * Test database channel is always included.
     */
    public function test_database_channel_always_included(): void
    {
        Config::set('textract.notifications.mail.enabled', false);

        $notification = new TextractPipelineNotification(
            $this->textractJob,
            'completed'
        );

        $channels = $notification->via($this->user);

        $this->assertContains('database', $channels);
    }

    /**
     * Test mail channel is optional and disabled by default.
     */
    public function test_mail_channel_disabled_by_default(): void
    {
        // Ensure mail is not explicitly enabled
        Config::set('textract.notifications.mail.enabled', false);

        $notification = new TextractPipelineNotification(
            $this->textractJob,
            'completed'
        );

        $channels = $notification->via($this->user);

        $this->assertNotContains('mail', $channels);
    }

    /**
     * Test mail channel can be enabled via config.
     */
    public function test_mail_channel_enabled_via_config(): void
    {
        Config::set('textract.notifications.mail.enabled', true);

        $notification = new TextractPipelineNotification(
            $this->textractJob,
            'completed'
        );

        $channels = $notification->via($this->user);

        $this->assertContains('mail', $channels);
    }

    /**
     * Test database notification format contains required fields.
     */
    public function test_database_notification_format(): void
    {
        $notification = new TextractPipelineNotification(
            $this->textractJob,
            'completed'
        );

        $data = $notification->toArray($this->user);

        $this->assertArrayHasKey('textract_job_id', $data);
        $this->assertArrayHasKey('event', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('case_id', $data);

        $this->assertEquals($this->textractJob->id, $data['textract_job_id']);
        $this->assertEquals('completed', $data['event']);
    }

    /**
     * Test database notification includes case_id when available.
     */
    public function test_database_notification_includes_case_id(): void
    {
        $case = \App\Models\LegalCase::factory()->create();

        $textractJob = TextractJob::factory()->create([
            'case_id' => $case->id,
            'status' => 'completed',
        ]);

        $notification = new TextractPipelineNotification(
            $textractJob,
            'completed'
        );

        $data = $notification->toArray($this->user);

        $this->assertEquals($case->id, $data['case_id']);
    }

    /**
     * Test mail message format for completion event.
     */
    public function test_mail_message_format_completed(): void
    {
        $notification = new TextractPipelineNotification(
            $this->textractJob,
            'completed'
        );

        $mailMessage = $notification->toMail($this->user);

        $this->assertInstanceOf(MailMessage::class, $mailMessage);
        $this->assertEquals('Textract Processing Complete', $mailMessage->subject);
    }

    /**
     * Test mail message format for failed event.
     */
    public function test_mail_message_format_failed(): void
    {
        $notification = new TextractPipelineNotification(
            $this->textractJob,
            'failed'
        );

        $mailMessage = $notification->toMail($this->user);

        $this->assertInstanceOf(MailMessage::class, $mailMessage);
        $this->assertEquals('Textract Processing Failed', $mailMessage->subject);
    }

    /**
     * Test mail message format for embedding_complete event.
     */
    public function test_mail_message_format_embedding_complete(): void
    {
        $notification = new TextractPipelineNotification(
            $this->textractJob,
            'embedding_complete'
        );

        $mailMessage = $notification->toMail($this->user);

        $this->assertInstanceOf(MailMessage::class, $mailMessage);
        $this->assertEquals('Embeddings Generated', $mailMessage->subject);
    }

    /**
     * Test mail message format for graph_synced event.
     */
    public function test_mail_message_format_graph_synced(): void
    {
        $notification = new TextractPipelineNotification(
            $this->textractJob,
            'graph_synced'
        );

        $mailMessage = $notification->toMail($this->user);

        $this->assertInstanceOf(MailMessage::class, $mailMessage);
        $this->assertEquals('Graph Sync Complete', $mailMessage->subject);
    }

    /**
     * Test custom message overrides default message.
     */
    public function test_custom_message_overrides_default(): void
    {
        $customMessage = 'This is a custom notification message';

        $notification = new TextractPipelineNotification(
            $this->textractJob,
            'completed',
            $customMessage
        );

        $data = $notification->toArray($this->user);

        $this->assertEquals($customMessage, $data['message']);
    }

    /**
     * Test default messages are generated for each event type.
     */
    public function test_default_messages_generated(): void
    {
        $events = ['completed', 'embedding_complete', 'graph_synced', 'failed'];

        foreach ($events as $event) {
            $notification = new TextractPipelineNotification(
                $this->textractJob,
                $event
            );

            $data = $notification->toArray($this->user);

            $this->assertNotEmpty($data['message']);
            $this->assertStringContainsString((string) $this->textractJob->id, $data['message']);
        }
    }

    /**
     * Test notification implements ShouldQueue for async sending.
     */
    public function test_notification_is_queueable(): void
    {
        $notification = new TextractPipelineNotification(
            $this->textractJob,
            'completed'
        );

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $notification);
    }

    /**
     * Test toArray() includes file_name from TextractJob.
     *
     * The database notification should include the file name so users
     * can identify which document the notification is about without
     * needing to look up the job.
     */
    public function test_to_array_includes_file_name(): void
    {
        $textractJob = TextractJob::factory()->create([
            'drive_file_name' => 'contract-2024.pdf',
            'status' => 'completed',
        ]);

        $notification = new TextractPipelineNotification(
            $textractJob,
            'completed'
        );

        $data = $notification->toArray($this->user);

        $this->assertArrayHasKey('file_name', $data);
        $this->assertEquals('contract-2024.pdf', $data['file_name']);
    }

    /**
     * Test toMail() uses error level styling for failed events.
     *
     * Laravel MailMessage supports ->error() which renders the
     * action button in red, providing visual cue for failures.
     */
    public function test_mail_uses_error_level_for_failed_events(): void
    {
        $notification = new TextractPipelineNotification(
            $this->textractJob,
            'failed',
            'OCR extraction timed out'
        );

        $mailMessage = $notification->toMail($this->user);

        $this->assertEquals('error', $mailMessage->level);
    }

    /**
     * Test toMail() uses success level styling for success events.
     *
     * Non-failure events should render with success styling.
     */
    public function test_mail_uses_success_level_for_success_events(): void
    {
        $notification = new TextractPipelineNotification(
            $this->textractJob,
            'completed'
        );

        $mailMessage = $notification->toMail($this->user);

        $this->assertEquals('success', $mailMessage->level);
    }

    /**
     * Test notifications config key is properly declared in textract config.
     *
     * Ensures the config value has a declared default in config/textract.php
     * rather than relying solely on the fallback in config() calls.
     */
    public function test_notifications_config_key_declared(): void
    {
        // Read the raw config array (not runtime overrides)
        $textractConfig = require base_path('config/textract.php');

        $this->assertArrayHasKey('notifications', $textractConfig);
        $this->assertArrayHasKey('mail', $textractConfig['notifications']);
        $this->assertArrayHasKey('enabled', $textractConfig['notifications']['mail']);
    }
}
