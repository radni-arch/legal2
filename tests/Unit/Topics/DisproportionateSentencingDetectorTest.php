<?php

namespace Tests\Unit\Topics;

use App\Models\LegalCase;
use App\Modules\HomeSearch\Services\OdlukeSearchAgent;
use App\Modules\Topics\Analyzers\DisproportionateSentencingDetector;
use App\Services\OpenAIService;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * DisproportionateSentencingDetectorTest
 *
 * Comprehensive unit tests for disproportionate sentencing detection.
 *
 * Tests:
 * - Baseline analysis (theft, assault, fraud baselines)
 * - Violation pattern detection (4 types)
 * - Severity calculation
 * - Defense strategy generation
 * - AI extraction from court decisions
 * - Regional comparison logic
 * - Mitigating/aggravating factor consideration
 */
class DisproportionateSentencingDetectorTest extends TestCase
{
    use UsesTestDatabase;

    protected DisproportionateSentencingDetector $detector;

    protected $mockOpenAI;

    protected $mockOdlukeAgent;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock dependencies
        $this->mockOpenAI = Mockery::mock(OpenAIService::class);
        $this->mockOdlukeAgent = Mockery::mock(OdlukeSearchAgent::class);

        $this->detector = new DisproportionateSentencingDetector(
            $this->mockOpenAI,
            $this->mockOdlukeAgent
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_detects_sentence_outlier_for_theft()
    {
        $case = LegalCase::factory()->create();

        // Theft baseline: 3-12 months, avg 6
        // Testing with 18 months (above max)
        $result = $this->detector->analyzeCase($case, [
            'offense_type' => 'theft',
            'sentence_months' => 18,
            'mitigating_factors' => [],
            'aggravating_factors' => [],
        ]);

        $this->assertTrue($result['disproportionate_detected']);
        $this->assertGreaterThanOrEqual(60, $result['severity']);
        $this->assertEquals('theft', $result['offense_type']);
        $this->assertEquals(18, $result['sentence_months']);

        $patterns = array_column($result['violation_patterns'], 'type');
        $this->assertContains('sentence_outlier', $patterns);
    }

    /** @test */
    public function it_does_not_detect_disproportionate_for_normal_sentence()
    {
        $case = LegalCase::factory()->create();

        // Assault baseline: 6-18 months, avg 10
        // Testing with 10 months (average)
        $result = $this->detector->analyzeCase($case, [
            'offense_type' => 'assault',
            'sentence_months' => 10,
            'mitigating_factors' => [],
            'aggravating_factors' => [],
        ]);

        $this->assertFalse($result['disproportionate_detected']);
        $this->assertLessThan(60, $result['severity']);
    }

    /** @test */
    public function it_analyzes_baseline_correctly_for_theft()
    {
        $case = LegalCase::factory()->create();

        $result = $this->detector->analyzeCase($case, [
            'offense_type' => 'theft',
            'sentence_months' => 6,
            'mitigating_factors' => [],
            'aggravating_factors' => [],
        ]);

        $baseline = $result['baseline_analysis'];

        $this->assertTrue($baseline['baseline_found']);
        $this->assertEquals(3, $baseline['baseline_min']);
        $this->assertEquals(12, $baseline['baseline_max']);
        $this->assertEquals(6, $baseline['baseline_average']);
        $this->assertFalse($baseline['is_outlier']);
        $this->assertEquals(100, $baseline['percentage_of_average']);
    }

    /** @test */
    public function it_analyzes_baseline_correctly_for_croatian_terms()
    {
        $case = LegalCase::factory()->create();

        // Test Croatian term "krađa" (theft)
        $result = $this->detector->analyzeCase($case, [
            'offense_type' => 'krađa',
            'sentence_months' => 6,
            'mitigating_factors' => [],
            'aggravating_factors' => [],
        ]);

        $baseline = $result['baseline_analysis'];

        $this->assertTrue($baseline['baseline_found']);
        $this->assertEquals(6, $baseline['baseline_average']);
    }

    /** @test */
    public function it_detects_no_mitigating_consideration_pattern()
    {
        $case = LegalCase::factory()->create();

        // Sentence at average (6 months) despite mitigating factors
        $result = $this->detector->analyzeCase($case, [
            'offense_type' => 'theft',
            'sentence_months' => 6,
            'mitigating_factors' => ['first_offense', 'remorse', 'cooperation'],
            'aggravating_factors' => [],
        ]);

        $patterns = $result['violation_patterns'];
        $patternTypes = array_column($patterns, 'type');

        $this->assertContains('no_mitigating_consideration', $patternTypes);

        $pattern = collect($patterns)
            ->firstWhere('type', 'no_mitigating_consideration');

        $this->assertEquals(80, $pattern['severity']);
        $this->assertStringContainsString('KZ Čl. 46', $pattern['legal_basis']);
    }

    /** @test */
    public function it_detects_improper_aggravation_pattern()
    {
        $case = LegalCase::factory()->create();

        // Sentence far above average (200%) with no aggravating factors
        $result = $this->detector->analyzeCase($case, [
            'offense_type' => 'theft',
            'sentence_months' => 12, // 200% of average (6)
            'mitigating_factors' => [],
            'aggravating_factors' => [], // No aggravating factors!
        ]);

        $patterns = $result['violation_patterns'];
        $patternTypes = array_column($patterns, 'type');

        $this->assertContains('improper_aggravation', $patternTypes);

        $pattern = collect($patterns)
            ->firstWhere('type', 'improper_aggravation');

        $this->assertEquals(90, $pattern['severity']);
    }

    /** @test */
    public function it_detects_comparative_injustice_pattern()
    {
        $case = LegalCase::factory()->create();

        // Sentence 18 months vs regional average of 8 months
        $result = $this->detector->analyzeCase($case, [
            'offense_type' => 'theft',
            'sentence_months' => 18,
            'mitigating_factors' => [],
            'aggravating_factors' => [],
            'regional_average' => 8,
        ]);

        $patterns = $result['violation_patterns'];
        $patternTypes = array_column($patterns, 'type');

        $this->assertContains('comparative_injustice', $patternTypes);

        $pattern = collect($patterns)
            ->firstWhere('type', 'comparative_injustice');

        $this->assertEquals(85, $pattern['severity']);
        $this->assertStringContainsString('ZKP Čl. 528', $pattern['legal_basis']);
    }

    /** @test */
    public function it_calculates_severity_correctly_for_extreme_outlier()
    {
        $case = LegalCase::factory()->create();

        // Theft: baseline avg 6 months
        // Testing with 24 months (400% of average)
        $result = $this->detector->analyzeCase($case, [
            'offense_type' => 'theft',
            'sentence_months' => 24,
            'mitigating_factors' => ['first_offense'],
            'aggravating_factors' => [],
        ]);

        // Base: 60 (outlier) + 30 (>200% avg) + 10 (mitigating ignored) + 10 (no aggravating) = 100+
        $this->assertGreaterThanOrEqual(90, $result['severity']);
    }

    /** @test */
    public function it_generates_appeal_sentence_strategy()
    {
        $case = LegalCase::factory()->create();

        $result = $this->detector->analyzeCase($case, [
            'offense_type' => 'theft',
            'sentence_months' => 18,
            'mitigating_factors' => [],
            'aggravating_factors' => [],
        ]);

        $strategies = $result['defense_strategy'];
        $strategyTypes = array_column($strategies, 'strategy');

        $this->assertContains('appeal_sentence', $strategyTypes);

        $strategy = collect($strategies)
            ->firstWhere('strategy', 'appeal_sentence');

        $this->assertEquals('high', $strategy['priority']);
        $this->assertStringContainsString('Žalba zbog neproporcionalne kazne', $strategy['title']);
        $this->assertStringContainsString('ZKP Čl. 528', $strategy['legal_basis']);
    }

    /** @test */
    public function it_generates_mitigating_factors_argument_strategy()
    {
        $case = LegalCase::factory()->create();

        $result = $this->detector->analyzeCase($case, [
            'offense_type' => 'theft',
            'sentence_months' => 8,
            'mitigating_factors' => ['first_offense', 'remorse'],
            'aggravating_factors' => [],
        ]);

        $strategies = $result['defense_strategy'];
        $strategyTypes = array_column($strategies, 'strategy');

        $this->assertContains('mitigating_factors_argument', $strategyTypes);

        $strategy = collect($strategies)
            ->firstWhere('strategy', 'mitigating_factors_argument');

        $this->assertEquals('high', $strategy['priority']);
        $this->assertStringContainsString('olakotnim okolnostima', $strategy['title']);
    }

    /** @test */
    public function it_generates_regional_disparity_strategy()
    {
        $case = LegalCase::factory()->create();

        $result = $this->detector->analyzeCase($case, [
            'offense_type' => 'theft',
            'sentence_months' => 18,
            'mitigating_factors' => [],
            'aggravating_factors' => [],
        ]);

        $strategies = $result['defense_strategy'];
        $strategyTypes = array_column($strategies, 'strategy');

        $this->assertContains('regional_disparity', $strategyTypes);

        $strategy = collect($strategies)
            ->firstWhere('strategy', 'regional_disparity');

        $this->assertEquals('medium', $strategy['priority']);
    }

    /** @test */
    public function it_provides_recommended_sentence_range()
    {
        $case = LegalCase::factory()->create();

        $result = $this->detector->analyzeCase($case, [
            'offense_type' => 'theft',
            'sentence_months' => 18,
            'mitigating_factors' => ['first_offense'],
            'aggravating_factors' => [],
        ]);

        $recommended = $result['recommended_sentence_range'];

        $this->assertEquals(3, $recommended['min']);
        $this->assertEquals(12, $recommended['max']);
        $this->assertNotNull($recommended['recommended']);
        $this->assertLessThan(6, $recommended['recommended']); // Should be reduced for mitigating factor
        $this->assertStringContainsString('mitigating factor', $recommended['reasoning']);
    }

    /** @test */
    public function it_adjusts_recommended_sentence_for_aggravating_factors()
    {
        $case = LegalCase::factory()->create();

        $result = $this->detector->analyzeCase($case, [
            'offense_type' => 'theft',
            'sentence_months' => 12,
            'mitigating_factors' => [],
            'aggravating_factors' => ['prior_convictions', 'premeditation'],
        ]);

        $recommended = $result['recommended_sentence_range'];

        $this->assertGreaterThan(6, $recommended['recommended']); // Should be increased for aggravating factors
        $this->assertStringContainsString('aggravating factor', $recommended['reasoning']);
    }

    /** @test */
    public function it_extracts_sentencing_info_from_court_decision_using_ai()
    {
        $caseData = [
            'text' => 'Optuženi je osuđen na 18 mjeseci zatvora za krađu. Prvi put je osuđen, iskazao je žaljenje.',
            'case_id' => 'K-123/2025',
        ];

        $this->mockOpenAI
            ->shouldReceive('chat')
            ->once()
            ->with(Mockery::on(function ($messages) {
                return count($messages) === 2 &&
                       $messages[0]['role'] === 'system' &&
                       $messages[1]['role'] === 'user';
            }), 'gpt-4o-mini', ['temperature' => 0.1, 'max_tokens' => 300])
            ->andReturn([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'offense_type' => 'theft',
                                'sentence_months' => 18,
                                'mitigating_factors' => ['first_offense', 'remorse'],
                                'aggravating_factors' => [],
                                'confidence' => 'high',
                            ]),
                        ],
                    ],
                ],
            ]);

        $reflection = new \ReflectionClass($this->detector);
        $method = $reflection->getMethod('extractSentencingInfo');
        $method->setAccessible(true);

        $result = $method->invoke($this->detector, $caseData);

        $this->assertEquals('theft', $result['offense_type']);
        $this->assertEquals(18, $result['sentence_months']);
        $this->assertContains('first_offense', $result['mitigating_factors']);
        $this->assertContains('remorse', $result['mitigating_factors']);
        $this->assertEquals('K-123/2025', $result['case_id']);
    }

    /** @test */
    public function it_handles_invalid_json_from_ai_extraction()
    {
        $caseData = [
            'text' => 'Some court decision text',
            'case_id' => 'K-123/2025',
        ];

        $this->mockOpenAI
            ->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => 'Invalid JSON { broken']],
                ],
            ]);

        $reflection = new \ReflectionClass($this->detector);
        $method = $reflection->getMethod('extractSentencingInfo');
        $method->setAccessible(true);

        $result = $method->invoke($this->detector, $caseData);

        $this->assertNull($result);
    }

    /** @test */
    public function it_returns_null_when_no_decision_text()
    {
        $caseData = [
            'case_id' => 'K-123/2025',
            // No 'text', 'content', or 'decision_text' field
        ];

        $reflection = new \ReflectionClass($this->detector);
        $method = $reflection->getMethod('extractSentencingInfo');
        $method->setAccessible(true);

        $result = $method->invoke($this->detector, $caseData);

        $this->assertNull($result);
    }

    /** @test */
    public function it_determines_worse_region_correctly()
    {
        $stats1 = ['disproportionate_percentage' => 58.3];
        $stats2 = ['disproportionate_percentage' => 32.1];

        $reflection = new \ReflectionClass($this->detector);
        $method = $reflection->getMethod('determineWorseRegion');
        $method->setAccessible(true);

        $result = $method->invoke($this->detector, $stats1, $stats2, 'Osijek', 'Zadar');

        $this->assertEquals('Osijek', $result['worse_region']);
        $this->assertEquals(26.2, $result['disproportionate_percentage_difference']);
        $this->assertEquals('very_significant', $result['significance']);
    }

    /** @test */
    public function it_generates_alarming_findings_for_high_disproportionate_rate()
    {
        $reflection = new \ReflectionClass($this->detector);
        $method = $reflection->getMethod('generateAlarmingFindings');
        $method->setAccessible(true);

        // Test >50% rate
        $findings = $method->invoke($this->detector, 58.3, 47);
        $this->assertCount(1, $findings);
        $this->assertStringContainsString('58.3%', $findings[0]);
        $this->assertStringContainsString('sistemski problem', $findings[0]);

        // Test >30% rate
        $findings = $method->invoke($this->detector, 35.5, 50);
        $this->assertCount(1, $findings);
        $this->assertStringContainsString('35.5%', $findings[0]);

        // Test <30% rate
        $findings = $method->invoke($this->detector, 15.0, 30);
        $this->assertEmpty($findings);
    }

    /** @test */
    public function it_identifies_legal_violations_from_patterns()
    {
        $case = LegalCase::factory()->create();

        $result = $this->detector->analyzeCase($case, [
            'offense_type' => 'theft',
            'sentence_months' => 18,
            'mitigating_factors' => ['first_offense'],
            'aggravating_factors' => [],
        ]);

        $violations = $result['legal_violations'];

        $this->assertNotEmpty($violations);
        $this->assertIsArray($violations);

        foreach ($violations as $violation) {
            $this->assertArrayHasKey('pattern_type', $violation);
            $this->assertArrayHasKey('severity', $violation);
            $this->assertArrayHasKey('legal_violation', $violation);
            $this->assertArrayHasKey('description', $violation);
        }
    }

    /** @test */
    public function it_has_correct_topic_metadata()
    {
        $this->assertEquals('disproportionate_sentencing', $this->detector->getTopicName());
        $this->assertStringContainsString('Disproportionate sentencing', $this->detector->getTopicDescription());

        $framework = $this->detector->getLegalFramework();
        $this->assertArrayHasKey('KZ_Čl_45', $framework);
        $this->assertArrayHasKey('KZ_Čl_46', $framework);
        $this->assertArrayHasKey('ZKP_Čl_528', $framework);
    }

    /** @test */
    public function it_returns_correct_search_keywords()
    {
        $reflection = new \ReflectionClass($this->detector);
        $method = $reflection->getMethod('getSearchKeywords');
        $method->setAccessible(true);

        $keywords = $method->invoke($this->detector);

        $this->assertContains('odmjeravanje kazne', $keywords);
        $this->assertContains('kazna zatvora', $keywords);
        $this->assertContains('olakotne okolnosti', $keywords);
        $this->assertContains('otegotne okolnosti', $keywords);
        $this->assertContains('KZ 45', $keywords);
        $this->assertContains('KZ 46', $keywords);
    }

    /** @test */
    public function it_handles_unknown_offense_type_gracefully()
    {
        $case = LegalCase::factory()->create();

        $result = $this->detector->analyzeCase($case, [
            'offense_type' => 'unknown_offense',
            'sentence_months' => 12,
            'mitigating_factors' => [],
            'aggravating_factors' => [],
        ]);

        $baseline = $result['baseline_analysis'];

        $this->assertFalse($baseline['baseline_found']);
        $this->assertNull($baseline['is_outlier']);
        $this->assertStringContainsString('No established baseline', $baseline['analysis']);
    }

    /** @test */
    public function it_detects_multiple_violation_patterns_simultaneously()
    {
        $case = LegalCase::factory()->create();

        // Extreme case: outlier sentence, mitigating factors ignored, no aggravating factors, regional disparity
        $result = $this->detector->analyzeCase($case, [
            'offense_type' => 'theft',
            'sentence_months' => 24, // Way above max (12)
            'mitigating_factors' => ['first_offense', 'remorse', 'cooperation'],
            'aggravating_factors' => [], // None!
            'regional_average' => 8,
        ]);

        $patterns = $result['violation_patterns'];
        $patternTypes = array_column($patterns, 'type');

        // Should detect all 4 violation types
        $this->assertContains('sentence_outlier', $patternTypes);
        $this->assertContains('no_mitigating_consideration', $patternTypes);
        $this->assertContains('improper_aggravation', $patternTypes);
        $this->assertContains('comparative_injustice', $patternTypes);

        // Severity should be very high
        $this->assertGreaterThanOrEqual(90, $result['severity']);
    }
}
