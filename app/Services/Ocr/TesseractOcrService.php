<?php

namespace App\Services\Ocr;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * TesseractOcrService
 *
 * High-quality OCR extraction using Tesseract with Croatian language support.
 * Converts PDF pages to high-DPI images and processes them individually,
 * preserving original page structure and formatting.
 */
class TesseractOcrService
{
    private string $tesseractBinary;
    private string $convertBinary;
    private string $languages;
    private int $dpi;
    private int $psm;
    private int $oem;
    private int $pageTimeout;
    private int $maxPages;
    private bool $preserveLayout;

    public function __construct()
    {
        $this->tesseractBinary = (string) config('ocr.tesseract.binary', '/usr/bin/tesseract');
        $this->convertBinary = (string) config('ocr.tesseract.convert_binary', '/usr/bin/convert');
        $this->languages = (string) config('ocr.tesseract.languages', 'hrv+eng');
        $this->dpi = (int) config('ocr.tesseract.dpi', 300);
        $this->psm = (int) config('ocr.tesseract.psm', 3);
        $this->oem = (int) config('ocr.tesseract.oem', 1);
        $this->pageTimeout = (int) config('ocr.tesseract.page_timeout', 120);
        $this->maxPages = (int) config('ocr.tesseract.max_pages', 0);
        $this->preserveLayout = (bool) config('ocr.tesseract.preserve_layout', true);
    }

    /**
     * Extract text from a PDF file using Tesseract OCR.
     *
     * @param string $pdfPath Absolute path to PDF file
     * @return array{text: string, status: string, pages: array, page_count: int, engine: string}
     */
    public function extractText(string $pdfPath): array
    {
        if (! is_file($pdfPath)) {
            Log::warning('TesseractOcrService: PDF not found', ['path' => $pdfPath]);
            return [
                'text' => '',
                'status' => 'error',
                'pages' => [],
                'page_count' => 0,
                'engine' => 'tesseract',
                'error' => 'File not found',
            ];
        }

        $tmpDir = sys_get_temp_dir() . '/tesseract_' . uniqid();
        @mkdir($tmpDir, 0755, true);

        try {
            // Step 1: Convert PDF pages to TIFF images
            $imageFiles = $this->convertPdfToImages($pdfPath, $tmpDir);

            if (empty($imageFiles)) {
                Log::warning('TesseractOcrService: No images generated from PDF', ['path' => $pdfPath]);
                return [
                    'text' => '',
                    'status' => 'error',
                    'pages' => [],
                    'page_count' => 0,
                    'engine' => 'tesseract',
                    'error' => 'PDF to image conversion failed',
                ];
            }

            // Step 2: OCR each page
            $pages = [];
            $errors = [];
            foreach ($imageFiles as $pageNum => $imagePath) {
                if ($this->maxPages > 0 && $pageNum > $this->maxPages) {
                    break;
                }

                $pageText = $this->ocrSinglePage($imagePath, $pageNum);
                if ($pageText !== null) {
                    $pages[$pageNum] = $pageText;
                } else {
                    $errors[] = $pageNum;
                }
            }

            // Step 3: Combine pages preserving structure
            $fullText = $this->combinePages($pages, $this->preserveLayout);
            $pageMetadata = $this->buildPageMetadata($pages);

            $status = empty($errors) ? 'success' : (empty($pages) ? 'error' : 'partial');

            Log::info('TesseractOcrService: extraction complete', [
                'path' => $pdfPath,
                'pages_processed' => count($pages),
                'pages_failed' => count($errors),
                'text_length' => mb_strlen($fullText),
            ]);

            return [
                'text' => $fullText,
                'status' => $status,
                'pages' => $pageMetadata,
                'page_count' => count($pages),
                'engine' => 'tesseract',
                'failed_pages' => $errors,
            ];
        } finally {
            // Cleanup temp files
            $this->cleanupTempDir($tmpDir);
        }
    }

