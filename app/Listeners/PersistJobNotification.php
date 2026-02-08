<?php

namespace App\Listeners;

use App\Events\JobCompleted;
use App\Events\JobFailed;
use App\Models\JobNotification;

class PersistJobNotification
{
    public function handle($event): void
    {
        if (!property_exists($event, 'shouldPersist') || !$event->shouldPersist) {
            return;
        }

        $data = [
            'user_id' => $event->userId,
            'job_id' => $event->jobId,
            'job_type' => $event->jobType,
            'job_name' => $event->jobName,
            'metadata' => $event->metadata,
        ];

        if ($event instanceof JobCompleted) {
            $data['status'] = 'completed';
            $data['result'] = $event->result;
        } elseif ($event instanceof JobFailed) {
            $data['status'] = 'failed';
            $data['error'] = $event->error;
            $data['stage'] = $event->stage;
        }

        JobNotification::create($data);
    }
}
