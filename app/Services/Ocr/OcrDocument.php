<?php

namespace App\Services\Ocr;

/**
 * DTOs (consider moving each to its own file in production)
 */
final class OcrDocument
{
    /** @var OcrPage[] */
    public array $pages = [];

    /**
     * Create an OcrDocument from plain text.
     *
     * Splits text by form feed characters (\f) into pages,
     * and by newlines into lines. Empty lines are filtered out.
     * Lines are created with zero confidence and zero coordinates
     * since no spatial data is available from plain text.
     *
     * Used as a fallback when Textract is skipped and only raw text
     * is available from local OCR (e.g., ocrmypdf/pdftotext).
     */
    public static function fromPlainText(string $text): self
    {
        $document = new self;

        if (trim($text) === '') {
            return $document;
        }

        $rawPages = explode("\f", $text);

        foreach ($rawPages as $pageIndex => $pageText) {
            $rawLines = explode("\n", $pageText);

            $ocrLines = [];
            $lineIndex = 0;
            foreach ($rawLines as $rawLine) {
                if (trim($rawLine) === '') {
                    continue;
                }

                $ocrLines[] = new OcrLine(
                    text: $rawLine,
                    left: 0.0,
                    top: $lineIndex * 0.05,
                    width: 1.0,
                    height: 0.04,
                    confidence: 0.0,
                );
                $lineIndex++;
            }

            if (! empty($ocrLines)) {
                $document->pages[] = new OcrPage(
                    number: $pageIndex + 1,
                    lines: $ocrLines,
                );
            }
        }

        return $document;
    }
}
