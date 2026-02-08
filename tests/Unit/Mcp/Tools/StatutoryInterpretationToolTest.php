<?php

namespace Tests\Unit\Mcp\Tools;

use App\Mcp\Tools\StatutoryInterpretationTool;
use App\Services\LawSearchService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Mockery;
use Tests\TestCase;

/**
 * TDD Tests for StatutoryInterpretationTool
 *
 * Tests statutory interpretation including:
 * - Legislative history analysis
 * - Statutory construction methods
 * - Conflicting provisions resolution
 * - Regulatory framework mapping
 *
 * Sprint 12.5 - Worker B: Missing MCP Tools
 */
class StatutoryInterpretationToolTest extends TestCase
{
    protected $lawSearchService;

    protected StatutoryInterpretationTool $tool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lawSearchService = Mockery::mock(LawSearchService::class);
        $this->tool = new StatutoryInterpretationTool($this->lawSearchService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ========================================
    // Test 1: Analyze legislative history
    // ========================================

    public function test_analyzes_legislative_history(): void
    {
        // Arrange: Mock service to return legislative history
        $this->lawSearchService
            ->shouldReceive('interpretStatute')
            ->once()
            ->with('ZKP Članak 9', Mockery::any())
            ->andReturn([
                'statute' => 'ZKP Članak 9',
                'title' => 'Načelo razmjernosti',
                'original_text' => 'Original version from 2008',
                'amendments' => [
                    ['date' => '2011-07-15', 'description' => 'Added proportionality requirement', 'reason' => 'EU harmonization'],
                    ['date' => '2019-01-01', 'description' => 'Clarified application scope', 'reason' => 'Court rulings'],
                ],
                'legislative_intent' => 'Ensure procedural fairness and protection of rights',
                'current_version' => '2019 version',
            ]);

        // Act: Call tool
        $request = new Request(['statute' => 'ZKP Članak 9',
            'operation' => 'history', ]);
        $result = $this->tool->handle($request);

        // Assert: Result contains legislative history
        $this->assertInstanceOf(Response::class, $result);
        $output = json_decode((string) $result->content(), true);
        $this->assertEquals('ZKP Članak 9', $output['statute']);
        $this->assertCount(2, $output['amendments']);
        $this->assertIsString($output['legislative_intent']);
    }

    // ========================================
    // Test 2: Apply statutory construction
    // ========================================

    public function test_applies_statutory_construction(): void
    {
        // Arrange: Mock service to return construction methods
        $this->lawSearchService
            ->shouldReceive('interpretStatute')
            ->once()
            ->with('Kazneni zakon Članak 87', Mockery::any())
            ->andReturn([
                'statute' => 'Kazneni zakon Članak 87',
                'method' => 'textualist',
                'plain_meaning' => 'Ordinary meaning of statutory text',
                'grammatical_analysis' => 'Subject-verb-object structure indicates...',
                'key_terms' => ['kazneno djelo', 'namjera', 'nehat'],
                'interpretation' => 'Statute requires both intent and act',
                'ambiguities' => ['Term "namjera" has multiple meanings in context'],
            ]);

        // Act: Call tool
        $request = new Request(['statute' => 'Kazneni zakon Članak 87',
            'operation' => 'construction',
            'method' => 'textualist', ]);
        $result = $this->tool->handle($request);

        // Assert: Result contains construction analysis
        $output = json_decode((string) $result->content(), true);
        $this->assertEquals('Kazneni zakon Članak 87', $output['statute']);
        $this->assertEquals('textualist', $output['method']);
        $this->assertIsArray($output['key_terms']);
        $this->assertIsString($output['interpretation']);
    }

    // ========================================
    // Test 3: Resolve conflicting provisions
    // ========================================

    public function test_resolves_conflicting_provisions(): void
    {
        // Arrange: Mock service to return conflict resolution
        $this->lawSearchService
            ->shouldReceive('interpretStatute')
            ->once()
            ->with('ZKP Članak 10', Mockery::any())
            ->andReturn([
                'primary_statute' => 'ZKP Članak 10',
                'conflicting_statute' => 'Ustav RH Članak 25',
                'conflict_type' => 'hierarchical',
                'resolution' => 'Constitutional provision prevails (lex superior)',
                'reconciliation' => 'ZKP must be read in light of Constitutional guarantee',
                'precedents' => ['dec-123', 'dec-456'],
                'recommended_interpretation' => 'Apply constitutional standard first',
            ]);

        // Act: Call tool
        $request = new Request(['statute' => 'ZKP Članak 10',
            'operation' => 'conflicts',
            'related_statute' => 'Ustav RH Članak 25', ]);
        $result = $this->tool->handle($request);

        // Assert: Result contains conflict resolution
        $output = json_decode((string) $result->content(), true);
        $this->assertEquals('ZKP Članak 10', $output['primary_statute']);
        $this->assertEquals('Ustav RH Članak 25', $output['conflicting_statute']);
        $this->assertEquals('hierarchical', $output['conflict_type']);
        $this->assertIsArray($output['precedents']);
    }

    // ========================================
    // Test 4: Map regulatory framework
    // ========================================

    public function test_maps_regulatory_framework(): void
    {
        // Arrange: Mock service to return regulatory framework
        $this->lawSearchService
            ->shouldReceive('interpretStatute')
            ->once()
            ->with('ZKP Članak 215', Mockery::any())
            ->andReturn([
                'statute' => 'ZKP Članak 215',
                'title' => 'Pretraga prostorija',
                'framework' => [
                    'primary_law' => ['ZKP Članak 215', 'ZKP Članak 216'],
                    'constitutional_basis' => ['Ustav RH Članak 34'],
                    'implementing_regulations' => ['Pravilnik o pretragama 2020'],
                    'related_provisions' => ['ZKP Članak 9', 'ZKP Članak 217'],
                ],
                'hierarchy' => ['Ustav RH', 'ZKP', 'Pravilnici'],
                'interaction_map' => 'Article 215 governs home searches, Article 216 defines procedure',
            ]);

        // Act: Call tool
        $request = new Request(['statute' => 'ZKP Članak 215',
            'operation' => 'framework', ]);
        $result = $this->tool->handle($request);

        // Assert: Result contains regulatory framework
        $output = json_decode((string) $result->content(), true);
        $this->assertEquals('ZKP Članak 215', $output['statute']);
        $this->assertArrayHasKey('primary_law', $output['framework']);
        $this->assertArrayHasKey('constitutional_basis', $output['framework']);
        $this->assertIsArray($output['hierarchy']);
    }
}
