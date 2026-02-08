<?php

namespace Tests\Integration;

use App\Services\Graph\CaseGraphSyncService;
use App\Services\Graph\DecisionGraphSyncService;
use App\Services\Graph\GraphRagOrchestrator;
use App\Services\Graph\LawGraphSyncService;
use App\Services\GraphDatabaseService;
use App\Services\TaggingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Neo4jDbConsistencyInvariantsTest
 *
 * Reasoning:
 * - The graph layer is a second source of truth used for reasoning and navigation.
 * - The biggest practical risk is drift: DB says "synced" / data exists, but Neo4j nodes/edges are missing.
 * - These tests create minimal deterministic fixtures, execute the real sync services, and then verify
 *   graph nodes + relationships exist and match DB identifiers.
 *
 * Execution notes:
 * - Tests are skipped if Neo4j is disabled or unavailable.
 * - We avoid deleting global Tag nodes (they are shared and constrained by Tag.name uniqueness).
 */
class Neo4jDbConsistencyInvariantsTest extends TestCase
{
    use UsesTestDatabase;

    protected ?GraphDatabaseService $graph = null;

    /** @var array<string> */
    protected array $cleanupNodeIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (! config('neo4j.sync.enabled', false)) {
            $this->markTestSkipped('Neo4j sync disabled (NEO4J_ENABLED=false)');
        }

        $this->graph = app(GraphDatabaseService::class);

        if (! $this->graph->isAvailable()) {
            $this->markTestSkipped('Neo4j is not available');
        }

