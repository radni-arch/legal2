<?php

namespace App\Services\Ocr;

use App\Contracts\Ocr\PdfTextExtractorInterface;
use Symfony\Component\Process\Process;

/**
 * PdfTextExtractor
 *
 * Extracts text from PDF files using the pdftotext CLI tool (from poppler-utils).
 * Used for fast local text extraction to detect text-rich PDFs before sending to Textract.
 */
class PdfTextExtractor implements PdfTextExtractorInterface
{
    /**
     * Extract text from a PDF file.
     *
     * @param string $pdfPath Path to the PDF file
     * @return array{success: bool, text: string, page_count: int, error: string|null}
     */
    public function extract(string $pdfPath): array
    {
        if (! is_file($pdfPath)) {
            return [
                'success' => false,
                'text' => '',
                'page_count' => 0,
                'error' => "File not found: {$pdfPath}",
            ];
        }

        try {
            // Get page count first using pdfinfo
            $pageCount = $this->getPageCount($pdfPath);

            // Extract text using pdftotext
            // -layout preserves original physical layout
            // -enc UTF-8 ensures proper encoding
            $process = new Process([
                'pdftotext',
                '-layout',
                '-enc', 'UTF-8',
                $pdfPath,
                '-', // Output to stdout
            ]);

            $process->setTimeout(60);
            $process->run();

            if (! $process->isSuccessful()) {
                return [
                    'success' => false,
                    'text' => '',
                    'page_count' => 0,
                    'error' => 'pdftotext command failed: ' . $process->getErrorOutput(),
                ];
            }

            $text = $process->getOutput();

            return [
                'success' => true,
                'text' => $text,
                'page_count' => $pageCount,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'text' => '',
                'page_count' => 0,
                'error' => 'pdftotext exception: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get page count from PDF using pdfinfo.
     */
    private function getPageCount(string $pdfPath): int
    {
        try {
            $process = new Process(['pdfinfo', $pdfPath]);
            $process->setTimeout(10);
            $process->run();

            if ($process->isSuccessful() && preg_match('/Pages:\s+(\d+)/', $process->getOutput(), $matches)) {
                return (int) $matches[1];
            }
        } catch (\Throwable) {
            // Fallback: estimate from text
        }

        return 1;
    }

    /**
     * Check if pdftotext is available on the system.
     */
    public function isAvailable(): bool
    {
        try {
            $process = new Process(['pdftotext', '-v']);
            $process->setTimeout(5);
            $process->run();

            // pdftotext outputs version info to stderr
            return $process->getExitCode() !== 127;
        } catch (\Throwable) {
            return false;
        }
    }
}
