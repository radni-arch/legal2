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
        Schema::create('agent_insight_events', function (Blueprint $table) {
            $table->id();

            // Agent and run information
            $table->string('agent_name')->index();
            $table->unsignedBigInteger('agent_run_id')->nullable();
            $table->foreign('agent_run_id')
                ->references('id')
                ->on('agent_runs')
                ->onDelete('set null');

            // Insight content
            $table->text('insight');
            $table->string('objective')->nullable()->index();

            // Event metadata
            $table->string('severity')->default('info')->index();
            // Severity levels: info, warning, critical

            $table->float('relevance_score')->nullable();
            $table->json('metadata')->nullable();

            // Source tracking
            $table->string('source')->nullable();
            $table->string('source_id')->nullable();

            $table->timestamps();

            // Composite index for common queries
            // Query pattern: Get events for agent, ordered by recency
            $table->index(['agent_name', 'created_at'], 'agent_events_recency_idx');

            // Index for severity-based filtering
            $table->index(['severity', 'created_at'], 'severity_recency_idx');

            // Index for objective-based filtering
            $table->index(['objective', 'created_at'], 'objective_events_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_insight_events');
    }
};
