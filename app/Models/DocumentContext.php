<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentContext extends Model
{
    use HasFactory, HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'generation_run_id',
        'context_type',
        'raw_input',
        'assembled_context',
        'case_ids',
        'evidence_ids',
        'decision_ids',
        'law_ids',
        'created_at',
    ];

    protected $casts = [
        'case_ids' => 'array',
        'evidence_ids' => 'array',
        'decision_ids' => 'array',
        'law_ids' => 'array',
        'created_at' => 'datetime',
    ];

    public function generationRun(): BelongsTo
    {
        return $this->belongsTo(DocumentGenerationRun::class, 'generation_run_id');
    }
}
