<?php

namespace App\Services\Graph;

use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for linking similar documents in the graph
 *
 * Uses vector embeddings to find and create SIMILAR_TO relationships
 * between documents based on cosine similarity.
 */
class GraphSimilarityLinker
{
    public function __construct(
        protected GraphDatabaseService $graph
    ) {}

    /**
     * Find similar documents and create similarity links
     *
     * @param  string  $nodeLabel  The label of the node (e.g., 'LawDocument')
     * @param  string  $nodeId  The ID of the node
     * @param  string|array|null  $content  The embedding vector (as JSON string, array, or null)
     */
    public function link(string $nodeLabel, string $nodeId, $content): void
    {
        if (! $content || ! config('neo4j.sync.enabled')) {
            return;
        }

        // Handle array input (direct vector array)
        if (is_array($content)) {
            $vector = $content;
        }
        // Handle string input (JSON encoded vector)
        elseif (is_string($content)) {
            $vector = json_decode($content, true);
        } else {
            return;
        }

        if (! is_array($vector)) {
            return;
        }

        // Find similar documents using vector similarity from relational DB
        $threshold = config('neo4j.similarity.threshold', 0.85);
        $limit = config('neo4j.similarity.max_relationships', 10);

        // Map node labels to their corresponding database tables
        $tableMap = [
            'LawDocument' => 'laws',
            'CourtDecisionDocument' => config('vizra-adk.tables.court_decision_documents', 'court_decision_documents'),
            'TextractDocument' => 'textract_documents',
        ];

        $table = $tableMap[$nodeLabel] ?? null;

        if (! $table) {
            Log::warning('Unknown node label for similarity relationships', [
                'node_label' => $nodeLabel,
                'supported_labels' => array_keys($tableMap),
            ]);

            return;
        }

        // Get similar documents (simplified - in production use proper vector similarity)
        $similar = DB::table($table)
            ->where('id', '!=', $nodeId)
            ->limit($limit)
            ->get();

        foreach ($similar as $doc) {
            $docVector = json_decode($doc->embedding_vector ?? '[]', true);
            if (! is_array($docVector)) {
                continue;
            }

            $similarity = $this->cosineSimilarity($vector, $docVector);

            if ($similarity >= $threshold) {
                $this->graph->createRelationship(
                    $nodeLabel,
                    $nodeId,
                    'SIMILAR_TO',
                    $nodeLabel,
                    $doc->id,
                    [
                        'similarity' => round($similarity, 4),
                        'computed_at' => now()->toIso8601String(),
                    ]
                );
            }
        }
    }

