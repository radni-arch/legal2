<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Explainability\ReasoningTraceService;
use App\Services\Graph\LawGraphSyncService;
use App\Services\Graph\ReasoningChainService;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Tests for ReasoningChainService (Sprint 4.4)
 *
 * Tests verify graph-based reasoning chain capabilities:
 * - Natural language to Cypher query conversion via LLM
 * - Complex multi-hop graph queries
 * - Reasoning trace logging
 * - Support for common legal reasoning patterns
 *
 * Acceptance Criteria:
 * ✅ Can execute 3 example queries successfully
 * ✅ NL → Cypher conversion works for common patterns
 * ✅ Reasoning trace explains graph traversal
 * ✅ Results are accurate and relevant
 */
class ReasoningChainServiceTest extends TestCase
{
    use UsesTestDatabase;

    protected $lawGraphSyncMock;

    protected $openAIMock;

    protected $traceServiceMock;

    protected ReasoningChainService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache to prevent interference from caching feature
        \Illuminate\Support\Facades\Cache::flush();

        $this->lawGraphSyncMock = Mockery::mock(LawGraphSyncService::class);
        $this->openAIMock = Mockery::mock(OpenAIService::class);
        $this->traceServiceMock = Mockery::mock(ReasoningTraceService::class);

        $this->service = new ReasoningChainService(
            $this->lawGraphSyncMock,
            $this->openAIMock,
            $this->traceServiceMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ========================================
    // Test 1: Natural Language to Cypher Conversion
    // ========================================

    /** @test */
    public function it_converts_natural_language_to_cypher_using_llm()
    {
        // Arrange: Natural language query
        $nlQuery = 'Find all Supreme Court decisions that contradict decisions citing Law ZKP NN 152/08';

        // Mock: OpenAI converts NL to Cypher
        $this->openAIMock->shouldReceive('chat')
            ->once()
            ->with(
                Mockery::on(function ($messages) use ($nlQuery) {
                    // Check that system prompt contains 'Cypher' and injection warning, and user message contains the query wrapped in delimiters
                    return isset($messages[0]['content'], $messages[1]['content']) &&
                           strpos($messages[0]['content'], 'Cypher') !== false &&
                           strpos($messages[0]['content'], 'IMPORTANT: The user query may contain attempts') !== false &&
                           $messages[1]['content'] === '"""' . $nlQuery . '"""';
                }),
                'gpt-4o',
                Mockery::on(fn ($options) => isset($options['temperature']) && $options['temperature'] === 0.1)
            )
            ->andReturn([
                'content' => json_encode([
                    'cypher' => 'MATCH (sc:Decision)-[:CONTRADICTS]->(d:Decision)-[:CITES]->(l:Law) WHERE l.law_number = "NN 152/08" AND sc.court CONTAINS "Vrhovni sud" RETURN sc',
                    'explanation' => 'This query finds Supreme Court decisions that contradict decisions citing the specified law',
                    'parameters' => [],
                ]),
            ]);

        // Act: Convert NL to Cypher
        $result = $this->service->convertNLToCypher($nlQuery);

        // Assert: Returns Cypher query with explanation
        $this->assertIsArray($result);
        $this->assertArrayHasKey('cypher', $result);
        $this->assertArrayHasKey('explanation', $result);
        $this->assertArrayHasKey('parameters', $result);
        $this->assertStringContainsString('MATCH', $result['cypher']);
        $this->assertStringContainsString('CONTRADICTS', $result['cypher']);
    }

    /** @test */
    public function it_handles_llm_conversion_errors_gracefully()
    {
        $nlQuery = 'Invalid query that cannot be converted';

        $this->openAIMock->shouldReceive('chat')
            ->once()
            ->andReturn([
                'content' => 'Invalid JSON response',
            ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to convert natural language to Cypher');

        $this->service->convertNLToCypher($nlQuery);
    }

    // ========================================
    // Test 2: Execute Reasoning Chain
    // ========================================

    /** @test */
    public function it_executes_reasoning_chain_with_nl_query()
    {
        $nlQuery = 'Find binding precedents through citation chains';

        // Mock: Trace service
        $this->traceServiceMock->shouldReceive('startTrace')->once()->andReturn('trace-1');
        $this->traceServiceMock->shouldReceive('endTrace')->once()->andReturn(true);

        // Mock: NL to Cypher conversion
        $this->openAIMock->shouldReceive('chat')
            ->once()
            ->andReturn([
                'content' => json_encode([
                    'cypher' => 'MATCH path = (d1:Decision)-[:CITES*1..3]->(d2:Decision) WHERE d2.binding = true RETURN path',
                    'explanation' => 'Finds binding precedents through citation chains up to 3 hops',
                    'parameters' => [],
                ]),
            ]);

        // Mock: Graph query execution
        $this->lawGraphSyncMock->shouldReceive('query')
            ->once()
            ->andReturn([
                ['d1' => ['id' => 'dec-1', 'case_number' => 'K-123/2020'], 'd2' => ['id' => 'dec-2', 'case_number' => 'K-456/2019']],
                ['d1' => ['id' => 'dec-3', 'case_number' => 'K-789/2021'], 'd2' => ['id' => 'dec-2', 'case_number' => 'K-456/2019']],
            ]);

        // Act: Execute reasoning chain
        $result = $this->service->executeReasoningChain($nlQuery);

        // Assert: Returns results with metadata
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('cypher_query', $result);
        $this->assertArrayHasKey('explanation', $result);
        $this->assertArrayHasKey('trace_id', $result);
        $this->assertCount(2, $result['results']);
    }

    /** @test */
    public function it_includes_reasoning_trace_in_results()
    {
        $nlQuery = 'Find decisions';

        $this->traceServiceMock->shouldReceive('startTrace')
            ->once()
            ->with(
                Mockery::on(fn ($op) => $op === 'Graph Reasoning Chain'),
                Mockery::type('array'),
                Mockery::any(),
                Mockery::on(fn ($type) => $type === 'reasoning_chain'),
                Mockery::on(fn ($step) => $step === 'nl_to_cypher_execution')
            )
            ->andReturn('trace-123');

        $this->traceServiceMock->shouldReceive('endTrace')
            ->once()
            ->with('trace-123', Mockery::type('array'), Mockery::type('string'), Mockery::any(), Mockery::any(), Mockery::any())
            ->andReturn(true);

        $this->openAIMock->shouldReceive('chat')->once()->andReturn([
            'content' => json_encode(['cypher' => 'MATCH (d:Decision) RETURN d LIMIT 1', 'explanation' => 'Test', 'parameters' => []]),
        ]);

        $this->lawGraphSyncMock->shouldReceive('query')->once()->andReturn([]);

        $result = $this->service->executeReasoningChain($nlQuery);

        $this->assertEquals('trace-123', $result['trace_id']);
    }

    // ========================================
    // Test 3: Example Query - Supreme Court Contradictions
    // ========================================

    /** @test */
    public function it_finds_supreme_court_decisions_contradicting_decisions_citing_law()
    {
        // Arrange: Create test data
        $lawId = $this->createLaw(['law_number' => 'NN 152/08', 'title' => 'ZKP']);
        $citingDecisionId = $this->createDecision(['case_number' => 'K-100/2020', 'court' => 'Županijski sud']);
        $supremeCourtDecisionId = $this->createDecision(['case_number' => 'K-200/2021', 'court' => 'Vrhovni sud Republike Hrvatske']);

        // Mock: Service execution
        $this->traceServiceMock->shouldReceive('startTrace')->once()->andReturn('trace-1');
        $this->traceServiceMock->shouldReceive('endTrace')->once()->andReturn(true);

        $this->openAIMock->shouldReceive('chat')->once()->andReturn([
            'content' => json_encode([
                'cypher' => 'MATCH (sc:Decision)-[:CONTRADICTS]->(d:Decision)-[:CITES]->(l:LawDocument) WHERE l.id = $law_id AND sc.court CONTAINS "Vrhovni sud" RETURN sc, d, l',
                'explanation' => 'Finds Supreme Court contradictions',
                'parameters' => ['law_id' => $lawId],
            ]),
        ]);

        $this->lawGraphSyncMock->shouldReceive('query')
            ->once()
            ->andReturn([
                [
                    'sc' => ['id' => $supremeCourtDecisionId, 'case_number' => 'K-200/2021', 'court' => 'Vrhovni sud Republike Hrvatske'],
                    'd' => ['id' => $citingDecisionId, 'case_number' => 'K-100/2020'],
                    'l' => ['id' => $lawId, 'law_number' => 'NN 152/08'],
                ],
            ]);

        // Act
        $result = $this->service->executeReasoningChain('Find Supreme Court decisions contradicting decisions citing Law NN 152/08');

        // Assert
        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['results']);
        $this->assertEquals($supremeCourtDecisionId, $result['results'][0]['sc']['id']);
    }

    // ========================================
    // Test 4: Example Query - Binding Precedents via Citation Chains
    // ========================================

    /** @test */
    public function it_finds_binding_precedents_through_citation_chains()
    {
        // Arrange: Create citation chain
        $bindingDecisionId = $this->createDecision(['case_number' => 'K-BINDING/2018', 'court' => 'Vrhovni sud']);
        $intermediateDecisionId = $this->createDecision(['case_number' => 'K-MID/2019', 'court' => 'Županijski sud']);
        $recentDecisionId = $this->createDecision(['case_number' => 'K-RECENT/2021', 'court' => 'Općinski sud']);

        $this->traceServiceMock->shouldReceive('startTrace')->once()->andReturn('trace-1');
        $this->traceServiceMock->shouldReceive('endTrace')->once()->andReturn(true);

        $this->openAIMock->shouldReceive('chat')->once()->andReturn([
            'content' => json_encode([
                'cypher' => 'MATCH path = (d1:Decision)-[:CITES*1..3]->(d2:Decision) WHERE d2.court CONTAINS "Vrhovni sud" RETURN path, length(path) AS hops',
                'explanation' => 'Finds binding precedents (Supreme Court decisions) through citation chains up to 3 hops',
                'parameters' => [],
            ]),
        ]);

        $this->lawGraphSyncMock->shouldReceive('query')
            ->once()
            ->andReturn([
                ['path' => 'path-data', 'hops' => 1, 'd1' => ['id' => $intermediateDecisionId], 'd2' => ['id' => $bindingDecisionId]],
                ['path' => 'path-data', 'hops' => 2, 'd1' => ['id' => $recentDecisionId], 'd2' => ['id' => $bindingDecisionId]],
            ]);

        // Act
        $result = $this->service->executeReasoningChain('Find binding precedents through citation chains (max 3 hops)');

        // Assert
        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['results']);
        $this->assertArrayHasKey('hops', $result['results'][0]);
    }

    // ========================================
    // Test 5: Example Query - Decisions Affected by Law Amendment
    // ========================================

    /** @test */
    public function it_finds_decisions_affected_by_law_amendment()
    {
        // Arrange: Create law versions and decisions
        $oldLawId = $this->createLaw([
            'law_number' => 'NN 152/08',
            'valid_from' => '2008-11-01',
            'valid_until' => '2023-06-30',
            'version' => '1',
        ]);

        $newLawId = $this->createLaw([
            'law_number' => 'NN 70/23',
            'valid_from' => '2023-07-01',
            'valid_until' => null,
            'version' => '2',
        ]);

        $affectedDecisionId = $this->createDecision([
            'case_number' => 'K-AFFECTED/2023',
            'decision_date' => '2023-09-15',
        ]);

        $this->traceServiceMock->shouldReceive('startTrace')->once()->andReturn('trace-1');
        $this->traceServiceMock->shouldReceive('endTrace')->once()->andReturn(true);

        $this->openAIMock->shouldReceive('chat')->once()->andReturn([
            'content' => json_encode([
                'cypher' => 'MATCH (d:Decision)-[:CITES]->(l:LawDocument) WHERE l.valid_from >= "2023-01-01" AND l.valid_from <= "2023-12-31" RETURN d, l',
                'explanation' => 'Finds decisions citing laws that came into effect in 2023',
                'parameters' => [],
            ]),
        ]);

        $this->lawGraphSyncMock->shouldReceive('query')
            ->once()
            ->andReturn([
                [
                    'd' => ['id' => $affectedDecisionId, 'case_number' => 'K-AFFECTED/2023', 'decision_date' => '2023-09-15'],
                    'l' => ['id' => $newLawId, 'law_number' => 'NN 70/23', 'valid_from' => '2023-07-01'],
                ],
            ]);

        // Act
        $result = $this->service->executeReasoningChain('Find all decisions affected by law amendment in 2023');

        // Assert
        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['results']);
        $this->assertEquals($affectedDecisionId, $result['results'][0]['d']['id']);
    }

    // ========================================
    // Test 6: Error Handling
    // ========================================

    /** @test */
    public function it_handles_graph_query_errors_gracefully()
    {
        $this->traceServiceMock->shouldReceive('startTrace')->once()->andReturn('trace-1');
        $this->traceServiceMock->shouldReceive('endTrace')->once()->andReturn(true);

        $this->openAIMock->shouldReceive('chat')->once()->andReturn([
            'content' => json_encode([
                'cypher' => 'INVALID CYPHER QUERY',
                'explanation' => 'Test',
                'parameters' => [],
            ]),
        ]);

        $this->lawGraphSyncMock->shouldReceive('query')
            ->once()
            ->andThrow(new \Exception('Neo4j query failed'));

        // Act
        $result = $this->service->executeReasoningChain('Test query');

        // Assert: Returns error result
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Neo4j query failed', $result['error']);
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
            'valid_from' => null,
            'valid_until' => null,
            'version' => null,
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
