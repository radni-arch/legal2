<?php

namespace App\Exceptions;

/**
 * Exception for partial OpenAI operation failures
 *
 * Used when a multipart operation (streaming, batch processing, etc.)
 * partially succeeds but encounters an error before completion.
 *
 * This allows callers to:
 * - Access successfully completed parts
 * - Decide whether to retry from last successful point
 * - Use partial results if acceptable
 */
class PartialOpenAIFailureException extends OpenAIException
{
    public const PARTIAL_STREAM_FAILURE = 5010;

    public const PARTIAL_BATCH_FAILURE = 5011;

    public const PARTIAL_UPLOAD_FAILURE = 5012;

    /**
     * Successfully completed parts before failure
     */
    protected array $successfulParts = [];

    /**
     * Metadata about the partial failure
     */
    protected array $failureMetadata = [];

    /**
     * Create a new partial failure exception
     *
     * @param  string  $message  Error message
     * @param  int  $code  Error code
     * @param  array  $successfulParts  Successfully completed parts
     * @param  array  $failureMetadata  Additional failure metadata
     * @param  \Throwable|null  $previous  Previous exception
     */
    public function __construct(
        string $message,
        int $code,
        array $successfulParts = [],
        array $failureMetadata = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->successfulParts = $successfulParts;
        $this->failureMetadata = $failureMetadata;
    }

    /**
     * Get successfully completed parts
     *
     * @return array Array of successfully completed parts (chunks, uploads, etc.)
     */
    public function getSuccessfulParts(): array
    {
        return $this->successfulParts;
    }

    /**
     * Get failure metadata
     *
     * @return array Metadata about the failure (chunk number, bytes transferred, etc.)
     */
    public function getFailureMetadata(): array
    {
        return $this->failureMetadata;
    }

    /**
     * Check if any parts were successful
     *
     * @return bool True if at least one part succeeded
     */
    public function hasSuccessfulParts(): bool
    {
        return ! empty($this->successfulParts);
    }

    /**
     * Get count of successful parts
     *
     * @return int Number of successfully completed parts
     */
    public function getSuccessfulPartCount(): int
    {
        return count($this->successfulParts);
    }

    /**
     * Get user-friendly error message
     */
    public function getUserMessage(): string
    {
        $partCount = $this->getSuccessfulPartCount();
        $hasPartial = $this->hasSuccessfulParts();

        return match ($this->getCode()) {
            self::PARTIAL_STREAM_FAILURE => $hasPartial
                ? "OpenAI streaming partially completed ({$partCount} chunks received) before failure."
                : 'OpenAI streaming failed before receiving any data.',
            self::PARTIAL_BATCH_FAILURE => $hasPartial
                ? "Batch operation partially completed ({$partCount} items processed) before failure."
                : 'Batch operation failed before processing any items.',
            self::PARTIAL_UPLOAD_FAILURE => $hasPartial
                ? 'File upload partially completed before failure.'
                : 'File upload failed at start.',
            default => $hasPartial
                ? "OpenAI operation partially completed ({$partCount} parts) before failure."
                : 'OpenAI operation failed before completing any parts.',
        };
    }

    /**
     * Report the exception with partial success information
     */
    public function report(): bool
    {
        logger()->error('OpenAI partial failure occurred', [
            'exception' => static::class,
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'successful_parts_count' => $this->getSuccessfulPartCount(),
            'has_successful_parts' => $this->hasSuccessfulParts(),
            'failure_metadata' => $this->failureMetadata,
        ]);

        return true;
    }
}
