<?php

namespace App\Services\Agents;

use App\Contracts\Agents\IterationControllerInterface;
use Illuminate\Support\Facades\Log;

/**
 * Iteration Controller Service
 *
 * Controls research iteration loops and resource budgets.
 * Extracted from AutonomousResearchAgent as part of Phase 2 refactoring.
 *
 * Responsibilities:
 * - Determine when to continue/stop iterating
 * - Track resource usage (tokens, time)
 * - Enforce budgets and limits
 *
 * Target: 100-150 lines
 */
class IterationControllerService implements IterationControllerInterface
{
    /**
     * Reason for stopping iterations
     */
    protected string $stopReason = '';

    /**
     * Total tokens used across all iterations
     */
    protected int $totalTokensUsed = 0;

    /**
     * Total time used across all iterations (in seconds)
     */
    protected float $totalTimeUsed = 0;

    /**
     * Check if should continue iterating
     *
     * @param  int  $currentIteration  Current iteration number
     * @param  int  $qualityScore  Current quality score (0-100)
     * @param  array  $limits  Limits configuration:
     *                         - max_iterations: Maximum number of iterations
     *                         - quality_threshold: Stop if quality reaches this
     *                         - token_budget: Maximum tokens allowed
     *                         - time_budget: Maximum time in seconds
     * @return bool True if should continue, false if should stop
     */
    public function shouldContinue(int $currentIteration, int $qualityScore, array $limits): bool
    {
        // Check max iterations
        if (isset($limits['max_iterations']) && $currentIteration >= $limits['max_iterations']) {
            $this->stopReason = 'max_iterations';
            Log::info('Stopping: Max iterations reached', ['iterations' => $currentIteration]);

            return false;
        }

        // Check quality threshold
        if (isset($limits['quality_threshold']) && $qualityScore >= $limits['quality_threshold']) {
            $this->stopReason = 'quality_threshold_met';
            Log::info('Stopping: Quality threshold met', ['quality' => $qualityScore]);

            return false;
        }

        // Check token budget
        if (isset($limits['token_budget']) && $this->totalTokensUsed >= $limits['token_budget']) {
            $this->stopReason = 'token_budget_exceeded';
            Log::warning('Stopping: Token budget exceeded', [
                'used' => $this->totalTokensUsed,
                'budget' => $limits['token_budget'],
            ]);

            return false;
        }

        // Check time budget
        if (isset($limits['time_budget']) && $this->totalTimeUsed >= $limits['time_budget']) {
            $this->stopReason = 'time_budget_exceeded';
            Log::warning('Stopping: Time budget exceeded', [
                'used' => $this->totalTimeUsed,
                'budget' => $limits['time_budget'],
            ]);

            return false;
        }

        // Continue iterating
        return true;
    }

    /**
     * Get reason for stopping
     *
     * @return string Reason code: 'max_iterations', 'quality_threshold_met',
     *                'token_budget_exceeded', 'time_budget_exceeded', or empty if not stopped
     */
    public function getStopReason(): string
    {
        return $this->stopReason;
    }

    /**
     * Track resource usage
     *
     * @param  array  $usage  Usage data:
     *                        - tokens: Number of tokens used
     *                        - time: Time elapsed in seconds
     */
    public function trackUsage(array $usage): void
    {
        $this->totalTokensUsed += $usage['tokens'] ?? 0;
        $this->totalTimeUsed += $usage['time'] ?? 0;

        Log::debug('Resource usage tracked', [
            'total_tokens' => $this->totalTokensUsed,
            'total_time' => $this->totalTimeUsed,
        ]);
    }

    /**
     * Get total tokens used
     *
     * @return int Total tokens used across all iterations
     */
    public function getTotalTokens(): int
    {
        return $this->totalTokensUsed;
    }

    /**
     * Get total time used
     *
     * @return float Total time used in seconds
     */
    public function getTotalTime(): float
    {
        return $this->totalTimeUsed;
    }

    /**
     * Reset tracking state
     *
     * Clears all accumulated metrics and stop reason.
     * Use this when starting a new research session.
     */
    public function reset(): void
    {
        $this->stopReason = '';
        $this->totalTokensUsed = 0;
        $this->totalTimeUsed = 0;
    }
}
