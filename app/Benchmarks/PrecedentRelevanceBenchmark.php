<?php

namespace App\Benchmarks;

use App\Services\CourtDecisionVectorStoreService;

/**
 * Measures the relevance of retrieved precedents to a given legal query
 *
 * Tests how well the system retrieves applicable court decisions
 * for specific legal scenarios.
 */
class PrecedentRelevanceBenchmark extends BaseBenchmark
{
    protected CourtDecisionVectorStoreService $decisionVectorStore;

    public function __construct(CourtDecisionVectorStoreService $decisionVectorStore)
    {
        $this->decisionVectorStore = $decisionVectorStore;
    }

    public function getName(): string
    {
        return 'Precedent Relevance';
    }

    public function getDescription(): string
    {
        return 'Measures relevance of court decision retrieval for legal queries';
    }

    protected function getDefaultConfig(): array
    {
        return [
            'test_queries' => [
                [
                    'query' => 'home search warrant disproportionality',
                    'expected_keywords' => ['proporcionalno', 'pretres', 'nalog', 'razmjer'],
                    'min_relevance_score' => 0.7,
                ],
                [
                    'query' => 'drug possession sentencing guidelines',
                    'expected_keywords' => ['droga', 'posjedovanje', 'kazna', 'odmjeravanje'],
                    'min_relevance_score' => 0.7,
                ],
                [
                    'query' => 'prosecutorial misconduct dismissal',
                    'expected_keywords' => ['povrede postupka', 'odvjetnik', 'odbacivanje'],
                    'min_relevance_score' => 0.7,
                ],
            ],
            'retrieval_limit' => 10,
            'relevance_threshold' => 0.6,
        ];
    }

    protected function execute(): array
    {
        $testQueries = $this->config['test_queries'];
        $retrievalLimit = $this->config['retrieval_limit'];
        $relevanceThreshold = $this->config['relevance_threshold'];

        $metrics = [
            'total_queries' => 0,
            'total_retrieved' => 0,
            'relevant_retrieved' => 0,
            'precision_at_k' => 0.0,
            'mean_average_precision' => 0.0,
            'recall_at_k' => 0.0,
            'ndcg' => 0.0, // Normalized Discounted Cumulative Gain
            'avg_retrieval_time_ms' => 0.0,
        ];

        $precisionScores = [];
        $retrievalTimes = [];

        foreach ($testQueries as $testCase) {
            $metrics['total_queries']++;

            $startTime = microtime(true);
            $results = $this->decisionVectorStore->search(
                $testCase['query'],
                $retrievalLimit
            );
            $retrievalTime = (microtime(true) - $startTime) * 1000;
            $retrievalTimes[] = $retrievalTime;

            $retrieved = count($results);
            $metrics['total_retrieved'] += $retrieved;

            // Evaluate relevance
            $relevantCount = 0;
            foreach ($results as $result) {
                if ($this->isRelevant($result, $testCase)) {
                    $relevantCount++;
                }
            }

            $metrics['relevant_retrieved'] += $relevantCount;

            // Calculate precision for this query
            if ($retrieved > 0) {
                $precisionScores[] = $relevantCount / $retrieved;
            }
        }

        // Calculate aggregate metrics
        if (count($precisionScores) > 0) {
            $metrics['precision_at_k'] = array_sum($precisionScores) / count($precisionScores);
            $metrics['mean_average_precision'] = array_sum($precisionScores) / count($precisionScores);
        }

        if ($metrics['total_retrieved'] > 0) {
            $metrics['recall_at_k'] = $metrics['relevant_retrieved'] / $metrics['total_retrieved'];
        }

        if (count($retrievalTimes) > 0) {
            $metrics['avg_retrieval_time_ms'] = array_sum($retrievalTimes) / count($retrievalTimes);
        }

        // Calculate NDCG (simplified version)
        $metrics['ndcg'] = $this->calculateNDCG($testQueries, $retrievalLimit);

        return $metrics;
    }

    /**
     * Check if a retrieved decision is relevant to the test case
     */
    protected function isRelevant(array $result, array $testCase): bool
    {
        $expectedKeywords = $testCase['expected_keywords'];
        $minRelevance = $testCase['min_relevance_score'];

        // Check if result has minimum score
        if (isset($result['score']) && $result['score'] < $minRelevance) {
            return false;
        }

        // Check if content contains expected keywords
        $content = strtolower($result['content'] ?? '');
        $matchedKeywords = 0;

        foreach ($expectedKeywords as $keyword) {
            if (stripos($content, $keyword) !== false) {
                $matchedKeywords++;
            }
        }

        // Require at least 50% keyword match
        return $matchedKeywords >= (count($expectedKeywords) / 2);
    }

    /**
     * Calculate Normalized Discounted Cumulative Gain
     */
    protected function calculateNDCG(array $testQueries, int $k): float
    {
        $ndcgScores = [];

        foreach ($testQueries as $testCase) {
            $results = $this->decisionVectorStore->search(
                $testCase['query'],
                $k
            );

            $dcg = 0.0;
            $idcg = 0.0;

            foreach ($results as $i => $result) {
                $position = $i + 1;
                $relevance = $this->isRelevant($result, $testCase) ? 1.0 : 0.0;

                // DCG formula: rel_i / log2(i + 1)
                if ($position > 1) {
                    $dcg += $relevance / log($position + 1, 2);
                } else {
                    $dcg += $relevance;
                }
            }

            // Ideal DCG (all relevant results at top)
            for ($i = 1; $i <= $k; $i++) {
                if ($i > 1) {
                    $idcg += 1.0 / log($i + 1, 2);
                } else {
                    $idcg += 1.0;
                }
            }

            if ($idcg > 0) {
                $ndcgScores[] = $dcg / $idcg;
            }
        }

        return count($ndcgScores) > 0 ? array_sum($ndcgScores) / count($ndcgScores) : 0.0;
    }

    /**
     * Lower retrieval time is better, higher scores are better
     */
    public function isImprovement(string $metricKey, float $diff): bool
    {
        $lowerIsBetter = ['avg_retrieval_time_ms'];

        if (in_array($metricKey, $lowerIsBetter)) {
            return $diff < 0;
        }

        return $diff > 0;
    }
}
