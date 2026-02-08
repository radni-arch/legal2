<?php

namespace App\Jobs\Graph;

use App\Models\Neo4jRetryQueueItem;
use App\Traits\BroadcastsJobProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class Neo4jDeadLetterJob implements ShouldQueue
{
    use BroadcastsJobProgress, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     * Dead letter jobs should not retry automatically.
     */
    public int $tries = 1;

    protected ?int $userId = null;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $queueItemId,
        public string $operationType,
        public array $payload,
        public string $error,
        ?int $userId = null
    ) {
        $this->userId = $userId ?? auth()->id();
        $this->onQueue('neo4j-dead-letter');
    }

    public function getJobDisplayName(): string
    {
        return 'Neo4j Dead Letter: ' . $this->operationType;
    }

    /**
     * Execute the job.
     *
     * This job simply logs the permanently failed operation to the dead letter queue.
     * Manual intervention will be required to retry or resolve these operations.
     */
    public function handle(): void
    {
        $queueItem = Neo4jRetryQueueItem::find($this->queueItemId);

        if (! $queueItem) {
            Log::error('Dead letter queue item not found', [
                'queue_item_id' => $this->queueItemId,
            ]);

            return;
        }

        Log::critical('Neo4j operation moved to dead letter queue', [
            'queue_item_id' => $this->queueItemId,
            'operation_type' => $this->operationType,
            'entity_type' => $queueItem->entity_type,
            'entity_id' => $queueItem->entity_id,
            'attempts' => $queueItem->attempts,
            'error' => $this->error,
            'payload' => $this->payload,
        ]);

        // Optionally, you can add additional notification logic here
        // For example, send email, Slack notification, or create an alert

        // Mark in database as dead letter
        $queueItem->update([
            'status' => 'dead_letter',
        ]);

        if ($this->userId) {
            $this->broadcastFailed(
                $this->userId,
                'neo4j_dead_letter_' . $this->queueItemId,
                $this->error,
                'Dead Letter',
                ['operation_type' => $this->operationType]
            );
        }
    }
}
