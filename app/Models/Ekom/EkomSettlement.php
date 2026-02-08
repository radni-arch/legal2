<?php

namespace App\Models\Ekom;

use Illuminate\Database\Eloquent\Model;

class EkomSettlement extends Model
{
    protected $table = 'ekom_settlements';

    protected $fillable = [
        'remote_id',
        'naziv',
        'postanski_broj',
        'synced_at',
    ];

    protected $casts = [
        'remote_id' => 'integer',
        'synced_at' => 'datetime',
    ];
}
