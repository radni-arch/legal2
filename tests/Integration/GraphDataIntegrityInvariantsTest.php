<?php

namespace Tests\Integration;

use App\Services\Graph\CaseGraphSyncService;
use App\Services\Graph\GraphRagOrchestrator;
use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * GraphDataIntegrityInvariantsTest
 *
 * Purpose:
 *  - Prove (by execution) that the graph layer (Neo4j) stays consistent with Postgres.
 *  - Encode invariants around "if DB says synced/exists, graph must have the node/edges".
 *
 * Notes:
 *  - These tests are conditional: they SKIP if Neo4j is not available.
 *  - We intentionally stub Tagging/Linkers to avoid LLM/external dependencies.
 */
class GraphDataIntegrityInvariantsTest extends TestCase
{
    use UsesTestDatabase;

    protected GraphDatabaseService $graph;

    /** @var array<string> */
    protected array $graphNodeIdsToCleanup = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure Neo4j sync is enabled for these tests.
        Config::set('neo4j.sync.enabled', true);

        $this->graph = app(GraphDatabaseService::class);

        if (! $this->graph->isAvailable()) {
            $this->markTestSkipped('Neo4j is not available');
        }
    }

    protected function tearDown(): void
    {
        // Best-effort cleanup
        try {
            if (isset($this->graph) && $this->graph->isAvailable()) {
                if (! empty($this->graphNodeIdsToCleanup)) {
                    $this->graph->run('MATCH (n) WHERE n.id IN $ids DETACH DELETE n', [
                        'ids' => array_values(array_unique($this->graphNodeIdsToCleanup)),
                    ]);
                }

                // Remove orphaned Keyword nodes created by keyword extraction
                $this->graph->run('MATCH (k:Keyword) WHERE NOT (k)<-[:HAS_KEYWORD]-() DELETE k');

                // Remove orphaned Jurisdiction/Court nodes created by these tests
                $this->graph->run('MATCH (j:Jurisdiction) WHERE NOT (j)<-[]-() DELETE j');
                $this->graph->run('MATCH (c:Court) WHERE NOT (c)<-[]-() DELETE c');
            }
        } catch (\Throwable) {
            // ignore
        }

        Mockery::close();
        parent::tearDown();
    }

    private function addCleanupId(string $id): void
    {
        $this->graphNodeIdsToCleanup[] = $id;
    }

    private function getOrchestrator(): GraphRagOrchestrator
    {
        // Fake TaggingService: avoid any external/LLM behavior.
        // We don't type-hint the class here to keep it decoupled.
        $tagging = new class
        {
            public function autoTag(string $nodeLabel, string $nodeId, string $content, array $metadata = []): void
            {
                // no-op
            }

            public function initializeTagHierarchy(): void
            {
                // no-op
            }

            public function getNodeTags(string $nodeLabel, string $nodeId): array
            {
                return [];
            }
        };

        // Stub linkers used by CaseGraphSyncService
        $keyword = Mockery::mock(\App\Services\Graph\GraphKeywordLinker::class);
        $keyword->shouldReceive('link')->andReturnNull();
        $keyword->shouldReceive('linkSimilar')->andReturn(0);

        $citation = Mockery::mock(\App\Services\Graph\GraphCitationLinker::class);
        $citation->shouldReceive('link')->andReturnNull();

        $similarity = Mockery::mock(\App\Services\Graph\GraphSimilarityLinker::class);
        $similarity->shouldReceive('link')->andReturnNull();
        $similarity->shouldReceive('linkSimilar')->andReturn(0);

        $caseSync = new CaseGraphSyncService(
            graph: $this->graph,
            keywordLinker: $keyword,
            citationLinker: $citation,
            similarityLinker: $similarity,
            tagging: $tagging
        );

        // Orchestrator: we pass null citation/similarity linkers to avoid additional relationships.
        return new GraphRagOrchestrator(
            graph: $this->graph,
            tagging: $tagging,
            caseSync: $caseSync,
            textractSync: null,
            advancedExtractor: null,
            citationLinker: null,
            similarityLinker: null
        );
    }

    private function ensureTable(string $table): void
    {
        if (! Schema::hasTable($table)) {
            $this->markTestSkipped("Missing table: {$table}");
        }
    }

    /**
     * Invariant: Graph schema must contain essential constraints.
     *
     * Reasoning:
     * - Without uniqueness constraints, your graph sync is not idempotent and can silently duplicate nodes.
     * - GraphDatabaseService::initializeSchema declares these constraints explicitly.
     */
    public function test_neo4j_schema_has_essential_constraints(): void
    {
        $this->graph->initializeSchema();

        $constraints = $this->graph->run('SHOW CONSTRAINTS');
        $names = [];
        foreach ($constraints as $record) {
            // Neo4j returns column 'name'
            $names[] = $record->get('name');
        }

        foreach ([
            'law_id',
            'law_doc_id',
            'case_id',
            'case_doc_id',
            'court_decision_doc_id',
            'keyword_name',
            'tag_name',
            'jurisdiction_name',
            'court_name',
        ] as $required) {
            $this->assertContains($required, $names, "Missing essential Neo4j constraint: {$required}");
        }
    }

    /**
     * Invariant: Each synced law chunk must produce a LawDocument node, and if jurisdiction exists,
     * it must be connected via BELONGS_TO_JURISDICTION.
     *
     * Reasoning:
     * - LawGraph sync explicitly creates LawDocument nodes and Jurisdiction edges.
     */
    public function test_law_sync_creates_lawdocument_and_jurisdiction_relationship(): void
    {
        $this->ensureTable('laws');

        $lawId = (string) Str::ulid();
        $this->addCleanupId($lawId);

        $jur = 'HR';
        $jurNodeId = 'jurisdiction_'.$jur;
        $this->addCleanupId($jurNodeId);

        $content = 'Test law content for invariants. NN 152/08 članak 1.';

        $payload = [
            'id' => $lawId,
            'doc_id' => 'inv-law-'.Str::random(8),
            'title' => 'Invariant Law',
            'law_number' => 'NN 152/08',
            'jurisdiction' => $jur,
            'country' => 'HR',
            'language' => 'hr',
            'chunk_index' => 0,
            'content' => $content,
            'content_hash' => hash('sha256', $content),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Insert only columns that exist.
        $insert = [];
        foreach ($payload as $k => $v) {
            if (Schema::hasColumn('laws', $k)) {
                $insert[$k] = $v;
            }
        }
        DB::table('laws')->insert($insert);

        $orchestrator = $this->getOrchestrator();
        $orchestrator->syncLaw($lawId); // creates LawDocument + jurisdiction edge per implementation

        $node = $this->graph->run('MATCH (l:LawDocument {id: $id}) RETURN l', ['id' => $lawId]);
        $this->assertGreaterThan(0, count($node), 'Expected LawDocument node to exist after syncLaw');

        $relCount = $this->graph->run(
            'MATCH (l:LawDocument {id: $id})-[:BELONGS_TO_JURISDICTION]->(j:Jurisdiction {name: $jur}) RETURN count(j) as c',
            ['id' => $lawId, 'jur' => $jur]
        );
        $this->assertEquals(1, $relCount[0]->get('c'), 'LawDocument must link to its Jurisdiction');
    }

    /**
     * Invariant: Each case document chunk must produce a CaseDocument node in Neo4j with key props.
     *
     * Reasoning:
     * - CaseGraphSyncService creates CaseDocument nodes and relies on these props for downstream retrieval.
     */
    public function test_case_document_sync_creates_casedocument_node(): void
    {
        $casesDocs = config('vizra-adk.tables.cases_documents', 'cases_documents');
        $this->ensureTable($casesDocs);

        $docId = (string) Str::ulid();
        $this->addCleanupId($docId);

        $content = 'Invariant case document content about proportionality and home search.';

        $payload = [
            'id' => $docId,
            'case_id' => null,
            'doc_id' => 'inv-case-doc-'.Str::random(8),
            'title' => 'Invariant Case Doc',
            'category' => 'note',
            'language' => 'hr',
            'chunk_index' => 0,
            'content' => $content,
            'content_hash' => hash('sha256', $content),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $insert = [];
        foreach ($payload as $k => $v) {
            if (Schema::hasColumn($casesDocs, $k)) {
                $insert[$k] = $v;
            }
        }

        DB::table($casesDocs)->insert($insert);

        $orchestrator = $this->getOrchestrator();
        $orchestrator->syncCase($docId);

        $node = $this->graph->run('MATCH (c:CaseDocument {id: $id}) RETURN c', ['id' => $docId]);
        $this->assertGreaterThan(0, count($node), 'Expected CaseDocument node to exist after syncCase');

        // Ensure required properties are present
        $props = $node[0]->get('c')->getProperties();
        $this->assertSame($payload['content_hash'], $props['content_hash'] ?? null, 'CaseDocument.content_hash must match DB');
        $this->assertSame(0, (int) ($props['chunk_index'] ?? -1), 'CaseDocument.chunk_index must match DB');
    }

    /**
     * Invariant: Each court decision chunk must produce a CourtDecisionDocument node
     * and link to Court and Jurisdiction nodes when present.
     */
    public function test_decision_sync_creates_decision_nodes_and_edges(): void
    {
        $decisionsTable = config('vizra-adk.tables.court_decisions', 'court_decisions');
        $docsTable = config('vizra-adk.tables.court_decision_documents', 'court_decision_documents');
        $this->ensureTable($decisionsTable);
        $this->ensureTable($docsTable);

        $decisionId = (string) Str::ulid();
        $docChunkId = (string) Str::ulid();
        $this->addCleanupId($docChunkId);

        $courtName = 'Invariant Court';
        $courtNodeId = 'court_'.md5($courtName);
        $this->addCleanupId($courtNodeId);

        $jur = 'HR';
        $jurNodeId = 'jurisdiction_'.$jur;
        $this->addCleanupId($jurNodeId);

        DB::table($decisionsTable)->insert([
            'id' => $decisionId,
            'case_number' => 'K-INV/'.rand(1, 9999),
            'title' => 'Invariant Decision',
            'court' => $courtName,
            'jurisdiction' => $jur,
            'decision_date' => now()->toDateString(),
            'decision_type' => 'presuda',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $content = 'Odluka se poziva na NN 152/08 članak 1.';
        $hash = hash('sha256', $content);

        // Build row respecting pgvector vs JSON columns
        $docRow = [
            'id' => $docChunkId,
            'decision_id' => $decisionId,
            'doc_id' => 'inv-decision-doc-'.Str::random(8),
            'chunk_index' => 0,
            'content' => $content,
            'metadata' => null,
            'embedding_provider' => 'manual',
            'embedding_model' => 'none',
            'embedding_dimensions' => 1536,
            'content_hash' => $hash,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn($docsTable, 'embedding')) {
            // pgvector - accept vector literal
            $docRow['embedding'] = DB::raw("'[".implode(',', array_fill(0, 1536, 0.0))."]'::vector");
        } elseif (Schema::hasColumn($docsTable, 'embedding_vector')) {
            $docRow['embedding_vector'] = json_encode(array_fill(0, 1536, 0.0));
        }

        // Insert only columns that exist
        $insert = [];
        foreach ($docRow as $k => $v) {
            if (Schema::hasColumn($docsTable, $k)) {
                $insert[$k] = $v;
            }
        }
        DB::table($docsTable)->insert($insert);

        $orchestrator = $this->getOrchestrator();
        $orchestrator->syncCourtDecision($decisionId);

        $node = $this->graph->run('MATCH (d:CourtDecisionDocument {id: $id}) RETURN d', ['id' => $docChunkId]);
        $this->assertGreaterThan(0, count($node), 'Expected CourtDecisionDocument node to exist after syncCourtDecision');

        $courtRel = $this->graph->run(
            'MATCH (d:CourtDecisionDocument {id: $id})-[:DECIDED_BY]->(c:Court {name: $court}) RETURN count(c) as c',
            ['id' => $docChunkId, 'court' => $courtName]
        );
        $this->assertEquals(1, $courtRel[0]->get('c'), 'Decision document must link to Court');

        $jurRel = $this->graph->run(
            'MATCH (d:CourtDecisionDocument {id: $id})-[:BELONGS_TO_JURISDICTION]->(j:Jurisdiction {name: $jur}) RETURN count(j) as c',
            ['id' => $docChunkId, 'jur' => $jur]
        );
        $this->assertEquals(1, $jurRel[0]->get('c'), 'Decision document must link to Jurisdiction');
    }

    /**
     * Invariant: TextractJob sync must create TextractDocument nodes for completed chunks,
     * and mark graph_sync_status as synced with timestamp.
     *
     * Reasoning:
     * - GraphRagOrchestrator::syncTextractJob explicitly sets graph_sync_status and graph_synced_at
     *   and creates BELONGS_TO relationships to CaseDocument when a matching case document exists.
     */
    public function test_textract_job_sync_creates_textractdocument_nodes_and_updates_job_status(): void
    {
        $this->ensureTable('textract_jobs');
        $this->ensureTable('textract_documents');

        $casesTable = config('vizra-adk.tables.cases', 'cases');
        $casesDocs = config('vizra-adk.tables.cases_documents', 'cases_documents');
        $this->ensureTable($casesTable);
        $this->ensureTable($casesDocs);

        // Create a Case + a CaseDocument row to allow BELONGS_TO relationship creation
        $caseId = (string) Str::ulid();
        DB::table($casesTable)->insert([
            'id' => $caseId,
            'case_number' => 'INV-CASE-'.rand(1, 9999),
            'title' => 'Invariant Case',
            'court' => 'Invariant Court',
            'jurisdiction' => 'HR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $caseDocId = (string) Str::ulid();
        $this->addCleanupId($caseDocId);

        $caseDocContent = 'Invariant case doc content used for BELONGS_TO linking.';
        DB::table($casesDocs)->insert([
            'id' => $caseDocId,
            'case_id' => $caseId,
            'doc_id' => 'inv-case-doc-'.Str::random(8),
            'chunk_index' => 0,
            'content' => $caseDocContent,
            'content_hash' => hash('sha256', $caseDocContent),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create textract job
        $jobRow = [
            'drive_file_id' => 'inv-drive-'.Str::random(8),
            'drive_file_name' => 'inv.pdf',
            's3_key' => null,
            'job_id' => null,
            'status' => 'succeeded',
            'error' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('textract_jobs', 'case_id')) {
            $jobRow['case_id'] = $caseId;
        }
        if (Schema::hasColumn('textract_jobs', 'embedding_status')) {
            $jobRow['embedding_status'] = 'synced';
        }
        if (Schema::hasColumn('textract_jobs', 'graph_sync_status')) {
            $jobRow['graph_sync_status'] = 'pending';
        }

        $textractJobId = (int) DB::table('textract_jobs')->insertGetId($jobRow);

        // Create one completed textract document chunk
        $texDocContent = 'Textract chunk content for invariants.';
        $textractDocId = DB::table('textract_documents')->insertGetId([
            'textract_job_id' => $textractJobId,
            'case_id' => $caseId,
            'content' => $texDocContent,
            'chunk_index' => 0,
            'chunk_overlap' => 0,
            'embedding' => json_encode(array_fill(0, 3, 0.0)), // JSON, dimensions nullable
            'embedding_dimensions' => 3,
            'processing_status' => 'completed',
            'embedded_at' => now(),
            'metadata' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->addCleanupId((string) $textractDocId);

        $orchestrator = $this->getOrchestrator();
        $orchestrator->syncTextractJob($textractJobId);

        // Graph node should exist
        $node = $this->graph->run('MATCH (t:TextractDocument {id: $id}) RETURN t', ['id' => (string) $textractDocId]);
        $this->assertGreaterThan(0, count($node), 'Expected TextractDocument node to exist after syncTextractJob');

        // Relationship to CaseDocument should exist
        $rel = $this->graph->run(
            'MATCH (t:TextractDocument {id: $tId})-[:BELONGS_TO]->(c:CaseDocument {id: $cId}) RETURN count(*) as c',
            ['tId' => (string) $textractDocId, 'cId' => $caseDocId]
        );
        $this->assertEquals(1, $rel[0]->get('c'), 'TextractDocument must BELONGS_TO a CaseDocument when case_id is present');

        // textract_jobs status must be marked synced with timestamp
        if (Schema::hasColumn('textract_jobs', 'graph_sync_status')) {
            $job = DB::table('textract_jobs')->where('id', $textractJobId)->first();
            $this->assertSame('synced', $job->graph_sync_status);

            if (Schema::hasColumn('textract_jobs', 'graph_synced_at')) {
                $this->assertNotNull($job->graph_synced_at, 'graph_sync_status=synced must have graph_synced_at');
            }
        }
    }
}
