<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BenchmarkRun extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'benchmark_class',
        'benchmark_name',
        'description',
        'git_commit_hash',
        'git_branch',
        'git_dirty',
        'started_at',
        'completed_at',
        'duration_ms',
        'status',
        'config',
        'metrics',
        'details',
        'php_version',
        'laravel_version',
        'system_info',
        'error_message',
        'error_trace',
    ];

    protected $casts = [
        'git_dirty' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'config' => 'array',
        'metrics' => 'array',
        'details' => 'array',
        'system_info' => 'array',
    ];

    /**
     * Boot the model to auto-generate ULID
     */
    protected static function booted()
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) \Illuminate\Support\Str::ulid();
            }
        });
    }

    /**
     * Scopes
     */
    public function scopeForBenchmark($query, string $benchmarkClass)
    {
        return $query->where('benchmark_class', $benchmarkClass);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeForCommit($query, string $commitHash)
    {
        return $query->where('git_commit_hash', $commitHash);
    }

    public function scopeLatest($query)
    {
        return $query->orderBy('started_at', 'desc');
    }

    /**
     * Get the metric value by key
     */
    public function getMetric(string $key, $default = null)
    {
        return data_get($this->metrics, $key, $default);
    }

    /**
     * Check if the run was successful
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the run failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if the run is still running
     */
    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    /**
     * Get formatted duration
     */
    public function getFormattedDuration(): string
    {
        if (! $this->duration_ms) {
            return 'N/A';
        }

        if ($this->duration_ms < 1000) {
            return $this->duration_ms.'ms';
        }

        return number_format($this->duration_ms / 1000, 2).'s';
    }
}
