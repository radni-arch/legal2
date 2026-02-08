<?php

namespace App\Jobs;

use App\Jobs\SyncTextractToGraph;
use App\Models\TextractJob;
use App\Models\User;
use App\Notifications\TextractPipelineNotification;
use App\Services\TextractVectorStoreService;
use App\Traits\BroadcastsJobProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * RegenerateTextractEmbeddings
 *
 * Purpose: Queue job for async regeneration of embeddings for TextractJob documents.
 * This job chunks the content, generates OpenAI embeddings, and stores them in TextractDocuments.
 *
 * Workflow:
 * 1. Loads TextractJob from database
 * 2. Validates that content is available (manual or extracted)
 * 3. Updates embedding_status to 'processing'
 * 4. Calls TextractVectorStoreService to:
 *    - Chunk the content
 *    - Generate embeddings with retry logic
 *    - Store TextractDocument records with vectors
 * 5. Updates embedding_status to 'synced' on success
 * 6. Handles errors with automatic retry (3 attempts)
 *
 * Key Features:
 * - Automatic retry with exponential backoff (2^attempt seconds)
 * - Status tracking in textract_jobs table
 * - Error logging with full context
 * - Configurable chunking and embedding parameters
 * - 10-minute timeout for large documents
 *
 * Usage:
 *   RegenerateTextractEmbeddings::dispatch($textractJobId, ['chunk_size' => 1000]);
 */
class RegenerateTextractEmbeddings implements ShouldQueue
{
    use BroadcastsJobProgress, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

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
    public int $timeout = 600; // 10 minutes for large documents

    protected ?int $userId = null;

    /**
     * Create a new job instance.
     *
     * @param  int  $textractJobId  The TextractJob ID to process
     * @param  array  $options  Processing options (chunk_size, model, etc.)
     * @param  int|null  $userId  The user who initiated this job
     */
    public function __construct(
        public int $textractJobId,
        public array $options = [],
        ?int $userId = null
    ) {
        $this->userId = $userId ?? auth()->id();
    }

    public function getJobDisplayName(): string
    {
        return 'Regenerating Embeddings';
    }

    /**
     * Execute the job.
     */
    public function handle(TextractVectorStoreService $vectorStore): void
    {
        Log::info('RegenerateTextractEmbeddings: Starting', [
            'textract_job_id' => $this->textractJobId,
            'options' => $this->options,
            'attempt' => $this->attempts(),
        ]);

        // Load the job
        $job = TextractJob::find($this->textractJobId);

        if (! $job) {
            Log::error('RegenerateTextractEmbeddings: Job not found', [
                'textract_job_id' => $this->textractJobId,
            ]);

            return;
        }

        // Check if content is available
        $content = $job->effective_content;

        if (empty($content)) {
            Log::warning('RegenerateTextractEmbeddings: No content available', [
                'textract_job_id' => $this->textractJobId,
                'status' => $job->status,
                'manually_edited' => $job->manually_edited,
            ]);

            $job->update([
                'embedding_status' => 'failed',
                'error' => 'No content available for embedding generation',
            ]);

            return;
        }

        // Validate job is ready
        // Accept both 'completed' (set by ProcessTextractJob) and 'succeeded' (legacy) status values
        if (! in_array($job->status, ['completed', 'succeeded'])) {
            Log::warning('RegenerateTextractEmbeddings: Job not in completed/succeeded status', [
                'textract_job_id' => $this->textractJobId,
                'status' => $job->status,
            ]);

            // Don't fail completely, just skip for now
            return;
        }

        // Check if job needs review (skip auto-embedding unless explicitly requested)
        // The skipReviewCheck option allows manual embedding to proceed even when needsReview is set
        $skipReviewCheck = $this->options['skipReviewCheck'] ?? false;
        if (! $skipReviewCheck && $job->needsReview()) {
            Log::warning('RegenerateTextractEmbeddings: Job needs review, skipping auto-embedding', [
                'textract_job_id' => $this->textractJobId,
                'review_reasons' => $job->getReviewReasons(),
            ]);

            // Don't fail, just skip - user must review and clear the flag first
            return;
        }

        $broadcastJobId = 'regen_embed_' . $this->textractJobId;

        try {
            if ($this->userId) {
                $this->broadcastStarted($this->userId, $broadcastJobId, [
                    'textract_job_id' => $this->textractJobId,
                ]);
            }

            // Generate embeddings using the service
            $result = $vectorStore->ingestTextractJob($this->textractJobId, $this->options);

            Log::info('RegenerateTextractEmbeddings: Completed successfully', [
                'textract_job_id' => $this->textractJobId,
                'result' => $result,
                'attempt' => $this->attempts(),
            ]);

            // Chain graph sync - single source of truth for embeddings -> graph pipeline
            if (config('neo4j.sync.enabled', false)) {
                SyncTextractToGraph::dispatch($this->textractJobId)
                    ->delay(now()->addSeconds(5));

                Log::info('RegenerateTextractEmbeddings: Chained graph sync', [
                    'textract_job_id' => $this->textractJobId,
                ]);
            }

            if ($this->userId) {
                $this->broadcastCompleted($this->userId, $broadcastJobId, $result ?? []);
            }

            // Send success notification if enabled
            $this->sendNotification($job, 'embedding_complete');

        } catch (\Exception $e) {
            Log::error('RegenerateTextractEmbeddings: Failed', [
                'textract_job_id' => $this->textractJobId,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
                'tries' => $this->tries,
            ]);

            // Update job status to failed
            $job->update([
                'embedding_status' => 'failed',
                'error' => 'Embedding generation failed: '.$e->getMessage(),
            ]);

            if ($this->userId) {
                $this->broadcastFailed($this->userId, $broadcastJobId, $e->getMessage(), 'Embedding Regeneration');
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
        Log::error('RegenerateTextractEmbeddings: Permanently failed after retries', [
            'textract_job_id' => $this->textractJobId,
            'attempts' => $this->tries,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        // Mark the job as permanently failed
        $job = TextractJob::find($this->textractJobId);

        if ($job) {
            $errorMessage = sprintf(
                'Embedding generation permanently failed after %d attempts: %s',
                $this->tries,
                $e->getMessage()
            );

            $job->update([
                'embedding_status' => 'failed',
                'error' => $errorMessage,
            ]);

            Log::info('RegenerateTextractEmbeddings: Marked job as permanently failed', [
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
            'embeddings',
            'textract_job:'.$this->textractJobId,
        ];
    }
}
