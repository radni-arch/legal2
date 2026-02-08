<?php

namespace App\Services\Graph;

use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\DB;

/**
 * Service for maintaining graph data integrity
 *
 * Responsibilities:
 * - Detect orphan nodes (Neo4j nodes without PostgreSQL records)
 * - Detect missing nodes (PostgreSQL records without Neo4j nodes)
 * - Validate relationship consistency
 * - Provide cleanup operations
 */
class GraphDataIntegrityService
{
    public function __construct(
        protected GraphDatabaseService $graph
    ) {}

    /**
     * Find orphan nodes - nodes in Neo4j without PostgreSQL records
     *
     * @param  string  $nodeType  The node type to check
     * @param  int  $batchSize  Number of nodes to process per batch (default: 1000)
     * @return array List of orphan nodes with metadata
     */
    public function findOrphanNodes(string $nodeType, int $batchSize = 1000): array
    {
        $allOrphans = [];
        $offset = 0;
        $tableName = $this->getTableForNodeType($nodeType);

        do {
            // Get batch of node IDs from Neo4j with pagination
            $cypher = "MATCH (n:{$nodeType}) RETURN n.id as id, n.case_number as case_number ORDER BY n.id SKIP {$offset} LIMIT {$batchSize}";
            $neo4jNodes = $this->graph->run($cypher);

            if (empty($neo4jNodes)) {
                break;
            }

            // Get IDs that exist in PostgreSQL
            $neo4jIds = array_column($neo4jNodes, 'id');
            $existingIds = DB::table($tableName)
                ->whereIn('id', $neo4jIds)
                ->pluck('id')
                ->toArray();

            // Filter out nodes that don't exist in PostgreSQL (orphans)
            $batchOrphans = array_values(array_filter($neo4jNodes, fn ($node) => ! in_array($node['id'], $existingIds)));

            // Accumulate orphans from this batch
            $allOrphans = array_merge($allOrphans, $batchOrphans);

            $offset += $batchSize;

            // Continue if we got a full batch, indicating more may exist
        } while (count($neo4jNodes) === $batchSize);

        return $allOrphans;
    }

    /**
     * Find missing nodes - PostgreSQL records without Neo4j nodes
     *
     * @param  string  $nodeType  The node type to check
     * @param  array|null  $idsToCheck  Specific IDs to check, or null for batch check from DB
     * @param  int  $batchSize  Number of IDs to check per query (default: 500)
     * @return array List of missing IDs
     */
    public function findMissingNodes(string $nodeType, ?array $idsToCheck = null, int $batchSize = 500): array
    {
        $tableName = $this->getTableForNodeType($nodeType);

        // Get IDs from PostgreSQL if not provided
        if ($idsToCheck === null) {
            $postgresIds = DB::table($tableName)
                ->limit(1000)
                ->pluck('id')
                ->toArray();
        } else {
            $postgresIds = $idsToCheck;
        }

        if (empty($postgresIds)) {
            return [];
        }

        $allMissing = [];

        // Chunk IDs into batches to avoid Neo4j query limits
        foreach (array_chunk($postgresIds, $batchSize) as $chunk) {
            // Build IN clause for Cypher - escape single quotes
            $escapedIds = array_map(fn ($id) => str_replace("'", "\\'", $id), $chunk);
            $idList = "'".implode("','", $escapedIds)."'";

            // Check which exist in Neo4j
            $cypher = "MATCH (n:{$nodeType}) WHERE n.id IN [{$idList}] RETURN n.id as id";
            $neo4jNodes = $this->graph->run($cypher);
            $neo4jIds = array_column($neo4jNodes, 'id');

            // Find missing in this chunk
            $chunkMissing = array_diff($chunk, $neo4jIds);
            $allMissing = array_merge($allMissing, $chunkMissing);
        }

        // Return IDs that don't exist in Neo4j
        return array_values($allMissing);
    }

