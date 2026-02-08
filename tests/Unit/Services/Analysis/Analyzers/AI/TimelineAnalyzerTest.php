<?php

namespace Tests\Unit\Services\Analysis\Analyzers\AI;

use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Services\Analysis\AI\ClaudeAnalysisService;
use App\Services\Analysis\Analyzers\AI\TimelineAnalyzer;
use App\Services\Analysis\Contracts\DocumentAnalyzerInterface;
use Mockery;
use Tests\TestCase;

class TimelineAnalyzerTest extends TestCase
{

    private ClaudeAnalysisService $claudeService;
    private TimelineAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->claudeService = Mockery::mock(ClaudeAnalysisService::class);
        $this->analyzer = new TimelineAnalyzer($this->claudeService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_implements_document_analyzer_interface(): void
    {
        $this->assertInstanceOf(DocumentAnalyzerInterface::class, $this->analyzer);
    }

    /** @test */
    public function it_returns_correct_type(): void
    {
        $this->assertEquals(DocumentAnalysis::TYPE_TIMELINE, $this->analyzer->type());
    }

    /** @test */
    public function it_returns_ai_basic_layer(): void
    {
        $this->assertEquals(DocumentAnalysis::LAYER_AI_BASIC, $this->analyzer->layer());
    }

    /** @test */
    public function analyze_calls_claude_with_system_prompt_containing_timeline_instructions(): void
    {
        $document = Mockery::mock(CaseDocument::class);
        $document->shouldReceive('latestAnalysis')
            ->with(DocumentAnalysis::TYPE_DATES)
            ->andReturn(null);
        $document->shouldReceive('latestAnalysis')
            ->with(DocumentAnalysis::TYPE_ENTITIES)
            ->andReturn(null);

        $systemPromptChecked = false;
        $this->claudeService->shouldReceive('analyzeJson')
            ->once()
            ->withArgs(function ($systemPrompt, $userContent) use (&$systemPromptChecked) {
                $systemPromptChecked = str_contains($systemPrompt, 'chronological timeline')
                    && str_contains($systemPrompt, 'Croatian legal document');
                return $systemPromptChecked;
            })
            ->andReturn([
                'parsed' => ['events' => []],
                'model' => 'claude-sonnet-4-5-20250929',
                'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
                'cost' => 0.001,
                'processing_time' => 1.5,
            ]);

        $this->analyzer->analyze($document, 'Some document text');

        $this->assertTrue($systemPromptChecked, 'System prompt should contain timeline instructions');
    }

    /** @test */
    public function analyze_includes_previously_extracted_dates_in_context(): void
    {
        $dateAnalysis = new DocumentAnalysis([
            'results' => [
                'dates' => [
                    ['date' => '2024-01-15', 'context' => 'Filing date'],
                    ['date' => '2024-02-20', 'context' => 'Hearing date'],
                ]
            ]
        ]);

        $document = Mockery::mock(CaseDocument::class);
        $document->shouldReceive('latestAnalysis')
            ->with(DocumentAnalysis::TYPE_DATES)
            ->andReturn($dateAnalysis);
        $document->shouldReceive('latestAnalysis')
            ->with(DocumentAnalysis::TYPE_ENTITIES)
            ->andReturn(null);

        $contextChecked = false;
        $this->claudeService->shouldReceive('analyzeJson')
            ->once()
            ->withArgs(function ($systemPrompt, $userContent) use (&$contextChecked) {
                $contextChecked = str_contains($userContent, '2024-01-15')
                    && str_contains($userContent, '2024-02-20')
                    && str_contains($userContent, 'PREVIOUSLY EXTRACTED DATA');
                return $contextChecked;
            })
            ->andReturn([
                'parsed' => ['events' => []],
                'model' => 'claude-sonnet-4-5-20250929',
                'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
                'cost' => 0.001,
                'processing_time' => 1.5,
            ]);

        $this->analyzer->analyze($document, 'Document text');

        $this->assertTrue($contextChecked, 'User content should include previously extracted dates');
    }

    /** @test */
    public function analyze_includes_previously_extracted_entities_in_context(): void
    {
        $entityAnalysis = new DocumentAnalysis([
            'results' => [
                'entities' => [
                    ['name' => 'Ivan Horvat', 'type' => 'person'],
                    ['name' => 'Trgovacki sud Zagreb', 'type' => 'organization'],
                ]
            ]
        ]);

        $document = Mockery::mock(CaseDocument::class);
        $document->shouldReceive('latestAnalysis')
            ->with(DocumentAnalysis::TYPE_DATES)
            ->andReturn(null);
        $document->shouldReceive('latestAnalysis')
            ->with(DocumentAnalysis::TYPE_ENTITIES)
            ->andReturn($entityAnalysis);

        $entitiesChecked = false;
        $this->claudeService->shouldReceive('analyzeJson')
            ->once()
            ->withArgs(function ($systemPrompt, $userContent) use (&$entitiesChecked) {
                $entitiesChecked = str_contains($userContent, 'Ivan Horvat')
                    && str_contains($userContent, 'Trgovacki sud Zagreb')
                    && str_contains($userContent, 'Entities');
                return $entitiesChecked;
            })
            ->andReturn([
                'parsed' => ['events' => []],
                'model' => 'claude-sonnet-4-5-20250929',
                'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
                'cost' => 0.001,
                'processing_time' => 1.5,
            ]);

        $this->analyzer->analyze($document, 'Document text');

        $this->assertTrue($entitiesChecked, 'User content should include previously extracted entities');
    }

    /** @test */
    public function analyze_returns_results_and_metadata(): void
    {
        $document = Mockery::mock(CaseDocument::class);
        $document->shouldReceive('latestAnalysis')->andReturn(null);

        $this->claudeService->shouldReceive('analyzeJson')
            ->once()
            ->andReturn([
                'parsed' => [
                    'events' => [
                        ['date' => '2024-01-15', 'description' => 'Filing', 'significance' => 'high'],
                    ],
                    'narrative_summary' => 'Case began with filing in January 2024.',
                    'key_periods' => [],
                    'gaps' => [],
                ],
                'model' => 'claude-sonnet-4-5-20250929',
                'usage' => ['input_tokens' => 500, 'output_tokens' => 200],
                'cost' => 0.0045,
                'processing_time' => 2.5,
            ]);

        $result = $this->analyzer->analyze($document, 'Document text');

        // Check results structure
        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('events', $result['results']);
        $this->assertArrayHasKey('narrative_summary', $result['results']);
        $this->assertEquals('2024-01-15', $result['results']['events'][0]['date']);

        // Check metadata structure
        $this->assertArrayHasKey('metadata', $result);
        $this->assertEquals('claude-sonnet-4-5-20250929', $result['metadata']['model']);
        $this->assertEquals(500, $result['metadata']['input_tokens']);
        $this->assertEquals(200, $result['metadata']['output_tokens']);
        $this->assertEquals(0.0045, $result['metadata']['cost_usd']);
        $this->assertEquals(2.5, $result['metadata']['processing_time_seconds']);
        $this->assertEquals('TimelineAnalyzer', $result['metadata']['analyzer']);
        $this->assertEquals(1, $result['metadata']['api_calls']);
    }

    /** @test */
    public function analyze_truncates_very_long_documents(): void
    {
        $document = Mockery::mock(CaseDocument::class);
        $document->shouldReceive('latestAnalysis')->andReturn(null);

        $truncationChecked = false;
        $this->claudeService->shouldReceive('analyzeJson')
            ->once()
            ->withArgs(function ($systemPrompt, $userContent) use (&$truncationChecked) {
                // Should be truncated to ~150000 chars plus truncation notice
                $truncationChecked = mb_strlen($userContent) < 160000
                    && str_contains($userContent, '[DOCUMENT TRUNCATED]');
                return $truncationChecked;
            })
            ->andReturn([
                'parsed' => ['events' => []],
                'model' => 'claude-sonnet-4-5-20250929',
                'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
                'cost' => 0.001,
                'processing_time' => 1.0,
            ]);

        // Create a very long document text (200k chars)
        $longText = str_repeat('a', 200000);

        $this->analyzer->analyze($document, $longText);

        $this->assertTrue($truncationChecked, 'Long documents should be truncated');
    }

    /** @test */
    public function system_prompt_specifies_expected_json_structure(): void
    {
        $document = Mockery::mock(CaseDocument::class);
        $document->shouldReceive('latestAnalysis')->andReturn(null);

        $structureChecked = false;
        $this->claudeService->shouldReceive('analyzeJson')
            ->once()
            ->withArgs(function ($systemPrompt, $userContent) use (&$structureChecked) {
                $structureChecked = str_contains($systemPrompt, '"events"')
                    && str_contains($systemPrompt, '"narrative_summary"')
                    && str_contains($systemPrompt, '"key_periods"')
                    && str_contains($systemPrompt, '"gaps"')
                    && str_contains($systemPrompt, 'date')
                    && str_contains($systemPrompt, 'description')
                    && str_contains($systemPrompt, 'actors')
                    && str_contains($systemPrompt, 'significance');
                return $structureChecked;
            })
            ->andReturn([
                'parsed' => ['events' => []],
                'model' => 'claude-sonnet-4-5-20250929',
                'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
                'cost' => 0.001,
                'processing_time' => 1.0,
            ]);

        $this->analyzer->analyze($document, 'Document text');

        $this->assertTrue($structureChecked, 'System prompt should specify expected JSON structure');
    }

    /** @test */
    public function system_prompt_includes_date_format_requirements(): void
    {
        $document = Mockery::mock(CaseDocument::class);
        $document->shouldReceive('latestAnalysis')->andReturn(null);

        $dateFormatChecked = false;
        $this->claudeService->shouldReceive('analyzeJson')
            ->once()
            ->withArgs(function ($systemPrompt, $userContent) use (&$dateFormatChecked) {
                $dateFormatChecked = str_contains($systemPrompt, 'ISO date')
                    && str_contains($systemPrompt, 'YYYY-MM-DD');
                return $dateFormatChecked;
            })
            ->andReturn([
                'parsed' => ['events' => []],
                'model' => 'claude-sonnet-4-5-20250929',
                'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
                'cost' => 0.001,
                'processing_time' => 1.0,
            ]);

        $this->analyzer->analyze($document, 'Document text');

        $this->assertTrue($dateFormatChecked, 'System prompt should include date format requirements');
    }
}
