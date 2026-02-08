<?php

namespace App\Exceptions;

use Exception;

class OpenAIException extends Exception
{
    public const API_KEY_MISSING = 5001;

    public const REQUEST_FAILED = 5002;

    public const INVALID_MODEL = 5003;

    public const RATE_LIMIT_EXCEEDED = 5004;

    public const TIMEOUT = 5005;

    public const STREAMING_FAILED = 5006;

    public const INVALID_RESPONSE = 5007;

    public const UNEXPECTED_ERROR = 5999;

    /**
     * Report the exception
     */
    public function report(): bool
    {
        logger()->error('OpenAI exception occurred', [
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
            self::API_KEY_MISSING => 'OpenAI API key is not configured.',
            self::REQUEST_FAILED => 'OpenAI API request failed.',
            self::INVALID_MODEL => 'Invalid model specified.',
            self::RATE_LIMIT_EXCEEDED => 'OpenAI rate limit exceeded.',
            self::TIMEOUT => 'OpenAI request timed out.',
            self::STREAMING_FAILED => 'OpenAI streaming connection failed.',
            self::INVALID_RESPONSE => 'Invalid response from OpenAI API.',
            default => 'OpenAI API operation failed.',
        };
    }
}
