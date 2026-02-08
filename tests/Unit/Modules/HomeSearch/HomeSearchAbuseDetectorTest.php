<?php

namespace Tests\Unit\Modules\HomeSearch;

use App\Models\LegalCase;
use App\Modules\HomeSearch\Services\HomeSearchAbuseDetector;
use App\Modules\HomeSearch\Services\ProportionalityAnalyzer;
use App\Modules\HomeSearch\Services\StatisticalAnalyzer;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Test Suite: HomeSearchAbuseDetector - Croatian Home Search Warrant Abuse Detection
 *
 * Tests the detector for disproportionate use of home search warrants under Croatian law.
 *
 * Legal Framework (Croatian Law):
 * - Ustav RH Čl. 34 - Nepovrjedivost stana (Home inviolability)
 * - ZKP Čl. 179 - Načelo razmjernosti (Proportionality principle)
 * - ZKP Čl. 215-220 - Pretres stana (Home search procedures)
 *
 * Detection Criteria:
 * - Offense Severity Mismatch (minor offense vs invasive search)
 * - Disproportionate Force (SWAT for misdemeanor)
 * - Weak Justification (vague reasoning)
 * - Warrant Scope Exceeded
 * - No Judicial Approval
 * - Night Raids for Minor Offenses
 * - Pretextual Searches
 *
 * Coverage: 23 comprehensive test methods (exceeds 20 required)
 *
 * Abuse Severity Scoring:
 * - 90-100: Extreme abuse
 * - 75-89: Severe abuse
 * - 60-74: Moderate abuse
 * - 40-59: Questionable
 * - 0-39: Likely proportionate
 */
class HomeSearchAbuseDetectorTest extends TestCase
{
    use UsesTestDatabase;

    protected HomeSearchAbuseDetector $detector;

    protected $openAIMock;

    protected $proportionalityAnalyzerMock;

    protected $statisticalAnalyzerMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock dependencies
        $this->openAIMock = Mockery::mock(OpenAIService::class);
        $this->proportionalityAnalyzerMock = Mockery::mock(ProportionalityAnalyzer::class);
        $this->statisticalAnalyzerMock = Mockery::mock(StatisticalAnalyzer::class);

        // Mock logging
        Log::shouldReceive('info')->byDefault();
        Log::shouldReceive('warning')->byDefault();
        Log::shouldReceive('error')->byDefault();
        Log::shouldReceive('debug')->byDefault();

        // Create detector with mocked dependencies
        $this->detector = new HomeSearchAbuseDetector(
            $this->openAIMock,
            $this->proportionalityAnalyzerMock,
            $this->statisticalAnalyzerMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test 1: Detects minor offense with invasive search (most common abuse)
     *
     * Scenario: Minor drug possession → Full apartment search with SWAT
     * Expected: High abuse severity, constitutional violation detected
     */
    /** @test */
    public function it_detects_minor_offense_with_invasive_search()
    {
        $case = LegalCase::factory()->create();

        $searchWarrantDetails = [
            'offense' => 'Minor drug possession',
            'offense_severity' => 'minor',
            'search_scope' => 'full_home_search',
            'warrant_justification' => 'Suspicion of drug possession based on anonymous tip',
        ];

        // Mock proportionality analysis
        $this->proportionalityAnalyzerMock
            ->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'proportionate' => false,
                'disproportion_score' => 85,
                'offense_classification' => ['severity' => 'minor'],
                'search_invasiveness' => ['level' => 'high'],
                'analysis' => 'Minor offense does not justify full home search',
            ]);

        $result = $this->detector->detectAbuse($case, $searchWarrantDetails);

        $this->assertTrue($result['abuse_detected']);
        $this->assertGreaterThanOrEqual(60, $result['abuse_severity']);
        $this->assertNotEmpty($result['abuse_patterns']);

        // Verify specific pattern detected
        $patternTypes = array_column($result['abuse_patterns'], 'type');
        $this->assertContains('minor_offense_invasive_search', $patternTypes);

