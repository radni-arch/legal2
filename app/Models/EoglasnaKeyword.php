<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EoglasnaKeyword extends Model
{
    use HasFactory;

    protected $table = 'eoglasna_keywords';

    protected $fillable = [
        'query', 'scope', 'deep_scan', 'enabled', 'last_run_at', 'last_date_published', 'notes',
    ];

    protected $casts = [
        'deep_scan' => 'boolean',
        'enabled' => 'boolean',
        'last_run_at' => 'datetime',
        'last_date_published' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function (self $model): void {
            if (empty($model->scope)) {
                $model->scope = 'notice';
            }
            if (! isset($model->deep_scan)) {
                $model->deep_scan = false;
            }
            if (! isset($model->enabled)) {
                $model->enabled = true;
            }
        });
    }

    public function matches(): HasMany
    {
        return $this->hasMany(EoglasnaKeywordMatch::class, 'keyword_id');
    }
}
