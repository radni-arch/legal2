<?php

namespace Tests\Unit\Events;

use App\Events\JobStarted;
use Illuminate\Broadcasting\PrivateChannel;
use Tests\TestCase;

class JobStartedTest extends TestCase
{
    public function test_job_started_event_broadcasts_on_private_channel(): void
    {
        $event = new JobStarted(
            userId: 1,
            jobId: 'job-123',
            jobType: 'ProcessTextractJob',
            jobName: 'Processing document.pdf',
            metadata: ['file_name' => 'document.pdf']
        );

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertEquals('private-user.1.jobs', $channels[0]->name);
    }

    public function test_job_started_event_has_correct_broadcast_data(): void
    {
        $event = new JobStarted(
            userId: 1,
            jobId: 'job-123',
            jobType: 'ProcessTextractJob',
            jobName: 'Processing document.pdf',
            metadata: ['file_name' => 'document.pdf']
        );

        $data = $event->broadcastWith();

        $this->assertEquals('job-123', $data['job_id']);
        $this->assertEquals('ProcessTextractJob', $data['job_type']);
        $this->assertEquals('Processing document.pdf', $data['job_name']);
        $this->assertEquals('started', $data['status']);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('metadata', $data);
    }
}
