<?php

namespace App\Pipelines\Textract;

use App\Actions\Textract\AnalyzeTextractLayout;
use Closure;

/**
 * Step: CollectLinesStep
 * Convert blocks into OcrDocument structure using new TextractLayoutAnalyzer.
 * Adds: ocrDocument, textractText
 */
class CollectLinesStep
{
    public function handle(array $payload, Closure $next): mixed
    {
        // Skip collecting Textract lines when using local OCR route (skip_textract)
        // LocalOcrRouteStep already populated textractText and linesByPage
        if ($payload['skip_textract'] ?? false) {
            $payload['job']->update(['status' => 'reconstructing']);
            return $next($payload);
        }

        // Use the saved JSON path to analyze the layout
        $jsonPath = $payload['resultsMeta']['localJsonAbs'] ?? null;

        if (! $jsonPath || ! file_exists($jsonPath)) {
            throw new \RuntimeException('Textract JSON file not found for analysis');
        }

        $payload['ocrDocument'] = AnalyzeTextractLayout::run($jsonPath);

        // Build full textract text for downstream comparison (TesseractOcrStep)
        $textractText = '';
        foreach ($payload['ocrDocument']->pages as $page) {
            foreach ($page->lines as $line) {
                $textractText .= $line->text . "\n";
            }
            $textractText .= "\n";
        }
        $payload['textractText'] = trim($textractText);

        $payload['job']->update(['status' => 'reconstructing']);

        return $next($payload);
    }
}
