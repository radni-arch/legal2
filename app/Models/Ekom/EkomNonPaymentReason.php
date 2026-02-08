<?php

namespace App\Models\Ekom;

use Illuminate\Database\Eloquent\Model;

class EkomNonPaymentReason extends Model
{
    protected $table = 'ekom_non_payment_reasons';

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
