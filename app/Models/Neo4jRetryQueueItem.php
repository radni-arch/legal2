<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Neo4jRetryQueueItem extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'neo4j_retry_queue';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'operation_type',
        'payload',
        'entity_type',
        'entity_id',
        'attempts',
        'max_attempts',
        'last_error',
        'status',
        'failed_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'payload' => 'array',
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'failed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Scope to get pending items.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get retrying items.
     */
    public function scopeRetrying($query)
    {
        return $query->where('status', 'retrying');
    }

    /**
     * Scope to get failed items.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope to get completed items.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to get items by operation type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('operation_type', $type);
    }

    /**
     * Scope to get items for a specific entity.
     */
    public function scopeForEntity($query, string $entityType, int $entityId)
    {
        return $query->where('entity_type', $entityType)
            ->where('entity_id', $entityId);
    }

    /**
     * Check if the item has reached max attempts.
     */
    public function hasReachedMaxAttempts(): bool
    {
        return $this->attempts >= $this->max_attempts;
    }

    /**
     * Increment the attempts counter.
     */
    public function incrementAttempts(): self
    {
        $this->increment('attempts');

        return $this->fresh();
    }

    /**
     * Mark as failed.
     */
    public function markAsFailed(string $error): self
    {
        $this->update([
            'status' => 'failed',
            'last_error' => $error,
            'failed_at' => now(),
        ]);

        return $this;
    }

    /**
     * Mark as completed.
     */
    public function markAsCompleted(): self
    {
        $this->update([
            'status' => 'completed',
            'last_error' => null,
        ]);

        return $this;
    }

    /**
     * Mark as retrying.
     */
    public function markAsRetrying(string $error): self
    {
        $this->update([
            'status' => 'retrying',
            'last_error' => $error,
        ]);

        return $this;
    }

    /**
     * Reset for retry.
     */
    public function resetForRetry(): self
    {
        $this->update([
            'status' => 'pending',
            'attempts' => 0,
            'last_error' => null,
            'failed_at' => null,
        ]);

        return $this;
    }
}
