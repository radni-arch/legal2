<?php

namespace Tests\Unit\Topics;

use App\Models\LegalCase;
use App\Modules\HomeSearch\Services\OdlukeSearchAgent;
use App\Modules\Topics\Analyzers\ExcessivePretensionDetector;
use App\Services\OpenAIService;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * ExcessivePretensionDetectorTest
 *
 * Comprehensive unit tests for excessive pretrial detention violation detection.
 *
 * Tests:
 * - Duration limit compliance (6 months general, 12 months serious)
 * - Justification requirement checking
 * - Procedural violation detection (monthly reviews)
 * - Proportionality analysis
 * - Severity calculation
 * - Defense strategy generation
 */
class ExcessivePretensionDetectorTest extends TestCase
{
    use UsesTestDatabase;

    protected ExcessivePretensionDetector $detector;

    protected $mockOpenAI;

    protected $mockOdlukeAgent;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock dependencies
        $this->mockOpenAI = Mockery::mock(OpenAIService::class);
        $this->mockOdlukeAgent = Mockery::mock(OdlukeSearchAgent::class);

        $this->detector = new ExcessivePretensionDetector(
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
    public function it_detects_excessive_duration_violation_for_general_offense()
    {
        $case = LegalCase::factory()->create();

        // 14 months detention for general offense (limit: 12 months)
        $result = $this->detector->analyzeCase($case, [
            'detention_months' => 14,
            'offense_severity' => 'general',
            'justification_provided' => true,
            'justification_text' => 'Defendant poses flight risk due to previous attempts to flee.',
            'monthly_reviews_conducted' => 13,
            'detention_grounds' => ['flight_risk'],
            'offense_max_penalty_years' => 5,
        ]);

        $this->assertTrue($result['violation_detected']);
        $this->assertGreaterThanOrEqual(60, $result['violation_severity']);
        $this->assertEquals(14, $result['detention_months']);
        $this->assertEquals(12, $result['applicable_limit_months']);
        $this->assertTrue($result['duration_analysis']['exceeds_limit']);
        $this->assertEquals(2, $result['duration_analysis']['excess_months']);

        // Check violation pattern
        $patternTypes = array_column($result['violation_patterns'], 'type');
        $this->assertContains('excessive_duration', $patternTypes);
    }

    /** @test */
    public function it_detects_excessive_duration_violation_for_serious_offense()
    {
        $case = LegalCase::factory()->create();

        // 26 months detention for serious offense (limit: 24 months)
        $result = $this->detector->analyzeCase($case, [
            'detention_months' => 26,
            'offense_severity' => 'serious',
            'justification_provided' => true,
            'justification_text' => 'Serious crime with evidence destruction risk.',
            'monthly_reviews_conducted' => 25,
            'detention_grounds' => ['evidence_destruction'],
            'offense_max_penalty_years' => 10,
        ]);

        $this->assertTrue($result['violation_detected']);
        $this->assertEquals(26, $result['detention_months']);
        $this->assertEquals(24, $result['applicable_limit_months']);
        $this->assertTrue($result['duration_analysis']['exceeds_limit']);
        $this->assertEquals(2, $result['duration_analysis']['excess_months']);
    }

    /** @test */
    public function it_does_not_detect_violation_when_within_limits()
    {
        $case = LegalCase::factory()->create();

        // 10 months detention for general offense (limit: 12 months) - OK
        $result = $this->detector->analyzeCase($case, [
            'detention_months' => 10,
            'offense_severity' => 'general',
            'justification_provided' => true,
            'justification_text' => 'Properly justified detention with specific reasoning.',
            'monthly_reviews_conducted' => 9,
            'detention_grounds' => ['flight_risk'],
            'offense_max_penalty_years' => 5,
        ]);

        $this->assertFalse($result['violation_detected']);
        $this->assertLessThan(60, $result['violation_severity']);
        $this->assertFalse($result['duration_analysis']['exceeds_limit']);
    }

    /** @test */
    public function it_detects_no_justification_violation()
    {
        $case = LegalCase::factory()->create();

        // Within duration limit but no justification provided
        $result = $this->detector->analyzeCase($case, [
            'detention_months' => 8,
            'offense_severity' => 'general',
            'justification_provided' => false, // No justification!
            'justification_text' => '',
            'monthly_reviews_conducted' => 7,
            'detention_grounds' => ['flight_risk'],
            'offense_max_penalty_years' => 5,
        ]);

        $patternTypes = array_column($result['violation_patterns'], 'type');
        $this->assertContains('no_justification', $patternTypes);

        $pattern = collect($result['violation_patterns'])
            ->firstWhere('type', 'no_justification');

        $this->assertEquals(85, $pattern['severity']);
        $this->assertStringContainsString('ZKP Čl. 122 violation', $pattern['legal_basis']);
    }

    /** @test */
    public function it_detects_generic_justification_as_violation()
    {
        $case = LegalCase::factory()->create();

        // Justification too short/generic (< 50 characters)
        $result = $this->detector->analyzeCase($case, [
            'detention_months' => 8,
            'offense_severity' => 'general',
            'justification_provided' => true,
            'justification_text' => 'Flight risk.', // Only 12 characters - too generic
            'monthly_reviews_conducted' => 7,
            'detention_grounds' => ['flight_risk'],
            'offense_max_penalty_years' => 5,
        ]);

        $patternTypes = array_column($result['violation_patterns'], 'type');
        $this->assertContains('no_justification', $patternTypes);
    }

    /** @test */
    public function it_detects_procedural_violations_for_missing_monthly_reviews()
    {
        $case = LegalCase::factory()->create();

        // 12 months detention but only 5 reviews (expected: 11)
        $result = $this->detector->analyzeCase($case, [
            'detention_months' => 12,
            'offense_severity' => 'general',
            'justification_provided' => true,
            'justification_text' => 'Properly justified detention with specific reasoning.',
            'monthly_reviews_conducted' => 5, // Missing 6 reviews!
            'detention_grounds' => ['flight_risk'],
            'offense_max_penalty_years' => 5,
        ]);

        $patternTypes = array_column($result['violation_patterns'], 'type');
        $this->assertContains('procedural_violations', $patternTypes);

        $pattern = collect($result['violation_patterns'])
            ->firstWhere('type', 'procedural_violations');

        $this->assertGreaterThanOrEqual(75, $pattern['severity']);
        $this->assertStringContainsString('ZKP Čl. 123 violation', $pattern['legal_basis']);
        $this->assertStringContainsString('monthly review', $pattern['description']);
    }

    /** @test */
    public function it_detects_proportionality_violation()
    {
        $case = LegalCase::factory()->create();

        // 30 months detention for offense with max 5 years penalty
        // 30 months = 2.5 years = 50% of max penalty
        $result = $this->detector->analyzeCase($case, [
            'detention_months' => 30,
            'offense_severity' => 'general',
            'justification_provided' => true,
            'justification_text' => 'Properly justified detention with specific reasoning.',
            'monthly_reviews_conducted' => 29,
            'detention_grounds' => ['flight_risk'],
            'offense_max_penalty_years' => 5, // Max 5 years = 60 months
        ]);

        $patternTypes = array_column($result['violation_patterns'], 'type');
        $this->assertContains('proportionality_violation', $patternTypes);

        $pattern = collect($result['violation_patterns'])
            ->firstWhere('type', 'proportionality_violation');

        $this->assertEquals(90, $pattern['severity']);
        $this->assertStringContainsString('ECHR Article 5', $pattern['legal_basis']);
        $this->assertStringContainsString('disproportionate', $pattern['description']);
    }

    /** @test */
    public function it_calculates_severity_correctly_for_severe_violations()
    {
        $case = LegalCase::factory()->create();

        // 20 months over limit (8 months excess) + no justification + missed reviews
        $result = $this->detector->analyzeCase($case, [
            'detention_months' => 20,
            'offense_severity' => 'general', // Limit: 12 months
            'justification_provided' => false,
            'justification_text' => '',
            'monthly_reviews_conducted' => 10, // Expected: 19
            'detention_grounds' => [],
            'offense_max_penalty_years' => 5,
        ]);

        // Base: 60 + Significantly over (6+ months): 30 + No justification: 15 + Missed reviews: ~10 = ~100+
        $this->assertEquals(100, $result['violation_severity']); // Capped at 100
    }

    /** @test */
    public function it_generates_immediate_release_motion_for_severe_violations()
    {
        $case = LegalCase::factory()->create();

        // 18 months for general offense (6 months over limit)
        $result = $this->detector->analyzeCase($case, [
            'detention_months' => 18,
            'offense_severity' => 'general',
            'justification_provided' => true,
            'justification_text' => 'Properly justified detention with specific reasoning.',
            'monthly_reviews_conducted' => 17,
            'detention_grounds' => ['flight_risk'],
            'offense_max_penalty_years' => 5,
        ]);

        $strategies = $result['defense_strategy'];
        $strategyTypes = array_column($strategies, 'strategy');

        $this->assertContains('immediate_release_motion', $strategyTypes);

        $motion = collect($strategies)
            ->firstWhere('strategy', 'immediate_release_motion');

        $this->assertEquals('critical', $motion['priority']);
        $this->assertStringContainsString('hitno ukidanje pritvora', $motion['title']);
        $this->assertStringContainsString('ZKP Čl. 123', $motion['legal_basis']);
    }

    /** @test */
    public function it_generates_echr_violation_strategy_for_high_severity()
    {
        $case = LegalCase::factory()->create();

        // 15 months for general offense
        $result = $this->detector->analyzeCase($case, [
            'detention_months' => 15,
            'offense_severity' => 'general',
            'justification_provided' => false,
            'justification_text' => '',
            'monthly_reviews_conducted' => 14,
            'detention_grounds' => [],
            'offense_max_penalty_years' => 5,
        ]);

        $strategies = $result['defense_strategy'];
        $strategyTypes = array_column($strategies, 'strategy');

        $this->assertContains('echr_violation', $strategyTypes);

        $strategy = collect($strategies)
            ->firstWhere('strategy', 'echr_violation');

        $this->assertEquals('high', $strategy['priority']);
        $this->assertStringContainsString('ECHR Article 5', $strategy['title']);
    }

    /** @test */
    public function it_generates_constitutional_complaint_strategy_when_limit_exceeded()
    {
        $case = LegalCase::factory()->create();

        // Any amount over limit triggers constitutional complaint
        $result = $this->detector->analyzeCase($case, [
            'detention_months' => 13,
            'offense_severity' => 'general', // Limit: 12
            'justification_provided' => true,
            'justification_text' => 'Properly justified detention with specific reasoning.',
            'monthly_reviews_conducted' => 12,
            'detention_grounds' => ['flight_risk'],
            'offense_max_penalty_years' => 5,
        ]);

        $strategies = $result['defense_strategy'];
        $strategyTypes = array_column($strategies, 'strategy');

        $this->assertContains('constitutional_complaint', $strategyTypes);

        $strategy = collect($strategies)
            ->firstWhere('strategy', 'constitutional_complaint');

        $this->assertEquals('medium', $strategy['priority']);
        $this->assertStringContainsString('Ustavna tužba', $strategy['title']);
        $this->assertStringContainsString('Ustav RH Čl. 24', $strategy['legal_basis']);
    }

    /** @test */
    public function it_extracts_detention_info_from_court_decision_using_ai()
    {
        $caseData = [
            'text' => 'Okrivljenik je u pritvoru 15 mjeseci. Kazneno djelo je teže djelo s kaznom do 10 godina.',
            'case_id' => 'Kv-123/2025',
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
                                'detention_months' => 15,
                                'offense_severity' => 'serious',
                                'justification_provided' => true,
                                'monthly_reviews_conducted' => 14,
                                'offense_max_penalty_years' => 10,
                                'confidence' => 'high',
                            ]),
                        ],
                    ],
                ],
            ]);

        $reflection = new \ReflectionClass($this->detector);
        $method = $reflection->getMethod('extractDetentionInfo');
        $method->setAccessible(true);

        $result = $method->invoke($this->detector, $caseData);

        $this->assertEquals(15, $result['detention_months']);
        $this->assertEquals('serious', $result['offense_severity']);
        $this->assertTrue($result['justification_provided']);
        $this->assertEquals(14, $result['monthly_reviews_conducted']);
        $this->assertEquals(10, $result['offense_max_penalty_years']);
        $this->assertEquals('Kv-123/2025', $result['case_id']);
    }

    /** @test */
    public function it_handles_invalid_json_from_ai_extraction()
    {
        $caseData = [
            'text' => 'Some court decision text',
            'case_id' => 'Kv-123/2025',
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
        $method = $reflection->getMethod('extractDetentionInfo');
        $method->setAccessible(true);

        $result = $method->invoke($this->detector, $caseData);

        $this->assertNull($result);
    }

    /** @test */
    public function it_categorizes_duration_ranges_correctly()
    {
        $reflection = new \ReflectionClass($this->detector);
        $method = $reflection->getMethod('getDurationRange');
        $method->setAccessible(true);

        $this->assertEquals('0-3 months', $method->invoke($this->detector, 2));
        $this->assertEquals('3-6 months', $method->invoke($this->detector, 5));
        $this->assertEquals('6-12 months', $method->invoke($this->detector, 10));
        $this->assertEquals('12-24 months', $method->invoke($this->detector, 18));
        $this->assertEquals('24+ months', $method->invoke($this->detector, 30));
    }

    /** @test */
    public function it_generates_alarming_findings_for_high_violation_rate()
    {
        $reflection = new \ReflectionClass($this->detector);
        $method = $reflection->getMethod('generateAlarmingFindings');
        $method->setAccessible(true);

        // Test >50% violation rate
        $findings = $method->invoke($this->detector, 65.5, 10.2);
        $this->assertNotEmpty($findings);
        $this->assertStringContainsString('65.5%', $findings[0]);
        $this->assertStringContainsString('sistemski problem', $findings[0]);

        // Test high average detention
        $findings = $method->invoke($this->detector, 25.0, 15.5);
        $this->assertNotEmpty($findings);
        $this->assertCount(2, $findings); // Both violation rate and average duration alarming
    }

    /** @test */
    public function it_determines_worse_region_correctly()
    {
        $stats1 = ['violation_percentage' => 72.3];
        $stats2 = ['violation_percentage' => 48.1];

        $reflection = new \ReflectionClass($this->detector);
        $method = $reflection->getMethod('determineWorseRegion');
        $method->setAccessible(true);

        $result = $method->invoke($this->detector, $stats1, $stats2, 'Osijek', 'Zadar');

        $this->assertEquals('Osijek', $result['worse_region']);
        $this->assertEquals(24.2, $result['violation_percentage_difference']);
        $this->assertEquals('very_significant', $result['significance']);
    }

    /** @test */
    public function it_identifies_legal_violations_from_patterns()
    {
        $case = LegalCase::factory()->create();

        // Multiple violations
        $result = $this->detector->analyzeCase($case, [
            'detention_months' => 18,
            'offense_severity' => 'general',
            'justification_provided' => false,
            'justification_text' => '',
            'monthly_reviews_conducted' => 10,
            'detention_grounds' => [],
            'offense_max_penalty_years' => 5,
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

        // Should have multiple violation types
        $violationTypes = array_column($violations, 'pattern_type');
        $this->assertContains('excessive_duration', $violationTypes);
        $this->assertContains('no_justification', $violationTypes);
        $this->assertContains('procedural_violations', $violationTypes);
    }

    /** @test */
    public function it_has_correct_topic_metadata()
    {
        $this->assertEquals('excessive_pretrial_detention', $this->detector->getTopicName());
        $this->assertStringContainsString('Excessive pretrial detention', $this->detector->getTopicDescription());

        $framework = $this->detector->getLegalFramework();
        $this->assertArrayHasKey('ZKP_Čl_122', $framework);
        $this->assertArrayHasKey('ZKP_Čl_123', $framework);
        $this->assertArrayHasKey('Ustav_RH_Čl_24', $framework);
        $this->assertArrayHasKey('ECHR_Article_5', $framework);
    }

    /** @test */
    public function it_returns_correct_search_keywords()
    {
        $reflection = new \ReflectionClass($this->detector);
        $method = $reflection->getMethod('getSearchKeywords');
        $method->setAccessible(true);

        $keywords = $method->invoke($this->detector);

        $this->assertContains('pritvor', $keywords);
        $this->assertContains('pritvaranje', $keywords);
        $this->assertContains('ukidanje pritvora', $keywords);
        $this->assertContains('ZKP 122', $keywords);
        $this->assertContains('ZKP 123', $keywords);
    }

    /** @test */
    public function it_handles_missing_decision_text_gracefully()
    {
        $caseData = [
            'case_id' => 'Kv-123/2025',
            // No 'text', 'content', or 'decision_text' field
        ];

        $reflection = new \ReflectionClass($this->detector);
        $method = $reflection->getMethod('extractDetentionInfo');
        $method->setAccessible(true);

        $result = $method->invoke($this->detector, $caseData);

        $this->assertNull($result);
    }
}
