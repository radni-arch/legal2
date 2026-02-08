<?php

namespace App\Pipelines\Textract;

use App\Services\Ocr\OcrmypdfService;
use Closure;
use Illuminate\Support\Facades\Log;

/**
 * Step: LocalOcrRouteStep
 *
 * Handles local OCR processing when Textract is being skipped.
 * This step runs OcrmypdfService with --skip-text flag for PDFs
 * that already have extractable text, preserving existing text
 * and only OCR'ing pages that need it.
 *
 * This step only runs when payload['skip_textract'] is true.
 *
 * Adds to payload:
 *   - textractText: string - text from pdftotext (already in payload) or ocrmypdf
 *   - blocks: array - empty array (no Textract blocks available)
 *   - linesByPage: array - extracted text organized by page
 *   - ocr_engine_used: string - 'ocrmypdf_skip_text'
 */
class LocalOcrRouteStep
{
    public function __construct(
        private ?OcrmypdfService $ocrmypdf = null
    ) {
        $this->ocrmypdf = $ocrmypdf ?? app(OcrmypdfService::class);
    }

    public function handle(array $payload, Closure $next): mixed
    {
        // Only run when skip_textract is true
        if (! ($payload['skip_textract'] ?? false)) {
            return $next($payload);
        }

        $driveFileId = (string) ($payload['driveFileId'] ?? 'unknown');
        $localPath = $payload['localPath'] ?? null;

        Log::info('LocalOcrRouteStep: processing with skip-text OCR', [
            'driveFileId' => $driveFileId,
            'ocr_route' => $payload['ocr_route'] ?? 'ocrmypdf_skip_text',
        ]);

        // Use pdftotext text if already extracted
        $text = $payload['pdftotext_text'] ?? '';
        $pageCount = $payload['ocr_routing_metadata']['page_count'] ?? 1;

        // If we have a local path and ocrmypdf is available, enhance with ocrmypdf
        if ($localPath && is_file($localPath) && $this->ocrmypdf?->isAvailable()) {
            $ocrResult = $this->ocrmypdf->process($localPath, [
                'skip_text' => true, // Preserve existing text, only OCR pages without text
                'deskew' => true,
            ]);

            if ($ocrResult['status'] === 'success' || $ocrResult['status'] === 'skipped_existing_text') {
                // Use ocrmypdf text if it's better (more complete)
                $ocrText = $ocrResult['text'] ?? '';
                if (mb_strlen($ocrText) > mb_strlen($text)) {
                    $text = $ocrText;
                }
                $pageCount = $ocrResult['page_count'] ?: $pageCount;

                // Store the searchable PDF if available
                if (! empty($ocrResult['output_pdf']) && is_file($ocrResult['output_pdf'])) {
                    $payload['ocrmypdf_output_pdf'] = $ocrResult['output_pdf'];
                }

                Log::info('LocalOcrRouteStep: ocrmypdf enhanced text', [
                    'driveFileId' => $driveFileId,
                    'status' => $ocrResult['status'],
                    'text_length' => mb_strlen($text),
                    'page_count' => $pageCount,
                ]);
            } else {
                Log::warning('LocalOcrRouteStep: ocrmypdf failed, using pdftotext text', [
                    'driveFileId' => $driveFileId,
                    'error' => $ocrResult['error'] ?? 'unknown',
                ]);
            }
        }

        // Populate payload for downstream steps
        $payload['textractText'] = $text;
        $payload['finalText'] = $text;
        $payload['blocks'] = []; // No Textract blocks available
        $payload['ocr_engine_used'] = 'ocrmypdf_skip_text';

        // Create linesByPage structure for compatibility
        $payload['linesByPage'] = $this->createLinesByPage($text, $pageCount);

        // Mark that Textract was skipped for cost tracking
        $routingMetadata = $payload['ocr_routing_metadata'] ?? [];
        $routingMetadata['skipped_textract'] = true;
        $routingMetadata['ocr_engine_used'] = 'ocrmypdf_skip_text';
        $routingMetadata['final_text_length'] = mb_strlen($text);
        $payload['ocr_routing_metadata'] = $routingMetadata;

        // Update job status to reflect local OCR processing
        if (isset($payload['job'])) {
            $payload['job']->update([
                'status' => 'processing_local_ocr',
                'metadata' => array_merge(
                    $payload['job']->metadata ?? [],
                    ['ocr_routing_metadata' => $routingMetadata]
                ),
            ]);
        }

        Log::info('LocalOcrRouteStep: completed', [
            'driveFileId' => $driveFileId,
            'text_length' => mb_strlen($text),
            'page_count' => $pageCount,
        ]);

        return $next($payload);
    }

    /**
     * Split text into pages for linesByPage structure.
     * Uses form feed characters (\f) as page separators.
     */
    private function createLinesByPage(string $text, int $expectedPageCount): array
    {
        $pages = explode("\f", $text);
        $linesByPage = [];

        foreach ($pages as $pageIndex => $pageText) {
            $lines = array_filter(
                explode("\n", trim($pageText)),
                fn ($line) => trim($line) !== ''
            );
            $linesByPage[$pageIndex + 1] = array_values($lines);
        }

        // Ensure we have at least the expected number of pages
        for ($i = count($linesByPage) + 1; $i <= $expectedPageCount; $i++) {
            $linesByPage[$i] = [];
        }

        return $linesByPage;
    }
}
