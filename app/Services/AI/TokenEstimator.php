<?php

namespace App\Services\AI;

/**
 * Estimates token count for text without requiring tiktoken library.
 *
 * Uses character-based heuristics with Unicode awareness.
 * More accurate than strlen/4 for non-ASCII text (Croatian, etc.)
 */
class TokenEstimator
{
    /**
     * Average characters per token for ASCII-heavy text (English).
     */
    private const ASCII_CHARS_PER_TOKEN = 4.0;

    /**
     * Average characters per token for Unicode-heavy text (Croatian, etc.)
     * Unicode characters often split into multiple tokens.
     */
    private const UNICODE_CHARS_PER_TOKEN = 2.5;

    /**
     * Estimate token count for given text.
     */
    public function estimate(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        $charCount = mb_strlen($text);
        $byteCount = strlen($text);

        // Ratio of bytes to chars indicates Unicode density
        // Pure ASCII: ratio = 1.0, Heavy Unicode: ratio > 1.5
        $unicodeRatio = $byteCount / max($charCount, 1);

        // Blend between ASCII and Unicode estimates based on actual content
        $asciiWeight = max(0, min(1, 1.0 - ($unicodeRatio - 1.0) * 2));
        $unicodeWeight = 1.0 - $asciiWeight;

        $charsPerToken = (self::ASCII_CHARS_PER_TOKEN * $asciiWeight)
                       + (self::UNICODE_CHARS_PER_TOKEN * $unicodeWeight);

        return (int) ceil($charCount / $charsPerToken);
    }
}
