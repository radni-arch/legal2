<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('case_analyses', function (Blueprint $table) {
            $table->id();
            $table->string('case_id');         // references your case identifier
            $table->string('analysis_type');    // 'timeline', 'contradictions', 'strategy', 'summary'
            $table->string('status')->default('pending');
            $table->json('results')->nullable();
            $table->json('metadata')->nullable();
            $table->json('document_ids')->nullable(); // which documents contributed
            $table->text('error_message')->nullable();
            $table->integer('version')->default(1);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['case_id', 'analysis_type', 'version']);
            $table->index(['status', 'analysis_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_analyses');
    }
};
