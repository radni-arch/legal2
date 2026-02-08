<?php

namespace App\Modules\HomeSearch\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * HomeSearchCase Model
 *
 * Stores extracted home search warrant cases from odluke.sudovi.hr
 * with structured data for analysis and statistical reporting.
 *
 * @property int $id
 * @property string $case_number
 * @property string|null $court
 * @property string|null $judge
 * @property string|null $decision_date
 * @property string|null $offense_type
 * @property string|null $offense_description
 * @property string|null $offense_severity
 * @property string|null $search_type
 * @property bool|null $evidence_found
 * @property bool|null $evidence_suppressed
 * @property array|null $legal_violations
 * @property array|null $zkp_articles_cited
 * @property bool|null $proportionality_mentioned
 * @property bool|null $constitutional_rights_mentioned
 * @property string|null $source_url
 * @property float|null $extraction_confidence
 * @property \Illuminate\Support\Carbon|null $extracted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class HomeSearchCase extends Model
{
    use SoftDeletes;

    protected $table = 'home_search_cases';

    protected $fillable = [
        'case_number',
        'court',
        'judge',
        'decision_date',
        'offense_type',
        'offense_description',
        'offense_severity',
        'search_type',
        'evidence_found',
        'evidence_suppressed',
        'legal_violations',
        'zkp_articles_cited',
        'proportionality_mentioned',
        'constitutional_rights_mentioned',
        'source_url',
        'extraction_confidence',
        'extracted_at',
    ];

    protected $casts = [
        'decision_date' => 'date',
        'evidence_found' => 'boolean',
        'evidence_suppressed' => 'boolean',
        'legal_violations' => 'array',
        'zkp_articles_cited' => 'array',
        'proportionality_mentioned' => 'boolean',
        'constitutional_rights_mentioned' => 'boolean',
        'extraction_confidence' => 'decimal:2',
        'extracted_at' => 'datetime',
    ];

    /**
     * Query scope: cases by court
     */
    public function scopeByCourt($query, string $court)
    {
        return $query->where('court', $court);
    }

    /**
     * Query scope: cases by date range
     */
    public function scopeByDateRange($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('decision_date', [$startDate, $endDate]);
    }

    /**
     * Query scope: cases by offense type
     */
    public function scopeByOffenseType($query, string $offenseType)
    {
        return $query->where('offense_type', $offenseType);
    }

    /**
     * Query scope: cases with evidence suppression
     */
    public function scopeWithEvidenceSuppressed($query)
    {
        return $query->where('evidence_suppressed', true);
    }

    /**
     * Query scope: cases mentioning proportionality
     */
    public function scopeWithProportionalityMention($query)
    {
        return $query->where('proportionality_mentioned', true);
    }

    /**
     * Query scope: cases mentioning constitutional rights
     */
    public function scopeWithConstitutionalRightsMention($query)
    {
        return $query->where('constitutional_rights_mentioned', true);
    }
}
