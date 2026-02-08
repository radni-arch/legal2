<?php

namespace App\Exceptions;

use Exception;

class MonitoringException extends Exception
{
    public const ALERT_CREATION_FAILED = 6001;

    public const ALERT_STORAGE_FAILED = 6002;

    public const NOTIFICATION_SEND_FAILED = 6003;

    public const EMAIL_SEND_FAILED = 6004;

    public const SLACK_SEND_FAILED = 6005;

    public const CACHE_ACCESS_FAILED = 6006;

    public const ALERT_RETRIEVAL_FAILED = 6007;

    public const RATE_LIMIT_CHECK_FAILED = 6008;

    public const UNEXPECTED_ERROR = 6999;

    /**
     * Report the exception
     */
    public function report(): bool
    {
        logger()->error('Monitoring exception occurred', [
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
            self::ALERT_CREATION_FAILED => 'Failed to create system alert.',
            self::ALERT_STORAGE_FAILED => 'Failed to store alert in cache.',
            self::NOTIFICATION_SEND_FAILED => 'Failed to send alert notification.',
            self::EMAIL_SEND_FAILED => 'Failed to send email notification.',
            self::SLACK_SEND_FAILED => 'Failed to send Slack notification.',
            self::CACHE_ACCESS_FAILED => 'Failed to access alert cache.',
            self::ALERT_RETRIEVAL_FAILED => 'Failed to retrieve alerts.',
            self::RATE_LIMIT_CHECK_FAILED => 'Failed to check alert rate limits.',
            default => 'Monitoring system error occurred.',
        };
    }
}
