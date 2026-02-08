<?php

namespace App\Benchmarks;

use Illuminate\Support\Facades\DB;

/**
 * Measures efficiency of multi-agent collaboration
 *
 * Tests how efficiently agents collaborate, including message passing,
 * task delegation, and coordination overhead.
 */
class CollaborationEfficiencyBenchmark extends BaseBenchmark
{
    public function getName(): string
    {
        return 'Multi-Agent Collaboration Efficiency';
    }

    public function getDescription(): string
    {
        return 'Measures efficiency metrics for multi-agent collaboration';
    }

    protected function getDefaultConfig(): array
    {
        return [
            'sample_size' => 100,
            'time_window_days' => 30,
        ];
    }

    protected function execute(): array
    {
        $sampleSize = $this->config['sample_size'];
        $timeWindowDays = $this->config['time_window_days'];

        $metrics = [
            'total_communications' => 0,
            'avg_message_latency_ms' => 0.0,
            'avg_messages_per_task' => 0.0,
            'successful_delegations' => 0,
            'failed_delegations' => 0,
            'delegation_success_rate' => 0.0,
            'avg_coordination_overhead' => 0.0,
            'parallelization_efficiency' => 0.0,
        ];

        try {
            // Get recent agent communications
            $communications = DB::table('agent_communications')
                ->where('created_at', '>=', now()->subDays($timeWindowDays))
                ->limit($sampleSize)
                ->get();

            $metrics['total_communications'] = count($communications);

            if ($metrics['total_communications'] > 0) {
                // Calculate average message latency
                $latencies = [];
                foreach ($communications as $comm) {
                    // Estimate latency from created_at to processed_at (if available)
                    if (isset($comm->processed_at) && $comm->created_at) {
                        $latency = strtotime($comm->processed_at) - strtotime($comm->created_at);
                        $latencies[] = $latency * 1000; // Convert to ms
                    }
                }

                if (count($latencies) > 0) {
                    $metrics['avg_message_latency_ms'] = array_sum($latencies) / count($latencies);
                }

                // Count successful vs failed delegations
                foreach ($communications as $comm) {
                    $payload = is_string($comm->payload) ? json_decode($comm->payload, true) : $comm->payload;

                    if (isset($payload['type']) && $payload['type'] === 'delegation') {
                        if (isset($payload['status']) && $payload['status'] === 'completed') {
                            $metrics['successful_delegations']++;
                        } else {
                            $metrics['failed_delegations']++;
                        }
                    }
                }

                $totalDelegations = $metrics['successful_delegations'] + $metrics['failed_delegations'];
                if ($totalDelegations > 0) {
                    $metrics['delegation_success_rate'] = $metrics['successful_delegations'] / $totalDelegations;
                }
            }

            // Estimate coordination overhead (simplified)
            $metrics['avg_coordination_overhead'] = $this->estimateCoordinationOverhead($communications);

            // Estimate parallelization efficiency
            $metrics['parallelization_efficiency'] = $this->estimateParallelizationEfficiency($communications);
        } catch (\Exception $e) {
            // If database queries fail, return minimal metrics
            $metrics['error'] = $e->getMessage();
        }

        return $metrics;
    }

    /**
     * Estimate coordination overhead as ratio of coordination messages to total
     */
    protected function estimateCoordinationOverhead($communications): float
    {
        if (empty($communications) || count($communications) === 0) {
            return 0.0;
        }

        $coordinationMessages = 0;
        foreach ($communications as $comm) {
            $payload = is_string($comm->payload) ? json_decode($comm->payload, true) : $comm->payload;

            if (isset($payload['type']) && in_array($payload['type'], ['delegation', 'status_update', 'coordination'])) {
                $coordinationMessages++;
            }
        }

        return $coordinationMessages / count($communications);
    }

    /**
     * Estimate parallelization efficiency
     */
    protected function estimateParallelizationEfficiency($communications): float
    {
        // Simplified: ratio of parallel tasks to sequential tasks
        // In a real implementation, would analyze actual execution patterns
        return 0.75; // Placeholder
    }

    /**
     * Lower latency and overhead is better, higher efficiency and success rate is better
     */
    public function isImprovement(string $metricKey, float $diff): bool
    {
        $lowerIsBetter = ['avg_message_latency_ms', 'failed_delegations', 'avg_coordination_overhead'];

        if (in_array($metricKey, $lowerIsBetter)) {
            return $diff < 0;
        }

        return $diff > 0;
    }
}
