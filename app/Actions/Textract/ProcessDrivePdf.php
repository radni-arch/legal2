<?php

namespace App\Actions\Textract;

use App\Models\TextractJob;
use App\Pipelines\Textract\CheckExistingTextStep;
use App\Pipelines\Textract\CheckOcrQualityStep;
use App\Pipelines\Textract\CollectLinesStep;
use App\Pipelines\Textract\CreateMetadataStep;
use App\Pipelines\Textract\DownloadDriveFileStep;
use App\Pipelines\Textract\EnsureJobStep;
use App\Pipelines\Textract\LocalOcrRouteStep;
use App\Pipelines\Textract\PersistReconstructedStep;
use App\Pipelines\Textract\ReconstructPdfStep;
use App\Pipelines\Textract\SaveResultsStep;
use App\Pipelines\Textract\StartAnalysisStep;
use App\Pipelines\Textract\TesseractOcrStep;
use App\Pipelines\Textract\UploadInputToS3Step;
use App\Pipelines\Textract\UploadOutputStep;
use App\Pipelines\Textract\WaitAndFetchStep;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\AsJob;

/**
 * Action: ProcessDrivePdf
 * Purpose: Orchestrate the end-to-end pipeline for a single Google Drive PDF using a Pipeline.
 */
class ProcessDrivePdf
{
    use AsAction, AsJob;

    /**
     * Main orchestrator for a single file.
     *
     * @param  string  $driveFileId  The Google Drive file ID
     * @param  string  $driveFileName  The file name
     * @param  bool  $forceTextract  Force Textract OCR path regardless of local OCR availability
     */
    public function handle(string $driveFileId, string $driveFileName, bool $forceTextract = false): void
    {
        Log::info('ProcessDrivePdf (Action): start', compact('driveFileId', 'driveFileName', 'forceTextract'));

        $maxPipelineSeconds = (int) config('textract.max_pipeline_seconds', 1800);
        $payload = [
            'driveFileId' => $driveFileId,
            'driveFileName' => $driveFileName,
            'forceTextract' => $forceTextract,
            'pipeline_start' => time(),
            'pipeline_timeout' => $maxPipelineSeconds,
        ];

        try {
            /** @var array $payload */
            $payload = app(Pipeline::class)
                ->send($payload)
                ->through([
                    EnsureJobStep::class,
                    DownloadDriveFileStep::class,
                    CheckExistingTextStep::class,   // Check for existing text (skip Textract optimization)
                    LocalOcrRouteStep::class,       // Handle local OCR when skip_textract is true
                    UploadInputToS3Step::class,     // Skip when skip_textract
                    StartAnalysisStep::class,       // Skip when skip_textract
                    WaitAndFetchStep::class,        // Skip when skip_textract
                    SaveResultsStep::class,         // Skip when skip_textract
                    CollectLinesStep::class,        // Skip when skip_textract
                    CheckOcrQualityStep::class,     // OCR quality analysis
                    TesseractOcrStep::class,        // Tesseract OCR routing & comparison
                    CreateMetadataStep::class,      // Legal metadata extraction
                    ReconstructPdfStep::class,
                    UploadOutputStep::class,
                    PersistReconstructedStep::class,
                ])
                ->thenReturn();

            if (isset($payload['job']) && $payload['job'] instanceof TextractJob) {
                // Prefer finalText from TesseractOcrStep if available
                $fullText = $payload['finalText'] ?? '';

                // Fallback: extract from ocrDocument if finalText not set
                if ($fullText === '') {
                    $doc = $payload['ocrDocument'] ?? null;
                    if ($doc && isset($doc->pages)) {
                        foreach ($doc->pages as $page) {
                            if (! isset($page->lines)) {
                                continue;
                            }
                            foreach ($page->lines as $line) {
                                $txt = $line->text ?? '';
                                if ($txt !== '') {
                                    $fullText .= $txt."\n";
                                }
                            }
                            $fullText .= "\n";
                        }
                    }
                    $fullText = trim($fullText);
                }

                // Update job with extracted content and set initial sync statuses
                $payload['job']->update([
                    'status' => 'succeeded',
                    'extracted_content' => $fullText,
                    'embedding_status' => 'pending',
                    'graph_sync_status' => 'pending',
                ]);
            }

            Log::info('ProcessDrivePdf: succeeded', [
                'driveFileId' => $driveFileId,
                'outKey' => $payload['outKey'] ?? null,
                'content_length' => mb_strlen($fullText ?? ''),
            ]);
        } catch (\Throwable $e) {
            Log::error('ProcessDrivePdf: failed', [
                'driveFileId' => $driveFileId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            TextractJob::where('drive_file_id', $driveFileId)->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);
            throw $e; // Let job runner record failure if queued
        }
    }
}
