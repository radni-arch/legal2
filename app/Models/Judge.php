<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Judge extends Model
{
    protected $fillable = ['name', 'primary_court_id', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function primaryCourt(): BelongsTo { return $this->belongsTo(Court::class, 'primary_court_id'); }
    public function cases(): HasMany { return $this->hasMany(CourtCase::class); }

    public function scopeActive($q) { return $q->where('is_active', true); }

    public static function findOrCreateByName(string $name, ?int $courtId = null): self {
        return static::firstOrCreate(['name' => $name, 'primary_court_id' => $courtId], ['is_active' => true]);
    }
}
