<?php

namespace App\DTOs\LegalArtillery;

/**
 * Immutable data transfer object representing the result of document validation.
 *
 * Contains validation scores, issues, suggestions, and strengths as evaluated
 * by the ArgumentValidator's LLM-based review.
 *
 * Verdicts: WEAK, MODERATE, STRONG, DEVASTATING, FIRE_READY, PARSE_ERROR
 */
class ValidationResult
{
    /**
     * Score threshold for considering a document valid.
     */
    public const VALIDITY_THRESHOLD = 6;

    /**
     * @param bool $isValid Whether the document passes validation threshold
     * @param int $score Overall validation score (1-10)
     * @param array<array{severity: string, description: string}> $issues Identified issues
     * @param array<string> $suggestions Improvement suggestions
     * @param array<string> $strengths Document strengths identified
     * @param string $verdict Validation verdict (WEAK|MODERATE|STRONG|DEVASTATING|FIRE_READY|PARSE_ERROR)
     * @param int $citationAccuracy Citation accuracy score (1-10)
     * @param int $argumentStrength Argument strength score (1-10)
     * @param int $logicalCoherence Logical coherence score (1-10)
     */
    public function __construct(
        public readonly bool $isValid,
        public readonly int $score,
        public readonly array $issues,
        public readonly array $suggestions,
        public readonly array $strengths,
        public readonly string $verdict,
        public readonly int $citationAccuracy,
        public readonly int $argumentStrength,
        public readonly int $logicalCoherence,
    ) {}

    /**
     * Create from an array (typically from parsed LLM response).
     *
     * @param array{
     *     is_valid?: bool,
     *     score?: int,
     *     overall_score?: int,
     *     issues?: array,
     *     suggestions?: array,
     *     improvements?: array,
     *     strengths?: array,
     *     verdict?: string,
     *     citation_accuracy?: int,
     *     argument_strength?: int,
     *     logical_coherence?: int
     * } $data
     */
    public static function fromArray(array $data): self
    {
        $score = $data['score'] ?? $data['overall_score'] ?? 0;
        $isValid = $data['is_valid'] ?? ($score >= self::VALIDITY_THRESHOLD);
        $suggestions = $data['suggestions'] ?? $data['improvements'] ?? [];

        return new self(
            isValid: $isValid,
            score: $score,
            issues: $data['issues'] ?? [],
            suggestions: $suggestions,
            strengths: $data['strengths'] ?? [],
            verdict: $data['verdict'] ?? self::calculateVerdict($score),
            citationAccuracy: $data['citation_accuracy'] ?? 0,
            argumentStrength: $data['argument_strength'] ?? 0,
            logicalCoherence: $data['logical_coherence'] ?? 0,
        );
    }

    /**
     * Create a failed validation result for parse errors.
     */
    public static function parseError(string $errorMessage): self
    {
        return new self(
            isValid: false,
            score: 0,
            issues: [
                ['severity' => 'critical', 'description' => $errorMessage],
            ],
            suggestions: [],
            strengths: [],
            verdict: 'PARSE_ERROR',
            citationAccuracy: 0,
            argumentStrength: 0,
            logicalCoherence: 0,
        );
    }

    /**
     * Convert to array representation.
     *
     * @return array{
     *     is_valid: bool,
     *     score: int,
     *     issues: array,
     *     suggestions: array,
     *     strengths: array,
     *     verdict: string,
     *     citation_accuracy: int,
     *     argument_strength: int,
     *     logical_coherence: int
     * }
     */
    public function toArray(): array
    {
        return [
            'is_valid' => $this->isValid,
            'score' => $this->score,
            'issues' => $this->issues,
            'suggestions' => $this->suggestions,
            'strengths' => $this->strengths,
            'verdict' => $this->verdict,
            'citation_accuracy' => $this->citationAccuracy,
            'argument_strength' => $this->argumentStrength,
            'logical_coherence' => $this->logicalCoherence,
        ];
    }

    /**
     * Check if the document is ready to fire (highest quality).
     */
    public function isFireReady(): bool
    {
        return $this->verdict === 'FIRE_READY';
    }

    /**
     * Check if there are any critical issues.
     */
    public function hasCriticalIssues(): bool
    {
        foreach ($this->issues as $issue) {
            if (($issue['severity'] ?? '') === 'critical') {
                return true;
            }
        }
        return false;
    }

    /**
     * Get count of issues by severity.
     *
     * @return array{critical: int, major: int, minor: int}
     */
    public function getIssueCounts(): array
    {
        $counts = ['critical' => 0, 'major' => 0, 'minor' => 0];

        foreach ($this->issues as $issue) {
            $severity = $issue['severity'] ?? 'minor';
            if (isset($counts[$severity])) {
                $counts[$severity]++;
            }
        }

        return $counts;
    }

    /**
     * Calculate verdict based on score.
     */
    private static function calculateVerdict(int $score): string
    {
        return match (true) {
            $score >= 9 => 'FIRE_READY',
            $score >= 8 => 'DEVASTATING',
            $score >= 6 => 'STRONG',
            $score >= 4 => 'MODERATE',
            default => 'WEAK',
        };
    }
}
