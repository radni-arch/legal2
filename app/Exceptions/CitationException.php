<?php

namespace App\Exceptions;

use Exception;

class CitationException extends Exception
{
    public const DECISION_NOT_FOUND = 10001;

    public const CITATION_EXTRACTION_FAILED = 10002;

    public const CITATION_DETECTION_FAILED = 10003;

    public const SIMILARITY_CALCULATION_FAILED = 10004;

    public const VECTOR_QUERY_FAILED = 10005;

    public const GRAPH_QUERY_FAILED = 10006;

    public const INVALID_CITATION_FORMAT = 10007;

    public const DATABASE_QUERY_FAILED = 10008;

    public const RESULT_ENHANCEMENT_FAILED = 10009;

    public const UNEXPECTED_ERROR = 10999;

    /**
     * Report the exception
     */
    public function report(): bool
    {
        logger()->error('Citation exception occurred', [
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
            self::DECISION_NOT_FOUND => 'Court decision not found.',
            self::CITATION_EXTRACTION_FAILED => 'Failed to extract citations from decision.',
            self::CITATION_DETECTION_FAILED => 'Citation detection failed.',
            self::SIMILARITY_CALCULATION_FAILED => 'Failed to calculate citation similarity.',
            self::VECTOR_QUERY_FAILED => 'Vector similarity query failed.',
            self::GRAPH_QUERY_FAILED => 'Graph citation query failed.',
            self::INVALID_CITATION_FORMAT => 'Invalid citation format.',
            self::DATABASE_QUERY_FAILED => 'Database query failed during citation analysis.',
            self::RESULT_ENHANCEMENT_FAILED => 'Failed to enhance citation results.',
            default => 'Citation analysis operation failed.',
        };
    }
}
