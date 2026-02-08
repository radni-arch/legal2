<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalFactPattern extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'raw_narrative',
        'structured_facts',
        'legal_area',
        'extraction_confidence',
    ];

    protected $casts = [
        'structured_facts' => 'array',
        'extraction_confidence' => 'float',
    ];

    /**
     * Get the user that owns the fact pattern.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get a specific fact from structured facts by key.
     */
    public function getFact(string $key, $default = null)
    {
        return data_get($this->structured_facts, $key, $default);
    }

    /**
     * Check if extraction confidence is high (above 0.7).
     */
    public function hasHighConfidence(): bool
    {
        return $this->extraction_confidence >= 0.7;
    }

    /**
     * Check if extraction confidence is low (below 0.5).
     */
    public function hasLowConfidence(): bool
    {
        return $this->extraction_confidence < 0.5;
    }

    /**
     * Get all parties involved from structured facts.
     */
    public function getParties(): array
    {
        return $this->getFact('parties', []);
    }

    /**
     * Get all events from structured facts.
     */
    public function getEvents(): array
    {
        return $this->getFact('events', []);
    }

    /**
     * Get all legal issues from structured facts.
     */
    public function getLegalIssues(): array
    {
        return $this->getFact('legal_issues', []);
    }
}
