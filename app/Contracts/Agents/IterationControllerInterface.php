<?php

namespace App\Contracts\Agents;

/**
 * Interface for controlling research iteration loops
 *
 * This component is responsible for:
 * - Determining when to continue/stop iterating
 * - Tracking resource usage (tokens, time)
 * - Enforcing budgets and limits
 */
interface IterationControllerInterface
{
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
    public function shouldContinue(int $currentIteration, int $qualityScore, array $limits): bool;

    /**
     * Get reason for stopping
     *
     * @return string Reason code: 'max_iterations', 'quality_threshold_met',
     *                'token_budget_exceeded', 'time_budget_exceeded', or empty if not stopped
     */
    public function getStopReason(): string;

    /**
     * Track resource usage
     *
     * @param  array  $usage  Usage data:
     *                        - tokens: Number of tokens used
     *                        - time: Time elapsed in seconds
     */
    public function trackUsage(array $usage): void;

    /**
     * Get total tokens used
     *
     * @return int Total tokens used across all iterations
     */
    public function getTotalTokens(): int;

    /**
     * Get total time used
     *
     * @return float Total time used in seconds
     */
    public function getTotalTime(): float;

    /**
     * Reset tracking state
     */
    public function reset(): void;
}
