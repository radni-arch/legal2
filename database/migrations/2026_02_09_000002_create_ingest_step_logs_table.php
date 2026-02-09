<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingest_step_logs', function (Blueprint $table) {
            $table->id();
            $table->string('ingest_run_id');
            $table->foreign('ingest_run_id')
                ->references('id')
                ->on('ingest_runs')
                ->cascadeOnDelete();
            $table->string('step_name'); // upload, ocr, extraction, analysis
            $table->string('status'); // started, completed, failed, skipped
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('metadata')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['ingest_run_id', 'step_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingest_step_logs');
    }
};
