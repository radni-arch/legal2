<?php

namespace App\Models;

use App\Casts\VectorCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AgentVectorMemory extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table;

    protected $fillable = [
        'id',
        'agent_name',
        'namespace',
        'objective',
        'content',
        'metadata',
        'source',
        'source_id',
        'chunk_index',
        'embedding_provider',
        'embedding_model',
        'embedding_dimensions',
        'embedding_norm',
        'content_hash',
        'token_count',
        'embedding_vector', // JSON array storage (pgvector not installed)
        'embedding', // pgvector column (when extension is available)
        'access_count', // Sprint 5.7: Track how often memory is accessed
    ];

    protected $casts = [
        'metadata' => 'array',
        'embedding_vector' => VectorCast::class,
        'embedding' => VectorCast::class,
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('vizra-adk.tables.agent_vector_memories', 'agent_vector_memories');
    }

    /**
     * Boot the model with event listeners to auto-fill required fields
     */
    protected static function booted()
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::ulid();
            }
            if (empty($model->namespace)) {
                $model->namespace = 'default';
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
                // Set as array - Laravel will handle JSON encoding for the database
                $model->embedding_vector = array_fill(0, 1536, 0.0);
            }
            if (empty($model->content_hash)) {
                $model->content_hash = hash('sha256', $model->content ?? '');
            }
            if (! isset($model->access_count)) {
                $model->access_count = 0;
            }
        });
    }
}
