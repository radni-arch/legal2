<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentIteration extends Model
{
    use HasFactory, HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'generation_run_id',
        'iteration_number',
        'phase',
        'document_version',
        'critic_feedback',
        'scores',
        'weighted_score',
        'improvement_delta',
        'ai_model_used',
        'tokens_used',
        'cost_estimate',
        'created_at',
    ];

    protected $casts = [
        'critic_feedback' => 'array',
        'scores' => 'array',
        'weighted_score' => 'decimal:2',
        'improvement_delta' => 'decimal:2',
        'cost_estimate' => 'decimal:4',
        'created_at' => 'datetime',
    ];

    public function generationRun(): BelongsTo
    {
        return $this->belongsTo(DocumentGenerationRun::class, 'generation_run_id');
    }
}
