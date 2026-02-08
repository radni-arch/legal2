<?php

namespace Tests\Feature;

use App\Models\LegalCase;
use App\Modules\HomeSearch\Services\OdlukeSearchAgent;
use App\Modules\Topics\Analyzers\DrugChargeAbuseDetector;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * TopicFrameworkIntegrationTest
 *
 * Integration tests for the full Topic Framework flow including:
 * - DrugChargeAbuseDetector analysis
 * - Integration with OdlukeSearchAgent
 * - AI extraction from court decisions
 * - Statistics generation
 * - Regional comparisons
 * - Caching
 */
class TopicFrameworkIntegrationTest extends TestCase
{
    use UsesTestDatabase;

    /** @test */
    public function it_analyzes_complete_drug_case_flow()
    {
        $case = LegalCase::factory()->create([
            'case_number' => 'K-123/2025',
            'title' => 'Test Drug Case',
        ]);

        $detector = app(DrugChargeAbuseDetector::class);

        $result = $detector->analyzeCase($case, [
            'drug_type' => 'cannabis',
            'amount' => 30,
            'charged_as' => 'dealing',
            'evidence_of_dealing' => [],
        ]);

        // Verify complete analysis structure
        $this->assertArrayHasKey('case_id', $result);
        $this->assertArrayHasKey('overcharge_detected', $result);
        $this->assertArrayHasKey('overcharge_severity', $result);
        $this->assertArrayHasKey('threshold_analysis', $result);
        $this->assertArrayHasKey('overcharging_patterns', $result);
        $this->assertArrayHasKey('defense_strategy', $result);
        $this->assertArrayHasKey('recommended_charge', $result);
        $this->assertArrayHasKey('legal_violations', $result);

        // Verify overcharge detection
        $this->assertTrue($result['overcharge_detected']);
        $this->assertGreaterThanOrEqual(60, $result['overcharge_severity']);

        // Verify threshold analysis
        $this->assertTrue($result['threshold_analysis']['personal_use_likely']);
        $this->assertEquals(30, $result['threshold_analysis']['threshold_amount']);

        // Verify patterns detected
        $this->assertNotEmpty($result['overcharging_patterns']);
        $patternTypes = array_column($result['overcharging_patterns'], 'type');
        $this->assertContains('personal_use_charged_as_dealing', $patternTypes);
        $this->assertContains('no_dealing_evidence', $patternTypes);

        // Verify defense strategies generated
        $this->assertNotEmpty($result['defense_strategy']);
        $strategies = array_column($result['defense_strategy'], 'strategy');
        $this->assertContains('motion_to_reduce_charges', $strategies);

        // Verify recommended charge
        $this->assertEquals('KZ Čl. 173', $result['recommended_charge']);

        // Verify legal violations identified
        $this->assertNotEmpty($result['legal_violations']);
    }

