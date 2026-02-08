<?php

namespace App\Jobs;

use App\Actions\Textract\ProcessDrivePdf;
use App\Models\TextractBatch;
use App\Models\TextractJob;
use App\Models\User;
use App\Notifications\TextractPipelineNotification;
use App\Traits\BroadcastsJobProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessTextractJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, BroadcastsJobProgress;

    public $tries = 3;

    public $backoff = [60, 300, 900]; // 1min, 5min, 15min

    public $timeout = 1800; // 30 minutes

    protected string $jobId;

    protected ?string $batchId;

    protected ?int $userId = null;

    protected ?string $fileName;

    /**
     * Create a new job instance.
     */
    public function __construct(string $jobId, ?string $batchId = null, ?int $userId = null)
    {
        $this->jobId = $jobId;
        $this->batchId = $batchId;
        $this->userId = $userId ?? auth()->id();

        // Set queue based on priority
        $job = TextractJob::find($jobId);
        if ($job) {
            $this->onQueue($job->queue_name ?? 'textract');
            $this->fileName = $job->drive_file_name;
        }
    }

    public function getJobDisplayName(): string
    {
        return 'Processing: '.($this->fileName ?? 'Document');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $startTime = microtime(true);

        $job = TextractJob::find($this->jobId);

        if (! $job) {
            Log::error('ProcessTextractJob - Job not found', ['job_id' => $this->jobId]);

            return;
        }

        try {
            // Broadcast start
            if ($this->userId) {
                $this->broadcastStarted($this->userId, $this->jobId, [
                    'file_name' => $job->drive_file_name,
                    'batch_id' => $this->batchId,
                ]);
            }

            // Mark as processing
            $job->update([
                'status' => 'processing',
                'processing_started_at' => now(),
                'worker_id' => getmypid(),
                'retry_count' => $this->attempts() - 1,
            ]);

            Log::info('ProcessTextractJob - Starting', [
                'job_id' => $this->jobId,
                'drive_file_id' => $job->drive_file_id,
                'drive_file_name' => $job->drive_file_name,
                'attempt' => $this->attempts(),
                'worker_id' => getmypid(),
            ]);

            // Broadcast progress: Processing
            if ($this->userId) {
                $this->broadcastProgress($this->userId, $this->jobId, 30, 'Processing', 'Running Textract OCR');
            }

            // Execute the ProcessDrivePdf pipeline via container to allow testing/mocking
            app(ProcessDrivePdf::class)->handle(
                $job->drive_file_id,
                $job->drive_file_name,
                true
            );

            // Broadcast progress: Finalizing
            if ($this->userId) {
                $this->broadcastProgress($this->userId, $this->jobId, 80, 'Finalizing', 'Storing results');
            }

            // Reload job to get updated fields from ProcessDrivePdf
            $job->refresh();

            // Store S3 keys in metadata for later access (table extraction, etc.)
            // These keys follow a predictable pattern based on configured prefixes
            $jsonPrefix = trim(env('S3_JSON_PREFIX', 'textract/json'), '/');
            $inputPrefix = trim(env('S3_INPUT_PREFIX', 'textract/input'), '/');
            $outputPrefix = trim(env('S3_OUTPUT_PREFIX', 'textract/output'), '/');

            $metadata = $job->metadata ?? [];
            $metadata['s3_json_key'] = $jsonPrefix.'/'.$job->drive_file_id.'.json';
            $metadata['s3_input_key'] = $inputPrefix.'/'.$job->drive_file_id.'.pdf';
            $metadata['s3_output_key'] = $outputPrefix.'/'.$job->drive_file_id.'.pdf';
            $metadata['processed_at'] = now()->toIso8601String();

            // Calculate performance metrics
            $duration = microtime(true) - $startTime;

            $metrics = [
                'duration_seconds' => round($duration, 2),
                'pages_processed' => $metadata['page_count'] ?? 0,
                'blocks_extracted' => $metadata['block_count'] ?? 0,
                'tables_extracted' => count($metadata['tables'] ?? []),
                'worker_id' => getmypid(),
                'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                'completed_at' => now()->toIso8601String(),
            ];

            $job->update([
                'status' => 'completed',
                'metadata' => $metadata,
                'performance_metrics' => $metrics,
            ]);

            // Update batch if exists
            if ($this->batchId) {
                $batch = TextractBatch::find($this->batchId);
                if ($batch) {
                    $batch->incrementProcessed(false);
                }
            }

            Log::info('ProcessTextractJob - Completed', [
                'job_id' => $this->jobId,
                'metrics' => $metrics,
            ]);

            // Broadcast completion
            if ($this->userId) {
                $this->broadcastCompleted($this->userId, $this->jobId, [
                    'pages_processed' => $metrics['pages_processed'],
                    'tables_extracted' => $metrics['tables_extracted'],
                    'duration_seconds' => $metrics['duration_seconds'],
                ], ['file_name' => $job->drive_file_name]);
            }

            // Send completion notification if enabled
            $this->sendNotification($job, 'completed');

            // Dispatch follow-up jobs
            $this->dispatchFollowUpJobs($job);

        } catch (\Exception $e) {
            Log::error('ProcessTextractJob - Failed', [
                'job_id' => $this->jobId,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update job status
            $job->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);

            // Broadcast failure
            if ($this->userId) {
                $this->broadcastFailed(
                    $this->userId,
                    $this->jobId,
                    $e->getMessage(),
                    'Processing',
                    ['file_name' => $job->drive_file_name]
                );
            }

            // Update batch if exists
            if ($this->batchId) {
                $batch = TextractBatch::find($this->batchId);
                if ($batch) {
                    $batch->incrementProcessed(true);
                }
            }

            // Re-throw if we should retry
            if ($this->attempts() < $this->tries) {
                throw $e;
            }
        }
    }

    /**
     * Dispatch follow-up jobs (tables, embeddings)
     */
    protected function dispatchFollowUpJobs(TextractJob $job): void
    {
        // Dispatch table extraction if enabled
        if (config('distributed-processing.textract.auto_extract_tables', true)) {
            ExtractTablesFromTextractJob::dispatch($job->id)
                ->onQueue('textract-tables')
                ->delay(now()->addSeconds(10));
        }

        // Dispatch embedding generation if enabled
        // ADR-001: Use RegenerateTextractEmbeddings (System B) as canonical embedding job
        if (config('distributed-processing.textract.auto_generate_embeddings', true)) {
            RegenerateTextractEmbeddings::dispatch($job->id)
                ->delay(now()->addSeconds(30));
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessTextractJob - Permanently failed', [
            'job_id' => $this->jobId,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
        ]);

        $job = TextractJob::find($this->jobId);
        if ($job) {
            $errorMessage = 'Failed after '.$this->tries.' attempts: '.$exception->getMessage();

            $job->update([
                'status' => 'failed',
                'error' => $errorMessage,
            ]);

            // Broadcast final failure
            if ($this->userId) {
                $this->broadcastFailed(
                    $this->userId,
                    $this->jobId,
                    $errorMessage,
                    'Permanently Failed',
                    ['file_name' => $job->drive_file_name]
                );
            }

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
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return ['textract', 'job:'.$this->jobId, 'batch:'.($this->batchId ?? 'none')];
    }
}
