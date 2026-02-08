<?php

namespace Tests\Unit\Events;

use App\Events\JobCompleted;
use Tests\TestCase;

class JobCompletedTest extends TestCase
{
    public function test_job_completed_event_broadcasts_correctly(): void
    {
        $event = new JobCompleted(
            userId: 1,
            jobId: 'job-123',
            jobType: 'ProcessTextractJob',
            jobName: 'Processing document.pdf',
            result: ['pages_processed' => 10, 'tables_found' => 3],
            metadata: []
        );

        $data = $event->broadcastWith();

        $this->assertEquals('completed', $data['status']);
        $this->assertEquals(100, $data['progress']);
        $this->assertEquals('Complete', $data['stage']);
        $this->assertArrayHasKey('result', $data);
    }

    public function test_job_completed_event_is_marked_for_persistence(): void
    {
        $event = new JobCompleted(
            userId: 1,
            jobId: 'job-123',
            jobType: 'ProcessTextractJob',
            jobName: 'Processing document.pdf',
            result: [],
            metadata: []
        );

        $this->assertTrue($event->shouldPersist);
    }
}
