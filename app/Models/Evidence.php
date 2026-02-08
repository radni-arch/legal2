<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Evidence Model Stub
 *
 * Temporary implementation until proper evidence table and model are created.
 * This stub allows the application to function without errors while evidence
 * functionality is being developed.
 */
class Evidence extends Model
{
    use HasFactory;

    protected $table = 'evidence';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'case_id', 'title', 'description', 'type', 'source',
    ];

    public function case()
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    protected static function booted()
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) \Illuminate\Support\Str::ulid();
            }
        });
    }
}
