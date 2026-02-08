<?php

namespace App\Jobs\Graph;

use App\Models\Neo4jRetryQueueItem;
use App\Services\GraphDatabaseService;
use App\Traits\BroadcastsJobProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Laudis\Neo4j\Exception\Neo4jException;
use Throwable;

class RetryNeo4jOperationJob implements ShouldQueue
{
    use BroadcastsJobProgress, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 5;

    /**
     * The number of seconds to wait before retrying the job (exponential backoff).
     */
    public array $backoff = [60, 300, 900, 3600, 7200]; // 1min, 5min, 15min, 1hr, 2hr

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public int $maxExceptions = 5;

    /**
     * The Neo4j retry queue item ID.
     */
    public ?int $queueItemId = null;

    protected ?int $userId = null;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $operationType,
        public array $payload,
        public ?string $entityType = null,
        public ?int $entityId = null,
        ?int $queueItemId = null,
        ?int $userId = null
    ) {
        $this->queueItemId = $queueItemId;
        $this->userId = $userId ?? auth()->id();
        $this->onQueue('neo4j-retry');
    }

    public function getJobDisplayName(): string
    {
        return 'Retrying Neo4j: ' . $this->operationType;
    }

    /**
     * Execute the job.
     */
    public function handle(GraphDatabaseService $graph): void
    {
        // Check if Neo4j is available before attempting retry
        if (! $graph->isAvailable()) {
            Log::warning('Neo4j is still unavailable, will retry later', [
                'operation_type' => $this->operationType,
                'attempt' => $this->attempts(),
                'max_attempts' => $this->tries,
            ]);

            throw new \RuntimeException('Neo4j is not available');
        }

        // Get or create queue item for tracking
        $queueItem = $this->getOrCreateQueueItem();

        try {
            Log::info('Retrying Neo4j operation', [
                'operation_type' => $this->operationType,
                'entity_type' => $this->entityType,
                'entity_id' => $this->entityId,
                'attempt' => $this->attempts(),
                'queue_item_id' => $queueItem->id,
            ]);

            // Execute the operation based on type
            $this->executeOperation($graph);

            // Mark as successful
            $queueItem->update([
                'status' => 'completed',
                'attempts' => $this->attempts(),
                'last_error' => null,
            ]);

            Log::info('Neo4j operation retry successful', [
                'operation_type' => $this->operationType,
                'entity_type' => $this->entityType,
                'entity_id' => $this->entityId,
                'attempt' => $this->attempts(),
            ]);

            if ($this->userId) {
                $this->broadcastCompleted($this->userId, 'neo4j_retry_' . $queueItem->id, [
                    'operation_type' => $this->operationType,
                    'attempt' => $this->attempts(),
                ]);
            }
        } catch (Neo4jException $e) {
            // Update queue item with error
            $queueItem->update([
                'status' => 'retrying',
                'attempts' => $this->attempts(),
                'last_error' => $e->getMessage(),
            ]);

            Log::warning('Neo4j operation retry failed, will retry again', [
                'operation_type' => $this->operationType,
                'attempt' => $this->attempts(),
                'max_attempts' => $this->tries,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } catch (Throwable $e) {
            // For non-Neo4j exceptions, also update and retry
            $queueItem->update([
                'status' => 'retrying',
                'attempts' => $this->attempts(),
                'last_error' => $e->getMessage(),
            ]);

            Log::error('Neo4j operation retry encountered unexpected error', [
                'operation_type' => $this->operationType,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Execute the Neo4j operation based on type.
     */
    protected function executeOperation(GraphDatabaseService $graph): void
    {
        switch ($this->operationType) {
            case 'query':
                $graph->run(
                    $this->payload['query'] ?? '',
                    $this->payload['params'] ?? []
                );
                break;

            case 'upsert_node':
                $graph->upsertNode(
                    $this->payload['label'] ?? '',
                    $this->payload['id'] ?? '',
                    $this->payload['properties'] ?? []
                );
                break;

            case 'create_relationship':
                $graph->createRelationship(
                    $this->payload['from_label'] ?? '',
                    $this->payload['from_id'] ?? '',
                    $this->payload['rel_type'] ?? '',
                    $this->payload['to_label'] ?? '',
                    $this->payload['to_id'] ?? '',
                    $this->payload['properties'] ?? []
                );
                break;

            case 'delete_node':
                $graph->deleteNode(
                    $this->payload['label'] ?? '',
                    $this->payload['id'] ?? ''
                );
                break;

            case 'batch_upsert':
                $graph->batchUpsertNodes(
                    $this->payload['label'] ?? '',
                    $this->payload['nodes'] ?? []
                );
                break;

            case 'store_decision':
                $graph->storeDecisionInGraph(
                    $this->payload['meta'] ?? [],
                    $this->payload['doc_id'] ?? ''
                );
                break;

            case 'transaction':
                $graph->transaction(function ($tsx) {
                    foreach ($this->payload['queries'] ?? [] as $queryData) {
                        $tsx->run(
                            $queryData['query'] ?? '',
                            $queryData['params'] ?? []
                        );
                    }
                });
                break;

            default:
                throw new \InvalidArgumentException("Unknown operation type: {$this->operationType}");
        }
    }

    /**
     * Get or create the queue item for tracking.
     */
    protected function getOrCreateQueueItem(): Neo4jRetryQueueItem
    {
        if ($this->queueItemId) {
            $item = Neo4jRetryQueueItem::find($this->queueItemId);
            if ($item) {
                return $item;
            }
        }

        // Create new queue item
        return Neo4jRetryQueueItem::create([
            'operation_type' => $this->operationType,
            'payload' => $this->payload,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'attempts' => 0,
            'max_attempts' => $this->tries,
            'status' => 'pending',
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('Neo4j operation permanently failed after all retries', [
            'operation_type' => $this->operationType,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
        ]);

        // Get or create queue item
        $queueItem = $this->getOrCreateQueueItem();

        // Update queue item as failed
        $queueItem->update([
            'status' => 'failed',
            'attempts' => $this->attempts(),
            'last_error' => $exception->getMessage(),
            'failed_at' => now(),
        ]);

        // Dispatch to dead letter queue for manual review
        Neo4jDeadLetterJob::dispatch(
            $queueItem->id,
            $this->operationType,
            $this->payload,
            $exception->getMessage()
        )->onQueue('neo4j-dead-letter');
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return $this->backoff;
    }
}
