<?php

namespace App\Services\Graph;

use App\Services\GraphDatabaseService;

class GraphExplorerService
{
    public function __construct(
        protected GraphDatabaseService $graph
    ) {}

    /**
     * Get node connections filtered by relationship types
     */
    public function getFilteredConnections(string $nodeId, array $relationshipTypes, int $limit = 50): array
    {
        if (empty($relationshipTypes)) {
            return [];
        }

        $cypher = '
            MATCH (n {id: $nodeId})-[r]-(connected)
            WHERE type(r) IN $relationshipTypes
            RETURN
                {id: connected.id, type: labels(connected)[0], properties: properties(connected)} AS node,
                {type: type(r), properties: properties(r)} AS rel
            LIMIT $limit
        ';

        $result = $this->graph->run($cypher, [
            'nodeId' => $nodeId,
            'relationshipTypes' => $relationshipTypes,
            'limit' => $limit,
        ]);

        return $result->toArray();
    }

    /**
     * Search nodes by name/title across the graph database
     */
    public function searchNodes(string $query, int $limit = 20): array
    {
        $query = trim($query);
        if (empty($query)) {
            return [];
        }

        $cypher = '
            MATCH (n)
            WHERE (n.name IS NOT NULL AND toLower(n.name) CONTAINS toLower($query))
               OR (n.title IS NOT NULL AND toLower(n.title) CONTAINS toLower($query))
               OR (n.case_number IS NOT NULL AND toLower(n.case_number) CONTAINS toLower($query))
               OR (n.keyword IS NOT NULL AND toLower(n.keyword) CONTAINS toLower($query))
               OR (n.value IS NOT NULL AND toLower(n.value) CONTAINS toLower($query))
            RETURN DISTINCT
                n.id as id,
                labels(n)[0] as type,
                coalesce(n.name, n.title, n.case_number, n.keyword, n.value, n.id) as label,
                size([(n)-[]-() | 1]) as connection_count
            ORDER BY connection_count DESC
            LIMIT $limit
        ';

        $result = $this->graph->run($cypher, [
            'query' => $query,
            'limit' => $limit,
        ]);

        return array_map(fn ($record) => [
            'id' => $record['id'],
            'type' => $record['type'],
            'label' => $record['label'],
            'connection_count' => $record['connection_count'] ?? 0,
        ], $result->toArray());
    }

    /**
     * Get an overview graph with the most connected nodes and their relationships
     * Used as entry point when no specific nodeId is provided
     */
    public function getOverviewGraph(int $nodeLimit = 30): array
    {
        // Get most connected nodes as starting points
        $cypher = '
            MATCH (n)
            WHERE labels(n)[0] IN ["CourtDecisionDocument", "LawDocument", "LegalTopic", "Judge"]
            WITH n, size([(n)-[]-() | 1]) as connections
            WHERE connections > 0
            ORDER BY connections DESC
            LIMIT $nodeLimit
        ';

        // First get the top nodes
        $topNodesCypher = $cypher . '
            RETURN DISTINCT
                n.id as node_id,
                labels(n)[0] as node_type,
                coalesce(n.name, n.title, n.case_number, n.id) as node_name,
                properties(n) as node_properties,
                connections
        ';

        $nodeResult = $this->graph->run($topNodesCypher, [
            'nodeLimit' => $nodeLimit,
        ]);

        $nodes = [];
        $nodeIds = [];

        foreach ($nodeResult->toArray() as $record) {
            $id = $record['node_id'];
            if (! in_array($id, $nodeIds)) {
                $nodes[] = [
                    'id' => $id,
                    'type' => $record['node_type'],
                    'label' => $record['node_name'],
                    'properties' => $record['node_properties'] ?? [],
                ];
                $nodeIds[] = $id;
            }
        }

        if (empty($nodeIds)) {
            return ['nodes' => [], 'edges' => []];
        }

        // Now get edges between these top nodes
        $edgeCypher = '
            MATCH (a)-[r]-(b)
            WHERE a.id IN $nodeIds AND b.id IN $nodeIds AND a.id < b.id
            RETURN DISTINCT
                startNode(r).id as source_id,
                endNode(r).id as target_id,
                type(r) as edge_type
        ';

        $edgeResult = $this->graph->run($edgeCypher, [
            'nodeIds' => $nodeIds,
        ]);

        $edges = [];
        foreach ($edgeResult->toArray() as $record) {
            if (isset($record['source_id'], $record['target_id'], $record['edge_type'])) {
                $edges[] = [
                    'source' => $record['source_id'],
                    'target' => $record['target_id'],
                    'type' => $record['edge_type'],
                ];
            }
        }

        return [
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }
}
