<?php

namespace App\Benchmarks;

use App\Models\BenchmarkRun;
use Illuminate\Support\Facades\Process;

abstract class BaseBenchmark
{
    protected ?BenchmarkRun $currentRun = null;

    protected array $config = [];

    /**
     * Get the benchmark name
     */
    abstract public function getName(): string;

    /**
     * Get the benchmark description
     */
    abstract public function getDescription(): string;

    /**
     * Execute the benchmark and return metrics
     *
     * @return array Metrics as key-value pairs
     */
    abstract protected function execute(): array;

    /**
     * Get default configuration
     */
    protected function getDefaultConfig(): array
    {
        return [];
    }

    /**
     * Set configuration for this benchmark run
     */
    public function setConfig(array $config): self
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);

        return $this;
    }

    /**
     * Run the benchmark and store results
     */
    public function run(): BenchmarkRun
    {
        // Ensure config has defaults
        if (empty($this->config)) {
            $this->config = $this->getDefaultConfig();
        }

        $startTime = microtime(true);
        $startedAt = now();

        // Get git information
        $gitInfo = $this->getGitInfo();

        // Create benchmark run record
        $this->currentRun = BenchmarkRun::create([
            'benchmark_class' => static::class,
            'benchmark_name' => $this->getName(),
            'description' => $this->getDescription(),
            'git_commit_hash' => $gitInfo['hash'],
            'git_branch' => $gitInfo['branch'],
            'git_dirty' => $gitInfo['dirty'],
            'started_at' => $startedAt,
            'status' => 'running',
            'config' => $this->config,
            'metrics' => [],
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'system_info' => $this->getSystemInfo(),
        ]);

        try {
            // Execute the benchmark
            $metrics = $this->execute();

            // Calculate duration
            $duration = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

            // Update with results
            $this->currentRun->update([
                'status' => 'completed',
                'completed_at' => now(),
                'duration_ms' => (int) $duration,
                'metrics' => $metrics,
            ]);
        } catch (\Exception $e) {
            // Mark as failed and store error
            $this->currentRun->update([
                'status' => 'failed',
                'completed_at' => now(),
                'duration_ms' => (int) ((microtime(true) - $startTime) * 1000),
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }

        return $this->currentRun;
    }

    /**
     * Get git information for current repository state
     */
    protected function getGitInfo(): array
    {
        try {
            // Get commit hash
            $hashResult = Process::run('git rev-parse HEAD');
            $hash = trim($hashResult->output());

            // Get branch name
            $branchResult = Process::run('git rev-parse --abbrev-ref HEAD');
            $branch = trim($branchResult->output());

            // Check for uncommitted changes
            $statusResult = Process::run('git status --porcelain');
            $dirty = ! empty(trim($statusResult->output()));

            return [
                'hash' => substr($hash, 0, 40), // Ensure it's 40 chars max
                'branch' => $branch,
                'dirty' => $dirty,
            ];
        } catch (\Exception $e) {
            // If git is not available or not a git repo, return defaults
            return [
                'hash' => 'unknown',
                'branch' => null,
                'dirty' => false,
            ];
        }
    }

    /**
     * Get system information
     */
    protected function getSystemInfo(): array
    {
        return [
            'os' => PHP_OS,
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
        ];
    }

    /**
     * Get recent benchmark runs for comparison
     */
    public function getRecentRuns(int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return BenchmarkRun::forBenchmark(static::class)
            ->completed()
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Compare this benchmark against a specific commit
     */
    public function compareWithCommit(string $commitHash): ?array
    {
        $baselineRun = BenchmarkRun::forBenchmark(static::class)
            ->forCommit($commitHash)
            ->completed()
            ->latest()
            ->first();

        if (! $baselineRun || ! $this->currentRun) {
            return null;
        }

        return $this->compareTwoRuns($baselineRun, $this->currentRun);
    }

    /**
     * Compare two benchmark runs
     */
    protected function compareTwoRuns(BenchmarkRun $baseline, BenchmarkRun $current): array
    {
        $comparison = [
            'baseline' => [
                'commit' => $baseline->git_commit_hash,
                'date' => $baseline->started_at->format('Y-m-d H:i:s'),
                'metrics' => $baseline->metrics,
            ],
            'current' => [
                'commit' => $current->git_commit_hash,
                'date' => $current->started_at->format('Y-m-d H:i:s'),
                'metrics' => $current->metrics,
            ],
            'changes' => [],
        ];

        // Calculate metric changes
        foreach ($baseline->metrics as $key => $baselineValue) {
            $currentValue = $current->getMetric($key);

            if ($currentValue !== null && is_numeric($baselineValue) && is_numeric($currentValue)) {
                $diff = $currentValue - $baselineValue;
                $percentChange = $baselineValue != 0
                    ? ($diff / $baselineValue) * 100
                    : 0;

                $comparison['changes'][$key] = [
                    'baseline' => $baselineValue,
                    'current' => $currentValue,
                    'diff' => $diff,
                    'percent_change' => round($percentChange, 2),
                    'improved' => $this->isImprovement($key, $diff),
                ];
            }
        }

        return $comparison;
    }

    /**
     * Determine if a metric change is an improvement
     * Override this in child classes if needed
     */
    public function isImprovement(string $metricKey, float $diff): bool
    {
        // By default, higher is better (e.g., accuracy, score)
        // Override for metrics where lower is better (e.g., latency, error_rate)
        return $diff > 0;
    }
}
