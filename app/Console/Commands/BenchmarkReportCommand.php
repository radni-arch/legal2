<?php

namespace App\Console\Commands;

use App\Models\BenchmarkRun;
use Illuminate\Console\Command;

class BenchmarkReportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'benchmark:report
                            {--benchmark= : Filter by benchmark class}
                            {--commit= : Filter by git commit hash}
                            {--limit=10 : Number of runs to show}
                            {--compare : Show comparison between commits}
                            {--format=table : Output format (table, json, csv)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate reports from benchmark runs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $benchmarkClass = $this->option('benchmark');
        $commitHash = $this->option('commit');
        $limit = (int) $this->option('limit');
        $compare = $this->option('compare');
        $format = $this->option('format');

        // Build query
        $query = BenchmarkRun::query()->latest();

        if ($benchmarkClass) {
            $query->forBenchmark($benchmarkClass);
        }

        if ($commitHash) {
            $query->forCommit($commitHash);
        }

        $runs = $query->limit($limit)->get();

        if ($runs->isEmpty()) {
            $this->warn('No benchmark runs found');

            return 0;
        }

        if ($compare) {
            $this->displayComparison($runs);
        } else {
            $this->displayRuns($runs, $format);
        }

        return 0;
    }

    /**
     * Display benchmark runs
     */
    protected function displayRuns($runs, string $format)
    {
        if ($format === 'json') {
            $this->line(json_encode($runs->toArray(), JSON_PRETTY_PRINT));

            return;
        }

        if ($format === 'csv') {
            $this->outputCsv($runs);

            return;
        }

        // Table format (default)
        $this->info('Benchmark Runs');
        $this->line(str_repeat('=', 120));

        $headers = ['ID', 'Benchmark', 'Status', 'Commit', 'Started', 'Duration', 'Key Metrics'];

        $rows = $runs->map(function ($run) {
            $metrics = collect($run->metrics)
                ->take(3)
                ->map(fn ($val, $key) => "$key: ".number_format($val, 2))
                ->implode(', ');

            return [
                substr($run->id, 0, 8).'...',
                class_basename($run->benchmark_class),
                $run->status,
                substr($run->git_commit_hash, 0, 7).($run->git_dirty ? '*' : ''),
                $run->started_at->format('Y-m-d H:i'),
                $run->getFormattedDuration(),
                substr($metrics, 0, 40).($metrics ? '...' : '-'),
            ];
        });

        $this->table($headers, $rows);

        // Show summary
        $this->newLine();
        $this->info('Summary:');
        $this->line('  Total runs: '.$runs->count());
        $this->line('  Completed: '.$runs->where('status', 'completed')->count());
        $this->line('  Failed: '.$runs->where('status', 'failed')->count());
        $this->line('  Running: '.$runs->where('status', 'running')->count());
    }

    /**
     * Display comparison between commits
     */
    protected function displayComparison($runs)
    {
        // Group by benchmark class
        $grouped = $runs->groupBy('benchmark_class');

        foreach ($grouped as $benchmarkClass => $benchmarkRuns) {
            $this->info("Benchmark: $benchmarkClass");
            $this->line(str_repeat('-', 120));

            // Get unique commits
            $commits = $benchmarkRuns->pluck('git_commit_hash')->unique()->values();

            if ($commits->count() < 2) {
                $this->warn('  Need at least 2 different commits to compare');
                $this->newLine();

                continue;
            }

            // Compare first two commits
            $newerRun = $benchmarkRuns->where('git_commit_hash', $commits[0])->first();
            $olderRun = $benchmarkRuns->where('git_commit_hash', $commits[1])->first();

            $this->line('  Comparing:');
            $this->line("    Newer: {$commits[0]} ({$newerRun->started_at->format('Y-m-d H:i')})");
            $this->line("    Older: {$commits[1]} ({$olderRun->started_at->format('Y-m-d H:i')})");
            $this->newLine();

            // Compare metrics
            $headers = ['Metric', 'Older', 'Newer', 'Change', '% Change', 'Trend'];
            $rows = [];

            foreach ($olderRun->metrics as $metricKey => $olderValue) {
                $newerValue = $newerRun->getMetric($metricKey);

                if ($newerValue !== null && is_numeric($olderValue) && is_numeric($newerValue)) {
                    $diff = $newerValue - $olderValue;
                    $percentChange = $olderValue != 0 ? ($diff / $olderValue) * 100 : 0;
                    $trend = $diff > 0 ? '↑' : ($diff < 0 ? '↓' : '→');

                    $rows[] = [
                        $metricKey,
                        number_format($olderValue, 4),
                        number_format($newerValue, 4),
                        sprintf('%+.4f', $diff),
                        sprintf('%+.2f%%', $percentChange),
                        $trend,
                    ];
                }
            }

            $this->table($headers, $rows);
            $this->newLine();
        }
    }

    /**
     * Output runs as CSV
     */
    protected function outputCsv($runs)
    {
        $headers = [
            'id',
            'benchmark_class',
            'benchmark_name',
            'status',
            'git_commit_hash',
            'git_branch',
            'started_at',
            'duration_ms',
        ];

        // Add metrics columns
        $allMetricKeys = $runs->flatMap(fn ($run) => array_keys($run->metrics ?? []))->unique();

        $headers = array_merge($headers, $allMetricKeys->toArray());

        // Output headers
        $this->line(implode(',', $headers));

        // Output data
        foreach ($runs as $run) {
            $row = [
                $run->id,
                $run->benchmark_class,
                $run->benchmark_name,
                $run->status,
                $run->git_commit_hash,
                $run->git_branch,
                $run->started_at->toIso8601String(),
                $run->duration_ms,
            ];

            // Add metric values
            foreach ($allMetricKeys as $metricKey) {
                $row[] = $run->getMetric($metricKey) ?? '';
            }

            $this->line(implode(',', $row));
        }
    }
}
