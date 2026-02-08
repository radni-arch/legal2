<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LawUpload extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table;

    protected $fillable = [
        'id', 'doc_id', 'ingested_law_id', 'disk', 'local_path', 'original_filename', 'mime_type', 'file_size',
        'sha256', 'source_url', 'downloaded_at', 'status', 'error',
    ];

    protected $casts = [
        'downloaded_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::ulid();
            }
            if (empty($model->doc_id)) {
                $model->doc_id = 'law-upload-'.Str::random(16);
            }
            if (empty($model->local_path)) {
                $model->local_path = '/tmp/law-upload-'.Str::random(8).'.pdf';
            }
        });
    }

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('vizra-adk.tables.law_uploads', 'law_uploads');
    }

    public function ingestedLaw()
    {
        return $this->belongsTo(IngestedLaw::class, 'ingested_law_id');
    }
}
