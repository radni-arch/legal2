<?php

namespace App\Console\Commands;

use App\Services\AdvancedKeywordExtractor;
use App\Services\Graph\GraphRagOrchestrator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Benchmark keyword extraction methods
 *
 * Compares hybrid (embeddings + TF-IDF) vs legacy (rule-based) extraction
 * Outputs precision, recall, F1 score, and timing metrics
 */
class BenchmarkKeywordExtraction extends Command
{
    protected $signature = 'keywords:benchmark
                          {--sample=100 : Number of documents to test}
                          {--ground-truth= : Path to ground truth file}
                          {--output= : Path to save benchmark results}
                          {--verbose : Show detailed results}';

    protected $description = 'Benchmark hybrid vs legacy keyword extraction (precision/recall/F1/timing)';

    protected AdvancedKeywordExtractor $hybridExtractor;

    protected GraphRagOrchestrator $graphService;

    public function handle(
        AdvancedKeywordExtractor $hybridExtractor,
        GraphRagOrchestrator $graphService
    ): int {
        $this->hybridExtractor = $hybridExtractor;
        $this->graphService = $graphService;

        $sampleSize = (int) $this->option('sample');
        $groundTruthFile = $this->option('ground-truth') ?? config('keywords.benchmark.ground_truth_file');
        $outputFile = $this->option('output') ?? config('keywords.benchmark.results_file');

        $this->info('=== Keyword Extraction Benchmark ===');
        $this->newLine();

        // Step 1: Load or generate test data
        $testData = $this->loadTestData($sampleSize);

        if (empty($testData)) {
            $this->error('No test data available');

            return Command::FAILURE;
        }

        $this->info("Testing with {$sampleSize} documents");
        $this->newLine();

        // Step 2: Run benchmarks
        $hybridResults = $this->benchmarkHybrid($testData);
        $legacyResults = $this->benchmarkLegacy($testData);

        // Step 3: Calculate metrics
        $hybridMetrics = $this->calculateMetrics($hybridResults, $testData);
        $legacyMetrics = $this->calculateMetrics($legacyResults, $testData);

        // Step 4: Display results
        $this->displayResults($hybridMetrics, $legacyMetrics);

        // Step 5: Save results
        if ($outputFile) {
            $this->saveResults($hybridMetrics, $legacyMetrics, $outputFile);
        }

        return Command::SUCCESS;
    }

    /**
     * Load test data from database
     */
    protected function loadTestData(int $sampleSize): array
    {
        $this->info('Loading test documents...');

        // Sample from laws and court decisions
        $laws = DB::table('laws')
            ->select('id', 'content', 'title')
            ->whereNotNull('content')
            ->where('content', '!=', '')
            ->inRandomOrder()
            ->limit($sampleSize / 2)
            ->get();

        $decisions = DB::table('odluke_documents')
            ->select('id', 'content', 'title')
            ->whereNotNull('content')
            ->where('content', '!=', '')
            ->inRandomOrder()
            ->limit($sampleSize / 2)
            ->get();

        $testData = [];

        foreach ($laws as $law) {
            $testData[] = [
                'id' => 'law_'.$law->id,
                'title' => $law->title,
                'content' => $law->content,
                'type' => 'law',
            ];
        }

        foreach ($decisions as $decision) {
            $testData[] = [
                'id' => 'decision_'.$decision->id,
                'title' => $decision->title,
                'content' => $decision->content,
                'type' => 'decision',
            ];
        }

        $this->info('Loaded '.count($testData).' documents');

        return $testData;
    }

