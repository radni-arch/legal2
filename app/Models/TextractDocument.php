<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TextractDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'textract_job_id',
        'case_id',
        'content',
        'chunk_index',
        'chunk_overlap',
        'embedding',
        'embedding_provider',
        'embedding_model',
        'embedding_dimensions',
        'token_count',
        'processing_status',
        'processing_error',
        'embedded_at',
        'metadata',
        's3_output_path',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'embedding' => 'array',
        'metadata' => 'array',
        'embedded_at' => 'datetime',
    ];

    /**
     * Get the textract job this document belongs to.
     */
    public function textractJob(): BelongsTo
    {
        return $this->belongsTo(TextractJob::class);
    }

    /**
     * Get the case this document belongs to.
     */
    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    /**
     * Scope to get only processed documents.
     */
    public function scopeProcessed($query)
    {
        return $query->where('processing_status', 'completed');
    }

    /**
     * Scope to get only pending documents.
     */
    public function scopePending($query)
    {
        return $query->where('processing_status', 'pending');
    }

    /**
     * Scope to get only failed documents.
     */
    public function scopeFailed($query)
    {
        return $query->where('processing_status', 'failed');
    }

    /**
     * Scope to get documents for a specific case.
     */
    public function scopeForCase($query, string $caseId)
    {
        return $query->where('case_id', $caseId);
    }

    /**
     * Scope to get documents ordered by chunk index.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('chunk_index');
    }

    /**
     * Check if document has been embedded.
     */
    public function hasEmbedding(): bool
    {
        return ! empty($this->embedding) && $this->processing_status === 'completed';
    }

    /**
     * Mark document as successfully embedded.
     */
    public function markAsEmbedded(array $embedding, array $metadata = []): void
    {
        $this->update([
            'embedding' => $embedding,
            'processing_status' => 'completed',
            'embedded_at' => now(),
            'embedding_provider' => $metadata['provider'] ?? $this->embedding_provider,
            'embedding_model' => $metadata['model'] ?? $this->embedding_model,
            'embedding_dimensions' => $metadata['dimensions'] ?? count($embedding),
            'processing_error' => null,
        ]);
    }

    /**
     * Mark document as failed.
     */
    public function markAsFailed(string $error): void
    {
        $this->update([
            'processing_status' => 'failed',
            'processing_error' => $error,
        ]);
    }

    /**
     * Calculate cosine similarity between this document and a query vector.
     *
     * @param  array  $queryVector  The vector to compare against
     * @return float|null Similarity score between -1 and 1, or null if no embedding
     */
    public function cosineSimilarity(array $queryVector): ?float
    {
        if (! $this->hasEmbedding()) {
            return null;
        }

        $dotProduct = 0;
        $magnitudeA = 0;
        $magnitudeB = 0;

        for ($i = 0; $i < count($this->embedding); $i++) {
            $dotProduct += $this->embedding[$i] * $queryVector[$i];
            $magnitudeA += $this->embedding[$i] ** 2;
            $magnitudeB += $queryVector[$i] ** 2;
        }

        $magnitudeA = sqrt($magnitudeA);
        $magnitudeB = sqrt($magnitudeB);

        if ($magnitudeA == 0 || $magnitudeB == 0) {
            return null;
        }

        return $dotProduct / ($magnitudeA * $magnitudeB);
    }

    /**
     * Get the full document content by combining all chunks.
     */
    public static function getFullContent(int $textractJobId): string
    {
        return static::where('textract_job_id', $textractJobId)
            ->ordered()
            ->pluck('content')
            ->implode("\n");
    }

    /**
     * Boot the model with event listeners.
     */
    protected static function booted(): void
    {
        // When processing status changes to completed, sync to parent job
        static::updated(function ($document) {
            if ($document->wasChanged('processing_status') && $document->processing_status === 'completed') {
                $job = $document->textractJob;

                // Check if all documents are processed
                $allProcessed = $job->documents()
                    ->where('processing_status', '!=', 'completed')
                    ->doesntExist();

                if ($allProcessed) {
                    $job->markEmbeddingSynced();
                }
            }
        });

        // When a document is deleted, clean up its Neo4j node
        static::deleting(function ($document) {
            try {
                // Only attempt Neo4j cleanup if enabled
                if (config('neo4j.sync.enabled', false)) {
                    $graphSyncService = app(\App\Services\Graph\TextractGraphSyncService::class);
                    $graphSyncService->unsync($document->id);

                    \Illuminate\Support\Facades\Log::info('TextractDocument Neo4j node deleted', [
                        'document_id' => $document->id,
                        'textract_job_id' => $document->textract_job_id,
                    ]);
                }
            } catch (\Exception $e) {
                // Log error but don't prevent database deletion
                \Illuminate\Support\Facades\Log::error('Failed to delete TextractDocument Neo4j node', [
                    'document_id' => $document->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                // Continue with database deletion even if Neo4j cleanup fails
            }
        });
    }
}
