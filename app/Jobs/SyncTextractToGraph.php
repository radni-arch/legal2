<?php

namespace App\Jobs;

use App\Events\TextractJobGraphSynced;
use App\Models\TextractJob;
use App\Models\User;
use App\Notifications\TextractPipelineNotification;
use App\Services\Graph\GraphRagOrchestrator;
use App\Traits\BroadcastsJobProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * SyncTextractToGraph
 *
 * Purpose: Queue job for async synchronization of TextractJob documents to Neo4j graph database.
 * This job creates graph nodes, extracts keywords, identifies citations, and establishes similarity relationships.
 *
 * Workflow:
 * 1. Loads TextractJob from database
 * 2. Validates that embeddings are ready (embedding_status = 'synced')
 * 3. Updates graph_sync_status to 'processing'
 * 4. Calls GraphRagOrchestrator to:
 *    - Create TextractDocument nodes in Neo4j
 *    - Link to CaseDocument if assigned
 *    - Extract and link keywords
 *    - Identify and create citation relationships to laws
 *    - Find similar documents and create SIMILAR_TO relationships
 *    - Auto-tag documents
 * 5. Updates graph_sync_status to 'synced' on success
 * 6. Handles errors with automatic retry (15 attempts)
 *
 * Key Features:
 * - Automatic retry with exponential backoff (2^attempt seconds)
 * - Status tracking in textract_jobs table
 * - Error logging with full context
 * - 5-minute timeout for graph operations
 * - Checks if Neo4j sync is enabled before processing
 *
 * Usage:
 *   SyncTextractToGraph::dispatch($textractJobId);
 */
