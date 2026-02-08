<?php

namespace App\Contracts\Research;

use App\Models\AgentRun;

/**
 * Interface for assessing overall research quality
 *
 * This component is responsible for:
 * - Evaluating iteration results
 * - Determining if research objectives are met
 * - Deciding when to stop research
 * - Synthesizing final research output
 */
interface QualityAssessorInterface
{
    /**
     * Assess overall research quality
     *
     * @param  string  $query  The research objective
     * @param  string  $answer  The synthesized answer/findings
     * @param  array  $evaluation  Evaluation data including:
     *                             - insights: Array of insights
     *                             - iterations: Number of iterations completed
     *                             - sources_count: Number of sources consulted
     * @return array Assessment with:
     *               - quality_score: Overall quality (0-1)
     *               - completeness_score: How complete the research is (0-1)
     *               - citation_quality: Quality of citations (0-1)
     *               - relevance_score: Relevance to objective (0-1)
     *               - strengths: Array of strengths
     *               - weaknesses: Array of weaknesses
     *               - recommendations: Suggestions for improvement
     */
    public function assess(string $query, string $answer, array $evaluation): array;

    /**
     * Check if answer is complete
     *
     * @param  array  $assessment  Assessment result from assess()
     * @return bool True if research is complete enough to stop
     */
    public function isComplete(array $assessment): bool;

    /**
     * Evaluate a single iteration
     *
     * @param  AgentRun  $run  The current research run
     * @param  array  $iteration  Iteration data including:
     *                            - actions: Actions executed
     *                            - plan: Plan for this iteration
     * @return array Evaluation with:
     *               - insights: Array of insights discovered
     *               - insights_count: Number of insights
     *               - should_stop: Whether to stop research
     */
    public function evaluateIteration(AgentRun $run, array $iteration): array;

    /**
     * Synthesize final research output
     *
     * @param  AgentRun  $run  The completed research run
     * @return string Final research report
     */
    public function synthesizeFinalOutput(AgentRun $run): string;
}
