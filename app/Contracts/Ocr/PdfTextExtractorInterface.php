<?php

namespace App\Contracts\Ocr;

/**
 * Interface for extracting text from PDF files.
 */
interface PdfTextExtractorInterface
{
    /**
     * Extract text from a PDF file.
     *
     * @param string $pdfPath Path to the PDF file
     * @return array{success: bool, text: string, page_count: int, error: string|null}
     */
    public function extract(string $pdfPath): array;
}
