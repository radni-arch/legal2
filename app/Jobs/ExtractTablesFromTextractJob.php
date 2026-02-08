<?php

namespace App\Jobs;

use App\Models\TextractJob;
use App\Services\Textract\TableExtractorService;
use App\Traits\BroadcastsJobProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExtractTablesFromTextractJob implements ShouldQueue
{
    use BroadcastsJobProgress, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;

    public $timeout = 600; // 10 minutes

    protected string $jobId;

    protected ?int $userId = null;

    /**
     * Create a new job instance.
     */
    public function __construct(string $jobId, ?int $userId = null)
    {
        $this->jobId = $jobId;
        $this->userId = $userId ?? auth()->id();
        $this->onQueue('textract-tables');
    }

    public function getJobDisplayName(): string
    {
        return 'Extracting Tables';
    }

    /**
     * Execute the job.
     */
    public function handle(TableExtractorService $tableExtractor): void
    {
        $job = TextractJob::find($this->jobId);

        if (! $job) {
            Log::error('ExtractTablesFromTextractJob - Job not found', [
                'job_id' => $this->jobId,
            ]);

            return;
        }

        try {
            if ($this->userId) {
                $this->broadcastStarted($this->userId, $this->jobId, [
                    'file_name' => $job->file_name ?? null,
                ]);
            }

            Log::info('ExtractTablesFromTextractJob - Starting', [
                'job_id' => $this->jobId,
                'file_name' => $job->file_name,
            ]);

            // Extract tables
            $tables = $tableExtractor->extractTables($job);

            if (empty($tables)) {
                Log::info('ExtractTablesFromTextractJob - No tables found', [
                    'job_id' => $this->jobId,
                ]);

                if ($this->userId) {
                    $this->broadcastCompleted($this->userId, $this->jobId, [
                        'tables_extracted' => 0,
                    ]);
                }

                return;
            }

            // Store tables in job metadata
            $metadata = $job->metadata ?? [];
            $metadata['tables'] = $tables;
            $metadata['tables_extracted_at'] = now()->toIso8601String();
            $metadata['table_count'] = count($tables);

            $job->update(['metadata' => $metadata]);

            Log::info('ExtractTablesFromTextractJob - Completed', [
                'job_id' => $this->jobId,
                'tables_extracted' => count($tables),
            ]);

            if ($this->userId) {
                $this->broadcastCompleted($this->userId, $this->jobId, [
                    'tables_extracted' => count($tables),
                ]);
            }

        } catch (\Exception $e) {
            Log::error('ExtractTablesFromTextractJob - Failed', [
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
            ]);

            if ($this->userId) {
                $this->broadcastFailed($this->userId, $this->jobId, $e->getMessage(), 'Table Extraction');
            }

            if ($this->attempts() < $this->tries) {
                throw $e;
            }
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return ['textract', 'tables', 'job:'.$this->jobId];
    }
}
