<?php

namespace App\Contracts\Search;

/**
 * Interface for aggregating and normalizing search results.
 *
 * Provides methods for combining results from multiple search sources,
 * applying weights, and generating aggregation statistics.
 */
interface SearchResultAggregatorInterface
{
    /**
     * Aggregate results from multiple corpora.
     *
     * @param  array  $corpusResults  Results organized by corpus
     * @param  array  $weights  Scoring weights per corpus (optional)
     * @return array Aggregated and scored results
     */
    public function aggregate(array $corpusResults, array $weights = []): array;

    /**
     * Aggregate results and sort by score.
     *
     * @param  array  $corpusResults  Results organized by corpus
     * @param  array  $weights  Scoring weights per corpus (optional)
     * @return array Sorted aggregated results
     */
    public function aggregateAndSort(array $corpusResults, array $weights = []): array;

    /**
     * Get aggregation statistics for corpus results.
     *
     * @param  array  $corpusResults  Results organized by corpus
     * @param  array  $weights  Scoring weights applied
     * @return array Statistics including total_results, avg_score, etc.
     */
    public function getAggregationStats(array $corpusResults, array $weights = []): array;

    /**
     * Get general statistics for already-aggregated results.
     *
     * @param  array  $aggregatedResults  Already aggregated results
     * @return array Statistics
     */
    public function getStatistics(array $aggregatedResults): array;

    /**
     * Validate corpus results structure.
     *
     * @param  array  $corpusResults  Results to validate
     * @return bool True if valid
     */
    public function validateCorpusResults(array $corpusResults): bool;

    /**
     * Normalize weight values to sum to a target value.
     *
     * @param  array  $weights  Raw weights
     * @param  float  $targetSum  Target sum (default: 1.0)
     * @return array Normalized weights
     */
    public function normalizeWeights(array $weights, float $targetSum = 1.0): array;

    /**
     * Filter results by minimum score threshold.
     *
     * @param  array  $results  Results to filter
     * @param  float  $minScore  Minimum score threshold
     * @return array Filtered results
     */
    public function filterByMinScore(array $results, float $minScore): array;

    /**
     * Group flat results by corpus.
     *
     * @param  array  $results  Flat array of results
     * @return array Results grouped by corpus
     */
    public function groupByCorpus(array $results): array;
}
