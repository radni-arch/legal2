<?php

namespace App\Exceptions;

use Exception;

class EmbeddingException extends Exception
{
    public const API_CONNECTION_FAILED = 2001;

    public const API_RATE_LIMITED = 2002;

    public const INVALID_INPUT = 2003;

    public const MODEL_NOT_FOUND = 2004;

    public const RESPONSE_PARSING_FAILED = 2005;

    public const TOKEN_LIMIT_EXCEEDED = 2006;

    public const UNEXPECTED_ERROR = 2999;

    /**
     * Report the exception
     */
    public function report(): bool
    {
        logger()->error('Embedding exception occurred', [
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
            self::API_CONNECTION_FAILED => 'Failed to connect to embedding service.',
            self::API_RATE_LIMITED => 'Embedding service rate limit exceeded. Please try again later.',
            self::INVALID_INPUT => 'Invalid text provided for embedding generation.',
            self::MODEL_NOT_FOUND => 'Embedding model not available.',
            self::RESPONSE_PARSING_FAILED => 'Failed to parse embedding service response.',
            self::TOKEN_LIMIT_EXCEEDED => 'Text too long for embedding generation.',
            default => 'Failed to generate text embeddings.',
        };
    }
}
