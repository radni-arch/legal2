<?php
// app/Services/Informator/Extractors/PdfTextExtractor.php

namespace App\Services\Informator\Extractors;

use RuntimeException;
use Smalot\PdfParser\Parser;

class PdfTextExtractor
{
    public function __construct(
        private readonly Parser $parser = new Parser(),
    ) {}

    /**
     * Convert PDF bytes to plain text, then normalize whitespace.
     */
    public function extractText(string $pdfBinary): string
    {
        try {
            $pdf = $this->parser->parseContent($pdfBinary);
            $text = (string) $pdf->getText();
        } catch (\Throwable $e) {
            throw new RuntimeException('Failed to parse PDF into text: '.$e->getMessage(), 0, $e);
        }

        $text = preg_replace('/\s+/u', ' ', $text) ?? '';
        return trim($text);
    }
}
