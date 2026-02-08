<?php

namespace Tests\Unit\Events;

use App\Events\JobFailed;
use Tests\TestCase;

class JobFailedTest extends TestCase
{
    public function test_job_failed_event_broadcasts_correctly(): void
    {
        $event = new JobFailed(
            userId: 1,
            jobId: 'job-123',
            jobType: 'ProcessTextractJob',
            jobName: 'Processing document.pdf',
            error: 'Connection timeout',
            stage: 'Uploading to S3',
            metadata: []
        );

        $data = $event->broadcastWith();

        $this->assertEquals('failed', $data['status']);
        $this->assertEquals('Connection timeout', $data['error']);
        $this->assertEquals('Uploading to S3', $data['stage']);
    }

    public function test_job_failed_event_is_marked_for_persistence(): void
    {
        $event = new JobFailed(
            userId: 1,
            jobId: 'job-123',
            jobType: 'ProcessTextractJob',
            jobName: 'Processing document.pdf',
            error: 'Error',
            stage: 'Processing',
            metadata: []
        );

        $this->assertTrue($event->shouldPersist);
    }
}
