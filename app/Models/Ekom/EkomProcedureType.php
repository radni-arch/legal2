<?php

namespace App\Models\Ekom;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EkomProcedureType extends Model
{
    protected $table = 'ekom_procedure_types';

    protected $fillable = [
        'remote_id',
        'court_remote_id',
        'naziv',
        'oznaka',
        'synced_at',
    ];

    protected $casts = [
        'remote_id' => 'integer',
        'court_remote_id' => 'integer',
        'synced_at' => 'datetime',
    ];

    public function scopeByRemoteId($query, int $remoteId)
    {
        return $query->where('remote_id', $remoteId);
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(EkomCourt::class, 'court_remote_id', 'remote_id');
    }

    public function submissionTypes(): HasMany
    {
        return $this->hasMany(EkomSubmissionType::class, 'procedure_type_remote_id', 'remote_id');
    }

    public function participantRoles(): HasMany
    {
        return $this->hasMany(EkomParticipantRole::class, 'procedure_type_remote_id', 'remote_id');
    }
}
