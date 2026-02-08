<?php

namespace Tests\Integration;

use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * GraphDbExtendedInvariantsTest
 *
 * Extends the Neo4j <-> relational DB invariants with:
 *  - keyword node correctness and relationship weights
 *  - relationship timestamp presence (created_at)
 *  - textract job graph sync completeness
 *
 * All tests are SKIP-safe when Neo4j is disabled or unavailable.
 */
class GraphDbExtendedInvariantsTest extends TestCase
{
    use UsesTestDatabase;

    private function graph(): GraphDatabaseService
    {
        /** @var GraphDatabaseService $svc */
        $svc = app(GraphDatabaseService::class);

        return $svc;
    }

    private function requireGraphOrSkip(): void
    {
        if (! config('neo4j.sync.enabled', true)) {
            $this->markTestSkipped('Neo4j sync is disabled (neo4j.sync.enabled=false)');
        }

        $graph = $this->graph();
        if (! $graph->isAvailable()) {
            $this->markTestSkipped('Neo4j is not available for graph invariants');
        }
    }

    /**
     * Invariant: Keyword nodes must have non-empty name and normalized form.
     *
     * Reasoning:
     * - GraphRagOrchestrator writes Keyword nodes with properties:
     *   name + normalized=mb_strtolower(name), and links via HAS_KEYWORD. fileciteturn12file9
     */
    public function test_keyword_nodes_have_name_and_normalized_properties(): void
    {
        $this->requireGraphOrSkip();
        $graph = $this->graph();

        $result = $graph->run('MATCH (k:Keyword) RETURN k.id as id, k.name as name, k.normalized as normalized LIMIT 500');

        foreach ($result as $row) {
            $id = (string) $row->get('id');
            $name = (string) ($row->get('name') ?? '');
            $normalized = (string) ($row->get('normalized') ?? '');

            $this->assertNotSame('', trim($id), 'Keyword.id must be present');
            $this->assertNotSame('', trim($name), "Keyword:{$id} name must be non-empty");
            $this->assertNotSame('', trim($normalized), "Keyword:{$id} normalized must be non-empty");

            // Keep this check conservative for unicode edge cases.
            $this->assertSame(mb_strtolower($name), $normalized, "Keyword:{$id} normalized must equal lowercase(name)");
        }

        $this->assertTrue(true);
    }

    /**
     * Invariant: HAS_KEYWORD relationship weight must be numeric and within a sane range.
     *
     * Reasoning:
     * - Keyword weights are used for ordering/ranking in getGraphContext. fileciteturn12file7
     * - Your legacy keyword extractor boosts legal terms up to low single digits; we allow a generous ceiling.
     */
    public function test_has_keyword_relationship_has_numeric_weight(): void
    {
        $this->requireGraphOrSkip();
        $graph = $this->graph();

        $rows = $graph->run('MATCH ()-[r:HAS_KEYWORD]->() RETURN r.weight as weight LIMIT 1000');

        foreach ($rows as $row) {
            $weight = $row->get('weight');

            $this->assertNotNull($weight, 'HAS_KEYWORD.weight must be present');
            $this->assertIsNumeric($weight, 'HAS_KEYWORD.weight must be numeric');

            $w = (float) $weight;
            $this->assertGreaterThanOrEqual(0.0, $w, 'HAS_KEYWORD.weight must be >= 0');
            $this->assertLessThanOrEqual(100.0, $w, 'HAS_KEYWORD.weight must be <= 100 (sanity bound)');
        }

        $this->assertTrue(true);
    }

    /**
     * Invariant: Core relationships should have created_at timestamp.
     *
     * Reasoning:
     * - GraphDatabaseService::createRelationship always injects created_at. fileciteturn9file16
     * - Missing created_at means relationships were created outside the standard service or corruption occurred.
     */
    public function test_core_relationships_have_created_at(): void
    {
        $this->requireGraphOrSkip();
        $graph = $this->graph();

        $types = [
            'HAS_KEYWORD',
            'HAS_TAG',
            'CITES',
            'REFERENCES',
            'BELONGS_TO_JURISDICTION',
            'DECIDED_BY',
            'SIMILAR_TO',
            'BELONGS_TO',
        ];

        foreach ($types as $type) {
            // If type doesn't exist, Neo4j just returns 0.
            $res = $graph->run(
                "MATCH ()-[r:{$type}]->() WHERE r.created_at IS NULL RETURN count(r) as missing"
            );

            $missing = (int) $res->first()->get('missing');
            $this->assertSame(0, $missing, "Relationship {$type} missing created_at");
        }
    }

