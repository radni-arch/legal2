<?php

namespace App\Console\Commands;

use App\Models\CaseDocumentUpload;
use App\Models\LegalCase;
use App\Services\CaseIngestPipeline;
use App\Services\OcrService;
use App\Services\TextractService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * CasesIngest Command
 *
 * Batch ingest case documents from a directory.
 * Supports both OCR (via Textract) and direct text extraction.
 *
 * Usage:
 *   # Normal ingestion
 *   php artisan cases:ingest --path=/path/to/pdfs --case=<case-id>
 *   php artisan cases:ingest --path=/path/to/pdfs --case=<case-id> --chunk=1200 --overlap=150
 *
 *   # Force Textract OCR
 *   php artisan cases:ingest --path=/path/to/pdfs --case=<case-id> --ocr
 *
 *   # Batch reprocess with Textract (from stdin)
 *   echo -e "doc-123\ndoc-456" | php artisan cases:ingest --batch-reprocess -v
 *   cat doc_ids.txt | php artisan cases:ingest --batch-reprocess --force-reprocess -v
 */
class CasesIngest extends Command
{
    protected $signature = 'cases:ingest
                            {--path= : Path to directory containing PDF files}
                            {--case= : ULID of the legal case to associate documents with}
                            {--chunk=1200 : Chunk size for text splitting}
                            {--overlap=150 : Overlap between chunks}
                            {--ocr : Force OCR via AWS Textract (default: try text extraction first)}
                            {--local-ocr : Use local OCR (tesseract) instead of Textract}
                            {--skip-existing : Skip files that already exist in case}
                            {--dry-run : Show what would be processed without actually processing}
                            {--force-reprocess : Force reprocess with Textract, skipping quality checks}
                            {--batch-reprocess : Reprocess multiple doc_ids from stdin (one per line)}';

    protected $description = 'Batch ingest case documents from a directory';

    protected CaseIngestPipeline $pipeline;

    protected OcrService $ocrService;

    protected TextractService $textractService;

    protected array $stats = [
        'total' => 0,
        'processed' => 0,
        'skipped' => 0,
        'failed' => 0,
        'needs_review' => 0,
    ];

