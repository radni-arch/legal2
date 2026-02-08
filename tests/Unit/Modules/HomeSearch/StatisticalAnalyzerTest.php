<?php

namespace Tests\Unit\Modules\HomeSearch;

use App\Modules\HomeSearch\Services\OdlukeSearchAgent;
use App\Modules\HomeSearch\Services\StatisticalAnalyzer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Comprehensive test suite for StatisticalAnalyzer
 *
 * This test suite covers:
 * - Regional comparisons (Osijek vs national average vs Zagreb vs Split)
 * - Trend analysis (year-over-year trends, temporal patterns)
 * - Statistical significance (percentages, rates, problem levels)
 * - Real data integration with OdlukeSearchAgent
 * - Simulated data generation and structure
 * - Caching behavior
 * - Problem level categorization thresholds
 *
 * The StatisticalAnalyzer is the "offensive statistics agent" that reveals
 * patterns of abuse in Croatian home search warrant practices.
 */
class StatisticalAnalyzerTest extends TestCase
{
    use UsesTestDatabase;

    protected StatisticalAnalyzer $analyzer;

    protected $odlukeAgentMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->odlukeAgentMock = Mockery::mock(OdlukeSearchAgent::class);

        // Mock Log facade to prevent actual logging
        Log::shouldReceive('info')->byDefault();
        Log::shouldReceive('debug')->byDefault();
        Log::shouldReceive('warning')->byDefault();
        Log::shouldReceive('error')->byDefault();

