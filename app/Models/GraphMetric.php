<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class GraphMetric extends Model
{
    protected $fillable = [
        'metric_type',
        'analyzed_at',
        'payload',
        'node_count',
        'relationship_count',
        'execution_time',
        'notes',
    ];

    protected $casts = [
        'analyzed_at' => 'datetime',
        'payload' => 'array',
        'node_count' => 'integer',
        'relationship_count' => 'integer',
        'execution_time' => 'float',
    ];

    // Metric types
    const TYPE_PAGERANK = 'pagerank';

    const TYPE_CLUSTERS = 'clusters';

    const TYPE_NETWORK_STATS = 'network_stats';

    const TYPE_CITATION_ANALYSIS = 'citation_analysis';

    /**
     * Scope to filter by metric type
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('metric_type', $type);
    }

    /**
     * Scope to get recent metrics
     */
    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('analyzed_at', '>=', now()->subDays($days));
    }

    /**
     * Scope to get latest metric of each type
     */
    public function scopeLatestOfEachType(Builder $query): Builder
    {
        return $query->whereIn('id', function ($subQuery) {
            $subQuery->selectRaw('MAX(id)')
                ->from('graph_metrics')
                ->groupBy('metric_type');
        });
    }

    /**
     * Get latest metric by type
     */
    public static function getLatest(string $type): ?self
    {
        return static::query()
            ->where('metric_type', $type)
            ->orderBy('analyzed_at', 'desc')
            ->first();
    }

    /**
     * Get all latest metrics (one per type)
     */
    public static function getLatestAll(): array
    {
        return static::query()
            ->latestOfEachType()
            ->orderBy('analyzed_at', 'desc')
            ->get()
            ->keyBy('metric_type')
            ->all();
    }

    /**
     * Get metric history for a specific type
     */
    public static function getHistory(string $type, int $days = 30): array
    {
        return static::query()
            ->where('metric_type', $type)
            ->recent($days)
            ->orderBy('analyzed_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Get summary statistics
     */
    public static function getSummary(int $days = 30): array
    {
        $metrics = static::query()->recent($days)->get();

        $summary = [];
        foreach ([self::TYPE_PAGERANK, self::TYPE_CLUSTERS, self::TYPE_NETWORK_STATS, self::TYPE_CITATION_ANALYSIS] as $type) {
            $typeMetrics = $metrics->where('metric_type', $type);

            $summary[$type] = [
                'count' => $typeMetrics->count(),
                'latest' => $typeMetrics->sortByDesc('analyzed_at')->first(),
                'avg_execution_time' => $typeMetrics->avg('execution_time'),
                'avg_node_count' => $typeMetrics->avg('node_count'),
                'avg_relationship_count' => $typeMetrics->avg('relationship_count'),
            ];
        }

        return $summary;
    }
}
