<?php

namespace App\Services\Search;

use App\Contracts\Search\SearchResultAggregatorInterface;

/**
 * SearchResultAggregator
 *
 * Responsible for aggregating search results from multiple corpora (laws, decisions, cases)
 * and applying corpus-specific weights to scores.
 * Extracted from UnifiedSearchService as part of Phase 2 refactoring.
 *
 * Target: 200-250 lines
 */
class SearchResultAggregator implements SearchResultAggregatorInterface
{
    /**
     * Get statistics about aggregated results
     *
     * @param  array  $aggregatedResults  Results from aggregate()
     * @return array Statistics by corpus
     */
    public function getStatistics(array $aggregatedResults): array
    {
        $stats = [];

        foreach ($aggregatedResults as $result) {
            $corpus = $result['corpus'] ?? 'unknown';

            if (! isset($stats[$corpus])) {
                $stats[$corpus] = [
                    'count' => 0,
                    'avg_score' => 0.0,
                    'max_score' => 0.0,
                    'min_score' => PHP_FLOAT_MAX,
                ];
            }

            $score = $result['weighted_score'] ?? 0.0;
            $stats[$corpus]['count']++;
            $stats[$corpus]['avg_score'] += $score;
            $stats[$corpus]['max_score'] = max($stats[$corpus]['max_score'], $score);
            $stats[$corpus]['min_score'] = min($stats[$corpus]['min_score'], $score);
        }

        // Calculate averages
        foreach ($stats as $corpus => $data) {
            if ($data['count'] > 0) {
                $stats[$corpus]['avg_score'] = $data['avg_score'] / $data['count'];
            }
        }

        return $stats;
    }

    /**
     * Aggregate results from multiple corpora with optional weights
     *
     * Takes results from different corpora (laws, decisions, cases) and merges them
     * into a single array. Applies corpus-specific weights to scores and preserves
     * the original scores as raw_score for transparency.
     *
     * @param  array  $corpusResults  Array of corpus => results[], where each corpus
     *                                 has an array of search results
     * @param  array  $weights  Optional array of corpus => weight (default: 1.0 for each)
     * @return array Merged array of all results with weighted scores
     *
     * @example
     * ```php
     * $aggregator = new SearchResultAggregator();
     *
     * $corpusResults = [
     *     'laws' => [
     *         ['id' => '1', 'score' => 0.9, 'title' => 'Law 1'],
     *         ['id' => '2', 'score' => 0.8, 'title' => 'Law 2'],
     *     ],
     *     'decisions' => [
     *         ['id' => '3', 'score' => 0.85, 'title' => 'Decision 1'],
     *     ],
     * ];
     *
     * $weights = [
     *     'laws' => 2.0,      // Laws get double weight
     *     'decisions' => 0.5, // Decisions get half weight
     * ];
     *
     * $results = $aggregator->aggregate($corpusResults, $weights);
     * // Returns: [
     * //   ['id' => '1', 'score' => 1.8, 'raw_score' => 0.9, 'corpus_weight' => 2.0, ...],
     * //   ['id' => '2', 'score' => 1.6, 'raw_score' => 0.8, 'corpus_weight' => 2.0, ...],
     * //   ['id' => '3', 'score' => 0.425, 'raw_score' => 0.85, 'corpus_weight' => 0.5, ...],
     * // ]
     * ```
     */
    public function aggregate(array $corpusResults, array $weights = []): array
    {
        $allResults = [];

        foreach ($corpusResults as $corpus => $results) {
            // Get weight for this corpus (default to 1.0 if not specified)
            $weight = $weights[$corpus] ?? 1.0;

            // Process each result in this corpus
            foreach ($results as $result) {
                // Preserve the original score
                $result['raw_score'] = $result['score'];

                // Apply corpus weight to score, capping at 1.0
                $result['score'] = min(1.0, $result['score'] * $weight);

                // Store the weight used for transparency
                $result['corpus_weight'] = $weight;

                // Add to merged results
                $allResults[] = $result;
            }
        }

        return $allResults;
    }

