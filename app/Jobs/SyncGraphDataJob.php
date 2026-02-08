<?php

namespace App\Jobs;

use App\Services\Graph\GraphRagOrchestrator;
use App\Traits\BroadcastsJobProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncGraphDataJob implements ShouldQueue
{
    use BroadcastsJobProgress, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600; // 1 hour max

    public int $tries = 2; // Retry once on failure

    protected ?int $userId = null;

    /**
     * Create a new job instance.
     *
     * @param  string  $syncType  One of: 'law', 'case', 'decision', 'textract', 'all'
     * @param  string|int|null  $id  Specific ID to sync (null for all)
     * @param  int|null  $userId  The user who initiated this job
     */
    public function __construct(
        public string $syncType,
        public string|int|null $id = null,
        ?int $userId = null
    ) {
        $this->userId = $userId ?? auth()->id();
    }

    public function getJobDisplayName(): string
    {
        return 'Graph Sync: ' . $this->syncType;
    }

    /**
     * Execute the job.
     */
    public function handle(GraphRagOrchestrator $graphRag): void
    {
        Log::info('SyncGraphDataJob started', [
            'sync_type' => $this->syncType,
            'id' => $this->id,
            'job_id' => $this->job?->getJobId(),
        ]);

        $broadcastJobId = 'graph_sync_' . $this->syncType . '_' . ($this->id ?? 'all');

        try {
            if ($this->userId) {
                $this->broadcastStarted($this->userId, $broadcastJobId, [
                    'sync_type' => $this->syncType,
                    'id' => $this->id,
                ]);
            }

            $result = match ($this->syncType) {
                'law' => $this->id
                    ? $this->syncSingle($graphRag, 'syncLaw', $this->id)
                    : $graphRag->syncAllLaws(),
                'case' => $this->id
                    ? $this->syncSingle($graphRag, 'syncCase', $this->id)
                    : $graphRag->syncAllCases(),
                'decision' => $this->id
                    ? $this->syncSingle($graphRag, 'syncCourtDecision', $this->id)
                    : $graphRag->syncAllCourtDecisions(),
                'textract' => $this->id
                    ? $this->syncSingle($graphRag, 'syncTextractJob', (int) $this->id)
                    : $graphRag->syncAllTextractJobs(),
                'all' => $this->syncAll($graphRag),
                default => throw new \InvalidArgumentException("Invalid sync type: {$this->syncType}"),
            };

            Log::info('SyncGraphDataJob completed', [
                'sync_type' => $this->syncType,
                'result' => $result,
            ]);

            if ($this->userId) {
                $this->broadcastCompleted($this->userId, $broadcastJobId, $result ?? []);
            }

        } catch (\Exception $e) {
            Log::error('SyncGraphDataJob failed', [
                'sync_type' => $this->syncType,
                'id' => $this->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($this->userId) {
                $this->broadcastFailed($this->userId, $broadcastJobId, $e->getMessage(), 'Graph Sync');
            }

            throw $e;
        }
    }

    /**
     * Sync a single entity
     */
    protected function syncSingle(GraphRagOrchestrator $graphRag, string $method, string|int $id): array
    {
        $graphRag->$method($id);

        return ['synced' => 1, 'errors' => 0];
    }

    /**
     * Sync all entity types
     */
    protected function syncAll(GraphRagOrchestrator $graphRag): array
    {
        $results = [];

        $results['laws'] = $graphRag->syncAllLaws();
        $results['cases'] = $graphRag->syncAllCases();
        $results['decisions'] = $graphRag->syncAllCourtDecisions();
        $results['textract'] = $graphRag->syncAllTextractJobs();

        return [
            'synced' => array_sum(array_column($results, 'synced')),
            'errors' => array_sum(array_column($results, 'errors')),
            'details' => $results,
        ];
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SyncGraphDataJob failed permanently', [
            'sync_type' => $this->syncType,
            'id' => $this->id,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Get the tags for the job.
     */
    public function tags(): array
    {
        return [
            'graph:sync',
            "graph:sync:{$this->syncType}",
            $this->id ? "id:{$this->id}" : 'batch',
        ];
    }

    /**
     * Helper: Dispatch sync with environment detection
     *
     * On localhost/dev: Run synchronously
     * On production: Dispatch to queue
     */
    public static function dispatchWithEnvDetection(string $syncType, string|int|null $id = null): mixed
    {
        $job = new self($syncType, $id);

        // Environment detection
        $isProduction = app()->environment('production');
        $isLocalhost = in_array(request()->ip(), ['127.0.0.1', '::1', 'localhost']);

        if ($isProduction && ! $isLocalhost) {
            // Production: Dispatch to queue
            Log::info('Dispatching SyncGraphDataJob to queue (production)', [
                'sync_type' => $syncType,
                'id' => $id,
            ]);

            return self::dispatch($syncType, $id);
        } else {
            // Localhost/dev: Run synchronously
            Log::info('Running SyncGraphDataJob synchronously (dev/localhost)', [
                'sync_type' => $syncType,
                'id' => $id,
            ]);

            $graphRag = app(GraphRagOrchestrator::class);
            $job->handle($graphRag);

            return ['mode' => 'sync', 'completed' => true];
        }
    }
}
