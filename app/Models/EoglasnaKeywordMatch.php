<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EoglasnaKeywordMatch extends Model
{
    protected $table = 'eoglasna_keyword_matches';

    protected $fillable = [
        'keyword_id', 'notice_uuid', 'matched_at', 'matched_fields',
    ];

    protected $casts = [
        'matched_at' => 'datetime',
        'matched_fields' => 'array',
    ];

    // Relations
    public function keyword()
    {
        return $this->belongsTo(EoglasnaKeyword::class, 'keyword_id');
    }

    public function notice()
    {
        return $this->belongsTo(EoglasnaNotice::class, 'notice_uuid', 'uuid');
    }
}
