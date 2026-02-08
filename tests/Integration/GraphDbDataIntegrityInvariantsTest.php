<?php

namespace Tests\Integration;

use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * GraphDbDataIntegrityInvariantsTest
 *
 * Purpose:
 *  - Encode Neo4j <-> Postgres cross-store invariants as executable tests.
 *  - Catch drift between relational source-of-truth tables and graph projections.
 *
 * Notes:
 *  - Tests are conditional: if Neo4j is disabled or unavailable, they SKIP.
 *  - Tests are bounded (LIMIT) to keep CI stable.
 *
 * Graph conventions:
 *  - GraphDatabaseService upserts use MERGE (n:Label {id: $id}) meaning the graph key is property `id`.
 */
class GraphDbDataIntegrityInvariantsTest extends TestCase
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

    private function assertGraphNodeIdsExistInTable(string $label, string $table, int $limit = 200): void
    {
        $graph = $this->graph();

        if (! Schema::hasTable($table)) {
            $this->markTestSkipped("Relational table missing: {$table}");
        }

        $rows = $graph->run(
            "MATCH (n:{$label}) RETURN n.id as id LIMIT {$limit}"
        );

        foreach ($rows as $row) {
            $id = (string) $row->get('id');
            $exists = DB::table($table)->where('id', $id)->exists();
            $this->assertTrue($exists, "Graph node {$label}:{$id} is orphaned (missing in {$table})");
        }

        // Empty graph is a valid state.
        $this->assertTrue(true);
    }

    /**
     * Invariant: Neo4j schema constraints should exist for core node types.
     *
     * Reasoning:
     * - GraphDatabaseService::initializeSchema creates uniqueness constraints for core labels
     *   (LawDocument, CaseDocument, CourtDecisionDocument, Keyword, Tag, Jurisdiction, Court, Topic, LegalConcept).
     *   Without these constraints, duplicates appear and graph queries become ambiguous.
     */
    public function test_neo4j_constraints_exist_for_core_labels(): void
    {
        $this->requireGraphOrSkip();

        $graph = $this->graph();

        // Ensure schema is initialized (idempotent).
        $graph->initializeSchema();

        // Neo4j 5+ syntax
        $constraints = $graph->run('SHOW CONSTRAINTS YIELD name RETURN name');
        $names = [];
        foreach ($constraints as $row) {
            $names[] = (string) $row->get('name');
        }

        // Names come from GraphDatabaseService::createConstraints()
        foreach ([
            'law_doc_id',
            'case_doc_id',
            'court_decision_doc_id',
            'keyword_name',
            'tag_name',
            'jurisdiction_name',
            'court_name',
            'topic_name',
            'concept_name',
        ] as $expected) {
            $this->assertContains($expected, $names, "Missing Neo4j constraint: {$expected}");
        }
    }

    /**
     * Invariant: Graph should not contain orphaned Document nodes.
     *
     * Reasoning:
     * - Orphaned nodes happen if a DB row is deleted without unsync.
     * - You already have an orphan cleanup command for Textract nodes;
     *   this test generalizes the idea across major document types.
     */
    public function test_graph_document_nodes_are_not_orphaned_relative_to_relational_db(): void
    {
        $this->requireGraphOrSkip();

        $this->assertGraphNodeIdsExistInTable('LawDocument', $this->table('vizra-adk.tables.laws', 'laws'));
        $this->assertGraphNodeIdsExistInTable('CaseDocument', $this->table('vizra-adk.tables.cases_documents', 'cases_documents'));
        $this->assertGraphNodeIdsExistInTable('CourtDecisionDocument', $this->table('vizra-adk.tables.court_decision_documents', 'court_decision_documents'));

        // TextractDocument nodes map to textract_documents (id bigint).
        $this->assertGraphNodeIdsExistInTable('TextractDocument', 'textract_documents');
    }

    /**
     * Invariant: For graph nodes that originate from relational rows, key properties must match.
     *
     * Reasoning:
     * - Prevents subtle drift where the graph points to the right node id but has wrong metadata,
     *   breaking filters in graph queries.
     */
    public function test_graph_node_properties_match_relational_rows_for_key_fields(): void
    {
        $this->requireGraphOrSkip();
        $graph = $this->graph();

        // LawDocument.content_hash must match laws.content_hash
        $lawsTable = $this->table('vizra-adk.tables.laws', 'laws');
        if (Schema::hasTable($lawsTable)) {
            $rows = $graph->run('MATCH (n:LawDocument) RETURN n.id as id, n.content_hash as ch LIMIT 100');
            foreach ($rows as $row) {
                $id = (string) $row->get('id');
                $ch = (string) ($row->get('ch') ?? '');
                $dbRow = DB::table($lawsTable)->select(['id', 'content_hash'])->where('id', $id)->first();
                $this->assertNotNull($dbRow, "Missing relational law row for LawDocument:{$id}");
                $this->assertSame((string) $dbRow->content_hash, $ch, "LawDocument:{$id} content_hash mismatch");
            }
        }

        // CourtDecisionDocument.decision_id must match court_decision_documents.decision_id
        $cddTable = $this->table('vizra-adk.tables.court_decision_documents', 'court_decision_documents');
        if (Schema::hasTable($cddTable)) {
            $rows = $graph->run('MATCH (n:CourtDecisionDocument) RETURN n.id as id, n.decision_id as decision_id LIMIT 100');
            foreach ($rows as $row) {
                $id = (string) $row->get('id');
                $decisionId = (string) ($row->get('decision_id') ?? '');
                $dbRow = DB::table($cddTable)->select(['id', 'decision_id'])->where('id', $id)->first();
                $this->assertNotNull($dbRow, "Missing relational decision doc row for CourtDecisionDocument:{$id}");
                $this->assertSame((string) $dbRow->decision_id, $decisionId, "CourtDecisionDocument:{$id} decision_id mismatch");
            }
        }

        // TextractDocument.textract_job_id must match textract_documents.textract_job_id
        if (Schema::hasTable('textract_documents')) {
            $rows = $graph->run('MATCH (n:TextractDocument) RETURN n.id as id, n.textract_job_id as job_id, n.case_id as case_id LIMIT 100');
            foreach ($rows as $row) {
                $id = (string) $row->get('id');
                $jobId = (string) ($row->get('job_id') ?? '');
                $caseId = (string) ($row->get('case_id') ?? '');

                $dbRow = DB::table('textract_documents')->select(['id', 'textract_job_id', 'case_id'])->where('id', $id)->first();
                $this->assertNotNull($dbRow, "Missing relational textract_documents row for TextractDocument:{$id}");
                $this->assertSame((string) $dbRow->textract_job_id, $jobId, "TextractDocument:{$id} textract_job_id mismatch");

                // case_id may be empty in some flows; when present, must match
                if (! empty($caseId)) {
                    $this->assertSame((string) $dbRow->case_id, $caseId, "TextractDocument:{$id} case_id mismatch");
                }
            }
        }

        $this->assertTrue(true);
    }

    /**
     * Invariant: Jurisdiction and Court relationships must exist when corresponding properties exist.
     *
     * Reasoning:
     * - Graph sync explicitly creates these nodes and relationships.
     * - Missing relationships breaks graph filtering/traversal like "all decisions by court".
     */
    public function test_graph_relationships_exist_for_jurisdiction_and_court_properties(): void
    {
        $this->requireGraphOrSkip();
        $graph = $this->graph();

        // LawDocument with jurisdiction must have BELONGS_TO_JURISDICTION relationship
        $missingLawJur = $graph->run(
            "MATCH (l:LawDocument)
             WHERE l.jurisdiction IS NOT NULL AND l.jurisdiction <> ''
             AND NOT (l)-[:BELONGS_TO_JURISDICTION]->(:Jurisdiction)
             RETURN count(l) as missing"
        );
        $missingLawJurCount = (int) $missingLawJur->first()->get('missing');
        $this->assertSame(0, $missingLawJurCount, 'Some LawDocument nodes have jurisdiction but no BELONGS_TO_JURISDICTION');

        // CourtDecisionDocument with court must have DECIDED_BY relationship
        $missingCourt = $graph->run(
            "MATCH (d:CourtDecisionDocument)
             WHERE d.court IS NOT NULL AND d.court <> ''
             AND NOT (d)-[:DECIDED_BY]->(:Court)
             RETURN count(d) as missing"
        );
        $missingCourtCount = (int) $missingCourt->first()->get('missing');
        $this->assertSame(0, $missingCourtCount, 'Some CourtDecisionDocument nodes have court but no DECIDED_BY->Court relationship');

        // CourtDecisionDocument with jurisdiction must have BELONGS_TO_JURISDICTION relationship
        $missingDecJur = $graph->run(
            "MATCH (d:CourtDecisionDocument)
             WHERE d.jurisdiction IS NOT NULL AND d.jurisdiction <> ''
             AND NOT (d)-[:BELONGS_TO_JURISDICTION]->(:Jurisdiction)
             RETURN count(d) as missing"
        );
        $missingDecJurCount = (int) $missingDecJur->first()->get('missing');
        $this->assertSame(0, $missingDecJurCount, 'Some CourtDecisionDocument nodes have jurisdiction but no BELONGS_TO_JURISDICTION');
    }

    /**
     * Invariant: If a TextractDocument belongs to a case that has any CaseDocument chunks,
     * then the TextractDocument must be linked to a CaseDocument node.
     *
     * Reasoning:
     * - The graph sync code attempts to create BELONGS_TO from TextractDocument -> CaseDocument
     *   when job->case_id is present and a case document exists.
     */
    public function test_textract_documents_link_to_case_documents_when_case_documents_exist(): void
    {
        $this->requireGraphOrSkip();
        $graph = $this->graph();

        $caseDocsTable = $this->table('vizra-adk.tables.cases_documents', 'cases_documents');
        if (! Schema::hasTable($caseDocsTable) || ! Schema::hasTable('textract_documents')) {
            $this->markTestSkipped('Required tables missing for textract->case linkage invariant');
        }

        // Pull a bounded sample of textract docs that have case_id.
        $rows = $graph->run(
            "MATCH (t:TextractDocument)
             WHERE t.case_id IS NOT NULL AND t.case_id <> ''
             RETURN t.id as id, t.case_id as case_id
             LIMIT 200"
        );

        foreach ($rows as $row) {
            $textractId = (string) $row->get('id');
            $caseId = (string) $row->get('case_id');

            // Only enforce linkage if relational DB has any case doc chunks.
            $hasCaseDocs = DB::table($caseDocsTable)->where('case_id', $caseId)->exists();
            if (! $hasCaseDocs) {
                continue;
            }

            $linked = $graph->run(
                "MATCH (t:TextractDocument {id: \$id})-[:BELONGS_TO]->(:CaseDocument)
                 RETURN count(*) as c",
                ['id' => $textractId]
            );

            $count = (int) $linked->first()->get('c');
            $this->assertGreaterThan(0, $count, "TextractDocument:{$textractId} should BELONGS_TO a CaseDocument when case has documents");
        }

        $this->assertTrue(true);
    }

    /**
     * Invariant: textract_jobs.graph_sync_status='synced' implies graph has corresponding TextractDocument nodes.
     *
     * Reasoning:
     * - Prevents the "status says synced but graph is missing" failure mode.
     * - Also checks parity with completed textract_documents in the relational DB.
     */
    public function test_textract_job_synced_status_implies_graph_nodes_exist_and_match_db_counts(): void
    {
        $this->requireGraphOrSkip();
        $graph = $this->graph();

        if (! Schema::hasTable('textract_jobs') || ! Schema::hasTable('textract_documents')) {
            $this->markTestSkipped('textract tables missing');
        }

        // Only check a bounded number of jobs.
        $jobs = DB::table('textract_jobs')
            ->where('graph_sync_status', 'synced')
            ->orderBy('id', 'desc')
            ->limit(25)
            ->get(['id', 'embedding_status', 'graph_sync_status']);

        foreach ($jobs as $job) {
            // Strong consistency: graph synced implies embeddings synced.
            if (property_exists($job, 'embedding_status') && $job->embedding_status !== null) {
                $this->assertSame('synced', (string) $job->embedding_status, "TextractJob {$job->id} graph synced but embedding_status not synced");
            }

            $dbCompleted = (int) DB::table('textract_documents')
                ->where('textract_job_id', $job->id)
                ->where('processing_status', 'completed')
                ->count();

            $this->assertGreaterThan(0, $dbCompleted, "TextractJob {$job->id} graph_sync_status=synced but has 0 completed textract_documents");

            $graphCountRes = $graph->run(
                'MATCH (t:TextractDocument) WHERE t.textract_job_id = $jobId RETURN count(t) as c',
                ['jobId' => $job->id]
            );

            $graphCount = (int) $graphCountRes->first()->get('c');
            $this->assertGreaterThanOrEqual(
                $dbCompleted,
                $graphCount,
                "TextractJob {$job->id} has fewer TextractDocument nodes in graph ({$graphCount}) than completed DB chunks ({$dbCompleted})"
            );
        }

        $this->assertTrue(true);
    }

    /**
     * Invariant: Core relationship properties exist.
     *
     * Reasoning:
     * - Your linkers and tagger attach semantic weights and timestamps.
     * - If those properties go missing, downstream ranking/filtering becomes unreliable.
     */
    public function test_graph_relationships_have_expected_properties(): void
    {
        $this->requireGraphOrSkip();
        $graph = $this->graph();

        // HAS_KEYWORD should always have weight
        $missingKeywordWeight = $graph->run(
            'MATCH ()-[r:HAS_KEYWORD]->() WHERE r.weight IS NULL RETURN count(r) as c'
        );
        $this->assertSame(0, (int) $missingKeywordWeight->first()->get('c'), 'HAS_KEYWORD relationships must have weight');

        // HAS_TAG should always have applied_at (TaggingService sets it)
        $missingAppliedAt = $graph->run(
            'MATCH ()-[r:HAS_TAG]->() WHERE r.applied_at IS NULL RETURN count(r) as c'
        );
        $this->assertSame(0, (int) $missingAppliedAt->first()->get('c'), 'HAS_TAG relationships must have applied_at');

        // All relationships created via GraphDatabaseService should have created_at
        $missingCreatedAt = $graph->run(
            'MATCH ()-[r]->() WHERE r.created_at IS NULL RETURN count(r) as c'
        );
        $this->assertSame(0, (int) $missingCreatedAt->first()->get('c'), 'All graph relationships must have created_at');
    }

    /**
     * Invariant: Context nodes should not be isolated.
     *
     * Reasoning:
     * - Court and Jurisdiction nodes are created specifically to connect documents.
     * - Isolated context nodes indicate partial writes or manual data tampering.
     */
    public function test_context_nodes_are_not_isolated(): void
    {
        $this->requireGraphOrSkip();
        $graph = $this->graph();

        $isolatedCourts = $graph->run(
            'MATCH (c:Court) WHERE NOT (c)<-[:DECIDED_BY]-() RETURN count(c) as c'
        );
        $this->assertSame(0, (int) $isolatedCourts->first()->get('c'), 'Court nodes should have at least one incoming DECIDED_BY');

        $isolatedJur = $graph->run(
            'MATCH (j:Jurisdiction) WHERE NOT (j)<-[:BELONGS_TO_JURISDICTION]-() RETURN count(j) as c'
        );
        $this->assertSame(0, (int) $isolatedJur->first()->get('c'), 'Jurisdiction nodes should have at least one incoming BELONGS_TO_JURISDICTION');

        // Keyword nodes should not be isolated either.
        $isolatedKeywords = $graph->run(
            'MATCH (k:Keyword) WHERE NOT (k)<-[:HAS_KEYWORD]-() RETURN count(k) as c'
        );
        $this->assertSame(0, (int) $isolatedKeywords->first()->get('c'), 'Keyword nodes should have at least one incoming HAS_KEYWORD');
    }

    /**
     * Invariant: Core document nodes should have created_at and updated_at.
     *
     * Reasoning:
     * - GraphDatabaseService::upsertNode injects created_at/updated_at.
     * - Missing timestamps imply nodes were created outside the service or schema drift occurred.
     */
    public function test_document_nodes_have_created_at_and_updated_at(): void
    {
        $this->requireGraphOrSkip();
        $graph = $this->graph();

        foreach (['LawDocument', 'CaseDocument', 'CourtDecisionDocument', 'TextractDocument'] as $label) {
            $missing = $graph->run(
                "MATCH (n:{$label}) WHERE n.created_at IS NULL OR n.updated_at IS NULL RETURN count(n) as c"
            );
            $this->assertSame(0, (int) $missing->first()->get('c'), "{$label} nodes must have created_at and updated_at");
        }
    }
}
