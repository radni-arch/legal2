<?php

namespace App\Contracts;

/**
 * Interface for graph database linking services
 *
 * This interface defines the contract for services that create
 * relationships and links between nodes in the graph database.
 *
 * Implementations should handle:
 * - Keyword extraction and linking
 * - Citation extraction and linking
 * - Similarity calculation and linking
 * - Any other relationship creation
 */
interface GraphLinkerInterface
{
    /**
     * Create graph relationships for a given node
     *
     * Analyzes the provided content and creates appropriate relationships
     * in the graph database (keywords, citations, similarities, etc.)
     *
     * @param  string  $nodeType  The type of node (e.g., 'LawDocument', 'CaseDocument')
     * @param  string  $nodeId  The unique identifier of the node
     * @param  string|array|null  $content  The content to analyze (string for text, array for embeddings, null if N/A)
     *
     * @throws \InvalidArgumentException if node type or ID is invalid
     * @throws \RuntimeException if graph database operation fails
     */
    public function link(string $nodeType, string $nodeId, string $content): void;

    /**
     * Find and link similar nodes based on content similarity.
     *
     * Uses vector similarity or other algorithms to find related nodes
     * and create SIMILAR_TO relationships.
     *
     * @param  string  $nodeType  The type of the source node
     * @param  string  $nodeId  The unique identifier of the source node
     * @param  float  $threshold  Similarity threshold (0.0 to 1.0)
     * @return int Number of similarity links created
     */
    public function linkSimilar(string $nodeType, string $nodeId, float $threshold = 0.8): int;

    /**
     * Extract and link citations from content.
     *
     * Identifies legal citations (laws, articles, court decisions) and creates
     * CITES relationships to the cited documents.
     *
     * @param  string  $nodeType  The type of the source node
     * @param  string  $nodeId  The unique identifier of the source node
     * @param  string  $content  The content to analyze for citations
     * @return array<string> Array of cited document IDs that were linked
     */
    public function linkCitations(string $nodeType, string $nodeId, string $content): array;

    /**
     * Link a node to relevant keywords and topics.
     *
     * Extracts keywords from content and creates HAS_KEYWORD relationships.
     *
     * @param  string  $nodeType  The type of the source node
     * @param  string  $nodeId  The unique identifier of the source node
     * @param  string  $content  The content to analyze for keywords
     * @return array<string> Array of keyword labels that were linked
     */
    public function linkKeywords(string $nodeType, string $nodeId, string $content): array;

    /**
     * Remove all relationships for a given node.
     *
     * @param  string  $nodeType  The type of the node
     * @param  string  $nodeId  The unique identifier of the node
     * @return int Number of relationships removed
     */
    public function unlinkAll(string $nodeType, string $nodeId): int;

    /**
     * Get supported relationship types for a node type.
     *
     * @param  string  $nodeType  The type of the node
     * @return array<string> Array of supported relationship types
     */
    public function getSupportedRelationships(string $nodeType): array;
}
