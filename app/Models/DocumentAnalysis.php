<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentAnalysis extends Model
{
    use HasFactory;

    protected $fillable = [
        'case_document_id',
        'analysis_layer',
        'analysis_type',
        'status',
        'results',
        'metadata',
        'error_message',
        'version',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'results' => 'json',
        'metadata' => 'json',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    // Layer constants
    const LAYER_EXTRACTION = 'extraction';
    const LAYER_PATTERN = 'pattern';
    const LAYER_AI_BASIC = 'ai_basic';
    const LAYER_AI_DEEP = 'ai_deep';

    // Type constants
    const TYPE_KEYWORDS = 'keywords';
    const TYPE_ENTITIES = 'entities';
    const TYPE_DATES = 'dates';
    const TYPE_CITATIONS = 'citations';
    const TYPE_STATISTICS = 'statistics';
    const TYPE_TIMELINE = 'timeline';
    const TYPE_SUMMARY = 'summary';
    const TYPE_KEY_FACTS = 'key_facts';
    const TYPE_CONTRADICTIONS = 'contradictions';
    const TYPE_STRATEGY = 'strategy';
    const TYPE_CASE_REFERENCES = 'case_references';
    const TYPE_DATES_WITH_CONTEXT = 'dates_with_context';

    public function caseDocument(): BelongsTo
    {
        return $this->belongsTo(CaseDocument::class);
    }

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

    /**
     * Reset analysis to pending for retry.
     */
    public function markPendingForRetry(): self
    {
        $this->update([
            'status' => self::STATUS_PENDING,
            'error_message' => null,
            'results' => null,
            'metadata' => null,
            'started_at' => null,
            'completed_at' => null,
        ]);
        return $this;
    }

    /**
     * Check if analysis can be retried.
     */
    public function canRetry(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Scope for failed analyses.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeForDocument($query, string $documentId)
    {
        return $query->where('case_document_id', $documentId);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('analysis_type', $type);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeLayer($query, string $layer)
    {
        return $query->where('analysis_layer', $layer);
    }
}
