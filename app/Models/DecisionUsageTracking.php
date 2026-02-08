<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Decision Usage Tracking Model
 *
 * Sprint 5.4: DecisionDiscoveryAgent Active Learning
 *
 * Tracks when court decisions are actually used in cases (cited, in motions, briefs)
 * to improve DecisionDiscoveryAgent's scoring accuracy through usage-based learning.
 */
class DecisionUsageTracking extends Model
{
    protected $table = 'decision_usage_tracking';

    protected $fillable = [
        'decision_id',
        'used_in_case_id',
        'usage_type',
        'used_at',
    ];

    protected $casts = [
        'used_at' => 'datetime',
    ];

    /**
     * Get the case where this decision was used
     */
    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'used_in_case_id');
    }

    /**
     * Scope to filter by usage type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('usage_type', $type);
    }

    /**
     * Scope to filter by decision ID
     */
    public function scopeForDecision($query, string $decisionId)
    {
        return $query->where('decision_id', $decisionId);
    }

    /**
     * Scope to get usage within date range
     */
    public function scopeUsedBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('used_at', [$startDate, $endDate]);
    }
}
