<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates textract_documents table for storing chunked content with embeddings.
     */
    public function up(): void
    {
        Schema::create('textract_documents', function (Blueprint $table) {
            $table->id();

            // Foreign keys
            $table->unsignedBigInteger('textract_job_id')->index();
            $table->string('case_id')->index();

            // Content chunks
            $table->text('content')->comment('Chunked text content');
            $table->integer('chunk_index')->default(0)
                ->comment('Order of this chunk in the document');
            $table->integer('chunk_overlap')->default(0)
                ->comment('Number of characters overlapping with previous chunk');

            // Vector embedding storage
            // Using JSON for flexibility - can be migrated to pgvector extension later
            $table->json('embedding')->nullable()
                ->comment('Vector embedding as JSON array');

            // Embedding metadata
            $table->string('embedding_provider')->nullable()
                ->comment('Provider: openai, cohere, huggingface, etc.');
            $table->string('embedding_model')->nullable()
                ->comment('Model name: text-embedding-ada-002, etc.');
            $table->integer('embedding_dimensions')->nullable()
                ->comment('Vector dimensions (e.g., 1536 for ada-002)');
            $table->integer('token_count')->nullable()
                ->comment('Number of tokens in this chunk');

            // Processing metadata
            $table->string('processing_status')->default('pending')
                ->comment('Status: pending|processing|completed|failed');
            $table->text('processing_error')->nullable();
            $table->timestamp('embedded_at')->nullable()
                ->comment('When the embedding was generated');

            // Additional metadata
            $table->json('metadata')->nullable()
                ->comment('Additional chunk metadata (page numbers, section info, etc.)');

            $table->timestamps();

            // Foreign key constraints
            $table->foreign('textract_job_id')
                ->references('id')->on('textract_jobs')
                ->onDelete('cascade');

            // Indexes for efficient queries
            $table->index(['textract_job_id', 'chunk_index']);
            $table->index('processing_status');
            $table->index(['case_id', 'processing_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('textract_documents');
    }
};