    /**
     * Calculate cosine similarity between two vectors
     *
     * @param  array  $vec1  First vector
     * @param  array  $vec2  Second vector
     * @return float Similarity score between 0.0 and 1.0
     */
    protected function cosineSimilarity(array $vec1, array $vec2): float
    {
        if (count($vec1) !== count($vec2)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $norm1 = 0.0;
        $norm2 = 0.0;

        for ($i = 0; $i < count($vec1); $i++) {
            $dotProduct += $vec1[$i] * $vec2[$i];
            $norm1 += $vec1[$i] * $vec1[$i];
            $norm2 += $vec2[$i] * $vec2[$i];
        }

        $norm1 = sqrt($norm1);
        $norm2 = sqrt($norm2);

        if ($norm1 == 0 || $norm2 == 0) {
            return 0.0;
        }

        return $dotProduct / ($norm1 * $norm2);
    }

    /**
     * Find and link similar nodes based on content similarity
     *
     * Uses pgvector cosine similarity to find similar documents and creates
     * SIMILAR_TO relationships in Neo4j graph database.
     *
     * @param  string  $nodeType  The node type (Decision, Law, Case)
     * @param  string  $nodeId  The node identifier
     * @param  float  $threshold  Minimum similarity score (0.0-1.0)
     * @return int Number of relationships created
     *
     * @throws \App\Exceptions\GraphException if node type is not supported
     */
    public function linkSimilar(string $nodeType, string $nodeId, float $threshold = 0.8): int
    {
        try {
            Log::debug('GraphSimilarityLinker: linkSimilar initiated', [
                'node_type' => $nodeType,
                'node_id' => $nodeId,
                'threshold' => $threshold,
            ]);

            // Get vector store for node type
            $vectorStore = $this->getVectorStoreForNodeType($nodeType);

            // Get source embedding
            $sourceEmbedding = $vectorStore->getEmbedding($nodeId);

            if (! $sourceEmbedding) {
                Log::debug('No embedding found for source node', [
                    'node_type' => $nodeType,
                    'node_id' => $nodeId,
                ]);

                return 0;
            }

            // Find similar documents via pgvector
            $similarDocs = $vectorStore->findSimilar($nodeId, $threshold, 20);

            if (empty($similarDocs)) {
                Log::debug('No similar documents found', [
                    'node_type' => $nodeType,
                    'node_id' => $nodeId,
                    'threshold' => $threshold,
                ]);

                return 0;
            }

            // Delete existing SIMILAR_TO relationships
            $deleteCypher = "
                MATCH (source:$nodeType {id: \$nodeId})-[r:SIMILAR_TO]->()
                DELETE r
            ";
            $this->graph->run($deleteCypher, ['nodeId' => $nodeId]);

            Log::debug('Deleted existing SIMILAR_TO relationships', [
                'node_type' => $nodeType,
                'node_id' => $nodeId,
            ]);

            // Create SIMILAR_TO relationships
            $created = 0;
            foreach ($similarDocs as $doc) {
                $cypher = "
                    MATCH (source:$nodeType {id: \$sourceId})
                    MATCH (target:$nodeType {id: \$targetId})
                    MERGE (source)-[r:SIMILAR_TO {
                        similarity_score: \$score,
                        created_at: datetime()
                    }]->(target)
                    RETURN r
                ";

                $this->graph->run($cypher, [
                    'sourceId' => $nodeId,
                    'targetId' => $doc['id'],
                    'score' => $doc['similarity_score'],
                ]);

                $created++;
            }

            Log::info('GraphSimilarityLinker: SIMILAR_TO relationships created', [
                'node_type' => $nodeType,
                'node_id' => $nodeId,
                'relationships_created' => $created,
                'threshold' => $threshold,
            ]);

            return $created;

        } catch (\App\Exceptions\GraphException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('GraphSimilarityLinker: linkSimilar failed', [
                'node_type' => $nodeType,
                'node_id' => $nodeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \App\Exceptions\GraphException(
                'Failed to link similar nodes: '.$e->getMessage(),
                \App\Exceptions\GraphException::RELATIONSHIP_CREATION_FAILED,
                $e
            );
        }
    }

    /**
     * Get the appropriate vector store service for a given node type
     *
     * @param  string  $nodeType  The node type
     * @return mixed The vector store service (CourtDecisionVectorStoreService|LawVectorStoreService|CaseVectorStoreService)
     *
     * @throws \App\Exceptions\GraphException if node type is not supported
     */
    protected function getVectorStoreForNodeType(string $nodeType)
    {
        $vectorStoreMap = [
            'Decision' => \App\Services\CourtDecisionVectorStoreService::class,
            'Law' => \App\Services\LawVectorStoreService::class,
            'Case' => \App\Services\CaseVectorStoreService::class,
        ];

        if (! isset($vectorStoreMap[$nodeType])) {
            Log::error('Unsupported node type for similarity linking', [
                'node_type' => $nodeType,
                'supported_types' => array_keys($vectorStoreMap),
            ]);

            throw new \App\Exceptions\GraphException(
                "Unsupported node type for similarity linking: {$nodeType}",
                \App\Exceptions\GraphException::INVALID_NODE_TYPE
            );
        }

        return app($vectorStoreMap[$nodeType]);
    }

    /**
     * Extract and link citations from content
     * Not applicable for similarity linker
     */
    public function linkCitations(string $nodeType, string $nodeId, string $content): array
    {
        return [];
    }

    /**
     * Link a node to relevant keywords and topics
     * Not applicable for similarity linker
     */
    public function linkKeywords(string $nodeType, string $nodeId, string $content): array
    {
        return [];
    }

    /**
     * Remove all SIMILAR_TO relationships for a given node
     *
     * Deletes all SIMILAR_TO relationships (both incoming and outgoing)
     * for the specified node.
     *
     * @param  string  $nodeType  The node type (Decision, Law, Case)
     * @param  string  $nodeId  The node identifier
     * @return int Number of relationships deleted
     */
    public function unlinkAll(string $nodeType, string $nodeId): int
    {
        try {
            Log::debug('GraphSimilarityLinker: unlinkAll initiated', [
                'node_type' => $nodeType,
                'node_id' => $nodeId,
            ]);

            // Delete all SIMILAR_TO relationships for this node (both incoming and outgoing)
            // Using undirected pattern -[r:SIMILAR_TO]-() to match both directions
            $cypher = "
                MATCH (source:{$nodeType} {id: \$nodeId})-[r:SIMILAR_TO]-()
                DELETE r
                RETURN count(r) as deleted_count
            ";

            $result = $this->graph->run($cypher, ['nodeId' => $nodeId]);

            $deletedCount = $result[0]['deleted_count'] ?? 0;

            Log::info('GraphSimilarityLinker: Similarity relationships deleted', [
                'node_type' => $nodeType,
                'node_id' => $nodeId,
                'deleted_count' => $deletedCount,
            ]);

            return $deletedCount;

        } catch (\Exception $e) {
            Log::error('GraphSimilarityLinker: unlinkAll failed', [
                'node_type' => $nodeType,
                'node_id' => $nodeId,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Get supported relationship types for similarity linker
     */
    public function getSupportedRelationships(string $nodeType): array
    {
        return ['SIMILAR_TO'];
    }
}
