<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DecisionImpactMetric extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'decision_id',
        'citation_count',
        'direct_citations',
        'indirect_citations',
        'authority_score',
        'precedent_strength',
        'influence_score',
        'citations_over_time',
        'citation_velocity',
        'temporal_decay_factor',
        'jurisdictional_spread',
        'jurisdictions_count',
        'citing_courts',
        'higher_court_citations',
        'same_court_citations',
        'lower_court_citations',
        'influential_cases',
        'impact_summary',
        'last_calculated_at',
        'last_citation_at',
    ];

    protected $casts = [
        'citations_over_time' => 'array',
        'jurisdictional_spread' => 'array',
        'citing_courts' => 'array',
        'influential_cases' => 'array',
        'authority_score' => 'float',
        'precedent_strength' => 'float',
        'influence_score' => 'float',
        'citation_velocity' => 'float',
        'temporal_decay_factor' => 'float',
        'last_calculated_at' => 'datetime',
        'last_citation_at' => 'datetime',
    ];

    // Relations
    public function decision(): BelongsTo
    {
        return $this->belongsTo(CourtDecision::class, 'decision_id');
    }

    // Scopes
    public function scopeHighAuthority($query, float $threshold = 0.7)
    {
        return $query->where('authority_score', '>=', $threshold);
    }

    public function scopeRecentlyUpdated($query, int $days = 7)
    {
        return $query->where('last_calculated_at', '>=', now()->subDays($days));
    }
}
