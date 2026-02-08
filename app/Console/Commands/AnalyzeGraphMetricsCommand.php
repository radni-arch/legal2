<?php

namespace App\Console\Commands;

use App\Models\GraphMetric;
use App\Services\GraphDatabaseService;
use App\Services\GraphQueryHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Analyze Neo4j graph metrics and store snapshots
 *
 * Runs PageRank, Louvain clustering, and network statistics
 * Stores results in graph_metrics table for UI/API consumption
 *
 * Scheduled to run weekly (Sunday at 04:00)
 */
class AnalyzeGraphMetricsCommand extends Command
{
    protected $signature = 'graph:analyze-metrics
                          {--type= : Specific metric type to analyze (pagerank, clusters, network_stats, citation_analysis)}
                          {--limit=50 : Number of top results to store}
                          {--force : Force analysis even if Neo4j sync is disabled}';

    protected $description = 'Analyze graph metrics (PageRank, clusters, network stats) and store snapshots';

    protected GraphQueryHelper $queryHelper;

    protected GraphDatabaseService $graphDb;

    public function handle(
        GraphQueryHelper $queryHelper,
        GraphDatabaseService $graphDb
    ): int {
        $this->queryHelper = $queryHelper;
        $this->graphDb = $graphDb;

        $this->info('=== Graph Metrics Analysis ===');
        $this->newLine();

        // Check if Neo4j is enabled
        if (! config('neo4j.sync.enabled') && ! $this->option('force')) {
            $this->warn('Neo4j sync is disabled. Use --force to run anyway.');

            return Command::FAILURE;
        }

        $type = $this->option('type');
        $limit = (int) $this->option('limit');

        try {
            if ($type) {
                // Analyze specific metric type
                $this->analyzeMetric($type, $limit);
            } else {
                // Analyze all metrics
                $this->analyzeAllMetrics($limit);
            }

            $this->newLine();
            $this->info('✓ Graph metrics analysis completed successfully');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->newLine();
            $this->error('Graph metrics analysis failed: '.$e->getMessage());

            Log::error('AnalyzeGraphMetricsCommand failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Analyze all metric types
     */
    protected function analyzeAllMetrics(int $limit): void
    {
        $this->info('Analyzing all metric types...');
        $this->newLine();

        $this->analyzePageRank($limit);
        $this->analyzeClusters();
        $this->analyzeNetworkStats();
        $this->analyzeCitationMetrics($limit);
    }

    /**
     * Analyze specific metric type
     */
    protected function analyzeMetric(string $type, int $limit): void
    {
        $this->info("Analyzing metric type: {$type}");
        $this->newLine();

        match ($type) {
            'pagerank' => $this->analyzePageRank($limit),
            'clusters' => $this->analyzeClusters(),
            'network_stats' => $this->analyzeNetworkStats(),
            'citation_analysis' => $this->analyzeCitationMetrics($limit),
            default => throw new \InvalidArgumentException("Unknown metric type: {$type}"),
        };
    }

    /**
     * Analyze PageRank (influential decisions)
     */
    protected function analyzePageRank(int $limit): void
    {
        $this->info('Running PageRank analysis...');

        $start = microtime(true);

        try {
            $influential = $this->queryHelper->findInfluentialDecisions($limit);
            $executionTime = microtime(true) - $start;

            if (empty($influential)) {
                $this->warn('  No influential decisions found');

                return;
            }

            // Store metric
            GraphMetric::create([
                'metric_type' => GraphMetric::TYPE_PAGERANK,
                'analyzed_at' => now(),
                'payload' => [
                    'top_decisions' => $influential,
                    'count' => count($influential),
                ],
                'node_count' => count($influential),
                'relationship_count' => 0, // PageRank doesn't return this directly
                'execution_time' => $executionTime,
                'notes' => "Top {$limit} influential decisions by PageRank",
            ]);

            $this->info(sprintf(
                '  ✓ PageRank: Found %d influential decisions (%.2fs)',
                count($influential),
                $executionTime
            ));

            if ($this->option('verbose')) {
                $this->displayInfluentialDecisions($influential);
            }

        } catch (\Exception $e) {
            $this->error('  PageRank analysis failed: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Analyze citation clusters (Louvain)
     */
    protected function analyzeClusters(): void
    {
        $this->info('Running Louvain clustering...');

        $start = microtime(true);

        try {
            $clusters = $this->queryHelper->detectCitationClusters();
            $executionTime = microtime(true) - $start;

            if (empty($clusters)) {
                $this->warn('  No clusters found');

                return;
            }

            // Calculate statistics
            $totalMembers = array_sum(array_map(fn ($c) => count($c['members']), $clusters));
            $avgClusterSize = $totalMembers / max(count($clusters), 1);

            // Store metric
            GraphMetric::create([
                'metric_type' => GraphMetric::TYPE_CLUSTERS,
                'analyzed_at' => now(),
                'payload' => [
                    'clusters' => $clusters,
                    'cluster_count' => count($clusters),
                    'avg_cluster_size' => round($avgClusterSize, 2),
                    'total_nodes' => $totalMembers,
                ],
                'node_count' => $totalMembers,
                'relationship_count' => 0, // Louvain doesn't return this directly
                'execution_time' => $executionTime,
                'notes' => sprintf('%d clusters detected via Louvain', count($clusters)),
            ]);

            $this->info(sprintf(
                '  ✓ Clusters: Found %d clusters with avg size %.1f (%.2fs)',
                count($clusters),
                $avgClusterSize,
                $executionTime
            ));

            if ($this->option('verbose')) {
                $this->displayClusters($clusters);
            }

        } catch (\Exception $e) {
            $this->error('  Clustering analysis failed: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Analyze network statistics
     */
    protected function analyzeNetworkStats(): void
    {
        $this->info('Computing network statistics...');

        $start = microtime(true);

        try {
            // Get total node and relationship counts
            $stats = $this->computeNetworkStats();
            $executionTime = microtime(true) - $start;

            // Store metric
            GraphMetric::create([
                'metric_type' => GraphMetric::TYPE_NETWORK_STATS,
                'analyzed_at' => now(),
                'payload' => $stats,
                'node_count' => $stats['total_nodes'] ?? 0,
                'relationship_count' => $stats['total_relationships'] ?? 0,
                'execution_time' => $executionTime,
                'notes' => 'Graph-wide network statistics',
            ]);

            $this->info(sprintf(
                '  ✓ Network: %d nodes, %d relationships (%.2fs)',
                $stats['total_nodes'] ?? 0,
                $stats['total_relationships'] ?? 0,
                $executionTime
            ));

            if ($this->option('verbose')) {
                $this->displayNetworkStats($stats);
            }

        } catch (\Exception $e) {
            $this->error('  Network stats failed: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Analyze citation-specific metrics
     */
    protected function analyzeCitationMetrics(int $limit): void
    {
        $this->info('Analyzing citation patterns...');

        $start = microtime(true);

        try {
            // Get top cited decisions
            $topCited = $this->getTopCitedDecisions($limit);
            $executionTime = microtime(true) - $start;

            if (empty($topCited)) {
                $this->warn('  No citation data found');

                return;
            }

            // Store metric
            GraphMetric::create([
                'metric_type' => GraphMetric::TYPE_CITATION_ANALYSIS,
                'analyzed_at' => now(),
                'payload' => [
                    'top_cited' => $topCited,
                    'count' => count($topCited),
                ],
                'node_count' => count($topCited),
                'relationship_count' => array_sum(array_column($topCited, 'citation_count')),
                'execution_time' => $executionTime,
                'notes' => "Top {$limit} most cited decisions",
            ]);

            $this->info(sprintf(
                '  ✓ Citations: Found %d highly cited decisions (%.2fs)',
                count($topCited),
                $executionTime
            ));

            if ($this->option('verbose')) {
                $this->displayTopCited($topCited);
            }

        } catch (\Exception $e) {
            $this->error('  Citation analysis failed: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Compute comprehensive network statistics
     */
    protected function computeNetworkStats(): array
    {
        // Total nodes and relationships
        $totalQuery = <<<'CYPHER'
MATCH (n)
WITH count(n) as nodeCount
MATCH ()-[r]->()
RETURN nodeCount, count(r) as relCount
CYPHER;

        $result = $this->graphDb->run($totalQuery);
        $totals = $result[0] ?? [];

        // Nodes by type
        $nodeTypeQuery = <<<'CYPHER'
MATCH (n)
RETURN labels(n)[0] as nodeType, count(n) as count
ORDER BY count DESC
CYPHER;

        $nodeTypes = $this->graphDb->run($nodeTypeQuery);

        // Relationships by type
        $relTypeQuery = <<<'CYPHER'
MATCH ()-[r]->()
RETURN type(r) as relType, count(r) as count
ORDER BY count DESC
CYPHER;

        $relTypes = $this->graphDb->run($relTypeQuery);

        return [
            'total_nodes' => $totals['nodeCount'] ?? 0,
            'total_relationships' => $totals['relCount'] ?? 0,
            'nodes_by_type' => $nodeTypes,
            'relationships_by_type' => $relTypes,
            'snapshot_date' => now()->toIso8601String(),
        ];
    }

    /**
     * Get top cited decisions
     */
    protected function getTopCitedDecisions(int $limit): array
    {
        $query = <<<'CYPHER'
MATCH (d:CourtDecisionDocument)<-[r:CITES]-()
WITH d, count(r) as citationCount
WHERE citationCount > 0
RETURN d.id as id, d.case_number as case_number,
       d.court as court, d.decision_date as date,
       citationCount as citation_count
ORDER BY citationCount DESC
LIMIT $limit
CYPHER;

        return $this->graphDb->run($query, ['limit' => $limit]);
    }

    /**
     * Display influential decisions table
     */
    protected function displayInfluentialDecisions(array $decisions): void
    {
        $this->newLine();
        $this->table(
            ['Rank', 'Case Number', 'Court', 'Date', 'PageRank Score'],
            array_map(fn ($d, $i) => [
                $i + 1,
                $d['case_number'] ?? 'N/A',
                $d['court'] ?? 'N/A',
                $d['date'] ?? 'N/A',
                sprintf('%.4f', $d['rank'] ?? 0),
            ], $decisions, array_keys($decisions))
        );
    }

    /**
     * Display clusters summary
     */
    protected function displayClusters(array $clusters): void
    {
        $this->newLine();
        $this->table(
            ['Cluster ID', 'Size', 'Sample Members'],
            array_slice(array_map(fn ($c) => [
                $c['community_id'] ?? 'N/A',
                count($c['members']),
                implode(', ', array_slice(array_column($c['members'], 'case_number'), 0, 3)),
            ], $clusters), 0, 10)
        );

        if (count($clusters) > 10) {
            $this->line('  ... and '.(count($clusters) - 10).' more clusters');
        }
    }

    /**
     * Display network statistics
     */
    protected function displayNetworkStats(array $stats): void
    {
        $this->newLine();
        $this->line('  Total Nodes: '.($stats['total_nodes'] ?? 0));
        $this->line('  Total Relationships: '.($stats['total_relationships'] ?? 0));

        $this->newLine();
        $this->line('  Nodes by Type:');
        foreach (array_slice($stats['nodes_by_type'] ?? [], 0, 5) as $type) {
            $this->line("    - {$type['nodeType']}: {$type['count']}");
        }

        $this->newLine();
        $this->line('  Relationships by Type:');
        foreach (array_slice($stats['relationships_by_type'] ?? [], 0, 5) as $type) {
            $this->line("    - {$type['relType']}: {$type['count']}");
        }
    }

    /**
     * Display top cited decisions
     */
    protected function displayTopCited(array $decisions): void
    {
        $this->newLine();
        $this->table(
            ['Rank', 'Case Number', 'Court', 'Citations'],
            array_map(fn ($d, $i) => [
                $i + 1,
                $d['case_number'] ?? 'N/A',
                $d['court'] ?? 'N/A',
                $d['citation_count'] ?? 0,
            ], $decisions, array_keys($decisions))
        );
    }
}
