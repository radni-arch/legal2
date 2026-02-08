<?php

namespace App\Services\Graph;

/**
 * Service for automatic detection of precedent relationships from legal text
 *
 * Analyzes decision text to identify references to other decisions and
 * determines the type of precedent relationship based on contextual indicators.
 *
 * Detects Croatian legal patterns:
 * - OVERRULES: "ukida" / "ukinuto"
 * - CONFIRMS: "potvrđuje" / "potvrđeno"
 * - MODIFIES: "preinačuje" / "preinačeno"
 * - FOLLOWS: "slijedi" / "primjenjuje" / "prema ustaljenoj praksi"
 * - DISTINGUISHES: "razlikuje se" / "ne primjenjuje se"
 */
class PrecedentDetector
{
    /**
     * Detection patterns for each relationship type
     *
     * @var array<string, array<string>>
     */
    protected array $patterns = [
        'OVERRULES' => [
            '/\bukida\b.*?(\b(?:Rev|Pž|Gž)\s*[-\s]*\d+[-\/]\d{4})/ui',
            '/\bukinuto\b.*?(\b(?:Rev|Pž|Gž)\s*[-\s]*\d+[-\/]\d{4})/ui',
        ],
        'CONFIRMS' => [
            '/\bpotvrđuje\b.*?(\b(?:Rev|Pž|Gž)\s*[-\s]*\d+[-\/]\d{4})/ui',
            '/\bpotvrđeno\b.*?(\b(?:Rev|Pž|Gž)\s*[-\s]*\d+[-\/]\d{4})/ui',
        ],
        'MODIFIES' => [
            '/\bpreinačuje\b.*?(\b(?:Rev|Pž|Gž)\s*[-\s]*\d+[-\/]\d{4})/ui',
            '/\bpreinačeno\b.*?(\b(?:Rev|Pž|Gž)\s*[-\s]*\d+[-\/]\d{4})/ui',
        ],
        'FOLLOWS' => [
            '/\bslijedi\b.*?(\b(?:Rev|Pž|Gž)\s*[-\s]*\d+[-\/]\d{4})/ui',
            '/(?<!\bne\s)\bprimjenjuje\b.*?(\b(?:Rev|Pž|Gž)\s*[-\s]*\d+[-\/]\d{4})/ui',
            '/prema\s+ustaljenoj\s+praksi.*?(\b(?:Rev|Pž|Gž)\s*[-\s]*\d+[-\/]\d{4})/ui',
        ],
        'DISTINGUISHES' => [
            '/\brazlikuje\s+(?:se\s+)?(?:od|prema)\b.*?(\b(?:Rev|Pž|Gž)\s*[-\s]*\d+[-\/]\d{4})/ui',
            '/\bne\s+primjenjuje\s+se\b.*?\bpredmet(?:u|a)?\s+(\b(?:Rev|Pž|Gž)\s*[-\s]*\d+[-\/]\d{4})/ui',
        ],
    ];

    /**
     * Context window size (characters before and after match)
     */
    protected int $contextWindow = 50;

    /**
     * Detect precedent relationships in text
     *
     * @param  string  $sourceId  The ID of the decision being analyzed
     * @param  string  $text  The text to analyze
     * @return array<int, array{source_id: string, target_case_number: string, relationship_type: string, context: string, confidence: float}>
     */
    public function detect(string $sourceId, string $text): array
    {
        if (empty($text)) {
            return [];
        }

        $detected = [];

        foreach ($this->patterns as $relationshipType => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
                    foreach ($matches[1] as $match) {
                        $caseNumber = $this->normalizeCaseNumber($match[0]);
                        $context = $this->extractContext($text, $match[1]);
                        $confidence = $this->calculateConfidence($relationshipType, $context);

                        $detected[] = [
                            'source_id' => $sourceId,
                            'target_case_number' => $caseNumber,
                            'relationship_type' => $relationshipType,
                            'context' => $context,
                            'confidence' => $confidence,
                        ];
                    }
                }
            }
        }

        return $detected;
    }

    /**
     * Normalize case number format
     */
    protected function normalizeCaseNumber(string $caseNumber): string
    {
        // Normalize spacing and separators
        return preg_replace('/\s+/', ' ', trim($caseNumber));
    }

    /**
     * Extract context around match position
     */
    protected function extractContext(string $text, int $position): string
    {
        $start = max(0, $position - $this->contextWindow);
        $length = $this->contextWindow * 2;

        $context = mb_substr($text, $start, $length, 'UTF-8');

        // Clean up context
        return trim($context);
    }

    /**
     * Calculate confidence score for detected relationship
     *
     * @param  string  $relationshipType
     * @param  string  $context
     * @return float Confidence score between 0 and 1
     */
    protected function calculateConfidence(string $relationshipType, string $context): float
    {
        // Base confidence score
        $confidence = 0.7;

        // Increase confidence if context contains additional legal terms
        $legalTerms = [
            'sud',
            'odluka',
            'rješenje',
            'presuda',
            'pravomoćno',
            'Vrhovni sud',
            'žalbeni sud',
        ];

        foreach ($legalTerms as $term) {
            if (mb_stripos($context, $term) !== false) {
                $confidence += 0.05;
            }
        }

        // Cap at 0.95 (never fully certain from text alone)
        return min(0.95, $confidence);
    }
}
