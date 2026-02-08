<?php

namespace App\Models\Ekom;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EkomParticipantRole extends Model
{
    protected $table = 'ekom_participant_roles';

    protected $fillable = [
        'remote_id',
        'procedure_type_remote_id',
        'naziv',
        'type',
        'synced_at',
    ];

    protected $casts = [
        'remote_id' => 'integer',
        'procedure_type_remote_id' => 'integer',
        'synced_at' => 'datetime',
    ];

    public function procedureType(): BelongsTo
    {
        return $this->belongsTo(EkomProcedureType::class, 'procedure_type_remote_id', 'remote_id');
    }
}
