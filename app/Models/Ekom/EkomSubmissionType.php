<?php

namespace App\Models\Ekom;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EkomSubmissionType extends Model
{
    protected $table = 'ekom_submission_types';

    protected $fillable = [
        'remote_id',
        'procedure_type_remote_id',
        'naziv',
        'oznaka',
        'context',
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

    public function feeOptions(): HasMany
    {
        return $this->hasMany(EkomFeeOption::class, 'submission_type_remote_id', 'remote_id');
    }
}
