<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_reasoning_traces', function (Blueprint $table) {
            $table->id();
            $table->uuid('trace_id')->unique();
            $table->uuid('parent_trace_id')->nullable()->index();
            $table->uuid('collaboration_id')->nullable()->index();
            $table->string('agent_type', 100)->nullable()->index();
            $table->string('step_type', 50)->nullable();
            $table->string('operation', 100)->nullable();
            $table->json('input_data')->nullable();
            $table->json('output_data')->nullable();
            $table->text('reasoning')->nullable();
            $table->decimal('confidence', 3, 2)->nullable();
            $table->integer('tokens_used')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->timestamps();

            // Add foreign key constraint for parent_trace_id
            // Note: This references trace_id (UUID) not id (BIGINT)
            // Laravel doesn't support this directly in Blueprint, so we use raw SQL
            // $table->foreign('parent_trace_id')->references('trace_id')->on('ai_reasoning_traces')->onDelete('cascade');

            // Indexes for performance
            $table->index('created_at');
            $table->index(['agent_type', 'step_type']);
        });

        // Add foreign key using raw SQL for UUID reference (only for PostgreSQL/MySQL)
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE ai_reasoning_traces ADD CONSTRAINT fk_parent_trace
                FOREIGN KEY (parent_trace_id) REFERENCES ai_reasoning_traces(trace_id) ON DELETE CASCADE');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign key constraint first (only for databases that support it)
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE ai_reasoning_traces DROP CONSTRAINT IF EXISTS fk_parent_trace');
        }

        Schema::dropIfExists('ai_reasoning_traces');
    }
};
