<?php

namespace App\Exceptions;

use Exception;

class AnalysisException extends Exception
{
    public const CASE_NOT_FOUND = 6001;

    public const INSUFFICIENT_DATA = 6002;

    public const LLM_REQUEST_FAILED = 6003;

    public const RESPONSE_PARSING_FAILED = 6004;

    public const INVALID_ANALYSIS_TYPE = 6005;

    public const MODULE_NOT_FOUND = 6006;

    public const CONTEXT_BUILDING_FAILED = 6007;

    public const RESULT_VALIDATION_FAILED = 6008;

    public const ANALYSIS_FAILED = 6009;

    public const UNEXPECTED_ERROR = 6999;
    const EXTRACTION_FAILED = 7000;

    /**
     * Report the exception
     */
    public function report(): bool
    {
        logger()->error('Analysis exception occurred', [
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
            self::CASE_NOT_FOUND => 'Case not found for analysis.',
            self::INSUFFICIENT_DATA => 'Insufficient data available for analysis.',
            self::LLM_REQUEST_FAILED => 'AI analysis service temporarily unavailable.',
            self::RESPONSE_PARSING_FAILED => 'Failed to process AI analysis results.',
            self::INVALID_ANALYSIS_TYPE => 'Invalid analysis type requested.',
            self::MODULE_NOT_FOUND => 'Analysis module not found.',
            self::CONTEXT_BUILDING_FAILED => 'Failed to build analysis context.',
            self::RESULT_VALIDATION_FAILED => 'Analysis results failed validation.',
            self::ANALYSIS_FAILED => 'Analysis operation failed.',
            default => 'Legal analysis failed.',
        };
    }
}