        // Schema and tag hierarchy are idempotent (IF NOT EXISTS), safe to run.
        $this->graph->initializeSchema();
        app(TaggingService::class)->initializeTagHierarchy();
    }

    protected function tearDown(): void
    {
        if ($this->graph && $this->graph->isAvailable()) {
            // Delete nodes created by this test run (except Tag nodes).
            if (! empty($this->cleanupNodeIds)) {
                $this->graph->run('MATCH (n) WHERE n.id IN $ids DETACH DELETE n', [
                    'ids' => array_values(array_unique($this->cleanupNodeIds)),
                ]);
            }

            // Clean up orphan keywords (same pattern used in GraphKeywordLinker::unlinkAll).
            $this->graph->run('MATCH (k:Keyword) WHERE NOT (k)<-[] DELETE k');

            // Clean up orphan jurisdictions/courts created by these tests.
            $this->graph->run('MATCH (j:Jurisdiction) WHERE j.name STARTS WITH "TEST_" DETACH DELETE j');
            $this->graph->run('MATCH (c:Court) WHERE c.name STARTS WITH "TEST_" DETACH DELETE c');
        }

        parent::tearDown();
    }

    private function requireTable(string $table): void
    {
        if (! Schema::hasTable($table)) {
            $this->markTestSkipped("Missing table: {$table}");
        }
    }

    private function assertGraphHasNode(string $label, string $id): void
    {
        $res = $this->graph->run(
            "MATCH (n:{$label} {id: $id}) RETURN count(n) as c",
            ['id' => $id]
        );
        $count = $res->first()?->get('c') ?? 0;
        $this->assertGreaterThan(0, (int) $count, "Expected {$label} node to exist: {$id}");
    }

    private function assertGraphHasRel(string $fromLabel, string $fromId, string $rel, string $toLabel, string $toId): void
    {
        $res = $this->graph->run(
            "MATCH (a:{$fromLabel} {id: $fromId})-[:{$rel}]->(b:{$toLabel} {id: $toId}) RETURN count(*) as c",
            ['fromId' => $fromId, 'toId' => $toId]
        );
        $count = $res->first()?->get('c') ?? 0;
        $this->assertGreaterThan(0, (int) $count, "Expected relationship {$fromLabel}({$fromId})-[:{$rel}]->{$toLabel}({$toId})");
    }

    /**
     * Invariant: Law sync must create a LawDocument node and attach it to Jurisdiction and Tags.
     *
     * Why: LawDocument is the canonical Neo4j representation of a law chunk; without it,
     * the legal reasoning layer will silently under-retrieve.
     */
    public function test_sync_law_creates_lawdocument_node_jurisdiction_and_tags(): void
    {
        $this->requireTable('laws');

        $lawId = (string) Str::ulid();
        $jur = 'TEST_'.Str::upper(Str::random(8));

        DB::table('laws')->insert([
            'id' => $lawId,
            'doc_id' => 'law-'.Str::random(10),
            'title' => 'Test Law',
            'law_number' => 'NN 152/08',
            'jurisdiction' => $jur,
            'chunk_index' => 0,
            'content' => 'Ovo je kazneno pravo i kazneni postupak. NN 152/08.',
            'metadata' => json_encode(['tags' => []], JSON_UNESCAPED_UNICODE),
            'content_hash' => hash('sha256', 'Ovo je kazneno pravo i kazneni postupak. NN 152/08.'),
            'token_count' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(LawGraphSyncService::class)->sync($lawId); // syncs LawDocument, Jurisdiction, tags fileciteturn9file2 fileciteturn11file12

        $this->cleanupNodeIds[] = $lawId;
        $this->cleanupNodeIds[] = 'jurisdiction_'.$jur;

        $this->assertGraphHasNode('LawDocument', $lawId);
        $this->assertGraphHasNode('Jurisdiction', 'jurisdiction_'.$jur);
        $this->assertGraphHasRel('LawDocument', $lawId, 'BELONGS_TO_JURISDICTION', 'Jurisdiction', 'jurisdiction_'.$jur);

        // Deterministic tag from content keyword "kazneno" => criminal_law tag fileciteturn11file12
        $tagId = 'tag_criminal_law';
        $this->assertGraphHasNode('Tag', $tagId);
        $this->assertGraphHasRel('LawDocument', $lawId, 'HAS_TAG', 'Tag', $tagId);
    }

    /**
     * Invariant: CaseDocument sync must create CaseDocument node and CITES relationship when a law citation exists.
     *
     * Why: this is how your system grounds case material in statutory sources (GraphCitationLinker).
     */
    public function test_sync_case_document_creates_node_and_cites_lawdocument(): void
    {
        $this->requireTable('laws');
        $this->requireTable(config('vizra-adk.tables.cases_documents', 'cases_documents'));

        // Create referenced law in DB
        $lawId = (string) Str::ulid();
        DB::table('laws')->insert([
            'id' => $lawId,
            'doc_id' => 'law-'.Str::random(10),
            'title' => 'Referenced Law',
            'law_number' => 'NN 152/08',
            'jurisdiction' => 'HR',
            'chunk_index' => 0,
            'content' => 'Law content.',
            'content_hash' => hash('sha256', 'Law content.'),
            'token_count' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create a case document that cites the law number
        $casesDocsTable = config('vizra-adk.tables.cases_documents', 'cases_documents');
        $caseDocId = (string) Str::ulid();
        $caseDocContent = 'Poziv na NN 152/08 članak 1. Ovo je kazneno.';

        DB::table($casesDocsTable)->insert([
            'id' => $caseDocId,
            'case_id' => null,
            'doc_id' => 'case-doc-'.Str::random(8),
            'title' => 'Test Case Document',
            'chunk_index' => 0,
            'content' => $caseDocContent,
            'metadata' => json_encode([], JSON_UNESCAPED_UNICODE),
            'content_hash' => hash('sha256', $caseDocContent),
            'token_count' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(CaseGraphSyncService::class)->sync($caseDocId); // creates CaseDocument node and CITES via GraphCitationLinker fileciteturn10file1 fileciteturn10file13

        $this->cleanupNodeIds[] = $caseDocId;
        $this->cleanupNodeIds[] = $lawId;

        $this->assertGraphHasNode('CaseDocument', $caseDocId);
        $this->assertGraphHasNode('LawDocument', $lawId);
        $this->assertGraphHasRel('CaseDocument', $caseDocId, 'CITES', 'LawDocument', $lawId);
    }

    /**
     * Invariant: Court decision sync must create CourtDecisionDocument node and link to Court/Jurisdiction.
     *
     * Why: the reasoning layer needs these nodes and edges for traversal and filtering.
     */
    public function test_sync_court_decision_creates_decided_by_and_jurisdiction_relationships_and_tags(): void
    {
        $decisionsTable = config('vizra-adk.tables.court_decisions', 'court_decisions');
        $decisionDocsTable = config('vizra-adk.tables.court_decision_documents', 'court_decision_documents');

        $this->requireTable($decisionsTable);
        $this->requireTable($decisionDocsTable);

        $decisionId = (string) Str::ulid();
        $jur = 'TEST_'.Str::upper(Str::random(8));
        $courtName = 'TEST_Vrhovni sud '.Str::random(6);

        DB::table($decisionsTable)->insert([
            'id' => $decisionId,
            'case_number' => 'K-TEST/'.rand(1, 9999).'/2025',
            'title' => 'Test Decision',
            'court' => $courtName,
            'jurisdiction' => $jur,
            'decision_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $docId = (string) Str::ulid();
        $content = 'Ovo je kazneno pravo. NN 152/08.';
        DB::table($decisionDocsTable)->insert([
            'id' => $docId,
            'decision_id' => $decisionId,
            'doc_id' => 'decision-doc-'.Str::random(8),
            'chunk_index' => 0,
            'content' => $content,
            'metadata' => json_encode([], JSON_UNESCAPED_UNICODE),
            'content_hash' => hash('sha256', $content),
            'embedding_provider' => 'manual',
            'embedding_model' => 'none',
            'embedding_dimensions' => 1536,
            // embedding_vector/embedding may exist; nullable in some environments
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(DecisionGraphSyncService::class)->sync($decisionId); // creates CourtDecisionDocument + DECIDED_BY + BELONGS_TO_JURISDICTION fileciteturn10file3

        $this->cleanupNodeIds[] = $docId;
        $this->cleanupNodeIds[] = 'jurisdiction_'.$jur;
        $this->cleanupNodeIds[] = 'court_'.md5($courtName);

        $this->assertGraphHasNode('CourtDecisionDocument', $docId);
        $this->assertGraphHasNode('Court', 'court_'.md5($courtName));
        $this->assertGraphHasNode('Jurisdiction', 'jurisdiction_'.$jur);

        $this->assertGraphHasRel('CourtDecisionDocument', $docId, 'DECIDED_BY', 'Court', 'court_'.md5($courtName));
        $this->assertGraphHasRel('CourtDecisionDocument', $docId, 'BELONGS_TO_JURISDICTION', 'Jurisdiction', 'jurisdiction_'.$jur);

        // Court name contains "vrhovni" => supreme_court tag should be applied fileciteturn11file12
        $tagId = 'tag_supreme_court';
        $this->assertGraphHasNode('Tag', $tagId);
        $this->assertGraphHasRel('CourtDecisionDocument', $docId, 'HAS_TAG', 'Tag', $tagId);
    }

    /**
     * Invariant: If a TextractJob is graph-synced, corresponding TextractDocument nodes must exist.
     *
     * Why: textract_jobs.graph_sync_status is used as the operational truth that graph sync succeeded.
     * Drift here breaks the entire "upload → OCR → graph reasoning" chain.
     */
    public function test_sync_textract_job_creates_textractdocument_nodes_and_links_to_case_document_when_present(): void
    {
        $this->requireTable('textract_jobs');
        $this->requireTable('textract_documents');
        $this->requireTable(config('vizra-adk.tables.cases', 'cases'));
        $this->requireTable(config('vizra-adk.tables.cases_documents', 'cases_documents'));

        $casesTable = config('vizra-adk.tables.cases', 'cases');
        $casesDocsTable = config('vizra-adk.tables.cases_documents', 'cases_documents');

        $caseId = (string) Str::ulid();
        DB::table($casesTable)->insert([
            'id' => $caseId,
            'case_number' => 'TEST-CASE-'.Str::random(6),
            'title' => 'Test Case',
            'court' => 'TEST_Court',
            'jurisdiction' => 'HR',
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // CaseDocument row for BELONGS_TO relationship creation in syncTextractJob fileciteturn9file5
        $caseDocId = (string) Str::ulid();
        DB::table($casesDocsTable)->insert([
            'id' => $caseDocId,
            'case_id' => $caseId,
            'doc_id' => 'case-doc-'.Str::random(8),
            'title' => 'CaseDoc',
            'chunk_index' => 0,
            'content' => 'Some case content',
            'metadata' => json_encode([], JSON_UNESCAPED_UNICODE),
            'content_hash' => hash('sha256', 'Some case content'),
            'token_count' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $jobId = DB::table('textract_jobs')->insertGetId([
            'drive_file_id' => 'drive-'.Str::random(8),
            'drive_file_name' => 'test.pdf',
            's3_key' => null,
            'job_id' => null,
            'status' => 'succeeded',
            'error' => null,
            'created_at' => now(),
            'updated_at' => now(),
            // fields added by later migration may exist; safe to omit
        ]);

        // Ensure case_id is stored if column exists (later migrations add it in model fillable; not in base migration)
        if (Schema::hasColumn('textract_jobs', 'case_id')) {
            DB::table('textract_jobs')->where('id', $jobId)->update(['case_id' => $caseId]);
        }

        // Create two completed textract document chunks
        $docRow1 = DB::table('textract_documents')->insertGetId([
            'textract_job_id' => $jobId,
            'case_id' => $caseId,
            'content' => 'Chunk 0 kazneno',
            'chunk_index' => 0,
            'chunk_overlap' => 0,
            'embedding' => json_encode([0.0, 0.0, 0.0]),
            'embedding_provider' => 'manual',
            'embedding_model' => 'none',
            'embedding_dimensions' => 3,
            'token_count' => 3,
            'processing_status' => 'completed',
            'embedded_at' => now(),
            'metadata' => json_encode([], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $docRow2 = DB::table('textract_documents')->insertGetId([
            'textract_job_id' => $jobId,
            'case_id' => $caseId,
            'content' => 'Chunk 1 kazneno',
            'chunk_index' => 1,
            'chunk_overlap' => 0,
            'embedding' => json_encode([0.0, 0.0, 0.0]),
            'embedding_provider' => 'manual',
            'embedding_model' => 'none',
            'embedding_dimensions' => 3,
            'token_count' => 3,
            'processing_status' => 'completed',
            'embedded_at' => now(),
            'metadata' => json_encode([], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Execute real job-level graph sync
        app(GraphRagOrchestrator::class)->syncTextractJob($jobId); // creates TextractDocument nodes and BELONGS_TO CaseDocument fileciteturn9file5

        // Track created nodes for cleanup
        $this->cleanupNodeIds[] = (string) $docRow1;
        $this->cleanupNodeIds[] = (string) $docRow2;
        $this->cleanupNodeIds[] = (string) $caseDocId;

        // Verify graph nodes exist
        $this->assertGraphHasNode('TextractDocument', (string) $docRow1);
        $this->assertGraphHasNode('TextractDocument', (string) $docRow2);
        $this->assertGraphHasNode('CaseDocument', (string) $caseDocId);

        // Verify BELONGS_TO relationship exists from TextractDocument to CaseDocument
        $this->assertGraphHasRel('TextractDocument', (string) $docRow1, 'BELONGS_TO', 'CaseDocument', (string) $caseDocId);
        $this->assertGraphHasRel('TextractDocument', (string) $docRow2, 'BELONGS_TO', 'CaseDocument', (string) $caseDocId);

        // Verify DB status updated
        if (Schema::hasColumn('textract_jobs', 'graph_sync_status') && Schema::hasColumn('textract_jobs', 'graph_synced_at')) {
            $row = DB::table('textract_jobs')->where('id', $jobId)->first();
            $this->assertSame('synced', $row->graph_sync_status);
            $this->assertNotNull($row->graph_synced_at);
        }
    }
}
