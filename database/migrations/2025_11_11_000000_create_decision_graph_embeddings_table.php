<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Decision Graph Embeddings Table (Sprint 4.5)
 *
 * Creates table to store Node2Vec graph embeddings for court decisions.
 * These embeddings capture structural similarity in the citation graph,
 * enabling hybrid search combining content + graph structure.
 *
 * Schema:
 * - decision_id: Foreign key to court_decisions table
 * - graph_embedding: 128-dimensional Node2Vec embedding (pgvector)
 * - trained_at: Timestamp when embedding was generated
 * - model_version: Version of Node2Vec model used
 */
return new class extends Migration
{
    public function up(): void
    {
        // Ensure pgvector extension is enabled
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        Schema::create('decision_graph_embeddings', function (Blueprint $table) {
            $table->string('decision_id', 100)->primary();
            $table->timestamp('trained_at')->nullable();
            $table->string('model_version', 50)->nullable();
            $table->timestamps();

            // Foreign key constraint to court_decisions
            $table->foreign('decision_id')
                ->references('id')
                ->on('court_decisions')
                ->onDelete('cascade');

            // Index for efficient lookups
            $table->index('trained_at');
            $table->index('model_version');
        });

        // Add vector column using raw SQL (pgvector syntax)
        DB::statement('ALTER TABLE decision_graph_embeddings ADD COLUMN graph_embedding vector(128)');

        // Create index for vector similarity search
        DB::statement('CREATE INDEX decision_graph_embeddings_embedding_idx ON decision_graph_embeddings USING ivfflat (graph_embedding vector_cosine_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('decision_graph_embeddings');
    }
};