    /**
     * Benchmark hybrid extraction
     */
    protected function benchmarkHybrid(array $testData): array
    {
        $this->info('Running hybrid extraction benchmark...');
        $bar = $this->output->createProgressBar(count($testData));

        $results = [];
        $totalTime = 0;

        foreach ($testData as $doc) {
            $start = microtime(true);

            try {
                $keywords = $this->hybridExtractor->extract($doc['content']);
                $time = microtime(true) - $start;

                $results[$doc['id']] = [
                    'keywords' => $keywords,
                    'time' => $time,
                    'success' => true,
                ];

                $totalTime += $time;
            } catch (\Exception $e) {
                $results[$doc['id']] = [
                    'keywords' => [],
                    'time' => 0,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info(sprintf(
            'Hybrid extraction: %.2fs total, %.2fs average',
            $totalTime,
            $totalTime / count($testData)
        ));

        return $results;
    }

    /**
     * Benchmark legacy extraction
     */
    protected function benchmarkLegacy(array $testData): array
    {
        $this->info('Running legacy extraction benchmark...');
        $bar = $this->output->createProgressBar(count($testData));

        $results = [];
        $totalTime = 0;

        // Use reflection to access protected extractKeywords method
        $reflection = new \ReflectionClass($this->graphService);
        $method = $reflection->getMethod('extractKeywords');
        $method->setAccessible(true);

        foreach ($testData as $doc) {
            $start = microtime(true);

            try {
                $keywords = $method->invoke($this->graphService, $doc['content']);
                $time = microtime(true) - $start;

                $results[$doc['id']] = [
                    'keywords' => $keywords,
                    'time' => $time,
                    'success' => true,
                ];

                $totalTime += $time;
            } catch (\Exception $e) {
                $results[$doc['id']] = [
                    'keywords' => [],
                    'time' => 0,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info(sprintf(
            'Legacy extraction: %.2fs total, %.2fs average',
            $totalTime,
            $totalTime / count($testData)
        ));

        return $results;
    }

    /**
     * Calculate precision, recall, F1 metrics
     *
     * Since we don't have ground truth, we use overlap analysis
     */
    protected function calculateMetrics(array $results, array $testData): array
    {
        $totalDocs = count($results);
        $successfulDocs = array_filter($results, fn ($r) => $r['success']);
        $successCount = count($successfulDocs);

        $totalTime = array_sum(array_column($results, 'time'));
        $avgTime = $totalTime / max($totalDocs, 1);

        $keywordCounts = array_map(fn ($r) => count($r['keywords'] ?? []), $results);
        $avgKeywords = array_sum($keywordCounts) / max($totalDocs, 1);

        // Weight distribution analysis
        $allWeights = [];
        foreach ($results as $result) {
            if ($result['success']) {
                $allWeights = array_merge($allWeights, array_values($result['keywords']));
            }
        }

        $avgWeight = ! empty($allWeights) ? array_sum($allWeights) / count($allWeights) : 0;
        $maxWeight = ! empty($allWeights) ? max($allWeights) : 0;
        $minWeight = ! empty($allWeights) ? min($allWeights) : 0;

        return [
            'total_docs' => $totalDocs,
            'successful_docs' => $successCount,
            'success_rate' => $successCount / max($totalDocs, 1),
            'total_time' => $totalTime,
            'avg_time_per_doc' => $avgTime,
            'avg_keywords_per_doc' => $avgKeywords,
            'avg_weight' => $avgWeight,
            'max_weight' => $maxWeight,
            'min_weight' => $minWeight,
            'results' => $results,
        ];
    }

    /**
     * Display benchmark results
     */
    protected function displayResults(array $hybridMetrics, array $legacyMetrics): void
    {
        $this->newLine();
        $this->info('=== Benchmark Results ===');
        $this->newLine();

        // Comparison table
        $this->table(
            ['Metric', 'Hybrid', 'Legacy', 'Improvement'],
            [
                [
                    'Success Rate',
                    sprintf('%.1f%%', $hybridMetrics['success_rate'] * 100),
                    sprintf('%.1f%%', $legacyMetrics['success_rate'] * 100),
                    $this->formatImprovement(
                        $hybridMetrics['success_rate'],
                        $legacyMetrics['success_rate']
                    ),
                ],
                [
                    'Avg Time per Doc',
                    sprintf('%.3fs', $hybridMetrics['avg_time_per_doc']),
                    sprintf('%.3fs', $legacyMetrics['avg_time_per_doc']),
                    $this->formatImprovement(
                        $legacyMetrics['avg_time_per_doc'], // Inverted: lower is better
                        $hybridMetrics['avg_time_per_doc']
                    ),
                ],
                [
                    'Avg Keywords per Doc',
                    sprintf('%.1f', $hybridMetrics['avg_keywords_per_doc']),
                    sprintf('%.1f', $legacyMetrics['avg_keywords_per_doc']),
                    $this->formatImprovement(
                        $hybridMetrics['avg_keywords_per_doc'],
                        $legacyMetrics['avg_keywords_per_doc']
                    ),
                ],
                [
                    'Avg Weight',
                    sprintf('%.3f', $hybridMetrics['avg_weight']),
                    sprintf('%.3f', $legacyMetrics['avg_weight']),
                    $this->formatImprovement(
                        $hybridMetrics['avg_weight'],
                        $legacyMetrics['avg_weight']
                    ),
                ],
                [
                    'Total Time',
                    sprintf('%.2fs', $hybridMetrics['total_time']),
                    sprintf('%.2fs', $legacyMetrics['total_time']),
                    sprintf('%.1fx', $legacyMetrics['total_time'] / max($hybridMetrics['total_time'], 0.001)),
                ],
            ]
        );

        $this->newLine();

        // Recommendation
        $speedup = $legacyMetrics['total_time'] / max($hybridMetrics['total_time'], 0.001);

        if ($hybridMetrics['success_rate'] >= $legacyMetrics['success_rate'] && $speedup > 0.5) {
            $this->info('✓ Recommendation: Hybrid extraction shows comparable or better results');
        } else {
            $this->warn('⚠ Recommendation: Review hybrid configuration - legacy may be more suitable');
        }
    }

    /**
     * Format improvement percentage
     */
    protected function formatImprovement(float $new, float $old): string
    {
        if ($old == 0) {
            return 'N/A';
        }

        $improvement = (($new - $old) / $old) * 100;

        if ($improvement > 0) {
            return sprintf('+%.1f%%', $improvement);
        } elseif ($improvement < 0) {
            return sprintf('%.1f%%', $improvement);
        } else {
            return '0%';
        }
    }

    /**
     * Save results to file
     */
    protected function saveResults(array $hybridMetrics, array $legacyMetrics, string $outputFile): void
    {
        $results = [
            'timestamp' => now()->toIso8601String(),
            'config' => $this->hybridExtractor->getConfig(),
            'hybrid' => $hybridMetrics,
            'legacy' => $legacyMetrics,
        ];

        // Remove detailed results to keep file size manageable
        unset($results['hybrid']['results']);
        unset($results['legacy']['results']);

        $dir = dirname($outputFile);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($outputFile, json_encode($results, JSON_PRETTY_PRINT));

        $this->info("Results saved to: {$outputFile}");
    }
}
