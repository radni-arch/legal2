<?php

namespace Tests\Unit\Services\Analysis\CaseLevel\AI;

use App\Models\CaseAnalysis;
use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Models\LegalCase;
use App\Services\Analysis\CaseLevel\AI\Contracts\ClaudeClientInterface;
use App\Services\Analysis\CaseLevel\AI\StrategyAnalyzer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;

class StrategyAnalyzerTest extends TestCase
{
    use DatabaseTransactions;

    private StrategyAnalyzer $analyzer;
    private $mockClaudeClient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockClaudeClient = Mockery::mock(ClaudeClientInterface::class);
        $this->analyzer = new StrategyAnalyzer($this->mockClaudeClient);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_implements_correct_analysis_type(): void
    {
        $this->assertEquals('strategy', $this->analyzer->analysisType());
    }

    /** @test */
    public function it_returns_empty_results_when_no_documents_exist(): void
    {
        // Arrange - case with no documents
        $case = LegalCase::factory()->create();

        // Act
        $result = $this->analyzer->analyze($case->id);

        // Assert
        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertEquals(0, $result['metadata']['documents_analyzed']);
        $this->assertEmpty($result['results']['strategies']);
        $this->assertEquals(0, $result['results']['total_strategies']);
    }

    /** @test */
    public function it_generates_strategic_recommendations_using_claude_api(): void
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $doc1 = CaseDocument::factory()->forCase($case)->create([
            'title' => 'Search Warrant',
            'category' => 'warrant',
        ]);
        $doc2 = CaseDocument::factory()->forCase($case)->create([
            'title' => 'Police Report',
            'category' => 'evidence',
        ]);

        // Create document-level analyses
        foreach ([$doc1, $doc2] as $doc) {
            DocumentAnalysis::create([
                'case_document_id' => $doc->id,
                'analysis_type' => DocumentAnalysis::TYPE_SUMMARY,
                'analysis_layer' => DocumentAnalysis::LAYER_AI_BASIC,
                'status' => DocumentAnalysis::STATUS_COMPLETED,
                'version' => 1,
                'results' => ['summary' => 'Document summary for ' . $doc->title],
            ]);
        }

        // Mock Claude API response
        $expectedClaudeResponse = [
            'strategies' => [
                [
                    'id' => 'strat-001',
                    'category' => 'evidence_challenges',
                    'title' => 'Challenge Search Warrant Validity',
                    'description' => 'Search warrant issued after search was conducted',
                    'strength' => 0.85,
                    'legal_basis' => 'ZKP čl. 240 - nalog mora biti izdan PRIJE pretrage',
                    'action_steps' => [
                        'File motion to suppress evidence',
                        'Request warrant issuance timestamp',
                        'Compare with search report timestamp',
                    ],
                    'risk' => 'Prosecution may argue exigent circumstances',
                ],
                [
                    'id' => 'strat-002',
                    'category' => 'credibility_attacks',
                    'title' => 'Challenge Witness Credibility',
                    'description' => 'Key witness has inconsistent statements',
                    'strength' => 0.65,
                    'legal_basis' => 'Cross-examination under ZKP čl. 285',
                    'action_steps' => [
                        'Prepare cross-examination questions',
                        'Document statement inconsistencies',
                    ],
                    'risk' => 'Witness may provide explanation for inconsistencies',
                ],
            ],
            'priority_actions' => [
                [
                    'priority' => 1,
                    'action' => 'File motion to suppress search evidence',
                    'deadline' => 'Before next hearing',
                    'strategy_id' => 'strat-001',
                ],
            ],
            'risk_assessment' => [
                [
                    'risk' => 'Strong prosecution evidence chain',
                    'severity' => 'medium',
                    'mitigation' => 'Focus on procedural defects',
                ],
            ],
            'summary' => 'Primary defense strategy: Challenge search warrant validity. Secondary: Attack witness credibility.',
        ];

        $this->mockClaudeClient
            ->shouldReceive('generateStrategicInsights')
            ->once()
            ->andReturn($expectedClaudeResponse);

        // Act
        $result = $this->analyzer->analyze($case->id);

