<?php

namespace App\Exceptions;

use Exception;

class EoglasnaException extends Exception
{
    public const API_CONNECTION_FAILED = 9001;

    public const API_REQUEST_FAILED = 9002;

    public const COURT_NOT_FOUND = 9003;

    public const INVALID_RESPONSE = 9004;

    public const MONITORING_FAILED = 9005;

    public const SYNC_FAILED = 9006;

    public const RATE_LIMIT_EXCEEDED = 9007;

    public const UNEXPECTED_ERROR = 9999;

    /**
     * Report the exception
     */
    public function report(): bool
    {
        logger()->error('Eoglasna exception occurred', [
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
            self::API_CONNECTION_FAILED => 'Failed to connect to Eoglasna API.',
            self::API_REQUEST_FAILED => 'Eoglasna API request failed.',
            self::COURT_NOT_FOUND => 'Court not found in Eoglasna registry.',
            self::INVALID_RESPONSE => 'Eoglasna API returned invalid response.',
            self::MONITORING_FAILED => 'Failed to monitor Eoglasna notices.',
            self::SYNC_FAILED => 'Failed to sync data from Eoglasna.',
            self::RATE_LIMIT_EXCEEDED => 'Eoglasna API rate limit exceeded.',
            default => 'Eoglasna service operation failed.',
        };
    }
}
