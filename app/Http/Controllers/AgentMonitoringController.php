<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\AgentRun;
use App\Models\DecisionDiscoveryRun;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class AgentMonitoringController extends Controller
{
    /**
     * Get overall agent system health.
     */
    public function health(Request $request)
    {
        $days = $request->integer('days', 7);

        // Cache health metrics for 60 seconds
        $health = \Illuminate\Support\Facades\Cache::remember("agent_health_{$days}d", 60, function () use ($days) {
            return [
                'status' => 'healthy',
                'timestamp' => now()->toIso8601String(),
                'period_days' => $days,
                'agents' => [
                    'research' => $this->getResearchAgentHealth($days),
                    'decision_discovery' => $this->getDecisionDiscoveryHealth($days),
                ],
                'queue' => $this->getQueueHealth(),
            ];
        });

        // Determine overall status
        $failureRates = [
            $health['agents']['research']['failure_rate'],
            $health['agents']['decision_discovery']['failure_rate'],
        ];

        if (max($failureRates) > 50) {
            $health['status'] = 'critical';
        } elseif (max($failureRates) > 25) {
            $health['status'] = 'degraded';
        }

        $health['timestamp'] = now()->toIso8601String();

        return ApiResponse::success($health);
    }

    /**
     * Get statistics for all agents.
     */
    public function statistics(Request $request)
    {
        $days = $request->integer('days', 30);

        // Cache statistics for 2 minutes
        $stats = \Illuminate\Support\Facades\Cache::remember("agent_statistics_{$days}d", 120, function () use ($days) {
            // Optimize: Use single query with aggregates for research agent
            $researchAggregates = AgentRun::recent($days)
                ->selectRaw('
                    COUNT(*) as total_runs,
                    SUM(CASE WHEN status = \'completed\' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = \'failed\' THEN 1 ELSE 0 END) as failed
                ')->first();

            // Optimize: Use single query with aggregates for decision discovery
            $discoveryAggregates = DecisionDiscoveryRun::recent($days)
                ->selectRaw('
                    COUNT(*) as total_runs,
                    SUM(CASE WHEN status = \'completed\' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = \'failed\' THEN 1 ELSE 0 END) as failed,
                    SUM(decisions_evaluated) as total_decisions_found,
                    SUM(decisions_ingested) as total_decisions_ingested
                ')->first();

            return [
                'period_days' => $days,
                'research_agent' => [
                    'total_runs' => $researchAggregates->total_runs ?? 0,
                    'completed' => $researchAggregates->completed ?? 0,
                    'failed' => $researchAggregates->failed ?? 0,
                    'avg_duration_seconds' => $this->getResearchAgentAvgDuration($days),
                ],
                'decision_discovery' => [
                    'total_runs' => $discoveryAggregates->total_runs ?? 0,
                    'completed' => $discoveryAggregates->completed ?? 0,
                    'failed' => $discoveryAggregates->failed ?? 0,
                    'avg_duration_seconds' => DecisionDiscoveryRun::getAverageDuration($days),
                    'total_decisions_found' => $discoveryAggregates->total_decisions_found ?? 0,
                    'total_decisions_ingested' => $discoveryAggregates->total_decisions_ingested ?? 0,
                ],
            ];
        });

        return response()->json($stats);
    }

    /**
     * Get recent agent runs with status.
     */
    public function recentRuns(Request $request)
    {
        $limit = $request->integer('limit', 20);

        return response()->json([
            'research_agent' => AgentRun::orderBy('created_at', 'desc')
                ->limit($limit)
                ->get(['id', 'status', 'objective', 'created_at', 'completed_at']),
            'decision_discovery' => DecisionDiscoveryRun::orderBy('created_at', 'desc')
                ->limit($limit)
                ->get(['id', 'status', 'decisions_evaluated', 'decisions_ingested', 'created_at', 'completed_at']),
        ]);
    }

    /**
     * Get failed jobs for debugging.
     */
    public function failedJobs(Request $request)
    {
        $limit = $request->integer('limit', 50);

        $failed = DB::table('failed_jobs')
            ->where('queue', 'default')
            ->orderBy('failed_at', 'desc')
            ->limit($limit)
            ->get(['id', 'connection', 'queue', 'payload', 'exception', 'failed_at']);

        return response()->json([
            'total' => DB::table('failed_jobs')->count(),
            'recent' => $failed,
        ]);
    }

    /**
     * Get research agent health metrics.
     */
    protected function getResearchAgentHealth(int $days): array
    {
        $total = AgentRun::recent($days)->count();
        $failed = AgentRun::recent($days)->where('status', 'failed')->count();

        return [
            'total_runs' => $total,
            'failed_runs' => $failed,
            'failure_rate' => $total > 0 ? round(($failed / $total) * 100, 2) : 0,
            'status' => $total > 0 && ($failed / $total) < 0.1 ? 'healthy' : 'degraded',
        ];
    }

    /**
     * Get decision discovery agent health metrics.
     */
    protected function getDecisionDiscoveryHealth(int $days): array
    {
        $total = DecisionDiscoveryRun::recent($days)->count();
        $failed = DecisionDiscoveryRun::recent($days)->failed()->count();

        return [
            'total_runs' => $total,
            'failed_runs' => $failed,
            'failure_rate' => $total > 0 ? round(($failed / $total) * 100, 2) : 0,
            'avg_decisions_per_run' => $total > 0
                ? round(DecisionDiscoveryRun::getTotalIngested($days) / $total, 2)
                : 0,
            'status' => $total > 0 && ($failed / $total) < 0.1 ? 'healthy' : 'degraded',
        ];
    }

    /**
     * Get queue health metrics.
     */
    protected function getQueueHealth(): array
    {
        $size = Queue::size('default');
        $failedCount = DB::table('failed_jobs')->count();

        return [
            'queue_size' => $size,
            'failed_jobs_total' => $failedCount,
            'status' => $size < 100 && $failedCount < 10 ? 'healthy' : 'degraded',
        ];
    }

    /**
     * Get average duration for research agent runs.
     */
    protected function getResearchAgentAvgDuration(int $days): float
    {
        $runs = AgentRun::recent($days)
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->get();

        if ($runs->isEmpty()) {
            return 0;
        }

        $totalSeconds = $runs->sum(function ($run) {
            if ($run->started_at && $run->completed_at) {
                return $run->started_at->diffInSeconds($run->completed_at);
            }

            return $run->elapsed_seconds ?? 0;
        });

        return round($totalSeconds / $runs->count(), 2);
    }
}
