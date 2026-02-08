<?php

namespace App\Models\Ekom;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EkomCourt extends Model
{
    protected $table = 'ekom_courts';

    protected $fillable = [
        'remote_id',
        'naziv',
        'oznaka',
        'vrsta_suda_id',
        'vrsta_suda_naziv',
        'data',
        'synced_at',
    ];

    protected $casts = [
        'remote_id' => 'integer',
        'vrsta_suda_id' => 'integer',
        'data' => 'array',
        'synced_at' => 'datetime',
    ];

    public function scopeByRemoteId($query, int $remoteId)
    {
        return $query->where('remote_id', $remoteId);
    }

    public function procedureTypes(): HasMany
    {
        return $this->hasMany(EkomProcedureType::class, 'court_remote_id', 'remote_id');
    }
}
