<?php

namespace App\Benchmarks;

/**
 * Measures accuracy of legal authority weighting
 *
 * Tests how accurately the system weights different legal authorities
 * (Supreme Court vs lower courts, binding vs persuasive precedent).
 */
class AuthorityWeightingBenchmark extends BaseBenchmark
{
    public function getName(): string
    {
        return 'Legal Authority Weighting';
    }

    public function getDescription(): string
    {
        return 'Measures accuracy of legal authority weighting (court hierarchy, binding precedent)';
    }

    protected function getDefaultConfig(): array
    {
        return [
            'test_cases' => [
                [
                    'authority' => 'Vrhovni sud Republike Hrvatske',
                    'type' => 'supreme_court',
                    'binding' => true,
                    'expected_weight' => 1.0,
                ],
                [
                    'authority' => 'Visoki upravni sud',
                    'type' => 'high_administrative_court',
                    'binding' => true,
                    'expected_weight' => 0.9,
                ],
                [
                    'authority' => 'Županijski sud',
                    'type' => 'county_court',
                    'binding' => false,
                    'expected_weight' => 0.6,
                ],
                [
                    'authority' => 'Općinski sud',
                    'type' => 'municipal_court',
                    'binding' => false,
                    'expected_weight' => 0.4,
                ],
            ],
            'tolerance' => 0.1,
        ];
    }

    protected function execute(): array
    {
        $testCases = $this->config['test_cases'];
        $tolerance = $this->config['tolerance'];

        $metrics = [
            'total_cases' => 0,
            'correct_weights' => 0,
            'within_tolerance' => 0,
            'mean_absolute_error' => 0.0,
            'hierarchy_accuracy' => 0.0,
        ];

        $absoluteErrors = [];

        foreach ($testCases as $testCase) {
            $metrics['total_cases']++;

            $predictedWeight = $this->calculateAuthorityWeight(
                $testCase['authority'],
                $testCase['type'],
                $testCase['binding']
            );

            $expectedWeight = $testCase['expected_weight'];
            $error = abs($predictedWeight - $expectedWeight);

            $absoluteErrors[] = $error;

            if ($error <= $tolerance) {
                $metrics['within_tolerance']++;
            }

            if (abs($predictedWeight - $expectedWeight) < 0.01) {
                $metrics['correct_weights']++;
            }
        }

        if (count($absoluteErrors) > 0) {
            $metrics['mean_absolute_error'] = array_sum($absoluteErrors) / count($absoluteErrors);
        }

        if ($metrics['total_cases'] > 0) {
            $metrics['hierarchy_accuracy'] = $metrics['within_tolerance'] / $metrics['total_cases'];
        }

        return $metrics;
    }

    /**
     * Calculate weight for legal authority
     */
    protected function calculateAuthorityWeight(string $authority, string $type, bool $binding): float
    {
        // Base weight by court type
        $baseWeights = [
            'supreme_court' => 1.0,
            'high_administrative_court' => 0.9,
            'county_court' => 0.6,
            'municipal_court' => 0.4,
        ];

        $weight = $baseWeights[$type] ?? 0.5;

        // Reduce weight for non-binding precedent
        if (! $binding) {
            $weight *= 0.8;
        }

        // Boost for specific high-authority courts
        if (stripos($authority, 'Vrhovni sud') !== false) {
            $weight = 1.0;
        } elseif (stripos($authority, 'Visoki') !== false) {
            $weight = max($weight, 0.9);
        }

        return min(1.0, $weight);
    }

    /**
     * Lower error is better, higher accuracy is better
     */
    public function isImprovement(string $metricKey, float $diff): bool
    {
        $lowerIsBetter = ['mean_absolute_error'];

        if (in_array($metricKey, $lowerIsBetter)) {
            return $diff < 0;
        }

        return $diff > 0;
    }
}
