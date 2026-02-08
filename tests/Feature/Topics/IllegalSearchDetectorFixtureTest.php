<?php

namespace Tests\Feature\Topics;

use App\Models\LegalCase;
use App\Modules\HomeSearch\Services\OdlukeSearchAgent;
use App\Modules\Topics\Analyzers\IllegalSearchDetector;
use App\Services\OpenAIService;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Deterministic "domain" tests for IllegalSearchDetector.
 *
 * Two layers:
 *  1) Pure deterministic case analysis (no AI): analyzeCase() should detect patterns and compute severity.
 *  2) AI extraction contract (still deterministic via mock OpenAI): extractSearchInfo() must parse the expected JSON schema.
 */
class IllegalSearchDetectorFixtureTest extends TestCase
{
    use UsesTestDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_analyze_case_detects_serious_illegal_search_patterns(): void
    {
        $openai = Mockery::mock(OpenAIService::class);
        $odluke = Mockery::mock(OdlukeSearchAgent::class);

        $detector = new IllegalSearchDetector($openai, $odluke);

        $case = LegalCase::factory()->create();

        $result = $detector->analyzeCase($case, [
            'has_warrant' => true,
            'search_type' => 'home',
            'warrant_defects' => ['vague description'],
            'force_used' => [],
            'exigency_details' => [],
            'scope_violations' => ['searched beyond authorization'],
        ]);

        $this->assertEquals($case->id, $result['case_id']);
        $this->assertTrue($result['violation_detected']);
        $this->assertGreaterThanOrEqual(60, $result['violation_severity']);
        $this->assertNotEmpty($result['violation_patterns']);
        $this->assertNotEmpty($result['defense_strategy']);
        $this->assertNotEmpty($result['legal_violations']);
    }

    public function test_extract_search_info_parses_ai_json_schema_fixture(): void
    {
        $openai = Mockery::mock(OpenAIService::class);
        $odluke = Mockery::mock(OdlukeSearchAgent::class);

        $openai->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'search_type' => 'home',
                            'has_warrant' => true,
                            'violations' => ['warrant_defect', 'scope_violation'],
                            'violation_details' => [
                                'warrant_defects' => ['vague description'],
                                'scope_violations' => ['searched beyond authorization'],
                            ],
                            'confidence' => 'high',
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ]],
            ]);

        $detector = new IllegalSearchDetector($openai, $odluke);

        $case = [
            'case_id' => 'CASE-TEST-1',
            'text' => 'U ovom predmetu proveden je pretres doma temeljem naloga, ali nalog je bio nejasan i pretres je prekoračio ovlasti. ZKP čl. 222.',
        ];

        $searchInfo = $this->callProtectedMethod($detector, 'extractSearchInfo', [$case]);

        $this->assertIsArray($searchInfo);
        $this->assertEquals('home', $searchInfo['search_type']);
        $this->assertTrue($searchInfo['has_warrant']);
        $this->assertContains('warrant_defect', $searchInfo['violations']);
        $this->assertEquals('high', $searchInfo['confidence']);
        $this->assertEquals('CASE-TEST-1', $searchInfo['case_id']);
    }

    public function test_extract_search_info_returns_null_when_text_missing(): void
    {
        $openai = Mockery::mock(OpenAIService::class);
        $openai->shouldNotReceive('chat');
        $odluke = Mockery::mock(OdlukeSearchAgent::class);

        $detector = new IllegalSearchDetector($openai, $odluke);

        $case = ['case_id' => 'CASE-EMPTY', 'text' => ''];

        $searchInfo = $this->callProtectedMethod($detector, 'extractSearchInfo', [$case]);
        $this->assertNull($searchInfo);
    }

    /**
     * Helper to call protected methods for testing (copied from existing agent tests).
     */
    protected function callProtectedMethod(object $object, string $method, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $m = $reflection->getMethod($method);
        $m->setAccessible(true);

        return $m->invokeArgs($object, $parameters);
    }
}
