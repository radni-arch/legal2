<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CaseDocument extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table;

    protected $fillable = [
        'id', 'case_id', 'doc_id', 'upload_id', 'title', 'category', 'author', 'language', 'tags',
        'chunk_index', 'content', 'metadata', 'actual', 'source', 'source_id', 'embedding_provider',
        'embedding_model', 'embedding_dimensions', 'embedding_norm', 'content_hash', 'token_count',
        'embedding_vector', 'embedding', 'document_date',
    ];

    protected $casts = [
        'tags' => 'array',
        'metadata' => 'array',
        'actual' => 'array',
        'embedding_vector' => 'array',
        'document_date' => 'datetime',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('vizra-adk.tables.cases_documents', 'cases_documents');
    }

    public function case()
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function upload()
    {
        return $this->belongsTo(CaseDocumentUpload::class, 'upload_id');
    }

    /**
     * Get all analyses for this document.
     */
    public function analyses(): HasMany
    {
        return $this->hasMany(DocumentAnalysis::class);
    }

    /**
     * Get the related chunks (if document is chunked).
     */
    public function chunks()
    {
        return $this->hasMany(CaseDocument::class, 'doc_id', 'doc_id')
            ->where('id', '!=', $this->id)
            ->orderBy('chunk_index');
    }

    /**
     * Get the related Textract job (if any).
     */
    public function textractJob()
    {
        return $this->hasOne(\App\Models\TextractDocument::class, 'case_document_id');
    }

    /**
     * Get the latest completed analysis of the given type.
     */
    public function latestAnalysis(string $type): ?DocumentAnalysis
    {
        return $this->analyses()
            ->ofType($type)
            ->completed()
            ->orderByDesc('version')
            ->first();
    }

    /**
     * Check if this document has a completed analysis of the given type.
     */
    public function hasCompletedAnalysis(string $type): bool
    {
        return $this->analyses()
            ->ofType($type)
            ->completed()
            ->exists();
    }

    /**
     * Boot the model with event listeners for graph database synchronization
     */
    protected static function booted()
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::ulid();
            }
            if (empty($model->doc_id)) {
                $model->doc_id = 'doc-'.(string) Str::ulid();
            }
            if (empty($model->embedding_provider)) {
                $model->embedding_provider = 'openai';
            }
            if (empty($model->embedding_model)) {
                $model->embedding_model = 'text-embedding-3-small';
            }
            if (empty($model->embedding_dimensions)) {
                $model->embedding_dimensions = 1536;
            }
            if (empty($model->embedding_vector)) {

                // Don't JSON encode - Laravel's array cast will handle it
                // For pgvector, pass array directly
                $model->embedding_vector = array_fill(0, 1536, 0.0);
            }
            if (empty($model->content_hash)) {
                $model->content_hash = hash('sha256', $model->content ?? '');
            }
        });

        static::saving(function ($caseDocument) {
            // Handle embedding column based on driver and pgvector availability
            $hasVectorColumn = false;
            $hasJsonColumn = false;

            try {
                $columns = \DB::getSchemaBuilder()->getColumnListing($caseDocument->getTable());
                $hasVectorColumn = in_array('embedding', $columns);
                $hasJsonColumn = in_array('embedding_vector', $columns);
            } catch (\Exception $e) {
                // Ignore schema check errors
            }

            // If we have pgvector (embedding column), remove embedding_vector from attributes
            if ($hasVectorColumn && ! $hasJsonColumn) {
                unset($caseDocument->attributes['embedding_vector']);
            } elseif ($hasJsonColumn && ! $hasVectorColumn) {
                unset($caseDocument->attributes['embedding']);
            }
        });

        static::updated(function ($caseDocument) {
            if (config('neo4j.sync.auto_sync')) {
                app(\App\Services\Graph\GraphRagOrchestrator::class)->syncCase($caseDocument->id);
            }
        });

        static::deleted(function ($caseDocument) {
            if (config('neo4j.sync.enabled')) {
                app(\App\Services\GraphDatabaseService::class)->deleteNode('CaseDocument', $caseDocument->id);
            }
        });
    }
}
