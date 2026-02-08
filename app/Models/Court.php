<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Court extends Model
{
    use HasFactory;
    protected $fillable = ['external_id', 'name', 'code', 'level', 'county', 'population'];
    protected $casts = ['external_id' => 'integer', 'level' => 'integer', 'population' => 'integer'];

    public function cases(): HasMany { return $this->hasMany(CourtCase::class); }
    public function judges(): HasMany { return $this->hasMany(Judge::class, 'primary_court_id'); }
    public function syncLogs(): HasMany { return $this->hasMany(SyncLog::class); }

    public function scopeMunicipal($q) { return $q->where('level', 1); }
    public function scopeByCounty($q, string $c) { return $q->where('county', $c); }

    public function getShortNameAttribute(): string {
        return str_replace(['Općinski sud u ', 'Općinski prekršajni sud u '], ['OS ', 'OPS '], $this->name);
    }

    public static function findByExternalId(int $id): ?self { return static::where('external_id', $id)->first(); }
}
