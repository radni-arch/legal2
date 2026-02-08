<?php

use App\Services\GraphDatabaseService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * Migration to add Party node type to Neo4j graph schema
 *
 * Phase 2 Enhancement - Task 2.4
 * Creates constraints and indexes for Party nodes
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! config('neo4j.sync.enabled', true)) {
            Log::info('Neo4j sync disabled, skipping Party schema migration');

            return;
        }

        try {
            $graph = app(GraphDatabaseService::class);
        } catch (\Exception $e) {
            Log::warning('Neo4j not available for Party schema migration', [
                'error' => $e->getMessage(),
            ]);

            return;
        }

        try {
            // Add Party unique constraint
            $graph->run('CREATE CONSTRAINT party_id IF NOT EXISTS FOR (p:Party) REQUIRE p.id IS UNIQUE');
            Log::info('Created Party ID constraint');

            // Add Party indexes
            $graph->run('CREATE INDEX party_name_idx IF NOT EXISTS FOR (p:Party) ON (p.name)');
            $graph->run('CREATE INDEX party_type_idx IF NOT EXISTS FOR (p:Party) ON (p.party_type)');
            Log::info('Created Party indexes');

            Log::info('Party node schema successfully added to Neo4j');
        } catch (\Exception $e) {
            Log::error('Failed to create Party schema in Neo4j', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function down(): void
    {
        Log::info('Party schema rollback - constraints remain in place for data safety');
    }
};
