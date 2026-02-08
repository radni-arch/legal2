<?php

namespace App\Services;

use App\Models\TextractDocument;
use App\Models\TextractJob;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Task 3.4: Health Dashboard Metrics
 *
 * Service for collecting Textract pipeline health metrics.
 */
class TextractHealthMetricsService
{
    public function getHealthMetrics(): array
    {
        return [
            'embedding_status_breakdown' => $this->getEmbeddingStatusBreakdown(),
            'graph_sync_status_breakdown' => $this->getGraphSyncStatusBreakdown(),
            'pipeline_durations' => $this->getPipelineDurations(),
            'failed_job_ages' => $this->getFailedJobAges(),
            'stale_pending_jobs' => $this->getStalePendingJobs(),
            'data_consistency' => $this->getDataConsistency(),
        ];
    }

    protected function getEmbeddingStatusBreakdown(): array
    {
        return TextractJob::query()
            ->select('embedding_status', DB::raw('count(*) as count'))
            ->groupBy('embedding_status')
            ->pluck('count', 'embedding_status')
            ->toArray();
    }

    protected function getGraphSyncStatusBreakdown(): array
    {
        return TextractJob::query()
            ->select('graph_sync_status', DB::raw('count(*) as count'))
            ->groupBy('graph_sync_status')
            ->pluck('count', 'graph_sync_status')
            ->toArray();
    }

    protected function getPipelineDurations(): array
    {
        $syncedJobs = TextractJob::where('embedding_status', 'synced')
            ->whereNotNull('created_at')
            ->whereNotNull('updated_at')
            ->get();

        if ($syncedJobs->isEmpty()) {
            return [
                'avg_ocr_to_embedding_minutes' => 0,
            ];
        }

        $totalMinutes = $syncedJobs->sum(function ($job) {
            return $job->created_at->diffInMinutes($job->updated_at);
        });

        return [
            'avg_ocr_to_embedding_minutes' => round($totalMinutes / $syncedJobs->count(), 2),
        ];
    }

    protected function getFailedJobAges(): array
    {
        $failedJobs = TextractJob::where(function ($query) {
            $query->where('status', 'failed')
                ->orWhere('embedding_status', 'failed')
                ->orWhere('graph_sync_status', 'failed');
        })
            ->orderBy('updated_at', 'asc')
            ->limit(10)
            ->get();

        if ($failedJobs->isEmpty()) {
            return [
                'oldest_failed_hours' => 0,
                'failed_jobs' => [],
            ];
        }

        $oldestJob = $failedJobs->first();
        $oldestHours = $oldestJob->updated_at->diffInMinutes(Carbon::now()) / 60;

        return [
            'oldest_failed_hours' => round($oldestHours, 2),
            'failed_jobs' => $failedJobs->map(function ($job) {
                return [
                    'id' => $job->id,
                    'status' => $job->status,
                    'embedding_status' => $job->embedding_status,
                    'graph_sync_status' => $job->graph_sync_status,
                    'updated_at' => $job->updated_at->toISOString(),
                ];
            })->toArray(),
        ];
    }

    protected function getStalePendingJobs(): array
    {
        $oneHourAgo = Carbon::now()->subHour();

        $staleEmbeddingPending = TextractJob::where('embedding_status', 'pending')
            ->where('updated_at', '<', $oneHourAgo)
            ->count();

        $staleGraphSyncPending = TextractJob::where('graph_sync_status', 'pending')
            ->where('updated_at', '<', $oneHourAgo)
            ->count();

        return [
            'stale_embedding_pending' => $staleEmbeddingPending,
            'stale_graph_sync_pending' => $staleGraphSyncPending,
        ];
    }

    protected function getDataConsistency(): array
    {
        // Jobs with synced embeddings but no TextractDocument children
        $missingDocuments = TextractJob::where('embedding_status', 'synced')
            ->whereDoesntHave('documents')
            ->count();

        return [
            'missing_textract_documents' => $missingDocuments,
        ];
    }
}
