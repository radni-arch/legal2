<?php

namespace App\Services\Graph;

/**
 * Extract legal principles from court decision text
 *
 * Uses pattern matching to identify:
 * - Explicit principle statements ("Sud utvrđuje pravno načelo...")
 * - Established judicial practice markers
 * - Ratio decidendi and obiter dicta indicators
 */
class LegalPrincipleExtractor
{
    /**
     * Croatian legal principle indicators
     */
    protected array $principlePatterns = [
        '/pravno\s+načelo/ui',
        '/temeljna\s+pravna\s+zasada/ui',
        '/sud\s+zauzima\s+stajalište/ui',
        '/ustaljen\w*\s+sudsk\w*\s+praks\w*/ui',  // Flexible Croatian forms
        '/opće\s+pravno\s+pravilo/ui',
        '/načelo\s+pravednosti/ui',
        '/načelo\s+jednakosti/ui',
        '/načelo\s+savjesnosti/ui',
        '/načelo\s+poštenja/ui',
    ];

    /**
     * Extract legal principles from text
     *
     * @param  string  $text  Decision text
     * @return array Extracted principles with metadata
     */
    public function extract(string $text): array
    {
        $principles = [];

        foreach ($this->principlePatterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    // Extract surrounding context (200 chars before/after)
                    $position = $match[1];
                    $start = max(0, $position - 200);
                    $length = min(mb_strlen($text) - $start, 500);
                    $context = mb_substr($text, $start, $length);

                    $principles[] = [
                        'pattern_matched' => $match[0],
                        'context' => trim($context),
                        'position' => $position,
                    ];
                }
            }
        }

        // Deduplicate by position (avoid counting same principle multiple times)
        $principles = $this->deduplicateByPosition($principles);

        return [
            'principles' => $principles,
            'count' => count($principles),
        ];
    }

    /**
     * Deduplicate principles that are too close together
     */
    protected function deduplicateByPosition(array $principles): array
    {
        if (empty($principles)) {
            return [];
        }

        usort($principles, fn ($a, $b) => $a['position'] <=> $b['position']);

        $result = [$principles[0]];
        $lastPosition = $principles[0]['position'];

        foreach ($principles as $principle) {
            // If more than 50 chars apart, consider it a different principle
            if ($principle['position'] - $lastPosition > 50) {
                $result[] = $principle;
                $lastPosition = $principle['position'];
            }
        }

        return $result;
    }
}
