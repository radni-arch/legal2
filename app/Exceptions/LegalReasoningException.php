<?php

namespace App\Exceptions;

use Exception;

class LegalReasoningException extends Exception
{
    public const FEATURE_EXTRACTION_FAILED = 7001;

    public const HISTORICAL_DATA_QUERY_FAILED = 7002;

    public const BASELINE_CALCULATION_FAILED = 7003;

    public const COMPLEXITY_CALCULATION_FAILED = 7004;

    public const BACKLOG_ESTIMATION_FAILED = 7005;

    public const MILESTONE_GENERATION_FAILED = 7006;

    public const CASE_NOT_FOUND = 7007;

    public const INVALID_CASE_DATA = 7008;

    public const LOGIC_VALIDATION_FAILED = 7009;

    public const STRATEGIC_PLANNING_FAILED = 7010;

    public const UNEXPECTED_ERROR = 7999;

    /**
     * Report the exception
     */
    public function report(): bool
    {
        logger()->error('Legal reasoning exception occurred', [
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
            self::FEATURE_EXTRACTION_FAILED => 'Failed to extract case features.',
            self::HISTORICAL_DATA_QUERY_FAILED => 'Failed to query historical case data.',
            self::BASELINE_CALCULATION_FAILED => 'Failed to calculate baseline duration.',
            self::COMPLEXITY_CALCULATION_FAILED => 'Failed to calculate case complexity.',
            self::BACKLOG_ESTIMATION_FAILED => 'Failed to estimate court backlog.',
            self::MILESTONE_GENERATION_FAILED => 'Failed to generate case milestones.',
            self::CASE_NOT_FOUND => 'Case not found.',
            self::INVALID_CASE_DATA => 'Invalid case data provided.',
            self::LOGIC_VALIDATION_FAILED => 'Legal logic validation failed.',
            self::STRATEGIC_PLANNING_FAILED => 'Strategic planning failed.',
            default => 'Legal reasoning operation failed.',
        };
    }
}
