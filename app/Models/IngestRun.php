<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * IngestRun tracks a single file's journey through the ingest pipeline.
 *
 * Created by IngestOrchestrator when a file is uploaded. Tracks status
 * from pending -> processing -> ocr -> embedding -> analysis -> completed/failed.
 *
 * @property string $id ULID primary key
 * @property int $user_id
 * @property string|null $case_id
 * @property string $source uploader|drive|api
 * @property string $original_filename
 * @property string $stored_path
 * @property string $stored_disk
 * @property string $status pending|processing|ocr|embedding|analysis|completed|failed
 * @property string|null $error_message
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property string $correlation_id UUID for observability
 */
class IngestRun extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id',
        'case_id',
        'source',
        'original_filename',
        'stored_path',
        'stored_disk',
        'status',
        'error_message',
        'started_at',
        'completed_at',
        'correlation_id',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Boot the model: auto-generate correlation_id if not set.
     */
    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->correlation_id)) {
                $model->correlation_id = (string) Str::uuid();
            }
        });
    }

    /**
     * The user who initiated this ingest run.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The legal case this ingest is associated with (optional).
     */
    public function legalCase(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }
}
