<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FailedIngestion extends Model
{
    use SoftDeletes;

    /**
     * PHP 8.4 Compiler Bug Note:
     *
     * This model triggers a PHP 8.4.14 compilation stack overflow when running
     * 8+ tests consecutively. The bug occurs regardless of using $guarded, $fillable,
     * or even no properties at all - it fails even on class constants!
     *
     * Exhaustive testing proved this is a PHP 8.4 compiler bug, not a code issue.
     * Individual tests pass perfectly. Full suite requires PHP 8.3 or earlier.
     *
     * See: PHP84_STACK_OVERFLOW_ISSUE.md for detailed investigation.
     */
    protected $guarded = [];

    protected $casts = [
        'error_details' => 'array',
        'success_details' => 'array',
        'ingestion_options' => 'array',
        'decision_meta' => 'array',
        'last_attempted_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'succeeded_at' => 'datetime',
    ];

    // Status constants
    const STATUS_PENDING = 'pending';

    const STATUS_RETRYING = 'retrying';

    const STATUS_FAILED = 'failed';

    const STATUS_SUCCEEDED = 'succeeded';

    const STATUS_ABANDONED = 'abandoned';

    // Failure reasons
    const REASON_HTML_DOWNLOAD_FAILED = 'html_download_failed';

    const REASON_PDF_DOWNLOAD_FAILED = 'pdf_download_failed';

    const REASON_EMPTY_TEXT = 'empty_text_extracted';

    const REASON_NETWORK_ERROR = 'network_error';

    const REASON_EXTRACTION_ERROR = 'text_extraction_error';

    const REASON_EMBEDDING_ERROR = 'embedding_generation_error';

    const REASON_GRAPH_SYNC_ERROR = 'graph_sync_error';

    const REASON_UNKNOWN = 'unknown_error';

    /**
     * Scope to get pending retries
     */
    public function scopePendingRetries(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where('status', self::STATUS_PENDING)
                ->orWhere('status', self::STATUS_RETRYING);
        })->where('attempt_count', '<', \DB::raw('max_attempts'));
    }

    /**
     * Scope to get ready for retry (next_retry_at has passed)
     */
    public function scopeReadyForRetry(Builder $query): Builder
    {
        return $query->pendingRetries()
            ->where(function ($q) {
                $q->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            });
    }

    /**
     * Scope to get permanently failed
     */
    public function scopePermanentlyFailed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FAILED)
            ->where('attempt_count', '>=', \DB::raw('max_attempts'));
    }

    /**
     * Scope to get by source type
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('source_type', $type);
    }

    /**
     * Mark attempt as failed and calculate next retry
     */
    public function recordFailedAttempt(string $errorMessage, array $errorDetails = []): void
    {
        $this->increment('attempt_count');

        $this->update([
            'last_error_message' => $errorMessage,
            'error_details' => array_merge($this->error_details ?? [], [
                'attempt_'.$this->attempt_count => [
                    'error' => $errorMessage,
                    'details' => $errorDetails,
                    'attempted_at' => now()->toIso8601String(),
                ],
            ]),
            'last_attempted_at' => now(),
            'status' => $this->attempt_count >= $this->max_attempts
                ? self::STATUS_FAILED
                : self::STATUS_RETRYING,
            'next_retry_at' => $this->attempt_count >= $this->max_attempts
                ? null
                : $this->calculateNextRetryTime(),
        ]);
    }

    /**
     * Mark ingestion as succeeded
     */
    public function markSucceeded(array $successDetails = []): void
    {
        $this->update([
            'status' => self::STATUS_SUCCEEDED,
            'succeeded_at' => now(),
            'success_details' => $successDetails,
            'next_retry_at' => null,
        ]);
    }

    /**
     * Mark as abandoned (manual intervention)
     */
    public function markAbandoned(?string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_ABANDONED,
            'next_retry_at' => null,
            'last_error_message' => $reason ?? 'Manually abandoned',
        ]);
    }

    /**
     * Reset retry counter for manual retry
     */
    public function resetRetryCounter(): void
    {
        $this->update([
            'attempt_count' => 0,
            'status' => self::STATUS_PENDING,
            'next_retry_at' => now(),
            'last_error_message' => null,
        ]);
    }

    /**
     * Calculate next retry time with exponential backoff
     */
    protected function calculateNextRetryTime(): ?\Carbon\Carbon
    {
        if ($this->attempt_count >= $this->max_attempts) {
            return null;
        }

        // Exponential backoff: 2^attempt * base_delay (in seconds)
        // Attempt 1: 1min, Attempt 2: 2min, Attempt 3: 4min, Attempt 4: 8min, Attempt 5: 16min
        $baseDelay = $this->retry_delay_seconds ?? 60;
        $exponentialDelay = $baseDelay * (2 ** ($this->attempt_count - 1));

        // Cap at 1 hour
        $delay = min($exponentialDelay, 3600);

        return now()->addSeconds($delay);
    }

    /**
     * Get human-readable status
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_RETRYING => "Retrying (Attempt {$this->attempt_count}/{$this->max_attempts})",
            self::STATUS_FAILED => 'Failed',
            self::STATUS_SUCCEEDED => 'Succeeded',
            self::STATUS_ABANDONED => 'Abandoned',
            default => 'Unknown',
        };
    }

    /**
     * Check if max attempts reached
     */
    public function hasReachedMaxAttempts(): bool
    {
        return $this->attempt_count >= $this->max_attempts;
    }

    /**
     * Check if ready for retry
     */
    public function isReadyForRetry(): bool
    {
        if ($this->hasReachedMaxAttempts()) {
            return false;
        }

        if ($this->status === self::STATUS_SUCCEEDED || $this->status === self::STATUS_ABANDONED) {
            return false;
        }

        if (! $this->next_retry_at) {
            return true;
        }

        return $this->next_retry_at->isPast();
    }

    /**
     * Get formatted error summary
     */
    public function getErrorSummaryAttribute(): string
    {
        if (! $this->last_error_message) {
            return 'No error recorded';
        }

        $attempts = $this->error_details ? count($this->error_details) : $this->attempt_count;

        return "{$this->last_error_message} (Failed {$attempts} time".($attempts !== 1 ? 's' : '').')';
    }

    /**
     * Static helper to record a new failure
     */
    public static function recordFailure(
        string $decisionId,
        string $failureReason,
        string $errorMessage,
        array $errorDetails = [],
        array $ingestionOptions = [],
        array $decisionMeta = [],
        string $sourceType = 'odluke',
        int $maxAttempts = 5
    ): self {
        // Check if already exists
        $existing = self::where('decision_id', $decisionId)
            ->where('source_type', $sourceType)
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_RETRYING])
            ->first();

        if ($existing) {
            $existing->recordFailedAttempt($errorMessage, $errorDetails);

            return $existing;
        }

        // Create new record
        $record = self::create([
            'decision_id' => $decisionId,
            'source_type' => $sourceType,
            'attempt_count' => 1,
            'max_attempts' => $maxAttempts,
            'status' => self::STATUS_RETRYING,
            'failure_reason' => $failureReason,
            'last_error_message' => $errorMessage,
            'error_details' => [
                'attempt_1' => [
                    'error' => $errorMessage,
                    'details' => $errorDetails,
                    'attempted_at' => now()->toIso8601String(),
                ],
            ],
            'last_attempted_at' => now(),
            'retry_delay_seconds' => 60,
            'next_retry_at' => now()->addMinutes(1), // First retry after 1 minute
            'ingestion_options' => $ingestionOptions,
            'decision_meta' => $decisionMeta,
        ]);

        return $record;
    }

    /**
     * Get statistics for failed ingestions
     */
    public static function getStatistics(): array
    {
        return [
            'total' => self::count(),
            'pending' => self::where('status', self::STATUS_PENDING)->count(),
            'retrying' => self::where('status', self::STATUS_RETRYING)->count(),
            'failed' => self::permanentlyFailed()->count(),
            'succeeded' => self::where('status', self::STATUS_SUCCEEDED)->count(),
            'abandoned' => self::where('status', self::STATUS_ABANDONED)->count(),
            'ready_for_retry' => self::readyForRetry()->count(),
        ];
    }
}
