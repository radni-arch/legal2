<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_contexts', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('generation_run_id', 26);
            $table->string('context_type', 50); // standalone, case_data, mixed
            $table->text('raw_input')->nullable();
            $table->text('assembled_context')->nullable();
            $table->json('case_ids')->nullable();
            $table->json('evidence_ids')->nullable();
            $table->json('decision_ids')->nullable();
            $table->json('law_ids')->nullable();
            $table->timestamp('created_at');

            $table->foreign('generation_run_id')
                ->references('id')
                ->on('document_generation_runs')
                ->onDelete('cascade');

            $table->index('generation_run_id');
            $table->index('context_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_contexts');
    }
};
