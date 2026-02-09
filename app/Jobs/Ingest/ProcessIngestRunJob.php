<?php

namespace App\Jobs\Ingest;

use App\Events\CaseDocumentIngested;
use App\Models\CaseDocument;
use App\Models\IngestRun;
use App\Models\TextractJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Processes an IngestRun: determines file type, dispatches OCR or text ingest.
 *
 * For PDFs: Creates a TextractJob and dispatches ProcessTextractJob.
 * For text files: Creates a CaseDocument directly with content.
 * Updates IngestRun status throughout and fires CaseDocumentIngested on completion.
 */
class ProcessIngestRunJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public string $ingestRunId;

    public function __construct(string $ingestRunId)
    {
        $this->ingestRunId = $ingestRunId;
        $this->onQueue('ingest');
    }

    public function handle(): void
    {
        $ingestRun = IngestRun::find($this->ingestRunId);

        if (! $ingestRun) {
            Log::error('ProcessIngestRunJob: IngestRun not found', ['id' => $this->ingestRunId]);

            return;
        }

        $ingestRun->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);

        try {
            $fileType = $this->determineFileType($ingestRun->original_filename, $ingestRun->stored_path);

            if ($fileType === 'pdf') {
                $this->processPdf($ingestRun);
            } else {
                $this->processText($ingestRun);
            }
        } catch (\Exception $e) {
            Log::error('ProcessIngestRunJob: Failed', [
                'ingest_run_id' => $ingestRun->id,
                'error' => $e->getMessage(),
            ]);

            $ingestRun->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            if ($this->attempts() < $this->tries) {
                throw $e;
            }
        }
    }

    /**
     * Determine file type from filename and path.
     */
    protected function determineFileType(string $filename, string $storedPath): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($extension, ['pdf'])) {
            return 'pdf';
        }

        // Check mime type from stored file if extension is ambiguous
        $mimeExtensions = ['txt', 'text', 'csv', 'md', 'json', 'xml', 'html', 'htm'];
        if (in_array($extension, $mimeExtensions)) {
            return 'text';
        }

        // Default to text for unknown types (safer than assuming PDF)
        return 'text';
    }

    /**
     * Process a PDF file through the Textract OCR pipeline.
     */
    protected function processPdf(IngestRun $ingestRun): void
    {
        $ingestRun->update(['status' => 'ocr']);

        // Create a TextractJob for OCR processing
        $textractJob = TextractJob::create([
            'drive_file_id' => $ingestRun->id, // Use ingest run ID as file reference
            'drive_file_name' => $ingestRun->original_filename,
            'case_id' => $ingestRun->case_id,
            's3_key' => $ingestRun->stored_path,
            'status' => 'pending',
            'metadata' => [
                'ingest_run_id' => $ingestRun->id,
                'correlation_id' => $ingestRun->correlation_id,
                'source' => $ingestRun->source,
            ],
        ]);

        // Dispatch the Textract processing job
        \App\Jobs\ProcessTextractJob::dispatch(
            $textractJob->id,
            null,
            $ingestRun->user_id
        )->onQueue('textract');

        Log::info('ProcessIngestRunJob: Dispatched Textract pipeline for PDF', [
            'ingest_run_id' => $ingestRun->id,
            'textract_job_id' => $textractJob->id,
            'correlation_id' => $ingestRun->correlation_id,
        ]);
    }

    /**
     * Process a text file by creating a CaseDocument directly.
     */
    protected function processText(IngestRun $ingestRun): void
    {
        $ingestRun->update(['status' => 'embedding']);

        // Read the file content
        $content = Storage::disk($ingestRun->stored_disk)->get($ingestRun->stored_path);

        if (empty($content)) {
            $ingestRun->update([
                'status' => 'failed',
                'error_message' => 'File content is empty',
                'completed_at' => now(),
            ]);

            return;
        }

        // Create a CaseDocument if we have a case_id
        if ($ingestRun->case_id) {
            $caseDocument = CaseDocument::create([
                'case_id' => $ingestRun->case_id,
                'title' => $ingestRun->original_filename,
                'content' => $content,
                'source' => 'upload',
                'metadata' => [
                    'ingest_run_id' => $ingestRun->id,
                    'correlation_id' => $ingestRun->correlation_id,
                    'stored_path' => $ingestRun->stored_path,
                    'stored_disk' => $ingestRun->stored_disk,
                ],
            ]);

            // Fire CaseDocumentIngested event
            CaseDocumentIngested::dispatch($caseDocument, $ingestRun->case_id);

            Log::info('ProcessIngestRunJob: Created CaseDocument and fired event', [
                'ingest_run_id' => $ingestRun->id,
                'case_document_id' => $caseDocument->id,
                'case_id' => $ingestRun->case_id,
            ]);
        }

        $ingestRun->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    /**
     * Handle permanent job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessIngestRunJob: Permanently failed', [
            'ingest_run_id' => $this->ingestRunId,
            'error' => $exception->getMessage(),
        ]);

        $ingestRun = IngestRun::find($this->ingestRunId);
        if ($ingestRun) {
            $ingestRun->update([
                'status' => 'failed',
                'error_message' => 'Permanently failed: ' . $exception->getMessage(),
                'completed_at' => now(),
            ]);
        }
    }

    /**
     * Get job tags for monitoring.
     */
    public function tags(): array
    {
        return ['ingest', 'ingest_run:' . $this->ingestRunId];
    }
}
