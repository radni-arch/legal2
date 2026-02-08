<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class OcrService
{
    public function extractTextFromPdf(string $pdfPath): string
    {
        if (! is_file($pdfPath)) {
            Log::warning('OcrService: PDF file not found', ['path' => $pdfPath]);

            return '';
        }

        // Try pdftotext first (layout preserved)
        $pdftotext = "/bin/pdftotext";
        if ($pdftotext !== '') {
            $proc = new Process([$pdftotext, '-enc', 'UTF-8', '-layout', $pdfPath, '-']);
            $proc->setTimeout(60);
            $proc->run();
            if ($proc->isSuccessful()) {
                $out = $proc->getOutput();
                $out = trim($out);
                if ($out !== '') {
                    return $out;
                }
            } else {
                Log::warning('OcrService: pdftotext failed', [
                    'path' => $pdfPath,
                    'exit_code' => $proc->getExitCode(),
                    'error' => $proc->getErrorOutput(),
                ]);
            }
        } else {
            Log::info('OcrService: pdftotext not available, skipping');
        }

        // Optional fallback: tesseract OCR (requires convert to images)
        $tesseract = trim((string) shell_exec('which tesseract'));
        $convert = trim((string) shell_exec('which convert'));
        if ($tesseract !== '' && $convert !== '') {
            $tmpBase = tempnam(sys_get_temp_dir(), 'ocr_');
            @unlink($tmpBase);
            $imgBase = $tmpBase.'_page';
            // Convert each PDF page to TIFF and OCR
            $convertCmd = [$convert, '-density', '300', $pdfPath, $imgBase.'.tif'];
            $p1 = new Process($convertCmd);
            $p1->setTimeout(120);
            $p1->run();
            if ($p1->isSuccessful()) {
                $pages = glob($imgBase.'-*.tif') ?: glob($imgBase.'.tif');
                $texts = [];
                foreach ((array) $pages as $i => $img) {
                    $outTxt = $tmpBase.'_'.($i + 1);
                    $p2 = new Process([$tesseract, $img, $outTxt, '-l', 'hr+eng']);
                    $p2->setTimeout(120);
                    $p2->run();
                    if ($p2->isSuccessful()) {
                        if (is_file($outTxt.'.txt')) {
                            $texts[] = file_get_contents($outTxt.'.txt');
                            @unlink($outTxt.'.txt');
                        }
                    } else {
                        Log::warning('OcrService: tesseract OCR failed for page', [
                            'path' => $pdfPath,
                            'page' => $i + 1,
                            'exit_code' => $p2->getExitCode(),
                            'error' => $p2->getErrorOutput(),
                        ]);
                    }
                    @unlink($img);
                }
                if (! empty($texts)) {
                    return trim(preg_replace('/\s+/u', ' ', implode("\n\n", $texts)) ?? '');
                }
            } else {
                Log::warning('OcrService: convert (ImageMagick) failed', [
                    'path' => $pdfPath,
                    'exit_code' => $p1->getExitCode(),
                    'error' => $p1->getErrorOutput(),
                ]);
            }
        } else {
            if ($tesseract === '') {
                Log::info('OcrService: tesseract not available, skipping OCR fallback');
            }
            if ($convert === '') {
                Log::info('OcrService: convert (ImageMagick) not available, skipping OCR fallback');
            }
        }

        Log::warning('OcrService: All OCR methods failed or produced no text', ['path' => $pdfPath]);

        return '';
    }
}
