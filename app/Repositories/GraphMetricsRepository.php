<?php

namespace App\Repositories;

use App\Models\GraphMetric;
use Illuminate\Support\Collection;

/**
 * Repository for accessing graph metrics
 *
 * Provides clean API for UI/API consumption of graph analytics:
 * - PageRank (influential decisions)
 * - Louvain clusters (citation communities)
 * - Network statistics
 * - Citation analysis
 */
class GraphMetricsRepository
{
    /**
     * Get latest metrics dashboard data
     *
     * Returns all latest metrics organized by type
     */
    public function getDashboard(): array
    {
        $latest = GraphMetric::getLatestAll();

        return [
            'pagerank' => $this->formatPageRank($latest[GraphMetric::TYPE_PAGERANK] ?? null),
            'clusters' => $this->formatClusters($latest[GraphMetric::TYPE_CLUSTERS] ?? null),
            'network_stats' => $this->formatNetworkStats($latest[GraphMetric::TYPE_NETWORK_STATS] ?? null),
            'citation_analysis' => $this->formatCitationAnalysis($latest[GraphMetric::TYPE_CITATION_ANALYSIS] ?? null),
            'last_updated' => $this->getLastUpdated($latest),
        ];
    }

    /**
     * Get influential decisions (PageRank)
     */
    public function getInfluentialDecisions(int $limit = 20): array
    {
        $metric = GraphMetric::getLatest(GraphMetric::TYPE_PAGERANK);

        if (! $metric) {
            return [];
        }

        $decisions = $metric->payload['top_decisions'] ?? [];

        return array_slice($decisions, 0, $limit);
    }

    /**
     * Get citation clusters
     */
    public function getCitationClusters(int $limit = 10): array
    {
        $metric = GraphMetric::getLatest(GraphMetric::TYPE_CLUSTERS);

        if (! $metric) {
            return [];
        }

        $clusters = $metric->payload['clusters'] ?? [];

        return array_slice($clusters, 0, $limit);
    }

    /**
     * Get cluster by ID
     */
    public function getCluster(int $clusterId): ?array
    {
        $metric = GraphMetric::getLatest(GraphMetric::TYPE_CLUSTERS);

        if (! $metric) {
            return null;
        }

        $clusters = $metric->payload['clusters'] ?? [];

        foreach ($clusters as $cluster) {
            if (($cluster['community_id'] ?? null) == $clusterId) {
                return $cluster;
            }
        }

        return null;
    }

    /**
     * Get network statistics
     */
    public function getNetworkStats(): ?array
    {
        $metric = GraphMetric::getLatest(GraphMetric::TYPE_NETWORK_STATS);

        return $metric?->payload;
    }

    /**
     * Get most cited decisions
     */
    public function getMostCited(int $limit = 20): array
    {
        $metric = GraphMetric::getLatest(GraphMetric::TYPE_CITATION_ANALYSIS);

        if (! $metric) {
            return [];
        }

        $cited = $metric->payload['top_cited'] ?? [];

        return array_slice($cited, 0, $limit);
    }

    /**
     * Get metrics history for a specific type
     */
    public function getHistory(string $type, int $days = 30): Collection
    {
        return GraphMetric::query()
            ->where('metric_type', $type)
            ->where('analyzed_at', '>=', now()->subDays($days))
            ->orderBy('analyzed_at', 'desc')
            ->get();
    }

    /**
     * Get trending metrics (comparison over time)
     */
    public function getTrending(string $type, int $days = 30): array
    {
        $history = $this->getHistory($type, $days);

        if ($history->count() < 2) {
            return [
                'trend' => 'insufficient_data',
                'change' => 0,
                'current' => null,
                'previous' => null,
            ];
        }

        $current = $history->first();
        $previous = $history->skip(1)->first();

        return match ($type) {
            GraphMetric::TYPE_PAGERANK => $this->comparePageRank($current, $previous),
            GraphMetric::TYPE_CLUSTERS => $this->compareClusters($current, $previous),
            GraphMetric::TYPE_NETWORK_STATS => $this->compareNetworkStats($current, $previous),
            GraphMetric::TYPE_CITATION_ANALYSIS => $this->compareCitations($current, $previous),
            default => ['trend' => 'unknown', 'change' => 0],
        };
    }

    /**
     * Get summary of all metrics
     */
    public function getSummary(int $days = 30): array
    {
        return GraphMetric::getSummary($days);
    }

