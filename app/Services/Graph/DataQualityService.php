<?php

namespace App\Services\Graph;

use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Data Quality Service for Graph Database
 *
 * Sprint 8.4: Data Quality Checks & Graph Maintenance
 *
 * Provides automated data quality checks to detect:
 * - Duplicate nodes (same case_number, different IDs)
 * - Orphan nodes (nodes with no relationships)
 * - Inconsistent data (missing required properties)
 * - Quality metrics and reporting
 */
class DataQualityService
{
    protected GraphDatabaseService $graphDb;

    /**
     * Required properties for each node type
     */
    protected array $requiredProperties = [
        'Case' => ['case_number', 'title'],
        'CaseDocument' => ['case_number'],
        'Law' => ['law_code', 'title'],
        'LawDocument' => ['law_code'],
        'Keyword' => ['keyword'],
        'Topic' => ['topic'],
        'Court' => ['name'],
        'LegalConcept' => ['name'],
        'Tag' => ['name'],
        'Jurisdiction' => ['name'],
    ];

    public function __construct(GraphDatabaseService $graphDb)
    {
        $this->graphDb = $graphDb;
    }

    /**
     * Detect duplicate nodes based on key properties
     *
     * Sprint 8.4: detectDuplicateNodes()
     *
     * Finds nodes with the same identifying properties (e.g., case_number)
     * but different Neo4j IDs, indicating duplicates that should be merged.
     *
     * @param  string|null  $nodeType  Filter by specific node type
     * @return array Array of duplicate groups
     */
    public function detectDuplicateNodes(?string $nodeType = null): array
    {
        if (! $this->graphDb->isAvailable()) {
            Log::warning('Neo4j not available for duplicate detection');

            return [
                'duplicates' => [],
                'total_duplicate_groups' => 0,
                'total_duplicate_nodes' => 0,
            ];
        }

        $duplicateGroups = [];
        $totalDuplicateNodes = 0;

        // Define key properties for each node type
        $nodeTypeKeys = [
            'Case' => 'case_number',
            'CaseDocument' => 'case_number',
            'Law' => 'law_code',
            'LawDocument' => 'law_code',
            'Keyword' => 'keyword',
            'Topic' => 'topic',
            'Court' => 'name',
            'LegalConcept' => 'name',
            'Tag' => 'name',
            'Jurisdiction' => 'name',
        ];

        $nodeTypes = $nodeType ? [$nodeType] : array_keys($nodeTypeKeys);

        foreach ($nodeTypes as $type) {
            if (! isset($nodeTypeKeys[$type])) {
                continue;
            }

            $keyProperty = $nodeTypeKeys[$type];

            // Find duplicate nodes by grouping on key property
            $query = <<<CYPHER
MATCH (n:{$type})
WHERE n.{$keyProperty} IS NOT NULL
WITH n.{$keyProperty} as key, COLLECT(n) as nodes
WHERE SIZE(nodes) > 1
RETURN key,
       [node IN nodes | {id: id(node), properties: properties(node)}] as duplicates
LIMIT 100
CYPHER;

            try {
                $results = $this->graphDb->run($query);

                foreach ($results as $result) {
                    $key = $result->get('key');
                    $duplicates = $result->get('duplicates');

                    if (count($duplicates) > 1) {
                        $duplicateGroups[] = [
                            'node_type' => $type,
                            'key_property' => $keyProperty,
                            'key_value' => $key,
                            'duplicate_count' => count($duplicates),
                            'node_ids' => array_column($duplicates, 'id'),
                            'nodes' => $duplicates,
                        ];

                        $totalDuplicateNodes += count($duplicates);
                    }
                }
            } catch (\Exception $e) {
                Log::error("Failed to detect duplicates for {$type}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'duplicates' => $duplicateGroups,
            'total_duplicate_groups' => count($duplicateGroups),
            'total_duplicate_nodes' => $totalDuplicateNodes,
            'checked_types' => $nodeTypes,
        ];
    }

    /**
     * Detect orphan nodes (nodes with no relationships)
     *
     * Sprint 8.4: detectOrphanNodes()
     *
     * Finds nodes that have no incoming or outgoing relationships.
     * These may indicate data quality issues or incomplete sync.
     *
     * @param  string|null  $nodeType  Filter by specific node type
     * @return array Array of orphan nodes
     */
    public function detectOrphanNodes(?string $nodeType = null): array
    {
        if (! $this->graphDb->isAvailable()) {
            Log::warning('Neo4j not available for orphan detection');

            return [
                'orphans' => [],
                'total_orphans' => 0,
            ];
        }

        $orphans = [];

        $nodeTypes = $nodeType ? [$nodeType] : array_keys($this->requiredProperties);

        foreach ($nodeTypes as $type) {
            // Find nodes with no relationships
            $query = <<<CYPHER
MATCH (n:{$type})
WHERE NOT (n)--()
RETURN id(n) as node_id,
       properties(n) as properties,
       '{$type}' as node_type
LIMIT 1000
CYPHER;

            try {
                $results = $this->graphDb->run($query);

                foreach ($results as $result) {
                    $orphans[] = [
                        'node_id' => $result->get('node_id'),
                        'node_type' => $result->get('node_type'),
                        'properties' => $result->get('properties'),
                    ];
                }
            } catch (\Exception $e) {
                Log::error("Failed to detect orphans for {$type}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'orphans' => $orphans,
            'total_orphans' => count($orphans),
            'checked_types' => $nodeTypes,
        ];
    }

    /**
     * Detect inconsistent data (missing required properties)
     *
     * Sprint 8.4: detectInconsistentData()
     *
     * Finds nodes that are missing required properties defined in the schema.
     *
     * @param  string|null  $nodeType  Filter by specific node type
     * @return array Array of inconsistent nodes
     */
    public function detectInconsistentData(?string $nodeType = null): array
    {
        if (! $this->graphDb->isAvailable()) {
            Log::warning('Neo4j not available for inconsistency detection');

            return [
                'inconsistencies' => [],
                'total_inconsistent' => 0,
            ];
        }

        $inconsistencies = [];

        $nodeTypes = $nodeType ? [$nodeType => $this->requiredProperties[$nodeType] ?? []] : $this->requiredProperties;

        foreach ($nodeTypes as $type => $requiredProps) {
            foreach ($requiredProps as $prop) {
                // Find nodes missing this required property
                $query = <<<CYPHER
MATCH (n:{$type})
WHERE n.{$prop} IS NULL OR n.{$prop} = ''
RETURN id(n) as node_id,
       properties(n) as properties,
       '{$type}' as node_type,
       '{$prop}' as missing_property
LIMIT 500
CYPHER;

                try {
                    $results = $this->graphDb->run($query);

                    foreach ($results as $result) {
                        $inconsistencies[] = [
                            'node_id' => $result->get('node_id'),
                            'node_type' => $result->get('node_type'),
                            'missing_property' => $result->get('missing_property'),
                            'properties' => $result->get('properties'),
                        ];
                    }
                } catch (\Exception $e) {
                    Log::error("Failed to detect inconsistencies for {$type}.{$prop}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return [
            'inconsistencies' => $inconsistencies,
            'total_inconsistent' => count($inconsistencies),
            'checked_types' => array_keys($nodeTypes),
        ];
    }

    /**
     * Merge duplicate nodes
     *
     * Sprint 8.4: Auto-fix duplicates
     *
     * Merges duplicate nodes by keeping the oldest node and moving all
     * relationships to it, then deleting the duplicates.
     *
     * @param  array  $duplicateGroup  Duplicate group from detectDuplicateNodes()
     * @return array Merge result
     */
    public function mergeDuplicateNodes(array $duplicateGroup): array
    {
        if (! $this->graphDb->isAvailable()) {
            return [
                'success' => false,
                'error' => 'Neo4j not available',
            ];
        }

        $nodeIds = $duplicateGroup['node_ids'];

        if (count($nodeIds) < 2) {
            return [
                'success' => false,
                'error' => 'Need at least 2 nodes to merge',
            ];
        }

        // Keep the first node (oldest), merge others into it
        $keepNodeId = $nodeIds[0];
        $mergeNodeIds = array_slice($nodeIds, 1);

        try {
            // Merge nodes: move all relationships to the kept node and delete duplicates
            $mergeNodeIdsStr = implode(', ', $mergeNodeIds);

            $query = <<<'CYPHER'
MATCH (keep)
WHERE id(keep) = $keepId
WITH keep
MATCH (dup)
WHERE id(dup) IN $dupIds
WITH keep, dup
CALL {
    WITH keep, dup
    OPTIONAL MATCH (dup)-[r]->(other)
    WHERE NOT (keep)-[]-(other)
    WITH keep, dup, r, other
    FOREACH (ignoreMe IN CASE WHEN r IS NOT NULL THEN [1] ELSE [] END |
        CREATE (keep)-[newR:REL]->(other)
        SET newR = properties(r)
    )
    RETURN count(*) as outgoing
}
WITH keep, dup
CALL {
    WITH keep, dup
    OPTIONAL MATCH (other)-[r]->(dup)
    WHERE NOT (other)-[]-(keep)
    WITH keep, dup, r, other
    FOREACH (ignoreMe IN CASE WHEN r IS NOT NULL THEN [1] ELSE [] END |
        CREATE (other)-[newR:REL]->(keep)
        SET newR = properties(r)
    )
    RETURN count(*) as incoming
}
WITH keep, dup
DETACH DELETE dup
RETURN count(dup) as merged_count
CYPHER;

            $result = $this->graphDb->run($query, [
                'keepId' => $keepNodeId,
                'dupIds' => $mergeNodeIds,
            ]);

            $mergedCount = $result->first()->get('merged_count');

            Log::info('Merged duplicate nodes', [
                'kept_node_id' => $keepNodeId,
                'merged_count' => $mergedCount,
                'node_type' => $duplicateGroup['node_type'],
                'key_value' => $duplicateGroup['key_value'],
            ]);

            return [
                'success' => true,
                'kept_node_id' => $keepNodeId,
                'merged_count' => $mergedCount,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to merge duplicate nodes', [
                'error' => $e->getMessage(),
                'keep_id' => $keepNodeId,
                'merge_ids' => $mergeNodeIds,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Calculate data quality score (0-100)
     *
     * Sprint 8.4: Quality scoring
     *
     * Calculates an overall data quality score based on:
     * - Percentage of nodes without duplicates
     * - Percentage of nodes with relationships (non-orphans)
     * - Percentage of nodes with complete required properties
     *
     * @return array Quality score and metrics
     */
    public function calculateQualityScore(): array
    {
        if (! $this->graphDb->isAvailable()) {
            return [
                'score' => 0,
                'total_nodes' => 0,
                'metrics' => [],
            ];
        }

        // Get total node count
        $totalNodesQuery = 'MATCH (n) RETURN count(n) as total';
        $totalNodes = $this->graphDb->run($totalNodesQuery)->first()->get('total');

        if ($totalNodes === 0) {
            return [
                'score' => 100,
                'total_nodes' => 0,
                'metrics' => [],
            ];
        }

        // Detect issues
        $duplicates = $this->detectDuplicateNodes();
        $orphans = $this->detectOrphanNodes();
        $inconsistencies = $this->detectInconsistentData();

        // Calculate scores for each dimension
        $duplicateScore = 100 - (($duplicates['total_duplicate_nodes'] / $totalNodes) * 100);
        $orphanScore = 100 - (($orphans['total_orphans'] / $totalNodes) * 100);
        $consistencyScore = 100 - (($inconsistencies['total_inconsistent'] / $totalNodes) * 100);

        // Weight the scores (duplicates are most critical)
        $overallScore = (
            ($duplicateScore * 0.5) +  // 50% weight
            ($orphanScore * 0.3) +      // 30% weight
            ($consistencyScore * 0.2)   // 20% weight
        );

        return [
            'score' => round($overallScore, 2),
            'total_nodes' => $totalNodes,
            'metrics' => [
                'duplicate_score' => round($duplicateScore, 2),
                'orphan_score' => round($orphanScore, 2),
                'consistency_score' => round($consistencyScore, 2),
                'duplicate_nodes' => $duplicates['total_duplicate_nodes'],
                'duplicate_groups' => $duplicates['total_duplicate_groups'],
                'orphan_nodes' => $orphans['total_orphans'],
                'inconsistent_nodes' => $inconsistencies['total_inconsistent'],
            ],
        ];
    }

    /**
     * Generate comprehensive quality report
     *
     * Sprint 8.4: generateQualityReport()
     *
     * Generates a detailed quality report including:
     * - Overall quality score
     * - List of detected issues
     * - Recommendations for improvement
     *
     * @param  bool  $emailReport  Whether to email the report to admins
     * @return array Quality report data
     */
    public function generateQualityReport(bool $emailReport = false): array
    {
        $startTime = microtime(true);

        Log::info('Generating graph data quality report');

        // Run all quality checks
        $qualityScore = $this->calculateQualityScore();
        $duplicates = $this->detectDuplicateNodes();
        $orphans = $this->detectOrphanNodes();
        $inconsistencies = $this->detectInconsistentData();

        // Get node and relationship counts
        $nodeStats = $this->getNodeStatistics();
        $relationshipStats = $this->getRelationshipStatistics();

        $report = [
            'generated_at' => now()->toIso8601String(),
            'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            'overall_quality_score' => $qualityScore['score'],
            'total_nodes' => $qualityScore['total_nodes'],
            'metrics' => $qualityScore['metrics'],
            'duplicates' => [
                'total_groups' => $duplicates['total_duplicate_groups'],
                'total_nodes' => $duplicates['total_duplicate_nodes'],
                'groups' => array_slice($duplicates['duplicates'], 0, 10), // Top 10
            ],
            'orphans' => [
                'total' => $orphans['total_orphans'],
                'nodes' => array_slice($orphans['orphans'], 0, 10), // Top 10
            ],
            'inconsistencies' => [
                'total' => $inconsistencies['total_inconsistent'],
                'nodes' => array_slice($inconsistencies['inconsistencies'], 0, 10), // Top 10
            ],
            'statistics' => [
                'nodes' => $nodeStats,
                'relationships' => $relationshipStats,
            ],
            'recommendations' => $this->generateRecommendations($qualityScore, $duplicates, $orphans, $inconsistencies),
        ];

        // Cache the report
        Cache::put('graph:quality_report:latest', $report, 86400); // 24 hours

        // Email report to admins if requested
        if ($emailReport) {
            $this->emailQualityReport($report);
        }

        Log::info('Quality report generated', [
            'score' => $qualityScore['score'],
            'duplicates' => $duplicates['total_duplicate_nodes'],
            'orphans' => $orphans['total_orphans'],
            'inconsistencies' => $inconsistencies['total_inconsistent'],
        ]);

        return $report;
    }

    /**
     * Get node statistics by type
     */
    protected function getNodeStatistics(): array
    {
        if (! $this->graphDb->isAvailable()) {
            return [];
        }

        $query = <<<'CYPHER'
MATCH (n)
RETURN labels(n)[0] as node_type, count(n) as count
ORDER BY count DESC
CYPHER;

        try {
            $results = $this->graphDb->run($query);
            $stats = [];

            foreach ($results as $result) {
                $stats[$result->get('node_type')] = $result->get('count');
            }

            return $stats;
        } catch (\Exception $e) {
            Log::error('Failed to get node statistics', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Get relationship statistics by type
     */
    protected function getRelationshipStatistics(): array
    {
        if (! $this->graphDb->isAvailable()) {
            return [];
        }

        $query = <<<'CYPHER'
MATCH ()-[r]->()
RETURN type(r) as rel_type, count(r) as count
ORDER BY count DESC
CYPHER;

        try {
            $results = $this->graphDb->run($query);
            $stats = [];

            foreach ($results as $result) {
                $stats[$result->get('rel_type')] = $result->get('count');
            }

            return $stats;
        } catch (\Exception $e) {
            Log::error('Failed to get relationship statistics', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Generate recommendations based on quality metrics
     */
    protected function generateRecommendations(array $qualityScore, array $duplicates, array $orphans, array $inconsistencies): array
    {
        $recommendations = [];

        // Duplicate recommendations
        if ($duplicates['total_duplicate_nodes'] > 0) {
            $recommendations[] = [
                'priority' => 'high',
                'category' => 'duplicates',
                'issue' => "Found {$duplicates['total_duplicate_groups']} duplicate groups ({$duplicates['total_duplicate_nodes']} nodes)",
                'action' => 'Run php artisan graph:check-quality --fix-duplicates to automatically merge duplicates',
            ];
        }

        // Orphan recommendations
        if ($orphans['total_orphans'] > 10) {
            $recommendations[] = [
                'priority' => 'medium',
                'category' => 'orphans',
                'issue' => "Found {$orphans['total_orphans']} orphan nodes with no relationships",
                'action' => 'Review orphan nodes manually to determine if they should be deleted or need relationship sync',
            ];
        }

        // Inconsistency recommendations
        if ($inconsistencies['total_inconsistent'] > 0) {
            $recommendations[] = [
                'priority' => 'medium',
                'category' => 'inconsistencies',
                'issue' => "Found {$inconsistencies['total_inconsistent']} nodes with missing required properties",
                'action' => 'Re-sync affected nodes from source database to populate missing properties',
            ];
        }

        // Overall score recommendations
        if ($qualityScore['score'] < 95) {
            $recommendations[] = [
                'priority' => 'high',
                'category' => 'overall',
                'issue' => "Overall quality score ({$qualityScore['score']}%) is below target (95%)",
                'action' => 'Address duplicates and inconsistencies to improve quality score',
            ];
        }

        return $recommendations;
    }

    /**
     * Email quality report to administrators
     */
    protected function emailQualityReport(array $report): void
    {
        $recipients = config('monitoring.alert_email', []);

        if (empty($recipients)) {
            Log::debug('No email recipients configured for quality reports');

            return;
        }

        try {
            Mail::raw($this->formatReportEmail($report), function ($message) use ($report, $recipients) {
                $message->to($recipients)
                    ->subject("[Graph Quality Report] Score: {$report['overall_quality_score']}%");
            });

            Log::info('Quality report emailed', [
                'recipients' => count($recipients),
                'score' => $report['overall_quality_score'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to email quality report', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Format quality report for email
     */
    protected function formatReportEmail(array $report): string
    {
        $score = $report['overall_quality_score'];
        $status = $score >= 95 ? '✅ GOOD' : ($score >= 85 ? '⚠️ DEGRADED' : '❌ CRITICAL');

        $email = <<<EMAIL
Graph Data Quality Report
Generated: {$report['generated_at']}
Duration: {$report['duration_ms']}ms

═══════════════════════════════════════════════════════
OVERALL QUALITY SCORE: {$score}% {$status}
═══════════════════════════════════════════════════════

METRICS:
  Total Nodes: {$report['total_nodes']}

  Duplicate Score: {$report['metrics']['duplicate_score']}%
    - Duplicate Groups: {$report['metrics']['duplicate_groups']}
    - Duplicate Nodes: {$report['metrics']['duplicate_nodes']}

  Orphan Score: {$report['metrics']['orphan_score']}%
    - Orphan Nodes: {$report['metrics']['orphan_nodes']}

  Consistency Score: {$report['metrics']['consistency_score']}%
    - Inconsistent Nodes: {$report['metrics']['inconsistent_nodes']}

RECOMMENDATIONS:

EMAIL;

        foreach ($report['recommendations'] as $i => $rec) {
            $num = $i + 1;
            $priority = strtoupper($rec['priority']);
            $email .= <<<REC

{$num}. [{$priority}] {$rec['category']}
   Issue: {$rec['issue']}
   Action: {$rec['action']}

REC;
        }

        if (empty($report['recommendations'])) {
            $email .= "  No issues detected. Graph quality is excellent!\n\n";
        }

        $email .= <<<'FOOTER'

═══════════════════════════════════════════════════════
NODE STATISTICS:

FOOTER;

        foreach ($report['statistics']['nodes'] as $type => $count) {
            $email .= "  {$type}: {$count}\n";
        }

        $email .= <<<'FOOTER'

RELATIONSHIP STATISTICS:

FOOTER;

        foreach ($report['statistics']['relationships'] as $type => $count) {
            $email .= "  {$type}: {$count}\n";
        }

        $email .= <<<'FOOTER'

═══════════════════════════════════════════════════════

This is an automated quality report from the AI Legal War Machine.
For more details, run: php artisan graph:check-quality
FOOTER;

        return $email;
    }

    /**
     * Get latest cached quality report
     */
    public function getLatestReport(): ?array
    {
        return Cache::get('graph:quality_report:latest');
    }
}
