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
        $tableName = config('vizra-adk.tables.agent_vector_memories', 'agent_vector_memories');

        if (! Schema::hasTable($tableName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) {
            // Add objective column for faster querying
            // This allows efficient filtering of insights by research objective
            $table->text('objective')->nullable()->after('namespace');

            // Add index on objective for query performance
            // This enables fast lookups when retrieving recent insights for a given objective
            $table->index('objective');

            // Add composite index for common query pattern: agent_name + objective + created_at
            // This optimizes the getRecentInsights() query which filters by agent and objective
            // and orders by recency
            $table->index(['agent_name', 'objective', 'created_at'], 'agent_objective_recency_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('vizra-adk.tables.agent_vector_memories', 'agent_vector_memories');

        if (! Schema::hasTable($tableName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex('agent_objective_recency_idx');
            $table->dropIndex(['objective']);

            // Drop column
            $table->dropColumn('objective');
        });
    }
};