    /**
     * Search for specific decision in metrics
     */
    public function findDecision(string $caseNumber): ?array
    {
        $latest = GraphMetric::getLatestAll();

        // Search in PageRank
        if (isset($latest[GraphMetric::TYPE_PAGERANK])) {
            $decisions = $latest[GraphMetric::TYPE_PAGERANK]['payload']['top_decisions'] ?? [];
            foreach ($decisions as $decision) {
                if (($decision['case_number'] ?? '') === $caseNumber) {
                    return [
                        'type' => 'pagerank',
                        'decision' => $decision,
                        'analyzed_at' => $latest[GraphMetric::TYPE_PAGERANK]['analyzed_at'],
                    ];
                }
            }
        }

        // Search in citations
        if (isset($latest[GraphMetric::TYPE_CITATION_ANALYSIS])) {
            $cited = $latest[GraphMetric::TYPE_CITATION_ANALYSIS]['payload']['top_cited'] ?? [];
            foreach ($cited as $decision) {
                if (($decision['case_number'] ?? '') === $caseNumber) {
                    return [
                        'type' => 'citation_analysis',
                        'decision' => $decision,
                        'analyzed_at' => $latest[GraphMetric::TYPE_CITATION_ANALYSIS]['analyzed_at'],
                    ];
                }
            }
        }

        return null;
    }

    // Protected helper methods

    protected function formatPageRank(?GraphMetric $metric): ?array
    {
        if (! $metric) {
            return null;
        }

        return [
            'top_decisions' => array_slice($metric->payload['top_decisions'] ?? [], 0, 10),
            'total_count' => $metric->payload['count'] ?? 0,
            'analyzed_at' => $metric->analyzed_at->toIso8601String(),
            'execution_time' => $metric->execution_time,
        ];
    }

    protected function formatClusters(?GraphMetric $metric): ?array
    {
        if (! $metric) {
            return null;
        }

        return [
            'clusters' => array_slice($metric->payload['clusters'] ?? [], 0, 5),
            'cluster_count' => $metric->payload['cluster_count'] ?? 0,
            'avg_cluster_size' => $metric->payload['avg_cluster_size'] ?? 0,
            'analyzed_at' => $metric->analyzed_at->toIso8601String(),
            'execution_time' => $metric->execution_time,
        ];
    }

    protected function formatNetworkStats(?GraphMetric $metric): ?array
    {
        if (! $metric) {
            return null;
        }

        return [
            'total_nodes' => $metric->payload['total_nodes'] ?? 0,
            'total_relationships' => $metric->payload['total_relationships'] ?? 0,
            'nodes_by_type' => array_slice($metric->payload['nodes_by_type'] ?? [], 0, 5),
            'relationships_by_type' => array_slice($metric->payload['relationships_by_type'] ?? [], 0, 5),
            'analyzed_at' => $metric->analyzed_at->toIso8601String(),
        ];
    }

    protected function formatCitationAnalysis(?GraphMetric $metric): ?array
    {
        if (! $metric) {
            return null;
        }

        return [
            'top_cited' => array_slice($metric->payload['top_cited'] ?? [], 0, 10),
            'total_count' => $metric->payload['count'] ?? 0,
            'analyzed_at' => $metric->analyzed_at->toIso8601String(),
            'execution_time' => $metric->execution_time,
        ];
    }

    protected function getLastUpdated(array $metrics): ?string
    {
        $dates = array_filter(array_map(fn ($m) => $m['analyzed_at'] ?? null, $metrics));

        if (empty($dates)) {
            return null;
        }

        return max($dates)->toIso8601String();
    }

    protected function comparePageRank(GraphMetric $current, GraphMetric $previous): array
    {
        $currentCount = $current->payload['count'] ?? 0;
        $previousCount = $previous->payload['count'] ?? 0;

        return [
            'trend' => $currentCount > $previousCount ? 'up' : ($currentCount < $previousCount ? 'down' : 'stable'),
            'change' => $currentCount - $previousCount,
            'current' => $currentCount,
            'previous' => $previousCount,
        ];
    }

    protected function compareClusters(GraphMetric $current, GraphMetric $previous): array
    {
        $currentCount = $current->payload['cluster_count'] ?? 0;
        $previousCount = $previous->payload['cluster_count'] ?? 0;

        return [
            'trend' => $currentCount > $previousCount ? 'up' : ($currentCount < $previousCount ? 'down' : 'stable'),
            'change' => $currentCount - $previousCount,
            'current' => $currentCount,
            'previous' => $previousCount,
        ];
    }

    protected function compareNetworkStats(GraphMetric $current, GraphMetric $previous): array
    {
        $currentNodes = $current->payload['total_nodes'] ?? 0;
        $previousNodes = $previous->payload['total_nodes'] ?? 0;

        return [
            'trend' => $currentNodes > $previousNodes ? 'up' : ($currentNodes < $previousNodes ? 'down' : 'stable'),
            'change' => $currentNodes - $previousNodes,
            'current' => $currentNodes,
            'previous' => $previousNodes,
        ];
    }

    protected function compareCitations(GraphMetric $current, GraphMetric $previous): array
    {
        $currentCount = $current->payload['count'] ?? 0;
        $previousCount = $previous->payload['count'] ?? 0;

        return [
            'trend' => $currentCount > $previousCount ? 'up' : ($currentCount < $previousCount ? 'down' : 'stable'),
            'change' => $currentCount - $previousCount,
            'current' => $currentCount,
            'previous' => $previousCount,
        ];
    }
}
