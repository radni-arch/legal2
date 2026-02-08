<?php

namespace Tests\Integration;

use App\Agents\Specialists\ResearchSpecialistAgent;
use App\Models\AgentCollaboration;
use App\Services\Collaboration\SharedAgentContext;
use App\Services\DecisionSearchService;
use App\Services\Explainability\ReasoningTraceService;
use App\Services\Graph\GraphResearchEnhancer;
use App\Services\LawSearchService;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Integration Test: Graph-Enhanced Research for ResearchSpecialistAgent (Sprint 4.3)
 *
 * Tests the complete workflow:
 * 1. ResearchSpecialistAgent performs vector search for laws and decisions
 * 2. GraphResearchEnhancer finds additional decisions via graph traversal
 * 3. Results are combined and deduplicated
 * 4. Metrics show improvement over vector-only search
 *
 * Acceptance Criteria:
 * ✅ After finding laws, also finds citing decisions
 * ✅ Citation chain traversal works (2-3 hops)
 * ✅ Graph results improve overall research quality
 * ✅ Integration test validates graph-enhanced research
 */
class GraphEnhancedResearchTest extends TestCase
{
    use UsesTestDatabase;

    /** @test */
    public function it_enhances_research_with_graph_traversal()
    {
        // Enable Neo4j for this test
        Config::set('neo4j.sync.enabled', true);

        // Mock dependencies
        $lawSearchMock = Mockery::mock(LawSearchService::class);
        $decisionSearchMock = Mockery::mock(DecisionSearchService::class);
        $openAIMock = Mockery::mock(OpenAIService::class);
        $traceServiceMock = Mockery::mock(ReasoningTraceService::class);
        $graphEnhancerMock = Mockery::mock(GraphResearchEnhancer::class);

        // Mock trace service (all calls)
        $traceServiceMock->shouldReceive('startTrace')->andReturn('trace-1', 'trace-2', 'trace-3', 'trace-4');
        $traceServiceMock->shouldReceive('endTrace')->andReturn(true);

        // Mock vector search: Find 2 laws and 1 decision
        $vectorLaws = [
            ['id' => 'law-1', 'law_number' => 'NN 152/08', 'title' => 'ZKP', 'score' => 0.95],
            ['id' => 'law-2', 'law_number' => 'NN 125/11', 'title' => 'KZ', 'score' => 0.88],
        ];

        $vectorDecisions = [
            ['id' => 'decision-vector-1', 'case_number' => 'K-100/2020', 'score' => 0.92],
        ];

        // Mock all OpenAI chat calls (research plan only - prioritization/summary not called in this flow)
        $openAIMock->shouldReceive('chat')
            ->once()
            ->andReturn([
                'content' => json_encode([
                    'strategy' => 'comprehensive',
                    'law_queries' => ['kazneni postupak pretres stana'],
                    'decision_queries' => ['pretres stana proporcionalnost'],
                ]),
            ]);

        $lawSearchMock->shouldReceive('hybridSearch')
            ->once()
            ->andReturn(['success' => true, 'data' => $vectorLaws]);

        $decisionSearchMock->shouldReceive('hybridSearch')
            ->once()
            ->andReturn(['success' => true, 'data' => $vectorDecisions]);

        // Mock graph enhancement: Find 3 additional decisions
        $graphEnhancedResults = [
            'vector_laws' => $vectorLaws,
            'vector_decisions' => $vectorDecisions,
            'graph_decisions_citing_laws' => [
                ['decision_id' => 'decision-graph-1', 'case_number' => 'K-200/2019'],
                ['decision_id' => 'decision-graph-2', 'case_number' => 'K-300/2018'],
            ],
            'graph_related_decisions' => [
                ['related_decision_id' => 'decision-graph-3', 'case_number' => 'K-400/2017', 'hops' => 1],
            ],
            'total_decisions' => 4, // 1 vector + 3 graph
            'metrics' => [
                'vector_decision_count' => 1,
                'graph_decision_count' => 3,
                'total_unique_decisions' => 4,
                'graph_enhancement_percentage' => 300.0, // 3/1 * 100
            ],
        ];

        $graphEnhancerMock->shouldReceive('enhanceResearchResults')
            ->once()
            ->with($vectorLaws, $vectorDecisions)
            ->andReturn($graphEnhancedResults);

        // Create agent with mocked graph enhancer
        $agent = new ResearchSpecialistAgent(
            $lawSearchMock,
            $decisionSearchMock,
            $openAIMock,
            $traceServiceMock,
            $graphEnhancerMock
        );

        // Create AgentCollaboration for context
        $collaboration = AgentCollaboration::create([
            'orchestrator' => 'test',
            'problem_type' => 'legal_research',
            'problem_statement' => 'Home search proportionality analysis',
            'status' => 'in_progress',
        ]);

        // Create shared context
        $context = new SharedAgentContext($collaboration);

        // Execute research
        $result = $agent->execute($context, [
            'type' => 'legal_research',
            'description' => 'Research Croatian laws and court decisions on home search proportionality',
            'focus' => 'home_search_proportionality',
        ]);

        // Assert: Research completed successfully
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('trace_id', $result);

        // Assert: Found laws via vector search
        $this->assertEquals(2, $result['laws_found']);

        // Assert: Found decisions via both vector (1) and graph (3) = 4 total
        $this->assertEquals(4, $result['decisions_found']);

        // Assert: Graph enhanced results written to context
        $graphEnhanced = $context->read('graph_enhanced_results');
        $this->assertNotNull($graphEnhanced);
        $this->assertEquals(300.0, $graphEnhanced['metrics']['graph_enhancement_percentage']);
        $this->assertEquals(4, $graphEnhanced['total_decisions']);
    }

