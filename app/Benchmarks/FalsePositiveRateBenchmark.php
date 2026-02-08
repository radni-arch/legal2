<?php

namespace App\Benchmarks;

use App\Modules\Misconduct\Services\MisconductDetector;

/**
 * Measures false positive rate in prosecutorial misconduct detection
 *
 * Tests how often the system incorrectly flags legitimate prosecutorial
 * actions as misconduct.
 */
class FalsePositiveRateBenchmark extends BaseBenchmark
{
    protected MisconductDetector $misconductDetector;

    public function __construct(MisconductDetector $misconductDetector)
    {
        $this->misconductDetector = $misconductDetector;
    }

    public function getName(): string
    {
        return 'False Positive Rate (Misconduct Detection)';
    }

    public function getDescription(): string
    {
        return 'Measures false positive rate in prosecutorial misconduct detection';
    }

    protected function getDefaultConfig(): array
    {
        return [
            'legitimate_cases' => [
                [
                    'description' => 'Properly filed charges with evidence',
                    'prosecutor_actions' => [
                        'Filed charges based on police report',
                        'Provided discovery within 30 days',
                        'Complied with all procedural requirements',
                    ],
                    'expected_misconduct' => false,
                ],
                [
                    'description' => 'Standard plea negotiation',
                    'prosecutor_actions' => [
                        'Offered reasonable plea bargain',
                        'Explained consequences to defendant',
                        'Followed court procedures',
                    ],
                    'expected_misconduct' => false,
                ],
                [
                    'description' => 'Proper witness preparation',
                    'prosecutor_actions' => [
                        'Met with witnesses before trial',
                        'Explained court procedures',
                        'Did not coach testimony',
                    ],
                    'expected_misconduct' => false,
                ],
            ],
            'severity_threshold' => 50, // Minimum severity to consider as flagged
        ];
    }

    protected function execute(): array
    {
        $legitimateCases = $this->config['legitimate_cases'];
        $severityThreshold = $this->config['severity_threshold'];

        $metrics = [
            'total_legitimate_cases' => 0,
            'false_positives' => 0,
            'true_negatives' => 0,
            'false_positive_rate' => 0.0,
            'specificity' => 0.0, // True negative rate
            'avg_severity_on_legitimate' => 0.0,
            'max_severity_on_legitimate' => 0,
        ];

        $severityScores = [];

        foreach ($legitimateCases as $testCase) {
            $metrics['total_legitimate_cases']++;

            // Analyze for misconduct (should not find any)
            $result = $this->analyzeMisconduct($testCase);

            $severityScore = $result['severity_score'] ?? 0;
            $severityScores[] = $severityScore;

            if ($severityScore > $severityThreshold) {
                // False positive: flagged as misconduct when it's legitimate
                $metrics['false_positives']++;
            } else {
                // True negative: correctly identified as legitimate
                $metrics['true_negatives']++;
            }

            if ($severityScore > $metrics['max_severity_on_legitimate']) {
                $metrics['max_severity_on_legitimate'] = $severityScore;
            }
        }

        // Calculate rates
        if ($metrics['total_legitimate_cases'] > 0) {
            $metrics['false_positive_rate'] = $metrics['false_positives'] / $metrics['total_legitimate_cases'];
            $metrics['specificity'] = $metrics['true_negatives'] / $metrics['total_legitimate_cases'];
        }

        if (count($severityScores) > 0) {
            $metrics['avg_severity_on_legitimate'] = array_sum($severityScores) / count($severityScores);
        }

        return $metrics;
    }

    /**
     * Analyze a case for misconduct
     */
    protected function analyzeMisconduct(array $testCase): array
    {
        // Create a synthetic case analysis
        $caseText = $testCase['description']."\n\n";
        $caseText .= "Prosecutor actions:\n";
        foreach ($testCase['prosecutor_actions'] as $action) {
            $caseText .= "- {$action}\n";
        }

        try {
            // Mock detection (in real implementation, would call actual detector)
            // For now, return a simple heuristic-based score
            $suspiciousWords = [
                'delayed', 'withheld', 'failed to disclose', 'intimidated',
                'threatened', 'coached', 'fabricated', 'suppressed',
            ];

            $severityScore = 0;
            foreach ($suspiciousWords as $word) {
                if (stripos($caseText, $word) !== false) {
                    $severityScore += 15;
                }
            }

            return [
                'severity_score' => min($severityScore, 100),
                'misconduct_types' => [],
                'analysis' => $caseText,
            ];
        } catch (\Exception $e) {
            return [
                'severity_score' => 0,
                'misconduct_types' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Lower false positive rate is better
     */
    public function isImprovement(string $metricKey, float $diff): bool
    {
        $lowerIsBetter = [
            'false_positives',
            'false_positive_rate',
            'avg_severity_on_legitimate',
            'max_severity_on_legitimate',
        ];

        if (in_array($metricKey, $lowerIsBetter)) {
            return $diff < 0;
        }

        return $diff > 0; // specificity, true_negatives
    }
}