        $this->analyzer = new StatisticalAnalyzer($this->odlukeAgentMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ========================================================================
    // REGIONAL COMPARISON TESTS
    // ========================================================================

    /** @test */
    public function it_compares_osijek_to_national_average()
    {
        Cache::shouldReceive('remember')->andReturnUsing(function ($key, $ttl, $callback) {
            return $callback();
        });

        $this->odlukeAgentMock->shouldReceive('searchHomeSearchCases')
            ->andReturn(['status' => 'framework_mode']);

        $osijeStats = $this->analyzer->getYearlyStatistics(2025, ['region' => 'Osijek']);
        $nationalStats = $this->analyzer->getYearlyStatistics(2025, ['region' => 'nationwide']);

        // Osijek should have higher misdemeanor percentage
        $this->assertGreaterThan(
            $nationalStats['summary']['percentage_misdemeanor'],
            $osijeStats['summary']['percentage_misdemeanor']
        );

        // Osijek should have higher searches per 100k
        $this->assertGreaterThan(
            $nationalStats['summary']['searches_per_100k_population'],
            $osijeStats['summary']['searches_per_100k_population']
        );

        // Osijek should have lower evidence found rate
        $this->assertLessThan(
            $nationalStats['summary']['evidence_found_rate'],
            $osijeStats['summary']['evidence_found_rate']
        );

        // Osijek should have higher suppression rate
        $this->assertGreaterThan(
            $nationalStats['summary']['suppression_rate'],
            $osijeStats['summary']['suppression_rate']
        );
    }

    /** @test */
    public function it_identifies_osijek_as_worst_region()
    {
        Cache::shouldReceive('remember')->andReturnUsing(function ($key, $ttl, $callback) {
            return $callback();
        });

        $this->odlukeAgentMock->shouldReceive('searchHomeSearchCases')
            ->andReturn(['status' => 'framework_mode']);

        $stats = $this->analyzer->getYearlyStatistics(2025, ['region' => 'Osijek']);

        $regions = $stats['by_region']['regions'];
        $osijeRegion = collect($regions)->firstWhere('region', 'Osijek-Baranja');

        $this->assertEquals(1, $osijeRegion['ranking']); // Worst (rank 1)
        $this->assertEquals('extreme', $osijeRegion['problem_level']);
        $this->assertGreaterThan(30, $osijeRegion['misdemeanor_percentage']);
    }

    /** @test */
    public function it_shows_regional_disparities_in_searches_per_capita()
    {
        Cache::shouldReceive('remember')->andReturnUsing(function ($key, $ttl, $callback) {
            return $callback();
        });

        $this->odlukeAgentMock->shouldReceive('searchHomeSearchCases')
            ->andReturn(['status' => 'framework_mode']);

        $stats = $this->analyzer->getYearlyStatistics(2025);

        $regions = $stats['by_region']['regions'];

        $osijek = collect($regions)->firstWhere('region', 'Osijek-Baranja');
        $zagreb = collect($regions)->firstWhere('region', 'Zagreb');
        $split = collect($regions)->firstWhere('region', 'Split-Dalmacija');

        // Osijek should have highest per capita rate
        $this->assertGreaterThan($zagreb['searches_per_100k'], $osijek['searches_per_100k']);
        $this->assertGreaterThan($split['searches_per_100k'], $osijek['searches_per_100k']);
    }

    /** @test */
    public function it_identifies_geographic_targeting_patterns()
    {
        Cache::shouldReceive('remember')->andReturnUsing(function ($key, $ttl, $callback) {
            return $callback();
        });

        $this->odlukeAgentMock->shouldReceive('searchHomeSearchCases')
            ->andReturn(['status' => 'framework_mode']);

        $stats = $this->analyzer->getYearlyStatistics(2025);

        $targeting = $stats['by_region']['geographic_targeting'];

        $this->assertArrayHasKey('worst_regions', $targeting);
        $this->assertArrayHasKey('best_regions', $targeting);
        $this->assertContains('Osijek-Baranja', $targeting['worst_regions']);
        $this->assertStringContainsString('Istočna Hrvatska', $targeting['pattern']);
    }

    /** @test */
    public function it_ranks_regions_by_problem_severity()
    {
        Cache::shouldReceive('remember')->andReturnUsing(function ($key, $ttl, $callback) {
            return $callback();
        });

        $this->odlukeAgentMock->shouldReceive('searchHomeSearchCases')
            ->andReturn(['status' => 'framework_mode']);

        $stats = $this->analyzer->getYearlyStatistics(2025);

        $regions = $stats['by_region']['regions'];

        // Verify ranking system exists
        $ranked = collect($regions)->whereNotNull('ranking');
        $this->assertGreaterThan(0, $ranked->count());

        // Verify problem levels are assigned
        foreach ($regions as $region) {
            if ($region['region'] !== 'National Average') {
                $this->assertContains($region['problem_level'], ['low', 'moderate', 'high', 'extreme']);
            }
        }
    }

    // ========================================================================
    // TREND ANALYSIS TESTS
    // ========================================================================

    /** @test */
    public function it_analyzes_year_over_year_trends()
    {
        Cache::shouldReceive('remember')->andReturnUsing(function ($key, $ttl, $callback) {
            return $callback();
        });

        $this->odlukeAgentMock->shouldReceive('searchHomeSearchCases')
            ->andReturn(['status' => 'framework_mode']);

        $stats = $this->analyzer->getYearlyStatistics(2025);

        $trends = $stats['temporal_trends']['year_over_year'];

        $this->assertCount(3, $trends); // 2023, 2024, 2025
        $this->assertEquals(2023, $trends[0]['year']);
        $this->assertEquals(2024, $trends[1]['year']);
        $this->assertEquals(2025, $trends[2]['year']);

        // Verify data structure
        foreach ($trends as $yearData) {
            $this->assertArrayHasKey('total_searches', $yearData);
            $this->assertArrayHasKey('misdemeanor_percentage', $yearData);
        }
    }

    /** @test */
    public function it_identifies_increasing_misdemeanor_trend()
    {
        Cache::shouldReceive('remember')->andReturnUsing(function ($key, $ttl, $callback) {
            return $callback();
        });

        $this->odlukeAgentMock->shouldReceive('searchHomeSearchCases')
            ->andReturn(['status' => 'framework_mode']);

        $stats = $this->analyzer->getYearlyStatistics(2025);

        $trends = $stats['temporal_trends']['year_over_year'];

        // Verify increasing trend (2023 < 2024 < 2025)
        $this->assertLessThan($trends[2]['misdemeanor_percentage'], $trends[1]['misdemeanor_percentage']);
        $this->assertLessThan($trends[1]['misdemeanor_percentage'], $trends[0]['misdemeanor_percentage']);

        $this->assertEquals('increasing', $stats['temporal_trends']['trend']);
    }

    /** @test */
    public function it_analyzes_monthly_distribution_patterns()
    {
        Cache::shouldReceive('remember')->andReturnUsing(function ($key, $ttl, $callback) {
            return $callback();
        });

        $this->odlukeAgentMock->shouldReceive('searchHomeSearchCases')
            ->andReturn(['status' => 'framework_mode']);

        $stats = $this->analyzer->getYearlyStatistics(2025);

        $monthly = $stats['temporal_trends']['monthly_distribution'];

        $this->assertArrayHasKey('highest_month', $monthly);
        $this->assertArrayHasKey('lowest_month', $monthly);
        $this->assertArrayHasKey('pattern', $monthly);
        $this->assertEquals('March', $monthly['highest_month']);
        $this->assertEquals('August', $monthly['lowest_month']);
    }

    /** @test */
    public function it_calculates_trend_magnitude_over_three_years()
    {
        Cache::shouldReceive('remember')->andReturnUsing(function ($key, $ttl, $callback) {
            return $callback();
        });

        $this->odlukeAgentMock->shouldReceive('searchHomeSearchCases')
            ->andReturn(['status' => 'framework_mode']);

        $stats = $this->analyzer->getYearlyStatistics(2025);

        $trends = $stats['temporal_trends']['year_over_year'];

        // Calculate increase from 2023 to 2025
        $increase2023to2025 = $trends[2]['misdemeanor_percentage'] - $trends[0]['misdemeanor_percentage'];
        $this->assertGreaterThan(0, $increase2023to2025); // Positive increase
        $this->assertGreaterThan(3, $increase2023to2025); // At least 3 percentage points increase
    }

    // ========================================================================
    // STATISTICAL SIGNIFICANCE TESTS
    // ========================================================================

    /** @test */
    public function it_calculates_percentage_of_misdemeanor_searches()
    {
        Cache::shouldReceive('remember')->andReturnUsing(function ($key, $ttl, $callback) {
            return $callback();
        });

        $this->odlukeAgentMock->shouldReceive('searchHomeSearchCases')
            ->andReturn(['status' => 'framework_mode']);

        $osijeStats = $this->analyzer->getYearlyStatistics(2025, ['region' => 'Osijek']);

        $percentage = $osijeStats['summary']['percentage_misdemeanor'];

        $this->assertIsFloat($percentage);
        $this->assertGreaterThan(20, $percentage); // > 20% is problematic
        $this->assertLessThan(100, $percentage);
    }

    /** @test */
    public function it_calculates_success_rates_by_offense_severity()
    {
        Cache::shouldReceive('remember')->andReturnUsing(function ($key, $ttl, $callback) {
            return $callback();
        });

        $this->odlukeAgentMock->shouldReceive('searchHomeSearchCases')
            ->andReturn(['status' => 'framework_mode']);

        $stats = $this->analyzer->getYearlyStatistics(2025);

        $successRates = $stats['success_rates']['by_offense_severity'];

        $this->assertCount(4, $successRates);

        // Verify success rates increase with severity
        $misdemeanor = collect($successRates)->firstWhere('severity', 'misdemeanor');
        $serious = collect($successRates)->firstWhere('severity', 'serious_criminal');

        $this->assertLessThan($serious['success_rate'], $misdemeanor['success_rate']);
    }

    /** @test */
    public function it_identifies_low_success_rates_as_fishing_expeditions()
    {
        Cache::shouldReceive('remember')->andReturnUsing(function ($key, $ttl, $callback) {
            return $callback();
        });

        $this->odlukeAgentMock->shouldReceive('searchHomeSearchCases')
            ->andReturn(['status' => 'framework_mode']);

        $stats = $this->analyzer->getYearlyStatistics(2025, ['region' => 'Osijek']);

        $fishingExpeditions = $stats['success_rates']['fishing_expeditions'];

        $this->assertTrue($fishingExpeditions['identified']);
        $this->assertStringContainsString('fishing expeditions', $stats['success_rates']['analysis']);
    }

    /** @test */
    public function it_calculates_suppression_rates()
    {
        Cache::shouldReceive('remember')->andReturnUsing(function ($key, $ttl, $callback) {
            return $callback();
        });

        $this->odlukeAgentMock->shouldReceive('searchHomeSearchCases')
            ->andReturn(['status' => 'framework_mode']);

        $stats = $this->analyzer->getYearlyStatistics(2025);

        $suppressionRate = $stats['summary']['suppression_rate'];

        $this->assertIsFloat($suppressionRate);
        $this->assertGreaterThan(0, $suppressionRate);
        $this->assertLessThan(100, $suppressionRate);
    }

    /** @test */
    public function it_categorizes_problem_levels_for_misdemeanor_percentages()
    {
        $analyzer = new class($this->odlukeAgentMock) extends StatisticalAnalyzer
        {
            public function exposeConvertOffenseTypeBreakdown(array $types, int $total): array
            {
                return $this->convertOffenseTypeBreakdown($types, $total);
            }
        };

        // Test extreme level (>30%)
        $result1 = $analyzer->exposeConvertOffenseTypeBreakdown(['prekršaj' => 35], 100);
        $this->assertEquals('extreme', $result1[0]['problem_level']);

        // Test high level (20-30%)
        $result2 = $analyzer->exposeConvertOffenseTypeBreakdown(['prekršaj' => 25], 100);
        $this->assertEquals('high', $result2[0]['problem_level']);

        // Test moderate level (10-20%)
        $result3 = $analyzer->exposeConvertOffenseTypeBreakdown(['prekršaj' => 15], 100);
        $this->assertEquals('moderate', $result3[0]['problem_level']);

        // Test justified level (<10%)
        $result4 = $analyzer->exposeConvertOffenseTypeBreakdown(['prekršaj' => 5], 100);
        $this->assertEquals('justified', $result4[0]['problem_level']);
    }

    /** @test */
    public function it_analyzes_constitutional_complaint_success_rates()
    {
        Cache::shouldReceive('remember')->andReturnUsing(function ($key, $ttl, $callback) {
            return $callback();
        });

        $this->odlukeAgentMock->shouldReceive('searchHomeSearchCases')
            ->andReturn(['status' => 'framework_mode']);

        $stats = $this->analyzer->getYearlyStatistics(2025);

        $constitutional = $stats['constitutional_challenges'];

        $this->assertArrayHasKey('suppression_success_rate', $constitutional);
        $this->assertArrayHasKey('violations_found', $constitutional);

        $successRate = $constitutional['suppression_success_rate'];
        $this->assertGreaterThan(0, $successRate);
        $this->assertLessThan(100, $successRate);
    }

    // ========================================================================
    // REAL DATA INTEGRATION TESTS
    // ========================================================================

    /** @test */
    public function it_fetches_real_data_from_odluke_agent()
    {
        Cache::shouldReceive('remember')->andReturnUsing(function ($key, $ttl, $callback) {
            return $callback();
        });

        $mockCases = [
            [
                'case_number' => 'K-1/2025',
                'court' => 'Općinski sud u Osijeku',
                'offense_type' => 'prekršaj',
                'date' => '2025-01-15',
                'evidence_found' => true,
                'evidence_suppressed' => false,
                'proportionality_mentioned' => false,
                'constitutional_rights_mentioned' => false,
            ],
            [
                'case_number' => 'K-2/2025',
                'court' => 'Općinski sud u Osijeku',
                'offense_type' => 'kazneno_djelo',
                'date' => '2025-02-20',
                'evidence_found' => false,
                'evidence_suppressed' => false,
                'proportionality_mentioned' => true,
                'constitutional_rights_mentioned' => true,
            ],
        ];

        $this->odlukeAgentMock->shouldReceive('searchHomeSearchCases')
            ->with(Mockery::type('array'))
            ->andReturn([
                'status' => 'real_data',
                'cases' => $mockCases,
            ]);

        $this->odlukeAgentMock->shouldReceive('analyzeExtractedCases')
            ->with($mockCases)
            ->andReturn([
                'total_cases' => 2,
                'by_offense_type' => ['prekršaj' => 1, 'kazneno_djelo' => 1],
                'by_court' => ['Općinski sud u Osijeku' => 2],
                'by_year' => ['2025' => 2],
                'evidence_found_rate' => 50.0,
                'suppression_rate' => 0.0,
                'proportionality_issues' => 1,
                'constitutional_issues' => 1,
                'alarming_findings' => [],
            ]);

        $stats = $this->analyzer->getYearlyStatistics(2025, ['region' => 'Osijek']);

        $this->assertEquals('real_data_retrieved', $stats['status']);
        $this->assertArrayHasKey('raw_cases', $stats);
        $this->assertCount(2, $stats['raw_cases']);
    }

    /** @test */
    public function it_falls_back_to_simulated_data_when_real_data_unavailable()
    {
        Cache::shouldReceive('remember')->andReturnUsing(function ($key, $ttl, $callback) {
            return $callback();
        });

        $this->odlukeAgentMock->shouldReceive('searchHomeSearchCases')
            ->andReturn(['status' => 'framework_mode']);

        $stats = $this->analyzer->getYearlyStatistics(2025);

        $this->assertEquals('simulated', $stats['summary']['data_completeness']);
        $this->assertStringContainsString('framework', $stats['summary']['note']);
    }

    /** @test */
    public function it_converts_agent_analysis_to_statistics_format()
    {
        $analyzer = new class($this->odlukeAgentMock) extends StatisticalAnalyzer
        {
            public function exposeConvertAgentAnalysisToStatistics($analysis, $year, $filters, $cases): array
            {
                return $this->convertAgentAnalysisToStatistics($analysis, $year, $filters, $cases);
            }
        };

        $agentAnalysis = [
            'total_cases' => 100,
            'by_offense_type' => ['prekršaj' => 30, 'kazneno_djelo' => 70],
            'by_court' => ['Općinski sud u Osijeku' => 60, 'Županijski sud' => 40],
            'by_year' => ['2025' => 100],
            'evidence_found_rate' => 55.5,
            'suppression_rate' => 8.2,
            'proportionality_issues' => 12,
            'constitutional_issues' => 7,
            'alarming_findings' => ['Test finding'],
        ];

        $result = $analyzer->exposeConvertAgentAnalysisToStatistics(
            $agentAnalysis,
            2025,
            ['region' => 'Osijek'],
            []
        );

        $this->assertEquals(2025, $result['year']);
        $this->assertEquals(100, $result['summary']['total_home_searches']);
        $this->assertEquals(30, $result['summary']['misdemeanor_based_searches']);
        $this->assertEquals(30.0, $result['summary']['percentage_misdemeanor']);
        $this->assertEquals('real_data_retrieved', $result['status']);
        $this->assertStringContainsString('REAL DATA', $result['data_sources']['odluke.sudovi.hr']);
    }

    /** @test */
    public function it_handles_empty_case_data_gracefully()
    {
        Cache::shouldReceive('remember')->andReturnUsing(function ($key, $ttl, $callback) {
            return $callback();
        });

        $this->odlukeAgentMock->shouldReceive('searchHomeSearchCases')
            ->andReturn([
                'status' => 'real_data',
                'cases' => [], // Empty cases
            ]);

        Log::shouldReceive('warning')
            ->once()
            ->with('StatisticalAnalyzer: No cases found in search results');

        $stats = $this->analyzer->getYearlyStatistics(2025);

        // Should fall back to simulated data
        $this->assertEquals('simulated', $stats['summary']['data_completeness']);
    }

    // ========================================================================
    // CACHING TESTS
    // ========================================================================

    /** @test */
    public function it_caches_yearly_statistics_for_24_hours()
    {
        $cacheKey = 'home_search_stats_2025_'.md5(json_encode([]));

        Cache::shouldReceive('remember')
            ->once()
            ->with($cacheKey, 86400, Mockery::type('Closure'))
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $this->odlukeAgentMock->shouldReceive('searchHomeSearchCases')
            ->andReturn(['status' => 'framework_mode']);

        $this->analyzer->getYearlyStatistics(2025);
    }

    /** @test */
    public function it_generates_unique_cache_keys_for_different_filters()
    {
        $cacheKeys = [];

        Cache::shouldReceive('remember')
            ->times(3)
            ->andReturnUsing(function ($key, $ttl, $callback) use (&$cacheKeys) {
                $cacheKeys[] = $key;

                return $callback();
            });

        $this->odlukeAgentMock->shouldReceive('searchHomeSearchCases')
            ->times(3)
            ->andReturn(['status' => 'framework_mode']);

        $this->analyzer->getYearlyStatistics(2025, ['region' => 'Osijek']);
        $this->analyzer->getYearlyStatistics(2025, ['region' => 'Zagreb']);
        $this->analyzer->getYearlyStatistics(2025, ['offense_type' => 'prekršaj']);

        // Verify all cache keys are unique
        $this->assertCount(3, array_unique($cacheKeys));
    }

    // ========================================================================
    // OFFENSE SEVERITY ANALYSIS TESTS
    // ========================================================================

    /** @test */
    public function it_analyzes_searches_by_offense_severity()
    {
        Cache::shouldReceive('remember')->andReturnUsing(function ($key, $ttl, $callback) {
            return $callback();
        });

        $this->odlukeAgentMock->shouldReceive('searchHomeSearchCases')
            ->andReturn(['status' => 'framework_mode']);

        $stats = $this->analyzer->getYearlyStatistics(2025);

        $breakdown = $stats['by_offense_severity']['breakdown'];

        $this->assertCount(4, $breakdown); // 4 severity levels

        $severities = array_column($breakdown, 'severity');
        $this->assertContains('misdemeanor', $severities);
        $this->assertContains('minor_criminal', $severities);
        $this->assertContains('medium_criminal', $severities);
        $this->assertContains('serious_criminal', $severities);
    }

    /** @test */
    public function it_identifies_extreme_misdemeanor_problem_level()
    {
        Cache::shouldReceive('remember')->andReturnUsing(function ($key, $ttl, $callback) {
            return $callback();
        });

        $this->odlukeAgentMock->shouldReceive('searchHomeSearchCases')
            ->andReturn(['status' => 'framework_mode']);

        $stats = $this->analyzer->getYearlyStatistics(2025, ['region' => 'Osijek']);

        $breakdown = $stats['by_offense_severity']['breakdown'];
        $misdemeanor = collect($breakdown)->firstWhere('severity', 'misdemeanor');

        $this->assertEquals('extreme', $misdemeanor['problem_level']);
        $this->assertGreaterThan(30, $misdemeanor['percentage']);
    }

    /** @test */
    public function it_provides_analysis_and_recommendations()
    {
        Cache::shouldReceive('remember')->andReturnUsing(function ($key, $ttl, $callback) {
            return $callback();
        });

        $this->odlukeAgentMock->shouldReceive('searchHomeSearchCases')
            ->andReturn(['status' => 'framework_mode']);

        $stats = $this->analyzer->getYearlyStatistics(2025, ['region' => 'Osijek']);

        $this->assertArrayHasKey('analysis', $stats['by_offense_severity']);
        $this->assertArrayHasKey('recommendation', $stats['by_offense_severity']);
        $this->assertStringContainsString('zlouporabe', $stats['by_offense_severity']['analysis']);
    }

    // ========================================================================
    // JUDGE AND PROSECUTOR ANALYSIS TESTS
    // ========================================================================

    /** @test */
    public function it_analyzes_patterns_by_judge()
    {
        Cache::shouldReceive('remember')->andReturnUsing(function ($key, $ttl, $callback) {
            return $callback();
        });

        $this->odlukeAgentMock->shouldReceive('searchHomeSearchCases')
            ->andReturn(['status' => 'framework_mode']);

        $stats = $this->analyzer->getYearlyStatistics(2025, ['region' => 'Osijek']);

        $judges = $stats['by_judge']['top_warrant_issuers'];

        $this->assertGreaterThan(0, count($judges));

        foreach ($judges as $judge) {
            $this->assertArrayHasKey('judge_id', $judge);
            $this->assertArrayHasKey('warrants_issued', $judge);
            $this->assertArrayHasKey('misdemeanor_percentage', $judge);
            $this->assertArrayHasKey('problem_level', $judge);
        }
    }

    /** @test */
    public function it_identifies_judges_with_extreme_misdemeanor_approval_rates()
    {
        Cache::shouldReceive('remember')->andReturnUsing(function ($key, $ttl, $callback) {
            return $callback();
        });

        $this->odlukeAgentMock->shouldReceive('searchHomeSearchCases')
            ->andReturn(['status' => 'framework_mode']);

        $stats = $this->analyzer->getYearlyStatistics(2025, ['region' => 'Osijek']);

        $judges = $stats['by_judge']['top_warrant_issuers'];
        $topJudge = $judges[0];

        $this->assertEquals('extreme', $topJudge['problem_level']);
        $this->assertGreaterThan(40, $topJudge['misdemeanor_percentage']);
    }

    /** @test */
    public function it_analyzes_patterns_by_prosecutor()
    {
        Cache::shouldReceive('remember')->andReturnUsing(function ($key, $ttl, $callback) {
            return $callback();
        });

        $this->odlukeAgentMock->shouldReceive('searchHomeSearchCases')
            ->andReturn(['status' => 'framework_mode']);

        $stats = $this->analyzer->getYearlyStatistics(2025, ['region' => 'Osijek']);

        $prosecutors = $stats['by_prosecutor']['top_warrant_requesters'];

        $this->assertGreaterThan(0, count($prosecutors));

        foreach ($prosecutors as $prosecutor) {
            $this->assertArrayHasKey('prosecutor_office', $prosecutor);
            $this->assertArrayHasKey('warrants_requested', $prosecutor);
            $this->assertArrayHasKey('approval_rate', $prosecutor);
            $this->assertArrayHasKey('misdemeanor_percentage', $prosecutor);
        }
    }

    /** @test */
    public function it_identifies_pattern_of_prosecutorial_misconduct()
    {
        Cache::shouldReceive('remember')->andReturnUsing(function ($key, $ttl, $callback) {
            return $callback();
        });

        $this->odlukeAgentMock->shouldReceive('searchHomeSearchCases')
            ->andReturn(['status' => 'framework_mode']);

        $stats = $this->analyzer->getYearlyStatistics(2025, ['region' => 'Osijek']);

        $misconduct = $stats['by_prosecutor']['pattern_of_misconduct'];

        $this->assertTrue($misconduct['identified']);
        $this->assertEquals('high', $misconduct['severity']);
        $this->assertArrayHasKey('recommended_action', $misconduct);
    }

    // ========================================================================
    // ALARMING FINDINGS TESTS
    // ========================================================================

    /** @test */
    public function it_generates_alarming_findings_for_osijek()
    {
        Cache::shouldReceive('remember')->andReturnUsing(function ($key, $ttl, $callback) {
            return $callback();
        });

        $this->odlukeAgentMock->shouldReceive('searchHomeSearchCases')
            ->andReturn(['status' => 'framework_mode']);

        $stats = $this->analyzer->getYearlyStatistics(2025, ['region' => 'Osijek']);

        $findings = $stats['summary']['alarming_findings'];

        $this->assertIsArray($findings);
        $this->assertGreaterThan(0, count($findings));

        // Should mention Osijek specifically
        $allFindings = implode(' ', $findings);
        $this->assertStringContainsString('Osijek', $allFindings);
    }

    /** @test */
    public function it_identifies_osijek_as_significantly_worse_than_national_average()
    {
        Cache::shouldReceive('remember')->andReturnUsing(function ($key, $ttl, $callback) {
            return $callback();
        });

        $this->odlukeAgentMock->shouldReceive('searchHomeSearchCases')
            ->andReturn(['status' => 'framework_mode']);

        $stats = $this->analyzer->getYearlyStatistics(2025, ['region' => 'Osijek']);

        $findings = $stats['summary']['alarming_findings'];
        $findingsText = implode(' ', $findings);

        // Should mention comparison to national average
        $this->assertStringContainsString('nacionaln', $findingsText);
        $this->assertStringContainsString('%', $findingsText);
    }

    // ========================================================================
    // DATA SOURCE AND STATUS TESTS
    // ========================================================================

    /** @test */
    public function it_lists_croatian_data_sources()
    {
        Cache::shouldReceive('remember')->andReturnUsing(function ($key, $ttl, $callback) {
            return $callback();
        });

        $this->odlukeAgentMock->shouldReceive('searchHomeSearchCases')
            ->andReturn(['status' => 'framework_mode']);

        $stats = $this->analyzer->getYearlyStatistics(2025);

        $sources = $stats['data_sources'];

        $this->assertArrayHasKey('odluke.sudovi.hr', $sources);
        $this->assertArrayHasKey('e-predmet', $sources);
        $this->assertArrayHasKey('DORH_reports', $sources);
    }

    /** @test */
    public function it_marks_data_completeness_status()
    {
        Cache::shouldReceive('remember')->andReturnUsing(function ($key, $ttl, $callback) {
            return $callback();
        });

        $this->odlukeAgentMock->shouldReceive('searchHomeSearchCases')
            ->andReturn(['status' => 'framework_mode']);

        $stats = $this->analyzer->getYearlyStatistics(2025);

        $this->assertArrayHasKey('data_completeness', $stats['summary']);
        $this->assertContains($stats['summary']['data_completeness'], ['simulated', 'partial', 'complete', 'real_data']);
    }

    /** @test */
    public function it_includes_data_collection_timestamp()
    {
        Cache::shouldReceive('remember')->andReturnUsing(function ($key, $ttl, $callback) {
            return $callback();
        });

        $this->odlukeAgentMock->shouldReceive('searchHomeSearchCases')
            ->andReturn(['status' => 'framework_mode']);

        $stats = $this->analyzer->getYearlyStatistics(2025);

        $this->assertArrayHasKey('data_collection_date', $stats['summary']);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $stats['summary']['data_collection_date']);
    }

    // ========================================================================
    // PATTERN SEARCH TESTS
    // ========================================================================

    /** @test */
    public function it_searches_for_specific_patterns()
    {
        $result = $this->analyzer->searchPatterns([
            'offense_type' => 'prekršaj',
            'region' => 'Osijek',
            'year' => 2025,
        ]);

        $this->assertArrayHasKey('search_criteria', $result);
        $this->assertArrayHasKey('results_found', $result);
        $this->assertEquals('prekršaj', $result['search_criteria']['offense_type']);
    }

    /** @test */
    public function it_provides_implementation_guidance_for_pattern_search()
    {
        $result = $this->analyzer->searchPatterns(['test' => 'criteria']);

        $this->assertArrayHasKey('implementation_needed', $result);
        $this->assertArrayHasKey('odluke_sudovi_hr_scraper', $result['implementation_needed']);
        $this->assertArrayHasKey('e_predmet_api', $result['implementation_needed']);
    }
}