class SyncTextractToGraph implements ShouldQueue
{
    use BroadcastsJobProgress, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 15;

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        // Exponential backoff: 2s, 4s, 8s
        return [2, 4, 8];
    }

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 300; // 5 minutes for graph operations

    protected ?int $userId = null;

    /**
     * Create a new job instance.
     *
     * @param  int  $textractJobId  The TextractJob ID to sync
     * @param  int|null  $userId  The user who initiated this job
     */
    public function __construct(
        public int $textractJobId,
        ?int $userId = null
    ) {
        $this->userId = $userId ?? auth()->id();
    }

    public function getJobDisplayName(): string
    {
        return 'Syncing to Graph';
    }

    /**
     * Execute the job.
     */
    public function handle(GraphRagOrchestrator $graphRag): void
    {
        Log::info('SyncTextractToGraph: Starting', [
            'textract_job_id' => $this->textractJobId,
            'attempt' => $this->attempts(),
        ]);

        // Check if Neo4j sync is enabled
        if (! config('neo4j.sync.enabled', false)) {
            Log::info('SyncTextractToGraph: Neo4j sync is disabled, skipping', [
                'textract_job_id' => $this->textractJobId,
            ]);

            return;
        }

        // Check if Neo4j is available before attempting sync
        $graphDb = app(\App\Services\GraphDatabaseService::class);
        if (! $graphDb->isAvailable()) {
            $healthStatus = $graphDb->getHealthStatus();

            // Release back to queue with exponential backoff (max 1 hour)
            $delay = (int) min(60 * pow(2, $this->attempts()), 3600);

            Log::warning('SyncTextractToGraph: Neo4j unavailable, releasing for retry', [
                'job_id' => $this->textractJobId,
                'health' => $healthStatus,
                'attempt' => $this->attempts(),
                'delay' => $delay,
            ]);

            // Update job to indicate Neo4j is unavailable with retry info
            $job = TextractJob::find($this->textractJobId);
            if ($job) {
                $job->update([
                    'graph_sync_status' => 'pending',
                    'error' => sprintf(
                        'Neo4j is not available. Will retry in %d seconds (attempt %d).',
                        $delay,
                        $this->attempts()
                    ),
                ]);
            }

            $this->release($delay);

            return;
        }

        // Load the job
        $job = TextractJob::find($this->textractJobId);

        if (! $job) {
            Log::error('SyncTextractToGraph: Job not found', [
                'textract_job_id' => $this->textractJobId,
            ]);

            return;
        }

        // Check if embeddings are ready
        if ($job->embedding_status !== 'synced') {
            Log::warning('SyncTextractToGraph: Embeddings not ready', [
                'textract_job_id' => $this->textractJobId,
                'embedding_status' => $job->embedding_status,
            ]);

            // Don't fail completely, embeddings might be generated later
            return;
        }

        // Check if job has succeeded
        if (! in_array($job->status, ['completed', 'succeeded'])) {
            Log::warning('SyncTextractToGraph: Job not in completed status', [
                'textract_job_id' => $this->textractJobId,
                'status' => $job->status,
            ]);

            return;
        }

        // Mark as processing before sync
        $job->update(['graph_sync_status' => 'processing']);
        $broadcastJobId = 'sync_graph_' . $this->textractJobId;

        try {
            if ($this->userId) {
                $this->broadcastStarted($this->userId, $broadcastJobId, [
                    'textract_job_id' => $this->textractJobId,
                ]);
            }

            // Sync to graph database
            $graphRag->syncTextractJob($this->textractJobId);

            Log::info('SyncTextractToGraph: Completed successfully', [
                'textract_job_id' => $this->textractJobId,
                'attempt' => $this->attempts(),
            ]);

            // Refresh job to get updated state and dispatch event for case completeness check
            $job->refresh();
            event(new TextractJobGraphSynced($job));

            Log::info('SyncTextractToGraph: Dispatched TextractJobGraphSynced event', [
                'textract_job_id' => $job->id,
                'case_id' => $job->case_id,
            ]);

            if ($this->userId) {
                $this->broadcastCompleted($this->userId, $broadcastJobId, [
                    'textract_job_id' => $this->textractJobId,
                ]);
            }

            // Send success notification if enabled
            $this->sendNotification($job, 'graph_synced');

        } catch (\Exception $e) {
            Log::error('SyncTextractToGraph: Failed', [
                'textract_job_id' => $this->textractJobId,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
                'tries' => $this->tries,
            ]);

            // Update job status to failed
            $job->update([
                'graph_sync_status' => 'failed',
                'error' => 'Graph sync failed: '.$e->getMessage(),
            ]);

            if ($this->userId) {
                $this->broadcastFailed($this->userId, $broadcastJobId, $e->getMessage(), 'Graph Sync');
            }

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $e): void
    {
        Log::error('SyncTextractToGraph: Permanently failed after retries', [
            'textract_job_id' => $this->textractJobId,
            'attempts' => $this->tries,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        // Mark the job as permanently failed
        $job = TextractJob::find($this->textractJobId);

        if ($job) {
            $errorMessage = sprintf(
                'Graph sync permanently failed after %d attempts: %s',
                $this->tries,
                $e->getMessage()
            );

            $job->update([
                'graph_sync_status' => 'failed',
                'error' => $errorMessage,
            ]);

            Log::info('SyncTextractToGraph: Marked job as permanently failed', [
                'textract_job_id' => $this->textractJobId,
            ]);

            // Send failure notification if enabled
            $this->sendNotification($job, 'failed', $errorMessage);
        }
    }

    /**
     * Send notification to user if notifications are enabled.
     */
    protected function sendNotification(TextractJob $job, string $event, ?string $message = null): void
    {
        if (! $this->userId) {
            return;
        }

        if (! config('textract.notifications.enabled', false)) {
            return;
        }

        $user = User::find($this->userId);
        if ($user) {
            $user->notify(new TextractPipelineNotification($job, $event, $message));
        }
    }

    /**
     * Get the tags for the job.
     */
    public function tags(): array
    {
        return [
            'textract',
            'graph',
            'neo4j',
            'textract_job:'.$this->textractJobId,
        ];
    }
}