    /**
     * Invariant: If a Textract job claims graph_sync_status=synced, the graph must contain
     * TextractDocument nodes for all completed textract_documents chunks of that job.
     *
     * Reasoning:
     * - GraphRagOrchestrator::syncTextractJob selects completed chunks and then marks job as synced. fileciteturn10file19
     * - So synced implies the projection exists.
     */
    public function test_textract_job_graph_sync_synced_implies_nodes_for_completed_chunks(): void
    {
        $this->requireGraphOrSkip();
        $graph = $this->graph();

        if (! Schema::hasTable('textract_jobs') || ! Schema::hasTable('textract_documents')) {
            $this->markTestSkipped('textract_jobs/textract_documents tables missing');
        }

        if (! Schema::hasColumn('textract_jobs', 'graph_sync_status')) {
            $this->markTestSkipped('textract_jobs.graph_sync_status column missing');
        }

        $jobs = DB::table('textract_jobs')
            ->where('graph_sync_status', 'synced')
            ->orderByDesc('id')
            ->limit(25)
            ->get(['id']);

        foreach ($jobs as $job) {
            $jobId = (int) $job->id;

            $completed = DB::table('textract_documents')
                ->where('textract_job_id', $jobId)
                ->where('processing_status', 'completed')
                ->count();

            // If there are no completed docs, the job should not be marked synced.
            $this->assertGreaterThan(0, $completed, "TextractJob {$jobId} is graph-synced but has 0 completed textract_documents");

            $graphCountRes = $graph->run(
                'MATCH (t:TextractDocument {textract_job_id: $jobId}) RETURN count(t) as c',
                ['jobId' => $jobId]
            );
            $graphCount = (int) $graphCountRes->first()->get('c');

            $this->assertGreaterThanOrEqual(
                $completed,
                $graphCount,
                "TextractJob {$jobId} has {$completed} completed DB chunks but only {$graphCount} TextractDocument graph nodes"
            );
        }

        $this->assertTrue(true);
    }

    /**
     * Invariant: TextractDocument nodes in graph must correspond to completed textract_documents rows.
     *
     * Reasoning:
     * - syncTextractJob only syncs DB rows with processing_status='completed'. fileciteturn10file19
     */
    public function test_graph_textract_documents_must_be_completed_in_db(): void
    {
        $this->requireGraphOrSkip();
        $graph = $this->graph();

        if (! Schema::hasTable('textract_documents')) {
            $this->markTestSkipped('textract_documents table missing');
        }

        $rows = $graph->run('MATCH (t:TextractDocument) RETURN t.id as id LIMIT 300');

        foreach ($rows as $row) {
            $id = (string) $row->get('id');
            $db = DB::table('textract_documents')->where('id', $id)->first(['processing_status']);
            $this->assertNotNull($db, "TextractDocument graph node {$id} is missing in DB (orphan)");
            $this->assertSame('completed', (string) $db->processing_status, "TextractDocument {$id} exists in graph but DB status is not completed");
        }

        $this->assertTrue(true);
    }

    /**
     * Invariant: DECIDED_BY relationship must point to Court with matching name.
     *
     * Reasoning:
     * - syncCourtDecision creates Court node with id = 'court_' . md5(court) and property name=court. fileciteturn12file14
     */
    public function test_decided_by_relationship_targets_correct_court_name(): void
    {
        $this->requireGraphOrSkip();
        $graph = $this->graph();

        $res = $graph->run(
            "MATCH (d:CourtDecisionDocument)-[:DECIDED_BY]->(c:Court)
             WHERE d.court IS NOT NULL AND d.court <> ''
             AND (c.name IS NULL OR c.name <> d.court)
             RETURN count(d) as mismatched"
        );

        $mismatched = (int) $res->first()->get('mismatched');
        $this->assertSame(0, $mismatched, 'Some DECIDED_BY relationships point to Court nodes with non-matching name');
    }
}
