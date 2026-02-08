<?php

namespace App\Services\Research;

/**
 * CitationGuard
 *
 * Why this exists:
 * - "Legal reasoning" can be subjective and model-dependent.
 * - But we can (and should) enforce a hard, deterministic contract that
 *   any AI-generated legal output contains at least one concrete citation
 *   pattern (case number, NN reference, article reference, ECLI, etc.).
 *
 * This is intentionally lightweight and CI-safe (no external calls).
 */
class CitationGuard
{
    /**
     * Conservative citation patterns for Croatian legal work.
     *
     * These are aligned with the patterns used in AnswerEvaluatorService::detectCitations
     * and extended with additional common Croatian forms.
     *
     * @return array<int, string>
     */
    public static function patterns(): array
    {
        return [
            // Case numbers like "Gž-1234/2023", "K-123/2024" etc.
            '/\b[\p{L}ČĆĐŠŽ]{1,5}-\d{1,6}\/\d{2,4}\b/iu',

            // Narodne novine references
            '/(?:NN|Narodne\s+novine)\s+\d+\/\d+/iu',

            // Article references (čl., članak)
            '/\bčl(?:anak|\.)\s*\d+[a-z]?\b/iu',

            // Common statute abbreviations + number (ZKP 220, KZ 190)
            '/\b(?:ZKP|KZ|ZPP|ZoSZOOD)\s*\d+\b/iu',

            // ECLI identifiers
            '/\bECLI:[A-Z]{2}:[A-Z0-9\.:_-]+\b/iu',
        ];
    }

    /**
     * Return true if text contains at least one recognizable legal citation.
     */
    public static function hasCitations(string $text): bool
    {
        $text = trim($text);
        if ($text === '') {
            return false;
        }

        foreach (self::patterns() as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract all citation snippets that match our patterns (best-effort).
     *
     * @return array<int, string>
     */
    public static function extract(string $text): array
    {
        $matches = [];

        foreach (self::patterns() as $pattern) {
            if (preg_match_all($pattern, $text, $m)) {
                foreach (($m[0] ?? []) as $hit) {
                    $hit = trim((string) $hit);
                    if ($hit !== '') {
                        $matches[] = $hit;
                    }
                }
            }
        }

        $matches = array_values(array_unique($matches));
        sort($matches);

        return $matches;
    }

    /**
     * Assert that a text is grounded by containing citations.
     *
     * @throws \InvalidArgumentException
     */
    public static function assertHasCitations(string $text, string $context = 'output'): void
    {
        if (! self::hasCitations($text)) {
            throw new \InvalidArgumentException("CitationGuard: missing citations in {$context}");
        }
    }
}
