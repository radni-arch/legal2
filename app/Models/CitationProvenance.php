<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Citation Provenance Model
 *
 * Tracks the source, verification, and metadata of legal citations used in AI reasoning.
 * Provides audit trail for legal authorities referenced by AI agents.
 *
 * Sprint 1 User Story 1.1: Database Schema for Reasoning Traces
 *
 * @property int $id
 * @property string $citation_id UUID
 * @property string|null $trace_id
 * @property string $citation_text
 * @property string|null $source_type
 * @property string|null $source_identifier
 * @property string|null $source_url
 * @property array|null $citation_metadata
 * @property string|null $verification_status
 * @property float|null $confidence_score
 * @property string|null $verification_notes
 * @property \Illuminate\Support\Carbon|null $verified_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class CitationProvenance extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'citation_provenance';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'citation_id',
        'trace_id',
        'citation_text',
        'source_type',
        'source_identifier',
        'source_url',
        'citation_metadata',
        'verification_status',
        'confidence_score',
        'verification_notes',
        'verified_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'citation_metadata' => 'array',
        'confidence_score' => 'float',
        'verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<string>
     */
    protected $hidden = [];

    /**
     * Boot function from Laravel.
     *
     * Automatically generate UUID for citation_id on creation.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->citation_id)) {
                $model->citation_id = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the reasoning trace associated with this citation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function trace()
    {
        return $this->belongsTo(AiReasoningTrace::class, 'trace_id', 'trace_id');
    }

    /**
     * Mark citation as verified.
     */
    public function markAsVerified(?string $notes = null): bool
    {
        return $this->update([
            'verification_status' => 'verified',
            'verified_at' => now(),
            'verification_notes' => $notes,
        ]);
    }

    /**
     * Mark citation as failed verification.
     */
    public function markAsFailed(?string $notes = null): bool
    {
        return $this->update([
            'verification_status' => 'failed',
            'verified_at' => now(),
            'verification_notes' => $notes,
        ]);
    }

    /**
     * Scope query to verified citations.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeVerified($query)
    {
        return $query->where('verification_status', 'verified');
    }

    /**
     * Scope query to pending citations.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePending($query)
    {
        return $query->where('verification_status', 'pending');
    }

    /**
     * Scope query to citations from a specific source type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFromSource($query, string $sourceType)
    {
        return $query->where('source_type', $sourceType);
    }
}
