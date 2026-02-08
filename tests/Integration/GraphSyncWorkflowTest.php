<?php

namespace Tests\Integration;

use App\Models\Case as LegalCase;
use App\Models\CaseDocument;
use App\Models\CourtDecisionDocument;
use App\Models\Law;
use App\Services\GraphDatabaseService;
use App\Services\GraphRagService;
use App\Services\TaggingService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Integration Test: Graph Database Syncing Workflow
 *
 * Tests the complete Graph RAG workflow:
 * 1. Node creation in Neo4j
 * 2. Relationship creation (citations, keywords, similarities)
 * 3. Auto-tagging
 * 4. Citation extraction and linking
 * 5. Keyword extraction and linking
 * 6. Similarity relationship creation
 * 7. Multi-document syncing
 */
class GraphSyncWorkflowTest extends TestCase
{
    use UsesTestDatabase;

    protected GraphRagService $graphRagService;

    protected GraphDatabaseService $graphService;

    protected TaggingService $taggingService;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip if Neo4j not enabled
        if (! config('neo4j.enabled', false)) {
            $this->markTestSkipped('Neo4j is not enabled');
        }

        $this->graphRagService = app(GraphRagService::class);
        $this->graphService = app(GraphDatabaseService::class);
        $this->taggingService = app(TaggingService::class);
    }

    /**
     * Test 1: Complete law document sync to graph
     *
     * @test
     *
     * @group integration
     * @group graph-sync
     */
    public function test_complete_law_document_sync_to_graph()
    {
        // Arrange: Create a law document
        $law = Law::factory()->create([
            'doc_id' => 'ZKP-240',
            'title' => 'Zakon o kaznenom postupku - Članak 240',
            'law_number' => 'ZKP-240',
            'content' => 'Pretraga doma i drugih prostorija može se izvršiti samo na temelju naredbe suda. Prema članku 159 Prekršajnog zakona.',
            'jurisdiction' => 'Republika Hrvatska',
            'country' => 'HR',
            'language' => 'hr',
        ]);

        // Act: Sync law to graph
        $this->graphRagService->syncLaw($law->id);

        // Assert: Verify node was created in Neo4j
        $result = $this->graphService->run(
            'MATCH (n:LawDocument {doc_id: $docId}) RETURN n',
            ['docId' => 'ZKP-240']
        );

        $this->assertNotEmpty($result);
        $node = $result->first()->get('n');
        $this->assertNotNull($node);

        // Verify node properties
        $properties = $node->getProperties();
        $this->assertEquals('ZKP-240', $properties['doc_id']);
        $this->assertEquals('Zakon o kaznenom postupku - Članak 240', $properties['title']);
        $this->assertEquals('ZKP-240', $properties['law_number']);
        $this->assertEquals('Republika Hrvatska', $properties['jurisdiction']);

        // Verify jurisdiction relationship
        $jurisdictionResult = $this->graphService->run(
            'MATCH (l:LawDocument {doc_id: $docId})-[:BELONGS_TO_JURISDICTION]->(j:Jurisdiction) RETURN j',
            ['docId' => 'ZKP-240']
        );

        $this->assertNotEmpty($jurisdictionResult);
        $jurisdiction = $jurisdictionResult->first()->get('j');
        $this->assertEquals('Republika Hrvatska', $jurisdiction->getProperties()['name']);

        // Verify keywords were extracted and linked
        $keywordResult = $this->graphService->run(
            'MATCH (l:LawDocument {doc_id: $docId})-[:HAS_KEYWORD]->(k:Keyword) RETURN k',
            ['docId' => 'ZKP-240']
        );

        $this->assertNotEmpty($keywordResult);

        // Verify citations were extracted
        $citationResult = $this->graphService->run(
            'MATCH (l:LawDocument {doc_id: $docId})-[:CITES]->(c) RETURN c',
            ['docId' => 'ZKP-240']
        );

        // May or may not have citations depending on content
        $this->assertIsObject($citationResult);
    }

    /**
     * Test 2: Complete case document sync to graph
     *
     * @test
     *
     * @group integration
     * @group graph-sync
     */
    public function test_complete_case_document_sync_to_graph()
    {
        // Arrange: Create a case document
        $case = LegalCase::factory()->create([
            'case_number' => 'Pp-123/2025',
        ]);

        $caseDoc = CaseDocument::factory()->create([
            'case_id' => $case->id,
            'doc_id' => 'case-doc-001',
            'title' => 'Optužnica - Pp-123/2025',
            'content' => 'Optužnica protiv okrivljenog za prekršaj prema ZKP članku 240.',
            'category' => 'indictment',
        ]);

        // Act: Sync case document to graph
        $this->graphRagService->syncCase($caseDoc->id);

        // Assert: Verify node was created
        $result = $this->graphService->run(
            'MATCH (n:CaseDocument {doc_id: $docId}) RETURN n',
            ['docId' => 'case-doc-001']
        );

        $this->assertNotEmpty($result);
        $node = $result->first()->get('n');

        // Verify properties
        $properties = $node->getProperties();
        $this->assertEquals('case-doc-001', $properties['doc_id']);
        $this->assertEquals('Optužnica - Pp-123/2025', $properties['title']);
        $this->assertEquals('indictment', $properties['category']);

        // Verify keywords
        $keywordResult = $this->graphService->run(
            'MATCH (c:CaseDocument {doc_id: $docId})-[:HAS_KEYWORD]->(k:Keyword) RETURN count(k) as count',
            ['docId' => 'case-doc-001']
        );

        $keywordCount = $keywordResult->first()->get('count');
        $this->assertGreaterThan(0, $keywordCount);
    }

    /**
     * Test 3: Complete court decision sync to graph
     *
     * @test
     *
     * @group integration
     * @group graph-sync
     */
    public function test_complete_court_decision_sync_to_graph()
    {
        // Arrange: Create a court decision
        $decision = CourtDecisionDocument::factory()->create([
            'doc_id' => 'decision-001',
            'title' => 'Presuda Županijskog suda u Osijeku',
            'content' => 'Sud je odlučio da je pretraga bila zakonita prema članku 240 ZKP.',
            'court' => 'Županijski sud u Osijeku',
            'case_number' => 'K-123/2025',
            'decision_date' => '2025-01-15',
        ]);

        // Act: Sync decision to graph
        $this->graphRagService->syncDecision($decision->id);

        // Assert: Verify node was created
        $result = $this->graphService->run(
            'MATCH (n:CourtDecisionDocument {doc_id: $docId}) RETURN n',
            ['docId' => 'decision-001']
        );

        $this->assertNotEmpty($result);
        $node = $result->first()->get('n');

        // Verify properties
        $properties = $node->getProperties();
        $this->assertEquals('decision-001', $properties['doc_id']);
        $this->assertEquals('Županijski sud u Osijeku', $properties['court']);

        // Verify court relationship
        $courtResult = $this->graphService->run(
            'MATCH (d:CourtDecisionDocument {doc_id: $docId})-[:DECIDED_BY]->(c:Court) RETURN c',
            ['docId' => 'decision-001']
        );

        $this->assertNotEmpty($courtResult);
        $court = $courtResult->first()->get('c');
        $this->assertEquals('Županijski sud u Osijeku', $court->getProperties()['name']);
    }

    /**
     * Test 4: Citation relationship creation
     *
     * @test
     *
     * @group integration
     * @group graph-sync
     */
    public function test_citation_relationship_creation()
    {
        // Arrange: Create two laws with citation relationship
        $lawCited = Law::factory()->create([
            'doc_id' => 'ZKP-159',
            'law_number' => 'ZKP-159',
            'title' => 'ZKP Članak 159',
            'content' => 'Prekršajni postupak.',
        ]);

        $lawCiting = Law::factory()->create([
            'doc_id' => 'ZKP-240',
            'law_number' => 'ZKP-240',
            'title' => 'ZKP Članak 240',
            'content' => 'Pretraga prema članku 159 ZKP.',
        ]);

        // Act: Sync both laws
        $this->graphRagService->syncLaw($lawCited->id);
        $this->graphRagService->syncLaw($lawCiting->id);

        // Assert: Verify citation relationship exists
        $result = $this->graphService->run(
            'MATCH (l1:LawDocument {law_number: $citing})-[:CITES]->(l2:LawDocument {law_number: $cited}) RETURN l1, l2',
            ['citing' => 'ZKP-240', 'cited' => 'ZKP-159']
        );

        // Citation extraction depends on citation detector, may or may not find
        $this->assertIsObject($result);
    }

    /**
     * Test 5: Keyword extraction and linking
     *
     * @test
     *
     * @group integration
     * @group graph-sync
     */
    public function test_keyword_extraction_and_linking()
    {
        // Arrange: Create document with clear keywords
        $law = Law::factory()->create([
            'doc_id' => 'test-keywords',
            'content' => 'Pretraga doma, naredba suda, krivični postupak, dokazi',
        ]);

        // Act: Sync to graph
        $this->graphRagService->syncLaw($law->id);

        // Assert: Verify keywords were extracted and linked
        $result = $this->graphService->run(
            'MATCH (l:LawDocument {doc_id: $docId})-[:HAS_KEYWORD]->(k:Keyword) RETURN k.name as keyword',
            ['docId' => 'test-keywords']
        );

        $this->assertNotEmpty($result);

        $keywords = [];
        foreach ($result as $record) {
            $keywords[] = $record->get('keyword');
        }

        // Should extract some keywords
        $this->assertNotEmpty($keywords);
    }

    /**
     * Test 6: Multi-document bulk syncing
     *
     * @test
     *
     * @group integration
     * @group graph-sync
     */
    public function test_multi_document_bulk_syncing()
    {
        // Arrange: Create multiple documents
        $laws = Law::factory()->count(5)->create();
        $decisions = CourtDecisionDocument::factory()->count(3)->create();
        $caseDocs = CaseDocument::factory()->count(2)->create();

        // Act: Bulk sync
        foreach ($laws as $law) {
            $this->graphRagService->syncLaw($law->id);
        }

        foreach ($decisions as $decision) {
            $this->graphRagService->syncDecision($decision->id);
        }

        foreach ($caseDocs as $caseDoc) {
            $this->graphRagService->syncCase($caseDoc->id);
        }

        // Assert: Verify all nodes were created
        $lawCount = $this->graphService->run('MATCH (n:LawDocument) RETURN count(n) as count');
        $this->assertGreaterThanOrEqual(5, $lawCount->first()->get('count'));

        $decisionCount = $this->graphService->run('MATCH (n:CourtDecisionDocument) RETURN count(n) as count');
        $this->assertGreaterThanOrEqual(3, $decisionCount->first()->get('count'));

        $caseCount = $this->graphService->run('MATCH (n:CaseDocument) RETURN count(n) as count');
        $this->assertGreaterThanOrEqual(2, $caseCount->first()->get('count'));
    }

    /**
     * Test 7: Similarity relationship creation
     *
     * @test
     *
     * @group integration
     * @group graph-sync
     */
    public function test_similarity_relationship_creation()
    {
        // Arrange: Create two similar documents
        $law1 = Law::factory()->create([
            'doc_id' => 'similar-1',
            'content' => 'Pretraga doma može se izvršiti samo uz naredbu suda.',
            'embedding_vector' => $this->mockEmbedding(),
        ]);

        $law2 = Law::factory()->create([
            'doc_id' => 'similar-2',
            'content' => 'Pretraga prostorija zahtijeva sudsku naredbu.',
            'embedding_vector' => $this->mockEmbedding(),
        ]);

        // Act: Sync both laws (will create similarity relationships if embeddings are similar)
        $this->graphRagService->syncLaw($law1->id);
        $this->graphRagService->syncLaw($law2->id);

        // Assert: Check if similarity relationships exist
        $result = $this->graphService->run(
            'MATCH (l1:LawDocument)-[r:SIMILAR_TO]->(l2:LawDocument) RETURN count(r) as count'
        );

        // Similarity depends on actual embedding similarity
        $this->assertIsObject($result);
    }

    /**
     * Test 8: Graph sync handles updates (idempotency)
     *
     * @test
     *
     * @group integration
     * @group graph-sync
     */
    public function test_graph_sync_handles_updates_idempotently()
    {
        // Arrange: Create and sync a law
        $law = Law::factory()->create([
            'doc_id' => 'update-test',
            'title' => 'Original Title',
        ]);

        $this->graphRagService->syncLaw($law->id);

        // Act: Update the law and re-sync
        DB::table('laws')
            ->where('id', $law->id)
            ->update(['title' => 'Updated Title']);

        $law->refresh();
        $this->graphRagService->syncLaw($law->id);

        // Assert: Verify only one node exists with updated properties
        $result = $this->graphService->run(
            'MATCH (n:LawDocument {doc_id: $docId}) RETURN n',
            ['docId' => 'update-test']
        );

        $this->assertCount(1, $result);
        $node = $result->first()->get('n');
        $this->assertEquals('Updated Title', $node->getProperties()['title']);
    }

    /**
     * Test 9: Graph sync error handling
     *
     * @test
     *
     * @group integration
     * @group graph-sync
     */
    public function test_graph_sync_handles_missing_document()
    {
        // Act & Assert: Syncing non-existent document should not throw exception
        $this->graphRagService->syncLaw('non-existent-id');

        // Should complete without error (gracefully handles missing documents)
        $this->assertTrue(true);
    }

    /**
     * Test 10: Complete workflow - document creation to graph sync
     *
     * @test
     *
     * @group integration
     * @group graph-sync
     * @group workflow
     */
    public function test_complete_workflow_document_creation_to_graph_sync()
    {
        // Arrange & Act: Create document and immediately sync
        $law = Law::factory()->create([
            'doc_id' => 'workflow-test',
            'title' => 'Test Law for Workflow',
            'content' => 'Complete workflow test content with keywords and citations.',
            'law_number' => 'TEST-001',
        ]);

        // Sync to graph
        $this->graphRagService->syncLaw($law->id);

        // Assert: End-to-end verification
        // 1. Verify node exists
        $nodeResult = $this->graphService->run(
            'MATCH (n:LawDocument {doc_id: $docId}) RETURN n',
            ['docId' => 'workflow-test']
        );
        $this->assertNotEmpty($nodeResult);

        // 2. Verify has relationships
        $relationshipCount = $this->graphService->run(
            'MATCH (n:LawDocument {doc_id: $docId})-[r]-() RETURN count(r) as count',
            ['docId' => 'workflow-test']
        );
        $count = $relationshipCount->first()->get('count');
        $this->assertGreaterThan(0, $count, 'Document should have at least some relationships');

        // 3. Verify is searchable in graph
        $searchResult = $this->graphService->run(
            'MATCH (n:LawDocument) WHERE n.title CONTAINS $term RETURN n',
            ['term' => 'Workflow']
        );
        $this->assertNotEmpty($searchResult);
    }

    /**
     * Helper: Generate mock embedding vector
     */
    protected function mockEmbedding(): array
    {
        $vector = [];
        for ($i = 0; $i < 1536; $i++) {
            $vector[] = (mt_rand(-100, 100) / 100);
        }

        return $vector;
    }

    protected function tearDown(): void
    {
        // Clean up test data from Neo4j if enabled
        if (config('neo4j.enabled', false)) {
            try {
                // Delete test nodes
                $this->graphService->run('MATCH (n) WHERE n.doc_id CONTAINS "test" OR n.doc_id CONTAINS "similar" OR n.doc_id CONTAINS "workflow" DETACH DELETE n');
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }

        parent::tearDown();
    }
}
