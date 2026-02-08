<?php

namespace App\Services;

use Symfony\Component\DomCrawler\Crawler;

class LawParser
{
    /**
     * Split law HTML into individual articles.
     *
     * Handles multiple article number formats:
     * - Standard: "Članak 24."
     * - Uppercase: "CLANAK 24."
     * - Lettered: "Članak 24a", "Članak 24.a", "Članak 24. a)"
     * - With delimiters: "Članak 24)", "Članak 24("
     *
     * Special handling:
     * - Non-breaking spaces (U+00A0) are normalized to regular spaces
     * - NN markers like "(NN 123/20)" are preserved in article body
     * - Lettered articles (24a, 24b) are merged into their base article (24)
     * - Nested HTML tags and wrappers are handled
     * - Missing <body> tags are handled gracefully
     *
     * @param  string  $html  Raw HTML content of the law
     * @return array Array of articles with 'number', 'heading_chain', and 'html' keys
     */
    public function splitIntoArticles(string $html): array
    {
        // Validate input
        if (empty(trim($html))) {
            return [['number' => '1', 'heading_chain' => [], 'html' => '']];
        }

        $crawler = new Crawler($html);
        $bodyHtml = $crawler->filter('body')->count() ? $crawler->filter('body')->html() : $html;

        // Normalize spaces: convert non-breaking spaces and normalize whitespace
        // Keep NN markers in body intact
        $normalized = preg_replace('/\xC2\xA0/u', ' ', $bodyHtml); // no-break space (U+00A0)
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        // Improved regex to match all article header variants:
        // - "Članak 24." or "CLANAK 24."
        // - "Članak 24a" or "CLANAK 24a"
        // - "Članak 24.a" or "Članak 24. a"
        // - "Članak 24. a)" with parenthesis
        // - "Članak 24)" or "Članak 24(" with delimiter
        // This ensures a break before any article header
        $normalized = preg_replace(
            '/(Članak|CLANAK)\s+(\d+)(?:\.(?:\s*[a-z])?|[a-z])?(?:\(|\s*\)|\s*)?/u',
            "\n$0",
            $normalized
        );

        // Split before each article heading with improved pattern
        // Supports all variants: "24.", "24.a", "24a", "24. a)", "24)", "24("
        $splitRegex = '/(?=\s*(?:<[^>]+>\s*)*(Članak|CLANAK)\s+\d+(?:\.(?:\s*[a-z])?|[a-z])?(?:\(|\s*\)|\s*)?)/u';
        $chunks = preg_split($splitRegex, $normalized, -1, PREG_SPLIT_NO_EMPTY);

        // Improved header pattern to match at the start of chunk
        // Captures: base number and optional letter (with or without dot/space)
        $headerAtStart = '/^\s*(?:<[^>]+>\s*)*(Članak|CLANAK)\s+(\d+)(?:\.(?:\s*([a-z]))?|([a-z]))?(?:\(|\s*\)|\s*)?/u';

        $articles = [];
        foreach ($chunks as $chunk) {
            if (! preg_match($headerAtStart, $chunk, $m)) {
                // Preamble or trailing text: append to previous article if any
                if (! empty($articles)) {
                    $articles[array_key_last($articles)]['html'] .= $chunk;
                }

                continue;
            }

            $base = $m[2];
            $letter = ! empty($m[3]) ? $m[3] : (! empty($m[4]) ? $m[4] : null);

            // Check if letter has space before it (e.g., "24. a)" vs "24.a")
            // Letters with space are explicitly formatted and should be kept
            // Also keep letters from uppercase "CLANAK" (official documents)
            $hasSpaceBeforeLetter = false;
            if ($letter) {
                if (! empty($m[3]) && preg_match('/\.\s+'.preg_quote($m[3], '/').'/', $m[0])) {
                    $hasSpaceBeforeLetter = true;
                } elseif ($m[1] === 'CLANAK') {
                    // Uppercase CLANAK indicates official source - keep letters
                    $hasSpaceBeforeLetter = true;
                }
            }

            // Normalize header text: "24." or "24.a"
            $numberText = $letter ? ($base.'.'.$letter) : ($base.'.');

            // Remove only the first header occurrence; keep "(NN ...)" in the body
            $body = preg_replace($headerAtStart, '', $chunk, 1);

            $articles[] = [
                'number' => $letter ? ($base.$letter) : (string) $base,
                'heading_chain' => [],
                'html' => '<h3>Članak '.$numberText.'</h3>'.$body,
                '_keep_letter' => $hasSpaceBeforeLetter, // metadata for merging logic
            ];
        }

        if (empty($articles)) {
            return [['number' => '1', 'heading_chain' => [], 'html' => $normalized]];
        }

        // Merge lettered articles (e.g., 24a, 24b) into their base (24), preserving headings
        $merged = [];
        foreach ($articles as $art) {
            if (preg_match('/^(\d+)([a-z])$/u', $art['number'], $nm)) {
                $base = $nm[1];
                $keepLetter = $art['_keep_letter'] ?? false;

                if (! empty($merged) && $merged[array_key_last($merged)]['number'] === $base) {
                    // Append lettered article content to the base article
                    $merged[array_key_last($merged)]['html'] .= $art['html'];

                    continue;
                }

                // No preceding base: strip letter unless explicitly formatted with space
                if (! $keepLetter) {
                    $art['number'] = $base;
                }
                unset($art['_keep_letter']); // Remove metadata
                $merged[] = $art;

                continue;
            }

            // Pure numeric article starts a new chunk
            unset($art['_keep_letter']); // Remove metadata
            $merged[] = $art;
        }

        return $merged;
    }
}
