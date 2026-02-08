<?php

namespace App\Exceptions;

use Exception;

class ScraperException extends Exception
{
    public const HTTP_REQUEST_FAILED = 5001;

    public const PARSE_ERROR = 5002;

    public const SCRAPING_FAILED = 5003;

    public const INVALID_URL = 5004;

    public const CONTENT_NOT_FOUND = 5005;

    public const RATE_LIMIT_EXCEEDED = 5006;

    public const UNEXPECTED_ERROR = 5999;

    /**
     * Report the exception
     */
    public function report(): bool
    {
        logger()->error('Scraper exception occurred', [
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
            self::HTTP_REQUEST_FAILED => 'HTTP request failed.',
            self::PARSE_ERROR => 'Failed to parse scraped content.',
            self::SCRAPING_FAILED => 'Web scraping operation failed.',
            self::INVALID_URL => 'Invalid or malformed URL.',
            self::CONTENT_NOT_FOUND => 'Expected content not found on page.',
            self::RATE_LIMIT_EXCEEDED => 'Rate limit exceeded.',
            default => 'Scraping operation failed.',
        };
    }
}
