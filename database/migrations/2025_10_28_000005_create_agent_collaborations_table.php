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
        Schema::create('agent_collaborations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('session_id')->index();
            $table->string('orchestrator')->nullable();
            $table->string('problem_type')->nullable();
            $table->text('problem_statement');
            $table->json('context')->nullable();
            $table->string('status')->default('in_progress'); // in_progress, completed, failed
            $table->json('agents_involved')->nullable(); // Array of agent names
            $table->json('execution_plan')->nullable();
            $table->json('shared_memory')->nullable(); // Shared context between agents
            $table->json('agent_outputs')->nullable(); // Individual agent results
            $table->json('final_result')->nullable();
            $table->text('synthesis')->nullable(); // Final synthesized answer
            $table->integer('total_steps')->default(0);
            $table->integer('completed_steps')->default(0);
            $table->integer('tokens_used')->default(0);
            $table->decimal('cost_spent', 10, 4)->default(0);
            $table->integer('duration_seconds')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('orchestrator');
            $table->index('started_at');
        });

        // Individual agent execution tracking
        Schema::create('agent_executions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('collaboration_id');
            $table->string('agent_name');
            $table->string('agent_role')->nullable();
            $table->integer('execution_order')->default(0);
            $table->string('status')->default('pending'); // pending, running, completed, failed
            $table->text('task_description')->nullable();
            $table->json('input_context')->nullable();
            $table->json('output')->nullable();
            $table->json('messages_to_others')->nullable(); // Messages sent to other agents
            $table->json('messages_from_others')->nullable(); // Messages received from other agents
            $table->integer('tokens_used')->default(0);
            $table->decimal('cost_spent', 10, 4)->default(0);
            $table->integer('duration_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('collaboration_id')
                ->references('id')
                ->on('agent_collaborations')
                ->onDelete('cascade');

            $table->index(['collaboration_id', 'execution_order']);
            $table->index('agent_name');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_executions');
        Schema::dropIfExists('agent_collaborations');
    }
};
