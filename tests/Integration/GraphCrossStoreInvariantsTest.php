<?php

namespace Tests\Integration;

use App\Models\CourtDecision;
use App\Models\CourtDecisionDocument;
use App\Models\Law;
use App\Services\Graph\GraphRagOrchestrator;
use App\Services\Graph\CaseGraphSyncService;
use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * GraphCrossStoreInvariantsTest
 *
 * Reasoning:
 * - Your system is "dual-written": relational DB is the source of truth, Neo4j is the reasoning/index layer.
 * - The highest-risk failures are mismatch states: DB says data exists/synced, but Neo4j is missing nodes or edges.
 * - These tests provide execution proof that key sync functions actually materialize the expected graph entities.
 *
 * Design:
 * - Skip automatically if Neo4j isn't available.
 * - Use fresh, deterministic fixtures created inside the test.
 * - Verify not only node existence, but critical relationships (jurisdiction, court, keyword, citation).
 */
class GraphCrossStoreInvariantsTest extends TestCase
{
    use UsesTestDatabase;

    protected GraphDatabaseService $graph;

    /** @var array<string> */
    protected array $createdNodeIds = [];

    /** @var array<string> */
    protected array $createdKeywordIds = [];

    /** @var array<string> */
    protected array $createdTagIds = [];

    /** @var array<string> */
    protected array $createdCourtIds = [];

    /** @var array<string> */
    protected array $createdJurisdictionIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget('neo4j:status');
        Config::set('neo4j.sync.enabled', true);

        $this->graph = app(GraphDatabaseService::class);

        if (! $this->graph->isAvailable()) {
            $this->markTestSkipped('Neo4j is not available');
        }

