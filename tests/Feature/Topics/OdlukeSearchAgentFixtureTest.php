<?php

namespace Tests\Feature\Topics;

use App\Mcp\OdlukeTools;
use App\Modules\HomeSearch\Services\OdlukeSearchAgent;
use App\Services\OpenAIService;
use Mockery;
use Tests\TestCase;

class OdlukeSearchAgentFixtureTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Invariant: when the extraction model returns all required fields with
     * valid enums/formats, the agent must:
     * - validate them,
     * - compute confidence,
     * - and mark extraction_status as complete.
     *
     * Reasoning:
     * This turns "domain usefulness" (home-search abuse analysis) into a
     * deterministic contract using the schema defined in the extraction prompt
     * and validator/partial-extraction handler. 
     */
    public function test_extract_case_data_marks_complete_when_all_required_fields_are_present_and_valid(): void
    {
        $openAI = Mockery::mock(OpenAIService::class);
        $tools = Mockery::mock(OdlukeTools::class)->shouldIgnoreMissing();

        $openAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'case_number' => 'K-123/2024',
                            'court' => 'Općinski sud u Osijeku',
                            'judge' => 'Sudac I. I.',
                            'date' => '2024-03-15',
                            'offense_type' => 'kazneno_djelo',
                            'offense_description' => 'Neovlaštena proizvodnja i promet drogama',
                            'offense_severity' => 'serious',
                            'search_type' => 'pretres doma',
                            'evidence_found' => 'yes',
                            'evidence_suppressed' => 'yes',
                            'legal_violations' => ['ZKP Čl. 215 - Pretres bez valjanog naloga'],
                            'zkp_articles_cited' => ['215', '217'],
                            'proportionality_mentioned' => true,
                            'constitutional_rights_mentioned' => true,
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ]],
            ]);

        $agent = new OdlukeSearchAgent($openAI, $tools);

        $result = $this->callProtectedMethod($agent, 'extractCaseData', [[
            'text' => 'Pretres doma... (fixture text)',
            'url' => 'https://odluke.sudovi.hr/mock/1',
        ]]);

        $this->assertIsArray($result);
        $this->assertEquals('complete', $result['extraction_status']);
        $this->assertArrayHasKey('confidence', $result);
        $this->assertGreaterThanOrEqual(0.7, $result['confidence']);
        $this->assertEquals('K-123/2024', $result['case_number']);
        $this->assertEquals('pretres doma', $result['search_type']);
        $this->assertEquals(['215', '217'], $result['zkp_articles_cited']);
        $this->assertEquals('https://odluke.sudovi.hr/mock/1', $result['source_url']);
        $this->assertArrayHasKey('extraction_date', $result);
    }

    /**
     * Invariant: if critical fields (case_number or court) are missing,
     * extraction must fail (no partial success).
     *
     * Reasoning:
     * Prevents polluted datasets: a record without case_number/court is unusable
     * for dedupe, analytics, or later legal argumentation.
     */
    public function test_extract_case_data_fails_when_critical_fields_missing(): void
    {
        $openAI = Mockery::mock(OpenAIService::class);
        $tools = Mockery::mock(OdlukeTools::class)->shouldIgnoreMissing();

        $openAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            // Missing case_number and court -> should fail in handlePartialExtraction
                            'case_number' => null,
                            'court' => null,
                            'judge' => null,
                            'date' => null,
                            'offense_type' => null,
                            'offense_description' => null,
                            'offense_severity' => null,
                            'search_type' => null,
                            'evidence_found' => null,
                            'evidence_suppressed' => null,
                            'legal_violations' => [],
                            'zkp_articles_cited' => [],
                            'proportionality_mentioned' => false,
                            'constitutional_rights_mentioned' => false,
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ]],
            ]);

        $agent = new OdlukeSearchAgent($openAI, $tools);

        $result = $this->callProtectedMethod($agent, 'extractCaseData', [[
            'text' => 'Nepovezan tekst.',
            'url' => 'https://odluke.sudovi.hr/mock/2',
        ]]);

        $this->assertIsArray($result);
        $this->assertEquals('failed', $result['extraction_status']);
        $this->assertArrayHasKey('error', $result);
        $this->assertArrayHasKey('partial_data', $result);
    }

    /**
     * Helper to call protected methods for testing (copied pattern used elsewhere).
     */
    protected function callProtectedMethod($object, $method, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $m = $reflection->getMethod($method);
        $m->setAccessible(true);

        return $m->invokeArgs($object, $parameters);
    }
}
