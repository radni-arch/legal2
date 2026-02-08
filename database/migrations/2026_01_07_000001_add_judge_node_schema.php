<?php

use App\Services\GraphDatabaseService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * Migration to add Judge node type to Neo4j graph schema
 *
 * Phase 2 Enhancement - Task 2.1
 * Creates constraints and indexes for Judge nodes
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! config('neo4j.sync.enabled', true)) {
            Log::info('Neo4j sync disabled, skipping Judge schema migration');

            return;
        }

        try {
            $graph = app(GraphDatabaseService::class);
        } catch (\Exception $e) {
            Log::warning('Neo4j not available for Judge schema migration', [
                'error' => $e->getMessage(),
            ]);

            return;
        }

        try {
            // Add Judge unique constraint
            $graph->run('CREATE CONSTRAINT judge_id IF NOT EXISTS FOR (j:Judge) REQUIRE j.id IS UNIQUE');
            Log::info('Created Judge ID constraint');

            // Add Judge indexes
            $graph->run('CREATE INDEX judge_name_idx IF NOT EXISTS FOR (j:Judge) ON (j.name)');
            $graph->run('CREATE INDEX judge_court_idx IF NOT EXISTS FOR (j:Judge) ON (j.court)');
            Log::info('Created Judge indexes');

            Log::info('Judge node schema successfully added to Neo4j');
        } catch (\Exception $e) {
            Log::error('Failed to create Judge schema in Neo4j', [
                'error' => $e->getMessage(),
            ]);
            // Don't throw - allow migration to continue for PostgreSQL-only environments
        }
    }

    public function down(): void
    {
        // Note: Neo4j constraints are typically not dropped during rollback
        // to prevent accidental data integrity issues
        Log::info('Judge schema rollback - constraints remain in place for data safety');
    }
};
