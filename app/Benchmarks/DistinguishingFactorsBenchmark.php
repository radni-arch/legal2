<?php

namespace App\Benchmarks;

/**
 * Measures accuracy of identifying distinguishing factors
 *
 * Tests how well the system identifies factors that distinguish
 * current cases from precedents (why precedent may not apply).
 */
class DistinguishingFactorsBenchmark extends BaseBenchmark
{
    public function getName(): string
    {
        return 'Distinguishing Factors Identification';
    }

    public function getDescription(): string
    {
        return 'Measures accuracy of identifying factors that distinguish cases from precedents';
    }

    protected function getDefaultConfig(): array
    {
        return [
            'test_cases' => [
                [
                    'current_case' => 'Defendant possessed 5g marijuana for personal use, first offense',
                    'precedent' => 'Defendant possessed 100g marijuana with intent to distribute',
                    'expected_factors' => [
                        'quantity_difference',
                        'intent_to_distribute',
                        'prior_record',
                    ],
                ],
                [
                    'current_case' => 'Search warrant executed at 2am without exigent circumstances',
                    'precedent' => 'Search warrant executed during daytime hours',
                    'expected_factors' => [
                        'time_of_execution',
                        'exigent_circumstances',
                    ],
                ],
            ],
        ];
    }

    protected function execute(): array
    {
        $testCases = $this->config['test_cases'];

        $metrics = [
            'total_cases' => 0,
            'avg_factors_identified' => 0.0,
            'precision' => 0.0,
            'recall' => 0.0,
            'f1_score' => 0.0,
        ];

        $precisionScores = [];
        $recallScores = [];
        $factorCounts = [];

        foreach ($testCases as $testCase) {
            $metrics['total_cases']++;

            $identifiedFactors = $this->identifyDistinguishingFactors(
                $testCase['current_case'],
                $testCase['precedent']
            );

            $expectedFactors = $testCase['expected_factors'];

            $factorCounts[] = count($identifiedFactors);

            // Calculate precision and recall
            $truePositives = count(array_intersect($identifiedFactors, $expectedFactors));
            $predicted = count($identifiedFactors);
            $actual = count($expectedFactors);

            if ($predicted > 0) {
                $precisionScores[] = $truePositives / $predicted;
            }

            if ($actual > 0) {
                $recallScores[] = $truePositives / $actual;
            }
        }

        if (count($factorCounts) > 0) {
            $metrics['avg_factors_identified'] = array_sum($factorCounts) / count($factorCounts);
        }

        if (count($precisionScores) > 0) {
            $metrics['precision'] = array_sum($precisionScores) / count($precisionScores);
        }

        if (count($recallScores) > 0) {
            $metrics['recall'] = array_sum($recallScores) / count($recallScores);
        }

        if ($metrics['precision'] + $metrics['recall'] > 0) {
            $metrics['f1_score'] = 2 * ($metrics['precision'] * $metrics['recall']) / ($metrics['precision'] + $metrics['recall']);
        }

        return $metrics;
    }

    /**
     * Identify distinguishing factors between cases
     */
    protected function identifyDistinguishingFactors(string $currentCase, string $precedent): array
    {
        $factors = [];

        // Quantity differences
        if (preg_match('/(\d+)g/', $currentCase, $currentMatch) &&
            preg_match('/(\d+)g/', $precedent, $precedentMatch)) {
            if ($currentMatch[1] !== $precedentMatch[1]) {
                $factors[] = 'quantity_difference';
            }
        }

        // Intent to distribute
        if (stripos($precedent, 'intent to distribute') !== false &&
            stripos($currentCase, 'personal use') !== false) {
            $factors[] = 'intent_to_distribute';
        }

        // Time of execution
        if (preg_match('/(\d+)(am|pm)/', $currentCase, $currentTime) &&
            preg_match('/(\d+)(am|pm)/', $precedent, $precedentTime)) {
            if ($currentTime[0] !== $precedentTime[0]) {
                $factors[] = 'time_of_execution';
            }
        }

        // Exigent circumstances
        if (stripos($currentCase, 'without exigent') !== false) {
            $factors[] = 'exigent_circumstances';
        }

        // Prior record
        if (stripos($currentCase, 'first offense') !== false &&
            stripos($precedent, 'first offense') === false) {
            $factors[] = 'prior_record';
        }

        return array_unique($factors);
    }

    /**
     * Higher scores are better
     */
    public function isImprovement(string $metricKey, float $diff): bool
    {
        return $diff > 0;
    }
}
