<?php

namespace App\Http\Controllers;

use App\Http\Requests\Graph\SubgraphRequest;
use App\Services\GraphDatabaseService;
use App\Services\GraphQueryHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GraphVisualizationController extends Controller
{
    public function __construct(
        protected GraphDatabaseService $graph,
        protected GraphQueryHelper $queryHelper
    ) {}

    /**
     * Get node with its neighbors
     *
     * GET /api/graph/visualize/{nodeId}
     */
    public function visualize(string $nodeId, Request $request)
    {
        try {
            $depth = $request->integer('depth', 1);
            $limit = $request->integer('limit', 50);

            $cypher = '
                MATCH (center {id: $nodeId})
                OPTIONAL MATCH path = (center)-[r*1..$depth]-(neighbor)
                WITH center, collect(DISTINCT neighbor) AS neighbors, collect(DISTINCT r) AS relationships
                RETURN
                    {id: center.id, type: labels(center)[0], properties: properties(center)} AS center,
                    [n IN neighbors | {id: n.id, type: labels(n)[0], properties: properties(n)}] AS neighbors,
                    [rel IN relationships | {type: type(rel), properties: properties(rel)}] AS relationships
                LIMIT $limit
            ';

            $result = $this->graph->run($cypher, [
                'nodeId' => $nodeId,
                'depth' => $depth,
                'limit' => $limit,
            ])->first();

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('Graph visualize failed', [
                'operation' => 'visualize',
                'node_id' => $nodeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to visualize graph',
            ], 500);
        }
    }

    /**
     * Get subgraph by filters
     *
     * POST /api/graph/subgraph
     */
    public function subgraph(SubgraphRequest $request)
    {
        try {
            $validated = $request->validated();

            $nodeType = $validated['node_type'];
            $filters = $validated['filters'] ?? [];
            $maxNodes = $validated['max_nodes'] ?? 100;
            $includeRels = $validated['include_relationships'] ?? true;

            // Build WHERE clause from filters
            $whereClauses = [];
            $params = ['maxNodes' => $maxNodes];

            foreach ($filters as $key => $value) {
                $whereClauses[] = "n.{$key} = \${$key}";
                $params[$key] = $value;
            }

            $whereClause = ! empty($whereClauses) ? 'WHERE '.implode(' AND ', $whereClauses) : '';

            $cypher = "
                MATCH (n:{$nodeType})
                {$whereClause}
                ".($includeRels ? 'OPTIONAL MATCH (n)-[r]-(m)' : '').'
                WITH n'.($includeRels ? ', collect(DISTINCT r) AS rels, collect(DISTINCT m) AS related' : '').'
                LIMIT $maxNodes
                RETURN
                    {id: n.id, type: labels(n)[0], properties: properties(n)} AS node
                    '.($includeRels ? ', rels, related' : '').'
            ';

            $result = $this->graph->run($cypher, $params);

            return response()->json([
                'success' => true,
                'data' => $result->toArray(),
            ]);
        } catch (\Exception $e) {
            Log::error('Graph subgraph failed', [
                'operation' => 'subgraph',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch subgraph',
            ], 500);
        }
    }

    /**
     * Get graph statistics
     *
     * GET /api/graph/stats
     */
    public function stats()
    {
        try {
            $cypher = '
                MATCH (n)
                WITH labels(n)[0] AS type, count(*) AS count
                RETURN type, count
                ORDER BY count DESC
            ';

            $nodes = $this->graph->run($cypher)->toArray();

            $relsCypher = '
                MATCH ()-[r]->()
                WITH type(r) AS type, count(*) AS count
                RETURN type, count
                ORDER BY count DESC
            ';

            $relationships = $this->graph->run($relsCypher)->toArray();

            return response()->json([
                'success' => true,
                'data' => [
                    'nodes' => $nodes,
                    'relationships' => $relationships,
                    'total_nodes' => array_sum(array_column($nodes, 'count')),
                    'total_relationships' => array_sum(array_column($relationships, 'count')),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Graph stats failed', [
                'operation' => 'stats',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch graph statistics',
            ], 500);
        }
    }
}
