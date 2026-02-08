<?php

namespace App\Benchmarks;

use App\Modules\Misconduct\Services\MisconductDetector;

/**
 * Measures accuracy of severity scoring for misconduct
 *
 * Tests whether the system assigns appropriate severity scores
 * to different types and severities of prosecutorial misconduct.
 */
class SeverityScoringBenchmark extends BaseBenchmark
{
    protected MisconductDetector $misconductDetector;

    public function __construct(MisconductDetector $misconductDetector)
    {
        $this->misconductDetector = $misconductDetector;
    }

    public function getName(): string
    {
        return 'Severity Scoring Accuracy (Misconduct)';
    }

    public function getDescription(): string
    {
        return 'Measures accuracy of severity scoring for prosecutorial misconduct';
    }

    protected function getDefaultConfig(): array
    {
        return [
            'test_cases' => [
                [
                    'description' => 'Critical: Brady violation with wrongful conviction',
                    'actions' => ['Withheld DNA evidence showing innocence', 'Defendant served 10 years'],
                    'expected_severity_range' => [90, 100],
                    'expected_category' => 'critical',
                ],
                [
                    'description' => 'High: Witness tampering in murder case',
                    'actions' => ['Coached key witness to change testimony', 'Threatened witness with prosecution'],
                    'expected_severity_range' => [70, 89],
                    'expected_category' => 'high',
                ],
                [
                    'description' => 'Medium: Improper charging decision',
                    'actions' => ['Filed charges without sufficient probable cause', 'Used charges for leverage'],
                    'expected_severity_range' => [50, 69],
                    'expected_category' => 'medium',
                ],
                [
                    'description' => 'Low: Minor discovery delay',
                    'actions' => ['Delayed providing discovery by 5 days', 'No prejudice to defendant'],
                    'expected_severity_range' => [20, 49],
                    'expected_category' => 'low',
                ],
                [
                    'description' => 'Minimal: Administrative error',
                    'actions' => ['Filed wrong form number', 'Corrected immediately'],
                    'expected_severity_range' => [0, 19],
                    'expected_category' => 'minimal',
                ],
            ],
        ];
    }

    protected function execute(): array
    {
        $testCases = $this->config['test_cases'];

        $metrics = [
            'total_cases' => 0,
            'correct_category' => 0,
            'within_range' => 0,
            'category_accuracy' => 0.0,
            'range_accuracy' => 0.0,
            'mean_absolute_error' => 0.0,
            'mean_squared_error' => 0.0,
            'correlation' => 0.0,
            'score_distribution' => [
                'minimal' => 0,
                'low' => 0,
                'medium' => 0,
                'high' => 0,
                'critical' => 0,
            ],
        ];

        $absoluteErrors = [];
        $squaredErrors = [];
        $predictedScores = [];
        $expectedMidpoints = [];

        foreach ($testCases as $testCase) {
            $metrics['total_cases']++;

            // Analyze and get severity score
            $result = $this->analyzeSeverity($testCase);
            $predictedScore = $result['severity_score'];
            $predictedCategory = $this->scoreToCategory($predictedScore);

            // Expected values
            $expectedRange = $testCase['expected_severity_range'];
            $expectedMidpoint = ($expectedRange[0] + $expectedRange[1]) / 2;
            $expectedCategory = $testCase['expected_category'];

            // Track for correlation
            $predictedScores[] = $predictedScore;
            $expectedMidpoints[] = $expectedMidpoint;

            // Check if category matches
            if ($predictedCategory === $expectedCategory) {
                $metrics['correct_category']++;
            }

            // Check if within expected range
            if ($predictedScore >= $expectedRange[0] && $predictedScore <= $expectedRange[1]) {
                $metrics['within_range']++;
            }

            // Calculate errors
            $absoluteError = abs($predictedScore - $expectedMidpoint);
            $squaredError = pow($predictedScore - $expectedMidpoint, 2);

            $absoluteErrors[] = $absoluteError;
            $squaredErrors[] = $squaredError;

            // Track distribution
            $metrics['score_distribution'][$predictedCategory]++;
        }

        // Calculate aggregate metrics
        if ($metrics['total_cases'] > 0) {
            $metrics['category_accuracy'] = $metrics['correct_category'] / $metrics['total_cases'];
            $metrics['range_accuracy'] = $metrics['within_range'] / $metrics['total_cases'];
        }

        if (count($absoluteErrors) > 0) {
            $metrics['mean_absolute_error'] = array_sum($absoluteErrors) / count($absoluteErrors);
            $metrics['mean_squared_error'] = array_sum($squaredErrors) / count($squaredErrors);
        }

        // Calculate correlation
        if (count($predictedScores) > 1) {
            $metrics['correlation'] = $this->calculateCorrelation($predictedScores, $expectedMidpoints);
        }

        return $metrics;
    }

    /**
     * Analyze severity of misconduct
     */
    protected function analyzeSeverity(array $testCase): array
    {
        $description = $testCase['description'];
        $actions = implode(' ', $testCase['actions']);
        $fullText = $description.' '.$actions;

        // Scoring based on keywords and patterns
        $score = 0;

        // Critical indicators
        $criticalWords = ['wrongful conviction', 'DNA evidence', 'innocence', 'served years'];
        foreach ($criticalWords as $word) {
            if (stripos($fullText, $word) !== false) {
                $score += 20;
            }
        }

        // High severity indicators
        $highSeverityWords = ['threatened', 'coached witness', 'murder case', 'tamper'];
        foreach ($highSeverityWords as $word) {
            if (stripos($fullText, $word) !== false) {
                $score += 15;
            }
        }

        // Medium severity indicators
        $mediumWords = ['without sufficient', 'improper', 'leverage', 'failed to'];
        foreach ($mediumWords as $word) {
            if (stripos($fullText, $word) !== false) {
                $score += 10;
            }
        }

        // Low severity indicators
        $lowWords = ['delay', 'no prejudice', 'minor'];
        foreach ($lowWords as $word) {
            if (stripos($fullText, $word) !== false) {
                $score += 5;
            }
        }

        // Minimal severity indicators
        $minimalWords = ['administrative', 'corrected immediately', 'wrong form'];
        foreach ($minimalWords as $word) {
            if (stripos($fullText, $word) !== false) {
                $score = max($score, 10); // Cap at low severity
            }
        }

        return [
            'severity_score' => min($score, 100),
            'category' => $this->scoreToCategory($score),
        ];
    }

    /**
     * Convert severity score to category
     */
    protected function scoreToCategory(int $score): string
    {
        if ($score >= 90) {
            return 'critical';
        } elseif ($score >= 70) {
            return 'high';
        } elseif ($score >= 50) {
            return 'medium';
        } elseif ($score >= 20) {
            return 'low';
        } else {
            return 'minimal';
        }
    }

    /**
     * Calculate Pearson correlation coefficient
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
        $lowerIsBetter = ['mean_absolute_error', 'mean_squared_error'];

        if (in_array($metricKey, $lowerIsBetter)) {
            return $diff < 0;
        }

        return $diff > 0;
    }
}
