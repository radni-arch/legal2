<?php

namespace App\Logging;

use Illuminate\Support\Str;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Monolog processor that adds correlation ID to all log records
 *
 * Correlation IDs enable tracing full request/agent execution flows
 * across distributed systems and async operations.
 *
 * Sprint 1.7: Observability Setup
 */
class CorrelationIdProcessor implements ProcessorInterface
{
    /**
     * Correlation ID key in log context
     */
    public const CORRELATION_ID_KEY = 'correlation_id';

    /**
     * Header name for correlation ID
     */
    public const HEADER_NAME = 'X-Correlation-ID';

    /**
     * Thread-local storage for correlation ID
     */
    protected static ?string $correlationId = null;

    /**
     * Additional context to merge into every log record
     */
    protected array $additionalContext = [];

    public function __construct(array $additionalContext = [])
    {
        $this->additionalContext = $additionalContext;
    }

    /**
     * Invoked by Monolog for each log record
     */
    public function __invoke(LogRecord $record): LogRecord
    {
        // Get or generate correlation ID
        $correlationId = $this->getCorrelationId();

        // Merge correlation ID and additional context into record
        $context = array_merge(
            $record->context,
            [self::CORRELATION_ID_KEY => $correlationId],
            $this->additionalContext
        );

        // Return new record with updated context
        return $record->with(context: $context);
    }

    /**
     * Get current correlation ID or generate new one
     */
    public static function getCorrelationId(): string
    {
        if (self::$correlationId !== null) {
            return self::$correlationId;
        }

        // Try to get from request header
        if (app()->has('request')) {
            $request = request();
            $headerValue = $request->header(self::HEADER_NAME);

            if ($headerValue) {
                return self::setCorrelationId($headerValue);
            }
        }

        // Generate new correlation ID
        return self::setCorrelationId(self::generateCorrelationId());
    }

    /**
     * Set correlation ID for current context
     */
    public static function setCorrelationId(string $correlationId): string
    {
        self::$correlationId = $correlationId;

        return $correlationId;
    }

    /**
     * Reset correlation ID (useful for testing or new request context)
     */
    public static function resetCorrelationId(): void
    {
        self::$correlationId = null;
    }

    /**
     * Generate a new correlation ID
     */
    protected static function generateCorrelationId(): string
    {
        return (string) Str::uuid();
    }

    /**
     * Get additional context that will be merged into every log
     */
    public function getAdditionalContext(): array
    {
        return $this->additionalContext;
    }

    /**
     * Set additional context
     */
    public function setAdditionalContext(array $context): void
    {
        $this->additionalContext = $context;
    }

    /**
     * Merge additional context
     */
    public function mergeAdditionalContext(array $context): void
    {
        $this->additionalContext = array_merge($this->additionalContext, $context);
    }
}
