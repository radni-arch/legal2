<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

class PdfRenderer
{
    public function renderArticle(array $ctx, string $destPath): void
    {
        $correlationId = request()->header('X-Request-ID') ?? Str::uuid()->toString();
        $startTime = microtime(true);

        Log::withContext(['correlation_id' => $correlationId]);

        Log::info('PdfRenderer renderArticle initiated', [
            'dest_path' => $destPath,
            'has_context' => ! empty($ctx),
            'law_title' => $ctx['law_title'] ?? null,
            'article_number' => $ctx['article_number'] ?? null,
            'user_id' => auth()->id(),
        ]);

        try {
            // $ctx sadrži: law_title, law_eli, law_pub_date, article_number, article_html, source_citation, etc.
            $html = View::make('pdf.article', $ctx)->render();

            Log::debug('PdfRenderer HTML generated', [
                'html_length' => strlen($html),
            ]);

            $pdf = Pdf::loadHTML($html)
                ->setPaper('a4', 'portrait');

            @mkdir(dirname($destPath), 0775, true);
            $pdf->save($destPath);

            $duration = (microtime(true) - $startTime) * 1000;

            $fileSize = file_exists($destPath) ? filesize($destPath) : 0;

            Log::info('PdfRenderer renderArticle completed', [
                'dest_path' => $destPath,
                'file_size_bytes' => $fileSize,
                'duration_ms' => round($duration, 2),
            ]);

            // Free memory after rendering each article
            unset($pdf, $html);
        } catch (\Exception $e) {
            Log::error('PdfRenderer renderArticle failed', [
                'dest_path' => $destPath,
                'law_title' => $ctx['law_title'] ?? null,
                'article_number' => $ctx['article_number'] ?? null,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
