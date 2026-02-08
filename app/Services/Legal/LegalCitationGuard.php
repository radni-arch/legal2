<?php

namespace App\Services\Legal;

/**
 * LegalCitationGuard
 *
 * Reasoning (why this exists):
 * - LLM-based legal reasoning is hard to unit test for *truth*, but we can test for *grounding*.
 * - In Croatian legal workflows, an uncited claim is dangerous: it can be hallucinated or unverifiable.
 *
 * This guard provides a deterministic, CI-safe heuristic for:
 * - detecting whether a text contains at least one recognizable Croatian legal citation.
 *
 * This intentionally does NOT claim legal correctness; it is a safety rail.
 */
class LegalCitationGuard
{
    /**
     * Check if text contains at least one legal citation-like pattern.
     *
     * Patterns are intentionally conservative and Croatian-focused.
     */
    public static function hasCitations(string $text): bool
    {
        $text = trim($text);
        if ($text === '') {
            return false;
        }

        $patterns = [
            // Case numbers like: Gž-1234/2023, K-123/2024, P-456/2025
            '/\b[A-ZČĆĐŠŽa-zčćđšž]{1,5}-\d{1,6}\/\d{2,4}\b/u',

            // Narodne novine references like: NN 35/05, Narodne novine 94/14
            '/\b(?:NN|Narodne\s+novine)\s+\d{1,3}\/\d{2,4}\b/iu',

            // ECLI identifiers
            '/\bECLI:HR:[A-ZČĆĐŠŽ]{3,12}:[0-9]{4}:[A-Z0-9.]{3,}\b/u',

            // Croatian law articles (common forms)
            '/\b(?:ZKP|KZ|ZOO|ZPP)\s*(?:Čl\.?|čl\.?|cl\.?|članak)\s*\d+\b/iu',
            '/\bUstav\s*RH\s*(?:Čl\.?|čl\.?|članak)\s*\d+\b/iu',
            '/\bČlanak\s+\d+\b/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Require at least one citation; throws if absent.
     */
    public static function assertHasCitations(string $text, string $message = 'Missing legal citations'): void
    {
        if (! self::hasCitations($text)) {
            throw new \InvalidArgumentException($message);
        }
    }
}
