<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiKeyCooldown extends Model
{
    protected $fillable = [
        'api_key_id', 'cooldown_type', 'started_at', 'ends_at',
        'source', 'retry_after_seconds', 'metadata', 'reason',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ends_at' => 'datetime',
        'retry_after_seconds' => 'integer',
        'metadata' => 'array',
    ];

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }

    public function scopeActive($query)
    {
        return $query->where('ends_at', '>', now());
    }

    public function isActive(): bool
    {
        return $this->ends_at->isFuture();
    }

    public function getRemainingSeconds(): int
    {
        return max(0, now()->diffInSeconds($this->ends_at, false));
    }
}
