<?php

namespace App\Models;

use App\Contracts\CourtCaseSearchable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EchrCase extends Model implements CourtCaseSearchable
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'item_id',
        'application_number',
        'ecli',
        'case_name',
        'case_name_short',
        'respondent_state',
        'originating_body',
        'document_type',
        'importance',
        'judgment_date',
        'decision_date',
        'introduction_date',
        'publication_date',
        'violations',
        'non_violations',
        'conclusion_summary',
        'full_text',
        'facts',
        'law_section',
        'legal_summary',
        'keywords',
        'kp_thesaurus',
        'external_sources',
        'cited_cases',
        'representedby',
        'has_separate_opinion',
        'language',
        'available_languages',
        'full_text_downloaded',
        'is_analyzed',
        'analysis_results',
        'last_synced_at',
    ];

    protected $casts = [
        'judgment_date' => 'date',
        'decision_date' => 'date',
        'introduction_date' => 'date',
        'publication_date' => 'date',
        'violations' => 'array',
        'non_violations' => 'array',
        'conclusion_summary' => 'array',
        'keywords' => 'array',
        'kp_thesaurus' => 'array',
        'external_sources' => 'array',
        'cited_cases' => 'array',
        'available_languages' => 'array',
        'analysis_results' => 'array',
        'has_separate_opinion' => 'boolean',
        'full_text_downloaded' => 'boolean',
        'is_analyzed' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    // Relationships
    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(EchrArticle::class, 'echr_case_article')
            ->withPivot(['status', 'conclusion_text'])
            ->withTimestamps();
    }

    public function violatedArticles(): BelongsToMany
    {
        return $this->articles()->wherePivot('status', 'VIOLATION');
    }

    public function noViolationArticles(): BelongsToMany
    {
        return $this->articles()->wherePivot('status', 'NO_VIOLATION');
    }

    public function citingCases(): HasMany
    {
        return $this->hasMany(EchrCaseCitation::class, 'cited_case_id');
    }

    public function citedCases(): HasMany
    {
        return $this->hasMany(EchrCaseCitation::class, 'citing_case_id');
    }

    // Scopes
    public function scopeAgainst($query, string $state)
    {
        return $query->where('respondent_state', $state);
    }

    public function scopeCroatia($query)
    {
        return $query->where('respondent_state', 'Croatia');
    }

    public function scopeImportant($query)
    {
        return $query->whereIn('importance', ['1', '2']);
    }

    public function scopeWithViolation($query, string $article)
    {
        return $query->whereHas('articles', function ($q) use ($article) {
            $q->where('article_code', $article)
              ->wherePivot('status', 'VIOLATION');
        });
    }

    public function scopeJudgments($query)
    {
        return $query->where('document_type', 'JUDGMENT');
    }

    public function scopeWithFullText($query)
    {
        return $query->where('full_text_downloaded', true);
    }

    // Accessors
    public function getHudocUrlAttribute(): string
    {
        return "https://hudoc.echr.coe.int/eng?i={$this->item_id}";
    }

    public function getShortNameAttribute(): string
    {
        return $this->case_name_short ?? $this->case_name;
    }

    // Methods
    public static function findByApplicationNumber(string $appNo): ?self
    {
        return static::where('application_number', $appNo)->first();
    }

    public static function findByItemId(string $itemId): ?self
    {
        return static::where('item_id', $itemId)->first();
    }

    public function isViolation(string $articleCode): bool
    {
        return $this->violatedArticles()
            ->where('article_code', $articleCode)
            ->exists();
    }

    public function markAsAnalyzed(array $results): void
    {
        $this->update([
            'is_analyzed' => true,
            'analysis_results' => $results,
        ]);
    }

    // CourtCaseSearchable interface
    public function getCaseIdentifier(): string
    {
        return $this->application_number ?? $this->item_id;
    }

    public function getCaseName(): string
    {
        return $this->case_name_short ?? $this->case_name;
    }

    public function getCourtName(): string
    {
        return 'European Court of Human Rights';
    }

    public function getDecisionDate(): ?string
    {
        return $this->judgment_date?->toDateString();
    }

    public function getFullText(): ?string
    {
        return $this->full_text;
    }

    public function getSourceUrl(): string
    {
        return $this->hudoc_url;
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'type' => 'echr',
            'identifier' => $this->getCaseIdentifier(),
            'name' => $this->getCaseName(),
            'court' => $this->getCourtName(),
            'date' => $this->getDecisionDate(),
            'state' => $this->respondent_state,
            'articles' => $this->articles->pluck('article_code')->toArray(),
            'violations' => $this->violations ?? [],
            'importance' => $this->importance,
            'url' => $this->getSourceUrl(),
        ];
    }
}
