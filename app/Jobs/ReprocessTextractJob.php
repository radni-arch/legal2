<?php

namespace App\Jobs;

use App\Actions\Textract\ProcessDrivePdf as ProcessDrivePdfAction;
use App\Models\TextractJob;
use App\Traits\BroadcastsJobProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ReprocessTextractJob
 *
 * Purpose: Queue job for re-OCR operations that forces reprocessing of previously processed documents.
 * This job wraps ProcessDrivePdf and ensures clean reprocessing by managing existing TextractJob records.
 *
 * Workflow:
 * 1. Identifies existing TextractJob for the given drive_file_id
 * 2. Preserves case_id and creates audit trail metadata
 * 3. Marks existing job as 'superseded' and soft-deletes it
 * 4. Creates fresh TextractJob with preserved case_id and reprocessing metadata
 * 5. Delegates to ProcessDrivePdf which runs the full extraction pipeline
 * 6. Links new job to previous job via metadata for compliance/debugging
 *
 * Key Features:
 * - Maintains complete audit trail of reprocessing operations
 * - Preserves case associations across job versions
 * - Handles EnsureJobStep::firstOrCreate() correctly by removing old jobs
 * - Comprehensive error handling and logging at all stages
 * - Configurable retry logic (3 attempts, 60s backoff, 10min timeout)
 *
 * Usage:
 *   ReprocessTextractJob::dispatch($driveFileId, $driveFileName, true);
 */
class ReprocessTextractJob implements ShouldQueue
{
    use BroadcastsJobProgress, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 600;

    protected ?int $userId = null;

    /**
     * Create a new job instance.
     *
     * @param  string  $driveFileId  The Google Drive file ID to reprocess
     * @param  string  $driveFileName  The name of the file
     * @param  bool  $forceTextract  Whether to force reprocessing (default: true)
     * @param  int|null  $userId  The user who initiated this job
     */
    public function __construct(
        public string $driveFileId,
        public string $driveFileName,
        public bool $forceTextract = true,
        ?int $userId = null
    ) {
        $this->userId = $userId ?? auth()->id();
    }

