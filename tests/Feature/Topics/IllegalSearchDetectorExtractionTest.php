<?php

namespace Tests\Feature\Topics;

use App\Modules\HomeSearch\Services\OdlukeSearchAgent;
use App\Modules\Topics\Analyzers\IllegalSearchDetector;
use App\Services\OpenAIService;
use Mockery;
use Tests\TestCase;

/**
 * Domain-useful fixture tests for IllegalSearchDetector's AI extraction step.
 *
 * Reasoning:
 * - analyzeCase() is purely deterministic and easy to test.
 * - The "hard" part is extractSearchInfo(): it parses LLM JSON into a structured schema.
 * - This test makes that schema deterministic by mocking OpenAIService->chat().
 */
class IllegalSearchDetectorExtractionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_extracts_structured_illegal_search_facets_from_decision_text_fixture(): void
    {
        $openai = Mockery::mock(OpenAIService::class);
        $odlukeAgent = Mockery::mock(OdlukeSearchAgent::class);

        $openai->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => "```json\n".json_encode([
                        'search_type' => 'HOME',
                        'has_warrant' => true,
                        'violations' => ['warrant_defect', 'scope_violation'],
                        'violation_details' => [
                            'warrant_defects' => ['nejasan opis', 'pogrešna adresa'],
                            'scope_violations' => ['pretraga izvan ovlasti'],
                        ],
                        'confidence' => 'high',
                    ], JSON_UNESCAPED_UNICODE)."\n```" ]],
                ],
            ]);

        // Expose protected method for testing
        $detector = new class($openai, $odlukeAgent) extends IllegalSearchDetector {
            public function publicExtractSearchInfo(array $case): ?array
            {
                return $this->extractSearchInfo($case);
            }
        };

        $case = [
            'case_id' => 'K-TEST-001/2024',
            'decision_text' => 'U provedenom postupku utvrđeno je da je pretres doma izvršen temeljem naloga s nejasnim opisom...'
                .' Pretraga je proširena izvan ovlasti, te su pronađeni predmeti koji nisu navedeni u nalogu.',
        ];

        $result = $detector->publicExtractSearchInfo($case);

        $this->assertIsArray($result);
        $this->assertEquals('home', $result['search_type']);
        $this->assertTrue($result['has_warrant']);
        $this->assertContains('warrant_defect', $result['violations']);
        $this->assertContains('scope_violation', $result['violations']);
        $this->assertEquals('high', $result['confidence']);
        $this->assertEquals('K-TEST-001/2024', $result['case_id']);
        $this->assertNotEmpty($result['violation_details']);
    }

    /** @test */
    public function it_returns_null_when_llm_output_is_not_json(): void
    {
        $openai = Mockery::mock(OpenAIService::class);
        $odlukeAgent = Mockery::mock(OdlukeSearchAgent::class);

        $openai->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => 'not json at all']],
                ],
            ]);

        $detector = new class($openai, $odlukeAgent) extends IllegalSearchDetector {
            public function publicExtractSearchInfo(array $case): ?array
            {
                return $this->extractSearchInfo($case);
            }
        };

        $result = $detector->publicExtractSearchInfo([
            'case_id' => 'K-TEST-002/2024',
            'decision_text' => 'pretres doma ...',
        ]);

        $this->assertNull($result);
    }

    /** @test */
    public function it_returns_null_when_required_fields_missing(): void
    {
        $openai = Mockery::mock(OpenAIService::class);
        $odlukeAgent = Mockery::mock(OdlukeSearchAgent::class);

        // Missing search_type and has_warrant
        $openai->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'violations' => ['warrant_defect'],
                        'confidence' => 'low',
                    ])]],
                ],
            ]);

        $detector = new class($openai, $odlukeAgent) extends IllegalSearchDetector {
            public function publicExtractSearchInfo(array $case): ?array
            {
                return $this->extractSearchInfo($case);
            }
        };

        $result = $detector->publicExtractSearchInfo([
            'case_id' => 'K-TEST-003/2024',
            'decision_text' => 'pretres doma ...',
        ]);

        $this->assertNull($result);
    }
}
