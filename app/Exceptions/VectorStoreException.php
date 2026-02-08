<?php

namespace App\Exceptions;

use Exception;

class VectorStoreException extends Exception
{
    public const CONNECTION_FAILED = 3001;

    public const QUERY_FAILED = 3002;

    public const INGESTION_FAILED = 3003;

    public const NAMESPACE_NOT_FOUND = 3004;

    public const INDEX_NOT_FOUND = 3005;

    public const INVALID_VECTOR_DIMENSION = 3006;

    public const BATCH_OPERATION_FAILED = 3007;

    public const METADATA_INVALID = 3008;

    public const UNEXPECTED_ERROR = 3999;

    /**
     * Report the exception
     */
    public function report(): bool
    {
        logger()->error('Vector store exception occurred', [
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
            self::CONNECTION_FAILED => 'Failed to connect to vector database.',
            self::QUERY_FAILED => 'Vector search query failed.',
            self::INGESTION_FAILED => 'Failed to store data in vector database.',
            self::NAMESPACE_NOT_FOUND => 'Vector database namespace not found.',
            self::INDEX_NOT_FOUND => 'Vector database index not found.',
            self::INVALID_VECTOR_DIMENSION => 'Vector dimension mismatch.',
            self::BATCH_OPERATION_FAILED => 'Batch vector operation failed.',
            self::METADATA_INVALID => 'Invalid metadata provided for vector storage.',
            default => 'Vector database operation failed.',
        };
    }
}
