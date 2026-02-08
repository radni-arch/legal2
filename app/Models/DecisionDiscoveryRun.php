<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DecisionDiscoveryRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'started_at',
        'completed_at',
        'topics_generated',
        'decisions_found',
        'decisions_evaluated',
        'decisions_ingested',
        'duration_seconds',
        'topic_filter',
        'topics',
        'errors',
        'statistics',
        'status',
        'error_message',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'duration_seconds' => 'decimal:2',
        'topics' => 'array',
        'errors' => 'array',
        'statistics' => 'array',
    ];

    /**
     * Get duration in seconds for this run.
     */
    public function duration(): ?int
    {
        if (! $this->completed_at) {
            return null;
        }

        return $this->started_at->diffInSeconds($this->completed_at);
    }

    /**
     * Check if run is currently running.
     */
    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    /**
     * Check if run completed successfully.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if run failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Scope: Only completed runs.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope: Only failed runs.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope: Only running runs.
     */
    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }

    /**
     * Scope: Recent runs (last N days).
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>', now()->subDays($days));
    }

    /**
     * Get success rate percentage.
     */
    public static function getSuccessRate(int $days = 30): float
    {
        $total = self::recent($days)->count();

        if ($total === 0) {
            return 0.0;
        }

        $completed = self::recent($days)->completed()->count();

        return round(($completed / $total) * 100, 2);
    }

    /**
     * Get average duration for successful runs (in seconds).
     */
    public static function getAverageDuration(int $days = 30): float
    {
        $runs = self::recent($days)->completed()->get();

        if ($runs->isEmpty()) {
            return 0.0;
        }

        $totalDuration = $runs->sum(function ($run) {
            return $run->duration() ?? 0;
        });

        return round($totalDuration / $runs->count(), 2);
    }

    /**
     * Get total decisions discovered in period.
     */
    public static function getTotalDiscovered(int $days = 30): int
    {
        return self::recent($days)
            ->completed()
            ->sum('decisions_evaluated') ?? 0;
    }

    public static function getTotalIngested(int $days = 30): int
    {
        return self::recent($days)
            ->completed()
            ->sum('decisions_ingested') ?? 0;
    }

    /**
     * Get statistics summary for a period.
     */
    public static function getStatistics(int $days = 30): array
    {
        return [
            'total_runs' => self::recent($days)->count(),
            'completed_runs' => self::recent($days)->completed()->count(),
            'failed_runs' => self::recent($days)->failed()->count(),
            'running_runs' => self::recent($days)->running()->count(),
            'success_rate' => self::getSuccessRate($days),
            'average_duration' => self::getAverageDuration($days),
            'total_discovered' => self::getTotalDiscovered($days),
            'total_ingested' => self::getTotalIngested($days),
        ];
    }
}
