<?php

namespace App\Models\Ekom;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EkomFeeOption extends Model
{
    protected $table = 'ekom_fee_options';

    protected $fillable = [
        'remote_id',
        'procedure_type_remote_id',
        'submission_type_remote_id',
        'naziv',
        'context',
        'synced_at',
    ];

    protected $casts = [
        'remote_id' => 'integer',
        'procedure_type_remote_id' => 'integer',
        'submission_type_remote_id' => 'integer',
        'synced_at' => 'datetime',
    ];

    public function procedureType(): BelongsTo
    {
        return $this->belongsTo(EkomProcedureType::class, 'procedure_type_remote_id', 'remote_id');
    }

    public function submissionType(): BelongsTo
    {
        return $this->belongsTo(EkomSubmissionType::class, 'submission_type_remote_id', 'remote_id');
    }
}