    /**
     * Aggregate results and sort by weighted score
     *
     * Convenience method that aggregates results and immediately sorts them
     * by the weighted score in descending order (highest scores first).
     *
     * @param  array  $corpusResults  Array of corpus => results[]
     * @param  array  $weights  Optional array of corpus => weight
     * @return array Sorted array of results
     */
    public function aggregateAndSort(array $corpusResults, array $weights = []): array
    {
        $results = $this->aggregate($corpusResults, $weights);

        // Sort by weighted score (descending)
        usort($results, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $results;
    }

    /**
     * Calculate statistics about aggregated results
     *
     * Returns useful statistics about the aggregation, such as result counts
     * per corpus, score distributions, and weight impact.
     *
     * @param  array  $corpusResults  Array of corpus => results[]
     * @param  array  $weights  Optional array of corpus => weight
     * @return array Statistics array
     */
    public function getAggregationStats(array $corpusResults, array $weights = []): array
    {
        $stats = [
            'total_results' => 0,
            'results_per_corpus' => [],
            'weights_applied' => [],
            'score_ranges' => [],
        ];

        foreach ($corpusResults as $corpus => $results) {
            $resultCount = count($results);
            $weight = $weights[$corpus] ?? 1.0;

            $stats['total_results'] += $resultCount;
            $stats['results_per_corpus'][$corpus] = $resultCount;
            $stats['weights_applied'][$corpus] = $weight;

            if ($resultCount > 0) {
                $scores = array_column($results, 'score');
                $weightedScores = array_map(fn ($s) => $s * $weight, $scores);

                $stats['score_ranges'][$corpus] = [
                    'min_raw' => min($scores),
                    'max_raw' => max($scores),
                    'avg_raw' => array_sum($scores) / count($scores),
                    'min_weighted' => min($weightedScores),
                    'max_weighted' => max($weightedScores),
                    'avg_weighted' => array_sum($weightedScores) / count($weightedScores),
                ];
            }
        }

        return $stats;
    }

    /**
     * Validate corpus results structure
     *
     * Checks that the corpus results array has the expected structure
     * (array of arrays with required fields).
     *
     * @param  array  $corpusResults  Array of corpus => results[]
     * @return bool True if valid structure
     */
    public function validateCorpusResults(array $corpusResults): bool
    {
        foreach ($corpusResults as $corpus => $results) {
            // Check corpus is a string
            if (! is_string($corpus)) {
                return false;
            }

            // Check results is an array
            if (! is_array($results)) {
                return false;
            }

            // Check each result has required fields
            foreach ($results as $result) {
                if (! is_array($result)) {
                    return false;
                }

                if (! isset($result['id']) || ! isset($result['score'])) {
                    return false;
                }

                if (! is_numeric($result['score'])) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Normalize weights to ensure they sum to a specific value
     *
     * Useful for ensuring weights are proportional and sum to 1.0 or another value.
     * This helps maintain consistency in scoring across different searches.
     *
     * @param  array  $weights  Array of corpus => weight
     * @param  float  $targetSum  Target sum for normalized weights (default: 1.0)
     * @return array Normalized weights
     */
    public function normalizeWeights(array $weights, float $targetSum = 1.0): array
    {
        if (empty($weights)) {
            return [];
        }

        $currentSum = array_sum($weights);

        if ($currentSum == 0) {
            // If all weights are zero, distribute evenly
            $count = count($weights);

            return array_fill_keys(array_keys($weights), $targetSum / $count);
        }

        // Scale weights to match target sum
        $scale = $targetSum / $currentSum;

        return array_map(fn ($w) => $w * $scale, $weights);
    }

    /**
     * Filter aggregated results by minimum weighted score
     *
     * Removes results that don't meet a minimum weighted score threshold.
     * Useful for post-aggregation filtering.
     *
     * @param  array  $results  Aggregated results (must have 'score' field)
     * @param  float  $minScore  Minimum weighted score threshold
     * @return array Filtered results
     */
    public function filterByMinScore(array $results, float $minScore): array
    {
        return array_values(array_filter($results, fn ($r) => $r['score'] >= $minScore));
    }

    /**
     * Group results by corpus
     *
     * Takes merged results and groups them back by corpus type.
     * Useful for displaying results in corpus-specific sections.
     *
     * @param  array  $results  Aggregated results (must have 'type' field)
     * @return array Array of corpus => results[]
     */
    public function groupByCorpus(array $results): array
    {
        $grouped = [];

        foreach ($results as $result) {
            $type = $result['type'] ?? 'unknown';
            $grouped[$type][] = $result;
        }

        return $grouped;
    }
}
