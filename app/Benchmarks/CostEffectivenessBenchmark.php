<?php

namespace App\Benchmarks;

use Illuminate\Support\Facades\DB;

/**
 * Measures cost-effectiveness of agent operations
 *
 * Tests the cost efficiency of AI agent operations, including
 * token usage, API costs, and cost per successful outcome.
 */
class CostEffectivenessBenchmark extends BaseBenchmark
{
    public function getName(): string
    {
        return 'Agent Cost Effectiveness';
    }

    public function getDescription(): string
    {
        return 'Measures cost-effectiveness of AI agent operations';
    }

    protected function getDefaultConfig(): array
    {
        return [
            'sample_size' => 100,
            'time_window_days' => 30,
            'gpt4_cost_per_1k_input' => 0.03,
            'gpt4_cost_per_1k_output' => 0.06,
            'gpt4mini_cost_per_1k_input' => 0.00015,
            'gpt4mini_cost_per_1k_output' => 0.0006,
        ];
    }

    protected function execute(): array
    {
        $sampleSize = $this->config['sample_size'];
        $timeWindowDays = $this->config['time_window_days'];

        $metrics = [
            'total_operations' => 0,
            'total_cost_usd' => 0.0,
            'avg_cost_per_operation' => 0.0,
            'total_tokens_used' => 0,
            'avg_tokens_per_operation' => 0,
            'cost_per_successful_outcome' => 0.0,
            'cost_savings_vs_baseline' => 0.0,
            'cost_efficiency_score' => 0.0,
        ];

        try {
            // Get recent AI reasoning traces (proxy for agent operations)
            $traces = DB::table('ai_reasoning_traces')
                ->where('created_at', '>=', now()->subDays($timeWindowDays))
                ->whereNotNull('metadata')
                ->limit($sampleSize)
                ->get();

            $metrics['total_operations'] = count($traces);
            $totalCost = 0;
            $totalTokens = 0;
            $successfulOperations = 0;

            foreach ($traces as $trace) {
                $metadata = is_string($trace->metadata) ? json_decode($trace->metadata, true) : $trace->metadata;

                // Extract token usage and cost
                $inputTokens = $metadata['input_tokens'] ?? 0;
                $outputTokens = $metadata['output_tokens'] ?? 0;
                $model = $metadata['model'] ?? 'gpt-4o-mini';

                $totalTokens += $inputTokens + $outputTokens;

                // Calculate cost based on model
                $cost = $this->calculateCost($inputTokens, $outputTokens, $model);
                $totalCost += $cost;

                // Check if operation was successful
                if (isset($metadata['success']) && $metadata['success']) {
                    $successfulOperations++;
                }
            }

            $metrics['total_cost_usd'] = $totalCost;
            $metrics['total_tokens_used'] = $totalTokens;

            if ($metrics['total_operations'] > 0) {
                $metrics['avg_cost_per_operation'] = $totalCost / $metrics['total_operations'];
                $metrics['avg_tokens_per_operation'] = $totalTokens / $metrics['total_operations'];
            }

            if ($successfulOperations > 0) {
                $metrics['cost_per_successful_outcome'] = $totalCost / $successfulOperations;
            }

            // Calculate cost efficiency score (0-1, higher is better)
            // Based on: low cost per operation + high success rate
            $maxAcceptableCost = 0.10; // $0.10 per operation
            $costScore = max(0, 1 - ($metrics['avg_cost_per_operation'] / $maxAcceptableCost));
            $successRate = $metrics['total_operations'] > 0 ? $successfulOperations / $metrics['total_operations'] : 0;

            $metrics['cost_efficiency_score'] = ($costScore + $successRate) / 2;
        } catch (\Exception $e) {
            $metrics['error'] = $e->getMessage();
        }

        return $metrics;
    }

    /**
     * Calculate API cost based on model and token usage
     */
    protected function calculateCost(int $inputTokens, int $outputTokens, string $model): float
    {
        $inputCostPer1k = $this->config['gpt4mini_cost_per_1k_input'];
        $outputCostPer1k = $this->config['gpt4mini_cost_per_1k_output'];

        if (str_contains($model, 'gpt-4o') && ! str_contains($model, 'mini')) {
            $inputCostPer1k = $this->config['gpt4_cost_per_1k_input'];
            $outputCostPer1k = $this->config['gpt4_cost_per_1k_output'];
        }

        $inputCost = ($inputTokens / 1000) * $inputCostPer1k;
        $outputCost = ($outputTokens / 1000) * $outputCostPer1k;

        return $inputCost + $outputCost;
    }

    /**
     * Lower cost is better, higher efficiency is better
     */
    public function isImprovement(string $metricKey, float $diff): bool
    {
        $lowerIsBetter = [
            'total_cost_usd',
            'avg_cost_per_operation',
            'total_tokens_used',
            'avg_tokens_per_operation',
            'cost_per_successful_outcome',
        ];

        if (in_array($metricKey, $lowerIsBetter)) {
            return $diff < 0;
        }

        return $diff > 0;
    }
}
