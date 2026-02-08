<?php

namespace Tests\Unit\Services\Analysis\CaseLevel\AI;

use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Models\LegalCase;
use App\Services\Analysis\CaseLevel\AI\Contracts\ClaudeClientInterface;
use App\Services\Analysis\CaseLevel\AI\GapAnalyzer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;

class GapAnalyzerTest extends TestCase
{
    use DatabaseTransactions;

    private GapAnalyzer $analyzer;
    private $mockClaudeClient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockClaudeClient = Mockery::mock(ClaudeClientInterface::class);
        $this->analyzer = new GapAnalyzer($this->mockClaudeClient);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_implements_correct_analysis_type(): void
    {
        $this->assertEquals('gaps', $this->analyzer->analysisType());
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
    }

    /** @test */
    public function it_identifies_missing_documents_using_claude_api(): void
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $doc1 = CaseDocument::factory()->forCase($case)->create([
            'title' => 'Police Report',
            'category' => 'evidence',
        ]);
        $doc2 = CaseDocument::factory()->forCase($case)->create([
            'title' => 'Indictment',
            'category' => 'pleading',
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
            'gaps' => [
                [
                    'id' => 'gap-001',
                    'type' => 'missing_document',
                    'description' => 'Search warrant missing - police report references warrant but it is not in file',
                    'severity' => 'high',
                    'expected_document' => 'Search Warrant',
                    'evidence' => 'Police report references warrant number KLASA: 511-01/24-01/123',
                    'legal_significance' => 'Cannot verify legality of search without warrant document',
                ],
                [
                    'id' => 'gap-002',
                    'type' => 'missing_document',
                    'description' => 'Witness statements referenced in indictment not present',
                    'severity' => 'medium',
                    'expected_document' => 'Witness Statements',
                    'evidence' => 'Indictment references 3 witnesses but no statements found',
                    'legal_significance' => 'Cannot assess witness credibility without statements',
                ],
            ],
            'timeline_gaps' => [],
            'procedural_gaps' => [],
            'summary' => 'Critical gap: Search warrant missing. Medium priority: Witness statements missing.',
        ];

        $this->mockClaudeClient
            ->shouldReceive('analyzeForGaps')
            ->once()
            ->andReturn($expectedClaudeResponse);

        // Act
        $result = $this->analyzer->analyze($case->id);

        // Assert
        $this->assertCount(2, $result['results']['gaps']);
        $this->assertEquals('missing_document', $result['results']['gaps'][0]['type']);
        $this->assertEquals('high', $result['results']['gaps'][0]['severity']);
        $this->assertArrayHasKey('legal_significance', $result['results']['gaps'][0]);
    }

    /** @test */
    public function it_identifies_timeline_gaps(): void
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $doc = CaseDocument::factory()->forCase($case)->create();

        // Create date analysis
        DocumentAnalysis::create([
            'case_document_id' => $doc->id,
            'analysis_type' => DocumentAnalysis::TYPE_DATES,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'version' => 1,
            'results' => ['dates' => [
                ['date' => '2024-01-15', 'context' => 'Arrest date'],
                ['date' => '2024-03-01', 'context' => 'Indictment date'],
            ]],
        ]);

        // Mock Claude response with timeline gaps
        $this->mockClaudeClient
            ->shouldReceive('analyzeForGaps')
            ->once()
            ->andReturn([
                'gaps' => [],
                'timeline_gaps' => [
                    [
                        'id' => 'tgap-001',
                        'type' => 'timeline_gap',
                        'period_start' => '2024-01-16',
                        'period_end' => '2024-02-28',
                        'description' => '44-day gap between arrest and indictment with no documentation',
                        'severity' => 'medium',
                        'expected_events' => ['Interrogation records', 'Detention extension orders'],
                        'legal_significance' => 'Under Croatian law (ZKP art. 123), detention must be reviewed every 30 days',
                    ],
                ],
                'procedural_gaps' => [],
                'summary' => 'Timeline gap of 44 days between arrest and indictment.',
            ]);

        // Act
        $result = $this->analyzer->analyze($case->id);

