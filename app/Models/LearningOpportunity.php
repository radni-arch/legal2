<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Learning Opportunity Model
 *
 * Sprint 5.1: Learning Opportunity Detection
 *
 * Stores low-confidence AI outputs that need human review
 * for active learning and model improvement.
 *
 * @property int $id
 * @property string $opportunity_type
 * @property string $source_type
 * @property int $source_id
 * @property array $ai_output
 * @property float $confidence_score
 * @property string|null $uncertainty_reason
 * @property string $status
 * @property array|null $human_label
 * @property int|null $reviewed_by
 * @property \Illuminate\Support\Carbon|null $reviewed_at
 * @property \Illuminate\Support\Carbon|null $incorporated_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class LearningOpportunity extends Model
{
    use HasFactory;

    protected $table = 'learning_opportunities';

    protected $fillable = [
        'opportunity_type',
        'source_type',
        'source_id',
        'ai_output',
        'confidence_score',
        'uncertainty_reason',
        'status',
        'human_label',
        'reviewed_by',
        'reviewed_at',
        'incorporated_at',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected $casts = [
        'ai_output' => 'array',
        'human_label' => 'array',
        'confidence_score' => 'decimal:2',
        'reviewed_at' => 'datetime',
        'incorporated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user who reviewed this opportunity
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Scope: Get pending opportunities
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: Get reviewed opportunities
     */
    public function scopeReviewed($query)
    {
        return $query->where('status', 'reviewed');
    }

    /**
     * Scope: Filter by opportunity type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('opportunity_type', $type);
    }

    /**
     * Scope: Filter by confidence below threshold
     */
    public function scopeConfidenceBelow($query, float $threshold)
    {
        return $query->where('confidence_score', '<', $threshold);
    }

    /**
     * Scope: Filter by confidence above threshold
     */
    public function scopeConfidenceAbove($query, float $threshold)
    {
        return $query->where('confidence_score', '>', $threshold);
    }

    /**
     * Scope: Order by confidence ascending (lowest first)
     */
    public function scopeLowestConfidenceFirst($query)
    {
        return $query->orderBy('confidence_score', 'asc');
    }

    /**
     * Mark this opportunity as reviewed
     */
    public function markAsReviewed(int $userId, array $humanLabel): void
    {
        $this->update([
            'status' => 'reviewed',
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
            'human_label' => $humanLabel,
        ]);
    }
}
