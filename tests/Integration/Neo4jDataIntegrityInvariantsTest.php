<?php

namespace Tests\Integration;

use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Neo4jDataIntegrityInvariantsTest
 *
 * Reasoning:
 * - The project uses Neo4j as a second persistence plane for legal reasoning (keywords, citations, similarity,
 *   jurisdictions/courts, etc.). If Neo4j and Postgres drift, you get "phantom" graph entities, missing
 *   edges, or broken traversal results.
 *
 * Strategy:
 * - Only run when Neo4j is enabled AND actually available.
 * - Enforce invariants that are implied by your sync services:
 *   - GraphDatabaseService::upsertNode always writes an `id` property and uses it for MERGE. fileciteturn14file8
 *   - GraphRagOrchestrator::syncTextractJob creates TextractDocument nodes (id = textract_documents.id) and may
 *     create BELONGS_TO -> CaseDocument relationships. fileciteturn10file10
 *   - GraphRagOrchestrator::syncCourtDecision creates CourtDecisionDocument nodes and DECIDED_BY + BELONGS_TO_JURISDICTION.
 *     fileciteturn13file3
 *   - GraphRagOrchestrator::syncLaw creates LawDocument nodes and BELONGS_TO_JURISDICTION. fileciteturn13file2
 *   - CaseGraphSyncService creates CaseDocument nodes from cases_documents rows. fileciteturn13file8
 */
class Neo4jDataIntegrityInvariantsTest extends TestCase
{
    use UsesTestDatabase;

    protected GraphDatabaseService $graph;

    protected function setUp(): void
    {
        parent::setUp();

        $this->graph = app(GraphDatabaseService::class);

        // Skip entirely when graph is disabled or unavailable.
        if (! config('neo4j.sync.enabled', true)) {
            $this->markTestSkipped('Neo4j sync disabled via config(neo4j.sync.enabled).');
        }

        if (! $this->graph->isAvailable()) {
            $status = $this->graph->getHealthStatus();
            $msg = 'Neo4j not available; skipping Neo4j invariants. ';
            if (! empty($status['error'] ?? null)) {
                $msg .= 'Error: '.$status['error'];
            }
            $this->markTestSkipped($msg);
        }
    }

    private function countCypher(string $cypher, array $params = [], string $field = 'count'): int
    {
        $res = $this->graph->run($cypher, $params);
        if ($res->count() === 0) {
            return 0;
        }

        return (int) $res->first()->get($field);
    }

    private function listCypher(string $cypher, array $params, string $field): array
    {
        $res = $this->graph->run($cypher, $params);
        $out = [];
        foreach ($res as $record) {
            $out[] = $record->get($field);
        }
        return $out;
    }

    private function normalizeIntIds(array $ids, int $limit = 1000): array
    {
        $out = [];
        foreach ($ids as $id) {
            if (count($out) >= $limit) {
                break;
            }

            // Neo4j property might be stored as int, string, etc.
            if (is_int($id)) {
                $out[] = $id;
                continue;
            }

            $s = trim((string) $id);
            if ($s === '') {
                continue;
            }
            if (ctype_digit($s)) {
                $out[] = (int) $s;
            }
        }
        return $out;
    }

    /**
     * Invariant: There must be no TextractDocument nodes in Neo4j that do not exist in Postgres.
     *
     * Reasoning:
     * - Orphaned graph nodes cause incorrect graph search results and misleading UI graph visualizations.
     * - You already have a cleanup command for orphaned Textract nodes; this test makes it a hard invariant.
     */
    public function test_no_orphan_textractdocument_nodes_in_neo4j(): void
    {
        if (! Schema::hasTable('textract_documents')) {
            $this->markTestSkipped('textract_documents table not present.');
        }

        $ids = $this->listCypher(
            'MATCH (n:TextractDocument) RETURN n.id as id LIMIT $limit',
            ['limit' => 1000],
            'id'
        );

        $ids = $this->normalizeIntIds($ids);
        if (empty($ids)) {
            $this->assertTrue(true);
            return;
        }

        $existing = DB::table('textract_documents')->whereIn('id', $ids)->pluck('id')->all();
        $existing = array_map('intval', $existing);
        $existingSet = array_flip($existing);

        $orphans = [];
        foreach ($ids as $id) {
            if (! isset($existingSet[$id])) {
                $orphans[] = $id;
            }
        }

        $this->assertSame(
            [],
            $orphans,
            'Found orphan TextractDocument nodes in Neo4j with no matching textract_documents rows. Sample: '.implode(', ', array_slice($orphans, 0, 10))
        );
    }