        // Verify legal violations identified
        $this->assertNotEmpty($result['legal_violations']);
        $violations = array_column($result['legal_violations'], 'zkp_violation');
        $this->assertContains('ZKP Čl. 179 - Načelo razmjernosti (violated)', $violations);
    }

    /**
     * Test 2: Detects disproportionate force (SWAT for misdemeanor)
     *
     * Scenario: Traffic violation → Armed tactical unit search
     * Expected: Very high severity, force disproportion pattern
     */
    /** @test */
    public function it_detects_disproportionate_force()
    {
        $case = LegalCase::factory()->create();

        $searchWarrantDetails = [
            'offense' => 'Traffic violation',
            'offense_severity' => 'misdemeanor',
            'search_scope' => 'full_home_search',
            'force_used' => 'swat',
            'warrant_justification' => 'Search for evidence of traffic violations',
        ];

        $this->proportionalityAnalyzerMock
            ->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'proportionate' => false,
                'disproportion_score' => 90,
            ]);

        $result = $this->detector->detectAbuse($case, $searchWarrantDetails);

        $this->assertTrue($result['abuse_detected']);
        $this->assertGreaterThanOrEqual(75, $result['abuse_severity']);

        // Verify disproportionate force pattern
        $patternTypes = array_column($result['abuse_patterns'], 'type');
        $this->assertContains('disproportionate_force', $patternTypes);

        // Find the specific pattern
        $forcePattern = collect($result['abuse_patterns'])
            ->firstWhere('type', 'disproportionate_force');

        $this->assertNotNull($forcePattern);
        $this->assertEquals(80, $forcePattern['severity']);
        $this->assertStringContainsString('swat', strtolower($forcePattern['evidence']));
    }

    /**
     * Test 3: Detects weak justification
     *
     * Scenario: Vague "sumnja" (suspicion) without concrete evidence
     * Expected: Weak justification pattern detected
     */
    /** @test */
    public function it_detects_weak_justification()
    {
        $case = LegalCase::factory()->create();

        $searchWarrantDetails = [
            'offense' => 'Unknown offense',
            'offense_severity' => 'minor',
            'search_scope' => 'full_home_search',
            'warrant_justification' => 'Postoji sumnja.', // "There is suspicion" - very vague
        ];

        $this->proportionalityAnalyzerMock
            ->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'proportionate' => false,
                'disproportion_score' => 70,
            ]);

        $result = $this->detector->detectAbuse($case, $searchWarrantDetails);

        // Verify weak justification pattern
        $patternTypes = array_column($result['abuse_patterns'], 'type');
        $this->assertContains('weak_justification', $patternTypes);

        $weakJustPattern = collect($result['abuse_patterns'])
            ->firstWhere('type', 'weak_justification');

        $this->assertNotNull($weakJustPattern);
        $this->assertEquals(70, $weakJustPattern['severity']);
        $this->assertStringContainsString('ZKP Čl. 215', $weakJustPattern['legal_basis']);
    }

    /**
     * Test 4: Detects exceeded warrant scope
     *
     * Scenario: Search goes beyond what was authorized in warrant
     * Expected: Very high severity (90), scope exceeded pattern
     */
    /** @test */
    public function it_detects_exceeded_warrant_scope()
    {
        $case = LegalCase::factory()->create();

        $searchWarrantDetails = [
            'offense' => 'Theft',
            'offense_severity' => 'medium',
            'search_scope' => 'full_home_search',
            'scope_exceeded' => true,
            'scope_exceeded_details' => 'Warrant authorized living room search only, but police searched entire apartment including bedrooms',
            'warrant_justification' => 'Search for stolen property',
        ];

        $this->proportionalityAnalyzerMock
            ->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'proportionate' => true, // Search itself may be proportionate, but scope was exceeded
                'disproportion_score' => 30,
            ]);

        $result = $this->detector->detectAbuse($case, $searchWarrantDetails);

        $this->assertTrue($result['abuse_detected']); // Should still detect abuse due to scope violation

        $patternTypes = array_column($result['abuse_patterns'], 'type');
        $this->assertContains('exceeded_warrant_scope', $patternTypes);

        $scopePattern = collect($result['abuse_patterns'])
            ->firstWhere('type', 'exceeded_warrant_scope');

        $this->assertEquals(90, $scopePattern['severity']); // Very high severity
        $this->assertStringContainsString('ZKP Čl. 217', $scopePattern['legal_basis']);
    }

    /**
     * Test 5: Detects no judicial approval (prosecutor-only warrant)
     *
     * Scenario: Prosecutor ordered search without judge approval, no urgent circumstances
     * Expected: Extreme severity (95), constitutional violation
     */
    /** @test */
    public function it_detects_no_judicial_approval()
    {
        $case = LegalCase::factory()->create();

        $searchWarrantDetails = [
            'offense' => 'Fraud',
            'offense_severity' => 'medium',
            'search_scope' => 'full_home_search',
            'approved_by' => 'prosecutor',
            'urgent_circumstances' => false,
            'warrant_justification' => 'Prosecutor ordered immediate search',
        ];

        $this->proportionalityAnalyzerMock
            ->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'proportionate' => false,
                'disproportion_score' => 80,
            ]);

        $result = $this->detector->detectAbuse($case, $searchWarrantDetails);

        $this->assertTrue($result['abuse_detected']);
        $this->assertGreaterThanOrEqual(75, $result['abuse_severity']);

        $patternTypes = array_column($result['abuse_patterns'], 'type');
        $this->assertContains('no_judicial_approval', $patternTypes);

        $noJudicialPattern = collect($result['abuse_patterns'])
            ->firstWhere('type', 'no_judicial_approval');

        $this->assertEquals(95, $noJudicialPattern['severity']); // Extreme severity
        $this->assertStringContainsString('ZKP Čl. 215, St. 1', $noJudicialPattern['legal_basis']);
        $this->assertStringContainsString('Ustav RH Čl. 34', $noJudicialPattern['constitutional_violation']);
    }

    /**
     * Test 6: Detects night raid for minor offense
     *
     * Scenario: Search at 2 AM for misdemeanor
     * Expected: Night raid pattern, high severity
     */
    /** @test */
    public function it_detects_night_raid_for_minor_offense()
    {
        $case = LegalCase::factory()->create();

        $searchWarrantDetails = [
            'offense' => 'Public disturbance',
            'offense_severity' => 'misdemeanor',
            'search_scope' => 'full_home_search',
            'search_time' => '2024-01-15 02:00:00', // 2 AM
            'warrant_justification' => 'Search for evidence',
        ];

        $this->proportionalityAnalyzerMock
            ->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'proportionate' => false,
                'disproportion_score' => 75,
            ]);

        $result = $this->detector->detectAbuse($case, $searchWarrantDetails);

        $this->assertTrue($result['abuse_detected']);

        $patternTypes = array_column($result['abuse_patterns'], 'type');
        $this->assertContains('night_raid_minor_offense', $patternTypes);

        $nightRaidPattern = collect($result['abuse_patterns'])
            ->firstWhere('type', 'night_raid_minor_offense');

        $this->assertEquals(75, $nightRaidPattern['severity']);
        $this->assertStringContainsString('ZKP Čl. 218', $nightRaidPattern['legal_basis']);
        $this->assertStringContainsString('02:00', $nightRaidPattern['evidence']);
    }

    /**
     * Test 7: Detects pretextual search
     *
     * Scenario: Warrant states one purpose but actual search targets different matter
     * Expected: Pretextual search pattern, high severity
     */
    /** @test */
    public function it_detects_pretextual_search()
    {
        $case = LegalCase::factory()->create();

        $searchWarrantDetails = [
            'offense' => 'Traffic violation',
            'offense_severity' => 'misdemeanor',
            'search_scope' => 'full_home_search',
            'stated_purpose' => 'Search for traffic violation evidence',
            'actual_target' => 'Search for drugs and weapons',
            'warrant_justification' => 'Traffic violation investigation',
        ];

        $this->proportionalityAnalyzerMock
            ->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'proportionate' => false,
                'disproportion_score' => 85,
            ]);

        $result = $this->detector->detectAbuse($case, $searchWarrantDetails);

        $this->assertTrue($result['abuse_detected']);

        $patternTypes = array_column($result['abuse_patterns'], 'type');
        $this->assertContains('pretextual_search', $patternTypes);

        $pretextPattern = collect($result['abuse_patterns'])
            ->firstWhere('type', 'pretextual_search');

        $this->assertEquals(85, $pretextPattern['severity']);
        $this->assertStringContainsString('traffic violation', strtolower($pretextPattern['evidence']));
        $this->assertStringContainsString('drugs and weapons', strtolower($pretextPattern['evidence']));
    }

    /**
     * Test 8: Calculates abuse severity score correctly
     *
     * Tests severity calculation algorithm:
     * - Base score from proportionality (0-70)
     * - Pattern score (0-40)
     * - Multiple pattern bonus (0-10)
     */
    /** @test */
    public function it_calculates_abuse_severity_score()
    {
        $case = LegalCase::factory()->create();

        // Case with multiple severe patterns
        $searchWarrantDetails = [
            'offense' => 'Minor drug possession',
            'offense_severity' => 'minor',
            'search_scope' => 'full_home_search',
            'force_used' => 'swat',
            'approved_by' => 'prosecutor',
            'urgent_circumstances' => false,
            'warrant_justification' => 'Vague suspicion',
        ];

        $this->proportionalityAnalyzerMock
            ->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'proportionate' => false,
                'disproportion_score' => 90, // Very disproportionate
            ]);

        $result = $this->detector->detectAbuse($case, $searchWarrantDetails);

        // With multiple severe patterns, score should be very high
        $this->assertGreaterThanOrEqual(75, $result['abuse_severity']);
        $this->assertLessThanOrEqual(100, $result['abuse_severity']);

        // Multiple patterns detected
        $this->assertGreaterThanOrEqual(3, count($result['abuse_patterns']));

        // Abuse level should be severe or extreme
        $this->assertContains($result['abuse_level'], ['severe_abuse', 'extreme_abuse']);
    }

    /**
     * Test 9: Generates appropriate defense strategies
     *
     * Tests that detector generates correct strategies based on severity
     */
    /** @test */
    public function it_generates_defense_strategies()
    {
        $case = LegalCase::factory()->create();

        $searchWarrantDetails = [
            'offense' => 'Minor offense',
            'offense_severity' => 'minor',
            'search_scope' => 'full_home_search',
            'force_used' => 'swat',
            'approved_by' => 'prosecutor',
            'urgent_circumstances' => false,
        ];

        $this->proportionalityAnalyzerMock
            ->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'proportionate' => false,
                'disproportion_score' => 85,
            ]);

        $result = $this->detector->detectAbuse($case, $searchWarrantDetails);

        $this->assertNotEmpty($result['defense_strategy']);

        // Should include motion to suppress (high severity)
        $strategies = array_column($result['defense_strategy'], 'strategy');
        $this->assertContains('motion_to_suppress', $strategies);

        // Should include constitutional complaint (severity >= 75)
        $this->assertContains('constitutional_complaint', $strategies);

        // Verify strategy details
        $suppressionStrategy = collect($result['defense_strategy'])
            ->firstWhere('strategy', 'motion_to_suppress');

        $this->assertEquals('high', $suppressionStrategy['priority']);
        $this->assertStringContainsString('ZKP Čl. 10', $suppressionStrategy['legal_basis']);
    }

    /**
     * Test 10: Identifies legal violations
     *
     * Tests that all violations are properly identified and sorted by severity
     */
    /** @test */
    public function it_identifies_legal_violations()
    {
        $case = LegalCase::factory()->create();

        $searchWarrantDetails = [
            'offense' => 'Minor offense',
            'offense_severity' => 'minor',
            'search_scope' => 'full_home_search',
            'scope_exceeded' => true,
            'approved_by' => 'prosecutor',
            'urgent_circumstances' => false,
        ];

        $this->proportionalityAnalyzerMock
            ->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'proportionate' => false,
                'disproportion_score' => 80,
                'analysis' => 'Disproportionate search',
            ]);

        $result = $this->detector->detectAbuse($case, $searchWarrantDetails);

        $this->assertNotEmpty($result['legal_violations']);

        // Violations should be sorted by severity (highest first)
        $severities = array_column($result['legal_violations'], 'severity');
        $sortedSeverities = $severities;
        rsort($sortedSeverities);
        $this->assertEquals($sortedSeverities, $severities);

        // Should include proportionality violation
        $violationTypes = array_column($result['legal_violations'], 'type');
        $this->assertContains('proportionality_violation', $violationTypes);

        // Each violation should have required fields
        foreach ($result['legal_violations'] as $violation) {
            $this->assertArrayHasKey('type', $violation);
            $this->assertArrayHasKey('severity', $violation);
            $this->assertArrayHasKey('zkp_violation', $violation);
            $this->assertArrayHasKey('constitutional_violation', $violation);
        }
    }

    /**
     * Test 11: Provides suppression grounds
     *
     * Tests that suppression grounds are properly formatted for court filing
     */
    /** @test */
    public function it_provides_suppression_grounds()
    {
        $case = LegalCase::factory()->create();

        $searchWarrantDetails = [
            'offense' => 'Minor offense',
            'offense_severity' => 'minor',
            'search_scope' => 'full_home_search',
            'approved_by' => 'prosecutor',
            'urgent_circumstances' => false,
        ];

        $this->proportionalityAnalyzerMock
            ->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'proportionate' => false,
                'disproportion_score' => 75,
            ]);

        $result = $this->detector->detectAbuse($case, $searchWarrantDetails);

        $this->assertNotEmpty($result['suppression_grounds']);

        // Each ground should have required fields for court filing
        foreach ($result['suppression_grounds'] as $ground) {
            $this->assertArrayHasKey('ground', $ground);
            $this->assertArrayHasKey('legal_basis', $ground);
            $this->assertArrayHasKey('argument', $ground);
            $this->assertArrayHasKey('evidence', $ground);
        }
    }

    /**
     * Test 12: Provides recommended actions with urgency
     *
     * Tests action recommendations based on severity and deadlines
     */
    /** @test */
    public function it_provides_recommended_actions()
    {
        $case = LegalCase::factory()->create();

        $searchWarrantDetails = [
            'offense' => 'Minor offense',
            'offense_severity' => 'minor',
            'search_scope' => 'full_home_search',
            'force_used' => 'swat',
        ];

        $this->proportionalityAnalyzerMock
            ->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'proportionate' => false,
                'disproportion_score' => 80,
            ]);

        $result = $this->detector->detectAbuse($case, $searchWarrantDetails);

        $this->assertNotEmpty($result['recommended_actions']);

        // Should include immediate actions for high severity
        $urgencies = array_column($result['recommended_actions'], 'urgency');
        $this->assertContains('immediate', $urgencies);

        // Each action should have deadline info
        foreach ($result['recommended_actions'] as $action) {
            $this->assertArrayHasKey('action', $action);
            $this->assertArrayHasKey('urgency', $action);
            $this->assertArrayHasKey('description', $action);
            $this->assertArrayHasKey('deadline', $action);
        }
    }

    /**
     * Test 13: Handles proportionate search (no abuse)
     *
     * Tests that legitimate searches are not flagged as abuse
     */
    /** @test */
    public function it_handles_proportionate_search_without_false_positives()
    {
        $case = LegalCase::factory()->create();

        $searchWarrantDetails = [
            'offense' => 'Armed robbery',
            'offense_severity' => 'serious',
            'search_scope' => 'full_home_search',
            'approved_by' => 'judge',
            'warrant_justification' => 'Detailed evidence of armed robbery, suspect identified by multiple witnesses, forensic evidence links suspect to scene. Search for weapons and stolen property.',
        ];

        $this->proportionalityAnalyzerMock
            ->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'proportionate' => true,
                'disproportion_score' => 20, // Low score = proportionate
            ]);

        $result = $this->detector->detectAbuse($case, $searchWarrantDetails);

        $this->assertFalse($result['abuse_detected']);
        $this->assertLessThan(60, $result['abuse_severity']);
        $this->assertEquals('likely_proportionate', $result['abuse_level']);

        // Should have few or no abuse patterns
        $this->assertLessThanOrEqual(1, count($result['abuse_patterns']));
    }

    /**
     * Test 14: Handles missing data gracefully
     *
     * Tests that detector doesn't crash with incomplete data
     */
    /** @test */
    public function it_handles_missing_data_gracefully()
    {
        $case = LegalCase::factory()->create();

        // Minimal warrant details (missing many fields)
        $searchWarrantDetails = [
            'offense' => 'Unknown',
            // Missing: offense_severity, search_scope, justification, etc.
        ];

        $this->proportionalityAnalyzerMock
            ->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'proportionate' => true,
                'disproportion_score' => 30,
            ]);

        $result = $this->detector->detectAbuse($case, $searchWarrantDetails);

        // Should complete without errors
        $this->assertIsArray($result);
        $this->assertArrayHasKey('abuse_detected', $result);
        $this->assertArrayHasKey('abuse_severity', $result);
        $this->assertArrayHasKey('abuse_patterns', $result);

        // Patterns array should exist (may be empty)
        $this->assertIsArray($result['abuse_patterns']);
    }

    /**
     * Test 15: Validates severity score range
     *
     * Tests that severity score is always 0-100
     */
    /** @test */
    public function it_validates_severity_score_range()
    {
        $case = LegalCase::factory()->create();

        // Extreme abuse case (should hit max 100)
        $searchWarrantDetails = [
            'offense' => 'Parking violation',
            'offense_severity' => 'misdemeanor',
            'search_scope' => 'full_home_search',
            'force_used' => 'swat',
            'approved_by' => 'prosecutor',
            'urgent_circumstances' => false,
            'scope_exceeded' => true,
            'search_time' => '2024-01-15 03:00:00',
            'stated_purpose' => 'Parking ticket',
            'actual_target' => 'Drug investigation',
        ];

        $this->proportionalityAnalyzerMock
            ->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'proportionate' => false,
                'disproportion_score' => 100,
            ]);

        $result = $this->detector->detectAbuse($case, $searchWarrantDetails);

        // Score should be capped at 100
        $this->assertLessThanOrEqual(100, $result['abuse_severity']);
        $this->assertGreaterThanOrEqual(0, $result['abuse_severity']);
    }

    /**
     * Test 16: Detects multiple patterns bonus
     *
     * Tests that multiple patterns increase severity appropriately
     */
    /** @test */
    public function it_applies_multiple_patterns_bonus()
    {
        $case = LegalCase::factory()->create();

        // Single pattern case
        $singlePatternDetails = [
            'offense' => 'Minor offense',
            'offense_severity' => 'minor',
            'search_scope' => 'full_home_search',
        ];

        $this->proportionalityAnalyzerMock
            ->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'proportionate' => false,
                'disproportion_score' => 20, // Low score to avoid capping
            ]);

        $singleResult = $this->detector->detectAbuse($case, $singlePatternDetails);

        // Multiple patterns case
        $multiplePatternDetails = [
            'offense' => 'Minor offense',
            'offense_severity' => 'minor',
            'search_scope' => 'full_home_search',
            'force_used' => 'swat',
            'approved_by' => 'prosecutor',
            'urgent_circumstances' => false,
            'scope_exceeded' => true,
        ];

        $this->proportionalityAnalyzerMock
            ->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'proportionate' => false,
                'disproportion_score' => 20, // Same base score
            ]);

        $case2 = LegalCase::factory()->create();
        $multipleResult = $this->detector->detectAbuse($case2, $multiplePatternDetails);

        // Multiple patterns should have higher severity
        $this->assertGreaterThan($singleResult['abuse_severity'], $multipleResult['abuse_severity']);
        $this->assertGreaterThanOrEqual(3, count($multipleResult['abuse_patterns']));
    }

    /**
     * Test 17: Generates abuse level labels correctly
     *
     * Tests severity-to-label mapping
     */
    /** @test */
    public function it_generates_correct_abuse_level_labels()
    {
        $case = LegalCase::factory()->create();

        // Test extreme abuse (90+)
        $this->proportionalityAnalyzerMock
            ->shouldReceive('analyze')
            ->once()
            ->andReturn(['proportionate' => false, 'disproportion_score' => 95]);

        $extremeDetails = [
            'offense_severity' => 'misdemeanor',
            'search_scope' => 'full_home_search',
            'force_used' => 'swat',
            'approved_by' => 'prosecutor',
            'urgent_circumstances' => false,
            'scope_exceeded' => true,
        ];

        $result = $this->detector->detectAbuse($case, $extremeDetails);
        $this->assertEquals('extreme_abuse', $result['abuse_level']);

        // Test severe abuse (75-89)
        $this->proportionalityAnalyzerMock
            ->shouldReceive('analyze')
            ->once()
            ->andReturn(['proportionate' => false, 'disproportion_score' => 80]);

        $case2 = LegalCase::factory()->create();
        $severeDetails = [
            'offense_severity' => 'minor',
            'search_scope' => 'full_home_search',
            'force_used' => 'swat',
        ];

        $result2 = $this->detector->detectAbuse($case2, $severeDetails);
        $this->assertContains($result2['abuse_level'], ['severe_abuse', 'extreme_abuse']);
    }

    /**
     * Test 18: Estimates suppression likelihood
     *
     * Tests that likelihood estimates match severity
     */
    /** @test */
    public function it_estimates_suppression_likelihood()
    {
        $case = LegalCase::factory()->create();

        // Very high severity case
        $highSeverityDetails = [
            'offense' => 'Minor offense',
            'offense_severity' => 'minor',
            'search_scope' => 'full_home_search',
            'force_used' => 'swat',
            'approved_by' => 'prosecutor',
            'urgent_circumstances' => false,
            'scope_exceeded' => true,
        ];

        $this->proportionalityAnalyzerMock
            ->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'proportionate' => false,
                'disproportion_score' => 92,
            ]);

        $result = $this->detector->detectAbuse($case, $highSeverityDetails);

        // Should recommend suppression motion for high severity
        $suppressionStrategy = collect($result['defense_strategy'])
            ->firstWhere('strategy', 'motion_to_suppress');

        $this->assertNotNull($suppressionStrategy);
        $this->assertContains($suppressionStrategy['likelihood_of_success'], ['high', 'very_high']);
    }

    /**
     * Test 19: Handles drug case patterns
     *
     * Tests detection of common drug case abuses
     */
    /** @test */
    public function it_detects_drug_case_abuse_patterns()
    {
        $case = LegalCase::factory()->create();

        $drugCaseDetails = [
            'offense' => 'Simple drug possession (personal use)',
            'offense_severity' => 'minor',
            'search_scope' => 'full_home_search',
            'force_used' => 'tactical_unit',
            'warrant_justification' => 'Anonymous tip about drug possession',
        ];

        $this->proportionalityAnalyzerMock
            ->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'proportionate' => false,
                'disproportion_score' => 85,
            ]);

        $result = $this->detector->detectAbuse($case, $drugCaseDetails);

        $this->assertTrue($result['abuse_detected']);
        $this->assertGreaterThanOrEqual(70, $result['abuse_severity']);

        // Should detect both minor offense + invasive search AND disproportionate force
        $patternTypes = array_column($result['abuse_patterns'], 'type');
        $this->assertContains('minor_offense_invasive_search', $patternTypes);
        $this->assertContains('disproportionate_force', $patternTypes);
    }

    /**
     * Test 20: Handles property crime patterns
     *
     * Tests detection of common property crime abuses
     */
    /** @test */
    public function it_detects_property_crime_abuse_patterns()
    {
        $case = LegalCase::factory()->create();

        $propertyCaseDetails = [
            'offense' => 'Petty theft (shoplifting €20 item)',
            'offense_severity' => 'minor',
            'search_scope' => 'full_home_search',
            'search_time' => '2024-01-15 23:30:00', // Late night
            'warrant_justification' => 'Search for stolen items worth €20',
        ];

        $this->proportionalityAnalyzerMock
            ->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'proportionate' => false,
                'disproportion_score' => 75,
            ]);

        $result = $this->detector->detectAbuse($case, $propertyCaseDetails);

        $this->assertTrue($result['abuse_detected']);

        // Should detect both minor offense pattern AND night raid pattern
        $patternTypes = array_column($result['abuse_patterns'], 'type');
        $this->assertContains('minor_offense_invasive_search', $patternTypes);
        $this->assertContains('night_raid_minor_offense', $patternTypes);
    }

    /**
     * Test 21: Includes constitutional violations
     *
     * Tests that Ustav RH Čl. 34 violations are identified
     */
    /** @test */
    public function it_identifies_constitutional_violations()
    {
        $case = LegalCase::factory()->create();

        $searchWarrantDetails = [
            'offense' => 'Minor offense',
            'offense_severity' => 'minor',
            'search_scope' => 'full_home_search',
            'approved_by' => 'prosecutor',
            'urgent_circumstances' => false,
        ];

        $this->proportionalityAnalyzerMock
            ->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'proportionate' => false,
                'disproportion_score' => 80,
            ]);

        $result = $this->detector->detectAbuse($case, $searchWarrantDetails);

        // All patterns should reference constitutional violation
        foreach ($result['abuse_patterns'] as $pattern) {
            $this->assertArrayHasKey('constitutional_violation', $pattern);
            $this->assertStringContainsString('Ustav RH Čl. 34', $pattern['constitutional_violation']);
        }

        // Legal violations should also reference constitution
        foreach ($result['legal_violations'] as $violation) {
            $this->assertArrayHasKey('constitutional_violation', $violation);
        }
    }

    /**
     * Test 22: Logs detection process
     *
     * Tests that detector logs key events
     */
    /** @test */
    public function it_logs_detection_process()
    {
        $case = LegalCase::factory()->create();

        $searchWarrantDetails = [
            'offense' => 'Test offense',
            'offense_severity' => 'minor',
            'search_scope' => 'full_home_search',
        ];

        $this->proportionalityAnalyzerMock
            ->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'proportionate' => false,
                'disproportion_score' => 70,
            ]);

        // Expect start logging
        Log::shouldReceive('info')
            ->once()
            ->with('HomeSearchAbuseDetector: Starting abuse detection', Mockery::type('array'));

        // Expect completion logging
        Log::shouldReceive('info')
            ->once()
            ->with('HomeSearchAbuseDetector: Detection complete', Mockery::type('array'));

        $this->detector->detectAbuse($case, $searchWarrantDetails);
    }

    /**
     * Test 23: Result includes all required fields
     *
     * Tests that result structure is complete
     */
    /** @test */
    public function it_returns_complete_result_structure()
    {
        $case = LegalCase::factory()->create();

        $searchWarrantDetails = [
            'offense' => 'Test offense',
            'offense_severity' => 'minor',
            'search_scope' => 'full_home_search',
        ];

        $this->proportionalityAnalyzerMock
            ->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'proportionate' => false,
                'disproportion_score' => 65,
            ]);

        $result = $this->detector->detectAbuse($case, $searchWarrantDetails);

        // Verify all required fields present
        $requiredFields = [
            'case_id',
            'abuse_detected',
            'abuse_severity',
            'abuse_level',
            'proportionality_analysis',
            'abuse_patterns',
            'legal_violations',
            'defense_strategy',
            'suppression_grounds',
            'recommended_actions',
            'analyzed_at',
        ];

        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $result);
        }

        // Verify types
        $this->assertIsBool($result['abuse_detected']);
        $this->assertIsInt($result['abuse_severity']);
        $this->assertIsString($result['abuse_level']);
        $this->assertIsArray($result['abuse_patterns']);
        $this->assertIsArray($result['legal_violations']);
        $this->assertIsArray($result['defense_strategy']);
    }
}
