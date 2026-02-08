<?php

namespace App\Services\Graph;

use InvalidArgumentException;

class BatchCypherBuilder
{
    /**
     * Default batch flush threshold
     */
    protected int $flushThreshold;

    public function __construct(?int $flushThreshold = null)
    {
        $this->flushThreshold = $flushThreshold ?? config('graph.batch.flush_threshold', 100);
    }

    /**
     * Build a batch MERGE query for nodes using UNWIND
     *
     * @param string $label Node label
     * @param array $nodes Array of node data arrays
     * @param string $idKey Property to use as unique identifier
     * @return array{query: string, parameters: array}
     */
    public function buildBatchUpsertNodes(string $label, array $nodes, string $idKey = 'id'): array
    {
        if (empty($nodes)) {
            throw new InvalidArgumentException('Nodes array cannot be empty');
        }

        // Validate identifiers to prevent Cypher injection
        $this->validateIdentifier($label, 'Label');
        $this->validateIdentifier($idKey, 'ID key');

        // Build the property SET clause dynamically from first node's keys
        $firstNode = reset($nodes);

        // Validate that idKey exists in node data
        if (!array_key_exists($idKey, $firstNode)) {
            throw new InvalidArgumentException("ID key '{$idKey}' not found in node data");
        }

        // Validate all property keys
        $propKeys = array_keys($firstNode);
        foreach ($propKeys as $key) {
            $this->validateIdentifier($key, 'Property name');
        }

        $setClause = implode(', ', array_map(fn($k) => "n.{$k} = node.{$k}", $propKeys));

        $query = <<<CYPHER
UNWIND \$nodes AS node
MERGE (n:{$label} {{$idKey}: node.{$idKey}})
SET {$setClause}
RETURN count(n) AS nodesProcessed
CYPHER;

        return [
            'query' => $query,
            'parameters' => ['nodes' => array_values($nodes)],
        ];
    }

    /**
     * Build a batch MERGE query for relationships using UNWIND
     *
     * @param string $fromLabel Source node label
     * @param string $toLabel Target node label
     * @param string $relType Relationship type
     * @param array $relationships Array of relationship data with fromId, toId, and optional props
     * @return array{query: string, parameters: array}
     */
    public function buildBatchUpsertRelationships(
        string $fromLabel,
        string $toLabel,
        string $relType,
        array $relationships,
        string $fromIdKey = 'id',
        string $toIdKey = 'id'
    ): array {
        if (empty($relationships)) {
            throw new InvalidArgumentException('Relationships array cannot be empty');
        }

        // Validate identifiers to prevent Cypher injection
        $this->validateIdentifier($fromLabel, 'From label');
        $this->validateIdentifier($toLabel, 'To label');
        $this->validateIdentifier($relType, 'Relationship type');
        $this->validateIdentifier($fromIdKey, 'From ID key');
        $this->validateIdentifier($toIdKey, 'To ID key');

        // Check if relationships have properties beyond fromId/toId
        $firstRel = reset($relationships);

        // Validate that fromId and toId exist in relationship data
        if (!array_key_exists('fromId', $firstRel)) {
            throw new InvalidArgumentException("Required field 'fromId' not found in relationship data");
        }
        if (!array_key_exists('toId', $firstRel)) {
            throw new InvalidArgumentException("Required field 'toId' not found in relationship data");
        }

        $hasProps = count(array_diff(array_keys($firstRel), ['fromId', 'toId'])) > 0;

        if ($hasProps) {
            $propKeys = array_diff(array_keys($firstRel), ['fromId', 'toId']);
            // Validate all property keys
            foreach ($propKeys as $key) {
                $this->validateIdentifier($key, 'Property name');
            }
            $setClause = 'SET ' . implode(', ', array_map(fn($k) => "r.{$k} = rel.{$k}", $propKeys));
        } else {
            $setClause = '';
        }

        $query = <<<CYPHER
UNWIND \$relationships AS rel
MATCH (from:{$fromLabel} {{$fromIdKey}: rel.fromId})
MATCH (to:{$toLabel} {{$toIdKey}: rel.toId})
MERGE (from)-[r:{$relType}]->(to)
{$setClause}
RETURN count(r) AS relationshipsProcessed
CYPHER;

        return [
            'query' => trim($query),
            'parameters' => ['relationships' => array_values($relationships)],
        ];
    }

    /**
     * Get flush threshold
     */
    public function getFlushThreshold(): int
    {
        return $this->flushThreshold;
    }

    /**
     * Chunk array into batches based on flush threshold
     *
     * @param array $items Items to chunk
     * @return array Array of chunks
     */
    public function chunk(array $items): array
    {
        return array_chunk($items, $this->flushThreshold);
    }

    /**
     * Validate identifier name (labels, property names, relationship types)
     *
     * @param string $identifier The identifier to validate
     * @param string $context Description of what is being validated (for error messages)
     * @throws InvalidArgumentException if identifier is invalid
     */
    private function validateIdentifier(string $identifier, string $context): void
    {
        if (empty($identifier)) {
            throw new InvalidArgumentException("{$context} cannot be empty");
        }

        // Neo4j identifiers: alphanumeric, underscore, must start with letter
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $identifier)) {
            throw new InvalidArgumentException(
                "{$context} '{$identifier}' contains invalid characters. " .
                "Must start with a letter and contain only letters, numbers, and underscores."
            );
        }
    }
}
