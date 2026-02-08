<?php

namespace Tests\Unit\Services\Analysis\CaseLevel\AI;

use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Models\LegalCase;
use App\Services\Analysis\CaseLevel\AI\Contracts\ClaudeClientInterface;
use App\Services\Analysis\CaseLevel\AI\ContradictionDetector;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;

class ContradictionDetectorTest extends TestCase
{
    use DatabaseTransactions;

    private ContradictionDetector $detector;
    private $mockClaudeClient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockClaudeClient = Mockery::mock(ClaudeClientInterface::class);
        $this->detector = new ContradictionDetector($this->mockClaudeClient);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_implements_correct_analysis_type(): void
    {
        $this->assertEquals('contradictions', $this->detector->analysisType());
    }

    /** @test */
    public function it_returns_empty_when_no_key_facts_exist(): void
    {
        // Arrange - case with no key_facts analyses
        $case = LegalCase::factory()->create();

        // Act
        $result = $this->detector->analyze($case->id);

        // Assert
        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertEmpty($result['results']['contradictions']);
        $this->assertEquals(0, $result['metadata']['documents_analyzed']);
    }

    /** @test */
    public function it_requires_minimum_two_documents_for_analysis(): void
    {
        // Arrange - create one document with key_facts
        $case = LegalCase::factory()->create();
        $document = CaseDocument::factory()->forCase($case)->create();

        DocumentAnalysis::create([
            'case_document_id' => $document->id,
            'analysis_type' => DocumentAnalysis::TYPE_KEY_FACTS,
            'analysis_layer' => DocumentAnalysis::LAYER_AI_BASIC,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'version' => 1,
            'results' => ['key_facts' => [
                ['fact' => 'Witness saw suspect at 10:00 AM', 'source' => 'witness statement'],
            ]],
        ]);

        // Act
        $result = $this->detector->analyze($case->id);

        // Assert - should skip AI analysis with single doc
        $this->assertEmpty($result['results']['contradictions']);
        $this->assertEquals(1, $result['metadata']['documents_analyzed']);
        $this->assertEquals('insufficient_documents', $result['metadata']['skip_reason'] ?? null);
    }

    /** @test */
    public function it_detects_contradictions_using_claude_api(): void
    {
        // Arrange - create multiple documents with key_facts
        $case = LegalCase::factory()->create();
        $doc1 = CaseDocument::factory()->forCase($case)->create(['title' => 'Witness Statement A']);
        $doc2 = CaseDocument::factory()->forCase($case)->create(['title' => 'Police Report']);

        DocumentAnalysis::create([
            'case_document_id' => $doc1->id,
            'analysis_type' => DocumentAnalysis::TYPE_KEY_FACTS,
            'analysis_layer' => DocumentAnalysis::LAYER_AI_BASIC,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'version' => 1,
            'results' => ['key_facts' => [
                ['fact' => 'Suspect left building at 10:00 AM', 'source' => 'witness statement'],
                ['fact' => 'Witness was at corner of Main and 5th', 'source' => 'witness statement'],
            ]],
        ]);

        DocumentAnalysis::create([
            'case_document_id' => $doc2->id,
            'analysis_type' => DocumentAnalysis::TYPE_KEY_FACTS,
            'analysis_layer' => DocumentAnalysis::LAYER_AI_BASIC,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'version' => 1,
            'results' => ['key_facts' => [
                ['fact' => 'Suspect was in building until 11:30 AM per security footage', 'source' => 'police report'],
                ['fact' => 'Building has one exit', 'source' => 'police report'],
            ]],
        ]);

        // Mock Claude API response with contradictions
        $expectedClaudeResponse = [
            'contradictions' => [
                [
                    'id' => 'contra-001',
                    'fact_a' => [
                        'text' => 'Suspect left building at 10:00 AM',
                        'document_id' => $doc1->id,
                        'document_title' => 'Witness Statement A',
                    ],
                    'fact_b' => [
                        'text' => 'Suspect was in building until 11:30 AM per security footage',
                        'document_id' => $doc2->id,
                        'document_title' => 'Police Report',
                    ],
                    'severity' => 'high',
                    'type' => 'timeline_conflict',
                    'explanation' => 'Direct contradiction in timing of suspect leaving building',
                    'investigation_points' => [
                        'Verify security footage timestamps',
                        'Re-interview witness about time of observation',
                    ],
                ],
            ],
            'summary' => 'Found 1 high-severity contradiction regarding timeline of events.',
        ];

        $this->mockClaudeClient
            ->shouldReceive('analyzeForContradictions')
            ->once()
            ->andReturn($expectedClaudeResponse);

        // Act
        $result = $this->detector->analyze($case->id);

        // Assert
        $this->assertArrayHasKey('results', $result);
        $this->assertCount(1, $result['results']['contradictions']);

        $contradiction = $result['results']['contradictions'][0];
        $this->assertEquals('high', $contradiction['severity']);
        $this->assertEquals('timeline_conflict', $contradiction['type']);
        $this->assertNotEmpty($contradiction['investigation_points']);

        $this->assertEquals(2, $result['metadata']['documents_analyzed']);
        $this->assertArrayHasKey('processing_time_seconds', $result['metadata']);
    }

