<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TextractBatch extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'batch_type',
        'source_identifier',
        'total_files',
        'processed_files',
        'failed_files',
        'status',
        'configuration',
        'statistics',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'configuration' => 'array',
        'statistics' => 'array',
        'total_files' => 'integer',
        'processed_files' => 'integer',
        'failed_files' => 'integer',
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
            if (empty($model->batch_type)) {
                $model->batch_type = 'default';
            }
        });
    }

    /**
     * Get jobs in this batch
     */
    public function jobs()
    {
        return $this->hasMany(TextractJob::class, 'batch_id');
    }

    /**
     * Get progress percentage
     */
    public function getProgressAttribute(): float
    {
        if ($this->total_files === 0) {
            return 0;
        }

        return round(($this->processed_files / $this->total_files) * 100, 1);
    }

    /**
     * Get success rate
     */
    public function getSuccessRateAttribute(): float
    {
        if ($this->processed_files === 0) {
            return 0;
        }
        $successful = $this->processed_files - $this->failed_files;

        return round(($successful / $this->processed_files) * 100, 1);
    }

    /**
     * Increment processed count
     */
    public function incrementProcessed(bool $failed = false): void
    {
        $this->increment('processed_files');
        if ($failed) {
            $this->increment('failed_files');
        }

        // Check if batch is complete
        if ($this->fresh()->processed_files >= $this->total_files) {
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
     * Mark as failed
     */
    public function markFailed(?string $reason = null): void
    {
        $statistics = $this->statistics ?? [];
        if ($reason) {
            $statistics['failure_reason'] = $reason;
        }

        $this->update([
            'status' => 'failed',
            'statistics' => $statistics,
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

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
