<?php

namespace App\DTOs\LegalArtillery;

/**
 * Immutable data transfer object representing the result of argument chain validation.
 *
 * Contains the overall chain score, individual argument scores, identified weak links,
 * and prioritized improvement suggestions.
 */
class ChainValidationResult
{
    /**
     * @param int $chainScore Overall chain validation score (0-100)
     * @param array<array{step: int, score: int, reasoning: string}> $argumentScores Individual scores per argument step
     * @param array<array{step: int, issue: string}> $weakLinks Identified weak links in the chain
     * @param array<string> $improvementPriority Prioritized list of improvement suggestions
     */
    public function __construct(
        public readonly int $chainScore,
        public readonly array $argumentScores,
        public readonly array $weakLinks,
        public readonly array $improvementPriority,
    ) {}

    /**
     * Create from an array (typically from parsed LLM response).
     *
     * @param array{
     *     chain_score: int,
     *     argument_scores: array<array{step: int, score: int, reasoning: string}>,
     *     weak_links: array<array{step: int, issue: string}>,
     *     improvement_priority: array<string>
     * } $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            chainScore: $data['chain_score'] ?? 0,
            argumentScores: $data['argument_scores'] ?? [],
            weakLinks: $data['weak_links'] ?? [],
            improvementPriority: $data['improvement_priority'] ?? [],
        );
    }

    /**
     * Create a default/failed result when validation fails.
     */
    public static function failed(): self
    {
        return new self(
            chainScore: 0,
            argumentScores: [],
            weakLinks: [],
            improvementPriority: [],
        );
    }

    /**
     * Convert to array representation.
     *
     * @return array{
     *     chain_score: int,
     *     argument_scores: array<array{step: int, score: int, reasoning: string}>,
     *     weak_links: array<array{step: int, issue: string}>,
     *     improvement_priority: array<string>
     * }
     */
    public function toArray(): array
    {
        return [
            'chain_score' => $this->chainScore,
            'argument_scores' => $this->argumentScores,
            'weak_links' => $this->weakLinks,
            'improvement_priority' => $this->improvementPriority,
        ];
    }

    /**
     * Check if the chain score exceeds a given threshold.
     *
     * @param int $threshold The minimum score threshold (0-100)
     */
    public function exceedsThreshold(int $threshold): bool
    {
        return $this->chainScore >= $threshold;
    }

    /**
     * Check if the chain has any weak links.
     */
    public function hasWeakLinks(): bool
    {
        return !empty($this->weakLinks);
    }

    /**
     * Get the verdict based on chain score.
     *
     * @return string One of: WEAK, MODERATE, STRONG, DEVASTATING, FIRE_READY
     */
    public function getVerdict(): string
    {
        return match (true) {
            $this->chainScore >= 95 => 'FIRE_READY',
            $this->chainScore >= 85 => 'DEVASTATING',
            $this->chainScore >= 70 => 'STRONG',
            $this->chainScore >= 50 => 'MODERATE',
            default => 'WEAK',
        };
    }
}
