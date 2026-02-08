<?php

namespace App\Services\LegalCitations;

/**
 * CitationGuard
 *
 * Opinionated, deterministic grounding helper for legal outputs.
 *
 * Goal: make "domain usefulness" testable without calling LLMs.
 *
 * This intentionally does NOT attempt to be a perfect Croatian legal citation parser.
 * It is a conservative guardrail used to:
 *  - detect whether an answer contains any legal citations at all
 *  - extract a few common identifier forms (ECLI, case numbers, NN refs, UUIDs)
 *  - verify cited identifiers exist in a provided allowed set (e.g., retrieved sources)
 */
class CitationGuard
{
    /**
     * Extract common legal citation strings.
     *
     * @return array<int,string>
     */
    public static function extractCitations(string $text): array
    {
        $text = (string) $text;
        if (trim($text) === '') {
            return [];
        }

        $matches = [];

        $patterns = [
            // ECLI identifiers
            '/\bECLI:[A-Z]{2}:[A-Z0-9]+:\d{4}:[A-Z0-9]+\b/i',

            // Narodne novine
            '/\b(?:NN|Narodne\s+novine)\s+\d{1,4}\/\d{2,4}\b/iu',

            // Common Croatian case number formats: K-123/2024, Gž-1234/2023, Rev-12/2020
            '/\b[A-ZČĆĐŠŽ][a-zčćđšž]{0,3}-\d+\/\d{4}\b/u',

            // Article references: "čl. 222", "Članak 34"
            '/\b(?:čl\.|cl\.|članak)\s*\d+\b/iu',

            // UUIDs (often used as external decision IDs)
            '/\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $m)) {
                foreach ($m[0] as $raw) {
                    $raw = trim((string) $raw);
                    if ($raw !== '') {
                        $matches[] = $raw;
                    }
                }
            }
        }

        // Normalize and de-duplicate
        $matches = array_values(array_unique(array_map(function ($x) {
            return preg_replace('/\s+/u', ' ', trim((string) $x));
        }, $matches)));

        return $matches;
    }

    /**
     * Extract "source identifiers" that should map to retrieved sources.
     *
     * Intentionally excludes generic "čl." references (those must be checked via other mechanisms
     * like MCP law_get_article tooling). Here we focus on identifiers that can be cross-validated
     * against retrieved source lists.
     *
     * @return array<int,string>
     */
    public static function extractSourceIdentifiers(string $text): array
    {
        $all = self::extractCitations($text);

        return array_values(array_filter($all, function ($c) {
            return (bool) (
                preg_match('/^ECLI:/i', $c)
                || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $c)
                || preg_match('/^[A-ZČĆĐŠŽ][a-zčćđšž]{0,3}-\d+\/\d{4}$/u', $c)
                || preg_match('/^(?:NN|Narodne\s+novine)\s+\d{1,4}\/\d{2,4}$/iu', $c)
            );
        }));
    }

    public static function hasAnyCitation(string $text): bool
    {
        return count(self::extractCitations($text)) > 0;
    }

    /**
     * Validate that all cited "source identifiers" are present in an allowed set.
     *
     * @param  array<int,string>  $allowedIdentifiers
     * @return array{ok: bool, found: array<int,string>, missing: array<int,string>}
     */
    public static function validateSourceIdentifiers(string $text, array $allowedIdentifiers): array
    {
        $allowedIdentifiers = array_values(array_unique(array_map('strval', $allowedIdentifiers)));
        $allowedLookup = array_fill_keys($allowedIdentifiers, true);

        $found = self::extractSourceIdentifiers($text);
        $missing = [];

        foreach ($found as $id) {
            if (! isset($allowedLookup[$id])) {
                $missing[] = $id;
            }
        }

        $missing = array_values(array_unique($missing));

        return [
            'ok' => count($missing) === 0,
            'found' => $found,
            'missing' => $missing,
        ];
    }
}
