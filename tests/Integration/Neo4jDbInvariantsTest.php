<?php

namespace Tests\Integration;

use App\Services\Graph\GraphRagOrchestrator;
use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Neo4jDbInvariantsTest
 *
 * Reasoning:
 * - Your system is explicitly dual-write: Postgres holds canonical rows (laws/cases/decisions/textract chunks)
 *   and Neo4j holds the reasoning layer. Trust requires proving that "sync" actually creates the expected
 *   nodes/edges and that DB state is consistent with graph state.
 *
 * Strategy:
 * - Create minimal deterministic DB fixtures.
 * - Execute the real sync methods (GraphRagOrchestrator / GraphDatabaseService).
 * - Assert graph facts with Cypher.
 * - Clean up created graph artifacts to keep the test environment stable.
 */
class Neo4jDbInvariantsTest extends TestCase
{
    use UsesTestDatabase;

    protected GraphDatabaseService $graph;

    /** @var array<string> */
    protected array $createdNodeIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Skip in environments where Neo4j is intentionally disabled.
        if (! config('neo4j.sync.enabled', false)) {
            $this->markTestSkipped('Neo4j sync disabled in config (neo4j.sync.enabled=false)');
        }

        $this->graph = app(GraphDatabaseService::class);

        // For invariant tests, we want "no surprises": if Neo4j is expected but not reachable, we skip
        // (so unit-only pipelines can still run). In your full CI, run these in the Neo4j-enabled job.
        if (! $this->graph->isAvailable()) {
            $this->markTestSkipped('Neo4j is not available');
        }

