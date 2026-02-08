<?php

namespace Tests\Unit\Topics;

use App\Models\LegalCase;
use App\Modules\HomeSearch\Services\OdlukeSearchAgent;
use App\Modules\Topics\Analyzers\IllegalSearchDetector;
use App\Services\OpenAIService;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * IllegalSearchDetectorTest
 *
 * Comprehensive unit tests for illegal search and seizure detection.
 *
 * Tests:
 * - Warrant defect detection
 * - Excessive force detection
 * - False exigency detection
 * - Scope violation detection
 * - Severity calculation
 * - Defense strategy generation
 * - AI extraction from court decisions
 * - Regional comparison logic
 */
class IllegalSearchDetectorTest extends TestCase
{
    use UsesTestDatabase;

    protected IllegalSearchDetector $detector;

    protected $mockOpenAI;

    protected $mockOdlukeAgent;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock dependencies
        $this->mockOpenAI = Mockery::mock(OpenAIService::class);
        $this->mockOdlukeAgent = Mockery::mock(OdlukeSearchAgent::class);

        $this->detector = new IllegalSearchDetector(
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
    public function it_detects_warrant_defect_violations()
    {
        $case = LegalCase::factory()->create();

        $result = $this->detector->analyzeCase($case, [
            'has_warrant' => true,
            'search_type' => 'home',
            'warrant_defects' => ['vague description', 'insufficient probable cause'],
            'force_used' => [],
            'exigency_details' => [],
            'scope_violations' => [],
        ]);

        $this->assertTrue($result['violation_detected']);
        $this->assertGreaterThanOrEqual(75, $result['violation_severity']);

        $patterns = $result['violation_patterns'];
        $patternTypes = array_column($patterns, 'type');
        $this->assertContains('warrant_defect', $patternTypes);

        $warrantPattern = collect($patterns)->firstWhere('type', 'warrant_defect');
        $this->assertEquals(85, $warrantPattern['severity']); // Base 75 + 10 for 2 defects
    }

    /** @test */
    public function it_detects_excessive_force_violations()
    {
        $case = LegalCase::factory()->create();

        $result = $this->detector->analyzeCase($case, [
            'has_warrant' => true,
            'search_type' => 'home',
            'warrant_defects' => [],
            'force_used' => ['breaking doors', 'terrorizing family', 'property destruction'],
            'exigency_details' => [],
            'scope_violations' => [],
        ]);

        $this->assertTrue($result['violation_detected']);
        $this->assertGreaterThanOrEqual(85, $result['violation_severity']);

        $patterns = $result['violation_patterns'];
        $patternTypes = array_column($patterns, 'type');
        $this->assertContains('excessive_force', $patternTypes);

        $forcePattern = collect($patterns)->firstWhere('type', 'excessive_force');
        $this->assertEquals(95, $forcePattern['severity']); // Base 85 + 10 for multiple incidents
    }

    /** @test */
    public function it_detects_false_exigency_violations()
    {
        $case = LegalCase::factory()->create();

        $result = $this->detector->analyzeCase($case, [
            'has_warrant' => false,
            'search_type' => 'home',
            'warrant_defects' => [],
            'force_used' => [],
            'exigency_details' => [
                'claimed_reason' => 'evidence destruction imminent',
                'actual_emergency' => false,
            ],
            'scope_violations' => [],
        ]);

        $this->assertTrue($result['violation_detected']);
        $this->assertGreaterThanOrEqual(90, $result['violation_severity']);

        $patterns = $result['violation_patterns'];
        $patternTypes = array_column($patterns, 'type');
        $this->assertContains('no_exigency', $patternTypes);

        $exigencyPattern = collect($patterns)->firstWhere('type', 'no_exigency');
        $this->assertEquals(90, $exigencyPattern['severity']);
        $this->assertStringContainsString('no actual emergency', $exigencyPattern['evidence']);
    }

    /** @test */
    public function it_detects_scope_violation()
    {
        $case = LegalCase::factory()->create();

        $result = $this->detector->analyzeCase($case, [
            'has_warrant' => true,
            'search_type' => 'home',
            'warrant_defects' => [],
            'force_used' => [],
            'exigency_details' => [],
            'scope_violations' => ['searched beyond authorization', 'seized unauthorized items'],
        ]);

        $this->assertTrue($result['violation_detected']);
        $this->assertGreaterThanOrEqual(80, $result['violation_severity']);

        $patterns = $result['violation_patterns'];
        $patternTypes = array_column($patterns, 'type');
        $this->assertContains('scope_violation', $patternTypes);

        $scopePattern = collect($patterns)->firstWhere('type', 'scope_violation');
        $this->assertEquals(90, $scopePattern['severity']); // Base 80 + 10 for multiple violations
    }

    /** @test */
    public function it_does_not_detect_violations_for_valid_search()
    {
        $case = LegalCase::factory()->create();

        $result = $this->detector->analyzeCase($case, [
            'has_warrant' => true,
            'search_type' => 'home',
            'warrant_defects' => [],
            'force_used' => [],
            'exigency_details' => [],
            'scope_violations' => [],
        ]);

        $this->assertFalse($result['violation_detected']);
        $this->assertEquals(0, $result['violation_severity']);
        $this->assertEmpty($result['violation_patterns']);
    }

    /** @test */
    public function it_detects_multiple_violation_types()
    {
        $case = LegalCase::factory()->create();

        $result = $this->detector->analyzeCase($case, [
            'has_warrant' => true,
            'search_type' => 'home',
            'warrant_defects' => ['vague description'],
            'force_used' => ['breaking doors'],
            'exigency_details' => [],
            'scope_violations' => ['searched beyond authorization'],
        ]);

        $this->assertTrue($result['violation_detected']);

        $patterns = $result['violation_patterns'];
        $this->assertCount(3, $patterns);

        $patternTypes = array_column($patterns, 'type');
        $this->assertContains('warrant_defect', $patternTypes);
        $this->assertContains('excessive_force', $patternTypes);
        $this->assertContains('scope_violation', $patternTypes);

        // Should add 5 points per additional violation type (2 additional = +10)
        $this->assertGreaterThanOrEqual(95, $result['violation_severity']);
    }

    /** @test */
    public function it_calculates_suppression_likelihood_correctly()
    {
        $case = LegalCase::factory()->create();

        // High likelihood: false exigency (severity 90+)
        $result1 = $this->detector->analyzeCase($case, [
            'has_warrant' => false,
            'search_type' => 'home',
            'warrant_defects' => [],
            'force_used' => [],
            'exigency_details' => [
                'claimed_reason' => 'emergency',
                'actual_emergency' => false,
            ],
            'scope_violations' => [],
        ]);
        $this->assertEquals('high', $result1['suppression_likelihood']);

        // Moderate likelihood: severity 70-84
        $result2 = $this->detector->analyzeCase($case, [
            'has_warrant' => true,
            'search_type' => 'home',
            'warrant_defects' => ['vague description'],
            'force_used' => [],
            'exigency_details' => [],
            'scope_violations' => [],
        ]);
        $this->assertEquals('moderate', $result2['suppression_likelihood']);

        // Low-moderate likelihood: severity 60-69
        // This is difficult to achieve with single violation, so we skip it

        // Low likelihood: severity < 60 (no violations)
        $result4 = $this->detector->analyzeCase($case, [
            'has_warrant' => true,
            'search_type' => 'home',
            'warrant_defects' => [],
            'force_used' => [],
            'exigency_details' => [],
            'scope_violations' => [],
        ]);
        $this->assertEquals('low', $result4['suppression_likelihood']);
    }

    /** @test */
    public function it_generates_motion_to_suppress_strategy()
    {
        $case = LegalCase::factory()->create();

        $result = $this->detector->analyzeCase($case, [
            'has_warrant' => true,
            'search_type' => 'home',
            'warrant_defects' => ['vague description'],
            'force_used' => [],
            'exigency_details' => [],
            'scope_violations' => [],
        ]);

        $strategies = $result['defense_strategy'];
        $strategyTypes = array_column($strategies, 'strategy');

        $this->assertContains('motion_to_suppress', $strategyTypes);

        $motion = collect($strategies)->firstWhere('strategy', 'motion_to_suppress');
        $this->assertEquals('high', $motion['priority']);
        $this->assertStringContainsString('ZKP Čl. 222', $motion['title']);
        $this->assertStringContainsString('suppress', strtolower($motion['description']));
    }

    /** @test */
    public function it_generates_challenge_warrant_validity_strategy()
    {
        $case = LegalCase::factory()->create();

        $result = $this->detector->analyzeCase($case, [
            'has_warrant' => true,
            'search_type' => 'home',
            'warrant_defects' => ['insufficient probable cause'],
            'force_used' => [],
            'exigency_details' => [],
            'scope_violations' => [],
        ]);

        $strategies = $result['defense_strategy'];
        $strategyTypes = array_column($strategies, 'strategy');

        $this->assertContains('challenge_warrant_validity', $strategyTypes);

        $strategy = collect($strategies)->firstWhere('strategy', 'challenge_warrant_validity');
        $this->assertEquals('high', $strategy['priority']);
        $this->assertStringContainsString('ZKP Čl. 220', $strategy['legal_basis']);
    }

    /** @test */
    public function it_generates_constitutional_violation_strategy()
    {
        $case = LegalCase::factory()->create();

        $result = $this->detector->analyzeCase($case, [
            'has_warrant' => true,
            'search_type' => 'home',
            'warrant_defects' => ['vague description'],
            'force_used' => [],
            'exigency_details' => [],
            'scope_violations' => [],
        ]);

        $strategies = $result['defense_strategy'];
        $strategyTypes = array_column($strategies, 'strategy');

        $this->assertContains('constitutional_violation', $strategyTypes);

        $strategy = collect($strategies)->firstWhere('strategy', 'constitutional_violation');
        $this->assertStringContainsString('Ustav RH', $strategy['legal_basis']);
        $this->assertStringContainsString('34', $strategy['legal_basis']);
        $this->assertStringContainsString('35', $strategy['legal_basis']);
    }

    /** @test */
    public function it_extracts_search_info_from_court_decision_using_ai()
    {
        $caseData = [
            'text' => 'Policija je izvršila pretres stana na temelju naloga. Nalog je sadržavao nejasan opis predmeta pretrage. Policija je razbila vrata bez najave.',
            'case_id' => 'K-123/2025',
        ];

        $this->mockOpenAI
            ->shouldReceive('chat')
            ->once()
            ->with(Mockery::on(function ($messages) {
                return count($messages) === 2 &&
                       $messages[0]['role'] === 'system' &&
                       $messages[1]['role'] === 'user';
            }), 'gpt-4o-mini', ['temperature' => 0.1, 'max_tokens' => 400])
            ->andReturn([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'search_type' => 'home',
                                'has_warrant' => true,
                                'violations' => ['warrant_defect', 'excessive_force'],
                                'violation_details' => [
                                    'warrant_defects' => ['vague description'],
                                    'force_used' => ['breaking doors'],
                                ],
                                'confidence' => 'high',
                            ]),
                        ],
                    ],
                ],
            ]);

        $reflection = new \ReflectionClass($this->detector);
        $method = $reflection->getMethod('extractSearchInfo');
        $method->setAccessible(true);

        $result = $method->invoke($this->detector, $caseData);

        $this->assertEquals('home', $result['search_type']);
        $this->assertTrue($result['has_warrant']);
        $this->assertContains('warrant_defect', $result['violations']);
        $this->assertContains('excessive_force', $result['violations']);
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
        $method = $reflection->getMethod('extractSearchInfo');
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
        $method = $reflection->getMethod('extractSearchInfo');
        $method->setAccessible(true);

        $result = $method->invoke($this->detector, $caseData);

        $this->assertNull($result);
    }

    /** @test */
    public function it_determines_worse_region_correctly()
    {
        $stats1 = ['violation_percentage' => 45.5];
        $stats2 = ['violation_percentage' => 28.3];

        $reflection = new \ReflectionClass($this->detector);
        $method = $reflection->getMethod('determineWorseRegion');
        $method->setAccessible(true);

        $result = $method->invoke($this->detector, $stats1, $stats2, 'Osijek', 'Zagreb');

        $this->assertEquals('Osijek', $result['worse_region']);
        $this->assertEquals(17.2, $result['violation_percentage_difference']);
        $this->assertEquals('significant', $result['significance']);
    }

    /** @test */
    public function it_generates_alarming_findings_for_high_violation_rate()
    {
        $reflection = new \ReflectionClass($this->detector);
        $method = $reflection->getMethod('generateAlarmingFindings');
        $method->setAccessible(true);

        // Test >40% rate
        $findings = $method->invoke($this->detector, 45.5, 50);
        $this->assertCount(1, $findings);
        $this->assertStringContainsString('45.5%', $findings[0]);
        $this->assertStringContainsString('sistemski problem', $findings[0]);

        // Test >25% rate
        $findings = $method->invoke($this->detector, 30.0, 40);
        $this->assertCount(1, $findings);
        $this->assertStringContainsString('30%', $findings[0]);

        // Test <25% rate
        $findings = $method->invoke($this->detector, 20.0, 30);
        $this->assertEmpty($findings);
    }

    /** @test */
    public function it_identifies_legal_violations_from_patterns()
    {
        $case = LegalCase::factory()->create();

        $result = $this->detector->analyzeCase($case, [
            'has_warrant' => true,
            'search_type' => 'home',
            'warrant_defects' => ['vague description'],
            'force_used' => ['breaking doors'],
            'exigency_details' => [],
            'scope_violations' => [],
        ]);

        $violations = $result['legal_violations'];

        $this->assertNotEmpty($violations);
        $this->assertIsArray($violations);
        $this->assertCount(2, $violations);

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
        $this->assertEquals('illegal_search', $this->detector->getTopicName());
        $this->assertStringContainsString('Illegal search and seizure', $this->detector->getTopicDescription());

        $framework = $this->detector->getLegalFramework();
        $this->assertArrayHasKey('Ustav_RH_Čl_34', $framework);
        $this->assertArrayHasKey('Ustav_RH_Čl_35', $framework);
        $this->assertArrayHasKey('ZKP_Čl_220', $framework);
        $this->assertArrayHasKey('ZKP_Čl_221', $framework);
        $this->assertArrayHasKey('ZKP_Čl_222', $framework);
    }

    /** @test */
    public function it_returns_correct_search_keywords()
    {
        $reflection = new \ReflectionClass($this->detector);
        $method = $reflection->getMethod('getSearchKeywords');
        $method->setAccessible(true);

        $keywords = $method->invoke($this->detector);

        $this->assertContains('pretres', $keywords);
        $this->assertContains('pretres stana', $keywords);
        $this->assertContains('nalog za pretres', $keywords);
        $this->assertContains('nezakoniti dokazi', $keywords);
        $this->assertContains('ZKP 220', $keywords);
        $this->assertContains('ZKP 221', $keywords);
        $this->assertContains('ZKP 222', $keywords);
        $this->assertContains('Ustav RH 34', $keywords);
    }

    /** @test */
    public function it_handles_warrantless_search_with_true_exigency()
    {
        $case = LegalCase::factory()->create();

        // Warrantless search with true emergency (not a violation)
        $result = $this->detector->analyzeCase($case, [
            'has_warrant' => false,
            'search_type' => 'vehicle',
            'warrant_defects' => [],
            'force_used' => [],
            'exigency_details' => [
                'claimed_reason' => 'suspect fleeing',
                'actual_emergency' => true,
            ],
            'scope_violations' => [],
        ]);

        $this->assertFalse($result['violation_detected']);
        $this->assertEquals(0, $result['violation_severity']);

        // Should not have no_exigency pattern
        $patternTypes = array_column($result['violation_patterns'], 'type');
        $this->assertNotContains('no_exigency', $patternTypes);
    }

    /** @test */
    public function it_handles_multiple_warrant_defects()
    {
        $case = LegalCase::factory()->create();

        $result = $this->detector->analyzeCase($case, [
            'has_warrant' => true,
            'search_type' => 'home',
            'warrant_defects' => ['vague description', 'wrong address', 'stale information'],
            'force_used' => [],
            'exigency_details' => [],
            'scope_violations' => [],
        ]);

        $this->assertTrue($result['violation_detected']);

        $warrantPattern = collect($result['violation_patterns'])
            ->firstWhere('type', 'warrant_defect');

        // Base 75 + 15 for 3+ defects = 90
        $this->assertEquals(90, $warrantPattern['severity']);
        $this->assertStringContainsString('3 legal defect(s)', $warrantPattern['description']);
    }

    /** @test */
    public function it_supports_different_search_types()
    {
        $case = LegalCase::factory()->create();

        // Home search
        $result1 = $this->detector->analyzeCase($case, [
            'has_warrant' => true,
            'search_type' => 'home',
            'warrant_defects' => [],
            'force_used' => [],
            'exigency_details' => [],
            'scope_violations' => [],
        ]);
        $this->assertEquals('home', $result1['search_type']);

        // Vehicle search
        $result2 = $this->detector->analyzeCase($case, [
            'has_warrant' => false,
            'search_type' => 'vehicle',
            'warrant_defects' => [],
            'force_used' => [],
            'exigency_details' => [],
            'scope_violations' => [],
        ]);
        $this->assertEquals('vehicle', $result2['search_type']);

        // Person search
        $result3 = $this->detector->analyzeCase($case, [
            'has_warrant' => false,
            'search_type' => 'person',
            'warrant_defects' => [],
            'force_used' => [],
            'exigency_details' => [],
            'scope_violations' => [],
        ]);
        $this->assertEquals('person', $result3['search_type']);
    }
}
