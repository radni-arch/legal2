<?php

namespace App\Benchmarks;

use App\Services\CourtDecisionVectorStoreService;

/**
 * Measures accuracy of precedent applicability scoring
 *
 * Tests how accurately the system scores the applicability
 * of precedents to current cases based on fact patterns.
 */
class ApplicabilityScoringBenchmark extends BaseBenchmark
{
    protected CourtDecisionVectorStoreService $decisionVectorStore;

    public function __construct(CourtDecisionVectorStoreService $decisionVectorStore)
    {
        $this->decisionVectorStore = $decisionVectorStore;
    }

    public function getName(): string
    {
        return 'Precedent Applicability Scoring';
    }

    public function getDescription(): string
    {
        return 'Measures accuracy of precedent applicability scoring for case fact patterns';
    }

    protected function getDefaultConfig(): array
    {
        return [
            'test_cases' => [
                [
                    'current_facts' => 'Home search for 5g marijuana, no distribution evidence',
                    'precedent' => 'Similar case: 10g marijuana, home search deemed disproportionate',
                    'expected_applicability' => 0.9, // Highly applicable
                ],
                [
                    'current_facts' => 'Drug trafficking, 1kg cocaine, organized crime',
                    'precedent' => 'Minor possession case, 5g marijuana, first offense',
                    'expected_applicability' => 0.2, // Not very applicable
                ],
                [
                    'current_facts' => 'Illegal detention, 72 hours without charges',
                    'precedent' => 'Illegal detention, 48 hours without charges, constitutional violation',
                    'expected_applicability' => 0.85, // Highly applicable
                ],
            ],
            'tolerance' => 0.15, // Allow 15% deviation from expected score
        ];
    }

    protected function execute(): array
    {
        $testCases = $this->config['test_cases'];
        $tolerance = $this->config['tolerance'];

        $metrics = [
            'total_cases' => 0,
            'within_tolerance' => 0,
            'mean_absolute_error' => 0.0,
            'mean_squared_error' => 0.0,
            'correlation' => 0.0,
        ];

        $absoluteErrors = [];
        $squaredErrors = [];
        $predictedScores = [];
        $expectedScores = [];

        foreach ($testCases as $testCase) {
            $metrics['total_cases']++;

            $predictedScore = $this->scoreApplicability(
                $testCase['current_facts'],
                $testCase['precedent']
            );

            $expectedScore = $testCase['expected_applicability'];

            $predictedScores[] = $predictedScore;
            $expectedScores[] = $expectedScore;

            $error = abs($predictedScore - $expectedScore);
            $absoluteErrors[] = $error;
            $squaredErrors[] = $error * $error;

            if ($error <= $tolerance) {
                $metrics['within_tolerance']++;
            }
        }

        // Calculate metrics
        if (count($absoluteErrors) > 0) {
            $metrics['mean_absolute_error'] = array_sum($absoluteErrors) / count($absoluteErrors);
            $metrics['mean_squared_error'] = array_sum($squaredErrors) / count($squaredErrors);
        }

        if (count($predictedScores) > 1) {
            $metrics['correlation'] = $this->calculateCorrelation($predictedScores, $expectedScores);
        }

        return $metrics;
    }

    /**
     * Score applicability of precedent to current facts
     */
    protected function scoreApplicability(string $currentFacts, string $precedent): float
    {
        // Simplified scoring based on keyword overlap
        $currentKeywords = array_filter(explode(' ', strtolower($currentFacts)));
        $precedentKeywords = array_filter(explode(' ', strtolower($precedent)));

        $intersection = array_intersect($currentKeywords, $precedentKeywords);
        $union = array_unique(array_merge($currentKeywords, $precedentKeywords));

        if (count($union) === 0) {
            return 0.0;
        }

        // Jaccard similarity
        $jaccardScore = count($intersection) / count($union);

        // Boost score if key legal terms match
        $keyTerms = ['search', 'detention', 'marijuana', 'cocaine', 'constitutional', 'disproportionate'];
        $keyTermMatches = 0;

        foreach ($keyTerms as $term) {
            if (in_array($term, $intersection)) {
                $keyTermMatches++;
            }
        }

        $boost = $keyTermMatches * 0.1;

        return min(1.0, $jaccardScore + $boost);
    }

    /**
     * Calculate Pearson correlation
     */
    protected function calculateCorrelation(array $x, array $y): float
    {
        if (count($x) !== count($y) || count($x) < 2) {
            return 0.0;
        }

        $n = count($x);
        $meanX = array_sum($x) / $n;
        $meanY = array_sum($y) / $n;

        $numerator = 0;
        $denomX = 0;
        $denomY = 0;

        for ($i = 0; $i < $n; $i++) {
            $diffX = $x[$i] - $meanX;
            $diffY = $y[$i] - $meanY;

            $numerator += $diffX * $diffY;
            $denomX += $diffX * $diffX;
            $denomY += $diffY * $diffY;
        }

        $denominator = sqrt($denomX * $denomY);

        return $denominator > 0 ? $numerator / $denominator : 0.0;
    }

    /**
     * Lower error is better, higher correlation is better
     */
    public function isImprovement(string $metricKey, float $diff): bool
    {
        $lowerIsBetter = ['mean_absolute_error', 'mean_squared_error'];

        if (in_array($metricKey, $lowerIsBetter)) {
            return $diff < 0;
        }

        return $diff > 0;
    }
}