    /** @test */
    public function it_compares_two_regions_with_complete_analysis()
    {
        $mockOdlukeAgent = Mockery::mock(OdlukeSearchAgent::class);
        $mockOpenAI = Mockery::mock(OpenAIService::class);

        // Mock Osijek data (worse)
        $mockOdlukeAgent->shouldReceive('searchHomeSearchCases')
            ->with(Mockery::on(function ($criteria) {
                return $criteria['region'] === 'Osijek' && $criteria['year'] === 2025;
            }))
            ->andReturn([
                'status' => 'real_data',
                'cases' => $this->generateMockCases(47, 'Osijek'),
            ]);

        // Mock Zadar data (better)
        $mockOdlukeAgent->shouldReceive('searchHomeSearchCases')
            ->with(Mockery::on(function ($criteria) {
                return $criteria['region'] === 'Zadar' && $criteria['year'] === 2025;
            }))
            ->andReturn([
                'status' => 'real_data',
                'cases' => $this->generateMockCases(31, 'Zadar'),
            ]);

        // Mock AI extraction
        $mockOpenAI->shouldReceive('chat')
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'drug_type' => 'cannabis',
                        'amount' => 25,
                        'unit' => 'grams',
                        'charged_as' => 'dealing',
                        'evidence_of_dealing' => [],
                        'confidence' => 'high',
                    ])]],
                ],
            ]);

        // Mock regional analysis
        $mockOpenAI->shouldReceive('chat')
            ->with(Mockery::on(function ($messages) {
                return count($messages) === 2 &&
                       $messages[0]['role'] === 'system' &&
                       strpos($messages[1]['content'], 'Compare prosecutorial practices') !== false;
            }), 'gpt-4o', Mockery::any())
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => 'Osijek shows significantly higher overcharging rates...']],
                ],
            ]);

        $detector = new DrugChargeAbuseDetector($mockOpenAI, $mockOdlukeAgent);

        $comparison = $detector->compareRegions('Osijek', 'Zadar', 2025);

        // Verify comparison structure
        $this->assertArrayHasKey('topic', $comparison);
        $this->assertArrayHasKey('year', $comparison);
        $this->assertArrayHasKey('region1', $comparison);
        $this->assertArrayHasKey('region2', $comparison);
        $this->assertArrayHasKey('differences', $comparison);
        $this->assertArrayHasKey('worse_region', $comparison);
        $this->assertArrayHasKey('analysis', $comparison);

        // Verify regions
        $this->assertEquals('Osijek', $comparison['region1']['name']);
        $this->assertEquals('Zadar', $comparison['region2']['name']);

        // Verify statistics present
        $this->assertArrayHasKey('statistics', $comparison['region1']);
        $this->assertArrayHasKey('statistics', $comparison['region2']);

        // Verify worse region determination
        $this->assertArrayHasKey('worse_region', $comparison['worse_region']);
        $this->assertArrayHasKey('analysis', $comparison['worse_region']);
    }

    /** @test */
    public function it_caches_statistics_properly()
    {
        Cache::flush();

        $mockOdlukeAgent = Mockery::mock(OdlukeSearchAgent::class);
        $mockOpenAI = Mockery::mock(OpenAIService::class);

        // Should only be called once (then cached)
        $mockOdlukeAgent->shouldReceive('searchHomeSearchCases')
            ->once()
            ->andReturn([
                'status' => 'real_data',
                'cases' => $this->generateMockCases(47, 'Osijek'),
            ]);

        $mockOpenAI->shouldReceive('chat')
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'drug_type' => 'cannabis',
                        'amount' => 25,
                        'unit' => 'grams',
                        'charged_as' => 'dealing',
                        'evidence_of_dealing' => [],
                        'confidence' => 'high',
                    ])]],
                ],
            ]);

        $detector = new DrugChargeAbuseDetector($mockOpenAI, $mockOdlukeAgent);

        // First call - should hit OdlukeSearchAgent
        $stats1 = $detector->getStatistics(['year' => 2025, 'region' => 'Osijek']);

        // Second call - should use cache (OdlukeSearchAgent won't be called again)
        $stats2 = $detector->getStatistics(['year' => 2025, 'region' => 'Osijek']);

        $this->assertEquals($stats1, $stats2);
    }

    /** @test */
    public function it_handles_multiple_drug_types_in_analysis()
    {
        $case = LegalCase::factory()->create();
        $detector = app(DrugChargeAbuseDetector::class);

        $drugTypes = [
            'cannabis' => ['amount' => 30, 'threshold' => 30],
            'cocaine' => ['amount' => 1, 'threshold' => 1],
            'heroin' => ['amount' => 1, 'threshold' => 1],
            'ecstasy' => ['amount' => 5, 'threshold' => 5],
        ];

        foreach ($drugTypes as $drugType => $data) {
            $result = $detector->analyzeCase($case, [
                'drug_type' => $drugType,
                'amount' => $data['amount'],
                'charged_as' => 'dealing',
                'evidence_of_dealing' => [],
            ]);

            $this->assertTrue($result['overcharge_detected'], "Failed for {$drugType}");
            $this->assertEquals($data['threshold'], $result['threshold_analysis']['threshold_amount']);
            $this->assertTrue($result['threshold_analysis']['personal_use_likely']);
        }
    }

    /** @test */
    public function it_generates_varying_severity_based_on_amount()
    {
        $case = LegalCase::factory()->create();
        $detector = app(DrugChargeAbuseDetector::class);

        // Test different amounts and verify severity increases for smaller amounts
        $amounts = [
            5 => 100,  // 5g = <25% of threshold = highest severity
            15 => 90,  // 15g = 50% of threshold = high severity
            25 => 80,  // 25g = 83% of threshold = medium-high severity
            30 => 70,  // 30g = 100% of threshold = medium severity
        ];

        foreach ($amounts as $amount => $expectedMinSeverity) {
            $result = $detector->analyzeCase($case, [
                'drug_type' => 'cannabis',
                'amount' => $amount,
                'charged_as' => 'dealing',
                'evidence_of_dealing' => [],
            ]);

            $this->assertGreaterThanOrEqual(
                $expectedMinSeverity,
                $result['overcharge_severity'],
                "Severity for {$amount}g should be >= {$expectedMinSeverity}"
            );
        }
    }

    /** @test */
    public function it_does_not_overcharge_when_evidence_present_and_above_threshold()
    {
        $case = LegalCase::factory()->create();
        $detector = app(DrugChargeAbuseDetector::class);

        $result = $detector->analyzeCase($case, [
            'drug_type' => 'cannabis',
            'amount' => 100, // Above threshold (30g)
            'charged_as' => 'dealing',
            'evidence_of_dealing' => ['scales', 'baggies', 'large_cash', 'phone_records'],
        ]);

        $this->assertFalse($result['overcharge_detected']);
        $this->assertEquals(0, $result['overcharge_severity']);
        $this->assertFalse($result['threshold_analysis']['personal_use_likely']);
    }

    /** @test */
    public function it_provides_high_quality_defense_strategies()
    {
        $case = LegalCase::factory()->create();
        $detector = app(DrugChargeAbuseDetector::class);

        $result = $detector->analyzeCase($case, [
            'drug_type' => 'cannabis',
            'amount' => 10, // Very small amount
            'charged_as' => 'dealing',
            'evidence_of_dealing' => [],
        ]);

        $this->assertNotEmpty($result['defense_strategy']);

        $motionStrategy = collect($result['defense_strategy'])
            ->firstWhere('strategy', 'motion_to_reduce_charges');

        $this->assertNotNull($motionStrategy);
        $this->assertEquals('high', $motionStrategy['priority']);
        $this->assertEquals('high', $motionStrategy['likelihood_of_success']);
        $this->assertStringContainsString('KZ Čl. 173', $motionStrategy['title']);
        $this->assertStringContainsString('personal use threshold', $motionStrategy['legal_basis']);
    }

    /**
     * Generate mock case data for testing
     */
    protected function generateMockCases(int $count, string $region): array
    {
        $cases = [];
        for ($i = 0; $i < $count; $i++) {
            $cases[] = [
                'case_id' => "{$region}-{$i}-2025",
                'text' => "Odluka suda. Optuženi iz {$region}a zatečen s 25g marihuane. Optužen za KZ Čl. 190.",
                'region' => $region,
            ];
        }

        return $cases;
    }
}
