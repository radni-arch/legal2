<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('document_analyses')) {
            return;
        }

        Schema::create('document_analyses', function (Blueprint $table) {
            $table->id();
            $table->string('case_document_id');
            $table->foreign('case_document_id')
                ->references('id')
                ->on('cases_documents')
                ->cascadeOnDelete();
            $table->string('analysis_layer');  // 'extraction', 'pattern', 'ai_basic', 'ai_deep'
            $table->string('analysis_type');   // 'keywords', 'entities', 'dates', 'timeline', 'contradictions', etc.
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->json('results')->nullable();
            $table->json('metadata')->nullable(); // token count, processing time, model used, etc.
            $table->text('error_message')->nullable();
            $table->integer('version')->default(1);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['case_document_id', 'analysis_type', 'version']);
            $table->index(['status', 'analysis_layer']);
            $table->index(['case_document_id', 'analysis_layer']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_analyses');
    }
};
