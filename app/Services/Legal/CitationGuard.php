<?php

namespace App\Services\Legal;

/**
 * CitationGuard
 *
 * Reasoning:
 * - We cannot reliably unit-test "legal correctness" of an LLM.
 * - We *can* enforce hard safety/quality contracts that are prerequisite for trust:
 *   1) Answers must contain Croatian legal citations (law/article/case/ECLI/NN).
 *   2) Any citations mentioned in the answer must be present in the retrieved sources.
 *
 * This is intentionally simple (regex + string containment) so it stays deterministic
 * and CI-safe.
 */
class CitationGuard
{
    /**
     * Extract citation-like strings from text.
     *
     * @return array{ecli: string[], nn: string[], statutes: string[], case_numbers: string[]}
     */
    public static function extract(string $text): array
    {
        $text = (string) $text;

        $patterns = [
            'ecli' => '/\bECLI:[A-Z]{2}:[A-Z0-9]+:\d{4}:[A-Z0-9._-]+\b/u',
            'nn' => '/\b(?:NN|Narodne\s+novine)\s+\d+\/\d+\b/iu',

            // Statute/article references (Croatian)
            // Examples: "ZKP Čl. 222", "KZ Čl. 190", "Članak 34"
            'statutes' => '/\b(?:ZKP|KZ|ZPP|ZOO|Ustav\s+RH)\s*(?:Čl\.?|Članak)?\s*\d+\b/iu',

            // Case numbers (Croatian courts)
            // Examples: "K-123/2024", "Pp-12/2025", "Gž-1234/2023"
            'case_numbers' => '/\b(?:[A-ZČĆĐŠŽ]{1,3}ž?|Pp|K|Kr|Kž|Us|U|Gž|Rev|I\.?\s*Kr|II\.?\s*Kr)\s*-?\s*\d+\/\d{4}\b/iu',
        ];

        $out = [
            'ecli' => [],
            'nn' => [],
            'statutes' => [],
            'case_numbers' => [],
        ];

        foreach ($patterns as $key => $pattern) {
            if (preg_match_all($pattern, $text, $m)) {
                $out[$key] = array_values(array_unique(array_map('trim', $m[0])));
            }
        }

        return $out;
    }

    /**
     * Quick boolean check.
     */
    public static function hasAnyCitation(string $text): bool
    {
        $c = self::extract($text);

        return count($c['ecli']) + count($c['nn']) + count($c['statutes']) + count($c['case_numbers']) > 0;
    }

    /**
     * Verify that any citation-like strings in the answer also appear in the sources.
     *
     * @param  array<int, mixed>  $sources  retrieved sources (arrays or strings)
     */
    public static function citationsAreGroundedInSources(string $answer, array $sources): bool
    {
        $citations = self::extract($answer);
        $all = array_merge($citations['ecli'], $citations['nn'], $citations['statutes'], $citations['case_numbers']);

        // If there are no citations, we treat this as not grounded.
        if (empty($all)) {
            return false;
        }

        $haystack = self::sourcesToSearchableText($sources);

        foreach ($all as $citation) {
            if (! str_contains(mb_strtolower($haystack), mb_strtolower($citation))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, mixed>  $sources
     */
    protected static function sourcesToSearchableText(array $sources): string
    {
        $parts = [];
        foreach ($sources as $s) {
            if (is_string($s)) {
                $parts[] = $s;
                continue;
            }
            if (is_array($s)) {
                // Prefer common fields, but fall back to JSON encoding.
                $parts[] = (string) ($s['content'] ?? $s['text'] ?? '');
                $parts[] = is_array($s['metadata'] ?? null) ? json_encode($s['metadata'], JSON_UNESCAPED_UNICODE) : (string) ($s['metadata'] ?? '');
                $parts[] = json_encode($s, JSON_UNESCAPED_UNICODE);
                continue;
            }

            // best-effort fallback
            $parts[] = json_encode($s, JSON_UNESCAPED_UNICODE);
        }

        return implode("\n\n---\n\n", array_filter($parts, fn ($x) => is_string($x) && trim($x) !== ''));
    }
}
