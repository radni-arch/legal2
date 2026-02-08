<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Model representing a legal provision (zakonska odredba).
 *
 * Stores statutory provisions from Croatian law with citations,
 * full text, interpretation guidance, and profile tagging.
 */
class LegalProvision extends Model
{
    use HasFactory;

    protected $fillable = [
        'law_name',
        'law_short',
        'article',
        'paragraph',
        'point',
        'title',
        'full_text',
        'interpretation',
        'tags',
        'rebuts',
        'complements',
        'strength',
        'source_url',
    ];

    protected $casts = [
        'tags' => 'array',
        'rebuts' => 'array',
        'complements' => 'array',
    ];

    /**
     * Scope: Filter provisions by law short code.
     *
     * @param  Builder  $query
     * @param  string  $short  Short code for the law (e.g., 'PZ', 'ZKP', 'Ustav')
     */
    public function scopeForLaw(Builder $query, string $short): Builder
    {
        return $query->where('law_short', $short);
    }

    /**
     * Scope: Filter provisions by article number.
     *
     * @param  Builder  $query
     * @param  string  $article  Article number
     */
    public function scopeForArticle(Builder $query, string $article): Builder
    {
        return $query->where('article', $article);
    }

    /**
     * Scope: Filter provisions containing a specific tag.
     *
     * @param  Builder  $query
     * @param  string  $tag  Tag to search for
     */
    public function scopeWithTag(Builder $query, string $tag): Builder
    {
        return $query->whereJsonContains('tags', $tag);
    }

    /**
     * Scope: Filter provisions by profile tag.
     *
     * @param  Builder  $query
     * @param  string  $profileKey  Profile key to search in tags
     */
    public function scopeForProfile(Builder $query, string $profileKey): Builder
    {
        return $query->where(function ($q) use ($profileKey) {
            $q->whereJsonContains('tags', $profileKey)
              ->orWhereJsonContains('tags', 'all_profiles');
        });
    }

    /**
     * Scope: Filter provisions marked as devastating.
     */
    public function scopeDevastating(Builder $query): Builder
    {
        return $query->where('strength', 'devastating');
    }

    /**
     * Scope: Filter provisions that rebut specific arguments.
     */
    public function scopeWithRebuttals(Builder $query): Builder
    {
        return $query->whereJsonLength('rebuts', '>', 0);
    }

    /**
     * Get provisions that complement this provision.
     */
    public function getComplements(): Collection
    {
        if (empty($this->complements)) {
            return collect();
        }

        return static::query()
            ->where(function ($query) {
                foreach ($this->complements as $reference) {
                    if (preg_match('/^(\w+)\s+(?:čl|cl)\.(\d+)(?:\s+st\.(\d+))?/u', $reference, $matches)) {
                        $query->orWhere(function ($sub) use ($matches) {
                            $sub->where('law_short', $matches[1])
                                ->where('article', $matches[2]);

                            if (isset($matches[3])) {
                                $sub->where('paragraph', $matches[3]);
                            }
                        });
                    }
                }
            })
            ->get();
    }

    /**
     * Generate a short citation for this provision.
     *
     * Example: "PZ cl.150 st.1" or "ZKP cl.10 st.2 toc.2"
     */
    public function shortCitation(): string
    {
        $cite = "{$this->law_short} cl.{$this->article}";

        if ($this->paragraph) {
            $cite .= " st.{$this->paragraph}";
        }

        if ($this->point) {
            $cite .= " toc.{$this->point}";
        }

        return $cite;
    }

    /**
     * Generate a full citation with law name.
     *
     * Example: "clanak 150. stavak 1. Prekrsajni zakon"
     */
    public function fullCitation(): string
    {
        $cite = "clanak {$this->article}.";

        if ($this->paragraph) {
            $cite .= " stavak {$this->paragraph}.";
        }

        if ($this->point) {
            $cite .= " tocka {$this->point}.";
        }

        $cite .= " {$this->law_name}";

        return $cite;
    }
}
