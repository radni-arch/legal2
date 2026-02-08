<?php

namespace App\Services\Ocr;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * OcrmypdfService
 *
 * Wraps ocrmypdf CLI for high-quality OCR with deskew, denoise, and
 * native searchable PDF output. Replaces the ImageMagick+Tesseract chain.
 *
 * Install: pip install ocrmypdf
 * Requires: tesseract-ocr, tesseract-ocr-hrv, ghostscript
 */
class OcrmypdfService
{
    private string $binary;
    private string $languages;
    private int $timeout;
    private int $maxPages;
    private bool $deskew;
    private bool $clean;
    private bool $removeBackground;
    private int $jobsParallel;
    private string $pdfRenderer;
    private string $outputType;

    public function __construct()
    {
        $this->binary = (string) config('ocr.ocrmypdf.binary', 'ocrmypdf');
        $this->languages = (string) config('ocr.ocrmypdf.languages', 'hrv+eng');
        $this->timeout = (int) config('ocr.ocrmypdf.timeout', 600);
        $this->maxPages = (int) config('ocr.ocrmypdf.max_pages', 0);
        $this->deskew = (bool) config('ocr.ocrmypdf.deskew', true);
        $this->clean = (bool) config('ocr.ocrmypdf.clean', false);
        $this->removeBackground = (bool) config('ocr.ocrmypdf.remove_background', false);
        $this->jobsParallel = (int) config('ocr.ocrmypdf.jobs', 2);
        $this->pdfRenderer = (string) config('ocr.ocrmypdf.pdf_renderer', 'sandwich');
        $this->outputType = (string) config('ocr.ocrmypdf.output_type', 'pdf');
    }

