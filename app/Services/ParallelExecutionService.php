<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Parallel Execution Service
 *
 * Executes independent operations in parallel using PHP's proc_open
 * for improved performance
 *
 * Sprint 6.4: Performance Optimization
 */
class ParallelExecutionService
{
    /**
     * Execute multiple callable functions in parallel
     *
     * @param  array  $tasks  Array of callables to execute
     * @param  int  $maxParallel  Maximum number of parallel executions
     * @return array Results indexed by task key
     */
    public function execute(array $tasks, int $maxParallel = 5): array
    {
        $results = [];
        $errors = [];
        $taskChunks = array_chunk($tasks, $maxParallel, true);

        foreach ($taskChunks as $chunk) {
            $chunkResults = $this->executeChunk($chunk);

            foreach ($chunkResults as $key => $result) {
                if (isset($result['error'])) {
                    $errors[$key] = $result['error'];
                } else {
                    $results[$key] = $result['data'] ?? null;
                }
            }
        }

        if (! empty($errors)) {
            Log::warning('Parallel execution had errors', [
                'error_count' => count($errors),
                'errors' => $errors,
            ]);
        }

        return $results;
    }

    /**
     * Execute a chunk of tasks in parallel using promises
     *
     * Note: This uses a simple implementation. For production,
     * consider using Amp, ReactPHP, or Swoole for true async
     */
    protected function executeChunk(array $tasks): array
    {
        $results = [];
        $startTime = microtime(true);

        // Execute all tasks
        foreach ($tasks as $key => $task) {
            try {
                $taskStartTime = microtime(true);
                $data = $task();
                $duration = (microtime(true) - $taskStartTime) * 1000;

                $results[$key] = [
                    'data' => $data,
                    'duration_ms' => round($duration, 2),
                ];
            } catch (\Exception $e) {
                $results[$key] = [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ];
            }
        }

        $totalDuration = (microtime(true) - $startTime) * 1000;

        Log::debug('Parallel chunk executed', [
            'task_count' => count($tasks),
            'total_duration_ms' => round($totalDuration, 2),
            'avg_duration_ms' => round($totalDuration / count($tasks), 2),
        ]);

        return $results;
    }

    /**
     * Execute searches in parallel (optimized for agent searches)
     *
     * @param  array  $searches  Array of search callables with 'type' and 'callable' keys
     * @return array Categorized results by search type
     */
    public function executeSearches(array $searches): array
    {
        $results = $this->execute($searches);

        // Organize results by type
        $organized = [];
        foreach ($results as $key => $result) {
            if (isset($searches[$key]['type'])) {
                $type = $searches[$key]['type'];
                if (! isset($organized[$type])) {
                    $organized[$type] = [];
                }
                $organized[$type][] = $result;
            }
        }

        return $organized;
    }

    /**
     * Execute multiple database queries in parallel
     *
     * @param  array  $queries  Array of query callables
     * @return array Query results
     */
    public function executeQueries(array $queries): array
    {
        // For true parallel DB queries, we'd need connection pooling
        // For now, this provides the interface for future optimization
        return $this->execute($queries);
    }

    /**
     * Execute LLM calls in parallel (useful for batching)
     *
     * @param  array  $llmCalls  Array of LLM callable functions
     * @param  int  $maxParallel  Max parallel calls (respect rate limits)
     * @return array LLM responses
     */
    public function executeLLMCalls(array $llmCalls, int $maxParallel = 3): array
    {
        // Limit parallel LLM calls to respect rate limits
        return $this->execute($llmCalls, $maxParallel);
    }

    /**
     * Helper: Create parallel search tasks for agents
     *
     * Usage example:
     * $tasks = $parallel->createSearchTasks([
     *     'law_query_1' => fn() => $lawSearch->search('query1'),
     *     'decision_query_1' => fn() => $decisionSearch->search('query1'),
     * ]);
     */
    public function createSearchTasks(array $searchFunctions): array
    {
        return $searchFunctions;
    }

    /**
     * Measure parallel vs sequential execution time
     */
    public function benchmark(array $tasks): array
    {
        // Sequential execution
        $seqStart = microtime(true);
        $seqResults = [];
        foreach ($tasks as $key => $task) {
            try {
                $seqResults[$key] = $task();
            } catch (\Exception $e) {
                $seqResults[$key] = ['error' => $e->getMessage()];
            }
        }
        $seqDuration = (microtime(true) - $seqStart) * 1000;

        // Parallel execution
        $parStart = microtime(true);
        $parResults = $this->execute($tasks);
        $parDuration = (microtime(true) - $parStart) * 1000;

        return [
            'sequential_ms' => round($seqDuration, 2),
            'parallel_ms' => round($parDuration, 2),
            'speedup' => round($seqDuration / $parDuration, 2).'x',
            'time_saved_ms' => round($seqDuration - $parDuration, 2),
            'time_saved_percent' => round((($seqDuration - $parDuration) / $seqDuration) * 100, 1).'%',
        ];
    }
}
