<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TextractJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'drive_file_id',
        'drive_file_name',
        'case_id',
        's3_key',
        'job_id',
        'status',
        'error',
        'metadata',
        'extracted_content',
        'manual_content',
        'manually_edited',
        'content_edited_at',
        'edited_by',
        'embedding_status',
        'graph_sync_status',
        'embedding_synced_at',
        'graph_synced_at',
        // Distributed processing fields
        'batch_id',
        'queue_name',
        'priority',
        'retry_count',
        'worker_id',
        'queued_at',
        'processing_started_at',
        'performance_metrics',
        // OCR engine tracking fields
        'ocr_engine',
        'ocr_routing_metadata',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'metadata' => 'array',
        'manually_edited' => 'boolean',
        'content_edited_at' => 'datetime',
        'embedding_synced_at' => 'datetime',
        'graph_synced_at' => 'datetime',
        // Distributed processing casts
        'performance_metrics' => 'array',
        'queued_at' => 'datetime',
        'processing_started_at' => 'datetime',
        // OCR engine tracking casts
        'ocr_routing_metadata' => 'array',
    ];

    /**
     * Get the user who last edited the content.
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }

    /**
     * Get the case this job belongs to.
     */
    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    /**
     * Get the chunked documents with embeddings.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(TextractDocument::class);
    }

    /**
     * Get the effective content (manual if edited, otherwise extracted).
     */
    public function getEffectiveContentAttribute(): ?string
    {
        return $this->manually_edited && $this->manual_content
            ? $this->manual_content
            : $this->extracted_content;
    }

    /**
     * Check if content is ready for embedding.
     */
    public function isReadyForEmbedding(): bool
    {
        return ! empty($this->effective_content)
            && in_array($this->status, ['completed', 'succeeded'])
            && $this->embedding_status === 'pending';
    }

    /**
     * Check if content is ready for graph sync.
     */
    public function isReadyForGraphSync(): bool
    {
        return ! empty($this->effective_content)
            && in_array($this->status, ['completed', 'succeeded'])
            && $this->graph_sync_status === 'pending';
    }

    /**
     * Mark content as manually edited.
     */
    public function markAsEdited(int $userId): void
    {
        $this->update([
            'manually_edited' => true,
            'content_edited_at' => now(),
            'edited_by' => $userId,
            // Reset sync status when content changes
            'embedding_status' => 'pending',
            'graph_sync_status' => 'pending',
        ]);
    }

    /**
     * Mark embedding as synced.
     */
    public function markEmbeddingSynced(): void
    {
        $this->update([
            'embedding_status' => 'synced',
            'embedding_synced_at' => now(),
        ]);
    }

    /**
     * Mark graph as synced.
     */
    public function markGraphSynced(): void
    {
        $this->update([
            'graph_sync_status' => 'synced',
            'graph_synced_at' => now(),
        ]);
    }

    /**
     * Check if this job needs human review due to OCR quality issues.
     *
     * The needsReview flag is set by CheckOcrQualityStep when quality thresholds
     * are not met (low confidence, low coverage, or too many low-confidence pages).
     */
    public function needsReview(): bool
    {
        return (bool) ($this->metadata['needsReview'] ?? false);
    }

    /**
     * Clear the needsReview flag after human review.
     *
     * This removes the needsReview flag and reviewReasons from metadata,
     * allowing auto-embedding to proceed.
     */
    public function clearNeedsReview(): void
    {
        $metadata = $this->metadata ?? [];
        unset($metadata['needsReview'], $metadata['reviewReasons']);

        $this->update(['metadata' => $metadata]);
    }

    /**
     * Get the reasons why this job needs review.
     *
     * @return array<string> List of review reasons
     */
    public function getReviewReasons(): array
    {
        return $this->metadata['reviewReasons'] ?? [];
    }

    /**
     * Boot the model with event listeners.
     */
    protected static function booted(): void
    {
        // When manual content is updated, reset sync statuses
        static::updating(function ($job) {
            if ($job->isDirty('manual_content') && $job->manually_edited) {
                $job->embedding_status = 'pending';
                $job->graph_sync_status = 'pending';
                $job->embedding_synced_at = null;
                $job->graph_synced_at = null;
            }
        });

        // After content changes, dispatch sync jobs automatically
        static::updated(function ($job) {
            // If embeddings failed, block graph sync
            if ($job->wasChanged('embedding_status') && $job->embedding_status === 'failed') {
                if ($job->graph_sync_status === 'pending') {
                    $job->updateQuietly([
                        'graph_sync_status' => 'blocked',
                    ]);

                    \Illuminate\Support\Facades\Log::info('Graph sync blocked due to embedding failure', [
                        'job_id' => $job->id,
                    ]);
                }
            }

            // Detect content changes (manual or extracted)
            $contentChanged = $job->wasChanged(['manual_content', 'extracted_content']);

            if ($contentChanged && in_array($job->status, ['completed', 'succeeded'])) {
                // Only auto-sync if enabled in config
                $autoSync = config('textract.auto_sync', true);

                if ($autoSync && ! empty($job->effective_content)) {
                    // Dispatch embedding regeneration job
                    \App\Jobs\RegenerateTextractEmbeddings::dispatch($job->id);

                    // Graph sync will be triggered AFTER embeddings complete.
                    // See: RegenerateTextractEmbeddings::handle() chains SyncTextractToGraph with 5s delay.
                    // DO NOT dispatch graph sync here - it will always skip because embeddings aren't ready.
                    \Illuminate\Support\Facades\Log::info('Auto-dispatched embedding regeneration for TextractJob', [
                        'job_id' => $job->id,
                        'trigger' => 'content_updated',
                        'note' => 'Graph sync will chain after embeddings complete',
                    ]);
                }
            }
        });

        // When a job is deleted, clean up related documents
        static::deleting(function ($job) {
            $job->documents()->delete();
        });
    }
}
