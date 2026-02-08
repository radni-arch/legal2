<?php

namespace App\Contracts\Research;

use App\Models\AgentRun;

/**
 * Interface for controlling research iterations
 *
 * This component is responsible for:
 * - Deciding whether to continue research
 * - Managing iteration parameters
 * - Enforcing budget and time constraints
 * - Coordinating the research loop
 */
interface IterationControllerInterface
{
    /**
     * Check if research should continue
     *
     * @param  int  $iteration  Current iteration number
     * @param  float  $qualityScore  Current quality score (0-1)
     * @param  array  $limits  Constraint limits including:
     *                         - max_iterations: Maximum iterations allowed
     *                         - token_budget: Token budget limit
     *                         - tokens_used: Tokens used so far
     *                         - cost_budget: Cost budget limit (USD)
     *                         - cost_spent: Cost spent so far
     *                         - time_limit_seconds: Time limit
     *                         - elapsed_seconds: Time elapsed
     *                         - threshold: Quality threshold to meet
     * @return bool True if should continue, false otherwise
     */
    public function shouldContinue(int $iteration, float $qualityScore, array $limits): bool;

    /**
     * Get next iteration parameters
     *
     * @param  int  $currentIteration  Current iteration number
     * @param  array  $results  Results from current iteration including:
     *                          - insights: Insights discovered
     *                          - quality_score: Quality of results
     *                          - actions_taken: Number of actions executed
     * @return array Parameters for next iteration:
     *               - suggested_actions_count: How many actions to plan
     *               - focus_areas: Areas to focus on
     *               - search_strategy: Suggested search strategy
     */
    public function nextIteration(int $currentIteration, array $results): array;

    /**
     * Check if agent should continue based on run state
     *
     * @param  AgentRun  $run  The current research run
     * @return bool True if should continue, false otherwise
     */
    public function shouldContinueRun(AgentRun $run): bool;

    /**
     * Execute the full research run loop
     *
     * @param  AgentRun  $run  The run to execute
     * @param  callable  $iterationCallback  Callback to execute each iteration
     *                                       Receives (AgentRun $run) and returns iteration array
     * @return AgentRun The completed/updated run
     */
    public function executeRunLoop(AgentRun $run, callable $iterationCallback): AgentRun;
}