        // Ensure schema exists for clean graphs.
        // This is idempotent (CREATE CONSTRAINT/INDEX IF NOT EXISTS).
        $this->graph->initializeSchema();
    }

    protected function tearDown(): void
    {
        // Best-effort cleanup (do not mask the original test failure).
        try {
            if (isset($this->graph) && $this->graph->isAvailable()) {
                if (! empty($this->createdNodeIds)) {
                    $this->graph->run(
                        'MATCH (n) WHERE n.id IN $ids DETACH DELETE n',
                        ['ids' => array_values(array_unique($this->createdNodeIds))],
                        ['disable_cache' => true]
                    );
                }

                // Cleanup orphan metadata nodes created only by these tests.
                $this->graph->run('MATCH (k:Keyword) WHERE NOT (k)<-[] DETACH DELETE k', [], ['disable_cache' => true]);
                $this->graph->run('MATCH (t:Tag) WHERE NOT (t)<-[] DETACH DELETE t', [], ['disable_cache' => true]);
            }
        } catch (\Throwable) {
            // ignore cleanup failures
        }

        parent::tearDown();
    }

    private function rememberNodeId(string $id): void
    {
        $this->createdNodeIds[] = $id;
    }

    private function assertNodeExists(string $label, string $id): void
    {
        $res = $this->graph->run(
            "MATCH (n:{$label} {id: \$id}) RETURN count(n) as c",
            ['id' => $id],
            ['disable_cache' => true]
        );
        $count = (int) ($res->first()?->get('c') ?? 0);
        $this->assertSame(1, $count, "Expected exactly 1 {$label} node with id={$id}");
    }

    private function assertRelationshipExists(string $fromLabel, string $fromId, string $type, string $toLabel, string $toId): void
    {
        $res = $this->graph->run(
            "MATCH (a:{$fromLabel} {id: \$fromId})-[:{$type}]->(b:{$toLabel} {id: \$toId}) RETURN count(*) as c",
            ['fromId' => $fromId, 'toId' => $toId],
            ['disable_cache' => true]
        );
        $count = (int) ($res->first()?->get('c') ?? 0);
        $this->assertGreaterThan(0, $count, "Expected {$type} relationship {$fromLabel}({$fromId}) -> {$toLabel}({$toId})");
    }

    /**
     * Invariant: When Graph is enabled, the Neo4j service should report healthy.
     *
     * Reasoning:
     * - Without a healthy graph connection, the reasoning layer and graph-enhanced RAG features cannot be trusted.
     */
    public function test_neo4j_health_status_is_healthy(): void
    {
        $status = $this->graph->getHealthStatus();
        $this->assertTrue($status['available'] ?? false);
        $this->assertTrue($status['healthy'] ?? false);
    }

    /**
     * Invariant: syncLaw creates LawDocument node + (optional) Jurisdiction node + BELONGS_TO_JURISDICTION.
     *
     * Based on GraphRagOrchestrator::syncLaw behavior.
     */
    public function test_sync_law_creates_expected_graph_entities(): void
    {
        $lawsTable = config('vizra-adk.tables.laws', 'laws');
        if (! Schema::hasTable($lawsTable)) {
            $this->markTestSkipped("Missing table: {$lawsTable}");
        }

        $lawId = (string) Str::ulid();
        $docId = 'law-test-'.Str::random(10);
        $jur = 'HR-'.Str::upper(Str::random(4));

        DB::table($lawsTable)->insert([
            'id' => $lawId,
            'doc_id' => $docId,
            'title' => 'Test Law '.Str::random(6),
            'law_number' => 'NN 152/08',
            'jurisdiction' => $jur,
            'country' => 'HR',
            'language' => 'hr',
            'chunk_index' => 0,
            'content' => 'Ovaj testni zakon sadrži odredbe o kaznenom postupku i pretresu doma. '.
                'Pretres doma i prava okrivljenika. Kazneni postupak.',
            'metadata' => json_encode(['article_number' => '1']),
            'content_hash' => hash('sha256', 'x'),
            'token_count' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orchestrator = app(GraphRagOrchestrator::class);
        $orchestrator->syncLaw($lawId);

        $this->rememberNodeId($lawId);
        $this->assertNodeExists('LawDocument', $lawId);

        $jurNodeId = 'jurisdiction_'.$jur;
        $this->rememberNodeId($jurNodeId);
        $this->assertNodeExists('Jurisdiction', $jurNodeId);
        $this->assertRelationshipExists('LawDocument', $lawId, 'BELONGS_TO_JURISDICTION', 'Jurisdiction', $jurNodeId);

        // Should have at least one keyword edge (keyword extraction is designed to link HAS_KEYWORD).
        $kwCount = $this->graph->run(
            'MATCH (l:LawDocument {id: $id})-[:HAS_KEYWORD]->(k:Keyword) RETURN count(k) as c',
            ['id' => $lawId],
            ['disable_cache' => true]
        );
        $this->assertGreaterThan(0, (int) ($kwCount->first()?->get('c') ?? 0), 'LawDocument should have HAS_KEYWORD edges');
    }

    /**
     * Invariant: CaseDocument sync should create CaseDocument node and link citations to laws.
     *
     * Reasoning:
     * - GraphCitationLinker is expected to create CITES relationships to LawDocument when law_number is present.
     */
    public function test_sync_case_document_creates_node_and_cites_law(): void
    {
        $casesTable = config('vizra-adk.tables.cases', 'cases');
        $casesDocs = config('vizra-adk.tables.cases_documents', 'cases_documents');
        $lawsTable = config('vizra-adk.tables.laws', 'laws');

        if (! (Schema::hasTable($casesTable) && Schema::hasTable($casesDocs) && Schema::hasTable($lawsTable))) {
            $this->markTestSkipped('Required tables missing for case graph invariant');
        }

        // Insert a referenced law (citation target)
        $lawId = (string) Str::ulid();
        DB::table($lawsTable)->insert([
            'id' => $lawId,
            'doc_id' => 'law-cited-'.Str::random(8),
            'title' => 'Cited Law',
            'law_number' => 'NN 152/08',
            'jurisdiction' => 'HR',
            'chunk_index' => 0,
            'content' => 'Zakon o kaznenom postupku - test content.',
            'metadata' => json_encode(['article_number' => '215']),
            'content_hash' => hash('sha256', 'law'),
            'token_count' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert a case parent
        $caseId = (string) Str::ulid();
        DB::table($casesTable)->insert([
            'id' => $caseId,
            'case_number' => 'K-'.rand(100, 999).'/'.date('Y'),
            'title' => 'Test Case '.Str::random(5),
            'court' => 'Općinski sud '.Str::random(6),
            'jurisdiction' => 'HR',
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert a case document chunk mentioning the law number to trigger GraphCitationLinker
        $caseDocId = (string) Str::ulid();
        $content = 'U ovom predmetu primjenjuje se NN 152/08, čl. 215. Pretres doma mora biti razmjeran.';
        DB::table($casesDocs)->insert([
            'id' => $caseDocId,
            'case_id' => $caseId,
            'doc_id' => 'doc-case-'.Str::random(8),
            'chunk_index' => 0,
            'title' => 'Case document',
            'content' => $content,
            'metadata' => json_encode(['source' => 'test']),
            'content_hash' => hash('sha256', $content),
            'token_count' => 30,
            'embedding_provider' => 'manual',
            'embedding_model' => 'none',
            'embedding_dimensions' => 1536,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orchestrator = app(GraphRagOrchestrator::class);
        $orchestrator->syncCase($caseDocId);

        $this->rememberNodeId($lawId);
        $this->rememberNodeId($caseDocId);

        $this->assertNodeExists('CaseDocument', $caseDocId);
        $this->assertNodeExists('LawDocument', $lawId);
        $this->assertRelationshipExists('CaseDocument', $caseDocId, 'CITES', 'LawDocument', $lawId);
    }

    /**
     * Invariant: CourtDecision sync should create CourtDecisionDocument nodes and attach Court/Jurisdiction.
     *
     * Based on GraphRagOrchestrator::syncCourtDecision behavior.
     */
    public function test_sync_court_decision_creates_document_court_and_jurisdiction_nodes(): void
    {
        $decisionsTable = config('vizra-adk.tables.court_decisions', 'court_decisions');
        $docsTable = config('vizra-adk.tables.court_decision_documents', 'court_decision_documents');

        if (! (Schema::hasTable($decisionsTable) && Schema::hasTable($docsTable))) {
            $this->markTestSkipped('Required tables missing for decision graph invariant');
        }

        $decisionId = (string) Str::ulid();
        $courtName = 'Vrhovni sud '.Str::random(6);
        $jur = 'HR-'.Str::upper(Str::random(4));

        DB::table($decisionsTable)->insert([
            'id' => $decisionId,
            'case_number' => 'Kzz-'.rand(1, 999).'/'.date('Y'),
            'title' => 'Test Decision '.Str::random(5),
            'court' => $courtName,
            'jurisdiction' => $jur,
            'judge' => 'Judge '.Str::random(5),
            'decision_date' => now()->toDateString(),
            'decision_type' => 'presuda',
            'ecli' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $docId = (string) Str::ulid();
        $docGroup = 'dec-'.Str::random(8);
        $content = 'Ova presuda se odnosi na pretres doma i proporcionalnost.';
        DB::table($docsTable)->insert([
            'id' => $docId,
            'decision_id' => $decisionId,
            'doc_id' => $docGroup,
            'chunk_index' => 0,
            'content' => $content,
            'metadata' => json_encode(['source' => 'test']),
            'content_hash' => hash('sha256', $content),
            'embedding_provider' => 'manual',
            'embedding_model' => 'none',
            'embedding_dimensions' => 1536,
            // embedding / embedding_vector are required by schema but can be nullable depending on environment
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orchestrator = app(GraphRagOrchestrator::class);
        $orchestrator->syncCourtDecision($decisionId);

        $this->rememberNodeId($docId);

        $this->assertNodeExists('CourtDecisionDocument', $docId);

        // Court node ID is derived from md5(court) per orchestrator
        $courtNodeId = 'court_'.md5($courtName);
        $jurNodeId = 'jurisdiction_'.$jur;

        $this->rememberNodeId($courtNodeId);
        $this->rememberNodeId($jurNodeId);

        $this->assertNodeExists('Court', $courtNodeId);
        $this->assertNodeExists('Jurisdiction', $jurNodeId);
        $this->assertRelationshipExists('CourtDecisionDocument', $docId, 'DECIDED_BY', 'Court', $courtNodeId);
        $this->assertRelationshipExists('CourtDecisionDocument', $docId, 'BELONGS_TO_JURISDICTION', 'Jurisdiction', $jurNodeId);
    }

    /**
     * Invariant: Textract job sync should create TextractDocument nodes and (when a case document exists)
     * link TextractDocument -> CaseDocument with BELONGS_TO.
     */
    public function test_sync_textract_job_creates_textract_document_and_links_to_case_document(): void
    {
        if (! (Schema::hasTable('textract_jobs') && Schema::hasTable('textract_documents'))) {
            $this->markTestSkipped('Required Textract tables missing');
        }

        $casesTable = config('vizra-adk.tables.cases', 'cases');
        $casesDocs = config('vizra-adk.tables.cases_documents', 'cases_documents');

        if (! (Schema::hasTable($casesTable) && Schema::hasTable($casesDocs))) {
            $this->markTestSkipped('Cases tables missing for textract linking invariant');
        }

        // Parent case + case document (target for BELONGS_TO)
        $caseId = (string) Str::ulid();
        DB::table($casesTable)->insert([
            'id' => $caseId,
            'case_number' => 'TX-'.rand(100, 999).'/'.date('Y'),
            'title' => 'Textract Link Case',
            'court' => 'Test Court '.Str::random(5),
            'jurisdiction' => 'HR',
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $caseDocId = (string) Str::ulid();
        $caseContent = 'Dokument predmeta koji će biti povezan s TextractDocument.';
        DB::table($casesDocs)->insert([
            'id' => $caseDocId,
            'case_id' => $caseId,
            'doc_id' => 'doc-case-'.Str::random(8),
            'chunk_index' => 0,
            'title' => 'CaseDoc for Textract link',
            'content' => $caseContent,
            'metadata' => json_encode([]),
            'content_hash' => hash('sha256', $caseContent),
            'token_count' => 20,
            'embedding_provider' => 'manual',
            'embedding_model' => 'none',
            'embedding_dimensions' => 1536,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Textract job
        $jobId = DB::table('textract_jobs')->insertGetId([
            'drive_file_id' => 'drive-'.Str::random(8),
            'drive_file_name' => 'test.pdf',
            'status' => 'succeeded',
            'error' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Attach case_id if the column exists in this environment
        if (Schema::hasColumn('textract_jobs', 'case_id')) {
            DB::table('textract_jobs')->where('id', $jobId)->update(['case_id' => $caseId]);
        }

        // Mark embedding synced so graph sync is allowed in the broader pipeline.
        if (Schema::hasColumn('textract_jobs', 'embedding_status')) {
            DB::table('textract_jobs')->where('id', $jobId)->update([
                'embedding_status' => 'synced',
                'embedding_synced_at' => now(),
                'graph_sync_status' => 'pending',
                'graph_synced_at' => null,
            ]);
        }

        // Textract document chunk
        $tdId = DB::table('textract_documents')->insertGetId([
            'textract_job_id' => $jobId,
            'case_id' => $caseId,
            'content' => 'Pretres doma i proporcionalnost. NN 152/08.',
            'chunk_index' => 0,
            'chunk_overlap' => 0,
            'embedding' => json_encode([0.1, 0.2, 0.3]),
            'embedding_provider' => 'manual',
            'embedding_model' => 'none',
            'embedding_dimensions' => 3,
            'token_count' => 10,
            'processing_status' => 'completed',
            'processing_error' => null,
            'embedded_at' => now(),
            'metadata' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orchestrator = app(GraphRagOrchestrator::class);
        $orchestrator->syncTextractJob((int) $jobId);

        // GraphRagOrchestrator uses textract_documents.id as the TextractDocument node id
        $textractNodeId = (string) $tdId;

        $this->rememberNodeId($textractNodeId);
        $this->rememberNodeId($caseDocId);

        $this->assertNodeExists('TextractDocument', $textractNodeId);
        $this->assertNodeExists('CaseDocument', $caseDocId);

        // BELONGS_TO should exist to the case document
        $this->assertRelationshipExists('TextractDocument', $textractNodeId, 'BELONGS_TO', 'CaseDocument', $caseDocId);

        // DB-side invariant: job graph_sync_status should be synced when syncTextractJob succeeds
        if (Schema::hasColumn('textract_jobs', 'graph_sync_status')) {
            $job = DB::table('textract_jobs')->where('id', $jobId)->first();
            $this->assertSame('synced', $job->graph_sync_status);
        }
    }
}
