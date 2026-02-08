<?php

namespace App\Console\Commands;

use App\Benchmarks\BaseBenchmark;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class BenchmarkRunCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'benchmark:run
                            {benchmark? : The benchmark class name or "all" to run all benchmarks}
                            {--compare= : Git commit hash to compare against}
                            {--config= : JSON string of configuration options}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run benchmarks to measure system performance';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $benchmarkArg = $this->argument('benchmark');
        $compareCommit = $this->option('compare');
        $configJson = $this->option('config');

        // Parse config
        $config = $configJson ? json_decode($configJson, true) : [];
        if ($configJson && json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Invalid JSON in --config option');

            return 1;
        }

        // Get benchmarks to run
        $benchmarks = $this->getBenchmarksToRun($benchmarkArg);

        if (empty($benchmarks)) {
            $this->error('No benchmarks found to run');
            $this->info('Available benchmarks:');
            foreach ($this->discoverBenchmarks() as $class) {
                $instance = app($class);
                $this->line("  - {$class} ({$instance->getName()})");
            }

            return 1;
        }

        $this->info('Running '.count($benchmarks).' benchmark(s)...');
        $this->newLine();

        $results = [];

        foreach ($benchmarks as $benchmarkClass) {
            try {
                $result = $this->runBenchmark($benchmarkClass, $config, $compareCommit);
                $results[] = $result;
            } catch (\Exception $e) {
                $this->error("Failed to run benchmark {$benchmarkClass}: {$e->getMessage()}");
            }
        }

        // Summary
        $this->newLine();
        $this->info('Benchmark Summary');
        $this->line(str_repeat('=', 60));

        $successCount = count(array_filter($results, fn ($r) => $r['success']));
        $this->info("Total: {$successCount}/".count($results).' completed successfully');

        return 0;
    }

    /**
     * Run a single benchmark
     */
    protected function runBenchmark(string $benchmarkClass, array $config, ?string $compareCommit): array
    {
        $this->info("Running: {$benchmarkClass}");

        $benchmark = app($benchmarkClass);

        if (! $benchmark instanceof BaseBenchmark) {
            throw new \RuntimeException('Benchmark class must extend BaseBenchmark');
        }

        // Set configuration
        if (! empty($config)) {
            $benchmark->setConfig($config);
        }

        // Run benchmark
        $run = $benchmark->run();

        // Display results
        $this->displayResults($run);

        // Compare if requested
        if ($compareCommit) {
            $comparison = $benchmark->compareWithCommit($compareCommit);
            if ($comparison) {
                $this->displayComparison($comparison);
            } else {
                $this->warn("No baseline found for commit: {$compareCommit}");
            }
        }

        $this->newLine();

        return [
            'class' => $benchmarkClass,
            'run_id' => $run->id,
            'success' => $run->isSuccessful(),
        ];
    }

    /**
     * Display benchmark results
     */
    protected function displayResults($run)
    {
        $this->line("  Status: {$run->status}");
        $this->line("  Duration: {$run->getFormattedDuration()}");
        $this->line("  Commit: {$run->git_commit_hash}".($run->git_dirty ? ' (dirty)' : ''));

        if (! empty($run->metrics)) {
            $this->line('  Metrics:');
            foreach ($run->metrics as $key => $value) {
                $formattedValue = is_numeric($value) ? number_format($value, 4) : $value;
                $this->line("    - {$key}: {$formattedValue}");
            }
        }

        if ($run->isFailed()) {
            $this->error("  Error: {$run->error_message}");
        }
    }

    /**
     * Display comparison results
     */
    protected function displayComparison(array $comparison)
    {
        $this->newLine();
        $this->info('  Comparison with baseline:');
        $this->line("    Baseline: {$comparison['baseline']['commit']} ({$comparison['baseline']['date']})");
        $this->line("    Current:  {$comparison['current']['commit']} ({$comparison['current']['date']})");

        if (! empty($comparison['changes'])) {
            $this->line('    Changes:');
            foreach ($comparison['changes'] as $metric => $change) {
                $symbol = $change['improved'] ? '↑' : '↓';
                $color = $change['improved'] ? 'green' : 'red';

                $this->line(sprintf(
                    '      - %s: %.4f → %.4f (%+.2f%%) %s',
                    $metric,
                    $change['baseline'],
                    $change['current'],
                    $change['percent_change'],
                    $symbol
                ), $color);
            }
        }
    }

    /**
     * Get benchmarks to run based on argument
     */
    protected function getBenchmarksToRun(?string $benchmarkArg): array
    {
        if (! $benchmarkArg || $benchmarkArg === 'all') {
            return $this->discoverBenchmarks();
        }

        // Check if it's a fully qualified class name
        if (class_exists($benchmarkArg)) {
            return [$benchmarkArg];
        }

        // Try to find by partial name
        $allBenchmarks = $this->discoverBenchmarks();
        $matches = array_filter($allBenchmarks, function ($class) use ($benchmarkArg) {
            return str_contains(strtolower($class), strtolower($benchmarkArg));
        });

        return array_values($matches);
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
}