    public function handle(
        CaseIngestPipeline $pipeline,
        OcrService $ocrService,
        TextractService $textractService
    ): int {
        $this->pipeline = $pipeline;
        $this->ocrService = $ocrService;
        $this->textractService = $textractService;

        // Check if batch reprocess mode
        if ($this->option('batch-reprocess')) {
            return $this->handleBatchReprocess();
        }

        // Validate inputs for normal file ingestion
        $path = $this->option('path');
        $caseId = $this->option('case');

        if (! $path) {
            $this->error('--path is required');

            return 1;
        }

        if (! $caseId) {
            $this->error('--case is required');

            return 1;
        }

        if (! is_dir($path)) {
            $this->error("Directory not found: {$path}");

            return 1;
        }

        // Verify case exists
        $case = LegalCase::find($caseId);
        if (! $case) {
            $this->error("Case not found: {$caseId}");

            return 1;
        }

        $this->info("Ingesting documents for case: {$case->case_number} ({$case->title})");
        $this->info("Source directory: {$path}");
        $this->newLine();

        // Find all PDF files
        $files = $this->findPdfFiles($path);
        $this->stats['total'] = count($files);

        if (empty($files)) {
            $this->warn('No PDF files found in directory');

            return 0;
        }

        $this->info("Found {$this->stats['total']} PDF file(s)");
        $this->newLine();

        // Process each file
        $progressBar = $this->output->createProgressBar($this->stats['total']);
        $progressBar->start();

        foreach ($files as $filePath) {
            $this->processFile($filePath, $case);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Print summary
        $this->printSummary();

        return 0;
    }

    /**
     * Handle batch reprocessing of documents from stdin.
     *
     * Reads doc_ids from stdin (one per line) and reprocesses each with Textract.
     */
    protected function handleBatchReprocess(): int
    {
        $this->info('Batch Reprocess Mode');
        $this->info('Reading doc_ids from stdin (one per line)...');
        $this->info('Press Ctrl+D when done, or pipe from a file.');
        $this->newLine();

        // Read doc_ids from stdin
        $docIds = [];
        $stdin = fopen('php://stdin', 'r');

        while (($line = fgets($stdin)) !== false) {
            $docId = trim($line);
            if ($docId !== '') {
                $docIds[] = $docId;
            }
        }

        fclose($stdin);

        if (empty($docIds)) {
            $this->warn('No doc_ids provided');

            return 0;
        }

        $this->stats['total'] = count($docIds);
        $this->info("Found {$this->stats['total']} doc_id(s) to reprocess");
        $this->newLine();

        // Process each doc_id with progress bar
        $progressBar = $this->output->createProgressBar($this->stats['total']);
        $progressBar->start();

        foreach ($docIds as $docId) {
            $this->reprocessDocument($docId);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Print summary
        $this->printSummary();

        return 0;
    }

    /**
     * Reprocess a single document by doc_id.
     */
    protected function reprocessDocument(string $docId): void
    {
        try {
            // Find the document upload record
            $upload = CaseDocumentUpload::where('doc_id', $docId)->first();

            if (! $upload) {
                $this->stats['failed']++;
                if ($this->option('verbose')) {
                    $this->newLine();
                    $this->error("Document not found: {$docId}");
                }

                return;
            }

            if ($this->option('dry-run')) {
                $this->stats['processed']++;
                if ($this->option('verbose')) {
                    $this->newLine();
                    $this->info("Would reprocess: {$docId} ({$upload->original_filename})");
                }

                return;
            }

            // Get the file path
            $localAbs = Storage::disk($upload->disk)->path($upload->local_path);

            if (! file_exists($localAbs)) {
                $this->stats['failed']++;
                if ($this->option('verbose')) {
                    $this->newLine();
                    $this->error("File not found: {$docId} - {$localAbs}");
                }

                return;
            }

            // Force Textract reprocessing
            $result = $this->extractTextWithTextract($localAbs, $docId, $upload);
            $text = $result['text'];
            $blocks = $result['blocks'];

            if (trim($text) === '') {
                $this->stats['failed']++;
                if ($this->option('verbose')) {
                    $this->newLine();
                    $this->error("Failed to extract text: {$docId}");
                }
                $upload->update(['status' => 'failed', 'error' => 'No text extracted']);

                return;
            }

            // Re-ingest via pipeline
            $case = LegalCase::find($upload->case_id);
            if (! $case) {
                $this->stats['failed']++;
                if ($this->option('verbose')) {
                    $this->newLine();
                    $this->error("Case not found for doc: {$docId}");
                }

                return;
            }

            $pipelineResult = $this->pipeline->ingest(
                caseId: $case->id,
                docId: $docId,
                rawText: $text,
                ocrBlocks: $blocks,
                options: [
                    'chunk_size' => (int) $this->option('chunk'),
                    'overlap' => (int) $this->option('overlap'),
                    'upload_id' => $upload->id,
                    'language' => 'hr',
                    'metadata' => [
                        'reprocessed' => true,
                        'reprocessed_at' => now()->toIso8601String(),
                        'force_reprocess' => $this->option('force-reprocess'),
                    ],
                ]
            );

            if ($pipelineResult['status'] === 'completed') {
                $this->stats['processed']++;
                if ($pipelineResult['needs_review']) {
                    $this->stats['needs_review']++;
                }
                $upload->update(['status' => 'completed']);

                if ($this->option('verbose')) {
                    $this->newLine();
                    $this->info("✓ Reprocessed: {$docId} ({$pipelineResult['chunk_count']} chunks)");
                    if ($pipelineResult['needs_review']) {
                        $this->warn('  ⚠ Needs review (low OCR quality)');
                    }
                }
            } else {
                $this->stats['failed']++;
                $upload->update(['status' => 'failed', 'error' => $pipelineResult['error'] ?? 'Unknown error']);

                if ($this->option('verbose')) {
                    $this->newLine();
                    $this->error("✗ Failed: {$docId}");
                }
            }

        } catch (\Throwable $e) {
            $this->stats['failed']++;
            if ($this->option('verbose')) {
                $this->newLine();
                $this->error("Exception reprocessing {$docId}: ".$e->getMessage());
            }
        }
    }

    protected function findPdfFiles(string $path): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'pdf') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    protected function processFile(string $filePath, LegalCase $case): void
    {
        $fileName = basename($filePath);
        $sha256 = hash_file('sha256', $filePath);

        // Check if already exists
        if ($this->option('skip-existing')) {
            $exists = CaseDocumentUpload::where('case_id', $case->id)
                ->where('sha256', $sha256)
                ->exists();

            if ($exists) {
                $this->stats['skipped']++;
                if ($this->option('verbose')) {
                    $this->newLine();
                    $this->warn("Skipped (already exists): {$fileName}");
                }

                return;
            }
        }

        if ($this->option('dry-run')) {
            $this->stats['processed']++;
            if ($this->option('verbose')) {
                $this->newLine();
                $this->info("Would process: {$fileName}");
            }

            return;
        }

        try {
            // Step 1: Store the file
            $docId = 'doc-'.Str::ulid();
            $localRel = "cases/{$case->id}/{$docId}/".$fileName;
            Storage::disk('local')->put($localRel, file_get_contents($filePath));
            $localAbs = Storage::disk('local')->path($localRel);

            // Step 2: Create upload record
            $upload = CaseDocumentUpload::create([
                'id' => (string) Str::ulid(),
                'case_id' => $case->id,
                'doc_id' => $docId,
                'disk' => 'local',
                'local_path' => $localRel,
                'original_filename' => $fileName,
                'mime_type' => 'application/pdf',
                'file_size' => filesize($filePath),
                'sha256' => $sha256,
                'status' => 'stored',
                'uploaded_at' => now(),
            ]);

            // Step 3: Extract text
            $text = '';
            $blocks = [];

            if ($this->option('ocr')) {
                // Force Textract OCR
                $result = $this->extractTextWithTextract($localAbs, $docId, $upload);
                $text = $result['text'];
                $blocks = $result['blocks'];
            } elseif ($this->option('local-ocr')) {
                // Local OCR
                $text = $this->extractTextLocal($localAbs);
            } else {
                // Try text extraction first
                $text = $this->extractTextLocal($localAbs);
            }

            if (trim($text) === '') {
                $this->stats['failed']++;
                if ($this->option('verbose')) {
                    $this->newLine();
                    $this->error("Failed to extract text: {$fileName}");
                }
                $upload->update(['status' => 'failed', 'error' => 'No text extracted']);

                return;
            }

            // Step 4: Ingest via pipeline
            $result = $this->pipeline->ingest(
                caseId: $case->id,
                docId: $docId,
                rawText: $text,
                ocrBlocks: $blocks,
                options: [
                    'chunk_size' => (int) $this->option('chunk'),
                    'overlap' => (int) $this->option('overlap'),
                    'upload_id' => $upload->id,
                    'language' => 'hr',
                    'metadata' => [
                        'original_path' => $filePath,
                        'cli_ingested' => true,
                        'ingested_at' => now()->toIso8601String(),
                    ],
                ]
            );

            if ($result['status'] === 'completed') {
                $this->stats['processed']++;
                if ($result['needs_review']) {
                    $this->stats['needs_review']++;
                }
                $upload->update(['status' => 'completed']);

                if ($this->option('verbose')) {
                    $this->newLine();
                    $this->info("✓ Processed: {$fileName} ({$result['chunk_count']} chunks)");
                    if ($result['needs_review']) {
                        $this->warn('  ⚠ Needs review (low OCR quality)');
                    }
                }
            } else {
                $this->stats['failed']++;
                $upload->update(['status' => 'failed', 'error' => $result['error'] ?? 'Unknown error']);

                if ($this->option('verbose')) {
                    $this->newLine();
                    $this->error("✗ Failed: {$fileName}");
                }
            }

        } catch (\Throwable $e) {
            $this->stats['failed']++;
            if ($this->option('verbose')) {
                $this->newLine();
                $this->error("Exception processing {$fileName}: ".$e->getMessage());
            }
        }
    }

    protected function extractTextLocal(string $pdfPath): string
    {
        try {
            return $this->ocrService->extractTextFromPdf($pdfPath);
        } catch (\Throwable $e) {
            if ($this->option('verbose')) {
                $this->warn('OCR extraction failed: '.$e->getMessage());
            }

            return '';
        }
    }

    /**
     * Extract text using AWS Textract OCR.
     *
     * Uploads PDF to S3, runs Textract analysis, fetches blocks, and saves results.
     * Follows S3 key naming conventions from PersistReconstructedStep.
     *
     * @param  string  $pdfPath  Absolute path to the PDF file
     * @param  string  $docId  Document ID (e.g., 'doc-{ulid}')
     * @param  CaseDocumentUpload  $upload  Upload record to update with metadata
     * @return array{text: string, blocks: array} Extracted text and Textract blocks
     */
    protected function extractTextWithTextract(string $pdfPath, string $docId, CaseDocumentUpload $upload): array
    {
        try {
            $startTime = microtime(true);

            if ($this->option('verbose')) {
                $this->newLine();
                $this->info('  → Uploading to S3...');
            }

            // Step 1: Upload to S3 (using consistent S3_INPUT_PREFIX from env)
            $inputPrefix = trim((string) env('S3_INPUT_PREFIX', 'textract/input'), '/');
            $s3InputKey = $inputPrefix.'/'.$docId.'.pdf';
            $this->textractService->uploadToS3($pdfPath, $s3InputKey);

            // Get file size for estimated processing time
            $fileSize = filesize($pdfPath);
            $fileSizeMB = round($fileSize / 1024 / 1024, 2);

            if ($this->option('verbose')) {
                $this->info('  → Starting Textract analysis...');
                $this->line("     File size: {$fileSizeMB} MB");
            }

            // Step 2: Start Textract document analysis with full features
            $jobId = $this->textractService->startDocumentAnalysis(
                s3Key: $s3InputKey,
                jobTag: $docId,
                featureTypes: ['LAYOUT', 'FORMS', 'TABLES', 'SIGNATURES']
            );

            // Estimate completion time (rough estimate: ~30 seconds per MB, min 60 seconds)
            $estimatedSeconds = max(60, (int) ($fileSizeMB * 30));
            $estimatedCompletion = now()->addSeconds($estimatedSeconds)->format('H:i:s');

            // Always log job ID and estimate to console and Laravel log
            $this->info("  → Textract Job ID: {$jobId}");
            $this->info("  → Estimated completion: ~{$estimatedSeconds}s (around {$estimatedCompletion})");

            \Log::info('Textract job started', [
                'job_id' => $jobId,
                'doc_id' => $docId,
                'file_size_mb' => $fileSizeMB,
                'estimated_seconds' => $estimatedSeconds,
                'estimated_completion' => $estimatedCompletion,
                's3_input_key' => $s3InputKey,
            ]);

            // Step 3: Wait and fetch results
            $blocks = $this->textractService->waitAndFetchDocumentAnalysis(
                jobId: $jobId,
                sleepSeconds: 5,
                maxWaitSeconds: 1800
            );

            $actualDuration = round(microtime(true) - $startTime, 2);

            if ($this->option('verbose')) {
                $this->info("  → Analysis complete in {$actualDuration}s. Processing ".count($blocks).' blocks...');
            }

            \Log::info('Textract job completed', [
                'job_id' => $jobId,
                'doc_id' => $docId,
                'actual_duration_seconds' => $actualDuration,
                'blocks_count' => count($blocks),
            ]);

            // Step 4: Save results to S3 and local storage (following PersistReconstructedStep conventions)
            $savedPaths = $this->textractService->saveResultsToS3AndLocal($docId, $blocks);

            // Step 5: Extract text from blocks
            $text = $this->extractTextFromBlocks($blocks);

            // Step 6: Update upload metadata with S3 paths and Textract info
            $metadata = $upload->metadata ?? [];
            $metadata['s3_input_key'] = $s3InputKey;
            $metadata['s3_json_key'] = $savedPaths['s3JsonKey'];
            $metadata['local_json_path'] = $savedPaths['localJsonRel'];
            $metadata['source'] = 'cli-textract';
            $metadata['textract_job_id'] = $jobId;
            $metadata['textract_duration_seconds'] = $actualDuration;
            $metadata['textract_started_at'] = now()->toIso8601String();
            $metadata['force_reprocess'] = $this->option('force-reprocess') || $this->option('batch-reprocess');
            $upload->update(['metadata' => $metadata]);

            if ($this->option('verbose')) {
                $this->info('  ✓ Textract extraction complete');
            }

            return [
                'text' => $text,
                'blocks' => $blocks,
            ];

        } catch (\Throwable $e) {
            if ($this->option('verbose')) {
                $this->newLine();
                $this->error('  ✗ Textract extraction failed: '.$e->getMessage());
            }

            \Log::error('Textract extraction failed', [
                'doc_id' => $docId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return empty result on failure
            return [
                'text' => '',
                'blocks' => [],
            ];
        }
    }

    /**
     * Extract text content from Textract blocks.
     *
     * Processes LINE blocks and concatenates their text with proper spacing.
     *
     * @param  array  $blocks  Textract blocks array
     * @return string Extracted text content
     */
    protected function extractTextFromBlocks(array $blocks): string
    {
        $lines = [];

        foreach ($blocks as $block) {
            // Extract text from LINE blocks (contains complete lines of text)
            if (isset($block['BlockType']) && $block['BlockType'] === 'LINE') {
                if (isset($block['Text']) && trim($block['Text']) !== '') {
                    $lines[] = $block['Text'];
                }
            }
        }

        // Join lines with newlines
        return implode("\n", $lines);
    }

    protected function printSummary(): void
    {
        $this->info('═══════════════════════════════════════');
        $this->info('           INGESTION SUMMARY           ');
        $this->info('═══════════════════════════════════════');
        $this->line("Total files:      {$this->stats['total']}");
        $this->line('Processed:        '.$this->formatStat($this->stats['processed'], 'info'));
        $this->line('Skipped:          '.$this->formatStat($this->stats['skipped'], 'comment'));
        $this->line('Failed:           '.$this->formatStat($this->stats['failed'], 'error'));
        $this->line('Needs review:     '.$this->formatStat($this->stats['needs_review'], 'warn'));
        $this->info('═══════════════════════════════════════');
    }

    protected function formatStat(int $count, string $type): string
    {
        $color = match ($type) {
            'info' => 'green',
            'comment' => 'yellow',
            'error' => 'red',
            'warn' => 'yellow',
            default => 'white',
        };

        return "<fg={$color}>{$count}</>";
    }
}
