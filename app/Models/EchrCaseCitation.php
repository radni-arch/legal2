<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EchrCaseCitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'citing_case_id',
        'cited_case_id',
        'cited_case_name',
        'cited_application_number',
        'citation_type',
        'context',
    ];

    public function citingCase(): BelongsTo
    {
        return $this->belongsTo(EchrCase::class, 'citing_case_id');
    }

    public function citedCase(): BelongsTo
    {
        return $this->belongsTo(EchrCase::class, 'cited_case_id');
    }
}
