<?php

namespace App\Services;

use App\Models\Neo4jRetryQueueItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Neo4jRetryMetricsService
{
    /**
     * Cache TTL for metrics (in seconds).
     */
    protected int $cacheTTL = 300; // 5 minutes

    /**
     * Get retry attempts per operation type.
     *
     * @return array Array of operation types with their retry counts
     */
    public function getRetryAttemptsPerOperationType(): array
    {
        return Cache::remember('neo4j_metrics:retry_attempts_per_type', $this->cacheTTL, function () {
            return Neo4jRetryQueueItem::select('operation_type', DB::raw('COUNT(*) as count'), DB::raw('SUM(attempts) as total_attempts'))
                ->groupBy('operation_type')
                ->orderBy('count', 'desc')
                ->get()
                ->map(fn ($item) => [
                    'operation_type' => $item->operation_type,
                    'count' => $item->count,
                    'total_attempts' => $item->total_attempts,
                    'avg_attempts' => $item->count > 0 ? round($item->total_attempts / $item->count, 2) : 0,
                ])
                ->toArray();
        });
    }

    /**
     * Get success rate after retry.
     *
     * @return array Success rate statistics
     */
    public function getSuccessRateAfterRetry(): array
    {
        return Cache::remember('neo4j_metrics:success_rate', $this->cacheTTL, function () {
            $total = Neo4jRetryQueueItem::count();
            $completed = Neo4jRetryQueueItem::completed()->count();
            $failed = Neo4jRetryQueueItem::failed()->count();
            $deadLetter = Neo4jRetryQueueItem::where('status', 'dead_letter')->count();
            $retrying = Neo4jRetryQueueItem::retrying()->count();
            $pending = Neo4jRetryQueueItem::pending()->count();

            return [
                'total' => $total,
                'completed' => $completed,
                'failed' => $failed,
                'dead_letter' => $deadLetter,
                'retrying' => $retrying,
                'pending' => $pending,
                'success_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
                'failure_rate' => $total > 0 ? round((($failed + $deadLetter) / $total) * 100, 2) : 0,
            ];
        });
    }

    /**
     * Get dead letter queue size.
     *
     * @return int Number of items in dead letter queue
     */
    public function getDeadLetterQueueSize(): int
    {
        return Cache::remember('neo4j_metrics:dead_letter_size', $this->cacheTTL, function () {
            return Neo4jRetryQueueItem::whereIn('status', ['failed', 'dead_letter'])->count();
        });
    }

    /**
     * Get average time to success.
     *
     * @return array Average time statistics
     */
    public function getAverageTimeToSuccess(): array
    {
        return Cache::remember('neo4j_metrics:avg_time_to_success', $this->cacheTTL, function () {
            $completedItems = Neo4jRetryQueueItem::completed()
                ->select(
                    DB::raw('AVG(TIMESTAMPDIFF(SECOND, created_at, updated_at)) as avg_seconds'),
                    DB::raw('MIN(TIMESTAMPDIFF(SECOND, created_at, updated_at)) as min_seconds'),
                    DB::raw('MAX(TIMESTAMPDIFF(SECOND, created_at, updated_at)) as max_seconds')
                )
                ->first();

            return [
                'avg_seconds' => $completedItems->avg_seconds ? round($completedItems->avg_seconds, 2) : 0,
                'min_seconds' => $completedItems->min_seconds ?? 0,
                'max_seconds' => $completedItems->max_seconds ?? 0,
                'avg_minutes' => $completedItems->avg_seconds ? round($completedItems->avg_seconds / 60, 2) : 0,
            ];
        });
    }

    /**
     * Get retry queue health status.
     *
     * @return array Health status with warnings
     */
    public function getHealthStatus(): array
    {
        $deadLetterSize = $this->getDeadLetterQueueSize();
        $successRate = $this->getSuccessRateAfterRetry();
        $retryingCount = $successRate['retrying'] ?? 0;

        $warnings = [];
        $status = 'healthy';

        // Check dead letter queue size
        if ($deadLetterSize > 100) {
            $warnings[] = "Dead letter queue has {$deadLetterSize} items (threshold: 100)";
            $status = 'critical';
        } elseif ($deadLetterSize > 50) {
            $warnings[] = "Dead letter queue has {$deadLetterSize} items (warning threshold: 50)";
            $status = $status === 'healthy' ? 'warning' : $status;
        }

        // Check success rate
        if ($successRate['success_rate'] < 50 && $successRate['total'] > 10) {
            $warnings[] = "Low success rate: {$successRate['success_rate']}% (threshold: 50%)";
            $status = 'critical';
        } elseif ($successRate['success_rate'] < 80 && $successRate['total'] > 10) {
            $warnings[] = "Moderate success rate: {$successRate['success_rate']}% (warning threshold: 80%)";
            $status = $status === 'healthy' ? 'warning' : $status;
        }

        // Check retrying queue size
        if ($retryingCount > 50) {
            $warnings[] = "High number of retrying items: {$retryingCount} (threshold: 50)";
            $status = $status === 'healthy' ? 'warning' : $status;
        }

        return [
            'status' => $status,
            'warnings' => $warnings,
            'metrics' => [
                'dead_letter_size' => $deadLetterSize,
                'success_rate' => $successRate['success_rate'],
                'retrying_count' => $retryingCount,
            ],
        ];
    }

    /**
     * Get recent failures (last 24 hours).
     *
     * @return array Recent failure statistics
     */
    public function getRecentFailures(): array
    {
        return Cache::remember('neo4j_metrics:recent_failures', $this->cacheTTL, function () {
            $recentFailures = Neo4jRetryQueueItem::where('failed_at', '>=', now()->subDay())
                ->select('operation_type', DB::raw('COUNT(*) as count'))
                ->groupBy('operation_type')
                ->orderBy('count', 'desc')
                ->get()
                ->toArray();

            $totalRecentFailures = Neo4jRetryQueueItem::where('failed_at', '>=', now()->subDay())->count();

            return [
                'total' => $totalRecentFailures,
                'by_type' => $recentFailures,
            ];
        });
    }

    /**
     * Get all metrics summary.
     *
     * @return array Complete metrics summary
     */
    public function getAllMetrics(): array
    {
        return [
            'retry_attempts_per_type' => $this->getRetryAttemptsPerOperationType(),
            'success_rate' => $this->getSuccessRateAfterRetry(),
            'dead_letter_queue_size' => $this->getDeadLetterQueueSize(),
            'average_time_to_success' => $this->getAverageTimeToSuccess(),
            'health_status' => $this->getHealthStatus(),
            'recent_failures' => $this->getRecentFailures(),
        ];
    }

    /**
     * Clear metrics cache.
     */
    public function clearCache(): void
    {
        Cache::forget('neo4j_metrics:retry_attempts_per_type');
        Cache::forget('neo4j_metrics:success_rate');
        Cache::forget('neo4j_metrics:dead_letter_size');
        Cache::forget('neo4j_metrics:avg_time_to_success');
        Cache::forget('neo4j_metrics:recent_failures');
    }
}
