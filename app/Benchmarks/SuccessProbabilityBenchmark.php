<?php

namespace App\Benchmarks;

use App\Modules\Defence\Services\DefenseStrategyAnalyzer;

/**
 * Measures accuracy of success probability predictions
 *
 * Tests how accurately the system predicts the probability
 * of success for different defense strategies.
 */
class SuccessProbabilityBenchmark extends BaseBenchmark
{
    protected DefenseStrategyAnalyzer $strategyAnalyzer;

    public function __construct(DefenseStrategyAnalyzer $strategyAnalyzer)
    {
        $this->strategyAnalyzer = $strategyAnalyzer;
    }

    public function getName(): string
    {
        return 'Defense Strategy Success Probability';
    }

    public function getDescription(): string
    {
        return 'Measures accuracy of success probability predictions for defense strategies';
    }

    protected function getDefaultConfig(): array
    {
        return [
            'test_cases' => [
                [
                    'strategy' => 'Motion to suppress illegally obtained evidence',
                    'case_facts' => 'Search without warrant, no exigent circumstances, clear constitutional violation',
                    'expected_probability' => 0.85,
                    'actual_outcome' => 'success',
                ],
                [
                    'strategy' => 'Motion to dismiss for prosecutorial misconduct',
                    'case_facts' => 'Brady violation, withheld exculpatory evidence, material to defense',
                    'expected_probability' => 0.75,
                    'actual_outcome' => 'success',
                ],
                [
                    'strategy' => 'Not guilty plea with weak alibi',
                    'case_facts' => 'Strong prosecution evidence, weak alibi witness, no physical evidence for defense',
                    'expected_probability' => 0.20,
                    'actual_outcome' => 'failure',
                ],
                [
                    'strategy' => 'Plea bargain negotiation',
                    'case_facts' => 'First offense, minor drug possession, willing to accept treatment',
                    'expected_probability' => 0.70,
                    'actual_outcome' => 'success',
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
            'calibration_score' => 0.0,
            'brier_score' => 0.0, // Lower is better
            'correct_outcome_predictions' => 0,
            'prediction_accuracy' => 0.0,
        ];

        $absoluteErrors = [];
        $brierScores = [];

        foreach ($testCases as $testCase) {
            $metrics['total_cases']++;

            $predictedProbability = $this->predictSuccessProbability(
                $testCase['strategy'],
                $testCase['case_facts']
            );

            $expectedProbability = $testCase['expected_probability'];
            $actualOutcome = $testCase['actual_outcome'] === 'success' ? 1.0 : 0.0;

            // Mean absolute error (vs expected probability)
            $error = abs($predictedProbability - $expectedProbability);
            $absoluteErrors[] = $error;

            if ($error <= $tolerance) {
                $metrics['within_tolerance']++;
            }

            // Brier score (vs actual outcome)
            $brierScore = pow($predictedProbability - $actualOutcome, 2);
            $brierScores[] = $brierScore;

            // Check if predicted outcome matches actual
            $predictedOutcome = $predictedProbability >= 0.5 ? 1.0 : 0.0;
            if ($predictedOutcome === $actualOutcome) {
                $metrics['correct_outcome_predictions']++;
            }
        }

        if (count($absoluteErrors) > 0) {
            $metrics['mean_absolute_error'] = array_sum($absoluteErrors) / count($absoluteErrors);
        }

        if (count($brierScores) > 0) {
            $metrics['brier_score'] = array_sum($brierScores) / count($brierScores);
        }

        if ($metrics['total_cases'] > 0) {
            $metrics['prediction_accuracy'] = $metrics['correct_outcome_predictions'] / $metrics['total_cases'];
            $metrics['calibration_score'] = 1.0 - $metrics['brier_score']; // Higher is better
        }

        return $metrics;
    }

    /**
     * Predict success probability for a strategy
     */
    protected function predictSuccessProbability(string $strategy, string $caseFacts): float
    {
        $probability = 0.5; // Base probability

        // Adjust based on strategy type
        if (stripos($strategy, 'suppress') !== false) {
            $probability = 0.6;

            if (stripos($caseFacts, 'constitutional violation') !== false) {
                $probability += 0.2;
            }

            if (stripos($caseFacts, 'without warrant') !== false &&
                stripos($caseFacts, 'no exigent') !== false) {
                $probability += 0.1;
            }
        } elseif (stripos($strategy, 'dismiss') !== false) {
            $probability = 0.5;

            if (stripos($caseFacts, 'Brady violation') !== false) {
                $probability += 0.2;
            }

            if (stripos($caseFacts, 'material to defense') !== false) {
                $probability += 0.1;
            }
        } elseif (stripos($strategy, 'not guilty') !== false) {
            $probability = 0.3;

            if (stripos($caseFacts, 'strong prosecution') !== false) {
                $probability -= 0.1;
            }

            if (stripos($caseFacts, 'weak alibi') !== false) {
                $probability -= 0.1;
            }
        } elseif (stripos($strategy, 'plea') !== false) {
            $probability = 0.65;

            if (stripos($caseFacts, 'first offense') !== false) {
                $probability += 0.05;
            }

            if (stripos($caseFacts, 'willing to accept treatment') !== false) {
                $probability += 0.05;
            }
        }

        return min(1.0, max(0.0, $probability));
    }

    /**
     * Lower error and Brier score is better, higher accuracy and calibration is better
     */
    public function isImprovement(string $metricKey, float $diff): bool
    {
        $lowerIsBetter = ['mean_absolute_error', 'brier_score'];

        if (in_array($metricKey, $lowerIsBetter)) {
            return $diff < 0;
        }

        return $diff > 0;
    }
}
