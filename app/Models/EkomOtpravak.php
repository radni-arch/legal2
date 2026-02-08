<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EkomOtpravak extends Model
{
    use HasFactory;

    /**
     * Status options for otpravci
     */
    public const STATUSES = [
        'kreiran' => 'Kreiran',
        'poslan' => 'Poslan',
        'dostavljen' => 'Dostavljen',
        'istekao_rok' => 'Istekao rok',
    ];

    protected $table = 'ekom_otpravci';

    protected $fillable = [
        'remote_id',
        'status',
        'predmet_remote_id',
        'vrijeme_slanja_sa_suda',
        'vrijeme_potvrde_primitka',
        'primljen_zbog_isteka_roka',
        'data',
        'last_synced_at',
    ];

    protected $casts = [
        'data' => 'array',
        'primljen_zbog_isteka_roka' => 'boolean',
        'last_synced_at' => 'datetime',
    ];
}
