<?php

namespace Tests\Integration;

use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * GraphDbDataIntegrityInvariantsExtendedTest
 *
 * Reasoning:
 *  - The graph is a projection used for legal reasoning. If it drifts from the relational DB,
 *    you get "silent wrongness" (missing edges, stale nodes, broken traversal).
 *  - These tests strengthen trust by asserting:
 *      (1) relationships carry timestamps,
 *      (2) taxonomy nodes (Keyword/Tag/Court/Jurisdiction) have required properties,
 *      (3) "synced" statuses in SQL correspond to actual nodes/edges in Neo4j,
 *      (4) key CourtDecision fields match between SQL and Neo4j.
 */
class GraphDbDataIntegrityInvariantsExtendedTest extends TestCase
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

    private function table(string $configKey, string $default): string
    {
        return (string) config($configKey, $default);
    }

    /**
     * Invariant: Every relationship in the graph must have a created_at timestamp.
     *
     * Reasoning:
     * - GraphDatabaseService::createRelationship() unconditionally injects created_at.
     * - If we ever find missing timestamps, it implies relationships were created bypassing
     *   the service (manual cypher, legacy code) and temporal reasoning/auditing becomes unreliable.
     */
    public function test_all_graph_relationships_have_created_at(): void
    {
        $this->requireGraphOrSkip();

        $graph = $this->graph();

        $result = $graph->run(
            'MATCH ()-[r]->() WHERE r.created_at IS NULL RETURN count(r) as missing'
        );

        $missing = (int) $result->first()->get('missing');
        $this->assertSame(0, $missing, 'Some graph relationships are missing created_at timestamps');
    }

    /**
     * Invariant: Taxonomy/context nodes must have required, non-empty properties.
     *
     * Reasoning:
     * - Keywords drive graph-enhanced retrieval (HAS_KEYWORD queries expect k.name).
     * - Tags drive UI filters and graph:stats top tags (expects t.name).
     * - Courts/Jurisdictions drive navigation and temporal/jurisdictional reasoning.
     */
    public function test_taxonomy_nodes_have_required_properties(): void
    {
        $this->requireGraphOrSkip();

        $graph = $this->graph();

        // Keyword nodes should have name and normalized, both non-empty.
        $kw = $graph->run(
            "MATCH (k:Keyword)
             WHERE k.name IS NULL OR trim(k.name) = '' OR k.normalized IS NULL OR trim(k.normalized) = ''
             RETURN count(k) as bad"
        );
        $this->assertSame(0, (int) $kw->first()->get('bad'), 'Keyword nodes missing name/normalized');

        // Tag nodes should have name + slug.
        $tag = $graph->run(
            "MATCH (t:Tag)
             WHERE t.name IS NULL OR trim(t.name) = '' OR t.slug IS NULL OR trim(t.slug) = ''
             RETURN count(t) as bad"
        );
        $this->assertSame(0, (int) $tag->first()->get('bad'), 'Tag nodes missing name/slug');

        // Court and Jurisdiction nodes must have name.
        $court = $graph->run(
            "MATCH (c:Court) WHERE c.name IS NULL OR trim(c.name) = '' RETURN count(c) as bad"
        );
        $this->assertSame(0, (int) $court->first()->get('bad'), 'Court nodes missing name');

        $jur = $graph->run(
            "MATCH (j:Jurisdiction) WHERE j.name IS NULL OR trim(j.name) = '' RETURN count(j) as bad"
        );
        $this->assertSame(0, (int) $jur->first()->get('bad'), 'Jurisdiction nodes missing name');

        // Topic and LegalConcept nodes must have name if they exist.
        $topic = $graph->run(
            "MATCH (t:Topic) WHERE t.name IS NULL OR trim(t.name) = '' RETURN count(t) as bad"
        );
        $this->assertSame(0, (int) $topic->first()->get('bad'), 'Topic nodes missing name');

        $concept = $graph->run(
            "MATCH (c:LegalConcept) WHERE c.name IS NULL OR trim(c.name) = '' RETURN count(c) as bad"
        );
        $this->assertSame(0, (int) $concept->first()->get('bad'), 'LegalConcept nodes missing name');
    }

    /**
     * Invariant: Graph nodes for key document labels must be connected (not totally isolated).
     *
     * Reasoning:
     * - A document node with no relationships cannot participate in reasoning or navigation.
     * - Sync pipelines are supposed to attach keywords/tags/jurisdiction/court edges.
     */
    public function test_document_nodes_are_not_isolated(): void
    {
        $this->requireGraphOrSkip();

        $graph = $this->graph();

        foreach (['LawDocument', 'CaseDocument', 'CourtDecisionDocument', 'TextractDocument'] as $label) {
            $res = $graph->run(
                "MATCH (n:{$label}) WHERE NOT (n)--() RETURN count(n) as isolated"
            );
            $isolated = (int) $res->first()->get('isolated');
            $this->assertSame(0, $isolated, "Found isolated {$label} nodes (no relationships)");
        }
    }

    /**
     * Invariant: If textract_jobs.graph_sync_status is 'synced', there must exist at least
     * one TextractDocument node in Neo4j for that job.
     *
     * Reasoning:
     * - GraphRagOrchestrator::syncTextractJob() marks graph_sync_status='synced' only after
     *   iterating completed textract_documents and upserting TextractDocument nodes.
     * - If status says synced but graph has no nodes, UI and reasoning will mislead users.
     */
    public function test_synced_textract_jobs_have_graph_nodes(): void
    {
        $this->requireGraphOrSkip();

        if (! Schema::hasTable('textract_jobs') || ! Schema::hasTable('textract_documents')) {
            $this->markTestSkipped('textract_jobs/textract_documents tables not present');
        }

        if (! Schema::hasColumn('textract_jobs', 'graph_sync_status')) {
            $this->markTestSkipped('textract_jobs.graph_sync_status not present');
        }

        $graph = $this->graph();

        $jobs = DB::table('textract_jobs')
            ->select(['id'])
            ->where('graph_sync_status', 'synced')
            ->orderBy('id')
            ->limit(50)
            ->get();

        foreach ($jobs as $job) {
            $jobId = (string) $job->id;

            $res = $graph->run(
                'MATCH (t:TextractDocument {textract_job_id: $jobId}) RETURN count(t) as c',
                ['jobId' => (int) $jobId]
            );

            $count = (int) $res->first()->get('c');
            $this->assertGreaterThan(0, $count, "textract_jobs.id={$jobId} is marked synced but has no TextractDocument nodes");
        }

        $this->assertTrue(true);
    }

    /**
     * Invariant: CourtDecisionDocument node properties for judge/decision_date should match
     * the parent court_decisions table.
     *
     * Reasoning:
     * - syncCourtDecision copies these fields from court_decisions into each chunk node.
     * - If these drift, graph filters and analytics break (court/date/judge).
     */
    public function test_court_decision_node_fields_match_parent_table(): void
    {
        $this->requireGraphOrSkip();

        $courtDecisionsTable = $this->table('vizra-adk.tables.court_decisions', 'court_decisions');
        if (! Schema::hasTable($courtDecisionsTable)) {
            $this->markTestSkipped("Table not present: {$courtDecisionsTable}");
        }

        $graph = $this->graph();

        $rows = $graph->run(
            'MATCH (d:CourtDecisionDocument) RETURN d.id as id, d.decision_id as decision_id, d.judge as judge, d.decision_date as decision_date LIMIT 100'
        );

        foreach ($rows as $row) {
            $decisionId = (string) ($row->get('decision_id') ?? '');
            if ($decisionId === '') {
                // If decision_id is missing, that's always bad for a CourtDecisionDocument node.
                $this->fail('CourtDecisionDocument node missing decision_id');
            }

            $db = DB::table($courtDecisionsTable)
                ->select(['id', 'judge', 'decision_date'])
                ->where('id', $decisionId)
                ->first();

            $this->assertNotNull($db, "CourtDecisionDocument references missing parent court_decisions row: {$decisionId}");

            $graphJudge = (string) ($row->get('judge') ?? '');
            $dbJudge = (string) ($db->judge ?? '');
            $this->assertSame($dbJudge, $graphJudge, "CourtDecisionDocument judge mismatch for decision_id={$decisionId}");

            // decision_date: compare as YYYY-MM-DD string when present
            $graphDate = (string) ($row->get('decision_date') ?? '');
            $dbDate = $db->decision_date ? (string) $db->decision_date : '';

            if ($dbDate !== '') {
                // Graph might store as string/date/datetime; normalize by prefix.
                $this->assertSame(substr($dbDate, 0, 10), substr($graphDate, 0, 10), "CourtDecisionDocument decision_date mismatch for decision_id={$decisionId}");
            }
        }

        $this->assertTrue(true);
    }
}
