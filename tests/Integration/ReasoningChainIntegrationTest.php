<?php

namespace Tests\Integration;

use App\Services\Explainability\ReasoningTraceService;
use App\Services\Graph\LawGraphSyncService;
use App\Services\Graph\ReasoningChainService;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Integration Test: Reasoning Chain Service (Sprint 4.4)
 *
 * Tests the complete workflow:
 * 1. User provides natural language query
 * 2. Service converts NL to Cypher using LLM
 * 3. Service executes Cypher query against Neo4j graph
 * 4. Results are returned with reasoning traces
 *
 * Acceptance Criteria:
 * ✅ Can execute 3 example queries successfully
 * ✅ NL → Cypher conversion works for common patterns
 * ✅ Reasoning trace explains graph traversal
 * ✅ Results are accurate and relevant
 */
class ReasoningChainIntegrationTest extends TestCase
{
    use UsesTestDatabase;

    /** @test */
    public function it_executes_end_to_end_reasoning_chain_workflow()
    {
        // Mock services
        $lawGraphSyncMock = Mockery::mock(LawGraphSyncService::class);
        $openAIMock = Mockery::mock(OpenAIService::class);
        $traceServiceMock = Mockery::mock(ReasoningTraceService::class);

        // Create test data
        $lawId = $this->createLaw(['law_number' => 'NN 152/08', 'title' => 'ZKP']);
        $decisionId = $this->createDecision(['case_number' => 'K-123/2020', 'court' => 'Vrhovni sud']);

        // Mock: Trace service
        $traceServiceMock->shouldReceive('startTrace')->once()->andReturn('trace-integration-1');
        $traceServiceMock->shouldReceive('endTrace')->once()->andReturn(true);

        // Mock: NL to Cypher conversion
        $openAIMock->shouldReceive('chat')
            ->once()
            ->andReturn([
                'content' => json_encode([
                    'cypher' => 'MATCH (d:Decision) WHERE d.court CONTAINS "Vrhovni sud" RETURN d LIMIT 5',
                    'explanation' => 'Finds Supreme Court decisions',
                    'parameters' => [],
                ]),
            ]);

        // Mock: Graph query execution
        $lawGraphSyncMock->shouldReceive('query')
            ->once()
            ->andReturn([
                ['d' => ['id' => $decisionId, 'case_number' => 'K-123/2020', 'court' => 'Vrhovni sud']],
            ]);

        // Create service
        $service = new ReasoningChainService($lawGraphSyncMock, $openAIMock, $traceServiceMock);

        // Act: Execute reasoning chain
        $result = $service->executeReasoningChain('Find Supreme Court decisions');

        // Assert: Complete workflow executed successfully
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('cypher_query', $result);
        $this->assertArrayHasKey('explanation', $result);
        $this->assertArrayHasKey('trace_id', $result);
        $this->assertEquals('trace-integration-1', $result['trace_id']);
        $this->assertCount(1, $result['results']);
    }

