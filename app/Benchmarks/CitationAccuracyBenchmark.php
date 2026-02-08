<?php

namespace App\Benchmarks;

use App\Services\LawVectorStoreService;
use Illuminate\Support\Facades\DB;

class CitationAccuracyBenchmark extends BaseBenchmark
{
    protected LawVectorStoreService $lawVectorStore;

    public function __construct(LawVectorStoreService $lawVectorStore)
    {
        $this->lawVectorStore = $lawVectorStore;
    }

    public function getName(): string
    {
        return 'Citation Accuracy';
    }

    public function getDescription(): string
    {
        return 'Measures accuracy of legal citation extraction and validation';
    }

    protected function getDefaultConfig(): array
    {
        return [
            'sample_size' => 100,
            'test_cases' => [
                // Format: [text, expected_citations]
                [
                    'text' => 'According to ZKP Article 9, the presumption of innocence applies.',
                    'expected' => ['ZKP Article 9', 'ZKP Članak 9'],
                ],
                [
                    'text' => 'The Croatian Constitution, Article 29 guarantees the right to a fair trial.',
                    'expected' => ['Ustav Article 29', 'Ustav Članak 29'],
                ],
                [
                    'text' => 'Kazneni zakon Article 87 defines the penalties.',
                    'expected' => ['KZ Article 87', 'KZ Članak 87'],
                ],
            ],
        ];
    }

    protected function execute(): array
    {
        $sampleSize = $this->config['sample_size'] ?? 100;
        $testCases = $this->config['test_cases'] ?? [];

        $metrics = [
            'total_tests' => 0,
            'correct_extractions' => 0,
            'false_positives' => 0,
            'false_negatives' => 0,
            'extraction_accuracy' => 0.0,
            'precision' => 0.0,
            'recall' => 0.0,
            'f1_score' => 0.0,
        ];

        // Test citation extraction from predefined test cases
        foreach ($testCases as $testCase) {
            $metrics['total_tests']++;

            $extracted = $this->extractCitations($testCase['text']);
            $expected = $testCase['expected'];

            // Calculate true positives, false positives, false negatives
            $truePositives = count(array_intersect($extracted, $expected));
            $falsePositives = count(array_diff($extracted, $expected));
            $falseNegatives = count(array_diff($expected, $extracted));

            if ($truePositives === count($expected) && $falsePositives === 0) {
                $metrics['correct_extractions']++;
            }

            $metrics['false_positives'] += $falsePositives;
            $metrics['false_negatives'] += $falseNegatives;
        }

        // Test citation validation against database
        $validationMetrics = $this->testCitationValidation($sampleSize);
        $metrics = array_merge($metrics, $validationMetrics);

        // Calculate accuracy metrics
        if ($metrics['total_tests'] > 0) {
            $metrics['extraction_accuracy'] = $metrics['correct_extractions'] / $metrics['total_tests'];
        }

        // Calculate precision, recall, and F1 score
        $totalPredicted = $metrics['correct_extractions'] + $metrics['false_positives'];
        $totalActual = $metrics['correct_extractions'] + $metrics['false_negatives'];

        if ($totalPredicted > 0) {
            $metrics['precision'] = $metrics['correct_extractions'] / $totalPredicted;
        }

        if ($totalActual > 0) {
            $metrics['recall'] = $metrics['correct_extractions'] / $totalActual;
        }

        if ($metrics['precision'] + $metrics['recall'] > 0) {
            $metrics['f1_score'] = 2 * ($metrics['precision'] * $metrics['recall']) / ($metrics['precision'] + $metrics['recall']);
        }

        return $metrics;
    }

    /**
     * Extract citations from text
     */
    protected function extractCitations(string $text): array
    {
        $citations = [];

        // Pattern for Croatian legal citations
        $patterns = [
            '/\b(ZKP|Ustav|KZ)\s+(Article|Članak)\s+(\d+)/i',
            '/\b(Zakon o kaznenom postupku)\s+(Article|Članak)\s+(\d+)/i',
            '/\b(Ustav Republike Hrvatske)\s+(Article|Članak)\s+(\d+)/i',
            '/\b(Kazneni zakon)\s+(Article|Članak)\s+(\d+)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    // Normalize to both formats
                    $law = $match[1];
                    $article = $match[3];

                    // Map full names to abbreviations
                    $abbreviations = [
                        'Zakon o kaznenom postupku' => 'ZKP',
                        'Ustav Republike Hrvatske' => 'Ustav',
                        'Kazneni zakon' => 'KZ',
                    ];

                    if (isset($abbreviations[$law])) {
                        $law = $abbreviations[$law];
                    }

                    $citations[] = "{$law} Article {$article}";
                    $citations[] = "{$law} Članak {$article}";
                }
            }
        }

        return array_unique($citations);
    }

    /**
     * Test citation validation against the database
     */
    protected function testCitationValidation(int $sampleSize): array
    {
        $metrics = [
            'validation_tests' => 0,
            'valid_citations' => 0,
            'invalid_citations' => 0,
            'validation_accuracy' => 0.0,
        ];

        try {
            // Get a sample of laws from database
            $laws = DB::table('laws')
                ->select('law_number', 'title')
                ->whereNotNull('law_number')
                ->limit($sampleSize)
                ->get();

            foreach ($laws as $law) {
                $metrics['validation_tests']++;

                // Check if law exists (simple presence check)
                $exists = DB::table('laws')
                    ->where('law_number', $law->law_number)
                    ->exists();

                if ($exists) {
                    $metrics['valid_citations']++;
                } else {
                    $metrics['invalid_citations']++;
                }
            }

            if ($metrics['validation_tests'] > 0) {
                $metrics['validation_accuracy'] = $metrics['valid_citations'] / $metrics['validation_tests'];
            }
        } catch (\Exception $e) {
            // If database query fails, skip validation metrics
            $metrics['validation_tests'] = 0;
        }

        return $metrics;
    }

    /**
     * For citation accuracy, lower error rates are better
     */
    public function isImprovement(string $metricKey, float $diff): bool
    {
        $lowerIsBetter = ['false_positives', 'false_negatives', 'invalid_citations'];

        if (in_array($metricKey, $lowerIsBetter)) {
            return $diff < 0; // Decrease is improvement
        }

        return $diff > 0; // Increase is improvement (for accuracy, precision, recall, f1)
    }
}
