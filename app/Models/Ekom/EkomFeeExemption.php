<?php

namespace App\Models\Ekom;

use Illuminate\Database\Eloquent\Model;

class EkomFeeExemption extends Model
{
    protected $table = 'ekom_fee_exemptions';

    protected $fillable = [
        'remote_id',
        'naziv',
        'synced_at',
    ];

    protected $casts = [
        'remote_id' => 'integer',
        'synced_at' => 'datetime',
    ];
}