    /** @test */
    public function it_executes_complex_multi_hop_query_with_contradictions()
    {
        // Mock services
        $lawGraphSyncMock = Mockery::mock(LawGraphSyncService::class);
        $openAIMock = Mockery::mock(OpenAIService::class);
        $traceServiceMock = Mockery::mock(ReasoningTraceService::class);

        // Create test data: citation chain with contradictions
        $lawId = $this->createLaw(['law_number' => 'NN 152/08']);
        $originalDecisionId = $this->createDecision(['case_number' => 'K-100/2020', 'court' => 'Županijski sud']);
        $contradictingDecisionId = $this->createDecision(['case_number' => 'K-200/2021', 'court' => 'Vrhovni sud']);

        // Mock: Trace service
        $traceServiceMock->shouldReceive('startTrace')->once()->andReturn('trace-contradiction-1');
        $traceServiceMock->shouldReceive('endTrace')->once()->andReturn(true);

        // Mock: LLM generates complex Cypher query
        $openAIMock->shouldReceive('chat')
            ->once()
            ->andReturn([
                'content' => json_encode([
                    'cypher' => 'MATCH (sc:Decision)-[:CONTRADICTS]->(d:Decision)-[:CITES]->(l:LawDocument) WHERE l.law_number = $law_number AND sc.court CONTAINS "Vrhovni sud" RETURN sc, d, l',
                    'explanation' => 'Finds Supreme Court decisions that contradict decisions citing specific law',
                    'parameters' => ['law_number' => 'NN 152/08'],
                ]),
            ]);

        // Mock: Graph returns contradiction relationship
        $lawGraphSyncMock->shouldReceive('query')
            ->once()
            ->with(
                Mockery::on(fn ($cypher) => strpos($cypher, 'CONTRADICTS') !== false),
                ['law_number' => 'NN 152/08']
            )
            ->andReturn([
                [
                    'sc' => ['id' => $contradictingDecisionId, 'case_number' => 'K-200/2021', 'court' => 'Vrhovni sud'],
                    'd' => ['id' => $originalDecisionId, 'case_number' => 'K-100/2020'],
                    'l' => ['id' => $lawId, 'law_number' => 'NN 152/08'],
                ],
            ]);

        // Create service
        $service = new ReasoningChainService($lawGraphSyncMock, $openAIMock, $traceServiceMock);

        // Act
        $result = $service->executeReasoningChain('Find Supreme Court decisions contradicting decisions citing Law NN 152/08');

        // Assert: Multi-hop query executed successfully
        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['results']);
        $this->assertEquals($contradictingDecisionId, $result['results'][0]['sc']['id']);
        $this->assertStringContainsString('CONTRADICTS', $result['cypher_query']);
        $this->assertStringContainsString('CITES', $result['cypher_query']);
    }

    /** @test */
    public function it_executes_temporal_reasoning_with_law_amendments()
    {
        // Mock services
        $lawGraphSyncMock = Mockery::mock(LawGraphSyncService::class);
        $openAIMock = Mockery::mock(OpenAIService::class);
        $traceServiceMock = Mockery::mock(ReasoningTraceService::class);

        // Create test data: law version timeline
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

        // Mock: Trace service
        $traceServiceMock->shouldReceive('startTrace')->once()->andReturn('trace-temporal-1');
        $traceServiceMock->shouldReceive('endTrace')->once()->andReturn(true);

        // Mock: LLM generates temporal Cypher query
        $openAIMock->shouldReceive('chat')
            ->once()
            ->andReturn([
                'content' => json_encode([
                    'cypher' => 'MATCH (d:Decision)-[:CITES]->(l:LawDocument) WHERE l.valid_from >= "2023-01-01" AND l.valid_from <= "2023-12-31" RETURN d, l',
                    'explanation' => 'Finds decisions citing laws that came into effect in 2023',
                    'parameters' => [],
                ]),
            ]);

        // Mock: Graph returns decisions affected by new law
        $lawGraphSyncMock->shouldReceive('query')
            ->once()
            ->andReturn([
                [
                    'd' => ['id' => $affectedDecisionId, 'case_number' => 'K-AFFECTED/2023', 'decision_date' => '2023-09-15'],
                    'l' => ['id' => $newLawId, 'law_number' => 'NN 70/23', 'valid_from' => '2023-07-01'],
                ],
            ]);

        // Create service
        $service = new ReasoningChainService($lawGraphSyncMock, $openAIMock, $traceServiceMock);

        // Act
        $result = $service->executeReasoningChain('Find all decisions affected by law amendment in 2023');

        // Assert: Temporal reasoning executed successfully
        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['results']);
        $this->assertEquals($affectedDecisionId, $result['results'][0]['d']['id']);
        $this->assertEquals($newLawId, $result['results'][0]['l']['id']);
        $this->assertStringContainsString('valid_from', $result['cypher_query']);
    }

    /** @test */
    public function it_handles_llm_and_graph_errors_in_integration()
    {
        // Mock services
        $lawGraphSyncMock = Mockery::mock(LawGraphSyncService::class);
        $openAIMock = Mockery::mock(OpenAIService::class);
        $traceServiceMock = Mockery::mock(ReasoningTraceService::class);

        // Mock: Trace service
        $traceServiceMock->shouldReceive('startTrace')->once()->andReturn('trace-error-1');
        $traceServiceMock->shouldReceive('endTrace')->once()->andReturn(true);

        // Mock: LLM conversion succeeds but graph query fails
        $openAIMock->shouldReceive('chat')
            ->once()
            ->andReturn([
                'content' => json_encode([
                    'cypher' => 'INVALID SYNTAX QUERY',
                    'explanation' => 'This will fail',
                    'parameters' => [],
                ]),
            ]);

        $lawGraphSyncMock->shouldReceive('query')
            ->once()
            ->andThrow(new \Exception('Neo4j syntax error'));

        // Create service
        $service = new ReasoningChainService($lawGraphSyncMock, $openAIMock, $traceServiceMock);

        // Act
        $result = $service->executeReasoningChain('Invalid query');

        // Assert: Error handled gracefully
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Neo4j syntax error', $result['error']);
        $this->assertArrayHasKey('trace_id', $result);
        $this->assertEquals('trace-error-1', $result['trace_id']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
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
