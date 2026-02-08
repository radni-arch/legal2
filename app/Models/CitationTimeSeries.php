<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Citation Time Series Model
 *
 * Tracks citation counts over time for court decisions.
 * Provides trend analysis (increase/decrease) by comparing with previous periods.
 */
class CitationTimeSeries extends Model
{
    use HasFactory;

    protected $table = 'citation_time_series';

    protected $fillable = [
        'decision_id',
        'period_start',
        'period_end',
        'period_type',
        'citation_count',
        'incoming_citations',
        'outgoing_citations',
        'avg_citation_importance',
        'citing_courts',
        'top_citing_decisions',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'citing_courts' => 'array',
        'top_citing_decisions' => 'array',
        'avg_citation_importance' => 'decimal:3',
    ];

    /**
     * Get the court decision that owns this time series entry
     */
    public function decision(): BelongsTo
    {
        return $this->belongsTo(CourtDecision::class, 'decision_id');
    }

    /**
     * Get citation trend (increase/decrease from previous period).
     *
     * @return string Trend indicator: 'new', 'up_{count}', 'down_{count}', or 'stable'
     */
    public function getTrendAttribute(): string
    {
        $previous = self::where('decision_id', $this->decision_id)
            ->where('period_type', $this->period_type)
            ->where('period_start', '<', $this->period_start)
            ->orderByDesc('period_start')
            ->first();

        if (! $previous) {
            return 'new';
        }

        $change = $this->citation_count - $previous->citation_count;

        if ($change > 0) {
            return "up_{$change}";
        } elseif ($change < 0) {
            return 'down_'.abs($change);
        } else {
            return 'stable';
        }
    }

    /**
     * Scope to get time series for a specific decision
     */
    public function scopeForDecision($query, string $decisionId)
    {
        return $query->where('decision_id', $decisionId);
    }

    /**
     * Scope to get time series for a specific period type
     */
    public function scopeOfPeriodType($query, string $periodType)
    {
        return $query->where('period_type', $periodType);
    }

    /**
     * Scope to get time series within a date range
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->where('period_start', '>=', $startDate)
            ->where('period_end', '<=', $endDate);
    }

    /**
     * Scope to get recent time series (last N periods)
     */
    public function scopeRecent($query, int $count = 6)
    {
        return $query->orderByDesc('period_start')->limit($count);
    }
}
