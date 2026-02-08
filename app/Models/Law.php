<?php

namespace App\Models;

use App\Casts\VectorCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Law extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table;

    protected $fillable = [
        'id', 'doc_id', 'ingested_law_id', 'title', 'law_number', 'jurisdiction', 'country', 'language',
        'promulgation_date', 'effective_date', 'repeal_date', 'version', 'chapter', 'section',
        'tags', 'source_url', 'chunk_index', 'content', 'metadata', 'embedding_provider',
        'embedding_model', 'embedding_dimensions', 'embedding_norm', 'content_hash', 'token_count',
        'embedding', 'embedding_vector', // Support both pgvector and JSON storage
        // Phase 4: Amendment tracking
        'amendments', 'repealed_by', 'parent_law_number', 'consolidation_date',
    ];

    protected $casts = [
        'tags' => 'array',
        'metadata' => 'array',
        'embedding' => VectorCast::class,  // Use VectorCast for pgvector compatibility
        'embedding_vector' => 'array',  // Support JSON storage when pgvector not available
        'promulgation_date' => 'date',
        'effective_date' => 'date',
        'repeal_date' => 'date',
        // Phase 4: Amendment tracking
        'amendments' => 'array',
        'consolidation_date' => 'date',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('vizra-adk.tables.laws', 'laws');
    }

    public function ingestedLaw()
    {
        return $this->belongsTo(IngestedLaw::class, 'ingested_law_id');
    }

    /**
     * Boot the model with event listeners for graph database synchronization
     */
    protected static function booted()
    {
        // Set default values for required fields if not provided
        static::creating(function ($law) {
            if (empty($law->embedding_provider)) {
                $law->embedding_provider = 'openai';
            }
            if (empty($law->embedding_model)) {
                $law->embedding_model = config('openai.models.embeddings', 'text-embedding-3-small');
            }
            if (empty($law->embedding_dimensions)) {
                $law->embedding_dimensions = 1536;
            }
            if (empty($law->content_hash)) {
                $law->content_hash = hash('sha256', $law->content ?? '');
            }
            // Note: Default embedding value handled by database migration or factory
        });

        // Handle embedding column based on driver and pgvector availability
        static::saving(function ($law) {
            // Check which embedding column exists in the database
            $hasVectorColumn = false;
            $hasJsonColumn = false;

            try {
                $columns = \DB::getSchemaBuilder()->getColumnListing($law->getTable());
                $hasVectorColumn = in_array('embedding', $columns);
                $hasJsonColumn = in_array('embedding_vector', $columns);
            } catch (\Exception $e) {
                // Ignore schema check errors
            }

            // If we have data in one format but need the other, convert
            if ($hasVectorColumn && ! $hasJsonColumn) {
                // Remove embedding_vector if it exists in attributes
                unset($law->attributes['embedding_vector']);
            } elseif ($hasJsonColumn && ! $hasVectorColumn) {
                // Remove embedding if it exists in attributes
                unset($law->attributes['embedding']);
            }
        });

        static::updated(function ($law) {
            if (config('neo4j.sync.auto_sync')) {
                app(\App\Services\Graph\GraphRagOrchestrator::class)->syncLaw($law->id);
            }
        });

        static::deleted(function ($law) {
            if (config('neo4j.sync.enabled')) {
                app(\App\Services\GraphDatabaseService::class)->deleteNode('LawDocument', $law->id);
            }
        });
    }
}