    public function getJobDisplayName(): string
    {
        return 'Reprocessing: ' . $this->driveFileName;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $broadcastJobId = 'reprocess_' . $this->driveFileId;

        Log::info('ReprocessTextractJob: Starting re-OCR', [
            'driveFileId' => $this->driveFileId,
            'driveFileName' => $this->driveFileName,
            'forceTextract' => $this->forceTextract,
        ]);

        try {
            if ($this->userId) {
                $this->broadcastStarted($this->userId, $broadcastJobId, [
                    'file_name' => $this->driveFileName,
                ]);
            }

            $previousJobId = null;
            $previousJobMetadata = null;
            $caseId = null;

            // If forcing reprocessing, archive existing job and prepare for fresh processing
            // Use transaction to ensure atomicity and prevent race conditions
            if ($this->forceTextract) {
                DB::transaction(function () use (&$previousJobId, &$previousJobMetadata, &$caseId) {
                    $existingJob = TextractJob::where('drive_file_id', $this->driveFileId)
                        ->whereNotIn('status', ['superseded', 'failed'])
                        ->lockForUpdate() // Prevent race conditions
                        ->first();

                    if ($existingJob) {
                        Log::info('ReprocessTextractJob: Archiving existing job for reprocessing', [
                            'existing_job_id' => $existingJob->id,
                            'existing_status' => $existingJob->status,
                            'case_id' => $existingJob->case_id,
                        ]);

                        // Preserve case_id for the new job
                        $caseId = $existingJob->case_id;

                        // Validate that case_id exists before proceeding
                        if (! $caseId) {
                            throw new \RuntimeException(
                                "Cannot reprocess job {$existingJob->id}: no case_id associated. ".
                                'Please assign a case to this document before reprocessing.'
                            );
                        }

                        // Store reference to previous job for audit trail
                        $previousJobId = $existingJob->id;
                        $previousJobMetadata = [
                            'previous_job_id' => $existingJob->id,
                            'previous_status' => $existingJob->status,
                            'previous_s3_key' => $existingJob->s3_key,
                            'previous_case_id' => $existingJob->case_id,
                            'superseded_at' => now()->toIso8601String(),
                            'reason' => 'Manual re-OCR requested',
                        ];

                        // Mark as superseded and update with audit metadata
                        $existingJob->update([
                            'status' => 'superseded',
                            'metadata' => array_merge($existingJob->metadata ?? [], [
                                'superseded_at' => now()->toIso8601String(),
                                'superseded_by' => 'ReprocessTextractJob',
                                'superseded_reason' => 'Manual re-OCR requested',
                            ]),
                        ]);

                        // Delete to allow fresh job creation via EnsureJobStep::firstOrCreate()
                        // The superseded record is preserved in metadata for audit trail
                        $existingJob->delete();

                        Log::info('ReprocessTextractJob: Existing job archived and removed', [
                            'previous_job_id' => $previousJobId,
                        ]);
                    }
                });

                // Create a fresh TextractJob before processing to preserve case_id
                // EnsureJobStep will find this job via firstOrCreate()
                if ($caseId) {
                    $newJob = TextractJob::create([
                        'drive_file_id' => $this->driveFileId,
                        'drive_file_name' => $this->driveFileName,
                        'case_id' => $caseId,
                        'status' => 'queued',
                        'metadata' => $previousJobMetadata ? ['reprocessed_from' => $previousJobMetadata] : [],
                    ]);

                    Log::info('ReprocessTextractJob: Created fresh job with preserved case_id', [
                        'new_job_id' => $newJob->id,
                        'case_id' => $caseId,
                        'previous_job_id' => $previousJobId,
                    ]);
                }
            }

            // Delegate to the ProcessDrivePdf Action orchestrator
            // EnsureJobStep will find the fresh job we just created (or create one if no previous job existed)
            ProcessDrivePdfAction::run($this->driveFileId, $this->driveFileName);

            Log::info('ReprocessTextractJob: Completed re-OCR', [
                'driveFileId' => $this->driveFileId,
                'previous_job_id' => $previousJobId,
                'reprocessed' => $this->forceTextract && $previousJobId !== null,
            ]);

            if ($this->userId) {
                $this->broadcastCompleted($this->userId, $broadcastJobId, [
                    'file_name' => $this->driveFileName,
                    'reprocessed' => $this->forceTextract && $previousJobId !== null,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('ReprocessTextractJob: Exception in handle', [
                'driveFileId' => $this->driveFileId,
                'error' => $e->getMessage(),
            ]);

            if ($this->userId) {
                $this->broadcastFailed($this->userId, $broadcastJobId, $e->getMessage(), 'Reprocessing');
            }

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $e): void
    {
        Log::error('ReprocessTextractJob: Failed', [
            'driveFileId' => $this->driveFileId,
            'driveFileName' => $this->driveFileName,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        if ($this->userId) {
            $this->broadcastFailed(
                $this->userId,
                'reprocess_' . $this->driveFileId,
                $e->getMessage(),
                'Permanently Failed',
                ['file_name' => $this->driveFileName]
            );
        }

        // Mark the most recent non-superseded job as failed
        $failedJob = TextractJob::where('drive_file_id', $this->driveFileId)
            ->whereNotIn('status', ['superseded', 'succeeded', 'failed'])
            ->latest()
            ->first();

        if ($failedJob) {
            $failedJob->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
                'metadata' => array_merge($failedJob->metadata ?? [], [
                    'failed_at' => now()->toIso8601String(),
                    'failed_in' => 'ReprocessTextractJob',
                    'exception_class' => get_class($e),
                ]),
            ]);

            Log::info('ReprocessTextractJob: Marked job as failed', [
                'job_id' => $failedJob->id,
                'driveFileId' => $this->driveFileId,
            ]);
        } else {
            Log::warning('ReprocessTextractJob: No active job found to mark as failed', [
                'driveFileId' => $this->driveFileId,
            ]);
        }
    }
}
