<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscoveryPackage extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'fact_pattern_id',
        'requests',
        'metadata',
    ];

    protected $casts = [
        'requests' => 'array',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function factPattern(): BelongsTo
    {
        return $this->belongsTo(LegalFactPattern::class, 'fact_pattern_id');
    }
}
