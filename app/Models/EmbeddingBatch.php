<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EmbeddingBatch extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'source_type',
        'total_items',
        'processed_items',
        'failed_items',
        'status',
        'embedding_model',
        'item_ids',
        'configuration',
        'tokens_used',
        'cost',
        'started_at',
        'completed_at',
        'error',
    ];

    protected $casts = [
        'item_ids' => 'array',
        'configuration' => 'array',
        'total_items' => 'integer',
        'processed_items' => 'integer',
        'failed_items' => 'integer',
        'tokens_used' => 'integer',
        'cost' => 'decimal:8',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    /**
     * Get progress percentage
     */
    public function getProgressAttribute(): float
    {
        if ($this->total_items === 0) {
            return 0;
        }

        return round(($this->processed_items / $this->total_items) * 100, 1);
    }

    /**
     * Increment processed count
     */
    public function incrementProcessed(int $tokens = 0, bool $failed = false): void
    {
        $this->increment('processed_items');
        $this->increment('tokens_used', $tokens);

        // Calculate cost: $0.00002 per 1K tokens for text-embedding-3-small
        $additionalCost = ($tokens / 1000) * 0.00002;
        $this->increment('cost', $additionalCost);

        if ($failed) {
            $this->increment('failed_items');
        }

        // Check if batch is complete
        if ($this->fresh()->processed_items >= $this->total_items) {
            $this->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }
    }

    /**
     * Mark as processing
     */
    public function markProcessing(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    /**
     * Mark batch as failed with error message
     */
    public function markFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error' => $error,
            'completed_at' => now(),
        ]);
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}