    /**
     * Process a PDF file with ocrmypdf.
     *
     * @param  string  $inputPath  Path to the input PDF file
     * @param  array<string, mixed>  $options  Additional options to override defaults
     * @return array{status: string, output_pdf: string, text: string, text_file: string|null, page_count: int, engine: string, error: string|null, exit_code: int}
     */
    public function process(string $inputPath, array $options = []): array
    {
        if (! is_file($inputPath)) {
            return [
                'status' => 'error',
                'output_pdf' => '',
                'text' => '',
                'text_file' => null,
                'page_count' => 0,
                'engine' => 'ocrmypdf',
                'error' => "Input file not found: {$inputPath}",
                'exit_code' => -1,
            ];
        }

        $tmpDir = sys_get_temp_dir().'/ocrmypdf_'.uniqid();
        @mkdir($tmpDir, 0755, true);

        $outputPdf = $options['output_pdf'] ?? $tmpDir.'/output.pdf';
        $sidecarPath = $options['sidecar'] ?? $tmpDir.'/output.txt';
        $options['sidecar'] = $sidecarPath;

        try {
            $command = $this->buildCommand($inputPath, $outputPdf, $options);

            Log::info('OcrmypdfService: executing', [
                'input' => $inputPath,
                'output' => $outputPdf,
                'command' => implode(' ', $command),
            ]);

            $process = new Process($command);
            $process->setTimeout($this->timeout);
            $process->run();

            $exitCode = $process->getExitCode();
            $text = '';
            $pageCount = 0;

            // Exit code 0 = success, 4 = skipped (already has text layer)
            if ($exitCode === 0 || $exitCode === 4) {
                if (is_file($sidecarPath)) {
                    $text = trim(file_get_contents($sidecarPath));
                }
                $pageCount = $this->estimatePageCount($outputPdf, $text);
                $status = $exitCode === 0 ? 'success' : 'skipped_existing_text';

                Log::info('OcrmypdfService: completed', [
                    'input' => $inputPath,
                    'status' => $status,
                    'text_length' => mb_strlen($text),
                    'page_count' => $pageCount,
                ]);

                return [
                    'status' => $status,
                    'output_pdf' => $outputPdf,
                    'text' => $text,
                    'text_file' => is_file($sidecarPath) ? $sidecarPath : null,
                    'page_count' => $pageCount,
                    'engine' => 'ocrmypdf',
                    'error' => null,
                    'exit_code' => $exitCode,
                ];
            }

            $errorOutput = $process->getErrorOutput() ?: $process->getOutput();

            Log::warning('OcrmypdfService: failed', [
                'input' => $inputPath,
                'exit_code' => $exitCode,
                'error' => $errorOutput,
            ]);

            return [
                'status' => 'error',
                'output_pdf' => '',
                'text' => '',
                'text_file' => null,
                'page_count' => 0,
                'engine' => 'ocrmypdf',
                'error' => "ocrmypdf exited with code {$exitCode}: ".mb_substr($errorOutput, 0, 500),
                'exit_code' => $exitCode,
            ];
        } catch (\Throwable $e) {
            Log::error('OcrmypdfService: exception', [
                'input' => $inputPath,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'error',
                'output_pdf' => '',
                'text' => '',
                'text_file' => null,
                'page_count' => 0,
                'engine' => 'ocrmypdf',
                'error' => $e->getMessage(),
                'exit_code' => -1,
            ];
        }
    }

    /**
     * Build the ocrmypdf command array.
     *
     * @param  string  $inputPath  Path to the input PDF
     * @param  string  $outputPath  Path for the output PDF
     * @param  array<string, mixed>  $options  Override options
     * @return string[]
     */
    public function buildCommand(string $inputPath, string $outputPath, array $options = []): array
    {
        $cmd = [$this->binary];

        $cmd[] = '--language';
        $cmd[] = $options['languages'] ?? $this->languages;

        if ($options['deskew'] ?? $this->deskew) {
            $cmd[] = '--deskew';
        }

        if ($options['clean'] ?? $this->clean) {
            $cmd[] = '--clean';
        }

        if ($options['remove_background'] ?? $this->removeBackground) {
            $cmd[] = '--remove-background';
        }

        if ($options['force_ocr'] ?? false) {
            $cmd[] = '--force-ocr';
        }

        if ($options['skip_text'] ?? false) {
            $cmd[] = '--skip-text';
        }

        if (isset($options['sidecar'])) {
            $cmd[] = '--sidecar';
            $cmd[] = $options['sidecar'];
        }

        $jobs = (int) ($options['jobs'] ?? $this->jobsParallel);
        if ($jobs > 0) {
            $cmd[] = '--jobs';
            $cmd[] = (string) $jobs;
        }

        $cmd[] = '--pdf-renderer';
        $cmd[] = $options['pdf_renderer'] ?? $this->pdfRenderer;

        $cmd[] = '--output-type';
        $cmd[] = $options['output_type'] ?? $this->outputType;

        $maxPages = (int) ($options['max_pages'] ?? $this->maxPages);
        if ($maxPages > 0) {
            $cmd[] = '--pages';
            $cmd[] = "1-{$maxPages}";
        }

        $cmd[] = '--rotate-pages';
        $cmd[] = '--continue-on-soft-render-error';

        $cmd[] = $inputPath;
        $cmd[] = $outputPath;

        return $cmd;
    }

    /**
     * Check if ocrmypdf is available on the system.
     */
    public function isAvailable(): bool
    {
        try {
            $process = new Process([$this->binary, '--version']);
            $process->setTimeout(10);
            $process->run();

            return $process->isSuccessful();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Check if Croatian language pack is available for Tesseract (used by ocrmypdf).
     */
    public function hasCroatianLanguage(): bool
    {
        try {
            $process = new Process(['tesseract', '--list-langs']);
            $process->setTimeout(10);
            $process->run();

            return $process->isSuccessful() && str_contains($process->getOutput(), 'hrv');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Get the ocrmypdf version string.
     */
    public function getVersion(): ?string
    {
        try {
            $process = new Process([$this->binary, '--version']);
            $process->setTimeout(10);
            $process->run();

            return $process->isSuccessful() ? trim($process->getOutput()) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Estimate page count from PDF or extracted text.
     */
    private function estimatePageCount(string $pdfPath, string $text): int
    {
        try {
            $process = new Process(['pdfinfo', $pdfPath]);
            $process->setTimeout(10);
            $process->run();
            if ($process->isSuccessful() && preg_match('/Pages:\s+(\d+)/', $process->getOutput(), $m)) {
                return (int) $m[1];
            }
        } catch (\Throwable) {
            // fallback to text-based estimation
        }

        if ($text !== '') {
            return max(1, substr_count($text, "\f") + 1);
        }

        return 0;
    }
}
