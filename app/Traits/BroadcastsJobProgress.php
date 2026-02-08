<?php

namespace App\Traits;

use App\Events\JobCompleted;
use App\Events\JobFailed;
use App\Events\JobProgress;
use App\Events\JobStarted;

trait BroadcastsJobProgress
{
    abstract public function getJobDisplayName(): string;

    protected function broadcastStarted(int $userId, string $jobId, array $metadata = []): void
    {
        event(new JobStarted(
            userId: $userId,
            jobId: $jobId,
            jobType: class_basename($this),
            jobName: $this->getJobDisplayName(),
            metadata: $metadata
        ));
    }

    protected function broadcastProgress(
        int $userId,
        string $jobId,
        int $progress,
        string $stage,
        ?string $currentItem = null,
        array $metadata = []
    ): void {
        event(new JobProgress(
            userId: $userId,
            jobId: $jobId,
            jobType: class_basename($this),
            jobName: $this->getJobDisplayName(),
            progress: $progress,
            stage: $stage,
            currentItem: $currentItem,
            metadata: $metadata
        ));
    }

    protected function broadcastCompleted(int $userId, string $jobId, array $result = [], array $metadata = []): void
    {
        event(new JobCompleted(
            userId: $userId,
            jobId: $jobId,
            jobType: class_basename($this),
            jobName: $this->getJobDisplayName(),
            result: $result,
            metadata: $metadata
        ));
    }

    protected function broadcastFailed(
        int $userId,
        string $jobId,
        string $error,
        string $stage,
        array $metadata = []
    ): void {
        event(new JobFailed(
            userId: $userId,
            jobId: $jobId,
            jobType: class_basename($this),
            jobName: $this->getJobDisplayName(),
            error: $error,
            stage: $stage,
            metadata: $metadata
        ));
    }
}
