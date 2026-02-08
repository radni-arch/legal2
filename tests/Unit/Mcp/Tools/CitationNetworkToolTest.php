<?php

namespace Tests\Unit\Mcp\Tools;

use App\Mcp\Tools\CitationNetworkTool;
use App\Services\DecisionCitationService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Mockery;
use Tests\TestCase;

/**
 * TDD Tests for CitationNetworkTool
 *
 * Tests citation network analysis including:
 * - Citation graph traversal
 * - Authority scoring
 * - Citation patterns
 * - Influence mapping
 *
 * Sprint 12.5 - Worker B: Missing MCP Tools
 */
class CitationNetworkToolTest extends TestCase
{
    protected $citationService;

    protected CitationNetworkTool $tool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->citationService = Mockery::mock(DecisionCitationService::class);
        $this->tool = new CitationNetworkTool($this->citationService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ========================================
    // Test 1: Traverse citation graph
    // ========================================

    public function test_traverses_citation_graph(): void
    {
        // Arrange: Mock service to return citation graph
        $this->citationService
            ->shouldReceive('analyzeCitations')
            ->once()
            ->with('dec-123', Mockery::any())
            ->andReturn([
                'root_decision' => 'dec-123',
                'depth' => 2,
                'nodes' => [
                    ['id' => 'dec-123', 'title' => 'Root decision', 'level' => 0],
                    ['id' => 'dec-456', 'title' => 'Cited decision 1', 'level' => 1],
                    ['id' => 'dec-789', 'title' => 'Cited decision 2', 'level' => 1],
                    ['id' => 'dec-abc', 'title' => 'Secondary citation', 'level' => 2],
                ],
                'edges' => [
                    ['from' => 'dec-123', 'to' => 'dec-456', 'type' => 'cites'],
                    ['from' => 'dec-123', 'to' => 'dec-789', 'type' => 'cites'],
                    ['from' => 'dec-456', 'to' => 'dec-abc', 'type' => 'cites'],
                ],
            ]);

        // Act: Call tool
        $request = new Request(['decision_id' => 'dec-123',
            'operation' => 'graph',
            'depth' => 2, ]);
        $result = $this->tool->handle($request);

        // Assert: Result contains citation graph
        $this->assertInstanceOf(Response::class, $result);
        $output = json_decode((string) $result->content(), true);
        $this->assertEquals('dec-123', $output['root_decision']);
        $this->assertCount(4, $output['nodes']);
        $this->assertCount(3, $output['edges']);
    }

    // ========================================
    // Test 2: Calculate authority score
    // ========================================

    public function test_calculates_authority_score(): void
    {
        // Arrange: Mock service to return authority score
        $this->citationService
            ->shouldReceive('analyzeCitations')
            ->once()
            ->with('dec-456', Mockery::any())
            ->andReturn([
                'decision_id' => 'dec-456',
                'authority_score' => 8.7,
                'citation_count' => 45,
                'cited_by_count' => 23,
                'h_index' => 12,
                'influence_rank' => 'high',
                'citation_velocity' => 3.2,
            ]);

        // Act: Call tool
        $request = new Request(['decision_id' => 'dec-456',
            'operation' => 'authority', ]);
        $result = $this->tool->handle($request);

        // Assert: Result contains authority metrics
        $output = json_decode((string) $result->content(), true);
        $this->assertEquals('dec-456', $output['decision_id']);
        $this->assertEquals(8.7, $output['authority_score']);
        $this->assertEquals(45, $output['citation_count']);
        $this->assertEquals('high', $output['influence_rank']);
    }

    // ========================================
    // Test 3: Analyze citation patterns
    // ========================================

    public function test_analyzes_citation_patterns(): void
    {
        // Arrange: Mock service to return citation patterns
        $this->citationService
            ->shouldReceive('analyzeCitations')
            ->once()
            ->with('dec-789', Mockery::any())
            ->andReturn([
                'decision_id' => 'dec-789',
                'patterns' => [
                    ['type' => 'co-citation', 'decisions' => ['dec-111', 'dec-222'], 'frequency' => 8],
                    ['type' => 'citation_burst', 'period' => '2023-Q1', 'count' => 15],
                ],
                'temporal_distribution' => [
                    '2023' => 25,
                    '2024' => 18,
                ],
                'citing_courts' => [
                    'Vrhovni sud RH' => 12,
                    'Županijski sud' => 8,
                ],
            ]);

        // Act: Call tool
        $request = new Request(['decision_id' => 'dec-789',
            'operation' => 'patterns', ]);
        $result = $this->tool->handle($request);

        // Assert: Result contains citation patterns
        $output = json_decode((string) $result->content(), true);
        $this->assertEquals('dec-789', $output['decision_id']);
        $this->assertCount(2, $output['patterns']);
        $this->assertIsArray($output['temporal_distribution']);
        $this->assertIsArray($output['citing_courts']);
    }

    // ========================================
    // Test 4: Map citation influence
    // ========================================

    public function test_maps_citation_influence(): void
    {
        // Arrange: Mock service to return influence map
        $this->citationService
            ->shouldReceive('analyzeCitations')
            ->once()
            ->with('dec-abc', Mockery::any())
            ->andReturn([
                'decision_id' => 'dec-abc',
                'influenced_decisions' => [
                    ['id' => 'dec-def', 'court' => 'Vrhovni sud RH', 'date' => '2024-01-15', 'influence_score' => 0.92],
                    ['id' => 'dec-ghi', 'court' => 'Županijski sud Osijek', 'date' => '2024-02-20', 'influence_score' => 0.85],
                ],
                'influence_spread' => [
                    'direct' => 15,
                    'indirect' => 32,
                    'total_reach' => 47,
                ],
                'key_concepts_propagated' => ['proportionality', 'due process'],
            ]);

        // Act: Call tool
        $request = new Request(['decision_id' => 'dec-abc',
            'operation' => 'influence',
            'limit' => 10, ]);
        $result = $this->tool->handle($request);

        // Assert: Result contains influence map
        $output = json_decode((string) $result->content(), true);
        $this->assertEquals('dec-abc', $output['decision_id']);
        $this->assertCount(2, $output['influenced_decisions']);
        $this->assertArrayHasKey('total_reach', $output['influence_spread']);
        $this->assertIsArray($output['key_concepts_propagated']);
    }
}