        // Ensure core constraints/indexes exist; safe to call repeatedly.
        $this->graph->initializeSchema();
    }

    protected function tearDown(): void
    {
        // Best-effort cleanup: delete nodes created by this test run.
        try {
            if (isset($this->graph) && $this->graph->isAvailable()) {
                $ids = array_values(array_unique(array_merge(
                    $this->createdNodeIds,
                    $this->createdKeywordIds,
                    $this->createdTagIds,
                    $this->createdCourtIds,
                    $this->createdJurisdictionIds,
                )));

                if (! empty($ids)) {
                    $this->graph->run('MATCH (n) WHERE n.id IN $ids DETACH DELETE n', ['ids' => $ids]);
                }

                // Cleanup any orphaned metadata nodes left behind
                $this->graph->run('MATCH (k:Keyword) WHERE NOT (k)<-[] DELETE k');
                $this->graph->run('MATCH (t:Tag) WHERE NOT (t)<-[] DELETE t');
                $this->graph->run('MATCH (j:Jurisdiction) WHERE NOT (j)<-[] DELETE j');
                $this->graph->run('MATCH (c:Court) WHERE NOT (c)<-[] DELETE c');
            }
        } catch (\Throwable) {
            // ignore cleanup errors
        }

        parent::tearDown();
    }

    private function assertGraphHasNode(string $label, string $id): void
    {
        $res = $this->graph->run(
            "MATCH (n:$label {id: \$id}) RETURN count(n) as c",
            ['id' => $id]
        );

        $count = (int) $res->first()->get('c');
        $this->assertSame(1, $count, "Expected Neo4j node $label:$id to exist");
    }

    private function assertGraphHasRelationship(string $fromLabel, string $fromId, string $type, string $toLabel, string $toId): void
    {
        $res = $this->graph->run(
            "MATCH (a:$fromLabel {id: \$fromId})-[:$type]->(b:$toLabel {id: \$toId}) RETURN count(*) as c",
            ['fromId' => $fromId, 'toId' => $toId]
        );

        $count = (int) $res->first()->get('c');
        $this->assertGreaterThan(0, $count, "Expected relationship $fromLabel:$fromId -[:$type]-> $toLabel:$toId");
    }

    /**
     * Invariant: Law sync must materialize:
     * - (LawDocument {id}) node
     * - (Jurisdiction {id=jurisdiction_<jurisdiction>}) node when jurisdiction present
     * - BELONGS_TO_JURISDICTION relationship
     * - HAS_KEYWORD edges to Keyword nodes
     *
     * Why:
     * - GraphRAG uses keyword and jurisdiction traversals for enhanced query and filtering.
     */
    public function test_sync_law_creates_law_document_and_jurisdiction_and_keywords(): void
    {
        $law = Law::factory()->create([
            'jurisdiction' => 'HR',
            'law_number' => 'NN 152/08',
            'title' => 'Zakon o kaznenom postupku (test)',
            'content' => 'Ovaj zakon uređuje kazneni postupak. Presuda se donosi u skladu sa zakonom.',
            'embedding_vector' => array_fill(0, 1536, 0.1),
        ]);

        $this->createdNodeIds[] = (string) $law->id;
        $jurisdictionId = 'jurisdiction_HR';
        $this->createdJurisdictionIds[] = $jurisdictionId;

        $orchestrator = app(GraphRagOrchestrator::class);
        $orchestrator->syncLaw((string) $law->id);

        $this->assertGraphHasNode('LawDocument', (string) $law->id);
        $this->assertGraphHasNode('Jurisdiction', $jurisdictionId);
        $this->assertGraphHasRelationship('LawDocument', (string) $law->id, 'BELONGS_TO_JURISDICTION', 'Jurisdiction', $jurisdictionId);

        // Keyword existence: at least one HAS_KEYWORD edge.
        $kwCount = $this->graph->run(
            'MATCH (d:LawDocument {id: $id})-[:HAS_KEYWORD]->(k:Keyword) RETURN count(k) as c',
            ['id' => (string) $law->id]
        );
        $this->assertGreaterThan(0, (int) $kwCount->first()->get('c'), 'LawDocument should have HAS_KEYWORD relationships');

        // Capture the specific keyword ids for cleanup (best-effort)
        $kwIds = $this->graph->run(
            'MATCH (:LawDocument {id: $id})-[:HAS_KEYWORD]->(k:Keyword) RETURN k.id as id',
            ['id' => (string) $law->id]
        );
        foreach ($kwIds as $rec) {
            $this->createdKeywordIds[] = (string) $rec->get('id');
        }

        // Tagging is rule-based; expect at least one HAS_TAG
        $tagCount = $this->graph->run(
            'MATCH (d:LawDocument {id: $id})-[:HAS_TAG]->(t:Tag) RETURN count(t) as c',
            ['id' => (string) $law->id]
        );
        $this->assertGreaterThan(0, (int) $tagCount->first()->get('c'), 'LawDocument should have HAS_TAG relationships');

        $tagIds = $this->graph->run(
            'MATCH (:LawDocument {id: $id})-[:HAS_TAG]->(t:Tag) RETURN t.id as id',
            ['id' => (string) $law->id]
        );
        foreach ($tagIds as $rec) {
            $this->createdTagIds[] = (string) $rec->get('id');
        }
    }

    /**
     * Invariant: Case sync must create a CaseDocument node and preserve citations.
     *
     * Why:
     * - GraphCitationLinker is expected to create CITES relationships to LawDocument when
     *   a case content references a law number. This is foundational to legal reasoning chains.
     */
    public function test_case_document_sync_creates_case_node_and_cites_law_when_law_number_present(): void
    {
        // Arrange: create a referenced law in relational DB
        $law = Law::factory()->create([
            'jurisdiction' => 'HR',
            'law_number' => 'NN 152/08',
            'title' => 'ZKP (test)',
            'content' => 'Test law content',
            'embedding_vector' => array_fill(0, 1536, 0.1),
        ]);
        $this->createdNodeIds[] = (string) $law->id;

        // Create a case document row (use direct insert to avoid factory coupling)
        $caseDocId = (string) Str::ulid();
        DB::table(config('vizra-adk.tables.cases_documents', 'cases_documents'))->insert([
            'id' => $caseDocId,
            'case_id' => null,
            'doc_id' => 'case-doc-'.(string) Str::ulid(),
            'upload_id' => null,
            'title' => 'Case document with citation',
            'category' => 'case',
            'language' => 'hr',
            'tags' => json_encode(['test']),
            'chunk_index' => 0,
            'content' => 'U skladu s NN 152/08 i odredbama zakona, sud donosi rješenje.',
            'metadata' => json_encode(['jurisdiction' => 'HR']),
            'source' => 'test',
            'source_id' => 'test-1',
            'embedding_provider' => 'manual',
            'embedding_model' => 'none',
            'embedding_dimensions' => 1536,
            'embedding_vector' => json_encode(array_fill(0, 1536, 0.0)),
            'embedding_norm' => null,
            'content_hash' => hash('sha256', 'U skladu s NN 152/08 i odredbama zakona, sud donosi rješenje.'),
            'token_count' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->createdNodeIds[] = $caseDocId;

        // Act: sync case + also sync law to ensure LawDocument exists in graph (citation linker upserts it too)
        $orchestrator = app(GraphRagOrchestrator::class);
        $orchestrator->syncLaw((string) $law->id);

        $caseSync = app(CaseGraphSyncService::class);
        $caseSync->sync($caseDocId);

        // Assert nodes
        $this->assertGraphHasNode('CaseDocument', $caseDocId);
        $this->assertGraphHasNode('LawDocument', (string) $law->id);

        // Assert CITES edge exists (GraphCitationLinker uses CITES for law_number citations)
        $this->assertGraphHasRelationship('CaseDocument', $caseDocId, 'CITES', 'LawDocument', (string) $law->id);
    }

    /**
     * Invariant: Court decision sync must materialize:
     * - CourtDecisionDocument nodes for each DB chunk
     * - DECIDED_BY -> Court if court present
     * - BELONGS_TO_JURISDICTION -> Jurisdiction if jurisdiction present
     *
     * Why:
     * - These edges are directly used in graph traversal and filtering.
     */
    public function test_sync_court_decision_creates_decision_nodes_court_and_jurisdiction_edges(): void
    {
        $decision = CourtDecision::factory()->create([
            'court' => 'Vrhovni sud Republike Hrvatske',
            'jurisdiction' => 'HR',
            'case_number' => 'Kž-123/2024',
            'title' => 'Test decision',
        ]);

        // Create a single chunk document
        $doc = CourtDecisionDocument::factory()->create([
            'decision_id' => $decision->id,
            'doc_id' => 'dec-doc-'.(string) Str::ulid(),
            'chunk_index' => 0,
            'content' => 'Odluka se temelji na NN 152/08 i načelu proporcionalnosti.',
            'metadata' => json_encode(['court' => $decision->court, 'jurisdiction' => $decision->jurisdiction]),
            'embedding_vector' => array_fill(0, 1536, 0.0),
            'content_hash' => hash('sha256', 'Odluka se temelji na NN 152/08 i načelu proporcionalnosti.'),
            'embedding_provider' => 'manual',
            'embedding_model' => 'none',
            'embedding_dimensions' => 1536,
        ]);

        $this->createdNodeIds[] = (string) $doc->id;

        $courtId = 'court_'.md5($decision->court);
        $jurisdictionId = 'jurisdiction_'.$decision->jurisdiction;
        $this->createdCourtIds[] = $courtId;
        $this->createdJurisdictionIds[] = $jurisdictionId;

        $orchestrator = app(GraphRagOrchestrator::class);
        $orchestrator->syncCourtDecision((string) $decision->id);

        $this->assertGraphHasNode('CourtDecisionDocument', (string) $doc->id);
        $this->assertGraphHasNode('Court', $courtId);
        $this->assertGraphHasNode('Jurisdiction', $jurisdictionId);

        $this->assertGraphHasRelationship('CourtDecisionDocument', (string) $doc->id, 'DECIDED_BY', 'Court', $courtId);
        $this->assertGraphHasRelationship('CourtDecisionDocument', (string) $doc->id, 'BELONGS_TO_JURISDICTION', 'Jurisdiction', $jurisdictionId);

        // Sanity: should have keywords
        $kwCount = $this->graph->run(
            'MATCH (d:CourtDecisionDocument {id: $id})-[:HAS_KEYWORD]->(k:Keyword) RETURN count(k) as c',
            ['id' => (string) $doc->id]
        );
        $this->assertGreaterThan(0, (int) $kwCount->first()->get('c'));
    }

    /**
     * Invariant: Textract job sync must create TextractDocument graph nodes for completed chunks.
     *
     * Why:
     * - OCR is a core workflow; without TextractDocument nodes, graph reasoning/search becomes incomplete.
     * - GraphRagOrchestrator::syncTextractJob explicitly upserts TextractDocument nodes and links keywords.
     */
    public function test_sync_textract_job_creates_textract_document_nodes_and_keywords(): void
    {
        // Create a minimal textract_jobs row (id is bigint)
        $jobId = DB::table('textract_jobs')->insertGetId([
            'drive_file_id' => 'drive-'.Str::random(8),
            'drive_file_name' => 'test.pdf',
            's3_key' => null,
            'job_id' => null,
            'status' => 'succeeded',
            'error' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create a completed textract_documents chunk
        $docId = DB::table('textract_documents')->insertGetId([
            'textract_job_id' => $jobId,
            'case_id' => 'case-'.Str::random(8),
            'content' => 'Kazneni postupak i presuda u skladu sa zakonom.',
            'chunk_index' => 0,
            'chunk_overlap' => 0,
            'embedding' => json_encode(array_fill(0, 1536, 0.0)),
            'embedding_provider' => 'manual',
            'embedding_model' => 'none',
            'embedding_dimensions' => 1536,
            'token_count' => 10,
            'processing_status' => 'completed',
            'processing_error' => null,
            'embedded_at' => now(),
            'metadata' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->createdNodeIds[] = (string) $docId;

        $orchestrator = app(GraphRagOrchestrator::class);
        $orchestrator->syncTextractJob((int) $jobId);

        $this->assertGraphHasNode('TextractDocument', (string) $docId);

        $kwCount = $this->graph->run(
            'MATCH (d:TextractDocument {id: $id})-[:HAS_KEYWORD]->(k:Keyword) RETURN count(k) as c',
            ['id' => (string) $docId]
        );
        $this->assertGreaterThan(0, (int) $kwCount->first()->get('c'));
    }
}
