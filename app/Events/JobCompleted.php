<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JobCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $shouldPersist = true;

    public function __construct(
        public int $userId,
        public string $jobId,
        public string $jobType,
        public string $jobName,
        public array $result = [],
        public array $metadata = []
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->userId.'.jobs'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'job.completed';
    }

    public function broadcastWith(): array
    {
        return [
            'job_id' => $this->jobId,
            'job_type' => $this->jobType,
            'job_name' => $this->jobName,
            'status' => 'completed',
            'progress' => 100,
            'stage' => 'Complete',
            'current_item' => null,
            'result' => $this->result,
            'metadata' => $this->metadata,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
