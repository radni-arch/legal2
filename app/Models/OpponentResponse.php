<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpponentResponse extends Model
{
    use HasUlids;

    protected $fillable = [
        'generation_run_id',
        'original_profile_key',
        'responder_type',
        'original_filename',
        'source_url',
        'raw_content',
        'summary',
        'key_arguments',
        'weaknesses',
        'recommended_counters',
        'counter_profile_key',
        'counter_run_id',
    ];

    protected $casts = [
        'key_arguments' => 'array',
        'weaknesses' => 'array',
        'recommended_counters' => 'array',
    ];

    public function originalRun(): BelongsTo
    {
        return $this->belongsTo(DocumentGenerationRun::class, 'generation_run_id');
    }

    public function counterRun(): BelongsTo
    {
        return $this->belongsTo(DocumentGenerationRun::class, 'counter_run_id');
    }
}
