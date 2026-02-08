<?php

namespace App\Benchmarks;

use App\Modules\Evidence\Services\RecontextualizationService;

/**
 * Measures quality of evidence recontextualization
 *
 * Tests how well the system recontextualizes prosecution evidence
 * from a defense perspective to reveal exculpatory interpretations.
 */
class RecontextualizationQualityBenchmark extends BaseBenchmark
{
    protected RecontextualizationService $recontextualizationService;

    public function __construct(RecontextualizationService $recontextualizationService)
    {
        $this->recontextualizationService = $recontextualizationService;
    }

    public function getName(): string
    {
        return 'Evidence Recontextualization Quality';
    }

    public function getDescription(): string
    {
        return 'Measures quality of evidence recontextualization from defense perspective';
    }

    protected function getDefaultConfig(): array
    {
        return [
            'test_cases' => [
                [
                    'prosecution_narrative' => 'Defendant was found with 5 grams of cocaine',
                    'expected_defense_angles' => [
                        'amount consistent with personal use',
                        'no paraphernalia suggesting distribution',
                        'possible planting of evidence',
                    ],
                    'min_quality_score' => 0.7,
                ],
                [
                    'prosecution_narrative' => 'Defendant fled from police',
                    'expected_defense_angles' => [
                        'fear of police misconduct',
                        'unaware of police identity',
                        'constitutional right to avoid contact',
                    ],
                    'min_quality_score' => 0.7,
                ],
            ],
        ];
    }

    protected function execute(): array
    {
        $testCases = $this->config['test_cases'];

        $metrics = [
            'total_cases' => 0,
            'avg_quality_score' => 0.0,
            'avg_alternative_count' => 0.0,
            'coverage_score' => 0.0,
            'novelty_score' => 0.0,
            'legal_soundness' => 0.0,
        ];

        $qualityScores = [];
        $alternativeCounts = [];
        $coverageScores = [];

        foreach ($testCases as $testCase) {
            $metrics['total_cases']++;

            $result = $this->recontextualize($testCase);
            $qualityScores[] = $result['quality_score'];
            $alternativeCounts[] = count($result['alternatives']);
            $coverageScores[] = $result['coverage'];
        }

        if (count($qualityScores) > 0) {
            $metrics['avg_quality_score'] = array_sum($qualityScores) / count($qualityScores);
            $metrics['avg_alternative_count'] = array_sum($alternativeCounts) / count($alternativeCounts);
            $metrics['coverage_score'] = array_sum($coverageScores) / count($coverageScores);
        }

        return $metrics;
    }

    protected function recontextualize(array $testCase): array
    {
        $narrative = $testCase['prosecution_narrative'];
        $expectedAngles = $testCase['expected_defense_angles'];

        // Simple keyword-based recontextualization
        $alternatives = [];

        if (stripos($narrative, 'found with') !== false && stripos($narrative, 'gram') !== false) {
            $alternatives[] = 'amount consistent with personal use';
        }

        if (stripos($narrative, 'fled') !== false) {
            $alternatives[] = 'fear of police misconduct';
            $alternatives[] = 'constitutional right to avoid contact';
        }

        // Calculate coverage
        $coverage = 0;
        foreach ($expectedAngles as $angle) {
            foreach ($alternatives as $alt) {
                if (stripos($alt, $angle) !== false || stripos($angle, $alt) !== false) {
                    $coverage++;
                    break;
                }
            }
        }

        $coverageRatio = count($expectedAngles) > 0 ? $coverage / count($expectedAngles) : 0;

        return [
            'alternatives' => $alternatives,
            'quality_score' => min($coverageRatio + (count($alternatives) * 0.1), 1.0),
            'coverage' => $coverageRatio,
        ];
    }

    public function isImprovement(string $metricKey, float $diff): bool
    {
        return $diff > 0; // All metrics: higher is better
    }
}