        // Assert
        $this->assertCount(2, $result['results']['strategies']);
        $this->assertEquals('evidence_challenges', $result['results']['strategies'][0]['category']);
        $this->assertEquals(0.85, $result['results']['strategies'][0]['strength']);
        $this->assertArrayHasKey('legal_basis', $result['results']['strategies'][0]);
        $this->assertCount(1, $result['results']['priority_actions']);
    }

    /** @test */
    public function it_categorizes_strategies_by_type(): void
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $doc = CaseDocument::factory()->forCase($case)->create();

        DocumentAnalysis::create([
            'case_document_id' => $doc->id,
            'analysis_type' => DocumentAnalysis::TYPE_SUMMARY,
            'analysis_layer' => DocumentAnalysis::LAYER_AI_BASIC,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'version' => 1,
            'results' => ['summary' => 'Test summary'],
        ]);

        // Mock response with multiple categories
        $this->mockClaudeClient
            ->shouldReceive('generateStrategicInsights')
            ->once()
            ->andReturn([
                'strategies' => [
                    ['id' => 's1', 'category' => 'evidence_challenges', 'strength' => 0.8],
                    ['id' => 's2', 'category' => 'evidence_challenges', 'strength' => 0.7],
                    ['id' => 's3', 'category' => 'procedural_defenses', 'strength' => 0.6],
                    ['id' => 's4', 'category' => 'credibility_attacks', 'strength' => 0.5],
                ],
                'priority_actions' => [],
                'risk_assessment' => [],
                'summary' => 'Multiple strategy types identified.',
            ]);

        // Act
        $result = $this->analyzer->analyze($case->id);

        // Assert
        $this->assertArrayHasKey('by_category', $result['results']);
        $this->assertCount(2, $result['results']['by_category']['evidence_challenges']);
        $this->assertCount(1, $result['results']['by_category']['procedural_defenses']);
        $this->assertCount(1, $result['results']['by_category']['credibility_attacks']);
    }

    /** @test */
    public function it_calculates_overall_strength_score(): void
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $doc = CaseDocument::factory()->forCase($case)->create();

        DocumentAnalysis::create([
            'case_document_id' => $doc->id,
            'analysis_type' => DocumentAnalysis::TYPE_SUMMARY,
            'analysis_layer' => DocumentAnalysis::LAYER_AI_BASIC,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'version' => 1,
            'results' => ['summary' => 'Test summary'],
        ]);

        // Mock response
        $this->mockClaudeClient
            ->shouldReceive('generateStrategicInsights')
            ->once()
            ->andReturn([
                'strategies' => [
                    ['id' => 's1', 'strength' => 0.9],
                    ['id' => 's2', 'strength' => 0.7],
                    ['id' => 's3', 'strength' => 0.8],
                ],
                'priority_actions' => [],
                'risk_assessment' => [
                    ['severity' => 'medium'],
                ],
                'summary' => 'Strong defense position.',
            ]);

        // Act
        $result = $this->analyzer->analyze($case->id);

        // Assert
        $this->assertArrayHasKey('overall_strength_score', $result['results']);
        $this->assertGreaterThan(0, $result['results']['overall_strength_score']);
        $this->assertLessThanOrEqual(1, $result['results']['overall_strength_score']);
    }

    /** @test */
    public function it_extracts_strongest_arguments(): void
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $doc = CaseDocument::factory()->forCase($case)->create();

        DocumentAnalysis::create([
            'case_document_id' => $doc->id,
            'analysis_type' => DocumentAnalysis::TYPE_SUMMARY,
            'analysis_layer' => DocumentAnalysis::LAYER_AI_BASIC,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'version' => 1,
            'results' => ['summary' => 'Test summary'],
        ]);

        // Mock response with varying strengths
        $this->mockClaudeClient
            ->shouldReceive('generateStrategicInsights')
            ->once()
            ->andReturn([
                'strategies' => [
                    ['id' => 's1', 'title' => 'Weak Strategy', 'strength' => 0.3],
                    ['id' => 's2', 'title' => 'Strong Strategy', 'strength' => 0.95],
                    ['id' => 's3', 'title' => 'Medium Strategy', 'strength' => 0.6],
                    ['id' => 's4', 'title' => 'Very Strong Strategy', 'strength' => 0.98],
                ],
                'priority_actions' => [],
                'risk_assessment' => [],
                'summary' => 'Multiple strategies with varying strengths.',
            ]);

        // Act
        $result = $this->analyzer->analyze($case->id);

        // Assert
        $this->assertArrayHasKey('strongest_arguments', $result['results']);
        $this->assertNotEmpty($result['results']['strongest_arguments']);
        // Strongest should be first
        $this->assertEquals('Very Strong Strategy', $result['results']['strongest_arguments'][0]['title']);
    }

    /** @test */
    public function it_gathers_case_level_analyses_for_context(): void
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $doc = CaseDocument::factory()->forCase($case)->create();

        DocumentAnalysis::create([
            'case_document_id' => $doc->id,
            'analysis_type' => DocumentAnalysis::TYPE_SUMMARY,
            'analysis_layer' => DocumentAnalysis::LAYER_AI_BASIC,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'version' => 1,
            'results' => ['summary' => 'Test summary'],
        ]);

        // Create case-level analyses
        CaseAnalysis::create([
            'case_id' => $case->id,
            'analysis_type' => 'contradictions',
            'status' => CaseAnalysis::STATUS_COMPLETED,
            'version' => 1,
            'results' => ['contradictions' => [['id' => 'c1', 'severity' => 'high']]],
        ]);

        CaseAnalysis::create([
            'case_id' => $case->id,
            'analysis_type' => 'gaps',
            'status' => CaseAnalysis::STATUS_COMPLETED,
            'version' => 1,
            'results' => ['gaps' => [['id' => 'g1', 'type' => 'missing_document']]],
        ]);

        $analysisDataCaptured = null;
        $this->mockClaudeClient
            ->shouldReceive('generateStrategicInsights')
            ->once()
            ->withArgs(function ($analysisData) use (&$analysisDataCaptured) {
                $analysisDataCaptured = $analysisData;
                return true;
            })
            ->andReturn([
                'strategies' => [],
                'priority_actions' => [],
                'risk_assessment' => [],
                'summary' => 'No strategies.',
            ]);

        // Act
        $result = $this->analyzer->analyze($case->id);

        // Assert - verify case-level analyses were included
        $this->assertNotNull($analysisDataCaptured);
        $this->assertArrayHasKey('case_analyses', $analysisDataCaptured);
        $this->assertArrayHasKey('contradictions', $analysisDataCaptured['case_analyses']);
        $this->assertArrayHasKey('gaps', $analysisDataCaptured['case_analyses']);
    }

    /** @test */
    public function it_handles_claude_api_failure_gracefully(): void
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $doc = CaseDocument::factory()->forCase($case)->create();

        DocumentAnalysis::create([
            'case_document_id' => $doc->id,
            'analysis_type' => DocumentAnalysis::TYPE_SUMMARY,
            'analysis_layer' => DocumentAnalysis::LAYER_AI_BASIC,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'version' => 1,
            'results' => ['summary' => 'Test summary'],
        ]);

        // Mock Claude API failure
        $this->mockClaudeClient
            ->shouldReceive('generateStrategicInsights')
            ->once()
            ->andThrow(new \RuntimeException('API connection failed'));

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('API connection failed');

        $this->analyzer->analyze($case->id);
    }

    /** @test */
    public function it_includes_croatian_legal_framework(): void
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $doc = CaseDocument::factory()->forCase($case)->create([
            'title' => 'Zapisnik o pretrazi',
            'category' => 'search_report',
        ]);

        DocumentAnalysis::create([
            'case_document_id' => $doc->id,
            'analysis_type' => DocumentAnalysis::TYPE_SUMMARY,
            'analysis_layer' => DocumentAnalysis::LAYER_AI_BASIC,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'version' => 1,
            'results' => ['summary' => 'Search conducted without proper warrant'],
        ]);

        $analysisDataCaptured = null;
        $this->mockClaudeClient
            ->shouldReceive('generateStrategicInsights')
            ->once()
            ->withArgs(function ($analysisData) use (&$analysisDataCaptured) {
                $analysisDataCaptured = $analysisData;
                return true;
            })
            ->andReturn([
                'strategies' => [
                    [
                        'id' => 's1',
                        'category' => 'evidence_challenges',
                        'title' => 'Motion to suppress - čl. 10 ZKP',
                        'description' => 'Evidence obtained through illegal search should be excluded',
                        'strength' => 0.9,
                        'legal_basis' => 'ZKP čl. 10 - nezakoniti dokazi ne mogu biti temelj presude',
                    ],
                ],
                'priority_actions' => [],
                'risk_assessment' => [],
                'summary' => 'Strong evidence exclusion argument under Croatian law.',
            ]);

        // Act
        $result = $this->analyzer->analyze($case->id);

        // Assert - verify Croatian legal framework is included in payload
        $this->assertNotNull($analysisDataCaptured);
        $this->assertArrayHasKey('legal_framework', $analysisDataCaptured);
        $this->assertEquals('ZKP (Zakon o kaznenom postupku)', $analysisDataCaptured['legal_framework']['primary_law']);
        $this->assertArrayHasKey('evidence_exclusion', $analysisDataCaptured['legal_framework']);
        $this->assertArrayHasKey('search_warrant', $analysisDataCaptured['legal_framework']);
    }

    /** @test */
    public function it_includes_metadata_with_processing_time(): void
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $doc = CaseDocument::factory()->forCase($case)->create();

        DocumentAnalysis::create([
            'case_document_id' => $doc->id,
            'analysis_type' => DocumentAnalysis::TYPE_SUMMARY,
            'analysis_layer' => DocumentAnalysis::LAYER_AI_BASIC,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'version' => 1,
            'results' => ['summary' => 'Test summary'],
        ]);

        $this->mockClaudeClient
            ->shouldReceive('generateStrategicInsights')
            ->once()
            ->andReturn([
                'strategies' => [],
                'priority_actions' => [],
                'risk_assessment' => [],
                'summary' => '',
            ]);

        // Act
        $result = $this->analyzer->analyze($case->id);

        // Assert
        $this->assertArrayHasKey('metadata', $result);
        $this->assertArrayHasKey('documents_analyzed', $result['metadata']);
        $this->assertArrayHasKey('processing_time_seconds', $result['metadata']);
        $this->assertArrayHasKey('model', $result['metadata']);
        $this->assertEquals(1, $result['metadata']['documents_analyzed']);
    }
}
