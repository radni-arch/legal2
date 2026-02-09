<?php

namespace App\Services\Ingest;

use App\Jobs\Ingest\ProcessIngestRunJob;
use App\Models\IngestRun;
use Illuminate\Support\Facades\Log;

/**
 * Canonical ingest orchestrator.
 *
 * Accepts a stored file path and dispatches the appropriate ingest pipeline.
 * Creates a tracked IngestRun record for observability and status tracking.
 */
class IngestOrchestrator
{
    /**
     * Ingest a stored file into the processing pipeline.
     *
     * Creates an IngestRun record and dispatches ProcessIngestRunJob
     * to handle OCR/embedding/analysis asynchronously.
     *
     * @param string $storedPath Path where file is stored on disk
     * @param string $disk Storage disk name (e.g. 'public')
     * @param string $originalFilename Original filename from upload
     * @param int $userId ID of the user who uploaded
     * @param string|null $caseId Optional case association
     * @param string $source Source of the file (uploader, drive, api)
     * @return IngestRun The created ingest run for tracking
     */
    public function ingest(
        string $storedPath,
        string $disk,
        string $originalFilename,
        int $userId,
        ?string $caseId = null,
        string $source = 'uploader',
    ): IngestRun {
        $ingestRun = IngestRun::create([
            'user_id' => $userId,
            'case_id' => $caseId,
            'source' => $source,
            'original_filename' => $originalFilename,
            'stored_path' => $storedPath,
            'stored_disk' => $disk,
            'status' => 'pending',
        ]);

        Log::info('IngestOrchestrator: Created IngestRun', [
            'ingest_run_id' => $ingestRun->id,
            'correlation_id' => $ingestRun->correlation_id,
            'file' => $originalFilename,
            'source' => $source,
            'case_id' => $caseId,
        ]);

        ProcessIngestRunJob::dispatch($ingestRun->id)->onQueue('ingest');

        return $ingestRun;
    }
}
