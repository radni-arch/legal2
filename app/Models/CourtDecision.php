<?php

namespace App\Models;

use App\Casts\JsonUnescaped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourtDecision extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table;

    protected $fillable = [
        'id', 'case_number', 'title', 'court', 'jurisdiction',
        'judge', 'decision_date', 'publication_date', 'decision_type',
        'register', 'finality', 'ecli', 'tags', 'description',
        'outcome', 'holding', 'precedential_value',
        'dissent_count', 'concurrence_count',
        'plaintiff', 'defendant',
    ];

    protected $casts = [
        'tags' => JsonUnescaped::class,
        'decision_date' => 'date',
        'publication_date' => 'date',
        'dissent_count' => 'integer',
        'concurrence_count' => 'integer',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('vizra-adk.tables.court_decisions', 'court_decisions');
    }

    // Relations
    public function documents()
    {
        return $this->hasMany(CourtDecisionDocument::class, 'decision_id');
    }

    public function uploads()
    {
        return $this->hasMany(CourtDecisionDocumentUpload::class, 'decision_id');
    }

    public function impactMetrics()
    {
        return $this->hasOne(DecisionImpactMetric::class, 'decision_id');
    }

    public function citationTimeSeries()
    {
        return $this->hasMany(CitationTimeSeries::class, 'decision_id');
    }

    public function caseMatches(): HasMany
    {
        return $this->hasMany(CourtCaseDecisionMatch::class, 'court_decision_id');
    }
}
