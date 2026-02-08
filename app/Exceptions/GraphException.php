<?php

namespace App\Exceptions;

use Exception;

class GraphException extends Exception
{
    public const CONNECTION_FAILED = 4001;

    public const QUERY_FAILED = 4002;

    public const NODE_CREATION_FAILED = 4003;

    public const RELATIONSHIP_CREATION_FAILED = 4004;

    public const NODE_NOT_FOUND = 4005;

    public const INVALID_CYPHER_QUERY = 4006;

    public const TRANSACTION_FAILED = 4007;

    public const SYNC_FAILED = 4008;

    public const INVALID_NODE_TYPE = 4009;

    public const RELATIONSHIP_DELETE_FAILED = 2008;

    public const UNEXPECTED_ERROR = 4999;

    /**
     * Report the exception
     */
    public function report(): bool
    {
        logger()->error('Graph database exception occurred', [
            'exception' => static::class,
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
        ]);

        return true;
    }

    /**
     * Get user-friendly error message
     */
    public function getUserMessage(): string
    {
        return match ($this->getCode()) {
            self::CONNECTION_FAILED => 'Failed to connect to graph database.',
            self::QUERY_FAILED => 'Graph database query failed.',
            self::NODE_CREATION_FAILED => 'Failed to create graph node.',
            self::RELATIONSHIP_CREATION_FAILED => 'Failed to create graph relationship.',
            self::NODE_NOT_FOUND => 'Graph node not found.',
            self::INVALID_CYPHER_QUERY => 'Invalid graph query syntax.',
            self::TRANSACTION_FAILED => 'Graph transaction failed.',
            self::SYNC_FAILED => 'Graph synchronization failed.',
            self::INVALID_NODE_TYPE => 'Invalid node type for this operation.',
            self::RELATIONSHIP_DELETE_FAILED => 'Failed to delete graph relationship.',
            default => 'Graph database operation failed.',
        };
    }
}
