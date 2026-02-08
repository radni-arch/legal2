<?php

namespace App\Pipelines\Textract;

use App\Models\TextractJob;
use App\Services\Ocr\DocumentOcrRouter;
use App\Services\Ocr\OcrAvailabilityGuard;
use App\Services\Ocr\OcrmypdfService;
use App\Services\Ocr\OcrQualityComparator;
use App\Services\Ocr\TesseractOcrService;
use Closure;
use Illuminate\Support\Facades\Log;

class TesseractOcrStep
{
    public function __construct(
        private DocumentOcrRouter $router,
        private TesseractOcrService $tesseract,
        private OcrQualityComparator $comparator,
        private ?OcrmypdfService $ocrmypdf = null,
    ) {}

    public function handle(array $payload, Closure $next): mixed
    {
        $driveFileId = (string) $payload['driveFileId'];
        $textractText = $payload['textractText'] ?? $this->extractTextFromPayload($payload);
        $blocks = $payload['blocks'] ?? [];
        $localPath = $payload['localPath'] ?? null;

        Log::info('TesseractOcrStep: analyzing document for OCR routing', [
            'driveFileId' => $driveFileId,
            'textract_text_length' => mb_strlen($textractText),
            'has_local_path' => $localPath !== null,
        ]);

        // Health check: verify local OCR is available
        $guard = app(OcrAvailabilityGuard::class);
        if (! $guard->canUseLocalOcr()) {
            Log::info('TesseractOcrStep: local OCR unavailable, keeping Textract', [
                'driveFileId' => $driveFileId,
            ]);
            $payload['ocr_engine_used'] = 'textract';
            $payload['finalText'] = $textractText;
            $payload['ocr_skipped_reason'] = 'local_ocr_unavailable';
            $payload['ocr_routing'] = ['engine' => 'textract', 'reasons' => ['local_ocr_unavailable'], 'confidence' => 1.0, 'analysis' => [], 'routing_score' => 0.0];

            return $next($payload);
        }

        $routing = $this->router->recommend($textractText, $blocks);
        $payload['ocr_routing'] = $routing;

        // Task 9: Quality-gated re-OCR override
        $needsReview = $payload['needsReview'] ?? false;
        if ($needsReview && $routing['engine'] !== 'tesseract') {
            $forceReOcr = (bool) config('ocr.force_reocr_on_review', true);
            if ($forceReOcr && $localPath !== null) {
                Log::info('TesseractOcrStep: quality-gated re-OCR override', [
                    'driveFileId' => $driveFileId,
                    'reviewReasons' => $payload['reviewReasons'] ?? [],
                    'original_recommendation' => $routing['engine'],
                ]);
                $routing['engine'] = 'tesseract';
                $routing['reasons'][] = 'quality_gated_override';
                $payload['ocr_routing'] = $routing;
            }
        }

        if ($routing['engine'] !== 'tesseract' || $localPath === null) {
            Log::info('TesseractOcrStep: keeping Textract output', [
                'driveFileId' => $driveFileId,
                'engine' => $routing['engine'],
                'reasons' => $routing['reasons'],
            ]);

            $payload['ocr_engine_used'] = 'textract';
            $payload['finalText'] = $textractText;

            return $next($payload);
        }

        Log::info('TesseractOcrStep: running OCR', [
            'driveFileId' => $driveFileId,
            'reasons' => $routing['reasons'],
        ]);

        // Task 5: Prefer ocrmypdf if available
        if ($this->ocrmypdf?->isAvailable()) {
            $ocrResult = $this->ocrmypdf->process($localPath, [
                'force_ocr' => true,
                'deskew' => true,
            ]);

            if ($ocrResult['status'] === 'success' && ! empty($ocrResult['text'])) {
                $comparison = $this->comparator->compare($textractText, $ocrResult['text']);
                $payload['ocr_comparison'] = $comparison;
                $payload['ocr_engine_used'] = $comparison['winner'] === 'tesseract' ? 'ocrmypdf' : 'textract';
                $payload['finalText'] = $comparison['winner'] === 'tesseract' ? $ocrResult['text'] : $textractText;

                if ($comparison['winner'] === 'tesseract' && is_file($ocrResult['output_pdf'])) {
                    $payload['ocrmypdf_output_pdf'] = $ocrResult['output_pdf'];
                }

                $this->updateJobMetadata($payload, $routing, $comparison, $ocrResult['page_count'] ?? 0);

                return $next($payload);
            }

            Log::warning('TesseractOcrStep: ocrmypdf failed, falling back to raw Tesseract', [
                'driveFileId' => $driveFileId,
                'error' => $ocrResult['error'] ?? 'empty output',
            ]);
        }

        // Fallback: raw TesseractOcrService (legacy path)
        $tesseractResult = $this->tesseract->extractText($localPath);

        if ($tesseractResult['status'] === 'error' || empty($tesseractResult['text'])) {
            Log::warning('TesseractOcrStep: Tesseract failed, falling back to Textract', [
                'driveFileId' => $driveFileId,
                'error' => $tesseractResult['error'] ?? 'empty output',
            ]);

            $payload['ocr_engine_used'] = 'textract';
            $payload['finalText'] = $textractText;
            $payload['tesseract_attempted'] = true;
            $payload['tesseract_error'] = $tesseractResult['error'] ?? 'empty output';

            return $next($payload);
        }

        $comparison = $this->comparator->compare($textractText, $tesseractResult['text']);

        Log::info('TesseractOcrStep: quality comparison', [
            'driveFileId' => $driveFileId,
            'winner' => $comparison['winner'],
            'improvement' => $comparison['improvement_percent'],
        ]);

        $payload['ocr_comparison'] = $comparison;
        $payload['ocr_engine_used'] = $comparison['winner'];

        if ($comparison['winner'] === 'tesseract') {
            $payload['finalText'] = $tesseractResult['text'];
            $payload['tesseract_pages'] = $tesseractResult['pages'];
            $payload['tesseract_page_count'] = $tesseractResult['page_count'];
        } else {
            $payload['finalText'] = $textractText;
        }

        $this->updateJobMetadata($payload, $routing, $comparison, $tesseractResult['page_count'] ?? 0);

        return $next($payload);
    }

    private function updateJobMetadata(array &$payload, array $routing, array $comparison, int $pageCount): void
    {
        if (isset($payload['job']) && $payload['job'] instanceof TextractJob) {
            $metadata = $payload['job']->metadata ?? [];
            $metadata['ocr_routing'] = [
                'engine_used' => $payload['ocr_engine_used'],
                'routing_reasons' => $routing['reasons'],
                'comparison' => $comparison,
                'tesseract_page_count' => $pageCount,
            ];
            $payload['job']->update(['metadata' => $metadata]);
        }
    }

    private function extractTextFromPayload(array $payload): string
    {
        $linesByPage = $payload['linesByPage'] ?? [];
        $text = '';
        foreach ($linesByPage as $pageLines) {
            foreach ((array) $pageLines as $line) {
                $text .= $line . "\n";
            }
            $text .= "\n";
        }
        return trim($text);
    }
}
