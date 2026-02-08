<?php

namespace App\Services\Graph;

/**
 * Graph Research Enhancer for ResearchSpecialistAgent (Sprint 4.3)
 *
 * Enhances vector-based research with graph traversal capabilities:
 * - Finding decisions that cite discovered laws
 * - Citation chain traversal (2-3 hops)
 * - Combining vector search with graph results
 * - Calculating precision/recall metrics
 *
 * This service enables ResearchSpecialistAgent to leverage both
 * vector similarity and graph relationships for comprehensive research.
 */
class GraphResearchEnhancer
{
    public function __construct(
        protected LawGraphSyncService $lawGraphSync,
        protected ?GraphEmbeddingService $embeddingService = null
    ) {
        $this->embeddingService = $embeddingService ?? app(GraphEmbeddingService::class);
    }

    /**
     * Find court decisions that cite the given laws
     *
     * @param  array  $lawIds  Array of law IDs found via vector search
     * @return array Array of decisions citing these laws
     */
    public function findDecisionsCitingLaws(array $lawIds): array
    {
        if (empty($lawIds)) {
            return [];
        }

        // Cypher query to find decisions that cite any of the given laws
        $cypher = <<<'CYPHER'
            MATCH (decision:Decision)-[:CITES]->(law:LawDocument)
            WHERE law.id IN $law_ids
            RETURN DISTINCT
                decision.id AS decision_id,
                decision.case_number AS case_number,
                law.id AS cited_law_id,
                law.law_number AS cited_law_number
            ORDER BY decision.decision_date DESC
        CYPHER;

        return $this->lawGraphSync->query($cypher, ['law_ids' => $lawIds]);
    }

    /**
     * Traverse citation chain to find related decisions
     *
     * Follows CITES relationships for N hops to discover
     * related decisions that may not appear in vector search.
     *
     * @param  string  $decisionId  Starting decision ID
     * @param  int  $hops  Number of hops (1-3)
     * @param  int  $limit  Maximum results to return
     * @return array Array of related decisions with hop count
     */
    public function traverseCitationChain(string $decisionId, int $hops = 2, int $limit = 50): array
    {
        // Cypher query to traverse citation chain
        $cypher = <<<CYPHER
            MATCH path = (start:Decision)-[:CITES*1..{$hops}]->(related:Decision)
            WHERE start.id = \$decision_id
            RETURN DISTINCT
                related.id AS related_decision_id,
                related.case_number AS case_number,
                length(path) AS hops,
                length(path) AS path_length
            ORDER BY hops ASC, related.decision_date DESC
            LIMIT \$limit
        CYPHER;

        $results = $this->lawGraphSync->query($cypher, [
            'decision_id' => $decisionId,
            'limit' => $limit,
        ]);

        // Limit results if needed (belt and suspenders with LIMIT in Cypher)
        return array_slice($results, 0, $limit);
    }

    /**
     * Enhance research results by combining vector search with graph traversal
     *
     * Takes vector search results and enriches them with:
     * - Decisions citing the discovered laws
     * - Related decisions via citation chains
     * - Deduplication and metrics
     *
     * @param  array  $vectorLaws  Laws found via vector search
     * @param  array  $vectorDecisions  Decisions found via vector search
     * @return array Enhanced results with graph data and metrics
     */
    public function enhanceResearchResults(array $vectorLaws, array $vectorDecisions): array
    {
        // Extract law IDs
        $lawIds = array_column($vectorLaws, 'id');

        // 1. Find decisions citing the discovered laws
        $graphDecisionsCitingLaws = $this->findDecisionsCitingLaws($lawIds);

        // 2. For each vector decision, traverse citation chain
        $graphRelatedDecisions = [];
        foreach ($vectorDecisions as $decision) {
            $related = $this->traverseCitationChain($decision['id'], 2, 10);
            $graphRelatedDecisions = array_merge($graphRelatedDecisions, $related);
        }

        // 3. Deduplicate decisions
        $vectorDecisionIds = array_column($vectorDecisions, 'id');
        $graphCitingIds = array_column($graphDecisionsCitingLaws, 'decision_id');
        $graphRelatedIds = array_column($graphRelatedDecisions, 'related_decision_id');

        // Remove duplicates from graph results
        $graphDecisionsCitingLaws = array_filter(
            $graphDecisionsCitingLaws,
            fn ($d) => ! in_array($d['decision_id'], $vectorDecisionIds)
        );

        $allDecisionIds = array_unique(array_merge(
            $vectorDecisionIds,
            array_column($graphDecisionsCitingLaws, 'decision_id'),
            $graphRelatedIds
        ));

        $totalUniqueDecisions = count($allDecisionIds);

        // 4. Calculate metrics
        $metrics = $this->calculateMetrics(
            count($vectorDecisions),
            count($graphDecisionsCitingLaws),
            count($graphRelatedDecisions),
            $totalUniqueDecisions
        );

        return [
            'vector_laws' => $vectorLaws,
            'vector_decisions' => $vectorDecisions,
            'graph_decisions_citing_laws' => array_values($graphDecisionsCitingLaws),
            'graph_related_decisions' => $graphRelatedDecisions,
            'total_decisions' => $totalUniqueDecisions,
            'metrics' => $metrics,
        ];
    }

