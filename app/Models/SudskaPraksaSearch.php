<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SudskaPraksaSearch extends Model
{
    protected $table = 'sudska_praksa_searches';

    protected $fillable = [
        'name',
        'keywords_file',
        'courts',
        'total_queries',
        'ultra_count',
        'zlato_count',
        'srebrno_count',
        'bronca_count',
        'error_count',
        'metadata',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function results(): HasMany
    {
        return $this->hasMany(SudskaPraksaResult::class, 'search_id');
    }

    /**
     * Get only high-value results (ultra + zlato)
     */
    public function goldResults(): HasMany
    {
        return $this->results()->whereIn('classification', ['ultra', 'zlato']);
    }

    /**
     * Compute summary stats from results
     */
    public function computeStats(): void
    {
        $results = $this->results;
        $this->total_queries = $results->count();
        $this->ultra_count = $results->where('classification', 'ultra')->count();
        $this->zlato_count = $results->where('classification', 'zlato')->count();
        $this->srebrno_count = $results->where('classification', 'srebrno')->count();
        $this->bronca_count = $results->where('classification', 'bronca')->count();
        $this->error_count = $results->where('classification', 'error')->count();
        $this->save();
    }
}