    /**
     * Convert PDF to per-page TIFF images using ImageMagick.
     *
     * @return array<int, string> Map of page number => image path
     */
    private function convertPdfToImages(string $pdfPath, string $tmpDir): array
    {
        $outputPattern = $tmpDir . '/page';

        $process = new Process([
            $this->convertBinary,
            '-density', (string) $this->dpi,
            '-depth', '8',
            '-strip',
            '-background', 'white',
            '-alpha', 'off',
            $pdfPath,
            $outputPattern . '.tif',
        ]);
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            Log::warning('TesseractOcrService: ImageMagick conversion failed', [
                'exit_code' => $process->getExitCode(),
                'error' => $process->getErrorOutput(),
            ]);
            return [];
        }

        // Collect generated images (multi-page PDFs produce page-0.tif, page-1.tif, etc.)
        $images = [];
        $multiPage = glob($tmpDir . '/page-*.tif');
        $singlePage = glob($tmpDir . '/page.tif');

        if (! empty($multiPage)) {
            sort($multiPage, SORT_NATURAL);
            foreach ($multiPage as $index => $path) {
                $images[$index + 1] = $path;
            }
        } elseif (! empty($singlePage)) {
            $images[1] = $singlePage[0];
        }

        return $images;
    }

    /**
     * Run Tesseract OCR on a single page image.
     */
    private function ocrSinglePage(string $imagePath, int $pageNum): ?string
    {
        $outputBase = $imagePath . '_ocr';

        $command = $this->buildCommand($imagePath, $outputBase);
        $process = new Process($command);
        $process->setTimeout($this->pageTimeout);
        $process->run();

        if (! $process->isSuccessful()) {
            Log::warning('TesseractOcrService: OCR failed for page', [
                'page' => $pageNum,
                'exit_code' => $process->getExitCode(),
                'error' => $process->getErrorOutput(),
            ]);
            return null;
        }

        $outputFile = $outputBase . '.txt';
        if (! is_file($outputFile)) {
            return null;
        }

        $text = file_get_contents($outputFile);
        @unlink($outputFile);

        return trim($text);
    }

    /**
     * Build the Tesseract command array.
     *
     * @return string[]
     */
    public function buildCommand(string $inputPath, string $outputBase): array
    {
        return [
            $this->tesseractBinary,
            $inputPath,
            $outputBase,
            '-l', $this->languages,
            '--psm', (string) $this->psm,
            '--oem', (string) $this->oem,
        ];
    }

    /**
     * Combine page texts, optionally preserving page structure.
     */
    public function combinePages(array $pages, bool $withSeparators = true): string
    {
        if (empty($pages)) {
            return '';
        }

        ksort($pages);

        if (! $withSeparators) {
            return implode("\n\n", $pages);
        }

        $parts = [];
        $isFirst = true;
        foreach ($pages as $pageNum => $text) {
            if (! $isFirst) {
                $parts[] = "\n\n--- Page {$pageNum} ---\n\n";
            }
            $parts[] = $text;
            $isFirst = false;
        }

        return implode('', $parts);
    }

    /**
     * Build per-page metadata for extracted text.
     *
     * @return array<int, array{page: int, char_count: int, word_count: int}>
     */
    public function buildPageMetadata(array $pages): array
    {
        $metadata = [];
        foreach ($pages as $pageNum => $text) {
            $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);
            $metadata[] = [
                'page' => $pageNum,
                'char_count' => mb_strlen($text, 'UTF-8'),
                'word_count' => count($words),
            ];
        }
        return $metadata;
    }

    /**
     * Check if Tesseract is available on the system.
     */
    public function isAvailable(): bool
    {
        $process = new Process([$this->tesseractBinary, '--version']);
        $process->setTimeout(10);
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * Check if Croatian language pack is installed.
     */
    public function hasCroatianLanguage(): bool
    {
        $process = new Process([$this->tesseractBinary, '--list-langs']);
        $process->setTimeout(10);
        $process->run();

        if (! $process->isSuccessful()) {
            return false;
        }

        return str_contains($process->getOutput(), 'hrv');
    }

    /**
     * Clean up temporary directory.
     */
    private function cleanupTempDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = glob($dir . '/*');
        foreach ((array) $files as $file) {
            @unlink($file);
        }
        @rmdir($dir);
    }
}
