<?php

namespace App\Exceptions\Graph;

use Exception;
use Throwable;

/**
 * Exception thrown when graph synchronization operations fail.
 *
 * This exception provides context about the sync failure including
 * the node type, node ID, and operation that failed.
 */
class GraphSyncException extends Exception
{
    /**
     * The type of node being synced (e.g., 'Judge', 'Party', 'Decision')
     */
    protected ?string $nodeType = null;

    /**
     * The ID of the node being synced
     */
    protected mixed $nodeId = null;

    /**
     * The operation being performed (e.g., 'sync', 'create', 'update', 'delete')
     */
    protected ?string $operation = null;

    /**
     * Set the node type context
     */
    public function setNodeType(string $nodeType): self
    {
        $this->nodeType = $nodeType;
        return $this;
    }

    /**
     * Get the node type context
     */
    public function getNodeType(): ?string
    {
        return $this->nodeType;
    }

    /**
     * Set the node ID context
     */
    public function setNodeId(mixed $nodeId): self
    {
        $this->nodeId = $nodeId;
        return $this;
    }

    /**
     * Get the node ID context
     */
    public function getNodeId(): mixed
    {
        return $this->nodeId;
    }

    /**
     * Set the operation context
     */
    public function setOperation(string $operation): self
    {
        $this->operation = $operation;
        return $this;
    }

    /**
     * Get the operation context
     */
    public function getOperation(): ?string
    {
        return $this->operation;
    }

    /**
     * Get all context as an array
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return [
            'node_type' => $this->nodeType,
            'node_id' => $this->nodeId,
            'operation' => $this->operation,
        ];
    }

    /**
     * Create exception for a node sync failure
     *
     * @param string $nodeType The type of node (e.g., 'Judge', 'Party')
     * @param mixed $nodeId The ID of the node
     * @param Throwable|null $previous The previous exception, if any
     * @return static
     */
    public static function forNode(string $nodeType, mixed $nodeId, ?Throwable $previous = null): static
    {
        $message = sprintf(
            'Failed to sync %s node with ID %s to graph database',
            $nodeType,
            $nodeId
        );

        $exception = new static($message, 0, $previous);
        $exception->setNodeType($nodeType);
        $exception->setNodeId($nodeId);

        return $exception;
    }

    /**
     * Create exception for a specific operation failure
     *
     * @param string $operation The operation that failed (e.g., 'create', 'update', 'delete')
     * @param string $nodeType The type of node
     * @param mixed $nodeId The ID of the node
     * @param Throwable|null $previous The previous exception, if any
     * @return static
     */
    public static function forOperation(
        string $operation,
        string $nodeType,
        mixed $nodeId,
        ?Throwable $previous = null
    ): static {
        $message = sprintf(
            'Failed to %s %s node with ID %s in graph database',
            $operation,
            $nodeType,
            $nodeId
        );

        $exception = new static($message, 0, $previous);
        $exception->setOperation($operation);
        $exception->setNodeType($nodeType);
        $exception->setNodeId($nodeId);

        return $exception;
    }
}
