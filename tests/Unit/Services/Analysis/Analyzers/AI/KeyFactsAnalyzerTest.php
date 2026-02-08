<?php

namespace Tests\Unit\Services\Analysis\Analyzers\AI;

use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Services\Analysis\AI\ClaudeAnalysisService;
use App\Services\Analysis\Analyzers\AI\KeyFactsAnalyzer;
use App\Services\Analysis\Contracts\DocumentAnalyzerInterface;
use Mockery;
use Tests\TestCase;

class KeyFactsAnalyzerTest extends TestCase
{

    private ClaudeAnalysisService $claudeService;
    private KeyFactsAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->claudeService = Mockery::mock(ClaudeAnalysisService::class);
        $this->analyzer = new KeyFactsAnalyzer($this->claudeService);
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
        $this->assertEquals(DocumentAnalysis::TYPE_KEY_FACTS, $this->analyzer->type());
    }

    /** @test */
    public function it_returns_ai_basic_layer(): void
    {
        $this->assertEquals(DocumentAnalysis::LAYER_AI_BASIC, $this->analyzer->layer());
    }

    /** @test */
    public function analyze_calls_claude_with_key_facts_instructions(): void
    {
        $document = Mockery::mock(CaseDocument::class);

        $promptChecked = false;
        $this->claudeService->shouldReceive('analyzeJson')
            ->once()
            ->withArgs(function ($systemPrompt, $userContent) use (&$promptChecked) {
                $promptChecked = str_contains($systemPrompt, 'factual claims')
                    && str_contains($systemPrompt, 'source')
                    && str_contains($systemPrompt, 'contradiction');
                return $promptChecked;
            })
            ->andReturn([
                'parsed' => ['facts' => []],
                'model' => 'claude-sonnet-4-5-20250929',
                'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
                'cost' => 0.001,
                'processing_time' => 1.5,
            ]);

        $this->analyzer->analyze($document, 'Some document text');

        $this->assertTrue($promptChecked, 'System prompt should contain key facts instructions');
    }

    /** @test */
    public function analyze_returns_results_and_metadata(): void
    {
        $document = Mockery::mock(CaseDocument::class);

        $this->claudeService->shouldReceive('analyzeJson')
            ->once()
            ->andReturn([
                'parsed' => [
                    'facts' => [
                        [
                            'id' => 'F1',
                            'statement' => 'Optuzenik je bio na mjestu zlocina 15. sijecnja 2024.',
                            'source' => 'Svjedok A, stranica 5',
                            'category' => 'location',
                            'certainty' => 'claimed',
                            'date_referenced' => '2024-01-15',
                        ],
                        [
                            'id' => 'F2',
                            'statement' => 'Optuzenik je imao alibi za 15. sijecnja 2024.',
                            'source' => 'Obrana, stranica 12',
                            'category' => 'alibi',
                            'certainty' => 'claimed',
                            'date_referenced' => '2024-01-15',
                        ],
                    ],
                    'fact_count' => 2,
                    'categories' => ['location', 'alibi'],
                    'potential_contradictions' => [
                        ['fact_ids' => ['F1', 'F2'], 'reason' => 'Location vs alibi conflict'],
                    ],
                ],
                'model' => 'claude-sonnet-4-5-20250929',
                'usage' => ['input_tokens' => 500, 'output_tokens' => 200],
                'cost' => 0.0045,
                'processing_time' => 2.5,
            ]);

        $result = $this->analyzer->analyze($document, 'Document text');

        // Check results structure
        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('facts', $result['results']);
        $this->assertCount(2, $result['results']['facts']);
        $this->assertEquals('F1', $result['results']['facts'][0]['id']);
        $this->assertArrayHasKey('statement', $result['results']['facts'][0]);
        $this->assertArrayHasKey('source', $result['results']['facts'][0]);
        $this->assertArrayHasKey('category', $result['results']['facts'][0]);

        // Check metadata structure
        $this->assertArrayHasKey('metadata', $result);
        $this->assertEquals('claude-sonnet-4-5-20250929', $result['metadata']['model']);
        $this->assertEquals(500, $result['metadata']['input_tokens']);
        $this->assertEquals(200, $result['metadata']['output_tokens']);
        $this->assertEquals(0.0045, $result['metadata']['cost_usd']);
        $this->assertEquals('KeyFactsAnalyzer', $result['metadata']['analyzer']);
        $this->assertEquals(1, $result['metadata']['api_calls']);
    }

    /** @test */
    public function analyze_truncates_very_long_documents(): void
    {
        $document = Mockery::mock(CaseDocument::class);

        $truncationChecked = false;
        $this->claudeService->shouldReceive('analyzeJson')
            ->once()
            ->withArgs(function ($systemPrompt, $userContent) use (&$truncationChecked) {
                $truncationChecked = mb_strlen($userContent) < 160000
                    && str_contains($userContent, '[DOCUMENT TRUNCATED]');
                return $truncationChecked;
            })
            ->andReturn([
                'parsed' => ['facts' => []],
                'model' => 'claude-sonnet-4-5-20250929',
                'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
                'cost' => 0.001,
                'processing_time' => 1.0,
            ]);

        $longText = str_repeat('a', 200000);
        $this->analyzer->analyze($document, $longText);

        $this->assertTrue($truncationChecked, 'Long documents should be truncated');
    }

    /** @test */
    public function system_prompt_specifies_expected_json_structure(): void
    {
        $document = Mockery::mock(CaseDocument::class);

        $structureChecked = false;
        $this->claudeService->shouldReceive('analyzeJson')
            ->once()
            ->withArgs(function ($systemPrompt, $userContent) use (&$structureChecked) {
                $structureChecked = str_contains($systemPrompt, '"facts"')
                    && str_contains($systemPrompt, '"id"')
                    && str_contains($systemPrompt, '"statement"')
                    && str_contains($systemPrompt, '"source"');
                return $structureChecked;
            })
            ->andReturn([
                'parsed' => ['facts' => []],
                'model' => 'claude-sonnet-4-5-20250929',
                'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
                'cost' => 0.001,
                'processing_time' => 1.0,
            ]);

        $this->analyzer->analyze($document, 'Document text');

        $this->assertTrue($structureChecked, 'System prompt should specify expected JSON structure');
    }

    /** @test */
    public function system_prompt_mentions_contradiction_detection(): void
    {
        $document = Mockery::mock(CaseDocument::class);

        $contradictionChecked = false;
        $this->claudeService->shouldReceive('analyzeJson')
            ->once()
            ->withArgs(function ($systemPrompt, $userContent) use (&$contradictionChecked) {
                $contradictionChecked = str_contains($systemPrompt, 'contradiction')
                    || str_contains($systemPrompt, 'potential_contradictions');
                return $contradictionChecked;
            })
            ->andReturn([
                'parsed' => ['facts' => []],
                'model' => 'claude-sonnet-4-5-20250929',
                'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
                'cost' => 0.001,
                'processing_time' => 1.0,
            ]);

        $this->analyzer->analyze($document, 'Document text');

        $this->assertTrue($contradictionChecked, 'System prompt should mention contradiction detection');
    }
}
