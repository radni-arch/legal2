<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Graph\GraphResearchEnhancer;
use App\Services\Graph\LawGraphSyncService;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Tests for GraphResearchEnhancer (Sprint 4.3)
 *
 * Tests verify graph-enhanced research capabilities for ResearchSpecialistAgent:
 * - Finding decisions that cite discovered laws
 * - Citation chain traversal (2-3 hops)
 * - Combining vector search with graph traversal
 * - Comparing vector vs. graph precision/recall
 *
 * Acceptance Criteria:
 * ✅ After finding laws, also finds citing decisions
 * ✅ Citation chain traversal works (2-3 hops)
 * ✅ Graph results improve overall research quality
 */
class GraphResearchEnhancerTest extends TestCase
{
    use UsesTestDatabase;

    protected $lawGraphSyncMock;

    protected GraphResearchEnhancer $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lawGraphSyncMock = Mockery::mock(LawGraphSyncService::class);
        $this->service = new GraphResearchEnhancer($this->lawGraphSyncMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ========================================
    // Test 1: Find decisions citing discovered laws
    // ========================================

    /** @test */
    public function it_finds_decisions_citing_laws()
    {
        // Arrange: Create laws found via vector search
        $law1Id = $this->createLaw(['law_number' => 'NN 152/08', 'title' => 'ZKP']);
        $law2Id = $this->createLaw(['law_number' => 'NN 125/11', 'title' => 'KZ']);

        // Create decisions that cite these laws
        $decision1Id = $this->createDecision(['case_number' => 'K-123/2020']);
        $decision2Id = $this->createDecision(['case_number' => 'K-456/2019']);
        $decision3Id = $this->createDecision(['case_number' => 'K-789/2021']);

        // Mock graph query: find decisions citing these laws
        $this->lawGraphSyncMock->shouldReceive('query')
            ->once()
            ->with(Mockery::on(function ($cypher) {
                return strpos($cypher, 'CITES') !== false &&
                       strpos($cypher, 'law_ids') !== false;
            }), Mockery::on(function ($params) use ($law1Id, $law2Id) {
                return isset($params['law_ids']) &&
                       in_array($law1Id, $params['law_ids']) &&
                       in_array($law2Id, $params['law_ids']);
            }))
            ->andReturn([
                [
                    'decision_id' => $decision1Id,
                    'case_number' => 'K-123/2020',
                    'cited_law_id' => $law1Id,
                    'cited_law_number' => 'NN 152/08',
                ],
                [
                    'decision_id' => $decision2Id,
                    'case_number' => 'K-456/2019',
                    'cited_law_id' => $law1Id,
                    'cited_law_number' => 'NN 152/08',
                ],
                [
                    'decision_id' => $decision3Id,
                    'case_number' => 'K-789/2021',
                    'cited_law_id' => $law2Id,
                    'cited_law_number' => 'NN 125/11',
                ],
            ]);

        // Act: Find decisions citing these laws
        $citingDecisions = $this->service->findDecisionsCitingLaws([$law1Id, $law2Id]);

        // Assert: Returns decisions that cite the laws
        $this->assertCount(3, $citingDecisions);
        $this->assertEquals($decision1Id, $citingDecisions[0]['decision_id']);
        $this->assertEquals($decision2Id, $citingDecisions[1]['decision_id']);
        $this->assertEquals($decision3Id, $citingDecisions[2]['decision_id']);
    }

    /** @test */
    public function it_returns_empty_array_when_no_decisions_cite_laws()
    {
        $lawId = $this->createLaw(['law_number' => 'NN 999/99']);

        $this->lawGraphSyncMock->shouldReceive('query')
            ->once()
            ->andReturn([]);

        $citingDecisions = $this->service->findDecisionsCitingLaws([$lawId]);

        $this->assertIsArray($citingDecisions);
        $this->assertEmpty($citingDecisions);
    }

    // ========================================
    // Test 2: Citation chain traversal (2-3 hops)
    // ========================================

    /** @test */
    public function it_traverses_citation_chains_two_hops()
    {
        // Arrange: Create citation chain
        // Decision A cites Decision B
        // Decision B cites Decision C
        $decisionAId = $this->createDecision(['case_number' => 'K-A/2020']);
        $decisionBId = $this->createDecision(['case_number' => 'K-B/2019']);
        $decisionCId = $this->createDecision(['case_number' => 'K-C/2018']);

        // Mock graph query: traverse 2 hops
        $this->lawGraphSyncMock->shouldReceive('query')
            ->once()
            ->with(Mockery::on(function ($cypher) {
                return strpos($cypher, 'CITES*1..2') !== false;
            }), Mockery::on(function ($params) use ($decisionAId) {
                return $params['decision_id'] === $decisionAId;
            }))
            ->andReturn([
                [
                    'related_decision_id' => $decisionBId,
                    'case_number' => 'K-B/2019',
                    'hops' => 1,
                    'path_length' => 1,
                ],
                [
                    'related_decision_id' => $decisionCId,
                    'case_number' => 'K-C/2018',
                    'hops' => 2,
                    'path_length' => 2,
                ],
            ]);

        // Act: Traverse citation chain (2 hops)
        $relatedDecisions = $this->service->traverseCitationChain($decisionAId, 2);

        // Assert: Returns decisions within 2 hops
        $this->assertCount(2, $relatedDecisions);
        $this->assertEquals($decisionBId, $relatedDecisions[0]['related_decision_id']);
        $this->assertEquals(1, $relatedDecisions[0]['hops']);
        $this->assertEquals($decisionCId, $relatedDecisions[1]['related_decision_id']);
        $this->assertEquals(2, $relatedDecisions[1]['hops']);
    }

    /** @test */
    public function it_traverses_citation_chains_three_hops()
    {
        $decisionId = $this->createDecision(['case_number' => 'K-123/2020']);

        $this->lawGraphSyncMock->shouldReceive('query')
            ->once()
            ->with(Mockery::on(function ($cypher) {
                return strpos($cypher, 'CITES*1..3') !== false;
            }), Mockery::any())
            ->andReturn([
                ['related_decision_id' => 'dec-1', 'hops' => 1],
                ['related_decision_id' => 'dec-2', 'hops' => 2],
                ['related_decision_id' => 'dec-3', 'hops' => 3],
            ]);

        $relatedDecisions = $this->service->traverseCitationChain($decisionId, 3);

        $this->assertCount(3, $relatedDecisions);
        $this->assertEquals(3, $relatedDecisions[2]['hops']);
    }

    /** @test */
    public function it_limits_citation_chain_results()
    {
        $decisionId = $this->createDecision(['case_number' => 'K-123/2020']);

        // Mock: Many related decisions found
        $mockResults = array_map(fn ($i) => [
            'related_decision_id' => "dec-{$i}",
            'hops' => 1,
        ], range(1, 50));

        $this->lawGraphSyncMock->shouldReceive('query')
            ->once()
            ->andReturn($mockResults);

        // Act: Traverse with limit
        $relatedDecisions = $this->service->traverseCitationChain($decisionId, 2, $limit = 10);

        // Assert: Returns max 10 results
        $this->assertCount(10, $relatedDecisions);
    }

    // ========================================
    // Test 3: Combine vector search with graph results
    // ========================================

    /** @test */
    public function it_enhances_research_results_with_graph_traversal()
    {
        // Arrange: Vector search found laws and decisions
        $vectorLaws = [
            ['id' => $this->createLaw(['law_number' => 'NN 152/08']), 'score' => 0.95],
            ['id' => $this->createLaw(['law_number' => 'NN 125/11']), 'score' => 0.88],
        ];

        $vectorDecisions = [
            ['id' => $this->createDecision(['case_number' => 'K-100/2020']), 'score' => 0.92],
        ];

        // Mock: Graph finds additional decisions citing the laws
        $this->lawGraphSyncMock->shouldReceive('query')
            ->once()
            ->andReturn([
                ['decision_id' => $this->createDecision(['case_number' => 'K-200/2019'])],
                ['decision_id' => $this->createDecision(['case_number' => 'K-300/2018'])],
            ]);

        // Mock: Citation chain traversal finds more related decisions
        $this->lawGraphSyncMock->shouldReceive('query')
            ->once()
            ->andReturn([
                ['related_decision_id' => $this->createDecision(['case_number' => 'K-400/2017']), 'hops' => 1],
            ]);

        // Act: Enhance research results
        $enhanced = $this->service->enhanceResearchResults($vectorLaws, $vectorDecisions);

        // Assert: Returns combined results with graph data
        $this->assertArrayHasKey('vector_laws', $enhanced);
        $this->assertArrayHasKey('vector_decisions', $enhanced);
        $this->assertArrayHasKey('graph_decisions_citing_laws', $enhanced);
        $this->assertArrayHasKey('graph_related_decisions', $enhanced);
        $this->assertArrayHasKey('total_decisions', $enhanced);

        $this->assertCount(2, $enhanced['vector_laws']);
        $this->assertCount(1, $enhanced['vector_decisions']);
        $this->assertCount(2, $enhanced['graph_decisions_citing_laws']);
        $this->assertCount(1, $enhanced['graph_related_decisions']);
    }

    /** @test */
    public function it_removes_duplicates_when_combining_results()
    {
        // Arrange: Same decision found via both vector and graph
        $sharedDecisionId = $this->createDecision(['case_number' => 'K-SHARED/2020']);

        $vectorLaws = [
            ['id' => $this->createLaw(['law_number' => 'NN 152/08'])],
        ];

        $vectorDecisions = [
            ['id' => $sharedDecisionId, 'score' => 0.92],
        ];

        // Mock: Graph also finds the same decision
        $this->lawGraphSyncMock->shouldReceive('query')
            ->once()
            ->andReturn([
                ['decision_id' => $sharedDecisionId], // Duplicate!
                ['decision_id' => $this->createDecision(['case_number' => 'K-NEW/2019'])],
            ]);

        $this->lawGraphSyncMock->shouldReceive('query')
            ->once()
            ->andReturn([]);

        // Act
        $enhanced = $this->service->enhanceResearchResults($vectorLaws, $vectorDecisions);

        // Assert: Duplicate removed, total count correct
        $allDecisionIds = array_merge(
            array_column($enhanced['vector_decisions'], 'id'),
            array_column($enhanced['graph_decisions_citing_laws'], 'decision_id')
        );

        $this->assertCount(2, $allDecisionIds); // 1 shared + 1 new = 2 unique
        $this->assertEquals(2, $enhanced['total_decisions']);
    }

    // ========================================
    // Test 4: Compare vector vs. graph precision/recall
    // ========================================

    /** @test */
    public function it_calculates_graph_enhancement_metrics()
    {
        $vectorLaws = [
            ['id' => $this->createLaw(['law_number' => 'NN 152/08'])],
        ];

        $vectorDecisions = [
            ['id' => $this->createDecision(['case_number' => 'K-V1/2020'])],
            ['id' => $this->createDecision(['case_number' => 'K-V2/2020'])],
        ];

        // Mock: Graph finds 3 additional decisions citing laws
        $this->lawGraphSyncMock->shouldReceive('query')
            ->once()
            ->with(Mockery::on(fn ($cypher) => strpos($cypher, 'CITES') !== false && strpos($cypher, 'law_ids') !== false), Mockery::any())
            ->andReturn([
                ['decision_id' => $this->createDecision(['case_number' => 'K-G1/2019'])],
                ['decision_id' => $this->createDecision(['case_number' => 'K-G2/2019'])],
                ['decision_id' => $this->createDecision(['case_number' => 'K-G3/2019'])],
            ]);

        // Mock: Citation chain traversal for each vector decision (2 calls, both return empty)
        $this->lawGraphSyncMock->shouldReceive('query')
            ->twice()
            ->with(Mockery::on(fn ($cypher) => strpos($cypher, 'CITES*') !== false), Mockery::any())
            ->andReturn([]);

        // Act
        $enhanced = $this->service->enhanceResearchResults($vectorLaws, $vectorDecisions);

        // Assert: Metrics calculated
        $this->assertArrayHasKey('metrics', $enhanced);
        $this->assertEquals(2, $enhanced['metrics']['vector_decision_count']);
        $this->assertEquals(3, $enhanced['metrics']['graph_decision_count']);
        $this->assertEquals(5, $enhanced['metrics']['total_unique_decisions']);
        $this->assertEquals(150.0, $enhanced['metrics']['graph_enhancement_percentage']); // 3/2 * 100 = 150%
    }

    // ========================================
    // Helper Methods
    // ========================================

    protected function createLaw(array $overrides = []): string
    {
        $defaults = [
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'doc_id' => 'law-'.uniqid(),
            'title' => 'Test Law',
            'law_number' => 'NN 100/20',
            'jurisdiction' => 'Republika Hrvatska',
            'country' => 'HR',
            'language' => 'hr',
            'chunk_index' => 0,
            'content' => 'Test law content',
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-3-small',
            'embedding_dimensions' => 1536,
            'content_hash' => md5('Test law content'),
        ];

        $lawData = array_merge($defaults, $overrides);
        $lawId = $lawData['id'];
        unset($lawData['id']);

        DB::table('laws')->insert(array_merge(['id' => $lawId], $lawData));

        return $lawId;
    }

    protected function createDecision(array $overrides = []): string
    {
        $defaults = [
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'case_number' => 'K-'.rand(100, 999).'/2020',
            'court' => 'Županijski sud u Osijeku',
            'decision_date' => '2020-01-01',
            'summary' => 'Test decision summary',
            'title' => 'Test Decision',
        ];

        $decisionData = array_merge($defaults, $overrides);
        $decisionId = $decisionData['id'];
        unset($decisionData['id']);

        DB::table('court_decisions')->insert(array_merge(['id' => $decisionId], $decisionData));

        return $decisionId;
    }
}