    /**
     * Invariant: For textract_jobs marked graph_sync_status=synced, Neo4j must contain matching TextractDocument nodes
     * for all completed textract_documents rows.
     */
    public function test_synced_textract_jobs_have_complete_graph_representation(): void
    {
        if (! Schema::hasTable('textract_jobs') || ! Schema::hasColumn('textract_jobs', 'graph_sync_status')) {
            $this->markTestSkipped('textract_jobs.graph_sync_status not present.');
        }
        if (! Schema::hasTable('textract_documents')) {
            $this->markTestSkipped('textract_documents table not present.');
        }

        $jobs = DB::table('textract_jobs')
            ->where('graph_sync_status', 'synced')
            ->orderByDesc('id')
            ->limit(25)
            ->get(['id', 'case_id']);

        foreach ($jobs as $job) {
            $jobId = (int) $job->id;

            $dbCount = (int) DB::table('textract_documents')
                ->where('textract_job_id', $jobId)
                ->where('processing_status', 'completed')
                ->count();

            $graphCount = $this->countCypher(
                'MATCH (n:TextractDocument {textract_job_id: $jobId}) RETURN count(n) as count',
                ['jobId' => $jobId]
            );

            $this->assertSame(
                $dbCount,
                $graphCount,
                "TextractJob {$jobId} is graph_synced but has mismatch between completed textract_documents ({$dbCount}) and Neo4j TextractDocument nodes ({$graphCount})."
            );

            // If the textract job is associated to a case and a CaseDocument exists, expect BELONGS_TO.
            if (! empty($job->case_id) && Schema::hasTable('cases_documents')) {
                $caseDoc = DB::table('cases_documents')->where('case_id', $job->case_id)->first();
                if ($caseDoc) {
                    $belongsToCount = $this->countCypher(
                        'MATCH (t:TextractDocument {textract_job_id: $jobId})-[:BELONGS_TO]->(:CaseDocument) RETURN count(t) as count',
                        ['jobId' => $jobId]
                    );
                    $this->assertGreaterThan(
                        0,
                        $belongsToCount,
                        "Expected TextractDocument→CaseDocument BELONGS_TO edges for TextractJob {$jobId} (case_id={$job->case_id})"
                    );
                }
            }
        }

        $this->assertTrue(true);
    }

    /**
     * Invariant: Court decision documents in Postgres must have matching CourtDecisionDocument nodes in Neo4j.
     */
    public function test_court_decision_documents_have_graph_nodes_and_core_relationships(): void
    {
        if (! Schema::hasTable('court_decision_documents') || ! Schema::hasTable('court_decisions')) {
            $this->markTestSkipped('court_decision_documents or court_decisions table not present.');
        }

        // Sample to keep tests bounded.
        $docs = DB::table('court_decision_documents as cdd')
            ->join('court_decisions as cd', 'cd.id', '=', 'cdd.decision_id')
            ->orderByDesc('cdd.id')
            ->limit(25)
            ->get([
                'cdd.id as doc_id',
                'cdd.decision_id as decision_id',
                'cdd.doc_id as group_doc_id',
                'cdd.chunk_index as chunk_index',
                'cdd.content_hash as content_hash',
                'cd.court as court',
                'cd.jurisdiction as jurisdiction',
            ]);

        foreach ($docs as $d) {
            $docId = (string) $d->doc_id;

            $nodeExists = $this->countCypher(
                'MATCH (n:CourtDecisionDocument {id: $id}) RETURN count(n) as count',
                ['id' => $docId]
            );
            $this->assertSame(1, $nodeExists, "Missing CourtDecisionDocument node in Neo4j for court_decision_documents.id={$docId}");

            // Required core properties implied by syncCourtDecision()
            // fileciteturn13file3
            $missingProps = $this->countCypher(
                'MATCH (n:CourtDecisionDocument {id: $id})\n'
                .'WHERE n.decision_id IS NULL OR n.doc_id IS NULL OR n.chunk_index IS NULL OR n.content_hash IS NULL\n'
                .'RETURN count(n) as count',
                ['id' => $docId]
            );
            $this->assertSame(0, $missingProps, "CourtDecisionDocument node {$docId} is missing required properties (decision_id/doc_id/chunk_index/content_hash)");

            // If court exists, must have DECIDED_BY edge.
            if (! empty($d->court)) {
                $hasCourt = $this->countCypher(
                    'MATCH (:CourtDecisionDocument {id: $id})-[:DECIDED_BY]->(c:Court) WHERE c.name IS NOT NULL RETURN count(c) as count',
                    ['id' => $docId]
                );
                $this->assertGreaterThan(0, $hasCourt, "Expected DECIDED_BY relationship for CourtDecisionDocument {$docId}");
            }

            // If jurisdiction exists, must have BELONGS_TO_JURISDICTION edge.
            if (! empty($d->jurisdiction)) {
                $hasJur = $this->countCypher(
                    'MATCH (:CourtDecisionDocument {id: $id})-[:BELONGS_TO_JURISDICTION]->(j:Jurisdiction) WHERE j.name IS NOT NULL RETURN count(j) as count',
                    ['id' => $docId]
                );
                $this->assertGreaterThan(0, $hasJur, "Expected BELONGS_TO_JURISDICTION relationship for CourtDecisionDocument {$docId}");
            }
        }

        $this->assertTrue(true);
    }

