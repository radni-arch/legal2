<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vector_documents', function (Blueprint $table) {
            $table->id();
            $table->string('file_name');
            $table->string('file_path')->nullable();
            $table->string('openai_file_id')->nullable()->index();
            $table->string('vector_store_id')->nullable()->index();
            $table->string('case_id')->nullable()->index();

            // State machine: pending → tagged → uploaded → cataloged
            $table->string('status')->default('pending')->index();

            // Metadata from AI tagger
            $table->jsonb('metadata')->nullable();
            $table->jsonb('attributes')->nullable();
            $table->jsonb('catalog_entry')->nullable();

            // Tracking
            $table->timestamp('tagged_at')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamp('cataloged_at')->nullable();
            $table->string('tagger_model')->nullable();
            $table->float('confidence')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['file_name', 'vector_store_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vector_documents');
    }
};
