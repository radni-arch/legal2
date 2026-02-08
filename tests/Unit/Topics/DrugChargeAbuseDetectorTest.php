<?php

namespace Tests\Unit\Topics;

use App\Models\LegalCase;
use App\Modules\HomeSearch\Services\OdlukeSearchAgent;
use App\Modules\Topics\Analyzers\DrugChargeAbuseDetector;
use App\Services\OpenAIService;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * DrugChargeAbuseDetectorTest
 *
 * Comprehensive unit tests for drug charge overcharging detection.
 *
 * Tests:
 * - Threshold analysis (30g cannabis, 1g cocaine, etc.)
 * - Overcharging pattern detection
 * - Severity calculation
 * - Defense strategy generation
 * - AI extraction from court decisions
 * - Regional comparison logic
 */
class DrugChargeAbuseDetectorTest extends TestCase
{
    use UsesTestDatabase;

    protected DrugChargeAbuseDetector $detector;

    protected $mockOpenAI;

    protected $mockOdlukeAgent;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock dependencies
        $this->mockOpenAI = Mockery::mock(OpenAIService::class);
        $this->mockOdlukeAgent = Mockery::mock(OdlukeSearchAgent::class);

        $this->detector = new DrugChargeAbuseDetector(
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
    public function it_detects_overcharging_for_30g_cannabis()
    {
        $case = LegalCase::factory()->create();

        $result = $this->detector->analyzeCase($case, [
            'drug_type' => 'cannabis',
            'amount' => 30,
            'charged_as' => 'dealing',
            'evidence_of_dealing' => [],
        ]);

        $this->assertTrue($result['overcharge_detected']);
        $this->assertGreaterThanOrEqual(60, $result['overcharge_severity']);
        $this->assertEquals('KZ Čl. 173', $result['recommended_charge']);
        $this->assertEquals('cannabis', $result['drug_type']);
        $this->assertEquals(30, $result['amount']);
    }

    /** @test */
    public function it_does_not_detect_overcharging_for_personal_use_charge()
    {
        $case = LegalCase::factory()->create();

        $result = $this->detector->analyzeCase($case, [
            'drug_type' => 'cannabis',
            'amount' => 30,
            'charged_as' => 'personal_use',
            'evidence_of_dealing' => [],
        ]);

        $this->assertFalse($result['overcharge_detected']);
        $this->assertEquals(0, $result['overcharge_severity']);
    }

    /** @test */
    public function it_analyzes_cannabis_threshold_correctly()
    {
        $case = LegalCase::factory()->create();

        // Test within threshold (30g)
        $result = $this->detector->analyzeCase($case, [
            'drug_type' => 'cannabis',
            'amount' => 30,
            'charged_as' => 'dealing',
            'evidence_of_dealing' => [],
        ]);

        $threshold = $result['threshold_analysis'];

        $this->assertTrue($threshold['threshold_found']);
        $this->assertEquals(30, $threshold['threshold_amount']);
        $this->assertTrue($threshold['within_threshold']);
        $this->assertTrue($threshold['personal_use_likely']);
        $this->assertEquals(100, $threshold['percentage_of_threshold']);
    }

    /** @test */
    public function it_analyzes_cocaine_threshold_correctly()
    {
        $case = LegalCase::factory()->create();

        // Test within threshold (1g)
        $result = $this->detector->analyzeCase($case, [
            'drug_type' => 'cocaine',
            'amount' => 0.8,
            'charged_as' => 'dealing',
            'evidence_of_dealing' => [],
        ]);

        $threshold = $result['threshold_analysis'];

        $this->assertTrue($threshold['threshold_found']);
        $this->assertEquals(1, $threshold['threshold_amount']);
        $this->assertTrue($threshold['within_threshold']);
        $this->assertEquals(80, $threshold['percentage_of_threshold']);
    }

    /** @test */
    public function it_detects_minimal_amount_overcharging_pattern()
    {
        $case = LegalCase::factory()->create();

        // 10g cannabis = only 33% of threshold (30g) = minimal amount
        $result = $this->detector->analyzeCase($case, [
            'drug_type' => 'cannabis',
            'amount' => 10,
            'charged_as' => 'dealing',
            'evidence_of_dealing' => [],
        ]);

        $patterns = $result['overcharging_patterns'];
        $patternTypes = array_column($patterns, 'type');

        $this->assertContains('minimal_amount_charged_as_dealing', $patternTypes);

        // Find the pattern
        $minimalPattern = collect($patterns)
            ->firstWhere('type', 'minimal_amount_charged_as_dealing');

        $this->assertEquals(90, $minimalPattern['severity']);
        $this->assertGreaterThanOrEqual(90, $result['overcharge_severity']);
    }

    /** @test */
    public function it_detects_personal_use_charged_as_dealing_pattern()
    {
        $case = LegalCase::factory()->create();

        $result = $this->detector->analyzeCase($case, [
            'drug_type' => 'cannabis',
            'amount' => 30,
            'charged_as' => 'dealing',
            'evidence_of_dealing' => [],
        ]);

        $patterns = $result['overcharging_patterns'];
        $patternTypes = array_column($patterns, 'type');

        $this->assertContains('personal_use_charged_as_dealing', $patternTypes);

        $pattern = collect($patterns)
            ->firstWhere('type', 'personal_use_charged_as_dealing');

        $this->assertEquals(85, $pattern['severity']);
        $this->assertStringContainsString('KZ Čl. 190 inappropriate', $pattern['legal_basis']);
    }

    /** @test */
    public function it_detects_no_dealing_evidence_pattern()
    {
        $case = LegalCase::factory()->create();

        $result = $this->detector->analyzeCase($case, [
            'drug_type' => 'cannabis',
            'amount' => 30,
            'charged_as' => 'dealing',
            'evidence_of_dealing' => [], // No evidence!
        ]);

        $patterns = $result['overcharging_patterns'];
        $patternTypes = array_column($patterns, 'type');

        $this->assertContains('no_dealing_evidence', $patternTypes);

        $pattern = collect($patterns)
            ->firstWhere('type', 'no_dealing_evidence');

        $this->assertEquals(80, $pattern['severity']);
    }

    /** @test */
    public function it_does_not_flag_dealing_charge_with_evidence()
    {
        $case = LegalCase::factory()->create();

        $result = $this->detector->analyzeCase($case, [
            'drug_type' => 'cannabis',
            'amount' => 100, // Above threshold
            'charged_as' => 'dealing',
            'evidence_of_dealing' => ['scales', 'baggies', 'large_cash'], // Evidence present
        ]);

        $this->assertFalse($result['overcharge_detected']);
    }

    /** @test */
    public function it_generates_motion_to_reduce_charges_strategy()
    {
        $case = LegalCase::factory()->create();

        $result = $this->detector->analyzeCase($case, [
            'drug_type' => 'cannabis',
            'amount' => 30,
            'charged_as' => 'dealing',
            'evidence_of_dealing' => [],
        ]);

        $strategies = $result['defense_strategy'];
        $strategyTypes = array_column($strategies, 'strategy');

        $this->assertContains('motion_to_reduce_charges', $strategyTypes);

        $motion = collect($strategies)
            ->firstWhere('strategy', 'motion_to_reduce_charges');

        $this->assertEquals('high', $motion['priority']);
        $this->assertStringContainsString('Prijedlog za promjenu kvalifikacije', $motion['title']);
        $this->assertStringContainsString('KZ Čl. 173', $motion['title']);
    }

    /** @test */
    public function it_generates_regional_comparison_strategy()
    {
        $case = LegalCase::factory()->create();

        $result = $this->detector->analyzeCase($case, [
            'drug_type' => 'cannabis',
            'amount' => 30,
            'charged_as' => 'dealing',
            'evidence_of_dealing' => [],
        ]);

        $strategies = $result['defense_strategy'];
        $strategyTypes = array_column($strategies, 'strategy');

        $this->assertContains('regional_comparison', $strategyTypes);

        $strategy = collect($strategies)
            ->firstWhere('strategy', 'regional_comparison');

        $this->assertEquals('medium', $strategy['priority']);
        $this->assertStringContainsString('Statistički dokaz', $strategy['title']);
    }

    /** @test */
    public function it_calculates_severity_correctly_for_very_small_amount()
    {
        $case = LegalCase::factory()->create();

        // 5g cannabis = < 25% of threshold = +30 points
        $result = $this->detector->analyzeCase($case, [
            'drug_type' => 'cannabis',
            'amount' => 5,
            'charged_as' => 'dealing',
            'evidence_of_dealing' => [],
        ]);

        // Base: 60 + Very small amount: 30 + No evidence: 10 = 100
        $this->assertEquals(100, $result['overcharge_severity']);
    }

    /** @test */
    public function it_extracts_drug_info_from_court_decision_using_ai()
    {
        $caseData = [
            'text' => 'Optuženi je zatečen s 25 grama marihuane. Optužen je za neovlaštenu proizvodnju prema KZ Čl. 190.',
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
                                'drug_type' => 'cannabis',
                                'amount' => 25,
                                'unit' => 'grams',
                                'charged_as' => 'dealing',
                                'evidence_of_dealing' => [],
                                'confidence' => 'high',
                            ]),
                        ],
                    ],
                ],
            ]);

        $reflection = new \ReflectionClass($this->detector);
        $method = $reflection->getMethod('extractDrugInfo');
        $method->setAccessible(true);

        $result = $method->invoke($this->detector, $caseData);

        $this->assertEquals('cannabis', $result['drug_type']);
        $this->assertEquals(25, $result['amount']);
        $this->assertEquals('dealing', $result['charged_as']);
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
        $method = $reflection->getMethod('extractDrugInfo');
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
        $method = $reflection->getMethod('extractDrugInfo');
        $method->setAccessible(true);

        $result = $method->invoke($this->detector, $caseData);

        $this->assertNull($result);
    }

    /** @test */
    public function it_determines_worse_region_correctly()
    {
        $stats1 = ['overcharge_percentage' => 68.1];
        $stats2 = ['overcharge_percentage' => 45.2];

        $reflection = new \ReflectionClass($this->detector);
        $method = $reflection->getMethod('determineWorseRegion');
        $method->setAccessible(true);

        $result = $method->invoke($this->detector, $stats1, $stats2, 'Osijek', 'Zadar');

        $this->assertEquals('Osijek', $result['worse_region']);
        $this->assertEquals(22.9, $result['overcharge_percentage_difference']);
        $this->assertEquals('very_significant', $result['significance']);
    }

    /** @test */
    public function it_categorizes_amount_ranges_correctly()
    {
        $reflection = new \ReflectionClass($this->detector);
        $method = $reflection->getMethod('getAmountRange');
        $method->setAccessible(true);

        $this->assertEquals('0-10g', $method->invoke($this->detector, 5));
        $this->assertEquals('10-30g', $method->invoke($this->detector, 20));
        $this->assertEquals('30-50g', $method->invoke($this->detector, 40));
        $this->assertEquals('50-100g', $method->invoke($this->detector, 75));
        $this->assertEquals('100g+', $method->invoke($this->detector, 150));
    }

    /** @test */
    public function it_generates_alarming_findings_for_high_overcharge_rate()
    {
        $reflection = new \ReflectionClass($this->detector);
        $method = $reflection->getMethod('generateAlarmingFindings');
        $method->setAccessible(true);

        // Test >50% rate
        $findings = $method->invoke($this->detector, 68.1, 47);
        $this->assertCount(1, $findings);
        $this->assertStringContainsString('68.1%', $findings[0]);
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
            'drug_type' => 'cannabis',
            'amount' => 30,
            'charged_as' => 'dealing',
            'evidence_of_dealing' => [],
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
        $this->assertEquals('drug_charge_severity', $this->detector->getTopicName());
        $this->assertStringContainsString('Overcharging in drug cases', $this->detector->getTopicDescription());

        $framework = $this->detector->getLegalFramework();
        $this->assertArrayHasKey('KZ_Čl_190', $framework);
        $this->assertArrayHasKey('KZ_Čl_173', $framework);
        $this->assertArrayHasKey('ZKP_Čl_179', $framework);
    }

    /** @test */
    public function it_returns_correct_search_keywords()
    {
        $reflection = new \ReflectionClass($this->detector);
        $method = $reflection->getMethod('getSearchKeywords');
        $method->setAccessible(true);

        $keywords = $method->invoke($this->detector);

        $this->assertContains('neovlaštena proizvodnja', $keywords);
        $this->assertContains('promet opojnim drogama', $keywords);
        $this->assertContains('trgovanje drogom', $keywords);
        $this->assertContains('KZ 190', $keywords);
        $this->assertContains('KZ 173', $keywords);
    }

    /** @test */
    public function it_handles_unknown_drug_type_gracefully()
    {
        $case = LegalCase::factory()->create();

        $result = $this->detector->analyzeCase($case, [
            'drug_type' => 'unknown_drug',
            'amount' => 30,
            'charged_as' => 'dealing',
            'evidence_of_dealing' => [],
        ]);

        $threshold = $result['threshold_analysis'];

        $this->assertFalse($threshold['threshold_found']);
        $this->assertNull($threshold['personal_use_likely']);
        $this->assertStringContainsString('No established threshold', $threshold['analysis']);
    }

    /** @test */
    public function it_normalizes_drug_type_names()
    {
        $case = LegalCase::factory()->create();

        // Test "marihuana" (Croatian) is recognized same as "cannabis"
        $result1 = $this->detector->analyzeCase($case, [
            'drug_type' => 'marihuana',
            'amount' => 30,
            'charged_as' => 'dealing',
            'evidence_of_dealing' => [],
        ]);

        $result2 = $this->detector->analyzeCase($case, [
            'drug_type' => 'cannabis',
            'amount' => 30,
            'charged_as' => 'dealing',
            'evidence_of_dealing' => [],
        ]);

        $this->assertEquals(
            $result1['threshold_analysis']['threshold_amount'],
            $result2['threshold_analysis']['threshold_amount']
        );
    }
}
