<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opponent_responses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('generation_run_id', 26)->nullable();
            $table->string('original_profile_key');
            $table->string('responder_type');
            $table->string('original_filename')->nullable();
            $table->string('source_url')->nullable();
            $table->text('raw_content');
            $table->text('summary');
            $table->jsonb('key_arguments');
            $table->jsonb('weaknesses');
            $table->jsonb('recommended_counters');
            $table->string('counter_profile_key')->nullable();
            $table->char('counter_run_id', 26)->nullable();
            $table->timestamps();

            $table->foreign('generation_run_id')
                ->references('id')
                ->on('document_generation_runs')
                ->nullOnDelete();

            $table->foreign('counter_run_id')
                ->references('id')
                ->on('document_generation_runs')
                ->nullOnDelete();

            $table->index('original_profile_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opponent_responses');
    }
};
