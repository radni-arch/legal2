<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CaseChronology extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'fact_pattern_id',
        'events',
        'analysis',
        'visualization_data',
    ];

    protected $casts = [
        'events' => 'array',
        'analysis' => 'array',
        'visualization_data' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    // Relations
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function factPattern(): BelongsTo
    {
        return $this->belongsTo(LegalFactPattern::class, 'fact_pattern_id');
    }
}
