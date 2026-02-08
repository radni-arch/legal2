<?php

namespace App\DTOs;

/**
 * Result of iterative document refinement.
 *
 * Contains the final refined content, iteration count,
 * validation history, and accumulated improvements.
 */
class RefinementResult
{
    /**
     * @param string $finalContent The final refined document content
     * @param int $iterations Number of iterations performed
     * @param array<array{
     *     iteration: int,
     *     score: int,
     *     verdict: string,
     *     improvements: array<string>,
     *     issues: array<array{severity: string, description: string}>
     * }> $validationHistory History of all validation results
     * @param array<string> $improvements All improvements applied across iterations
     * @param string $finalVerdict Final validation verdict
     * @param int $finalScore Final validation score
     */
    public function __construct(
        public readonly string $finalContent,
        public readonly int $iterations,
        public readonly array $validationHistory,
        public readonly array $improvements,
        public readonly string $finalVerdict,
        public readonly int $finalScore,
    ) {}

    /**
     * Check if refinement achieved the target quality.
     */
    public function isFireReady(): bool
    {
        return $this->finalScore >= 8 || $this->finalVerdict === 'FIRE_READY';
    }

    /**
     * Check if max iterations were exhausted without achieving target.
     */
    public function wasExhausted(int $maxIterations): bool
    {
        return $this->iterations >= $maxIterations && !$this->isFireReady();
    }

    /**
     * Get score progression across iterations.
     *
     * @return array<int>
     */
    public function scoreProgression(): array
    {
        return array_column($this->validationHistory, 'score');
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'final_content' => $this->finalContent,
            'iterations' => $this->iterations,
            'validation_history' => $this->validationHistory,
            'improvements' => $this->improvements,
            'final_verdict' => $this->finalVerdict,
            'final_score' => $this->finalScore,
            'is_fire_ready' => $this->isFireReady(),
        ];
    }
}
