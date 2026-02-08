<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_iterations', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('generation_run_id', 26);
            $table->integer('iteration_number');
            $table->string('phase', 50); // critic, worker
            $table->text('document_version')->nullable();
            $table->json('critic_feedback')->nullable();
            $table->json('scores')->nullable();
            $table->decimal('weighted_score', 5, 2)->nullable();
            $table->decimal('improvement_delta', 5, 2)->nullable();
            $table->string('ai_model_used', 100)->nullable();
            $table->integer('tokens_used')->nullable();
            $table->decimal('cost_estimate', 10, 4)->nullable();
            $table->timestamp('created_at');

            $table->foreign('generation_run_id')
                ->references('id')
                ->on('document_generation_runs')
                ->onDelete('cascade');

            $table->index(['generation_run_id', 'iteration_number']);
            $table->index('phase');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_iterations');
    }
};
