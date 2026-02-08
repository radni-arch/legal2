<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CaseStrategy extends Model
{
    use HasFactory;
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'case_id',
        'version',
        'status',
        'objectives',
        'analysis',
        'arguments',
        'risks',
        'precedents',
        'action_plan',
        'timeline',
        'recommendations',
        'summary',
        'confidence_score',
        'metrics',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'objectives' => 'array',
        'analysis' => 'array',
        'arguments' => 'array',
        'risks' => 'array',
        'precedents' => 'array',
        'action_plan' => 'array',
        'timeline' => 'array',
        'recommendations' => 'array',
        'metrics' => 'array',
        'confidence_score' => 'float',
        'approved_at' => 'datetime',
    ];

    // Relations
    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeLatestVersion($query)
    {
        return $query->orderByDesc('version');
    }
}
