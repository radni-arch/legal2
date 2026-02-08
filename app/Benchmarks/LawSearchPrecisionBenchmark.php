<?php

namespace App\Benchmarks;

use App\Services\LawVectorStoreService;

/**
 * Measures precision of law article search and retrieval
 *
 * Tests how accurately the system retrieves specific law articles
 * based on legal concepts and queries.
 */
class LawSearchPrecisionBenchmark extends BaseBenchmark
{
    protected LawVectorStoreService $lawVectorStore;

    public function __construct(LawVectorStoreService $lawVectorStore)
    {
        $this->lawVectorStore = $lawVectorStore;
    }

    public function getName(): string
    {
        return 'Law Search Precision';
    }

    public function getDescription(): string
    {
        return 'Measures precision of law article retrieval for legal concepts';
    }

    protected function getDefaultConfig(): array
    {
        return [
            'test_cases' => [
                [
                    'query' => 'presumption of innocence',
                    'expected_law' => 'ZKP',
                    'expected_articles' => ['9'],
                ],
                [
                    'query' => 'right to counsel',
                    'expected_law' => 'ZKP',
                    'expected_articles' => ['64', '65'],
                ],
                [
                    'query' => 'search warrant requirements',
                    'expected_law' => 'ZKP',
                    'expected_articles' => ['214', '215', '216'],
                ],
                [
                    'query' => 'unlawful detention',
                    'expected_law' => 'Ustav RH',
                    'expected_articles' => ['22', '23'],
                ],
            ],
            'retrieval_limit' => 10,
            'exact_match_weight' => 1.0,
            'partial_match_weight' => 0.5,
        ];
    }

    protected function execute(): array
    {
        $testCases = $this->config['test_cases'];
        $retrievalLimit = $this->config['retrieval_limit'];

        $metrics = [
            'total_queries' => 0,
            'exact_matches' => 0,
            'partial_matches' => 0,
            'no_matches' => 0,
            'precision' => 0.0,
            'recall' => 0.0,
            'f1_score' => 0.0,
            'mean_reciprocal_rank' => 0.0,
            'avg_search_time_ms' => 0.0,
            'total_relevant' => 0,
            'total_retrieved' => 0,
        ];

        $reciprocalRanks = [];
        $searchTimes = [];
        $precisionScores = [];
        $recallScores = [];

        foreach ($testCases as $testCase) {
            $metrics['total_queries']++;
            $metrics['total_relevant'] += count($testCase['expected_articles']);

            $startTime = microtime(true);
            $results = $this->lawVectorStore->search(
                $testCase['query'],
                $retrievalLimit
            );
            $searchTime = (microtime(true) - $startTime) * 1000;
            $searchTimes[] = $searchTime;

            $metrics['total_retrieved'] += count($results);

            // Evaluate results
            $foundExact = false;
            $foundPartial = false;
            $firstRelevantRank = null;
            $relevantFound = 0;

            foreach ($results as $index => $result) {
                $rank = $index + 1;
                $matchType = $this->evaluateMatch($result, $testCase);

                if ($matchType === 'exact') {
                    $foundExact = true;
                    $relevantFound++;
                    if ($firstRelevantRank === null) {
                        $firstRelevantRank = $rank;
                    }
                } elseif ($matchType === 'partial') {
                    $foundPartial = true;
                    $relevantFound++;
                    if ($firstRelevantRank === null) {
                        $firstRelevantRank = $rank;
                    }
                }
            }

            if ($foundExact) {
                $metrics['exact_matches']++;
            } elseif ($foundPartial) {
                $metrics['partial_matches']++;
            } else {
                $metrics['no_matches']++;
            }

            // Calculate MRR
            if ($firstRelevantRank !== null) {
                $reciprocalRanks[] = 1.0 / $firstRelevantRank;
            } else {
                $reciprocalRanks[] = 0.0;
            }

            // Calculate precision and recall for this query
            if (count($results) > 0) {
                $precisionScores[] = $relevantFound / count($results);
            }

            if (count($testCase['expected_articles']) > 0) {
                $recallScores[] = $relevantFound / count($testCase['expected_articles']);
            }
        }

        // Calculate aggregate metrics
        if ($metrics['total_queries'] > 0) {
            $metrics['precision'] = ($metrics['exact_matches'] + ($metrics['partial_matches'] * 0.5)) / $metrics['total_queries'];
        }

        if (count($precisionScores) > 0) {
            $avgPrecision = array_sum($precisionScores) / count($precisionScores);
            $avgRecall = array_sum($recallScores) / count($recallScores);

            $metrics['precision'] = $avgPrecision;
            $metrics['recall'] = $avgRecall;

            if ($avgPrecision + $avgRecall > 0) {
                $metrics['f1_score'] = 2 * ($avgPrecision * $avgRecall) / ($avgPrecision + $avgRecall);
            }
        }

        if (count($reciprocalRanks) > 0) {
            $metrics['mean_reciprocal_rank'] = array_sum($reciprocalRanks) / count($reciprocalRanks);
        }

        if (count($searchTimes) > 0) {
            $metrics['avg_search_time_ms'] = array_sum($searchTimes) / count($searchTimes);
        }

        return $metrics;
    }

    /**
     * Evaluate if a result matches the expected law/articles
     */
    protected function evaluateMatch(array $result, array $testCase): string
    {
        $content = $result['content'] ?? '';
        $metadata = $result['metadata'] ?? [];

        $expectedLaw = $testCase['expected_law'];
        $expectedArticles = $testCase['expected_articles'];

        // Check if the law matches
        $lawMatches = false;
        if (stripos($content, $expectedLaw) !== false) {
            $lawMatches = true;
        }

        if (! $lawMatches) {
            return 'none';
        }

        // Check if any expected article is mentioned
        $articleMatches = 0;
        foreach ($expectedArticles as $article) {
            // Look for patterns like "Članak 9", "Article 9", "čl. 9"
            $patterns = [
                "/\bČlanak\s+{$article}\b/i",
                "/\bArticle\s+{$article}\b/i",
                "/\bčl\.\s+{$article}\b/i",
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $content)) {
                    $articleMatches++;
                    break;
                }
            }
        }

        // Exact match: law matches and all expected articles found
        if ($articleMatches === count($expectedArticles)) {
            return 'exact';
        }

        // Partial match: law matches and at least one article found
        if ($articleMatches > 0) {
            return 'partial';
        }

        // Law matches but no specific articles
        return 'partial';
    }

    /**
     * Lower search time is better, higher scores are better
     */
    public function isImprovement(string $metricKey, float $diff): bool
    {
        $lowerIsBetter = ['avg_search_time_ms', 'no_matches'];

        if (in_array($metricKey, $lowerIsBetter)) {
            return $diff < 0;
        }

        return $diff > 0;
    }
}
