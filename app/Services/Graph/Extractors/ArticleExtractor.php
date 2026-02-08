<?php

namespace App\Services\Graph\Extractors;

class ArticleExtractor
{
    /**
     * Patterns for Croatian law article references
     * NOTE: Order matters! Specific patterns (with paragraphs) must come BEFORE general patterns
     * Croatian declensions: članak (nom), članka (gen), članku (dat/loc), člankom (instr)
     */
    protected array $patterns = [
        // članak 1. stavak 2. (with paragraph - must come first!)
        '/[Čč]lan(ak|ka|ku|kom)\s+(\d+[a-z]?)\.?\s+stavak\s+(\d+)\.?/iu',
        // čl. 1. st. 2. (abbreviated with paragraph - must come first!)
        '/[Čč]l\.\s*(\d+[a-z]?)\.?\s+st\.\s*(\d+)\.?/iu',
        // Članak/Članku/Člankom 1., Članak 1.a, Članak 101. (all grammatical cases)
        '/[Čč]lan(ak|ka|ku|kom)\s+(\d+[a-z]?)\.?/iu',
        // Čl. 1., Čl. 1a
        '/[Čč]l\.\s*(\d+[a-z]?)\.?/iu',
        // Article 1 (English)
        '/Article\s+(\d+[a-z]?)\.?/i',
    ];

    /**
     * Extract article references from text
     *
     * @param string $text Text to analyze
     * @param string|null $lawId Optional law ID to associate articles with
     * @return array Array of extracted articles
     */
    public function extractReferences(string $text, ?string $lawId = null): array
    {
        if (empty(trim($text))) {
            return [];
        }

        $articles = [];
        $seen = [];

        foreach ($this->patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                foreach ($matches as $match) {
                    // For patterns with grammatical cases, match[1] is the case ending, match[2] is article number
                    // For patterns with paragraphs, match[3] would be the paragraph
                    // We need to find the first numeric capture group for article number
                    $articleNumber = null;
                    $paragraph = null;
                    $position = $match[0][1];

                    // Iterate through captures to find article number and paragraph
                    for ($i = 1; $i < count($match); $i++) {
                        if (isset($match[$i][0]) && preg_match('/^\d+[a-z]?$/', $match[$i][0])) {
                            if ($articleNumber === null) {
                                $articleNumber = $match[$i][0];
                            } else {
                                // Second numeric match is the paragraph
                                $paragraph = $match[$i][0];
                            }
                        }
                    }

                    // Create unique key to avoid duplicates
                    $key = $articleNumber . '_' . ($paragraph ?? 'null');
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;

                    // Extract context (surrounding text)
                    $context = $this->extractContext($text, $position, 100);

                    $articles[] = [
                        'article_number' => $articleNumber,
                        'paragraph' => $paragraph,
                        'context' => $context,
                        'law_id' => $lawId,
                        'id' => $this->generateArticleId($lawId, $articleNumber),
                    ];
                }
            }
        }

        // Sort by article number
        usort($articles, function ($a, $b) {
            return $this->compareArticleNumbers($a['article_number'], $b['article_number']);
        });

        return $articles;
    }

    /**
     * Extract article structure from a law document
     *
     * @param string $lawText The full text of the law
     * @param string $lawId The law document ID
     * @return array Array of article structures
     */
    public function extractStructure(string $lawText, string $lawId): array
    {
        if (empty(trim($lawText))) {
            return [];
        }

        $articles = [];
        $position = 0;

        // Pattern to find article headers and their content
        // Use negative lookahead to match everything until next article header
        $pattern = '/[Čč]lanak\s+(\d+[a-z]?)\.?\s*\n((?:(?!^\s*[Čč]lanak\s+\d).)*)/mus';

        if (preg_match_all($pattern, $lawText, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $position++;
                $articleNumber = $match[1]; // Article number
                $content = trim($match[2]); // Content

                // Count paragraphs (stavak)
                $paragraphCount = preg_match_all('/\(\d+\)|\bstavak\s+\d+/iu', $content);

                $articles[] = [
                    'id' => $this->generateArticleId($lawId, $articleNumber),
                    'law_id' => $lawId,
                    'article_number' => $articleNumber,
                    'title' => $this->extractArticleTitle($content),
                    'content' => $content,
                    'paragraph_count' => $paragraphCount,
                    'position' => $position,
                ];
            }
        }

        return $articles;
    }

    /**
     * Generate deterministic article ID
     */
    public function generateArticleId(?string $lawId, string $articleNumber): string
    {
        return 'article_' . md5(($lawId ?? 'unknown') . '_' . $articleNumber);
    }

    /**
     * Extract context around a position in text
     */
    protected function extractContext(string $text, int $position, int $length): string
    {
        $start = max(0, $position - $length);
        $end = min(strlen($text), $position + $length);

        $context = substr($text, $start, $end - $start);
        $context = preg_replace('/\s+/', ' ', trim($context));

        return $context;
    }

    /**
     * Extract article title if present (first line or parenthetical)
     */
    protected function extractArticleTitle(string $content): ?string
    {
        // Check for parenthetical title at start
        if (preg_match('/^\s*\(([^)]+)\)/', $content, $match)) {
            return trim($match[1]);
        }

        // First line might be a title if it's short
        $lines = explode("\n", trim($content));
        $firstLine = trim($lines[0] ?? '');
        if (strlen($firstLine) < 100 && !preg_match('/^\(\d+\)/', $firstLine)) {
            return $firstLine ?: null;
        }

        return null;
    }

    /**
     * Compare article numbers for sorting (handles 1, 1a, 2, 10, etc.)
     */
    protected function compareArticleNumbers(string $a, string $b): int
    {
        preg_match('/(\d+)([a-z]?)/', $a, $matchA);
        preg_match('/(\d+)([a-z]?)/', $b, $matchB);

        $numA = (int) ($matchA[1] ?? 0);
        $numB = (int) ($matchB[1] ?? 0);

        if ($numA !== $numB) {
            return $numA - $numB;
        }

        return strcmp($matchA[2] ?? '', $matchB[2] ?? '');
    }
}