        // Assert
        $this->assertCount(1, $result['results']['timeline_gaps']);
        $this->assertEquals('2024-01-16', $result['results']['timeline_gaps'][0]['period_start']);
        $this->assertEquals('2024-02-28', $result['results']['timeline_gaps'][0]['period_end']);
    }

    /** @test */
    public function it_identifies_procedural_gaps(): void
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $doc = CaseDocument::factory()->forCase($case)->create([
            'title' => 'Home Search Report',
            'category' => 'evidence',
        ]);

        DocumentAnalysis::create([
            'case_document_id' => $doc->id,
            'analysis_type' => DocumentAnalysis::TYPE_SUMMARY,
            'analysis_layer' => DocumentAnalysis::LAYER_AI_BASIC,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'version' => 1,
            'results' => ['summary' => 'Home search conducted at defendant address'],
        ]);

        // Mock Claude response with procedural gaps
        $this->mockClaudeClient
            ->shouldReceive('analyzeForGaps')
            ->once()
            ->andReturn([
                'gaps' => [],
                'timeline_gaps' => [],
                'procedural_gaps' => [
                    [
                        'id' => 'pgap-001',
                        'type' => 'procedural_gap',
                        'description' => 'No evidence of defendant notification before search',
                        'severity' => 'high',
                        'missing_procedure' => 'Defendant notification under ZKP art. 240',
                        'legal_basis' => 'ZKP art. 240 requires notification before home search unless exigent circumstances',
                        'defense_opportunity' => 'Potential motion to suppress evidence from illegal search',
                    ],
                ],
                'summary' => 'Procedural gap: Missing defendant notification for home search.',
            ]);

        // Act
        $result = $this->analyzer->analyze($case->id);

        // Assert
        $this->assertCount(1, $result['results']['procedural_gaps']);
        $this->assertEquals('high', $result['results']['procedural_gaps'][0]['severity']);
        $this->assertArrayHasKey('defense_opportunity', $result['results']['procedural_gaps'][0]);
    }

    /** @test */
    public function it_gathers_all_analysis_types_for_context(): void
    {
        // Arrange - create multiple analysis types
        $case = LegalCase::factory()->create();
        $doc = CaseDocument::factory()->forCase($case)->create();

        // Create various analyses
        DocumentAnalysis::create([
            'case_document_id' => $doc->id,
            'analysis_type' => DocumentAnalysis::TYPE_KEY_FACTS,
            'analysis_layer' => DocumentAnalysis::LAYER_AI_BASIC,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'version' => 1,
            'results' => ['key_facts' => [['fact' => 'Key fact 1']]],
        ]);

        DocumentAnalysis::create([
            'case_document_id' => $doc->id,
            'analysis_type' => DocumentAnalysis::TYPE_DATES,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'version' => 1,
            'results' => ['dates' => [['date' => '2024-01-01']]],
        ]);

        $caseDataCaptured = null;
        $this->mockClaudeClient
            ->shouldReceive('analyzeForGaps')
            ->once()
            ->withArgs(function ($caseData) use (&$caseDataCaptured) {
                $caseDataCaptured = $caseData;
                return true;
            })
            ->andReturn([
                'gaps' => [],
                'timeline_gaps' => [],
                'procedural_gaps' => [],
                'summary' => 'No gaps found.',
            ]);

        // Act
        $result = $this->analyzer->analyze($case->id);

        // Assert - verify all analysis types were gathered
        $this->assertNotNull($caseDataCaptured);
        $this->assertArrayHasKey('documents', $caseDataCaptured);
        $this->assertArrayHasKey('analyses', $caseDataCaptured);
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
            ->shouldReceive('analyzeForGaps')
            ->once()
            ->andThrow(new \RuntimeException('API connection failed'));

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('API connection failed');

        $this->analyzer->analyze($case->id);
    }

    /** @test */
    public function it_categorizes_gaps_by_severity(): void
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

        // Mock response with various severities
        $this->mockClaudeClient
            ->shouldReceive('analyzeForGaps')
            ->once()
            ->andReturn([
                'gaps' => [
                    ['id' => 'g1', 'type' => 'missing_document', 'severity' => 'high'],
                    ['id' => 'g2', 'type' => 'missing_document', 'severity' => 'high'],
                    ['id' => 'g3', 'type' => 'missing_document', 'severity' => 'medium'],
                ],
                'timeline_gaps' => [
                    ['id' => 'tg1', 'severity' => 'low'],
                ],
                'procedural_gaps' => [
                    ['id' => 'pg1', 'severity' => 'high'],
                ],
                'summary' => 'Multiple gaps found.',
            ]);

        // Act
        $result = $this->analyzer->analyze($case->id);

        // Assert
        $this->assertArrayHasKey('by_severity', $result['results']);
        $this->assertEquals(3, $result['results']['by_severity']['high']);
        $this->assertEquals(1, $result['results']['by_severity']['medium']);
        $this->assertEquals(1, $result['results']['by_severity']['low']);
    }

    /** @test */
    public function it_includes_croatian_legal_context(): void
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $doc = CaseDocument::factory()->forCase($case)->create([
            'title' => 'Rjesenje o pritvoru',
            'category' => 'court_order',
        ]);

        DocumentAnalysis::create([
            'case_document_id' => $doc->id,
            'analysis_type' => DocumentAnalysis::TYPE_SUMMARY,
            'analysis_layer' => DocumentAnalysis::LAYER_AI_BASIC,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'version' => 1,
            'results' => ['summary' => 'Detention order issued by investigating judge'],
        ]);

        $this->mockClaudeClient
            ->shouldReceive('analyzeForGaps')
            ->once()
            ->andReturn([
                'gaps' => [
                    [
                        'id' => 'g1',
                        'type' => 'missing_document',
                        'description' => 'Zalba na rjesenje o pritvoru (detention appeal) not found',
                        'severity' => 'medium',
                        'legal_basis' => 'ZKP cl. 131 - right to appeal detention',
                        'defense_opportunity' => 'Verify if appeal was filed within 48-hour deadline',
                    ],
                ],
                'timeline_gaps' => [],
                'procedural_gaps' => [],
                'summary' => 'Missing detention appeal documentation.',
            ]);

        // Act
        $result = $this->analyzer->analyze($case->id);

        // Assert - verify Croatian legal context is included
        $this->assertArrayHasKey('legal_basis', $result['results']['gaps'][0]);
        $this->assertStringContainsString('ZKP', $result['results']['gaps'][0]['legal_basis']);
    }
}
