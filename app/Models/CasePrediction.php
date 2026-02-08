<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CasePrediction extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'case_id',
        'prediction_type',
        'features',
        'prediction',
        'confidence',
        'model_version',
        'similar_cases',
        'reasoning',
        'predicted_at',
        'actual_outcome_at',
        'actual_outcome',
        'accuracy_score',
    ];

    protected $casts = [
        'features' => 'array',
        'prediction' => 'array',
        'similar_cases' => 'array',
        'actual_outcome' => 'array',
        'confidence' => 'float',
        'accuracy_score' => 'float',
        'predicted_at' => 'datetime',
        'actual_outcome_at' => 'datetime',
    ];

    // Relations
    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    // Scopes
    public function scopeOfType($query, string $type)
    {
        return $query->where('prediction_type', $type);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('predicted_at', '>=', now()->subDays($days));
    }
}
