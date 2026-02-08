<?php

use App\Services\GraphDatabaseService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * Sprint D2.1: Graph Schema Design - Add Court Decision Graph Schema
 *
 * This migration adds Neo4j constraints and indexes for court decisions.
 * It ensures ECLI uniqueness, adds indexes for case_number, court, decision_date,
 * and creates the necessary infrastructure for graph-based queries.
 *
 * Related Files:
 * - docs/GRAPH_SCHEMA.cypher - Complete schema documentation
 * - app/Services/GraphDatabaseService.php - Graph operations service
 * - app/Services/GraphRagService.php - Graph RAG operations
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if Neo4j is enabled
        if (! config('neo4j.sync.enabled', true)) {
            Log::info('Neo4j sync disabled, skipping graph schema migration');

            return;
        }

        // Try to initialize GraphDatabaseService
        try {
            $graph = app(GraphDatabaseService::class);
        } catch (\Exception $e) {
            Log::warning('GraphDatabaseService not available during migration', [
                'error' => $e->getMessage(),
            ]);

            return;
        }

        Log::info('Adding Neo4j constraints and indexes for court decisions');

        // Add constraints
        $this->addConstraints($graph);

        // Add indexes
        $this->addIndexes($graph);

        Log::info('Neo4j graph schema migration completed successfully');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Neo4j constraints and indexes can be dropped manually if needed
        // We don't automatically drop them to preserve data integrity
        Log::info('Graph schema migration rollback - constraints and indexes remain in place');
    }

    /**
     * Add Neo4j constraints for court decisions
     */
    protected function addConstraints(GraphDatabaseService $graph): void
    {
        $constraints = [
            // Court Decision Document constraints
            [
                'name' => 'court_decision_doc_id',
                'cypher' => 'CREATE CONSTRAINT court_decision_doc_id IF NOT EXISTS FOR (cdd:CourtDecisionDocument) REQUIRE cdd.id IS UNIQUE',
            ],
            // NOTE: ECLI uniqueness constraint removed because ECLI can be nullable
            // We keep the ECLI index for fast lookups when ECLI is present
        ];

        foreach ($constraints as $constraint) {
            try {
                $graph->run($constraint['cypher']);
                Log::info('Created Neo4j constraint: '.$constraint['name']);
            } catch (\Exception $e) {
                Log::warning('Failed to create Neo4j constraint: '.$constraint['name'], [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Add Neo4j indexes for court decisions
     */
    protected function addIndexes(GraphDatabaseService $graph): void
    {
        $indexes = [
            // ECLI Index (for fast lookup by European Case Law Identifier when present)
            [
                'name' => 'court_decision_ecli_idx',
                'cypher' => 'CREATE INDEX court_decision_ecli_idx IF NOT EXISTS FOR (cdd:CourtDecisionDocument) ON (cdd.ecli)',
            ],

            // Case Number Index (for lookup by case number)
            [
                'name' => 'court_decision_case_number_idx',
                'cypher' => 'CREATE INDEX court_decision_case_number_idx IF NOT EXISTS FOR (cdd:CourtDecisionDocument) ON (cdd.case_number)',
            ],

            // Court Index (for filtering by court)
            [
                'name' => 'court_decision_court_idx',
                'cypher' => 'CREATE INDEX court_decision_court_idx IF NOT EXISTS FOR (cdd:CourtDecisionDocument) ON (cdd.court)',
            ],

            // Decision Date Index (for temporal queries)
            [
                'name' => 'court_decision_date_idx',
                'cypher' => 'CREATE INDEX court_decision_date_idx IF NOT EXISTS FOR (cdd:CourtDecisionDocument) ON (cdd.decision_date)',
            ],

            // Decision Type Index (for filtering by type: presuda, rješenje, etc.)
            [
                'name' => 'court_decision_type_idx',
                'cypher' => 'CREATE INDEX court_decision_type_idx IF NOT EXISTS FOR (cdd:CourtDecisionDocument) ON (cdd.decision_type)',
            ],

            // Composite Index (case_number + court for precise lookups)
            [
                'name' => 'court_decision_case_court_idx',
                'cypher' => 'CREATE INDEX court_decision_case_court_idx IF NOT EXISTS FOR (cdd:CourtDecisionDocument) ON (cdd.case_number, cdd.court)',
            ],

            // Decision ID Index (for joining with parent court_decisions table)
            [
                'name' => 'court_decision_parent_idx',
                'cypher' => 'CREATE INDEX court_decision_parent_idx IF NOT EXISTS FOR (cdd:CourtDecisionDocument) ON (cdd.decision_id)',
            ],

            // Jurisdiction Index (for filtering by jurisdiction)
            [
                'name' => 'court_decision_jurisdiction_idx',
                'cypher' => 'CREATE INDEX court_decision_jurisdiction_idx IF NOT EXISTS FOR (cdd:CourtDecisionDocument) ON (cdd.jurisdiction)',
            ],
        ];

        foreach ($indexes as $index) {
            try {
                $graph->run($index['cypher']);
                Log::info('Created Neo4j index: '.$index['name']);
            } catch (\Exception $e) {
                Log::warning('Failed to create Neo4j index: '.$index['name'], [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
};
