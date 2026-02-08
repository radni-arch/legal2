<?php

namespace App\Exceptions;

use Exception;

class SearchException extends Exception
{
    public const EMBEDDING_FAILED = 1001;

    public const VECTOR_SEARCH_FAILED = 1002;

    public const CORPUS_NOT_FOUND = 1003;

    public const INVALID_QUERY = 1004;

    public const RESULT_FORMATTING_FAILED = 1005;

    public const CACHE_FAILED = 1006;

    public const DATABASE_CONNECTION_FAILED = 1007;

    public const FILTER_APPLICATION_FAILED = 1008;

    public const RESULT_NORMALIZATION_FAILED = 1009;

    public const UNEXPECTED_ERROR = 1999;

    /**
     * Report the exception to monitoring services
     */
    public function report(): bool
    {
        // Only report non-client errors
        if ($this->getCode() >= 1900) {
            logger()->error('Search exception occurred', [
                'exception' => static::class,
                'message' => $this->getMessage(),
                'code' => $this->getCode(),
                'file' => $this->getFile(),
                'line' => $this->getLine(),
            ]);
        }

        return true;
    }

    /**
     * Get user-friendly error message
     */
    public function getUserMessage(): string
    {
        return match ($this->getCode()) {
            self::EMBEDDING_FAILED => 'Failed to process your search query. Please try again.',
            self::VECTOR_SEARCH_FAILED => 'Search service is temporarily unavailable. Please try again later.',
            self::CORPUS_NOT_FOUND => 'The requested legal corpus was not found.',
            self::INVALID_QUERY => 'Your search query is invalid. Please check and try again.',
            self::RESULT_FORMATTING_FAILED => 'Failed to format search results. Please try again.',
            self::CACHE_FAILED => 'Search completed but caching failed.',
            self::DATABASE_CONNECTION_FAILED => 'Database connection failed. Please try again later.',
            self::FILTER_APPLICATION_FAILED => 'Failed to apply search filters. Please try again.',
            self::RESULT_NORMALIZATION_FAILED => 'Failed to normalize search results. Please try again.',
            default => 'An unexpected error occurred. Please try again later.',
        };
    }
}
