<?php

namespace App\Services\Pdf;

use App\Contracts\Services\Pdf\PdfArticleSplitterInterface;
use App\Exceptions\PdfException;
use App\Services\PdfRenderer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use setasign\Fpdi\Fpdi;
use Symfony\Component\Process\Process;

class PdfArticleSplitter2 implements PdfArticleSplitterInterface
{
    public function __construct(private PdfRenderer $renderer) {}

    public function split(
        string $pdfPath,
        string $outDir,
        string $mode = 'pages',
        ?string $lawTitle = null,
        ?string $eli = null,
        ?string $pubDate = null,
        int $startPage = 1,
        array $opts = []
    ): array {
        $startTime = microtime(true);

        Log::info('Starting PDF article split', [
            'pdf_path' => basename($pdfPath),
            'mode' => $mode,
            'law_title' => $lawTitle,
            'start_page' => $startPage,
        ]);

        try {
            // Validate PDF file
            if (! is_file($pdfPath)) {
                Log::error('PDF file not found', [
                    'pdf_path' => $pdfPath,
                ]);
                throw new PdfException(
                    "PDF not found: $pdfPath",
                    PdfException::PDF_NOT_FOUND
                );
            }

            // Create output directory
            $mkdirStart = microtime(true);
            if (! @mkdir($outDir, 0775, true) && ! is_dir($outDir)) {
                $mkdirDuration = microtime(true) - $mkdirStart;
                Log::error('Failed to create output directory', [
                    'out_dir' => $outDir,
                    'duration_ms' => round($mkdirDuration * 1000, 2),
                ]);
                throw new PdfException(
                    "Failed to create output directory: $outDir",
                    PdfException::FILE_WRITE_FAILED
                );
            }

            $onlyNumbers = array_map([$this, 'normalizeArticleNumber'], $opts['only_numbers'] ?? []);
            $sidecar = (bool) ($opts['sidecar'] ?? false);
            $embedXmp = (bool) ($opts['embed_xmp'] ?? false);
            $extraAttrs = (array) ($opts['extra_attrs'] ?? []);
            $dry = (bool) ($opts['dry'] ?? false);

            // Get page count
            $pageCountStart = microtime(true);
            try {
                $pageCount = $this->getPageCount($pdfPath);
                $pageCountDuration = microtime(true) - $pageCountStart;

                if ($pageCount < 1) {
                    Log::error('Invalid page count', [
                        'page_count' => $pageCount,
                        'duration_ms' => round($pageCountDuration * 1000, 2),
                    ]);
                    throw new PdfException(
                        'Unable to read page count (pdfinfo).',
                        PdfException::PAGE_COUNT_FAILED
                    );
                }

                Log::debug('Page count retrieved', [
                    'page_count' => $pageCount,
                    'duration_ms' => round($pageCountDuration * 1000, 2),
                ]);
            } catch (PdfException $e) {
                throw $e;
            } catch (\Throwable $e) {
                $pageCountDuration = microtime(true) - $pageCountStart;
                Log::error('Page count retrieval failed', [
                    'error' => $e->getMessage(),
                    'duration_ms' => round($pageCountDuration * 1000, 2),
                ]);
                throw new PdfException(
                    "Failed to get page count: {$e->getMessage()}",
                    PdfException::PAGE_COUNT_FAILED,
                    $e
                );
            }

            // Detect article starts
            $detectStart = microtime(true);
            try {
                $articleStarts = $this->detectArticleStartsByPage($pdfPath, $startPage, $pageCount);
                $detectDuration = microtime(true) - $detectStart;

                if (empty($articleStarts)) {
                    Log::error('No articles detected in PDF', [
                        'start_page' => $startPage,
                        'end_page' => $pageCount,
                        'duration_ms' => round($detectDuration * 1000, 2),
                    ]);
                    throw new PdfException(
                        "Nisam pronašao oznake 'Članak N.' u PDF-u. Pokušaj s --start-page ili mode=render.",
                        PdfException::ARTICLE_DETECTION_FAILED
                    );
                }

                Log::info('Articles detected', [
                    'article_count' => count($articleStarts),
                    'duration_ms' => round($detectDuration * 1000, 2),
                ]);
            } catch (PdfException $e) {
                throw $e;
            } catch (\Throwable $e) {
                $detectDuration = microtime(true) - $detectStart;
                Log::error('Article detection failed', [
                    'error' => $e->getMessage(),
                    'duration_ms' => round($detectDuration * 1000, 2),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw new PdfException(
                    "Article detection failed: {$e->getMessage()}",
                    PdfException::ARTICLE_DETECTION_FAILED,
                    $e
                );
            }

            // Izgradi raspon stranica za svaki članak; dupliciraj graničnu stranicu
            $ranges = $this->buildPageRanges($articleStarts, $pageCount);

            // Ako je specificiran subset članaka (onlyNumbers), filtriraj ranges
            if ($onlyNumbers) {
                $ranges = array_values(array_filter($ranges, fn ($r) => in_array($r['number'], $onlyNumbers, true)));
            }

            $manifest = [];
            if ($mode === 'pages') {
                foreach ($ranges as $item) {
                    $fileName = $this->buildArticleFileName($lawTitle, (string) $item['number']);
                    $dest = rtrim($outDir, '/').'/'.$fileName;

                    if (! $dry) {
                        $this->exportPageRange($pdfPath, $item['start_page'], $item['end_page'], $dest);
                    }

                    // Taggable attributes + optional XMP/sidecar
                    $attr = $this->buildAttributes($lawTitle, (string) $item['number'], $eli, $pubDate, $item, $dest, $extraAttrs);
                    if (! $dry) {
                        $this->maybeWriteSidecarAndXmp($dest, $attr, $sidecar, $embedXmp);
                    }

                    $manifest[] = $this->makeMeta($pdfPath, $dest, $item['number'], $item['start_page'], $item['end_page'], $lawTitle, $eli, $pubDate, 'pages');
                }
            } else {
                // render mode – izvući puni tekst i rasparčati po člancima
                $fullText = $this->getText($pdfPath, $startPage, $pageCount);
                $chunks = $this->splitTextIntoArticles($fullText);

                // Pokušaj upariti članke iz teksta s detektiranim brojevima i stranicama (po broju; fallback po redu)
                $byNumber = [];
                foreach ($ranges as $r) {
                    $byNumber[$r['number']] = $r;
                }

                $i = 0;
                foreach ($chunks as $chunk) {
                    $num = $chunk['number'];
                    if ($onlyNumbers && ! in_array($num, $onlyNumbers, true)) {
                        continue;
                    }

                    $text = $chunk['text'];
                    $range = $byNumber[$num] ?? $ranges[$i] ?? ['start_page' => null, 'end_page' => null];
                    $fileName = $this->buildArticleFileName($lawTitle, (string) $num);
                    $dest = rtrim($outDir, '/').'/'.$fileName;

                    // Napravi minimalistički HTML s očuvanim novim redovima + skriveni search tags
                    $searchTags = $this->makeSearchTags($lawTitle, (string) $num, $eli, $pubDate, $extraAttrs);
                    $articleHtml = '<div style="white-space:pre-wrap">'.e($text).'</div>';
                    $articleHtml .= '<div style="display:none" aria-hidden="true" data-type="search-tags">'.e(implode(', ', $searchTags)).'</div>';

                    $ctx = [
                        'law_title' => $lawTitle ?: 'Nepoznati naslov',
                        'law_eli' => $eli ?: '',
                        'law_pub_date' => $pubDate ?: '',
                        'article_number' => $num,
                        'article_html' => $articleHtml,
                        'generated_at' => gmdate('Y-m-d H:i:s').'Z',
                        'generator_version' => '1.0.0',
                        'search_tags' => $searchTags,
                    ];

                    if (! $dry) {
                        $this->renderer->renderArticle($ctx, $dest);
                    }

                    // Taggable attributes + optional XMP/sidecar
                    $attr = $this->buildAttributes($lawTitle, (string) $num, $eli, $pubDate, $range, $dest, $extraAttrs);
                    if (! $dry) {
                        $this->maybeWriteSidecarAndXmp($dest, $attr, $sidecar, $embedXmp);
                    }

                    $manifest[] = $this->makeMeta($pdfPath, $dest, (string) $num, $range['start_page'], $range['end_page'], $lawTitle, $eli, $pubDate, 'render');
                    $i++;
                }
            }

            // Snimi manifest
            if (! $dry) {
                $manifestStart = microtime(true);
                try {
                    $manifestPath = rtrim($outDir, '/').'/manifest.json';
                    $manifestData = json_encode([
                        'source_pdf' => realpath($pdfPath),
                        'mode' => $mode,
                        'count' => count($manifest),
                        'generated_at' => gmdate('c'),
                        'articles' => $manifest,
                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

                    if ($manifestData === false) {
                        throw new PdfException(
                            'JSON encoding failed for manifest',
                            PdfException::FILE_WRITE_FAILED
                        );
                    }

                    $bytesWritten = file_put_contents($manifestPath, $manifestData);
                    if ($bytesWritten === false) {
                        throw new PdfException(
                            'Failed to write manifest file',
                            PdfException::FILE_WRITE_FAILED
                        );
                    }

                    $manifestDuration = microtime(true) - $manifestStart;
                    Log::debug('Manifest written', [
                        'manifest_path' => $manifestPath,
                        'bytes' => $bytesWritten,
                        'duration_ms' => round($manifestDuration * 1000, 2),
                    ]);
                } catch (PdfException $e) {
                    throw $e;
                } catch (\Throwable $e) {
                    $manifestDuration = microtime(true) - $manifestStart;
                    Log::error('Manifest write failed', [
                        'error' => $e->getMessage(),
                        'duration_ms' => round($manifestDuration * 1000, 2),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    throw new PdfException(
                        "Failed to write manifest: {$e->getMessage()}",
                        PdfException::FILE_WRITE_FAILED,
                        $e
                    );
                }
            }

            $totalDuration = microtime(true) - $startTime;
            Log::info('PDF article split completed successfully', [
                'pdf_path' => basename($pdfPath),
                'mode' => $mode,
                'article_count' => count($manifest),
                'total_duration_ms' => round($totalDuration * 1000, 2),
            ]);

            return $manifest;
        } catch (PdfException $e) {
            $totalDuration = microtime(true) - $startTime;
            Log::error('PDF article split failed', [
                'pdf_path' => basename($pdfPath),
                'mode' => $mode,
                'error_code' => $e->getCode(),
                'error' => $e->getMessage(),
                'total_duration_ms' => round($totalDuration * 1000, 2),
            ]);
            throw $e;
        } catch (\Throwable $e) {
            $totalDuration = microtime(true) - $startTime;
            Log::error('PDF article split failed with unexpected error', [
                'pdf_path' => basename($pdfPath),
                'mode' => $mode,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'total_duration_ms' => round($totalDuration * 1000, 2),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new PdfException(
                "Unexpected error in PDF split: {$e->getMessage()}",
                PdfException::UNEXPECTED_ERROR,
                $e
            );
        }
    }

    private function getPageCount(string $pdfPath): int
    {
        $startTime = microtime(true);

        try {
            Log::debug('Getting PDF page count', [
                'pdf_path' => basename($pdfPath),
            ]);

            $process = new Process(['pdfinfo', $pdfPath]);
            $process->setTimeout(20);

            $execStart = microtime(true);
            $process->run();
            $execDuration = microtime(true) - $execStart;

            if (! $process->isSuccessful()) {
                $errorOutput = $process->getErrorOutput();
                Log::error('pdfinfo execution failed', [
                    'pdf_path' => basename($pdfPath),
                    'error_output' => $errorOutput,
                    'exec_duration_ms' => round($execDuration * 1000, 2),
                ]);
                throw new PdfException(
                    "pdfinfo error: {$errorOutput}",
                    PdfException::PAGE_COUNT_FAILED
                );
            }

            $out = $process->getOutput();
            $pageCount = preg_match('/Pages:\s+(\d+)/', $out, $m) ? (int) $m[1] : 0;

            $totalDuration = microtime(true) - $startTime;
            Log::debug('PDF page count retrieved', [
                'pdf_path' => basename($pdfPath),
                'page_count' => $pageCount,
                'total_duration_ms' => round($totalDuration * 1000, 2),
            ]);

            return $pageCount;
        } catch (PdfException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $totalDuration = microtime(true) - $startTime;
            Log::error('Page count retrieval failed', [
                'pdf_path' => basename($pdfPath),
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'total_duration_ms' => round($totalDuration * 1000, 2),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new PdfException(
                "Failed to get page count: {$e->getMessage()}",
                PdfException::PAGE_COUNT_FAILED,
                $e
            );
        }
    }

    private function runPdftotext(array $args): string
    {
        $startTime = microtime(true);

        try {
            Log::debug('Running pdftotext', [
                'arg_count' => count($args),
            ]);

            // Sastavi i pokreni: pdftotext <args>
            $process = new Process(array_merge(['pdftotext'], $args));
            $process->setTimeout(60);

            $execStart = microtime(true);
            $process->run();
            $execDuration = microtime(true) - $execStart;

            if (! $process->isSuccessful()) {
                $errorOutput = $process->getErrorOutput();
                Log::error('pdftotext execution failed', [
                    'error_output' => $errorOutput,
                    'exec_duration_ms' => round($execDuration * 1000, 2),
                ]);
                throw new PdfException(
                    "pdftotext error: {$errorOutput}",
                    PdfException::TEXT_EXTRACTION_FAILED
                );
            }

            $output = $process->getOutput();
            $outputLength = strlen($output);

            $totalDuration = microtime(true) - $startTime;
            Log::debug('pdftotext execution succeeded', [
                'output_length' => $outputLength,
                'total_duration_ms' => round($totalDuration * 1000, 2),
            ]);

            return $output;
        } catch (PdfException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $totalDuration = microtime(true) - $startTime;
            Log::error('pdftotext execution failed with unexpected error', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'total_duration_ms' => round($totalDuration * 1000, 2),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new PdfException(
                "pdftotext failed: {$e->getMessage()}",
                PdfException::PROCESS_EXECUTION_FAILED,
                $e
            );
        }
    }

    private function getText(string $pdfPath, int $from, int $to): string
    {
        // VAŽNO: ovdje je 'UTF-8' bez crte ispred
        return $this->runPdftotext([
            '-f', (string) $from,
            '-l', (string) $to,
            '-enc', 'UTF-8',
            '-layout',
            $pdfPath,
            '-', // output to stdout
        ]);
    }

    private function getPageText(string $pdfPath, int $page): string
    {
        return $this->runPdftotext([
            '-f', (string) $page,
            '-l', (string) $page,
            '-enc', 'UTF-8',
            '-layout',
            $pdfPath,
            '-',
        ]);
    }

    private function detectArticleStartsByPage(string $pdfPath, int $startPage, int $endPage): array
    {
        $starts = [];
        for ($p = $startPage; $p <= $endPage; $p++) {
            $txt = $this->getPageText($pdfPath, $p);
            // Članak na početku retka (ignoriraj pojavljivanja usred rečenica)
            if (preg_match_all('/(^|\R)\s*(Članak|CLANAK)\s+(\d+(?:\.[a-z])?)\s*\./iu', $txt, $m)) {
                foreach ($m[3] as $raw) {
                    $num = $this->normalizeArticleNumber($raw);
                    // izbjegni duplikate (ako isti članak više puta spomenut na istoj stranici)
                    if (! array_key_exists($num, array_column($starts, null, 'number') ?? [])) {
                        $starts[] = ['number' => $num, 'start_page' => $p];
                    }
                }
            }
        }
        // Sortiraj po start_page
        usort($starts, fn ($a, $b) => $a['start_page'] <=> $b['start_page']);

        return $starts;
    }

    private function buildPageRanges(array $starts, int $lastPage): array
    {
        $ranges = [];
        $count = count($starts);
        for ($i = 0; $i < $count; $i++) {
            $start = $starts[$i]['start_page'];
            $end = ($i < $count - 1) ? $starts[$i + 1]['start_page'] : $lastPage;
            // dupliciraj graničnu stranicu: prethodni završava na stranici početka sljedećeg
            $ranges[] = [
                'number' => $starts[$i]['number'],
                'start_page' => $start,
                'end_page' => $end,
            ];
        }

        return $ranges;
    }

    private function exportPageRange(string $srcPdf, int $from, int $to, string $destPdf): void
    {
        $startTime = microtime(true);

        try {
            Log::debug('Exporting PDF page range', [
                'source' => basename($srcPdf),
                'from_page' => $from,
                'to_page' => $to,
                'dest' => basename($destPdf),
            ]);

            $pdf = new Fpdi;

            // Phase 1: Load source PDF
            $loadStart = microtime(true);
            try {
                $pageCount = $pdf->setSourceFile($srcPdf);
                $loadDuration = microtime(true) - $loadStart;

                Log::debug('Source PDF loaded', [
                    'source' => basename($srcPdf),
                    'total_pages' => $pageCount,
                    'duration_ms' => round($loadDuration * 1000, 2),
                ]);
            } catch (\Throwable $e) {
                $loadDuration = microtime(true) - $loadStart;
                Log::error('Failed to load source PDF', [
                    'source' => basename($srcPdf),
                    'error' => $e->getMessage(),
                    'duration_ms' => round($loadDuration * 1000, 2),
                ]);
                throw new PdfException(
                    "Failed to load source PDF: {$e->getMessage()}",
                    PdfException::INVALID_PDF,
                    $e
                );
            }

            // Normalize page range
            $from = max(1, min($pageCount, $from));
            $to = max(1, min($pageCount, $to));
            if ($to < $from) {
                $to = $from;
            }

            // Phase 2: Import pages
            $importStart = microtime(true);
            $pagesImported = 0;
            for ($p = $from; $p <= $to; $p++) {
                try {
                    $tpl = $pdf->importPage($p);
                    $size = $pdf->getTemplateSize($tpl);
                    $pdf->AddPage($size['width'] > $size['height'] ? 'L' : 'P', [$size['width'], $size['height']]);
                    $pdf->useTemplate($tpl);
                    $pagesImported++;
                } catch (\Throwable $e) {
                    Log::warning('Failed to import page', [
                        'page' => $p,
                        'error' => $e->getMessage(),
                    ]);
                    // Continue with other pages
                }
            }
            $importDuration = microtime(true) - $importStart;

            Log::debug('Pages imported', [
                'pages_imported' => $pagesImported,
                'requested_pages' => ($to - $from + 1),
                'duration_ms' => round($importDuration * 1000, 2),
            ]);

            if ($pagesImported === 0) {
                throw new PdfException(
                    "No pages could be imported from range {$from}-{$to}",
                    PdfException::PAGE_EXPORT_FAILED
                );
            }

            // Phase 3: Write output file
            $writeStart = microtime(true);
            try {
                @mkdir(dirname($destPdf), 0775, true);
                $pdf->Output($destPdf, 'F');
                $writeDuration = microtime(true) - $writeStart;

                Log::debug('PDF page range exported successfully', [
                    'dest' => basename($destPdf),
                    'file_size' => @filesize($destPdf),
                    'write_duration_ms' => round($writeDuration * 1000, 2),
                ]);
            } catch (\Throwable $e) {
                $writeDuration = microtime(true) - $writeStart;
                Log::error('Failed to write output PDF', [
                    'dest' => basename($destPdf),
                    'error' => $e->getMessage(),
                    'write_duration_ms' => round($writeDuration * 1000, 2),
                ]);
                throw new PdfException(
                    "Failed to write output PDF: {$e->getMessage()}",
                    PdfException::FILE_WRITE_FAILED,
                    $e
                );
            }

            $totalDuration = microtime(true) - $startTime;
            Log::info('PDF page range export completed', [
                'source' => basename($srcPdf),
                'dest' => basename($destPdf),
                'pages' => "{$from}-{$to}",
                'total_duration_ms' => round($totalDuration * 1000, 2),
            ]);
        } catch (PdfException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $totalDuration = microtime(true) - $startTime;
            Log::error('PDF page range export failed with unexpected error', [
                'source' => basename($srcPdf),
                'dest' => basename($destPdf),
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'total_duration_ms' => round($totalDuration * 1000, 2),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new PdfException(
                "Unexpected error exporting pages: {$e->getMessage()}",
                PdfException::UNEXPECTED_ERROR,
                $e
            );
        }
    }

    private function splitTextIntoArticles(string $text): array
    {
        // Normaliziraj novačak
        $text = str_replace("\r\n", "\n", $text);
        // Ubaci razdjelnike prije "Članak N."
        $text = preg_replace('/(^|\R)\s*(Članak|CLANAK)\s+(\d+(?:\.[a-z])?)\s*\./iu', "\n<<BREAK>>Članak $3.\n", $text);

        $parts = array_values(array_filter(array_map('trim', explode('<<BREAK>>', $text))));
        $out = [];
        foreach ($parts as $part) {
            if (preg_match('/^Članak\s+(\d+(?:\.[a-z])?)\s*\./iu', $part, $m)) {
                $num = $this->normalizeArticleNumber($m[1]);
                $out[] = ['number' => $num, 'text' => $part];
            }
        }

        return $out;
    }

    private function normalizeArticleNumber(string $raw): string
    {
        // "8.a" -> "8a", "12"->"12"
        $raw = Str::of($raw)->lower()->toString();

        return str_replace('.', '', $raw);
    }

    private function makeMeta(
        string $srcPdf, string $destPdf, string $num, ?int $startPage, ?int $endPage,
        ?string $title, ?string $eli, ?string $pubDate, string $mode
    ): array {
        $bytes = @filesize($destPdf) ?: null;
        $sha256 = @hash_file('sha256', $destPdf) ?: null;

        return [
            'article_number' => (string) $num,
            'source_pdf' => realpath($srcPdf),
            'output_pdf' => realpath($destPdf),
            'pages' => ['start' => $startPage, 'end' => $endPage],
            'law_title' => $title,
            'eli' => $eli,
            'publication_date' => $pubDate,
            'mode' => $mode,
            'file' => ['bytes' => $bytes, 'sha256' => $sha256],
            'generated_at' => gmdate('c'),
        ];
    }

    private function buildArticleFileName(?string $lawTitle, string $num): string
    {
        $prefix = $lawTitle ? ($lawTitle.' - ') : '';

        return $prefix.sprintf('clanak-%s.pdf', $num);
    }

    private function makeSearchTags(?string $lawTitle, string $num, ?string $eli, ?string $pubDate, array $extraAttrs): array
    {
        $tags = array_values(array_filter([
            $lawTitle,
            'Članak '.$num,
            $eli,
            $pubDate,
        ]));
        foreach ($extraAttrs as $k => $v) {
            $tags[] = "{$k}:{$v}";
        }

        return array_unique($tags);
    }

    private function buildAttributes(?string $lawTitle, string $num, ?string $eli, ?string $pubDate, array $range, string $dest, array $extraAttrs): array
    {
        $attrs = array_merge([
            'title' => (string) ($lawTitle ?? ''),
            'article' => (string) $num,
            'eli' => (string) ($eli ?? ''),
            'publication_date' => (string) ($pubDate ?? ''),
            'pages' => ($range['start_page'] ?? null) && ($range['end_page'] ?? null)
                ? (($range['start_page']).'-'.($range['end_page']))
                : '',
            'sha256' => @hash_file('sha256', $dest) ?: '',
            'file_name' => basename($dest),
        ], $extraAttrs);

        // keywords za pretragu
        $attrs['keywords'] = implode(', ', $this->makeSearchTags($lawTitle, $num, $eli, $pubDate, $extraAttrs));

        return array_filter($attrs, fn ($v) => $v !== null && $v !== '');
    }

    private function maybeWriteSidecarAndXmp(string $pdfPath, array $attrs, bool $sidecar, bool $embedXmp): void
    {
        if ($sidecar) {
            $side = preg_replace('/\.pdf$/i', '.attrs.json', $pdfPath);
            file_put_contents($side, json_encode($attrs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        //        if ($embedXmp) {
        //            $this->embedJsonToPdfXmpDescription($pdfPath, $attrs);
        //        }
    }

    private function embedJsonToPdfXmpDescription(string $pdfIn, array $json): void
    {
        $bin = trim((string) shell_exec('which exiftool'));
        if ($bin === '') {
            return; // ignoriraj ako nema exiftool
        }
        $tmpJson = tempnam(sys_get_temp_dir(), 'attrs_').'.json';
        file_put_contents($tmpJson, json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        try {
            $args = [
                $bin,
                '-charset', 'UTF8',
                '-P',
                'XMP:Label=AI_Metadata',
                'XMP:MetadataDate=now',
                'XMP-dc:Description<='.$tmpJson,
                '-overwrite_original',
                $pdfIn,
            ];
            $proc = new Process($args, null, null, null, 60);
            $proc->run();
            // tihi fallback – bez izbacivanja iznimke
        } finally {
            @unlink($tmpJson);
        }
    }
}
