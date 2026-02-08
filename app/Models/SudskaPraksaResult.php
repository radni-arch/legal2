<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SudskaPraksaResult extends Model
{
    protected $table = 'sudska_praksa_results';

    protected $fillable = [
        'search_id',
        'category',
        'category_description',
        'query',
        'comment',
        'count',
        'classification',
        'url',
        'is_expanded',
        'fetched_at',
    ];

    protected $casts = [
        'is_expanded' => 'boolean',
        'fetched_at' => 'datetime',
    ];

    public function search(): BelongsTo
    {
        return $this->belongsTo(SudskaPraksaSearch::class, 'search_id');
    }

    /**
     * Scope: only high-value results
     */
    public function scopeGold($query)
    {
        return $query->whereIn('classification', ['ultra', 'zlato']);
    }

    /**
     * Scope: only results with classification
     */
    public function scopeClassification($query, string $classification)
    {
        return $query->where('classification', $classification);
    }
}
