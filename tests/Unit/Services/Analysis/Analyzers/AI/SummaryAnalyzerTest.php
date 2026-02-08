<?php

namespace Tests\Unit\Services\Analysis\Analyzers\AI;

use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Services\Analysis\AI\ClaudeAnalysisService;
use App\Services\Analysis\Analyzers\AI\SummaryAnalyzer;
use App\Services\Analysis\Contracts\DocumentAnalyzerInterface;
use Mockery;
use Tests\TestCase;

class SummaryAnalyzerTest extends TestCase
{

    private ClaudeAnalysisService $claudeService;
    private SummaryAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->claudeService = Mockery::mock(ClaudeAnalysisService::class);
        $this->analyzer = new SummaryAnalyzer($this->claudeService);
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
        $this->assertEquals(DocumentAnalysis::TYPE_SUMMARY, $this->analyzer->type());
    }

    /** @test */
    public function it_returns_ai_basic_layer(): void
    {
        $this->assertEquals(DocumentAnalysis::LAYER_AI_BASIC, $this->analyzer->layer());
    }

    /** @test */
    public function analyze_calls_claude_with_summary_instructions(): void
    {
        $document = Mockery::mock(CaseDocument::class);

        $promptChecked = false;
        $this->claudeService->shouldReceive('analyzeJson')
            ->once()
            ->withArgs(function ($systemPrompt, $userContent) use (&$promptChecked) {
                $promptChecked = str_contains($systemPrompt, 'summary')
                    && str_contains($systemPrompt, 'parties')
                    && str_contains($systemPrompt, 'subject_matter')
                    && str_contains($systemPrompt, 'procedural_posture')
                    && str_contains($systemPrompt, 'key_arguments')
                    && str_contains($systemPrompt, 'ruling');
                return $promptChecked;
            })
            ->andReturn([
                'parsed' => [
                    'parties' => [],
                    'subject_matter' => '',
                    'procedural_posture' => '',
                    'key_arguments' => [],
                    'ruling' => null,
                ],
                'model' => 'claude-sonnet-4-5-20250929',
                'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
                'cost' => 0.001,
                'processing_time' => 1.5,
            ]);

        $this->analyzer->analyze($document, 'Some document text');

        $this->assertTrue($promptChecked, 'System prompt should contain summary instructions');
    }

    /** @test */
    public function analyze_returns_results_and_metadata(): void
    {
        $document = Mockery::mock(CaseDocument::class);

        $this->claudeService->shouldReceive('analyzeJson')
            ->once()
            ->andReturn([
                'parsed' => [
                    'parties' => [
                        'plaintiff' => 'Republika Hrvatska',
                        'defendant' => 'Ivan Horvat',
                    ],
                    'subject_matter' => 'Kazneno djelo prijevare',
                    'procedural_posture' => 'Prvostupanjska presuda',
                    'key_arguments' => [
                        ['party' => 'prosecution', 'argument' => 'Dokaz A'],
                        ['party' => 'defense', 'argument' => 'Alibi B'],
                    ],
                    'ruling' => [
                        'outcome' => 'guilty',
                        'sentence' => '2 godine zatvora',
                    ],
                    'one_paragraph_summary' => 'Optuzenik Ivan Horvat proglasen je krivim...',
                ],
                'model' => 'claude-sonnet-4-5-20250929',
                'usage' => ['input_tokens' => 500, 'output_tokens' => 200],
                'cost' => 0.0045,
                'processing_time' => 2.5,
            ]);

        $result = $this->analyzer->analyze($document, 'Document text');

        // Check results structure
        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('parties', $result['results']);
        $this->assertArrayHasKey('subject_matter', $result['results']);
        $this->assertArrayHasKey('procedural_posture', $result['results']);
        $this->assertArrayHasKey('key_arguments', $result['results']);
        $this->assertArrayHasKey('ruling', $result['results']);

        // Check metadata structure
        $this->assertArrayHasKey('metadata', $result);
        $this->assertEquals('claude-sonnet-4-5-20250929', $result['metadata']['model']);
        $this->assertEquals(500, $result['metadata']['input_tokens']);
        $this->assertEquals(200, $result['metadata']['output_tokens']);
        $this->assertEquals(0.0045, $result['metadata']['cost_usd']);
        $this->assertEquals('SummaryAnalyzer', $result['metadata']['analyzer']);
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
                'parsed' => ['parties' => []],
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
    public function system_prompt_specifies_croatian_legal_context(): void
    {
        $document = Mockery::mock(CaseDocument::class);

        $croatianChecked = false;
        $this->claudeService->shouldReceive('analyzeJson')
            ->once()
            ->withArgs(function ($systemPrompt, $userContent) use (&$croatianChecked) {
                $croatianChecked = str_contains($systemPrompt, 'Croatian')
                    || str_contains($systemPrompt, 'hrvatski');
                return $croatianChecked;
            })
            ->andReturn([
                'parsed' => ['parties' => []],
                'model' => 'claude-sonnet-4-5-20250929',
                'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
                'cost' => 0.001,
                'processing_time' => 1.0,
            ]);

        $this->analyzer->analyze($document, 'Document text');

        $this->assertTrue($croatianChecked, 'System prompt should specify Croatian legal context');
    }
}