    /**
     * Invariant: LawDocument nodes in Neo4j must have core properties; and if they specify jurisdiction,
     * they should be connected to a Jurisdiction node.
     */
    public function test_law_documents_have_required_properties_and_jurisdiction_edges(): void
    {
        if (! Schema::hasTable('laws')) {
            $this->markTestSkipped('laws table not present.');
        }

        // If there are no LawDocument nodes yet, don't fail.
        $lawNodeCount = $this->countCypher('MATCH (n:LawDocument) RETURN count(n) as count');
        if ($lawNodeCount === 0) {
            $this->assertTrue(true);
            return;
        }

        // Missing core properties that syncLaw writes. fileciteturn13file2
        $missingCore = $this->countCypher(
            'MATCH (n:LawDocument)\n'
            .'WHERE n.id IS NULL OR n.doc_id IS NULL OR n.content_hash IS NULL OR n.chunk_index IS NULL\n'
            .'RETURN count(n) as count'
        );
        $this->assertSame(0, $missingCore, 'Some LawDocument nodes are missing required properties (id/doc_id/content_hash/chunk_index).');

        // If jurisdiction is set on LawDocument, ensure relationship exists.
        $missingJurRel = $this->countCypher(
            'MATCH (n:LawDocument)\n'
            .'WHERE n.jurisdiction IS NOT NULL AND n.jurisdiction <> ""\n'
            .'AND NOT (n)-[:BELONGS_TO_JURISDICTION]->(:Jurisdiction)\n'
            .'RETURN count(n) as count'
        );
        $this->assertSame(0, $missingJurRel, 'Some LawDocument nodes have jurisdiction set but lack BELONGS_TO_JURISDICTION relationship.');
    }

    /**
     * Invariant: Case documents in Postgres (cases_documents) should have a corresponding CaseDocument node.
     *
     * Reasoning:
     * - CaseGraphSyncService syncs by case document row ID (cases_documents.id) and sets properties like case_id/doc_id/content_hash.
     *   fileciteturn13file8
     *
     * Note:
     * - There is also a separate Neo4jService path that uses CaseDocument id = doc_id (string like 'doc-...')
     *   and HAS_DOCUMENT relationship. fileciteturn11file7
     * - This invariant only checks the CaseGraphSyncService contract.
     */
    public function test_cases_documents_rows_have_case_document_nodes_when_synced(): void
    {
        if (! Schema::hasTable('cases_documents')) {
            $this->markTestSkipped('cases_documents table not present.');
        }

        $rows = DB::table('cases_documents')
            ->whereNotNull('id')
            ->orderByDesc('id')
            ->limit(25)
            ->get(['id', 'case_id', 'doc_id', 'chunk_index', 'content_hash']);

        foreach ($rows as $r) {
            $id = (string) $r->id;

            $exists = $this->countCypher(
                'MATCH (n:CaseDocument {id: $id}) RETURN count(n) as count',
                ['id' => $id]
            );

            // Only enforce when graph has any CaseDocument nodes (fresh graph is a valid state before sync).
            $totalCaseDocs = $this->countCypher('MATCH (n:CaseDocument) RETURN count(n) as count');
            if ($totalCaseDocs === 0) {
                $this->assertTrue(true);
                return;
            }

            $this->assertSame(1, $exists, "Missing CaseDocument node in Neo4j for cases_documents.id={$id}");

            // Must have core properties the sync writes.
            $missingCore = $this->countCypher(
                'MATCH (n:CaseDocument {id: $id})\n'
                .'WHERE n.doc_id IS NULL OR n.chunk_index IS NULL OR n.content_hash IS NULL\n'
                .'RETURN count(n) as count',
                ['id' => $id]
            );
            $this->assertSame(0, $missingCore, "CaseDocument node {$id} is missing doc_id/chunk_index/content_hash");
        }

        $this->assertTrue(true);
    }

    /**
     * Invariant: TextractDocument nodes must have core properties that TextractGraphSyncService writes.
     *
     * This is a pure-graph invariant (doesn't require DB joins) and catches graph-side partial upserts.
     */
    public function test_textract_document_nodes_have_core_properties(): void
    {
        $count = $this->countCypher('MATCH (n:TextractDocument) RETURN count(n) as count');
        if ($count === 0) {
            $this->assertTrue(true);
            return;
        }

        // Properties set by TextractGraphSyncService::createTextractDocumentNode(). fileciteturn10file3
        $missing = $this->countCypher(
            'MATCH (n:TextractDocument)\n'
            .'WHERE n.textract_job_id IS NULL OR n.case_id IS NULL OR n.chunk_index IS NULL OR n.processing_status IS NULL\n'
            .'RETURN count(n) as count'
        );
        $this->assertSame(0, $missing, 'Some TextractDocument nodes are missing required properties (textract_job_id/case_id/chunk_index/processing_status).');

        // If processing_status is completed, embedded_at should be present.
        $missingEmbeddedAt = $this->countCypher(
            'MATCH (n:TextractDocument)\n'
            .'WHERE n.processing_status = "completed" AND (n.embedded_at IS NULL OR n.embedded_at = "")\n'
            .'RETURN count(n) as count'
        );
        $this->assertSame(0, $missingEmbeddedAt, 'Some TextractDocument nodes have processing_status=completed but no embedded_at.');
    }
}
