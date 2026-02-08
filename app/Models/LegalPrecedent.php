<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LegalPrecedent extends Model
{
    use HasFactory;

    protected $fillable = [
        'case_number',
        'court',
        'court_full',
        'decision_date',
        'published_in',
        'applicant',
        'respondent',
        'echr_app_number',
        'legal_issue',
        'key_holding',
        'key_quote',
        'quote_language',
        'relevance_to_case',
        'strength',
        'articles_interpreted',
        'argument_types',
        'tags',
        'source_url',
        'nn_reference',
    ];

    protected $casts = [
        'decision_date' => 'date',
        'articles_interpreted' => 'array',
        'argument_types' => 'array',
        'tags' => 'array',
    ];

    /**
     * Scope: Filter precedents by court code.
     *
     * @param  Builder  $query
     * @param  string  $court  Court code (e.g., 'USRH', 'ECHR', 'VSRH')
     */
    public function scopeByCourt(Builder $query, string $court): Builder
    {
        return $query->where('court', $court);
    }

    /**
     * Scope: Filter precedents by court code.
     *
     * @param  Builder  $query
     * @param  string  $court  Court code (e.g., 'USRH', 'ECHR', 'VSRH')
     */
    public function scopeFromCourt(Builder $query, string $court): Builder
    {
        return $query->where('court', $court);
    }

    /**
     * Scope: Filter precedents by profile tag.
     *
     * @param  Builder  $query
     * @param  string  $profileKey  Profile key to search in tags
     */
    public function scopeForProfile(Builder $query, string $profileKey): Builder
    {
        return $query->whereJsonContains('tags', $profileKey);
    }

    /**
     * Scope: Filter precedents by argument type.
     *
     * @param  Builder  $query
     * @param  string  $argumentType  Argument type to search in argument_types
     */
    public function scopeForArgument(Builder $query, string $argumentType): Builder
    {
        return $query->whereJsonContains('argument_types', $argumentType);
    }

    /**
     * Scope: Filter devastating precedents for a profile.
     *
     * @param  Builder  $query
     * @param  string  $profileKey  Profile key to search in tags
     */
    public function scopeDevastatingForProfile(Builder $query, string $profileKey): Builder
    {
        return $query->whereJsonContains('tags', $profileKey)
            ->where('strength', 'devastating');
    }

    /**
     * Build a citation block for LLM prompts.
     */
    public function toCitationBlock(): string
    {
        $label = $this->case_number;
        if ($this->court === 'ECHR' && $this->applicant && $this->respondent) {
            $label = "{$this->applicant} v. {$this->respondent}";
        }

        $date = $this->decision_date
            ? $this->decision_date->format('Y-m-d')
            : '';

        $block = "**{$label}** ({$this->court}" . ($date ? ", {$date}" : '') . ')';
        if ($this->key_holding) {
            $block .= "\nStav: {$this->key_holding}";
        }

        if ($this->key_quote) {
            $block .= "\nCitat [{$this->quote_language}]: \"{$this->key_quote}\"";
        }

        if ($this->relevance_to_case) {
            $block .= "\nRelevantnost: {$this->relevance_to_case}";
        }

        return $block;
    }

    /**
     * Format a legal citation string for this precedent.
     *
     * Formats vary by court:
     * - USRH: "USRH, U-III-3071/2006, 18.3.2009."
     * - ECHR: "ECHR, Dragojević v. Croatia, App. 68955/11, 15.1.2015"
     * - Other: "COURT, case_number, date"
     */
    public function citation(): string
    {
        $date = $this->decision_date
            ? $this->decision_date->format('j.n.Y')
            : '';

        // ECHR format includes application number
        if ($this->court === 'ECHR' && $this->echr_app_number) {
            $cite = "{$this->court}, {$this->case_number}, App. {$this->echr_app_number}";
            if ($date) {
                $cite .= ", {$date}";
            }

            return $cite;
        }

        // Standard format for Croatian courts
        $cite = "{$this->court}, {$this->case_number}";
        if ($date) {
            $cite .= ", {$date}";
        }

        // Append Narodne Novine reference if available
        if ($this->nn_reference) {
            $cite .= ", {$this->nn_reference}";
        }

        return $cite;
    }
}
