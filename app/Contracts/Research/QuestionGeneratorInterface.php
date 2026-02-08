<?php

namespace App\Contracts\Research;

use App\Models\AgentRun;

/**
 * Interface for generating and refining research questions
 *
 * This component is responsible for planning the next research actions based on:
 * - The initial research objective
 * - Context from past insights
 * - Previous iteration results
 * - Available search tools and their capabilities
 */
interface QuestionGeneratorInterface
{
    /**
     * Generate research questions/actions from initial query
     *
     * @param  string  $query  The research objective
     * @param  array  $context  Additional context including:
     *                          - past_insights: Insights from previous research runs
     *                          - current_iteration: Current iteration number
     *                          - max_iterations: Maximum allowed iterations
     *                          - topics: Related topics
     * @return array Plan with structure:
     *               - reasoning: Explanation of why these actions make sense
     *               - actions: Array of actions to execute
     *               Each action has:
     *               - tool: Tool name (e.g., 'law_vector_search')
     *               - params: Parameters for the tool
     *               - rationale: Why this action helps achieve the objective
     */
    public function generate(string $query, array $context = []): array;

    /**
     * Refine questions based on previous results
     *
     * @param  array  $questions  Previous questions/actions that were executed
     * @param  array  $results  Results from executing those actions
     * @param  array  $evaluation  Evaluation of the previous iteration including:
     *                             - insights: Array of insights discovered
     *                             - insights_count: Number of insights found
     *                             - should_stop: Whether research should stop
     * @return array Updated plan with new/refined actions
     */
    public function refine(array $questions, array $results, array $evaluation): array;

    /**
     * Generate fallback actions when LLM planning fails
     *
     * @param  AgentRun  $run  The current research run
     * @return array Array of fallback actions
     */
    public function generateFallback(AgentRun $run): array;
}
