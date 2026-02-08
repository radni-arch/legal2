<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseFeature extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'case_id',
        'case_type',
        'case_category',
        'complexity_level',
        'complexity_score',
        'document_count',
        'precedent_count',
        'party_count',
        'claim_count',
        'client_type',
        'opponent_type',
        'legal_issues',
        'applicable_laws',
        'jurisdiction_factors',
        'days_since_filing',
        'estimated_duration_days',
        'evidence_types',
        'evidence_strength_score',
        'claim_amount',
        'claim_amount_category',
        'motion_count',
        'hearing_count',
        'discovery_completed',
        'embedding_provider',
        'embedding_model',
        'embedding_dimensions',
        'embedding',
        'embedding_norm',
        'features_extracted_at',
        'extraction_version',
    ];

    protected $casts = [
        'legal_issues' => 'array',
        'applicable_laws' => 'array',
        'jurisdiction_factors' => 'array',
        'evidence_types' => 'array',
        'embedding' => 'array',
        'complexity_score' => 'float',
        'evidence_strength_score' => 'float',
        'embedding_norm' => 'float',
        'claim_amount' => 'decimal:2',
        'discovery_completed' => 'boolean',
        'features_extracted_at' => 'datetime',
    ];

    // Relations
    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

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
}
