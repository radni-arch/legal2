<?php

namespace App\Contracts\Services;

/**
 * AgentEvaluationServiceInterface
 *
 * Provides evaluation capabilities for autonomous agent runs including:
 * - Multi-dimensional quality scoring
 * - Iterative improvement assessment
 * - Evaluation report generation
 */
interface AgentEvaluationServiceInterface
{
    /**
     * Evaluate an agent run output
     *
     * Performs comprehensive evaluation across multiple dimensions:
     * - Relevance: How well the output addresses the objective
     * - Completeness: Coverage of all aspects
     * - Accuracy: Factual correctness and citation quality
     * - Clarity: Structure and presentation
     * - Novelty: New insights vs existing knowledge
     *
     * @param  int  $runId  Agent run ID to evaluate
     * @param  string  $output  Agent output to evaluate
     * @return array Evaluation results with scores and feedback
     */
    public function evaluateRun(int $runId, string $output): array;

    /**
     * Generate a comprehensive evaluation report for an agent run
     *
     * @param  int  $runId  Agent run ID
     * @return string Formatted evaluation report
     */
    public function generateReport(int $runId): string;
}
