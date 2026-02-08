<?php

namespace Tests\Unit\Topics;

use App\Models\LegalCase;
use App\Modules\HomeSearch\Services\OdlukeSearchAgent;
use App\Modules\Topics\Analyzers\BailAbuseDetector;
use App\Services\OpenAIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BailAbuseDetectorTest
 *
 * Test suite for BailAbuseDetector - detecting excessive bail, unjustified denials,
 * and impossible bail conditions.
 */
class BailAbuseDetectorTest extends TestCase
{
    use RefreshDatabase;

    private BailAbuseDetector $detector;
    private OpenAIService $openAI;
    private OdlukeSearchAgent $odlukeAgent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->openAI = $this->createMock(OpenAIService::class);
        $this->odlukeAgent = $this->createMock(OdlukeSearchAgent::class);

        $this->detector = new BailAbuseDetector($this->openAI, $this->odlukeAgent);
    }

    /**
     * Test that detector properly extends TopicAnalyzer
     */
    public function test_detector_has_correct_topic_name(): void
    {
        $this->assertEquals('bail_abuse', $this->detector->getTopicName());
    }

    public function test_detector_has_correct_topic_description(): void
    {
        $this->assertEquals(
            'Excessive bail denial or unreasonable amounts',
            $this->detector->getTopicDescription()
        );
    }

    public function test_detector_has_legal_framework(): void
    {
        $framework = $this->detector->getLegalFramework();

        $this->assertIsArray($framework);
        $this->assertArrayHasKey('ZKP_Čl_102', $framework);
        $this->assertArrayHasKey('ZKP_Čl_98', $framework);
        $this->assertArrayHasKey('Ustav_RH_Čl_24', $framework);
    }

    /**
     * Test excessive bail detection
     */
    public function test_analyzeCase_detects_excessive_bail(): void
    {
        $case = LegalCase::factory()->create([
            'case_number' => 'K-1234/2025',
            'court' => 'Županijski sud Osijek',
        ]);

        $bailData = [
            'bail_amount' => 100000, // 100,000 HRK
            'offense' => 'misdemeanor_theft', // Minor offense
            'offense_severity' => 'minor',
            'defendant_income' => 5000, // Monthly income 5,000 HRK
            'flight_risk_evidence' => [],
            'bail_status' => 'set',
        ];

        $result = $this->detector->analyzeCase($case, $bailData);

        $this->assertIsArray($result);
        $this->assertTrue($result['abuse_detected']);
        $this->assertGreaterThanOrEqual(85, $result['abuse_severity']);
        $this->assertArrayHasKey('patterns', $result);
        $this->assertArrayHasKey('defense_strategy', $result);
        $this->assertArrayHasKey('legal_violations', $result);

        // Check that excessive_bail pattern was detected
        $patternTypes = array_column($result['patterns'], 'type');
        $this->assertContains('excessive_bail', $patternTypes);
    }

    /**
     * Test unjustified bail denial detection
     */
    public function test_analyzeCase_detects_unjustified_denial(): void
    {
        $case = LegalCase::factory()->create([
            'case_number' => 'K-5678/2025',
            'court' => 'Županijski sud Zadar',
        ]);

        $bailData = [
            'bail_amount' => 0, // Denied
            'offense' => 'minor_drug_possession',
            'offense_severity' => 'minor',
            'defendant_income' => 8000,
            'flight_risk_evidence' => [], // No evidence of flight risk
            'bail_status' => 'denied',
            'defendant_ties' => ['local_residence', 'family', 'employment'], // Strong community ties
        ];

        $result = $this->detector->analyzeCase($case, $bailData);

        $this->assertTrue($result['abuse_detected']);
        $this->assertGreaterThanOrEqual(90, $result['abuse_severity']);

        // Check that unjustified_denial pattern was detected
        $patternTypes = array_column($result['patterns'], 'type');
        $this->assertContains('unjustified_denial', $patternTypes);
    }

    /**
     * Test impossible bail conditions detection
     */
    public function test_analyzeCase_detects_impossible_conditions(): void
    {
        $case = LegalCase::factory()->create([
            'case_number' => 'K-9012/2025',
            'court' => 'Županijski sud Split',
        ]);

        $bailData = [
            'bail_amount' => 50000,
            'offense' => 'assault',
            'offense_severity' => 'moderate',
            'defendant_income' => 6000,
            'flight_risk_evidence' => [],
            'bail_status' => 'set',
            'bail_conditions' => [
                'daily_police_reporting', // Must report daily
                'no_contact_with_100_people', // Impossible to verify
                'surrender_passport_not_owned', // Doesn't have passport
            ],
        ];

        $result = $this->detector->analyzeCase($case, $bailData);

        $this->assertTrue($result['abuse_detected']);
        $this->assertGreaterThanOrEqual(80, $result['abuse_severity']);

        // Check that impossible_conditions pattern was detected
        $patternTypes = array_column($result['patterns'], 'type');
        $this->assertContains('impossible_conditions', $patternTypes);
    }

    /**
     * Test no abuse detected when bail is reasonable
     */
    public function test_analyzeCase_no_abuse_when_bail_reasonable(): void
    {
        $case = LegalCase::factory()->create([
            'case_number' => 'K-3456/2025',
            'court' => 'Županijski sud Zagreb',
        ]);

        $bailData = [
            'bail_amount' => 10000, // Reasonable amount
            'offense' => 'minor_theft',
            'offense_severity' => 'minor',
            'defendant_income' => 8000,
            'flight_risk_evidence' => [],
            'bail_status' => 'set',
            'bail_conditions' => ['weekly_check_in'], // Reasonable condition
        ];

        $result = $this->detector->analyzeCase($case, $bailData);

        $this->assertFalse($result['abuse_detected']);
        $this->assertLessThan(60, $result['abuse_severity']);
    }

    /**
     * Test pattern detection for excessive bail
     */
    public function test_detectPatterns_excessive_bail(): void
    {
        $bailData = [
            'bail_amount' => 200000,
            'offense_severity' => 'minor',
            'defendant_income' => 5000,
            'bail_status' => 'set',
        ];

        $reflection = new \ReflectionClass($this->detector);
        $method = $reflection->getMethod('detectPatterns');
        $method->setAccessible(true);

        $patterns = $method->invoke($this->detector, $bailData);

        $this->assertIsArray($patterns);
        $this->assertNotEmpty($patterns);

        $patternTypes = array_column($patterns, 'type');
        $this->assertContains('excessive_bail', $patternTypes);

        // Find the excessive_bail pattern
        $excessivePattern = null;
        foreach ($patterns as $pattern) {
            if ($pattern['type'] === 'excessive_bail') {
                $excessivePattern = $pattern;
                break;
            }
        }

        $this->assertNotNull($excessivePattern);
        $this->assertEquals(85, $excessivePattern['severity']);
        $this->assertArrayHasKey('description', $excessivePattern);
        $this->assertArrayHasKey('legal_basis', $excessivePattern);
    }

    /**
     * Test pattern detection for unjustified denial
     */
    public function test_detectPatterns_unjustified_denial(): void
    {
        $bailData = [
            'bail_amount' => 0,
            'offense_severity' => 'minor',
            'flight_risk_evidence' => [],
            'bail_status' => 'denied',
            'defendant_ties' => ['local_residence', 'family'],
        ];

        $reflection = new \ReflectionClass($this->detector);
        $method = $reflection->getMethod('detectPatterns');
        $method->setAccessible(true);

        $patterns = $method->invoke($this->detector, $bailData);

        $patternTypes = array_column($patterns, 'type');
        $this->assertContains('unjustified_denial', $patternTypes);

        // Find the pattern
        $denialPattern = null;
        foreach ($patterns as $pattern) {
            if ($pattern['type'] === 'unjustified_denial') {
                $denialPattern = $pattern;
                break;
            }
        }

        $this->assertNotNull($denialPattern);
        // Severity can be 90 or 95 depending on community ties
        $this->assertGreaterThanOrEqual(90, $denialPattern['severity']);
    }

    /**
     * Test pattern detection for impossible conditions
     */
    public function test_detectPatterns_impossible_conditions(): void
    {
        $bailData = [
            'bail_amount' => 30000,
            'bail_status' => 'set',
            'bail_conditions' => [
                'daily_police_reporting',
                'no_internet_access', // Impossible in modern life
                'surrender_passport_not_owned',
            ],
        ];

        $reflection = new \ReflectionClass($this->detector);
        $method = $reflection->getMethod('detectPatterns');
        $method->setAccessible(true);

        $patterns = $method->invoke($this->detector, $bailData);

        $patternTypes = array_column($patterns, 'type');
        $this->assertContains('impossible_conditions', $patternTypes);

        // Find the pattern
        $conditionsPattern = null;
        foreach ($patterns as $pattern) {
            if ($pattern['type'] === 'impossible_conditions') {
                $conditionsPattern = $pattern;
                break;
            }
        }

        $this->assertNotNull($conditionsPattern);
        $this->assertEquals(80, $conditionsPattern['severity']);
    }

    /**
     * Test defense strategy generation
     */
    public function test_generateDefenseStrategy(): void
    {
        $analysis = [
            'abuse_severity' => 85,
            'patterns' => [
                [
                    'type' => 'excessive_bail',
                    'severity' => 85,
                    'description' => 'Bail amount disproportionate',
                ],
            ],
            'bail_amount' => 100000,
            'defendant_income' => 5000,
        ];

        $reflection = new \ReflectionClass($this->detector);
        $method = $reflection->getMethod('generateDefenseStrategy');
        $method->setAccessible(true);

        $strategies = $method->invoke($this->detector, $analysis);

        $this->assertIsArray($strategies);
        $this->assertNotEmpty($strategies);

        foreach ($strategies as $strategy) {
            $this->assertArrayHasKey('strategy', $strategy);
            $this->assertArrayHasKey('priority', $strategy);
            $this->assertArrayHasKey('title', $strategy);
            $this->assertArrayHasKey('description', $strategy);
            $this->assertArrayHasKey('legal_basis', $strategy);
        }
    }

    /**
     * Test search keywords
     */
    public function test_getSearchKeywords(): void
    {
        $reflection = new \ReflectionClass($this->detector);
        $method = $reflection->getMethod('getSearchKeywords');
        $method->setAccessible(true);

        $keywords = $method->invoke($this->detector);

        $this->assertIsArray($keywords);
        $this->assertNotEmpty($keywords);

        // Should contain bail-related Croatian keywords
        $this->assertContains('jamčevina', $keywords);
        $this->assertContains('istražni zatvor', $keywords);
    }

    /**
     * Test abuse severity calculation for excessive bail
     */
    public function test_calculateAbuseSeverity_for_excessive_bail(): void
    {
        $patterns = [
            [
                'type' => 'excessive_bail',
                'severity' => 85,
            ],
        ];

        $bailData = [
            'bail_amount' => 150000,
            'defendant_income' => 6000,
        ];

        $reflection = new \ReflectionClass($this->detector);
        $method = $reflection->getMethod('calculateAbuseSeverity');
        $method->setAccessible(true);

        $severity = $method->invoke($this->detector, $patterns, $bailData);

        $this->assertIsInt($severity);
        $this->assertGreaterThanOrEqual(0, $severity);
        $this->assertLessThanOrEqual(100, $severity);
        $this->assertGreaterThanOrEqual(85, $severity);
    }

    /**
     * Test abuse severity calculation for unjustified denial
     */
    public function test_calculateAbuseSeverity_for_unjustified_denial(): void
    {
        $patterns = [
            [
                'type' => 'unjustified_denial',
                'severity' => 90,
            ],
        ];

        $bailData = [
            'bail_status' => 'denied',
            'flight_risk_evidence' => [],
        ];

        $reflection = new \ReflectionClass($this->detector);
        $method = $reflection->getMethod('calculateAbuseSeverity');
        $method->setAccessible(true);

        $severity = $method->invoke($this->detector, $patterns, $bailData);

        $this->assertGreaterThanOrEqual(90, $severity);
    }

    /**
     * Test that getStatistics returns proper structure
     */
    public function test_getStatistics_returns_proper_structure(): void
    {
        $this->odlukeAgent
            ->expects($this->once())
            ->method('searchHomeSearchCases')
            ->willReturn([
                'cases' => [],
                'total' => 0,
            ]);

        $criteria = [
            'region' => 'Osijek',
            'year' => 2025,
        ];

        $stats = $this->detector->getStatistics($criteria);

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_cases', $stats);
    }

    /**
     * Test regional comparison
     */
    public function test_determineWorseRegion(): void
    {
        $stats1 = [
            'total_cases' => 100,
            'abuse_detected_count' => 60,
            'abuse_percentage' => 60.0,
        ];

        $stats2 = [
            'total_cases' => 80,
            'abuse_detected_count' => 32,
            'abuse_percentage' => 40.0,
        ];

        $reflection = new \ReflectionClass($this->detector);
        $method = $reflection->getMethod('determineWorseRegion');
        $method->setAccessible(true);

        $result = $method->invoke($this->detector, $stats1, $stats2, 'Osijek', 'Zadar');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('worse_region', $result);
        $this->assertEquals('Osijek', $result['worse_region']);
        $this->assertArrayHasKey('abuse_percentage_difference', $result);
        $this->assertEquals(20.0, $result['abuse_percentage_difference']);
    }
}
