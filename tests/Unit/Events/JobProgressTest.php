<?php

namespace Tests\Unit\Events;

use App\Events\JobProgress;
use Illuminate\Broadcasting\PrivateChannel;
use Tests\TestCase;

class JobProgressTest extends TestCase
{
    public function test_job_progress_event_broadcasts_on_private_channel(): void
    {
        $event = new JobProgress(
            userId: 1,
            jobId: 'job-123',
            jobType: 'ProcessTextractJob',
            jobName: 'Processing document.pdf',
            progress: 50,
            stage: 'Extracting text',
            currentItem: 'Page 5 of 10',
            metadata: []
        );

        $channels = $event->broadcastOn();

        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertEquals('private-user.1.jobs', $channels[0]->name);
    }

    public function test_job_progress_event_includes_detailed_progress(): void
    {
        $event = new JobProgress(
            userId: 1,
            jobId: 'job-123',
            jobType: 'ProcessTextractJob',
            jobName: 'Processing document.pdf',
            progress: 50,
            stage: 'Extracting text',
            currentItem: 'Page 5 of 10',
            metadata: ['total_pages' => 10]
        );

        $data = $event->broadcastWith();

        $this->assertEquals(50, $data['progress']);
        $this->assertEquals('Extracting text', $data['stage']);
        $this->assertEquals('Page 5 of 10', $data['current_item']);
        $this->assertEquals('in_progress', $data['status']);
    }
}