    /** @test */
    public function it_categorizes_contradictions_by_type(): void
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $doc1 = CaseDocument::factory()->forCase($case)->create(['title' => 'Document 1']);
        $doc2 = CaseDocument::factory()->forCase($case)->create(['title' => 'Document 2']);

        DocumentAnalysis::create([
            'case_document_id' => $doc1->id,
            'analysis_type' => DocumentAnalysis::TYPE_KEY_FACTS,
            'analysis_layer' => DocumentAnalysis::LAYER_AI_BASIC,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'version' => 1,
            'results' => ['key_facts' => [['fact' => 'Fact 1', 'source' => 'doc1']]],
        ]);

        DocumentAnalysis::create([
            'case_document_id' => $doc2->id,
            'analysis_type' => DocumentAnalysis::TYPE_KEY_FACTS,
            'analysis_layer' => DocumentAnalysis::LAYER_AI_BASIC,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'version' => 1,
            'results' => ['key_facts' => [['fact' => 'Fact 2', 'source' => 'doc2']]],
        ]);

        // Mock Claude response with multiple contradiction types
        $this->mockClaudeClient
            ->shouldReceive('analyzeForContradictions')
            ->once()
            ->andReturn([
                'contradictions' => [
                    ['id' => 'c1', 'severity' => 'high', 'type' => 'timeline_conflict', 'fact_a' => [], 'fact_b' => []],
                    ['id' => 'c2', 'severity' => 'medium', 'type' => 'statement_conflict', 'fact_a' => [], 'fact_b' => []],
                    ['id' => 'c3', 'severity' => 'low', 'type' => 'procedural_irregularity', 'fact_a' => [], 'fact_b' => []],
                ],
                'summary' => 'Found 3 contradictions',
            ]);

        // Act
        $result = $this->detector->analyze($case->id);

        // Assert - should have categorization in results
        $this->assertCount(3, $result['results']['contradictions']);
        $this->assertArrayHasKey('by_severity', $result['results']);
        $this->assertArrayHasKey('by_type', $result['results']);

        $this->assertEquals(1, $result['results']['by_severity']['high']);
        $this->assertEquals(1, $result['results']['by_severity']['medium']);
        $this->assertEquals(1, $result['results']['by_severity']['low']);
    }

    /** @test */
    public function it_handles_claude_api_failure_gracefully(): void
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $doc1 = CaseDocument::factory()->forCase($case)->create();
        $doc2 = CaseDocument::factory()->forCase($case)->create();

        foreach ([$doc1, $doc2] as $doc) {
            DocumentAnalysis::create([
                'case_document_id' => $doc->id,
                'analysis_type' => DocumentAnalysis::TYPE_KEY_FACTS,
                'analysis_layer' => DocumentAnalysis::LAYER_AI_BASIC,
                'status' => DocumentAnalysis::STATUS_COMPLETED,
                'version' => 1,
                'results' => ['key_facts' => [['fact' => 'Some fact', 'source' => 'test']]],
            ]);
        }

        // Mock Claude API failure
        $this->mockClaudeClient
            ->shouldReceive('analyzeForContradictions')
            ->once()
            ->andThrow(new \RuntimeException('API rate limit exceeded'));

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('API rate limit exceeded');

        $this->detector->analyze($case->id);
    }

    /** @test */
    public function it_includes_document_metadata_in_contradictions(): void
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $doc1 = CaseDocument::factory()->forCase($case)->create([
            'title' => 'Witness Statement - John Doe',
            'category' => 'witness',
        ]);
        $doc2 = CaseDocument::factory()->forCase($case)->create([
            'title' => 'Police Investigation Report',
            'category' => 'evidence',
        ]);

        DocumentAnalysis::create([
            'case_document_id' => $doc1->id,
            'analysis_type' => DocumentAnalysis::TYPE_KEY_FACTS,
            'analysis_layer' => DocumentAnalysis::LAYER_AI_BASIC,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'version' => 1,
            'results' => ['key_facts' => [['fact' => 'Witness claims X', 'source' => 'statement']]],
        ]);

        DocumentAnalysis::create([
            'case_document_id' => $doc2->id,
            'analysis_type' => DocumentAnalysis::TYPE_KEY_FACTS,
            'analysis_layer' => DocumentAnalysis::LAYER_AI_BASIC,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'version' => 1,
            'results' => ['key_facts' => [['fact' => 'Evidence shows Y', 'source' => 'police']]],
        ]);

        $this->mockClaudeClient
            ->shouldReceive('analyzeForContradictions')
            ->once()
            ->andReturn([
                'contradictions' => [
                    [
                        'id' => 'c1',
                        'fact_a' => ['text' => 'Witness claims X', 'document_id' => $doc1->id],
                        'fact_b' => ['text' => 'Evidence shows Y', 'document_id' => $doc2->id],
                        'severity' => 'high',
                        'type' => 'factual_conflict',
                    ],
                ],
                'summary' => 'Found contradiction',
            ]);

        // Act
        $result = $this->detector->analyze($case->id);

        // Assert
        $this->assertArrayHasKey('document_ids', $result['metadata']);
        // Cast to string for comparison since the implementation converts ULIDs to strings
        $this->assertContains((string) $doc1->id, $result['metadata']['document_ids']);
        $this->assertContains((string) $doc2->id, $result['metadata']['document_ids']);
    }
}
