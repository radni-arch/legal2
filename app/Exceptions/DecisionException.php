<?php

namespace App\Exceptions;

use Exception;

class DecisionException extends Exception
{
    public const DISCOVERY_FAILED = 6001;

    public const SEARCH_FAILED = 6002;

    public const METADATA_FETCH_FAILED = 6003;

    public const INGESTION_FAILED = 6004;

    public const CACHE_ERROR = 6005;

    public const DECISION_NOT_FOUND = 6006;

    public const INVALID_PARAMETERS = 6007;

    public const BATCH_INGESTION_FAILED = 6008;

    public const UNEXPECTED_ERROR = 6999;

    /**
     * Report the exception
     */
    public function report(): bool
    {
        logger()->error('Decision exception occurred', [
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
            self::DISCOVERY_FAILED => 'Court decision discovery failed.',
            self::SEARCH_FAILED => 'Decision search failed.',
            self::METADATA_FETCH_FAILED => 'Failed to fetch decision metadata.',
            self::INGESTION_FAILED => 'Decision ingestion failed.',
            self::CACHE_ERROR => 'Cache operation failed.',
            self::DECISION_NOT_FOUND => 'Decision not found.',
            self::INVALID_PARAMETERS => 'Invalid search parameters.',
            self::BATCH_INGESTION_FAILED => 'Batch ingestion operation failed.',
            default => 'Decision operation failed.',
        };
    }
}
