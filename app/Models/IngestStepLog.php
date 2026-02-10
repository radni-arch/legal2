<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * IngestStepLog tracks timing and status of individual pipeline steps.
 *
 * Each IngestRun has multiple step logs (upload, ocr, extraction, analysis).
 * Used for end-to-end observability of the ingest pipeline.
 *
 * @property int $id
 * @property string $ingest_run_id FK to ingest_runs
 * @property string $step_name upload|ocr|extraction|analysis
 * @property string $status started|completed|failed|skipped
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property int|null $duration_ms Duration in milliseconds
 * @property array|null $metadata Arbitrary JSON metadata
 * @property string|null $error_message Error details on failure
 */
class IngestStepLog extends Model
{
    protected $fillable = [
        'ingest_run_id',
        'step_name',
        'status',
        'started_at',
        'completed_at',
        'duration_ms',
        'metadata',
        'error_message',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'duration_ms' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * The ingest run this step belongs to.
     */
    public function ingestRun(): BelongsTo
    {
        return $this->belongsTo(IngestRun::class);
    }
}
