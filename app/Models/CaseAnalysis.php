<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaseAnalysis extends Model
{
    protected $table = 'case_analyses';

    protected $fillable = [
        'case_id',
        'analysis_type',
        'status',
        'results',
        'metadata',
        'document_ids',
        'error_message',
        'version',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'results' => 'json',
        'metadata' => 'json',
        'document_ids' => 'json',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    public function markProcessing(): self
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'started_at' => now(),
        ]);
        return $this;
    }

    public function markCompleted(array $results, array $metadata = []): self
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'results' => $results,
            'metadata' => $metadata,
            'completed_at' => now(),
        ]);
        return $this;
    }

    public function markFailed(string $error): self
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $error,
            'completed_at' => now(),
        ]);
        return $this;
    }
}
