<?php

namespace Tests\Unit\Traits;

use App\Events\JobCompleted;
use App\Events\JobFailed;
use App\Events\JobProgress;
use App\Events\JobStarted;
use App\Models\User;
use App\Traits\BroadcastsJobProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BroadcastsJobProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_broadcasts_job_started_event(): void
    {
        Event::fake();
        $user = User::factory()->create();

        $job = new class {
            use BroadcastsJobProgress {
                broadcastStarted as public;
                broadcastProgress as public;
                broadcastCompleted as public;
                broadcastFailed as public;
            }

            public function getJobDisplayName(): string
            {
                return 'Test Job';
            }
        };

        $job->broadcastStarted($user->id, 'test-job-id', ['test' => 'data']);

        Event::assertDispatched(JobStarted::class, function ($event) use ($user) {
            return $event->userId === $user->id
                && $event->jobId === 'test-job-id'
                && $event->jobName === 'Test Job';
        });
    }

    public function test_broadcasts_job_progress_event(): void
    {
        Event::fake();
        $user = User::factory()->create();

        $job = new class {
            use BroadcastsJobProgress {
                broadcastStarted as public;
                broadcastProgress as public;
                broadcastCompleted as public;
                broadcastFailed as public;
            }

            public function getJobDisplayName(): string
            {
                return 'Test Job';
            }
        };

        $job->broadcastProgress($user->id, 'test-job-id', 50, 'Processing', 'Item 5 of 10');

        Event::assertDispatched(JobProgress::class, function ($event) {
            return $event->progress === 50
                && $event->stage === 'Processing'
                && $event->currentItem === 'Item 5 of 10';
        });
    }

    public function test_broadcasts_job_completed_event(): void
    {
        Event::fake();
        $user = User::factory()->create();

        $job = new class {
            use BroadcastsJobProgress {
                broadcastStarted as public;
                broadcastProgress as public;
                broadcastCompleted as public;
                broadcastFailed as public;
            }

            public function getJobDisplayName(): string
            {
                return 'Test Job';
            }
        };

        $job->broadcastCompleted($user->id, 'test-job-id', ['pages' => 10]);

        Event::assertDispatched(JobCompleted::class, function ($event) {
            return $event->result === ['pages' => 10];
        });
    }

    public function test_broadcasts_job_failed_event(): void
    {
        Event::fake();
        $user = User::factory()->create();

        $job = new class {
            use BroadcastsJobProgress {
                broadcastStarted as public;
                broadcastProgress as public;
                broadcastCompleted as public;
                broadcastFailed as public;
            }

            public function getJobDisplayName(): string
            {
                return 'Test Job';
            }
        };

        $job->broadcastFailed($user->id, 'test-job-id', 'Connection error', 'Uploading');

        Event::assertDispatched(JobFailed::class, function ($event) {
            return $event->error === 'Connection error'
                && $event->stage === 'Uploading';
        });
    }
}
