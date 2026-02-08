<?php

namespace App\Support\Legal;

/**
 * CitationGuard
 *
 * Reasoning (why this exists):
 * - We can't reliably unit-test "legal reasoning correctness" for LLM output.
 * - We *can* enforce non-negotiable safety contracts:
 *   1) Outputs must contain Croatian legal citations (NN, čl., case refs).
 *   2) When outputs reference internal source IDs (decision/document chunks), those IDs must come
 *      from the retrieved context set (no invented IDs).
 *
 * This is intentionally lightweight and deterministic, suitable for CI.
 */
class CitationGuard
{
    /**
     * Heuristic detection of Croatian legal citations.
     *
     * Mirrors the project’s existing evaluation patterns (NN, čl., st.)
     * and common legal abbreviations.
     */
    public static function hasCroatianLegalCitations(string $text): bool
    {
        $text = trim($text);
        if ($text === '') {
            return false;
        }

        $patterns = [
            '/(?:NN|Narodne\s+novine)\s+\d+\/\d+/iu', // e.g., NN 94/14
            '/\bčl\.?\s*\d+/iu',                      // čl. 222
            '/\bst\.?\s*\d+/iu',                      // st. 2
            '/\b(?:ZKP|KZ|ZOO|Ustav(?:\s+RH)?)\b/iu',   // key law abbreviations
            '/\b[A-ZČĆĐŠŽ][a-zčćđšž]+-\d+\/\d+\b/u',   // e.g. Gž-1234/2023
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract internal-looking IDs from text.
     *
     * Intended for IDs like:
     * - ULID: 26 chars Crockford base32
     * - UUID: 36 chars with dashes
     * - Odluke IDs: long-ish hex with optional dashes (as the client accepts / extracts)
     */
    public static function extractReferencedIds(string $text): array
    {
        $text = (string) $text;

        $ids = [];

        // ULID (Crockford base32 without I,L,O,U)
        if (preg_match_all('/\b[0-9A-HJKMNP-TV-Z]{26}\b/', $text, $m)) {
            foreach ($m[0] as $x) {
                $ids[] = $x;
            }
        }

        // UUID
        if (preg_match_all('/\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/i', $text, $m)) {
            foreach ($m[0] as $x) {
                $ids[] = $x;
            }
        }

        // Odluke-like IDs (hex-ish with optional dashes, minimum 12 chars, must contain at least one hex letter)
        if (preg_match_all('/\b(?=[0-9a-fA-F-]{12,}\b)(?=.*[a-fA-F])[0-9a-fA-F]+(?:-[0-9a-fA-F]+)*\b/', $text, $m)) {
            foreach ($m[0] as $x) {
                $ids[] = $x;
            }
        }

        $ids = array_values(array_unique($ids));

        return $ids;
    }

    /**
     * Validate that every referenced ID in $text is in $allowedIds.
     *
     * @return array{ok: bool, referenced: array<int,string>, invalid: array<int,string>}
     */
    public static function validateReferencedIds(string $text, array $allowedIds): array
    {
        $allowed = array_fill_keys(array_map('strval', $allowedIds), true);
        $referenced = self::extractReferencedIds($text);

        $invalid = [];
        foreach ($referenced as $id) {
            if (! isset($allowed[(string) $id])) {
                $invalid[] = $id;
            }
        }

        return [
            'ok' => count($invalid) === 0,
            'referenced' => $referenced,
            'invalid' => $invalid,
        ];
    }
}