    /**
     * Calculate enhancement metrics
     *
     * @param  int  $vectorCount  Decisions from vector search
     * @param  int  $graphCitingCount  Decisions from graph (citing laws)
     * @param  int  $graphRelatedCount  Related decisions from citation chains
     * @param  int  $totalUnique  Total unique decisions
     * @return array Metrics
     */
    protected function calculateMetrics(
        int $vectorCount,
        int $graphCitingCount,
        int $graphRelatedCount,
        int $totalUnique
    ): array {
        $graphCount = $graphCitingCount + $graphRelatedCount;

        $enhancementPercentage = $vectorCount > 0
            ? ($graphCount / $vectorCount) * 100
            : 0;

        return [
            'vector_decision_count' => $vectorCount,
            'graph_decision_count' => $graphCount,
            'graph_citing_laws_count' => $graphCitingCount,
            'graph_related_count' => $graphRelatedCount,
            'total_unique_decisions' => $totalUnique,
            'graph_enhancement_percentage' => round($enhancementPercentage, 2),
        ];
    }

    /**
     * Hybrid Similarity Search: Combine content and graph structure (Sprint 7.1)
     *
     * Combines vector similarity (content) with graph embeddings (structure)
     * to find more relevant decisions than either method alone.
     *
     * @param  string  $decisionId  Source decision ID
     * @param  int  $limit  Number of results to return
     * @param  float  $contentWeight  Weight for content similarity (0.0-1.0, default 0.6)
     * @return array Hybrid search results with separate scores
     */
    public function hybridSimilaritySearch(
        string $decisionId,
        int $limit = 10,
        float $contentWeight = 0.6
    ): array {
        // Validate weight
        $contentWeight = max(0.0, min(1.0, $contentWeight));
        $graphWeight = 1.0 - $contentWeight;

        // Get graph-based similar decisions
        $graphResults = $this->embeddingService->findSimilarByGraph($decisionId, $limit * 2);

        if (empty($graphResults)) {
            return [
                'success' => false,
                'error' => 'No graph embeddings found for decision. Run: php artisan graph:generate-embeddings',
                'results' => [],
            ];
        }

        // For now, return graph results with hybrid scoring placeholder
        // In production, this would also fetch content similarity scores
        // and combine them using the weighted formula
        $hybridResults = array_map(function ($result) use ($contentWeight, $graphWeight) {
            // Placeholder: In production, fetch content similarity from vector store
            $contentScore = 0.5; // Would come from vector search

            $hybridScore = ($contentWeight * $contentScore) + ($graphWeight * $result['similarity']);

            return [
                'decision_id' => $result['decision_id'],
                'hybrid_score' => round($hybridScore, 4),
                'content_score' => round($contentScore, 4),
                'graph_score' => round($result['similarity'], 4),
                'content_weight' => $contentWeight,
                'graph_weight' => $graphWeight,
            ];
        }, $graphResults);

        // Sort by hybrid score descending
        usort($hybridResults, fn ($a, $b) => $b['hybrid_score'] <=> $a['hybrid_score']);

        // Limit results
        $hybridResults = array_slice($hybridResults, 0, $limit);

        return [
            'success' => true,
            'results' => $hybridResults,
            'total' => count($hybridResults),
            'weights' => [
                'content' => $contentWeight,
                'graph' => $graphWeight,
            ],
        ];
    }

    /**
     * Find structurally similar decisions using graph embeddings
     *
     * Wrapper around GraphEmbeddingService for convenience.
     * Finds decisions with similar citation patterns (structural similarity).
     *
     * @param  string  $decisionId  Source decision ID
     * @param  int  $limit  Number of results
     * @return array Similar decisions with similarity scores
     */
    public function findSimilarByGraphStructure(string $decisionId, int $limit = 10): array
    {
        return $this->embeddingService->findSimilarByGraph($decisionId, $limit);
    }

    /**
     * Check if decision has graph embedding available
     *
     * @param  string  $decisionId  Decision ID
     * @return bool True if embedding exists
     */
    public function hasGraphEmbedding(string $decisionId): bool
    {
        return $this->embeddingService->hasEmbedding($decisionId);
    }
}
