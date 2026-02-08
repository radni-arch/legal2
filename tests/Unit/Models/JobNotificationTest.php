<?php

namespace Tests\Unit\Models;

use App\Models\JobNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_notification_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $notification = JobNotification::create([
            'user_id' => $user->id,
            'job_id' => 'job-123',
            'job_type' => 'ProcessTextractJob',
            'job_name' => 'Processing document.pdf',
            'status' => 'completed',
        ]);

        $this->assertInstanceOf(User::class, $notification->user);
        $this->assertEquals($user->id, $notification->user->id);
    }

    public function test_job_notification_can_be_marked_as_read(): void
    {
        $user = User::factory()->create();
        $notification = JobNotification::create([
            'user_id' => $user->id,
            'job_id' => 'job-123',
            'job_type' => 'ProcessTextractJob',
            'job_name' => 'Processing document.pdf',
            'status' => 'completed',
        ]);

        $this->assertFalse($notification->read);

        $notification->markAsRead();

        $this->assertTrue($notification->fresh()->read);
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_unread_scope_returns_only_unread_notifications(): void
    {
        $user = User::factory()->create();

        JobNotification::create([
            'user_id' => $user->id,
            'job_id' => 'job-1',
            'job_type' => 'ProcessTextractJob',
            'job_name' => 'Doc 1',
            'status' => 'completed',
            'read' => false,
        ]);

        JobNotification::create([
            'user_id' => $user->id,
            'job_id' => 'job-2',
            'job_type' => 'ProcessTextractJob',
            'job_name' => 'Doc 2',
            'status' => 'completed',
            'read' => true,
        ]);

        $unread = JobNotification::unread()->get();

        $this->assertCount(1, $unread);
        $this->assertEquals('job-1', $unread->first()->job_id);
    }
}
