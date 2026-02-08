<?php

namespace App\Contracts\Research;

/**
 * Interface for evaluating answer quality and extracting insights
 *
 * This component is responsible for:
 * - Extracting legal insights from search results
 * - Scoring relevance of sources to the research objective
 * - Formatting results for LLM processing
 * - Providing fallback extraction when LLM fails
 */
interface AnswerEvaluatorInterface
{
    /**
     * Evaluate answer quality
     *
     * @param  string  $query  The research objective/query
     * @param  string  $answer  The answer or insight to evaluate
     * @param  array  $sources  Sources supporting the answer with metadata:
     *                          - type: Source type (law, decision, case)
     *                          - content: Source content
     *                          - citations: Legal citations
     *                          - relevance_score: Relevance to query
     * @return array Evaluation result with:
     *               - quality_score: Overall quality (0-1)
     *               - has_citations: Whether proper citations exist
     *               - is_relevant: Whether answer addresses the query
     *               - is_actionable: Whether insight is actionable
     *               - feedback: Specific feedback for improvement
     */
    public function evaluate(string $query, string $answer, array $sources): array;

    /**
     * Score relevance of sources
     *
     * @param  string  $query  The research objective/query
     * @param  array  $sources  Array of sources to score
     * @return array Sources with relevance scores added:
     *               - Each source gets 'relevance_score' (0-1)
     *               - Sources sorted by relevance (highest first)
     */
    public function scoreRelevance(string $query, array $sources): array;

    /**
     * Extract insight from search result
     *
     * @param  array  $result  Search result to extract from
     * @param  string  $objective  Research objective
     * @return string|null Extracted insight or null if not relevant
     */
    public function extractInsight(array $result, string $objective): ?string;

    /**
     * Extract insight using simple fallback logic (no LLM)
     *
     * @param  array  $result  Search result
     * @return string|null Extracted insight or null
     */
    public function extractSimpleInsight(array $result): ?string;

    /**
     * Format search results for LLM insight extraction
     *
     * @param  array  $result  Search result to format
     * @return string Formatted string suitable for LLM prompt
     */
    public function formatResultsForExtraction(array $result): string;
}
