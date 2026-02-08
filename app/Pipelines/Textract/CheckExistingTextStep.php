<?php

namespace App\Pipelines\Textract;

use App\Contracts\Ocr\PdfTextExtractorInterface;
use App\Models\TextractJob;
use App\Services\Ocr\PdfTextExtractor;
use Closure;
use Illuminate\Support\Facades\Log;

/**
 * Step: CheckExistingTextStep
 *
 * Checks if the PDF already contains extractable text using pdftotext.
 * If words per page meets or exceeds the configurable minimum threshold
 * (ocr.min_words_per_page_skip, default 50), routes to OcrmypdfService
 * with --skip-text flag instead of AWS Textract, saving ~$1.50/1000 pages.
 *
 * Adds to payload:
 *   - skip_textract: bool - whether to skip Textract
 *   - ocr_route: string - 'textract' or 'ocrmypdf_skip_text'
 *   - existing_text_coverage: float - ratio of actual words per page vs threshold
 *   - ocr_routing_metadata: array - detailed routing decision metadata
 *   - pdftotext_error: string|null - error message if extraction failed
 */
class CheckExistingTextStep
{
    /**
     * Default minimum words per page to consider a document "text-rich".
     * Legal documents typically have 250-500 words per page.
     * Used as fallback when ocr.min_words_per_page_skip config is not set.
     */
    private const EXPECTED_WORDS_PER_PAGE = 50;

    public function __construct(
        private ?PdfTextExtractorInterface $extractor = null
    ) {
        $this->extractor = $extractor ?? app(PdfTextExtractor::class);
    }

    public function handle(array $payload, Closure $next): mixed
    {
        $driveFileId = (string) ($payload['driveFileId'] ?? 'unknown');
        $localPath = $payload['localPath'] ?? null;
        $forceTextract = (bool) ($payload['forceTextract'] ?? false);

        // If forceTextract is set, skip this check entirely
        if ($forceTextract) {
            Log::info('CheckExistingTextStep: forceTextract enabled, skipping text check', [
                'driveFileId' => $driveFileId,
            ]);

            $payload['skip_textract'] = false;
            $payload['ocr_route'] = 'textract';

            return $next($payload);
        }

        // If no local path, we can't check text - proceed to Textract
        if ($localPath === null || ! is_file($localPath)) {
            Log::info('CheckExistingTextStep: no local file, proceeding to Textract', [
                'driveFileId' => $driveFileId,
                'localPath' => $localPath,
            ]);

            $payload['skip_textract'] = false;
            $payload['ocr_route'] = 'textract';

            return $next($payload);
        }

        // Extract text using pdftotext
        $extraction = $this->extractor->extract($localPath);

        if (! $extraction['success']) {
            Log::warning('CheckExistingTextStep: pdftotext failed, proceeding to Textract', [
                'driveFileId' => $driveFileId,
                'error' => $extraction['error'],
            ]);

            $payload['skip_textract'] = false;
            $payload['ocr_route'] = 'textract';
            $payload['pdftotext_error'] = $extraction['error'];

            return $next($payload);
        }

        // Calculate coverage
        $text = $extraction['text'];
        $pageCount = max(1, $extraction['page_count']);
        $wordCount = $this->countWords($text);
        $wordsPerPage = $wordCount / $pageCount;

        // Get min words per page threshold for skipping Textract (default 50)
        // If the document has at least this many words per page, it has
        // enough embedded text to skip AWS Textract and use ocrmypdf instead.
        $minWordsPerPage = (int) config('ocr.min_words_per_page_skip', self::EXPECTED_WORDS_PER_PAGE);

        // Coverage: ratio of actual words per page to threshold baseline
        // Capped at 1.0 (100%) - used for reporting/metadata
        $coverage = min(1.0, $wordsPerPage / max(1, $minWordsPerPage));

        // Skip decision: direct words-per-page comparison against threshold
        $shouldSkipTextract = $wordsPerPage >= $minWordsPerPage;

        Log::info('CheckExistingTextStep: text coverage analysis', [
            'driveFileId' => $driveFileId,
            'word_count' => $wordCount,
            'page_count' => $pageCount,
            'words_per_page' => round($wordsPerPage, 2),
            'coverage' => round($coverage, 4),
            'min_words_per_page_skip' => $minWordsPerPage,
            'skip_textract' => $shouldSkipTextract,
        ]);

        // Set routing decision
        $payload['skip_textract'] = $shouldSkipTextract;
        $payload['ocr_route'] = $shouldSkipTextract ? 'ocrmypdf_skip_text' : 'textract';
        $payload['existing_text_coverage'] = $coverage;

        // Set detailed routing metadata
        $routingMetadata = [
            'skipped_textract' => $shouldSkipTextract,
            'existing_text_coverage' => round($coverage, 4),
            'words_per_page' => round($wordsPerPage, 2),
            'total_words' => $wordCount,
            'page_count' => $pageCount,
            'threshold_used' => $minWordsPerPage,
            'decision_reason' => $shouldSkipTextract
                ? "Words per page " . round($wordsPerPage, 2) . " >= min threshold {$minWordsPerPage}"
                : "Words per page " . round($wordsPerPage, 2) . " < min threshold {$minWordsPerPage}",
        ];
        $payload['ocr_routing_metadata'] = $routingMetadata;

        // If skipping Textract, store the pre-extracted text for potential use
        if ($shouldSkipTextract) {
            $payload['pdftotext_text'] = $text;
        }

        // Update job metadata if available
        if (isset($payload['job']) && $payload['job'] instanceof TextractJob) {
            $metadata = $payload['job']->metadata ?? [];
            $metadata['ocr_routing_metadata'] = $routingMetadata;
            $payload['job']->update(['metadata' => $metadata]);
        }

        return $next($payload);
    }

    /**
     * Count words in text.
     */
    private function countWords(string $text): int
    {
        $text = trim($text);
        if ($text === '') {
            return 0;
        }

        // Split on whitespace, filter empty strings
        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

        return count($words);
    }
}
