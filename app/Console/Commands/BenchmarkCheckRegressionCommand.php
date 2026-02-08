<?php

namespace App\Console\Commands;

use App\Benchmarks\BaseBenchmark;
use App\Models\BenchmarkRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class BenchmarkCheckRegressionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'benchmark:check-regression
                            {baseline? : Git commit hash for baseline (default: main branch HEAD)}
                            {--threshold=0.05 : Regression threshold (default 5%)}
                            {--benchmarks=* : Specific benchmarks to check (default: all)}
                            {--fail-fast : Exit on first regression}
                            {--format=text : Output format (text, json, github)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for benchmark regressions against baseline';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $baselineCommit = $this->argument('baseline');
        $threshold = (float) $this->option('threshold');
        $specificBenchmarks = $this->option('benchmarks');
        $failFast = $this->option('fail-fast');
        $format = $this->option('format');

        // Get baseline commit (default to main branch HEAD)
        if (! $baselineCommit) {
            $baselineCommit = $this->getMainBranchHead();
            if (! $baselineCommit) {
                $this->error('Could not determine baseline commit. Please specify explicitly.');

                return 1;
            }
        }

        $this->info("Checking for regressions against baseline: {$baselineCommit}");
        $this->info('Threshold: '.($threshold * 100).'%');
        $this->newLine();

        // Get benchmarks to check
        $benchmarksToCheck = empty($specificBenchmarks)
            ? $this->discoverBenchmarks()
            : $this->resolveBenchmarkClasses($specificBenchmarks);

        if (empty($benchmarksToCheck)) {
            $this->error('No benchmarks found to check');

            return 1;
        }

        // Run benchmarks and check for regressions
        $results = [];
        $hasRegressions = false;

        foreach ($benchmarksToCheck as $benchmarkClass) {
            $this->line("Checking: {$benchmarkClass}");

            try {
                $result = $this->checkBenchmarkRegression(
                    $benchmarkClass,
                    $baselineCommit,
                    $threshold
                );

                $results[] = $result;

                if ($result['has_regression']) {
                    $hasRegressions = true;

                    if ($failFast) {
                        $this->error('  ⚠️  REGRESSION DETECTED - failing fast');
                        break;
                    }
                }
            } catch (\Exception $e) {
                $this->error("  Failed to check {$benchmarkClass}: {$e->getMessage()}");
                $results[] = [
                    'benchmark' => $benchmarkClass,
                    'error' => $e->getMessage(),
                    'has_regression' => false,
                ];
            }
        }

        // Output results
        $this->newLine();
        $this->outputResults($results, $format, $threshold);

        // Return exit code
        if ($hasRegressions) {
            $this->error('❌ Regression check FAILED - performance degradation detected');

            return 1;
        }

        $this->info('✅ Regression check PASSED - no significant degradation');

        return 0;
    }

    /**
     * Check a single benchmark for regression
     */
    protected function checkBenchmarkRegression(
        string $benchmarkClass,
        string $baselineCommit,
        float $threshold
    ): array {
        // Run the benchmark
        $benchmark = app($benchmarkClass);

        if (! $benchmark instanceof BaseBenchmark) {
            throw new \RuntimeException('Benchmark must extend BaseBenchmark');
        }

        $currentRun = $benchmark->run();

        // Get baseline run
        $baselineRun = BenchmarkRun::forBenchmark($benchmarkClass)
            ->forCommit($baselineCommit)
            ->completed()
            ->latest()
            ->first();

        if (! $baselineRun) {
            $this->warn("  ⚠️  No baseline found for commit {$baselineCommit}");

            return [
                'benchmark' => $benchmarkClass,
                'name' => $benchmark->getName(),
                'has_regression' => false,
                'no_baseline' => true,
                'current_run_id' => $currentRun->id,
            ];
        }

        // Compare metrics
        $regressions = [];
        $improvements = [];
        $stable = [];

        foreach ($currentRun->metrics as $metricKey => $currentValue) {
            $baselineValue = $baselineRun->getMetric($metricKey);

            if ($baselineValue === null || ! is_numeric($currentValue) || ! is_numeric($baselineValue)) {
                continue;
            }

            // Skip if baseline is zero (can't calculate percentage)
            if ($baselineValue == 0) {
                continue;
            }

            $diff = $currentValue - $baselineValue;
            $percentChange = ($diff / abs($baselineValue)) * 100;

            $isImprovement = $benchmark->isImprovement($metricKey, $diff);

            $metricResult = [
                'metric' => $metricKey,
                'baseline' => $baselineValue,
                'current' => $currentValue,
                'diff' => $diff,
                'percent_change' => $percentChange,
                'is_improvement' => $isImprovement,
            ];

            // Check for regression (performance got worse beyond threshold)
            if (! $isImprovement && abs($percentChange) > ($threshold * 100)) {
                $regressions[] = $metricResult;
            } elseif ($isImprovement && abs($percentChange) > ($threshold * 100)) {
                $improvements[] = $metricResult;
            } else {
                $stable[] = $metricResult;
            }
        }

        return [
            'benchmark' => $benchmarkClass,
            'name' => $benchmark->getName(),
            'has_regression' => ! empty($regressions),
            'baseline_commit' => $baselineCommit,
            'current_run_id' => $currentRun->id,
            'baseline_run_id' => $baselineRun->id,
            'regressions' => $regressions,
            'improvements' => $improvements,
            'stable' => $stable,
        ];
    }

    /**
     * Output results in specified format
     */
    protected function outputResults(array $results, string $format, float $threshold): void
    {
        if ($format === 'json') {
            $this->line(json_encode($results, JSON_PRETTY_PRINT));

            return;
        }

        if ($format === 'github') {
            $this->outputGitHubFormat($results, $threshold);

            return;
        }

        // Text format (default)
        $this->line('Regression Check Summary');
        $this->line(str_repeat('=', 80));

        $totalBenchmarks = count($results);
        $benchmarksWithRegression = count(array_filter($results, fn ($r) => $r['has_regression'] ?? false));
        $benchmarksWithImprovements = count(array_filter($results, fn ($r) => ! empty($r['improvements'] ?? [])));

        $this->line("Total benchmarks: {$totalBenchmarks}");
        $this->line("With regressions: {$benchmarksWithRegression}");
        $this->line("With improvements: {$benchmarksWithImprovements}");
        $this->newLine();

        foreach ($results as $result) {
            if (isset($result['error'])) {
                $this->error("❌ {$result['benchmark']}: {$result['error']}");

                continue;
            }

            if (isset($result['no_baseline'])) {
                $this->warn("⚠️  {$result['name']}: No baseline (skipped)");

                continue;
            }

            $symbol = $result['has_regression'] ? '❌' : '✅';
            $this->line("{$symbol} {$result['name']}");

            // Show regressions
            if (! empty($result['regressions'])) {
                $this->line('  Regressions:');
                foreach ($result['regressions'] as $reg) {
                    $this->error(sprintf(
                        '    - %s: %.4f → %.4f (%+.2f%%)',
                        $reg['metric'],
                        $reg['baseline'],
                        $reg['current'],
                        $reg['percent_change']
                    ));
                }
            }

            // Show improvements
            if (! empty($result['improvements'])) {
                $this->line('  Improvements:');
                foreach ($result['improvements'] as $imp) {
                    $this->info(sprintf(
                        '    - %s: %.4f → %.4f (%+.2f%%)',
                        $imp['metric'],
                        $imp['baseline'],
                        $imp['current'],
                        $imp['percent_change']
                    ));
                }
            }

            $this->newLine();
        }
    }

    /**
     * Output results in GitHub Actions format (for PR comments)
     */
    protected function outputGitHubFormat(array $results, float $threshold): void
    {
        $output = "## Benchmark Regression Check\n\n";
        $output .= '**Threshold:** '.($threshold * 100)."%\n\n";

        $hasRegressions = false;
        $hasImprovements = false;

        foreach ($results as $result) {
            if ($result['has_regression'] ?? false) {
                $hasRegressions = true;
            }
            if (! empty($result['improvements'] ?? [])) {
                $hasImprovements = true;
            }
        }

        // Summary
        $status = $hasRegressions ? '❌ FAILED' : '✅ PASSED';
        $output .= "**Status:** {$status}\n\n";

        // Detailed results
        $output .= "### Results\n\n";

        foreach ($results as $result) {
            if (isset($result['error'])) {
                $output .= "❌ **{$result['benchmark']}**: Error - {$result['error']}\n\n";

                continue;
            }

            if (isset($result['no_baseline'])) {
                $output .= "⚠️ **{$result['name']}**: No baseline data\n\n";

                continue;
            }

            $symbol = $result['has_regression'] ? '❌' : '✅';
            $output .= "{$symbol} **{$result['name']}**\n\n";

            // Regressions table
            if (! empty($result['regressions'])) {
                $output .= "**Regressions:**\n\n";
                $output .= "| Metric | Baseline | Current | Change |\n";
                $output .= "|--------|----------|---------|--------|\n";

                foreach ($result['regressions'] as $reg) {
                    $output .= sprintf(
                        "| %s | %.4f | %.4f | %+.2f%% |\n",
                        $reg['metric'],
                        $reg['baseline'],
                        $reg['current'],
                        $reg['percent_change']
                    );
                }

                $output .= "\n";
            }

            // Improvements table
            if (! empty($result['improvements'])) {
                $output .= "**Improvements:**\n\n";
                $output .= "| Metric | Baseline | Current | Change |\n";
                $output .= "|--------|----------|---------|--------|\n";

                foreach ($result['improvements'] as $imp) {
                    $output .= sprintf(
                        "| %s | %.4f | %.4f | %+.2f%% |\n",
                        $imp['metric'],
                        $imp['baseline'],
                        $imp['current'],
                        $imp['percent_change']
                    );
                }

                $output .= "\n";
            }
        }

        $this->line($output);

        // Also save to file for GitHub Actions to use
        file_put_contents('benchmark-results.md', $output);
        $this->info('Results saved to benchmark-results.md');
    }

    /**
     * Get main branch HEAD commit
     */
    protected function getMainBranchHead(): ?string
    {
        try {
            // Try 'main' branch
            $result = \Illuminate\Support\Facades\Process::run('git rev-parse origin/main');
            if ($result->successful()) {
                return trim($result->output());
            }

            // Try 'master' branch
            $result = \Illuminate\Support\Facades\Process::run('git rev-parse origin/master');
            if ($result->successful()) {
                return trim($result->output());
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Discover all benchmark classes
     */
    protected function discoverBenchmarks(): array
    {
        $benchmarkPath = app_path('Benchmarks');

        if (! File::exists($benchmarkPath)) {
            return [];
        }

        $benchmarks = [];
        $files = File::allFiles($benchmarkPath);

        foreach ($files as $file) {
            $class = 'App\\Benchmarks\\'.str_replace(
                ['/', '.php'],
                ['\\', ''],
                $file->getRelativePathname()
            );

            if (class_exists($class) && is_subclass_of($class, BaseBenchmark::class)) {
                $benchmarks[] = $class;
            }
        }

        return $benchmarks;
    }

    /**
     * Resolve benchmark class names
     */
    protected function resolveBenchmarkClasses(array $benchmarkNames): array
    {
        $allBenchmarks = $this->discoverBenchmarks();
        $resolved = [];

        foreach ($benchmarkNames as $name) {
            // Check if it's a fully qualified class name
            if (class_exists($name)) {
                $resolved[] = $name;

                continue;
            }

            // Try to find by partial name
            $matches = array_filter($allBenchmarks, function ($class) use ($name) {
                return str_contains(strtolower($class), strtolower($name));
            });

            $resolved = array_merge($resolved, array_values($matches));
        }

        return array_unique($resolved);
    }
}
