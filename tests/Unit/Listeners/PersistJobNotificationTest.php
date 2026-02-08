<?php

namespace Tests\Unit\Listeners;

use App\Events\JobCompleted;
use App\Events\JobFailed;
use App\Events\JobProgress;
use App\Listeners\PersistJobNotification;
use App\Models\JobNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersistJobNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_persists_job_completed_event(): void
    {
        $user = User::factory()->create();
        $event = new JobCompleted(
            userId: $user->id,
            jobId: 'job-123',
            jobType: 'ProcessTextractJob',
            jobName: 'Processing document.pdf',
            result: ['pages' => 10],
            metadata: []
        );

        $listener = new PersistJobNotification();
        $listener->handle($event);

        $this->assertDatabaseHas('job_notifications', [
            'user_id' => $user->id,
            'job_id' => 'job-123',
            'job_type' => 'ProcessTextractJob',
            'status' => 'completed',
        ]);
    }

    public function test_persists_job_failed_event(): void
    {
        $user = User::factory()->create();
        $event = new JobFailed(
            userId: $user->id,
            jobId: 'job-456',
            jobType: 'ProcessTextractJob',
            jobName: 'Processing failed.pdf',
            error: 'Connection timeout',
            stage: 'Uploading',
            metadata: []
        );

        $listener = new PersistJobNotification();
        $listener->handle($event);

        $this->assertDatabaseHas('job_notifications', [
            'user_id' => $user->id,
            'job_id' => 'job-456',
            'status' => 'failed',
            'error' => 'Connection timeout',
        ]);
    }

    public function test_does_not_persist_progress_events(): void
    {
        $user = User::factory()->create();
        $event = new JobProgress(
            userId: $user->id,
            jobId: 'job-789',
            jobType: 'ProcessTextractJob',
            jobName: 'Processing doc.pdf',
            progress: 50,
            stage: 'Processing',
            currentItem: 'Page 5',
            metadata: []
        );

        $listener = new PersistJobNotification();
        $listener->handle($event);

        $this->assertDatabaseMissing('job_notifications', [
            'job_id' => 'job-789',
        ]);
    }
}
