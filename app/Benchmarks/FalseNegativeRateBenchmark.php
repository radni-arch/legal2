<?php

namespace App\Benchmarks;

use App\Modules\Misconduct\Services\MisconductDetector;

/**
 * Measures false negative rate in prosecutorial misconduct detection
 *
 * Tests how often the system fails to detect actual misconduct,
 * which could lead to violations going unaddressed.
 */
class FalseNegativeRateBenchmark extends BaseBenchmark
{
    protected MisconductDetector $misconductDetector;

    public function __construct(MisconductDetector $misconductDetector)
    {
        $this->misconductDetector = $misconductDetector;
    }

    public function getName(): string
    {
        return 'False Negative Rate (Misconduct Detection)';
    }

    public function getDescription(): string
    {
        return 'Measures false negative rate - missed misconduct detections';
    }

    protected function getDefaultConfig(): array
    {
        return [
            'misconduct_cases' => [
                [
                    'description' => 'Brady violation - withheld exculpatory evidence',
                    'misconduct_type' => 'brady_violation',
                    'prosecutor_actions' => [
                        'Failed to disclose witness statement favorable to defense',
                        'Withheld forensic evidence report showing innocence',
                    ],
                    'expected_severity_min' => 70,
                ],
                [
                    'description' => 'Witness coaching',
                    'misconduct_type' => 'witness_tampering',
                    'prosecutor_actions' => [
                        'Coached witness to change testimony',
                        'Instructed witness to omit exculpatory details',
                    ],
                    'expected_severity_min' => 60,
                ],
                [
                    'description' => 'Improper charging',
                    'misconduct_type' => 'improper_charging',
                    'prosecutor_actions' => [
                        'Filed charges without sufficient evidence',
                        'Used charges as leverage for cooperation',
                    ],
                    'expected_severity_min' => 50,
                ],
                [
                    'description' => 'Suppression of evidence',
                    'misconduct_type' => 'evidence_suppression',
                    'prosecutor_actions' => [
                        'Destroyed potentially exculpatory evidence',
                        'Failed to preserve crime scene photos',
                    ],
                    'expected_severity_min' => 75,
                ],
            ],
            'detection_threshold' => 40, // Minimum severity to consider detected
        ];
    }

    protected function execute(): array
    {
        $misconductCases = $this->config['misconduct_cases'];
        $detectionThreshold = $this->config['detection_threshold'];

        $metrics = [
            'total_misconduct_cases' => 0,
            'true_positives' => 0,
            'false_negatives' => 0,
            'false_negative_rate' => 0.0,
            'sensitivity' => 0.0, // True positive rate / recall
            'avg_severity_detected' => 0.0,
            'avg_severity_missed' => 0.0,
            'detection_by_type' => [],
        ];

        $detectedSeverities = [];
        $missedSeverities = [];

        foreach ($misconductCases as $testCase) {
            $metrics['total_misconduct_cases']++;
            $misconductType = $testCase['misconduct_type'];

            // Initialize type tracking
            if (! isset($metrics['detection_by_type'][$misconductType])) {
                $metrics['detection_by_type'][$misconductType] = [
                    'total' => 0,
                    'detected' => 0,
                    'missed' => 0,
                ];
            }
            $metrics['detection_by_type'][$misconductType]['total']++;

            // Analyze for misconduct
            $result = $this->analyzeMisconduct($testCase);
            $severityScore = $result['severity_score'] ?? 0;

            if ($severityScore >= $detectionThreshold) {
                // True positive: correctly detected misconduct
                $metrics['true_positives']++;
                $metrics['detection_by_type'][$misconductType]['detected']++;
                $detectedSeverities[] = $severityScore;
            } else {
                // False negative: failed to detect actual misconduct
                $metrics['false_negatives']++;
                $metrics['detection_by_type'][$misconductType]['missed']++;
                $missedSeverities[] = $severityScore;
            }
        }

        // Calculate rates
        if ($metrics['total_misconduct_cases'] > 0) {
            $metrics['false_negative_rate'] = $metrics['false_negatives'] / $metrics['total_misconduct_cases'];
            $metrics['sensitivity'] = $metrics['true_positives'] / $metrics['total_misconduct_cases'];
        }

        if (count($detectedSeverities) > 0) {
            $metrics['avg_severity_detected'] = array_sum($detectedSeverities) / count($detectedSeverities);
        }

        if (count($missedSeverities) > 0) {
            $metrics['avg_severity_missed'] = array_sum($missedSeverities) / count($missedSeverities);
        }

        return $metrics;
    }

    /**
     * Analyze a case for misconduct
     */
    protected function analyzeMisconduct(array $testCase): array
    {
        $caseText = $testCase['description']."\n\n";
        $caseText .= "Prosecutor actions:\n";
        foreach ($testCase['prosecutor_actions'] as $action) {
            $caseText .= "- {$action}\n";
        }

        // Simplified misconduct scoring based on keywords
        $severeWords = [
            'withheld' => 20,
            'failed to disclose' => 20,
            'destroyed' => 25,
            'coached' => 20,
            'fabricated' => 25,
            'suppressed' => 20,
            'without sufficient evidence' => 15,
            'intimidated' => 20,
            'threatened' => 20,
        ];

        $severityScore = 0;
        $detectedTypes = [];

        foreach ($severeWords as $word => $weight) {
            if (stripos($caseText, $word) !== false) {
                $severityScore += $weight;
                $detectedTypes[] = $testCase['misconduct_type'];
            }
        }

        return [
            'severity_score' => min($severityScore, 100),
            'misconduct_types' => array_unique($detectedTypes),
            'analysis' => $caseText,
        ];
    }

    /**
     * Lower false negative rate is better, higher sensitivity is better
     */
    public function isImprovement(string $metricKey, float $diff): bool
    {
        $lowerIsBetter = ['false_negatives', 'false_negative_rate', 'avg_severity_missed'];

        if (in_array($metricKey, $lowerIsBetter)) {
            return $diff < 0;
        }

        return $diff > 0; // sensitivity, true_positives, avg_severity_detected
    }
}
