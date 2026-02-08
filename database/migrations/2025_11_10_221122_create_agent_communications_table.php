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
        Schema::create('agent_communications', function (Blueprint $table) {
            $table->id();
            $table->uuid('communication_id')->unique();
            $table->uuid('trace_id')->nullable()->index();
            $table->uuid('collaboration_id')->nullable()->index();
            $table->string('sender_agent_type', 100)->nullable()->index();
            $table->string('receiver_agent_type', 100)->nullable()->index();
            $table->string('message_type', 50)->nullable();
            $table->json('message_data')->nullable();
            $table->json('response_data')->nullable();
            $table->string('status', 50)->nullable()->default('pending');
            $table->integer('duration_ms')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index('created_at');
            $table->index(['sender_agent_type', 'receiver_agent_type']);
            $table->index('status');
        });

        // Add foreign key to ai_reasoning_traces (only for PostgreSQL/MySQL)
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE agent_communications ADD CONSTRAINT fk_communication_trace
                FOREIGN KEY (trace_id) REFERENCES ai_reasoning_traces(trace_id) ON DELETE CASCADE');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign key constraint first (only for databases that support it)
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE agent_communications DROP CONSTRAINT IF EXISTS fk_communication_trace');
        }

        Schema::dropIfExists('agent_communications');
    }
};
