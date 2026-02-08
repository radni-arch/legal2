<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EchrArticle extends Model
{
    use HasFactory;

    protected $fillable = [
        'article_code',
        'article_name',
        'description',
        'protocol',
        'sort_order',
    ];

    public function cases(): BelongsToMany
    {
        return $this->belongsToMany(EchrCase::class, 'echr_case_article')
            ->withPivot(['status', 'conclusion_text'])
            ->withTimestamps();
    }

    public function violationCases(): BelongsToMany
    {
        return $this->cases()->wherePivot('status', 'VIOLATION');
    }

    public function getViolationCountAttribute(): int
    {
        return $this->violationCases()->count();
    }

    public static function findByCode(string $code): ?self
    {
        return static::where('article_code', $code)->first();
    }
}
