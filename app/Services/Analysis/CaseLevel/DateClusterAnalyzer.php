<?php

namespace App\Services\Analysis\CaseLevel;

use App\Models\DocumentAnalysis;
use Carbon\Carbon;

class DateClusterAnalyzer
{
    private int $clusterGapDays = 3;

    public function analyze(string $caseId): array
    {
        $startTime = microtime(true);

        // Gather all dates from all documents in this case
        $dateAnalyses = DocumentAnalysis::whereHas('caseDocument', function ($q) use ($caseId) {
            $q->where('case_id', $caseId);
        })
            ->where('analysis_type', DocumentAnalysis::TYPE_DATES)
            ->where('status', DocumentAnalysis::STATUS_COMPLETED)
            ->get();

        $allDates = [];
        foreach ($dateAnalyses as $analysis) {
            $docId = $analysis->case_document_id;
            foreach ($analysis->results['dates'] ?? [] as $dateEntry) {
                $allDates[] = array_merge($dateEntry, ['document_id' => $docId]);
            }
        }

        // Sort by date
        usort($allDates, fn($a, $b) => $a['date'] <=> $b['date']);

        // Cluster dates within N days of each other
        $clusters = [];
        $currentCluster = [];

        foreach ($allDates as $entry) {
            if (empty($currentCluster)) {
                $currentCluster[] = $entry;
                continue;
            }

            $lastDate = Carbon::parse(end($currentCluster)['date']);
            $thisDate = Carbon::parse($entry['date']);

            if ($lastDate->diffInDays($thisDate) <= $this->clusterGapDays) {
                $currentCluster[] = $entry;
            } else {
                $clusters[] = $this->summarizeCluster($currentCluster);
                $currentCluster = [$entry];
            }
        }

        if (!empty($currentCluster)) {
            $clusters[] = $this->summarizeCluster($currentCluster);
        }

        // Rank clusters by document cross-reference count
        usort($clusters, fn($a, $b) => $b['document_count'] <=> $a['document_count']);

        return [
            'results' => [
                'clusters' => $clusters,
                'total_dates' => count($allDates),
                'total_clusters' => count($clusters),
                'multi_document_clusters' => count(array_filter($clusters, fn($c) => $c['document_count'] > 1)),
            ],
            'metadata' => [
                'processing_time_seconds' => round(microtime(true) - $startTime, 4),
                'documents_analyzed' => $dateAnalyses->count(),
            ],
        ];
    }

    private function summarizeCluster(array $entries): array
    {
        $dates = array_column($entries, 'date');
        $docIds = array_unique(array_column($entries, 'document_id'));
        $contexts = array_column($entries, 'context');

        return [
            'date_start' => min($dates),
            'date_end' => max($dates),
            'date_count' => count($entries),
            'document_count' => count($docIds),
            'document_ids' => array_values($docIds),
            'contexts' => array_slice($contexts, 0, 5), // Top 5 context snippets
        ];
    }
}
