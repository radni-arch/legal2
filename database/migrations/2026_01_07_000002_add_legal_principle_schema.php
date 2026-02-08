<?php

use App\Services\GraphDatabaseService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * Migration to add LegalPrinciple node type to Neo4j graph schema
 *
 * Phase 2 Enhancement - Task 2.3
 * Creates constraints and indexes for LegalPrinciple nodes
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! config('neo4j.sync.enabled', true)) {
            Log::info('Neo4j sync disabled, skipping LegalPrinciple schema migration');

            return;
        }

        try {
            $graph = app(GraphDatabaseService::class);
        } catch (\Exception $e) {
            Log::warning('Neo4j not available for LegalPrinciple schema migration', [
                'error' => $e->getMessage(),
            ]);

            return;
        }

        try {
            // Add LegalPrinciple unique constraint
            $graph->run('CREATE CONSTRAINT legal_principle_id IF NOT EXISTS FOR (lp:LegalPrinciple) REQUIRE lp.id IS UNIQUE');
            Log::info('Created LegalPrinciple ID constraint');

            // Add LegalPrinciple indexes
            $graph->run('CREATE INDEX legal_principle_name_idx IF NOT EXISTS FOR (lp:LegalPrinciple) ON (lp.name)');
            $graph->run('CREATE INDEX legal_principle_category_idx IF NOT EXISTS FOR (lp:LegalPrinciple) ON (lp.category)');
            Log::info('Created LegalPrinciple indexes');

            Log::info('LegalPrinciple node schema successfully added to Neo4j');
        } catch (\Exception $e) {
            Log::error('Failed to create LegalPrinciple schema in Neo4j', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function down(): void
    {
        Log::info('LegalPrinciple schema rollback - constraints remain in place for data safety');
    }
};
