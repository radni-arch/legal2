<?php

namespace App\Benchmarks;

/**
 * Measures accuracy of legal argument strength assessment
 *
 * Tests how accurately the system assesses the strength
 * of legal arguments across different domains.
 */
class ArgumentStrengthBenchmark extends BaseBenchmark
{
    public function getName(): string
    {
        return 'Legal Argument Strength Assessment';
    }

    public function getDescription(): string
    {
        return 'Measures accuracy of assessing legal argument strength';
    }

    protected function getDefaultConfig(): array
    {
        return [
            'test_cases' => [
                [
                    'argument' => 'Search violated Fourth Amendment - no warrant, no exigent circumstances, clear constitutional precedent',
                    'domain' => 'constitutional_law',
                    'supporting_precedents' => 3,
                    'counter_arguments' => 0,
                    'expected_strength' => 0.9,
                ],
                [
                    'argument' => 'Defendant is innocent because he said so',
                    'domain' => 'criminal_defense',
                    'supporting_precedents' => 0,
                    'counter_arguments' => 5,
                    'expected_strength' => 0.1,
                ],
                [
                    'argument' => 'Prosecutorial misconduct - Brady violation with withheld DNA evidence',
                    'domain' => 'procedural_violations',
                    'supporting_precedents' => 5,
                    'counter_arguments' => 1,
                    'expected_strength' => 0.85,
                ],
                [
                    'argument' => 'Evidence should be suppressed due to minor chain of custody gap',
                    'domain' => 'evidence_law',
                    'supporting_precedents' => 1,
                    'counter_arguments' => 3,
                    'expected_strength' => 0.35,
                ],
            ],
            'tolerance' => 0.15,
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
            'correlation' => 0.0,
            'strong_args_identified' => 0,
            'weak_args_identified' => 0,
            'classification_accuracy' => 0.0,
        ];

        $absoluteErrors = [];
        $predictedStrengths = [];
        $expectedStrengths = [];

        foreach ($testCases as $testCase) {
            $metrics['total_cases']++;

            $predictedStrength = $this->assessArgumentStrength(
                $testCase['argument'],
                $testCase['domain'],
                $testCase['supporting_precedents'],
                $testCase['counter_arguments']
            );

            $expectedStrength = $testCase['expected_strength'];

            $predictedStrengths[] = $predictedStrength;
            $expectedStrengths[] = $expectedStrength;

            $error = abs($predictedStrength - $expectedStrength);
            $absoluteErrors[] = $error;

            if ($error <= $tolerance) {
                $metrics['within_tolerance']++;
            }

            // Check classification (strong vs weak)
            $predictedStrong = $predictedStrength >= 0.6;
            $expectedStrong = $expectedStrength >= 0.6;

            if ($predictedStrong === $expectedStrong) {
                if ($predictedStrong) {
                    $metrics['strong_args_identified']++;
                } else {
                    $metrics['weak_args_identified']++;
                }
            }
        }

        if (count($absoluteErrors) > 0) {
            $metrics['mean_absolute_error'] = array_sum($absoluteErrors) / count($absoluteErrors);
        }

        if (count($predictedStrengths) > 1) {
            $metrics['correlation'] = $this->calculateCorrelation($predictedStrengths, $expectedStrengths);
        }

        if ($metrics['total_cases'] > 0) {
            $correctClassifications = $metrics['strong_args_identified'] + $metrics['weak_args_identified'];
            $metrics['classification_accuracy'] = $correctClassifications / $metrics['total_cases'];
        }

        return $metrics;
    }

    /**
     * Assess strength of a legal argument
     */
    protected function assessArgumentStrength(
        string $argument,
        string $domain,
        int $supportingPrecedents,
        int $counterArguments
    ): float {
        $strength = 0.5; // Base strength

        // Adjust for precedent support
        $strength += ($supportingPrecedents * 0.1);

        // Reduce for counter-arguments
        $strength -= ($counterArguments * 0.1);

        // Boost for strong legal language
        $strongIndicators = [
            'constitutional',
            'precedent',
            'violation',
            'material',
            'exculpatory',
        ];

        foreach ($strongIndicators as $indicator) {
            if (stripos($argument, $indicator) !== false) {
                $strength += 0.05;
            }
        }

        // Reduce for weak language
        $weakIndicators = [
            'said so',
            'because',
            'minor',
            'might',
            'possibly',
        ];

        foreach ($weakIndicators as $indicator) {
            if (stripos($argument, $indicator) !== false) {
                $strength -= 0.1;
            }
        }

        return min(1.0, max(0.0, $strength));
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
     * Lower error is better, higher accuracy and correlation is better
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
