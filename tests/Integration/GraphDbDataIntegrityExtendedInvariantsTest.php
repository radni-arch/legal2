<?php

namespace Tests\Integration;

use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * GraphDbDataIntegrityExtendedInvariantsTest
 *
 * Additional Neo4j<->DB invariants focused on:
 *  - sync parity ("synced" markers imply graph materialization)
 *  - relationship property integrity (weights, citation_type, created_at)
 */
class GraphDbDataIntegrityExtendedInvariantsTest extends TestCase
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
     * Invariant: textract_jobs.graph_sync_status=synced implies Neo4j has a TextractDocument
     * node for every completed textract_documents row for that job.
     *
     * Reasoning:
     * - GraphRag sync for TextractJob iterates completed chunks and upserts TextractDocument nodes,
     *   then marks graph_sync_status as synced.
     * - If the counts diverge, graph reasoning will silently miss OCR content.
     */
    public function test_textract_job_synced_implies_graph_contains_all_completed_chunks(): void
    {
        $this->requireGraphOrSkip();

        if (! Schema::hasTable('textract_jobs') || ! Schema::hasTable('textract_documents')) {
            $this->markTestSkipped('textract_jobs/textract_documents tables not present');
        }

        if (! Schema::hasColumn('textract_jobs', 'graph_sync_status')) {
            $this->markTestSkipped('textract_jobs.graph_sync_status column not present');
        }

        $graph = $this->graph();

        $jobIds = DB::table('textract_jobs')
            ->where('graph_sync_status', 'synced')
            ->orderByDesc('id')
            ->limit(20)
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->toArray();

        foreach ($jobIds as $jobId) {
            $dbCount = (int) DB::table('textract_documents')
                ->where('textract_job_id', $jobId)
                ->where('processing_status', 'completed')
                ->count();

            $res = $graph->run(
                'MATCH (t:TextractDocument) WHERE t.textract_job_id = $jobId RETURN count(t) as c',
                ['jobId' => $jobId]
            );
            $graphCount = (int) $res->first()->get('c');

            $this->assertSame(
                $dbCount,
                $graphCount,
                "TextractJob {$jobId} synced but Neo4j TextractDocument count {$graphCount} != DB completed chunks {$dbCount}"
            );
        }

        $this->assertTrue(true);
    }

    /**
     * Invariant: Keyword nodes and HAS_KEYWORD relationships are structurally valid.
     *
     * Reasoning:
     * - GraphKeywordLinker upserts Keyword nodes with name+normalized and creates HAS_KEYWORD with weight.
     * - Missing these breaks keyword-driven traversals and ranking.
     */
    public function test_keyword_nodes_and_has_keyword_relationships_have_required_properties(): void
    {
        $this->requireGraphOrSkip();
        $graph = $this->graph();

        $missingKeywordProps = $graph->run(
            "MATCH (k:Keyword)
             WHERE k.name IS NULL OR k.name = '' OR k.normalized IS NULL OR k.normalized = ''
             RETURN count(k) as c"
        );
        $this->assertSame(0, (int) $missingKeywordProps->first()->get('c'), 'Keyword nodes must have non-empty name and normalized');

        $missingWeight = $graph->run(
            "MATCH ()-[r:HAS_KEYWORD]->(:Keyword)
             WHERE r.weight IS NULL
             RETURN count(r) as c"
        );
        $this->assertSame(0, (int) $missingWeight->first()->get('c'), 'HAS_KEYWORD relationships must have weight');
    }

    /**
     * Invariant: Citation relationships must carry citation_type and created_at.
     *
     * Reasoning:
     * - GraphCitationLinker sets citation_type (law_number/article_reference/case_reference/etc.)
     *   and GraphDatabaseService always stamps created_at on relationships.
     */
    public function test_citation_relationships_have_citation_type_and_created_at(): void
    {
        $this->requireGraphOrSkip();
        $graph = $this->graph();

        // Only enforce for doc->law citations (where citation extraction applies)
        $missingCitesProps = $graph->run(
            "MATCH (d)-[r:CITES]->(l:LawDocument)
             WHERE (d:LawDocument OR d:CaseDocument OR d:CourtDecisionDocument OR d:TextractDocument)
             AND (r.citation_type IS NULL OR r.citation_type = '' OR r.created_at IS NULL)
             RETURN count(r) as c"
        );
        $this->assertSame(0, (int) $missingCitesProps->first()->get('c'), 'CITES relationships must have citation_type and created_at');

        $missingRefProps = $graph->run(
            "MATCH (d)-[r:REFERENCES]->(c:CaseDocument)
             WHERE (d:LawDocument OR d:CaseDocument OR d:CourtDecisionDocument OR d:TextractDocument)
             AND (r.citation_type IS NULL OR r.citation_type = '' OR r.created_at IS NULL)
             RETURN count(r) as c"
        );
        $this->assertSame(0, (int) $missingRefProps->first()->get('c'), 'REFERENCES relationships must have citation_type and created_at');
    }

    /**
     * Invariant: Every CourtDecisionDocument node’s decision_id must exist in relational court_decisions.
     *
     * Reasoning:
     * - Graph nodes carry decision_id as a join handle; missing parents means graph traversal
     *   may show decisions that cannot be loaded/displayed.
     */
    public function test_court_decision_document_nodes_have_existing_parent_decision_rows(): void
    {
        $this->requireGraphOrSkip();

        $cddTable = (string) config('vizra-adk.tables.court_decision_documents', 'court_decision_documents');
        $cdTable = (string) config('vizra-adk.tables.court_decisions', 'court_decisions');

        if (! Schema::hasTable($cddTable) || ! Schema::hasTable($cdTable)) {
            $this->markTestSkipped('court decision tables not present');
        }

        $graph = $this->graph();

        $rows = $graph->run('MATCH (n:CourtDecisionDocument) RETURN n.id as id, n.decision_id as decision_id LIMIT 200');

        foreach ($rows as $row) {
            $decisionId = (string) ($row->get('decision_id') ?? '');
            $this->assertNotSame('', $decisionId, 'CourtDecisionDocument must have decision_id property');

            $exists = DB::table($cdTable)->where('id', $decisionId)->exists();
            $this->assertTrue($exists, "CourtDecisionDocument references missing court_decisions row: {$decisionId}");
        }

        $this->assertTrue(true);
    }
}
