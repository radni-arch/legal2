<?php

namespace App\Console\Commands;

use App\Agents\Specialists\PrecedentAnalystAgent;
use App\Agents\Specialists\ResearchSpecialistAgent;
use App\Agents\Specialists\RiskAnalystAgent;
use App\Agents\Specialists\StrategySpecialistAgent;
use App\Services\Collaboration\SharedAgentContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProfileAgentPerformanceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agents:profile
                            {agent? : Agent to profile (research|precedent|risk|strategy|all)}
                            {--runs=5 : Number of test runs per agent}
                            {--save : Save results to file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Profile agent execution performance and identify bottlenecks';

    protected array $results = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $agent = $this->argument('agent') ?? 'all';
        $runs = (int) $this->option('runs');

        $this->info('🔬 Agent Performance Profiler');
        $this->info('============================');
        $this->newLine();

        // Enable query logging
        DB::enableQueryLog();

        $agents = $this->getAgentsToProfile($agent);

        foreach ($agents as $agentName => $agentClass) {
            $this->info("Profiling {$agentName}...");
            $this->profileAgent($agentName, $agentClass, $runs);
            $this->newLine();
        }

        $this->displaySummary();

        if ($this->option('save')) {
            $this->saveResults();
        }

        return 0;
    }

    /**
     * Get agents to profile
     */
    protected function getAgentsToProfile(string $agent): array
    {
        $allAgents = [
            'ResearchSpecialist' => ResearchSpecialistAgent::class,
            'PrecedentAnalyst' => PrecedentAnalystAgent::class,
            'RiskAnalyst' => RiskAnalystAgent::class,
            'StrategySpecialist' => StrategySpecialistAgent::class,
        ];

        if ($agent === 'all') {
            return $allAgents;
        }

        $agentMap = [
            'research' => 'ResearchSpecialist',
            'precedent' => 'PrecedentAnalyst',
            'risk' => 'RiskAnalyst',
            'strategy' => 'StrategySpecialist',
        ];

        $agentName = $agentMap[strtolower($agent)] ?? null;

        if (! $agentName || ! isset($allAgents[$agentName])) {
            $this->error("Unknown agent: {$agent}");
            $this->info('Available: research, precedent, risk, strategy, all');
            exit(1);
        }

        return [$agentName => $allAgents[$agentName]];
    }

    /**
     * Profile an agent
     */
    protected function profileAgent(string $agentName, string $agentClass, int $runs): void
    {
        $timings = [];
        $queryStats = [];
        $memoryStats = [];

        $testProblem = $this->getTestProblem($agentName);

        for ($i = 0; $i < $runs; $i++) {
            $this->line('  Run '.($i + 1)."/{$runs}...");

            // Clear query log
            DB::flushQueryLog();

            $memoryBefore = memory_get_usage(true);
            $startTime = microtime(true);

            try {
                $this->runAgent($agentName, $agentClass, $testProblem);

                $endTime = microtime(true);
                $memoryAfter = memory_get_usage(true);

                $duration = ($endTime - $startTime) * 1000; // Convert to ms
                $memoryUsed = ($memoryAfter - $memoryBefore) / 1024 / 1024; // Convert to MB

                $timings[] = $duration;
                $memoryStats[] = $memoryUsed;

                // Capture query stats
                $queries = DB::getQueryLog();
                $queryStats[] = [
                    'count' => count($queries),
                    'total_time' => array_sum(array_column($queries, 'time')),
                    'queries' => $queries,
                ];

            } catch (\Exception $e) {
                $this->error("  Error: {$e->getMessage()}");

                continue;
            }

            // Small delay between runs
            usleep(100000); // 100ms
        }

        if (empty($timings)) {
            $this->error('  All runs failed');

            return;
        }

        // Calculate statistics
        $avgTime = array_sum($timings) / count($timings);
        $minTime = min($timings);
        $maxTime = max($timings);
        $avgMemory = array_sum($memoryStats) / count($memoryStats);
        $avgQueryCount = array_sum(array_column($queryStats, 'count')) / count($queryStats);
        $avgQueryTime = array_sum(array_column($queryStats, 'total_time')) / count($queryStats);

        // Store results
        $this->results[$agentName] = [
            'runs' => $runs,
            'avg_time_ms' => round($avgTime, 2),
            'min_time_ms' => round($minTime, 2),
            'max_time_ms' => round($maxTime, 2),
            'avg_memory_mb' => round($avgMemory, 2),
            'avg_query_count' => round($avgQueryCount, 2),
            'avg_query_time_ms' => round($avgQueryTime, 2),
            'timings' => $timings,
            'query_stats' => $queryStats,
        ];

        // Display results
        $this->displayAgentResults($agentName, $this->results[$agentName]);

        // Analyze bottlenecks
        $this->analyzeBottlenecks($agentName, $this->results[$agentName]);
    }

    /**
     * Run an agent
     */
    protected function runAgent(string $agentName, string $agentClass, string $problem): void
    {
        $context = new SharedAgentContext(
            sessionId: 'profile_'.uniqid(),
            problemStatement: $problem
        );

        // Prepare context based on agent dependencies
        $this->prepareContext($context, $agentName);

        // Instantiate and execute agent
        $agent = app($agentClass);
        $agent->execute($context, ['type' => 'profiling', 'description' => 'Performance profiling test']);
    }

    /**
     * Prepare context for agent
     */
    protected function prepareContext(SharedAgentContext $context, string $agentName): void
    {
        switch ($agentName) {
            case 'ResearchSpecialist':
                // No preparation needed
                break;

            case 'PrecedentAnalyst':
                // Mock research results
                $context->write('researched_decisions', [
                    ['id' => 1, 'court' => 'Vrhovni sud', 'case_number' => 'Test-1', 'title' => 'Test decision'],
                ]);
                $context->write('researched_laws', [
                    ['id' => 1, 'law_number' => 'ZKP 1', 'title' => 'Test law', 'similarity' => 0.9],
                ]);
                break;

            case 'RiskAnalyst':
                // Mock strategy results
                $context->write('legal_arguments', [
                    ['title' => 'Test Argument', 'argument' => 'Test', 'strength_score' => 70, 'potential_counterarguments' => []],
                ]);
                $context->write('strategic_recommendations', ['procedural_steps' => []]);
                $context->write('strongest_precedents', []);
                break;

            case 'StrategySpecialist':
                // Mock precedent results
                $context->write('strongest_precedents', [
                    ['court' => 'Vrhovni sud', 'applicability_score' => 85, 'binding_authority' => 'binding'],
                ]);
                $context->write('analyzed_laws', [
                    ['law_number' => 'ZKP 1', 'title' => 'Test law', 'relevance_score' => 0.9],
                ]);
                $context->write('distinguishing_factors', []);
                break;
        }
    }

    /**
     * Get test problem for agent
     */
    protected function getTestProblem(string $agentName): string
    {
        return 'Client charged with minor drug possession (2g marijuana) after 4-hour home search. Question: Was the search proportionate to the offense?';
    }

    /**
     * Display results for an agent
     */
    protected function displayAgentResults(string $agentName, array $results): void
    {
        $avgTime = $results['avg_time_ms'];
        $target = $this->getTargetTime($agentName);

        $color = $avgTime <= $target ? 'green' : ($avgTime <= $target * 1.5 ? 'yellow' : 'red');

        $this->line("  <fg={$color}>Avg Time: {$avgTime}ms</> (target: {$target}ms)");
        $this->line("  Range: {$results['min_time_ms']}ms - {$results['max_time_ms']}ms");
        $this->line("  Memory: {$results['avg_memory_mb']}MB");
        $this->line("  Queries: {$results['avg_query_count']} queries ({$results['avg_query_time_ms']}ms)");
    }

    /**
     * Get target time for agent
     */
    protected function getTargetTime(string $agentName): int
    {
        return match ($agentName) {
            'ResearchSpecialist' => 5000, // 5 seconds
            'PrecedentAnalyst' => 8000,   // 8 seconds
            'RiskAnalyst' => 6000,        // 6 seconds
            'StrategySpecialist' => 7000, // 7 seconds
            default => 10000,
        };
    }

    /**
     * Analyze bottlenecks
     */
    protected function analyzeBottlenecks(string $agentName, array $results): void
    {
        $avgTime = $results['avg_time_ms'];
        $avgQueryTime = $results['avg_query_time_ms'];
        $avgQueryCount = $results['avg_query_count'];

        $bottlenecks = [];

        // Check if queries are a bottleneck (>30% of time)
        if ($avgQueryTime > ($avgTime * 0.3)) {
            $percentage = round(($avgQueryTime / $avgTime) * 100, 1);
            $bottlenecks[] = "Database queries consuming {$percentage}% of time ({$avgQueryTime}ms)";
        }

        // Check if too many queries
        if ($avgQueryCount > 50) {
            $bottlenecks[] = "High query count: {$avgQueryCount} queries (consider query reduction)";
        }

        // Analyze individual slow queries
        $slowQueries = $this->findSlowQueries($results['query_stats']);
        if (! empty($slowQueries)) {
            $bottlenecks[] = 'Found '.count($slowQueries).' slow queries (>100ms)';
        }

        if (! empty($bottlenecks)) {
            $this->newLine();
            $this->warn('  ⚠️  Bottlenecks Detected:');
            foreach ($bottlenecks as $bottleneck) {
                $this->line("     - {$bottleneck}");
            }
        }
    }

    /**
     * Find slow queries
     */
    protected function findSlowQueries(array $queryStats): array
    {
        $slowQueries = [];

        foreach ($queryStats as $stat) {
            foreach ($stat['queries'] as $query) {
                if ($query['time'] > 100) { // >100ms
                    $slowQueries[] = [
                        'query' => $query['query'],
                        'time' => $query['time'],
                        'bindings' => $query['bindings'],
                    ];
                }
            }
        }

        return $slowQueries;
    }

    /**
     * Display summary
     */
    protected function displaySummary(): void
    {
        $this->info('Performance Summary');
        $this->info('==================');
        $this->newLine();

        $table = [];
        foreach ($this->results as $agentName => $results) {
            $target = $this->getTargetTime($agentName);
            $avgTime = $results['avg_time_ms'];
            $status = $avgTime <= $target ? '✅' : ($avgTime <= $target * 1.5 ? '⚠️' : '❌');

            $table[] = [
                $agentName,
                $status,
                round($avgTime, 0).'ms',
                $target.'ms',
                round(($avgTime / $target) * 100, 0).'%',
                $results['avg_query_count'],
            ];
        }

        $this->table(
            ['Agent', 'Status', 'Avg Time', 'Target', '% of Target', 'Queries'],
            $table
        );

        // Recommendations
        $this->newLine();
        $this->info('Optimization Recommendations:');
        $this->line('1. Add indexes for slow queries');
        $this->line('2. Implement result caching for repeated searches');
        $this->line('3. Parallelize independent operations');
        $this->line('4. Batch LLM calls where possible');
        $this->line('5. Optimize N+1 query patterns');
    }

    /**
     * Save results to file
     */
    protected function saveResults(): void
    {
        $timestamp = now()->format('Y-m-d_His');
        $filename = "agent_performance_{$timestamp}.json";
        $path = storage_path("app/profiling/{$filename}");

        // Ensure directory exists
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        // Save results
        file_put_contents($path, json_encode([
            'timestamp' => now()->toIso8601String(),
            'results' => $this->results,
        ], JSON_PRETTY_PRINT));

        $this->newLine();
        $this->info("Results saved to: {$path}");
    }
}
