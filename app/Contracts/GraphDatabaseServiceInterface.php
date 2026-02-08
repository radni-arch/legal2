<?php

namespace App\Contracts;

/**
 * Graph Database Service Interface
 *
 * Defines the contract for Neo4j graph database operations including
 * node management, relationship creation, graph traversal, and transactions.
 */
interface GraphDatabaseServiceInterface
{
    /**
     * Check if Neo4j is available and connected
     *
     * @return bool True if Neo4j is available, false otherwise
     */
    public function isAvailable(): bool;

    /**
     * Get cached health status without performing a new check
     *
     * @return array|null Cached health status or null if not cached
     */
    public function getCachedHealthStatus(): ?array;

    /**
     * Get current health status of Neo4j connection
     *
     * Performs a health check and returns detailed status information
     * about connectivity, constraints, indexes, and overall health.
     *
     * @return array{
     *     available: bool,
     *     healthy: bool,
     *     constraints_exist: bool,
     *     indexes_exist: bool,
     *     checked_at: string
     * }
     */
    public function getHealthStatus(): array;

    /**
     * Execute a Cypher query with parameters
     *
     * @param  string  $query  The Cypher query to execute
     * @param  array  $parameters  Query parameters
     * @return mixed Query result
     *
     * @throws \RuntimeException If query execution fails
     */
    public function run(string $query, array $parameters = []): mixed;

    /**
     * Execute multiple operations in a transaction
     *
     * @param  callable  $callback  Function containing graph operations
     * @return mixed The result of the callback
     *
     * @throws \RuntimeException If transaction fails
     */
    public function transaction(callable $callback): mixed;

    /**
     * Initialize graph database schema
     *
     * Creates necessary constraints and indexes for optimal performance.
     */
    public function initializeSchema(): void;

    /**
     * Create or update a node in the graph
     *
     * @param  string  $label  Node label (e.g., 'LawDocument', 'CourtDecision')
     * @param  string  $id  Unique identifier for the node
     * @param  array  $properties  Node properties
     */
    public function upsertNode(string $label, string $id, array $properties): void;

    /**
     * Create a relationship between two nodes
     *
     * @param  string  $fromLabel  Label of source node
     * @param  string  $fromId  ID of source node
     * @param  string  $relType  Relationship type (e.g., 'CITES', 'SIMILAR_TO')
     * @param  string  $toLabel  Label of target node
     * @param  string  $toId  ID of target node
     * @param  array  $properties  Relationship properties
     */
    public function createRelationship(
        string $fromLabel,
        string $fromId,
        string $relType,
        string $toLabel,
        string $toId,
        array $properties = []
    ): void;

    /**
     * Delete a node and all its relationships
     *
     * @param  string  $label  Node label
     * @param  string  $id  Node identifier
     */
    public function deleteNode(string $label, string $id): void;

    /**
     * Find similar nodes based on properties
     *
     * @param  string  $label  Node label to search
     * @param  array  $properties  Properties to match
     * @param  int  $limit  Maximum number of results
     * @return array Array of similar nodes
     */
    public function findSimilar(string $label, array $properties, int $limit = 10): array;

    /**
     * Get nodes related to a given node
     *
     * @param  string  $label  Source node label
     * @param  string  $id  Source node identifier
     * @param  string|null  $relType  Optional relationship type filter
     * @param  int  $depth  Traversal depth (1-N hops)
     * @param  int  $limit  Maximum results
     * @return array Array of related nodes
     */
    public function getRelated(
        string $label,
        string $id,
        ?string $relType = null,
        int $depth = 1,
        int $limit = 50
    ): array;

    /**
     * Find shortest path between two nodes
     *
     * @param  string  $fromLabel  Source node label
     * @param  string  $fromId  Source node ID
     * @param  string  $toLabel  Target node label
     * @param  string  $toId  Target node ID
     * @param  int  $maxDepth  Maximum path depth
     * @return array|null Path information or null if no path exists
     */
    public function findPath(
        string $fromLabel,
        string $fromId,
        string $toLabel,
        string $toId,
        int $maxDepth = 5
    ): ?array;

    /**
     * Get a node with all its relationships
     *
     * @param  string  $label  Node label
     * @param  string  $id  Node identifier
     * @return array|null Node with relationships or null if not found
     */
    public function getNodeWithRelationships(string $label, string $id): ?array;

    /**
     * Batch upsert multiple nodes
     *
     * @param  string  $label  Node label
     * @param  array  $nodes  Array of nodes to upsert
     * @param  string  $idKey  Property to use as unique identifier
     * @return int Total count of nodes processed
     */
    public function batchUpsertNodes(string $label, array $nodes, string $idKey = 'id'): int;

    /**
     * Batch upsert multiple relationships
     *
     * @param  string  $type  Relationship type
     * @param  array  $relationships  Array of relationships to upsert (must include fromId and toId)
     * @return int Total count of relationships processed
     */
    public function batchUpsertRelationships(string $type, array $relationships): int;

    /**
     * Clear all nodes and relationships from the database
     *
     * WARNING: This operation is destructive and cannot be undone.
     */
    public function clearAll(): void;

    /**
     * Store a court decision in the graph
     *
     * Legacy method for decision storage.
     *
     * @param  array  $meta  Decision metadata
     * @param  string  $docId  Document identifier
     */
    public function storeDecisionInGraph(array $meta, string $docId): void;
}
