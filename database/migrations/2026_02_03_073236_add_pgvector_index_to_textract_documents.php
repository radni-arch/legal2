<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Add pgvector index to textract_documents table for efficient similarity search.
 *
 * This migration:
 * 1. Converts the embedding column from JSON to pgvector vector type
 * 2. Adds an IVFFlat index for fast cosine similarity searches
 *
 * The IVFFlat index with vector_cosine_ops is optimized for cosine distance (<=>)
 * queries. Lists=100 is a good starting point for tables with thousands of rows.
 *
 * Performance notes:
 * - IVFFlat requires building clusters, so it works best with existing data
 * - For empty tables, consider running REINDEX after data is loaded
 * - The index speeds up queries from O(n) to approximately O(n/lists)
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Converts embedding column to vector type and adds IVFFlat index.
     */
    public function up(): void
    {
        // Only run on PostgreSQL
        if (DB::connection()->getDriverName() !== 'pgsql') {
            Log::info('Skipping pgvector migration - not using PostgreSQL');

            return;
        }

        // Check if pgvector extension is available
        $pgvectorExists = DB::select("SELECT 1 FROM pg_extension WHERE extname = 'vector' LIMIT 1");
        if (empty($pgvectorExists)) {
            Log::warning('pgvector extension not installed - skipping index creation');

            return;
        }

        // Check if embedding column exists and is JSON type
        $columnInfo = DB::select(
            "SELECT data_type FROM information_schema.columns WHERE table_name = 'textract_documents' AND column_name = 'embedding'"
        );

        if (empty($columnInfo)) {
            Log::warning('embedding column not found on textract_documents table');

            return;
        }

        $currentType = strtolower($columnInfo[0]->data_type ?? '');

        // Only convert if currently JSON/JSONB (not already vector)
        if (in_array($currentType, ['json', 'jsonb'])) {
            // Convert JSON embedding column to vector type
            // First, add a temporary vector column
            DB::statement('ALTER TABLE textract_documents ADD COLUMN IF NOT EXISTS embedding_temp vector(1536)');

            // Copy data from JSON to vector (for rows that have embeddings)
            DB::statement("
                UPDATE textract_documents
                SET embedding_temp = embedding::text::vector
                WHERE embedding IS NOT NULL
                  AND jsonb_typeof(embedding::jsonb) = 'array'
                  AND jsonb_array_length(embedding::jsonb) = 1536
            ");

            // Drop old column and rename new one
            DB::statement('ALTER TABLE textract_documents DROP COLUMN embedding');
            DB::statement('ALTER TABLE textract_documents RENAME COLUMN embedding_temp TO embedding');

            Log::info('Converted textract_documents.embedding from JSON to vector(1536)');
        }

        // Add IVFFlat index for cosine similarity search
        // lists = 100 is a good balance for tables up to ~100k rows
        DB::statement('CREATE INDEX IF NOT EXISTS idx_textract_documents_embedding ON textract_documents USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100)');

        Log::info('Created IVFFlat index on textract_documents.embedding for cosine similarity search');
    }

    /**
     * Reverse the migrations.
     *
     * Drops the index and converts embedding back to JSON.
     */
    public function down(): void
    {
        // Only run on PostgreSQL
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Drop the index first
        DB::statement('DROP INDEX IF EXISTS idx_textract_documents_embedding');

        // Check if embedding is currently vector type
        $columnInfo = DB::select(
            "SELECT data_type FROM information_schema.columns WHERE table_name = 'textract_documents' AND column_name = 'embedding'"
        );

        if (! empty($columnInfo) && strtolower($columnInfo[0]->data_type ?? '') === 'user-defined') {
            // Convert back to JSON
            DB::statement('ALTER TABLE textract_documents ADD COLUMN IF NOT EXISTS embedding_temp json');

            // Copy data back to JSON format
            DB::statement("
                UPDATE textract_documents
                SET embedding_temp = embedding::text::json
                WHERE embedding IS NOT NULL
            ");

            DB::statement('ALTER TABLE textract_documents DROP COLUMN embedding');
            DB::statement('ALTER TABLE textract_documents RENAME COLUMN embedding_temp TO embedding');

            Log::info('Reverted textract_documents.embedding from vector to JSON');
        }
    }
};