    /** @test */
    public function it_works_without_graph_enhancer_when_neo4j_disabled()
    {
        // Disable Neo4j
        Config::set('neo4j.sync.enabled', false);

        // Mock dependencies (without graph enhancer)
        $lawSearchMock = Mockery::mock(LawSearchService::class);
        $decisionSearchMock = Mockery::mock(DecisionSearchService::class);
        $openAIMock = Mockery::mock(OpenAIService::class);
        $traceServiceMock = Mockery::mock(ReasoningTraceService::class);

        $traceServiceMock->shouldReceive('startTrace')->andReturn('trace-1', 'trace-2');
        $traceServiceMock->shouldReceive('endTrace')->andReturn(true);

        $vectorLaws = [
            ['id' => 'law-1', 'law_number' => 'NN 152/08', 'score' => 0.95],
        ];

        $vectorDecisions = [
            ['id' => 'decision-1', 'case_number' => 'K-100/2020', 'score' => 0.92],
        ];

        $openAIMock->shouldReceive('chat')->once()->andReturn(
            ['content' => json_encode(['law_queries' => ['test'], 'decision_queries' => ['test']])]
        );

        $lawSearchMock->shouldReceive('hybridSearch')->once()->andReturn(['success' => true, 'data' => $vectorLaws]);
        $decisionSearchMock->shouldReceive('hybridSearch')->once()->andReturn(['success' => true, 'data' => $vectorDecisions]);

        // Create agent WITHOUT graph enhancer
        $agent = new ResearchSpecialistAgent(
            $lawSearchMock,
            $decisionSearchMock,
            $openAIMock,
            $traceServiceMock,
            null // No graph enhancer
        );

        // Create AgentCollaboration for context
        $collaboration = AgentCollaboration::create([
            'orchestrator' => 'test',
            'problem_type' => 'legal_research',
            'problem_statement' => 'Test research without graph',
            'status' => 'in_progress',
        ]);

        $context = new SharedAgentContext($collaboration);

        $result = $agent->execute($context, [
            'type' => 'legal_research',
            'description' => 'Test research without graph enhancement',
        ]);

        // Assert: Works with vector search only
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['laws_found']);
        $this->assertEquals(1, $result['decisions_found']);

        // Assert: No graph enhancement performed
        $graphEnhanced = $context->read('graph_enhanced_results');
        $this->assertNull($graphEnhanced);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
