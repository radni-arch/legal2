<?php

namespace Tests\Unit\Mcp\Tools;

use App\Mcp\Tools\LegalConceptTool;
use App\Services\LawSearchService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Mockery;
use Tests\TestCase;

/**
 * TDD Tests for LegalConceptTool
 *
 * Tests legal concept analysis including:
 * - Concept definition lookup
 * - Related concepts discovery
 * - Precedent identification
 * - Doctrinal analysis
 *
 * Sprint 12.5 - Worker B: Missing MCP Tools
 */
class LegalConceptToolTest extends TestCase
{
    protected $lawSearchService;

    protected LegalConceptTool $tool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lawSearchService = Mockery::mock(LawSearchService::class);
        $this->tool = new LegalConceptTool($this->lawSearchService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ========================================
    // Test 1: Define legal concept
    // ========================================

    public function test_defines_legal_concept(): void
    {
        // Arrange: Mock service to return concept definition
        $this->lawSearchService
            ->shouldReceive('analyzeConcept')
            ->once()
            ->with('proportionality', Mockery::any())
            ->andReturn([
                'concept' => 'proportionality',
                'definition' => 'Principle requiring balance between means and ends in legal actions',
                'legal_basis' => ['ZKP Članak 9', 'Ustav RH Članak 16'],
                'examples' => ['Search warrant must be proportional to crime severity'],
                'jurisdiction' => 'HR',
            ]);

        // Act: Call tool
        $request = new Request(['concept' => 'proportionality',
            'operation' => 'define', ]);
        $result = $this->tool->handle($request);

        // Assert: Result contains definition
        $this->assertInstanceOf(Response::class, $result);
        $output = json_decode((string) $result->content(), true);
        $this->assertEquals('proportionality', $output['concept']);
        $this->assertIsString($output['definition']);
        $this->assertIsArray($output['legal_basis']);
        $this->assertIsArray($output['examples']);
    }

    // ========================================
    // Test 2: Find related concepts
    // ========================================

    public function test_finds_related_concepts(): void
    {
        // Arrange: Mock service to return related concepts
        $this->lawSearchService
            ->shouldReceive('analyzeConcept')
            ->once()
            ->with('due process', Mockery::any())
            ->andReturn([
                'concept' => 'due process',
                'related_concepts' => [
                    ['name' => 'fair trial', 'relationship' => 'encompasses', 'similarity' => 0.95],
                    ['name' => 'natural justice', 'relationship' => 'equivalent', 'similarity' => 0.88],
                    ['name' => 'procedural fairness', 'relationship' => 'synonym', 'similarity' => 0.92],
                ],
                'count' => 3,
            ]);

        // Act: Call tool
        $request = new Request(['concept' => 'due process',
            'operation' => 'related', ]);
        $result = $this->tool->handle($request);

        // Assert: Result contains related concepts
        $output = json_decode((string) $result->content(), true);
        $this->assertEquals('due process', $output['concept']);
        $this->assertCount(3, $output['related_concepts']);
        $this->assertEquals('fair trial', $output['related_concepts'][0]['name']);
    }

    // ========================================
    // Test 3: Identify precedents
    // ========================================

    public function test_identifies_precedents(): void
    {
        // Arrange: Mock service to return precedents
        $this->lawSearchService
            ->shouldReceive('analyzeConcept')
            ->once()
            ->with('illegal search', Mockery::any())
            ->andReturn([
                'concept' => 'illegal search',
                'precedents' => [
                    ['decision_id' => 'dec-123', 'court' => 'Vrhovni sud RH', 'date' => '2023-05-15', 'relevance' => 0.95],
                    ['decision_id' => 'dec-456', 'court' => 'Županijski sud Osijek', 'date' => '2023-03-10', 'relevance' => 0.88],
                ],
                'count' => 2,
            ]);

        // Act: Call tool
        $request = new Request(['concept' => 'illegal search',
            'operation' => 'precedents',
            'limit' => 5, ]);
        $result = $this->tool->handle($request);

        // Assert: Result contains precedents
        $output = json_decode((string) $result->content(), true);
        $this->assertEquals('illegal search', $output['concept']);
        $this->assertCount(2, $output['precedents']);
        $this->assertEquals('Vrhovni sud RH', $output['precedents'][0]['court']);
    }

    // ========================================
    // Test 4: Analyze doctrine
    // ========================================

    public function test_analyzes_doctrine(): void
    {
        // Arrange: Mock service to return doctrinal analysis
        $this->lawSearchService
            ->shouldReceive('analyzeConcept')
            ->once()
            ->with('fruit of poisonous tree', Mockery::any())
            ->andReturn([
                'concept' => 'fruit of poisonous tree',
                'doctrine_type' => 'exclusionary rule',
                'origin' => 'U.S. common law, adopted in Croatian law',
                'application' => 'Evidence obtained from illegal search is inadmissible',
                'exceptions' => ['Independent source', 'Inevitable discovery', 'Good faith'],
                'croatian_equivalent' => 'plodovi otrovanog stabla',
                'legal_basis' => ['ZKP Članak 10'],
            ]);

        // Act: Call tool
        $request = new Request(['concept' => 'fruit of poisonous tree',
            'operation' => 'doctrine', ]);
        $result = $this->tool->handle($request);

        // Assert: Result contains doctrinal analysis
        $output = json_decode((string) $result->content(), true);
        $this->assertEquals('fruit of poisonous tree', $output['concept']);
        $this->assertEquals('exclusionary rule', $output['doctrine_type']);
        $this->assertIsArray($output['exceptions']);
        $this->assertIsString($output['croatian_equivalent']);
    }
}
