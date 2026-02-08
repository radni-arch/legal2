<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VectorDocument extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'file_name',
        'file_path',
        'openai_file_id',
        'vector_store_id',
        'case_id',
        'status',
        'metadata',
        'attributes',
        'catalog_entry',
        'tagged_at',
        'uploaded_at',
        'cataloged_at',
        'tagger_model',
        'confidence',
    ];

    protected $casts = [
        'metadata' => 'array',
        'attributes' => 'array',
        'catalog_entry' => 'array',
        'tagged_at' => 'datetime',
        'uploaded_at' => 'datetime',
        'cataloged_at' => 'datetime',
    ];

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_TAGGED = 'tagged';
    public const STATUS_UPLOADED = 'uploaded';
    public const STATUS_CATALOGED = 'cataloged';
    public const STATUS_ERROR = 'error';

    /**
     * Scope to filter by vector store ID.
     */
    public function scopeForStore($query, string $vsId)
    {
        return $query->where('vector_store_id', $vsId);
    }

    /**
     * Scope to get tagged documents.
     */
    public function scopeTagged($query)
    {
        return $query->where('status', self::STATUS_TAGGED);
    }

    /**
     * Scope to get documents needing upload.
     */
    public function scopeNeedsUpload($query)
    {
        return $query->whereIn('status', [self::STATUS_TAGGED])
                     ->whereNull('openai_file_id');
    }

    /**
     * Scope to get documents needing catalog entry.
     */
    public function scopeNeedsCatalog($query)
    {
        return $query->where('status', self::STATUS_UPLOADED)
                     ->whereNull('cataloged_at');
    }

    /**
     * Check if document is in pending state.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if document is tagged.
     */
    public function isTagged(): bool
    {
        return $this->status === self::STATUS_TAGGED;
    }

    /**
     * Check if document is uploaded.
     */
    public function isUploaded(): bool
    {
        return $this->status === self::STATUS_UPLOADED;
    }

    /**
     * Check if document is cataloged.
     */
    public function isCataloged(): bool
    {
        return $this->status === self::STATUS_CATALOGED;
    }

    /**
     * Mark document as tagged.
     */
    public function markAsTagged(array $metadata, ?string $taggerModel = null, ?float $confidence = null): self
    {
        $this->update([
            'status' => self::STATUS_TAGGED,
            'metadata' => $metadata,
            'tagged_at' => now(),
            'tagger_model' => $taggerModel,
            'confidence' => $confidence,
        ]);

        return $this;
    }

    /**
     * Mark document as uploaded.
     */
    public function markAsUploaded(string $fileId, ?array $attributes = null): self
    {
        $this->update([
            'status' => self::STATUS_UPLOADED,
            'openai_file_id' => $fileId,
            'attributes' => $attributes,
            'uploaded_at' => now(),
        ]);

        return $this;
    }

    /**
     * Mark document as cataloged.
     */
    public function markAsCataloged(array $catalogEntry): self
    {
        $this->update([
            'status' => self::STATUS_CATALOGED,
            'catalog_entry' => $catalogEntry,
            'cataloged_at' => now(),
        ]);

        return $this;
    }

    /**
     * Mark document as error.
     */
    public function markAsError(string $errorMessage = null): self
    {
        $meta = $this->metadata ?? [];
        $meta['last_error'] = $errorMessage;
        $meta['error_at'] = now()->toIso8601String();

        $this->update([
            'status' => self::STATUS_ERROR,
            'metadata' => $meta,
        ]);

        return $this;
    }
}
