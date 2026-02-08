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
        if (Schema::hasTable('orchestration_logs')) {
            return;
        }

        Schema::create('orchestration_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('orchestration_id')->unique();
            $table->string('task_description')->nullable();
            $table->json('agent_pipeline')->nullable(); // Ordered list of agents to execute
            $table->json('shared_context')->nullable(); // Context passed between agents
            $table->json('execution_history')->nullable(); // Full execution log
            $table->string('status')->default('pending'); // pending, running, completed, failed
            $table->integer('total_agents')->default(0);
            $table->integer('completed_agents')->default(0);
            $table->integer('failed_agents')->default(0);
            $table->integer('tokens_used')->default(0);
            $table->decimal('cost_spent', 10, 4)->default(0);
            $table->integer('duration_ms')->nullable();
            $table->integer('token_budget')->nullable(); // Max tokens allowed
            $table->decimal('cost_budget', 10, 4)->nullable(); // Max cost allowed
            $table->integer('time_budget_ms')->nullable(); // Max time allowed
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('orchestration_id');
            $table->index('status');
            $table->index('started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orchestration_logs');
    }
};