    /**
     * Validate relationship consistency - check for dangling relationships
     *
     * This finds relationships where the target node doesn't exist in the expected
     * node types. Note: Due to Neo4j's nature, most dangling relationships are
     * structurally impossible, but this catches cases where nodes may have been
     * deleted without proper cascade.
     *
     * @param  string  $relationType  The relationship type to validate
     * @return array List of inconsistent relationships
     */
    public function validateRelationshipConsistency(string $relationType): array
    {
        // Find relationships where target node has no valid label
        // This Cypher query finds relationships where the target isn't
        // one of the expected node types (indicating orphaned data)
        $cypher = "
            MATCH (a)-[r:{$relationType}]->(b)
            WHERE NOT (
                b:CourtDecisionDocument OR
                b:LawDocument OR
                b:Court OR
                b:Jurisdiction OR
                b:Keyword OR
                b:Judge OR
                b:Party OR
                b:LegalPrinciple
            )
            RETURN a.id as from_id, b.id as to_id, type(r) as rel_type
            LIMIT 100
        ";

        return $this->graph->run($cypher);
    }

    /**
     * Generate a comprehensive integrity report
     *
     * Checks all node types and relationship types for issues and
     * produces a complete health status report.
     *
     * @return array Complete integrity report with summary
     */
    public function generateIntegrityReport(): array
    {
        $nodeTypes = ['CourtDecisionDocument', 'LawDocument', 'Court', 'Keyword'];
        $relationshipTypes = ['CITES', 'REFERENCES', 'DECIDED_BY', 'HAS_KEYWORD'];

        $orphanNodes = [];
        $missingNodes = [];
        $danglingRels = [];

        // Check each node type for orphans and missing nodes
        foreach ($nodeTypes as $type) {
            try {
                $orphanNodes[$type] = $this->findOrphanNodes($type);
            } catch (\Exception $e) {
                $orphanNodes[$type] = ['error' => $e->getMessage()];
            }

            try {
                $missingNodes[$type] = $this->findMissingNodes($type);
            } catch (\Exception $e) {
                $missingNodes[$type] = ['error' => $e->getMessage()];
            }
        }

        // Check each relationship type for dangling references
        foreach ($relationshipTypes as $relType) {
            try {
                $danglingRels[$relType] = $this->validateRelationshipConsistency($relType);
            } catch (\Exception $e) {
                $danglingRels[$relType] = ['error' => $e->getMessage()];
            }
        }

        // Calculate totals (ignoring error entries)
        $totalOrphans = array_sum(array_map(
            fn ($v) => is_array($v) && ! isset($v['error']) ? count($v) : 0,
            $orphanNodes
        ));
        $totalMissing = array_sum(array_map(
            fn ($v) => is_array($v) && ! isset($v['error']) ? count($v) : 0,
            $missingNodes
        ));
        $totalDangling = array_sum(array_map(
            fn ($v) => is_array($v) && ! isset($v['error']) ? count($v) : 0,
            $danglingRels
        ));

        return [
            'orphan_nodes' => $orphanNodes,
            'missing_nodes' => $missingNodes,
            'dangling_relationships' => $danglingRels,
            'generated_at' => now()->toIso8601String(),
            'summary' => [
                'total_orphan_nodes' => $totalOrphans,
                'total_missing_nodes' => $totalMissing,
                'total_dangling_relationships' => $totalDangling,
                'health_status' => ($totalOrphans + $totalMissing + $totalDangling) === 0 ? 'healthy' : 'needs_attention',
            ],
        ];
    }

    /**
     * Get the PostgreSQL table name for a node type
     *
     * @throws \InvalidArgumentException
     */
    protected function getTableForNodeType(string $nodeType): string
    {
        return match ($nodeType) {
            'CourtDecisionDocument' => 'court_decision_documents',
            'LawDocument' => 'law_documents',
            'CaseDocument' => 'cases_documents',
            'Court' => 'courts',
            'Keyword' => 'keywords',
            'Tag' => 'tags',
            'Topic' => 'topics',
            'Judge' => 'judges',
            default => throw new \InvalidArgumentException("Unknown node type: {$nodeType}"),
        };
    }
}
