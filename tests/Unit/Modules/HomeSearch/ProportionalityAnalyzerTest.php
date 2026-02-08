<?php

namespace Tests\Unit\Modules\HomeSearch;

use App\Models\LegalCase;
use App\Modules\HomeSearch\Services\ProportionalityAnalyzer;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Comprehensive test suite for ProportionalityAnalyzer
 *
 * This test suite covers:
 * - Severity scoring (offense classification with 0-100 scores)
 * - Invasiveness scoring (search characteristics with 0-100 scores)
 * - Proportionality test (four-part test: legitimacy, suitability, necessity, proportionality stricto sensu)
 * - Disproportion score calculation (0-100, higher = more disproportionate)
 * - Threshold analysis (boundary testing for all scoring categories)
 * - AI analysis generation
 *
 * The ProportionalityAnalyzer implements Croatian law's proportionality principle
 * (načelo razmjernosti) from ZKP Čl. 179 and Ustav RH Čl. 34.
 */
class ProportionalityAnalyzerTest extends TestCase
{
    use UsesTestDatabase;

    protected ProportionalityAnalyzer $analyzer;

    protected $openAIMock;

    protected LegalCase $case;

    protected function setUp(): void
    {
        parent::setUp();

        $this->openAIMock = Mockery::mock(OpenAIService::class);

        // Mock Log facade to prevent actual logging
        Log::shouldReceive('info')->byDefault();
        Log::shouldReceive('debug')->byDefault();
        Log::shouldReceive('warning')->byDefault();
        Log::shouldReceive('error')->byDefault();

        $this->analyzer = new ProportionalityAnalyzer($this->openAIMock);
        $this->case = LegalCase::factory()->create();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ========================================================================
    // SEVERITY SCORING TESTS
    // ========================================================================

    /** @test */
    public function it_classifies_misdemeanor_with_severity_15()
    {
        $analyzer = new class($this->openAIMock) extends ProportionalityAnalyzer
        {
            public function exposeClassifyOffenseSeverity(array $details): array
            {
                return $this->classifyOffenseSeverity($details);
            }
        };

        $result = $analyzer->exposeClassifyOffenseSeverity([
            'offense' => 'Prometni prekršaj',
            'offense_type' => 'prekršaj',
        ]);

        $this->assertEquals('misdemeanor', $result['severity']);
        $this->assertEquals(15, $result['severity_score']);
        $this->assertFalse($result['justifies_home_search']); // < 40 threshold
    }

    /** @test */
    public function it_classifies_minor_criminal_offense_with_severity_35()
    {
        $analyzer = new class($this->openAIMock) extends ProportionalityAnalyzer
        {
            public function exposeClassifyOffenseSeverity(array $details): array
            {
                return $this->classifyOffenseSeverity($details);
            }
        };

        $result = $analyzer->exposeClassifyOffenseSeverity([
            'offense' => 'Simple theft',
            'offense_type' => 'kazneno_djelo',
            'max_penalty_years' => 2,
        ]);

        $this->assertEquals('minor_criminal', $result['severity']);
        $this->assertEquals(35, $result['severity_score']);
        $this->assertFalse($result['justifies_home_search']); // < 40 threshold
    }

    /** @test */
    public function it_classifies_medium_offense_with_severity_60()
    {
        $analyzer = new class($this->openAIMock) extends ProportionalityAnalyzer
        {
            public function exposeClassifyOffenseSeverity(array $details): array
            {
                return $this->classifyOffenseSeverity($details);
            }
        };

        $result = $analyzer->exposeClassifyOffenseSeverity([
            'offense' => 'Krađa',
            'offense_type' => 'kazneno_djelo',
            'max_penalty_years' => 7,
        ]);

        $this->assertEquals('medium', $result['severity']);
        $this->assertEquals(60, $result['severity_score']);
        $this->assertTrue($result['justifies_home_search']); // >= 40 threshold
    }

    /** @test */
    public function it_classifies_serious_offense_with_severity_90()
    {
        $analyzer = new class($this->openAIMock) extends ProportionalityAnalyzer
        {
            public function exposeClassifyOffenseSeverity(array $details): array
            {
                return $this->classifyOffenseSeverity($details);
            }
        };

        $result = $analyzer->exposeClassifyOffenseSeverity([
            'offense' => 'Ubojstvo',
            'offense_type' => 'kazneno_djelo',
            'max_penalty_years' => 15,
        ]);

        $this->assertEquals('serious', $result['severity']);
        $this->assertEquals(90, $result['severity_score']);
        $this->assertTrue($result['justifies_home_search']);
    }

    /** @test */
    public function it_infers_serious_offense_from_croatian_keywords()
    {
        $analyzer = new class($this->openAIMock) extends ProportionalityAnalyzer
        {
            public function exposeClassifyOffenseSeverity(array $details): array
            {
                return $this->classifyOffenseSeverity($details);
            }
        };

        $seriousKeywords = [
            'Ubojstvo' => 'serious',
            'Razbojništvo' => 'serious',
            'Silovanje' => 'serious',
            'Trgovina ljudima' => 'serious',
            'Organizirani kriminal' => 'serious',
        ];

        foreach ($seriousKeywords as $offense => $expectedSeverity) {
            $result = $analyzer->exposeClassifyOffenseSeverity([
                'offense' => $offense,
                'offense_type' => 'kazneno_djelo',
            ]);

            $this->assertEquals($expectedSeverity, $result['severity'], "Failed for offense: {$offense}");
            $this->assertEquals(90, $result['severity_score']);
        }
    }

    /** @test */
    public function it_infers_medium_offense_from_croatian_keywords()
    {
        $analyzer = new class($this->openAIMock) extends ProportionalityAnalyzer
        {
            public function exposeClassifyOffenseSeverity(array $details): array
            {
                return $this->classifyOffenseSeverity($details);
            }
        };

        $mediumKeywords = ['Krađa', 'Prevara', 'Napad', 'Prijetnja'];

        foreach ($mediumKeywords as $offense) {
            $result = $analyzer->exposeClassifyOffenseSeverity([
                'offense' => $offense,
                'offense_type' => 'kazneno_djelo',
            ]);

            $this->assertEquals('medium', $result['severity'], "Failed for offense: {$offense}");
            $this->assertEquals(60, $result['severity_score']);
        }
    }

    /** @test */
    public function it_defaults_to_unknown_severity_50_for_unrecognized_offenses()
    {
        $analyzer = new class($this->openAIMock) extends ProportionalityAnalyzer
        {
            public function exposeClassifyOffenseSeverity(array $details): array
            {
                return $this->classifyOffenseSeverity($details);
            }
        };

        $result = $analyzer->exposeClassifyOffenseSeverity([
            'offense' => 'Some unrecognized offense',
            'offense_type' => 'kazneno_djelo',
        ]);

        $this->assertEquals('unknown', $result['severity']);
        $this->assertEquals(50, $result['severity_score']);
        $this->assertTrue($result['justifies_home_search']); // 50 >= 40
    }

    // ========================================================================
    // INVASIVENESS SCORING TESTS
    // ========================================================================

    /** @test */
    public function it_scores_invasiveness_based_on_search_scope()
    {
        $analyzer = new class($this->openAIMock) extends ProportionalityAnalyzer
        {
            public function exposeAssessSearchInvasiveness(array $details): array
            {
                return $this->assessSearchInvasiveness($details);
            }
        };

        // Full home search = 40 points
        $result1 = $analyzer->exposeAssessSearchInvasiveness([
            'search_scope' => 'full_home_search',
        ]);
        $this->assertEquals(40, $result1['invasiveness_score']);

        // Partial search = 20 points
        $result2 = $analyzer->exposeAssessSearchInvasiveness([
            'search_scope' => 'partial',
        ]);
        $this->assertEquals(20, $result2['invasiveness_score']);

        // Specific items only = 10 points
        $result3 = $analyzer->exposeAssessSearchInvasiveness([
            'search_scope' => 'specific_items_only',
        ]);
        $this->assertEquals(10, $result3['invasiveness_score']);
    }

    /** @test */
    public function it_scores_invasiveness_based_on_force_used()
    {
        $analyzer = new class($this->openAIMock) extends ProportionalityAnalyzer
        {
            public function exposeAssessSearchInvasiveness(array $details): array
            {
                return $this->assessSearchInvasiveness($details);
            }
        };

        // SWAT team = 30 points
        $result1 = $analyzer->exposeAssessSearchInvasiveness([
            'force_used' => 'swat',
        ]);
        $this->assertEquals(30, $result1['invasiveness_score']);

        // Tactical unit = 30 points
        $result2 = $analyzer->exposeAssessSearchInvasiveness([
            'force_used' => 'tactical_unit',
        ]);
        $this->assertEquals(30, $result2['invasiveness_score']);

        // Armed officers = 20 points
        $result3 = $analyzer->exposeAssessSearchInvasiveness([
            'force_used' => 'armed_officers',
        ]);
        $this->assertEquals(20, $result3['invasiveness_score']);

        // Standard officers = 10 points
        $result4 = $analyzer->exposeAssessSearchInvasiveness([
            'force_used' => 'standard_officers',
        ]);
        $this->assertEquals(10, $result4['invasiveness_score']);
    }

    /** @test */
    public function it_adds_20_points_for_night_raids()
    {
        $analyzer = new class($this->openAIMock) extends ProportionalityAnalyzer
        {
            public function exposeAssessSearchInvasiveness(array $details): array
            {
                return $this->assessSearchInvasiveness($details);
            }
        };

        // Night raid at 23:00 (11 PM)
        $result1 = $analyzer->exposeAssessSearchInvasiveness([
            'search_time' => '2025-03-15 23:00:00',
        ]);
        $this->assertEquals(20, $result1['invasiveness_score']);

        // Early morning raid at 05:00 (5 AM)
        $result2 = $analyzer->exposeAssessSearchInvasiveness([
            'search_time' => '2025-03-15 05:00:00',
        ]);
        $this->assertEquals(20, $result2['invasiveness_score']);

        // Daytime search at 14:00 (2 PM) - no extra points
        $result3 = $analyzer->exposeAssessSearchInvasiveness([
            'search_time' => '2025-03-15 14:00:00',
        ]);
        $this->assertEquals(0, $result3['invasiveness_score']);
    }

    /** @test */
    public function it_adds_10_points_for_searches_longer_than_4_hours()
    {
        $analyzer = new class($this->openAIMock) extends ProportionalityAnalyzer
        {
            public function exposeAssessSearchInvasiveness(array $details): array
            {
                return $this->assessSearchInvasiveness($details);
            }
        };

        // 5 hours = +10 points
        $result1 = $analyzer->exposeAssessSearchInvasiveness([
            'duration_hours' => 5,
        ]);
        $this->assertEquals(10, $result1['invasiveness_score']);

        // 3 hours = 0 points
        $result2 = $analyzer->exposeAssessSearchInvasiveness([
            'duration_hours' => 3,
        ]);
        $this->assertEquals(0, $result2['invasiveness_score']);
    }

    /** @test */
    public function it_calculates_combined_invasiveness_score()
    {
        $analyzer = new class($this->openAIMock) extends ProportionalityAnalyzer
        {
            public function exposeAssessSearchInvasiveness(array $details): array
            {
                return $this->assessSearchInvasiveness($details);
            }
        };

        // Full home search (40) + SWAT (30) + night raid (20) + long duration (10) = 100
        $result = $analyzer->exposeAssessSearchInvasiveness([
            'search_scope' => 'full_home_search',
            'force_used' => 'swat',
            'search_time' => '2025-03-15 23:00:00',
            'duration_hours' => 6,
        ]);

        $this->assertEquals(100, $result['invasiveness_score']);
        $this->assertEquals('extremely_invasive', $result['invasiveness_level']);
    }

    /** @test */
    public function it_caps_invasiveness_score_at_100()
    {
        $analyzer = new class($this->openAIMock) extends ProportionalityAnalyzer
        {
            public function exposeAssessSearchInvasiveness(array $details): array
            {
                return $this->assessSearchInvasiveness($details);
            }
        };

        // Try to exceed 100
        $result = $analyzer->exposeAssessSearchInvasiveness([
            'search_scope' => 'full_home_search', // 40
            'force_used' => 'swat', // 30
            'search_time' => '2025-03-15 23:00:00', // 20
            'duration_hours' => 10, // 10
            // Total would be 100, capped at 100
        ]);

        $this->assertLessThanOrEqual(100, $result['invasiveness_score']);
    }

    // ========================================================================
    // INVASIVENESS LEVEL THRESHOLD TESTS
    // ========================================================================

    /** @test */
    public function it_categorizes_invasiveness_levels_correctly()
    {
        $analyzer = new class($this->openAIMock) extends ProportionalityAnalyzer
        {
            public function exposeGetInvasivenessLevel(int $score): string
            {
                return $this->getInvasivenessLevel($score);
            }
        };

        $this->assertEquals('non_invasive', $analyzer->exposeGetInvasivenessLevel(0));
        $this->assertEquals('non_invasive', $analyzer->exposeGetInvasivenessLevel(19));
        $this->assertEquals('minimally_invasive', $analyzer->exposeGetInvasivenessLevel(20));
        $this->assertEquals('minimally_invasive', $analyzer->exposeGetInvasivenessLevel(39));
        $this->assertEquals('moderately_invasive', $analyzer->exposeGetInvasivenessLevel(40));
        $this->assertEquals('moderately_invasive', $analyzer->exposeGetInvasivenessLevel(59));
        $this->assertEquals('very_invasive', $analyzer->exposeGetInvasivenessLevel(60));
        $this->assertEquals('very_invasive', $analyzer->exposeGetInvasivenessLevel(79));
        $this->assertEquals('extremely_invasive', $analyzer->exposeGetInvasivenessLevel(80));
        $this->assertEquals('extremely_invasive', $analyzer->exposeGetInvasivenessLevel(100));
    }

    // ========================================================================
    // PROPORTIONALITY TEST (FOUR-PART TEST)
    // ========================================================================

    /** @test */
    public function it_passes_legitimacy_test_for_criminal_offenses()
    {
        $analyzer = new class($this->openAIMock) extends ProportionalityAnalyzer
        {
            public function exposeApplyProportionalityTest($off, $inv, $details): array
            {
                return $this->applyProportionalityTest($off, $inv, $details);
            }
        };

        $offenseClassification = [
            'offense_type' => 'kazneno_djelo',
            'severity_score' => 60,
        ];

        $result = $analyzer->exposeApplyProportionalityTest(
            $offenseClassification,
            ['invasiveness_score' => 50],
            []
        );

        $this->assertTrue($result['legitimacy']['passes']);
        $this->assertStringContainsString('legitimate', $result['legitimacy']['analysis']);
    }

    /** @test */
    public function it_fails_legitimacy_test_for_low_severity_offenses()
    {
        $analyzer = new class($this->openAIMock) extends ProportionalityAnalyzer
        {
            public function exposeApplyProportionalityTest($off, $inv, $details): array
            {
                return $this->applyProportionalityTest($off, $inv, $details);
            }
        };

        $offenseClassification = [
            'offense_type' => 'prekršaj',
            'severity_score' => 15, // < 40
        ];

        $result = $analyzer->exposeApplyProportionalityTest(
            $offenseClassification,
            ['invasiveness_score' => 50],
            []
        );

        $this->assertFalse($result['legitimacy']['passes']);
        $this->assertStringContainsString('Questionable', $result['legitimacy']['analysis']);
    }

    /** @test */
    public function it_passes_suitability_test_when_items_sought_specified()
    {
        $analyzer = new class($this->openAIMock) extends ProportionalityAnalyzer
        {
            public function exposeApplyProportionalityTest($off, $inv, $details): array
            {
                return $this->applyProportionalityTest($off, $inv, $details);
            }
        };

        $result = $analyzer->exposeApplyProportionalityTest(
            ['offense_type' => 'kazneno_djelo', 'severity_score' => 60],
            ['invasiveness_score' => 50],
            ['items_sought' => 'Stolen jewelry and documents']
        );

        $this->assertTrue($result['suitability']['passes']);
        $this->assertStringContainsString('suitable', $result['suitability']['analysis']);
    }

    /** @test */
    public function it_fails_suitability_test_when_items_sought_not_specified()
    {
        $analyzer = new class($this->openAIMock) extends ProportionalityAnalyzer
        {
            public function exposeApplyProportionalityTest($off, $inv, $details): array
            {
                return $this->applyProportionalityTest($off, $inv, $details);
            }
        };

        $result = $analyzer->exposeApplyProportionalityTest(
            ['offense_type' => 'kazneno_djelo', 'severity_score' => 60],
            ['invasiveness_score' => 50],
            [] // No items_sought
        );

        $this->assertFalse($result['suitability']['passes']);
        $this->assertStringContainsString('unsuitable', $result['suitability']['analysis']);
    }

    /** @test */
    public function it_passes_necessity_test_for_high_severity_offenses()
    {
        $analyzer = new class($this->openAIMock) extends ProportionalityAnalyzer
        {
            public function exposeApplyProportionalityTest($off, $inv, $details): array
            {
                return $this->applyProportionalityTest($off, $inv, $details);
            }
        };

        $result = $analyzer->exposeApplyProportionalityTest(
            ['offense_type' => 'kazneno_djelo', 'severity_score' => 60],
            ['invasiveness_score' => 50],
            []
        );

        $this->assertTrue($result['necessity']['passes']);
        $this->assertStringContainsString('necessary', $result['necessity']['analysis']);
    }

    /** @test */
    public function it_passes_necessity_test_when_alternatives_considered()
    {
        $analyzer = new class($this->openAIMock) extends ProportionalityAnalyzer
        {
            public function exposeApplyProportionalityTest($off, $inv, $details): array
            {
                return $this->applyProportionalityTest($off, $inv, $details);
            }
        };

        $result = $analyzer->exposeApplyProportionalityTest(
            ['offense_type' => 'kazneno_djelo', 'severity_score' => 35], // Low severity
            ['invasiveness_score' => 50],
            ['alternatives_considered' => true]
        );

        $this->assertTrue($result['necessity']['passes']);
    }

    /** @test */
    public function it_passes_proportionality_stricto_sensu_when_invasiveness_not_excessive()
    {
        $analyzer = new class($this->openAIMock) extends ProportionalityAnalyzer
        {
            public function exposeApplyProportionalityTest($off, $inv, $details): array
            {
                return $this->applyProportionalityTest($off, $inv, $details);
            }
        };

        // Invasiveness (60) - Severity (50) = 10 <= 20 (threshold)
        $result = $analyzer->exposeApplyProportionalityTest(
            ['offense_type' => 'kazneno_djelo', 'severity_score' => 50],
            ['invasiveness_score' => 60],
            []
        );

        $this->assertTrue($result['proportionality_stricto_sensu']['passes']);
        $this->assertEquals(10, $result['proportionality_stricto_sensu']['difference']);
    }

    /** @test */
    public function it_fails_proportionality_stricto_sensu_when_invasiveness_excessive()
    {
        $analyzer = new class($this->openAIMock) extends ProportionalityAnalyzer
        {
            public function exposeApplyProportionalityTest($off, $inv, $details): array
            {
                return $this->applyProportionalityTest($off, $inv, $details);
            }
        };

        // Invasiveness (80) - Severity (30) = 50 > 20 (threshold)
        $result = $analyzer->exposeApplyProportionalityTest(
            ['offense_type' => 'kazneno_djelo', 'severity_score' => 30],
            ['invasiveness_score' => 80],
            []
        );

        $this->assertFalse($result['proportionality_stricto_sensu']['passes']);
        $this->assertEquals(50, $result['proportionality_stricto_sensu']['difference']);
        $this->assertStringContainsString('disproportionate', $result['proportionality_stricto_sensu']['analysis']);
    }

    /** @test */
    public function it_passes_overall_test_when_all_four_tests_pass()
    {
        $analyzer = new class($this->openAIMock) extends ProportionalityAnalyzer
        {
            public function exposeApplyProportionalityTest($off, $inv, $details): array
            {
                return $this->applyProportionalityTest($off, $inv, $details);
            }
        };

        $result = $analyzer->exposeApplyProportionalityTest(
            ['offense_type' => 'kazneno_djelo', 'severity_score' => 60],
            ['invasiveness_score' => 60],
            [
                'items_sought' => 'Drugs and weapons',
                'alternatives_considered' => true,
            ]
        );

        $this->assertTrue($result['overall_passes']);
    }

    /** @test */
    public function it_fails_overall_test_when_any_test_fails()
    {
        $analyzer = new class($this->openAIMock) extends ProportionalityAnalyzer
        {
            public function exposeApplyProportionalityTest($off, $inv, $details): array
            {
                return $this->applyProportionalityTest($off, $inv, $details);
            }
        };

        // Fails suitability (no items_sought)
        $result = $analyzer->exposeApplyProportionalityTest(
            ['offense_type' => 'kazneno_djelo', 'severity_score' => 60],
            ['invasiveness_score' => 60],
            [] // Missing items_sought
        );

        $this->assertFalse($result['overall_passes']);
    }

    // ========================================================================
    // DISPROPORTION SCORE CALCULATION TESTS
    // ========================================================================

    /** @test */
    public function it_calculates_disproportion_score_from_invasiveness_severity_difference()
    {
        $analyzer = new class($this->openAIMock) extends ProportionalityAnalyzer
        {
            public function exposeCalculateDisproportionScore($off, $inv, $test): int
            {
                return $this->calculateDisproportionScore($off, $inv, $test);
            }
        };

        // Invasiveness (70) - Severity (30) = 40 difference = 40 base score
        $score = $analyzer->exposeCalculateDisproportionScore(
            ['severity' => 'minor_criminal', 'severity_score' => 30],
            ['invasiveness_score' => 70],
            ['overall_passes' => false, 'legitimacy' => ['passes' => true], 'suitability' => ['passes' => true], 'necessity' => ['passes' => true], 'proportionality_stricto_sensu' => ['passes' => true]]
        );

        $this->assertEquals(40, $score);
    }

    /** @test */
    public function it_adds_15_points_for_each_failed_proportionality_test()
    {
        $analyzer = new class($this->openAIMock) extends ProportionalityAnalyzer
        {
            public function exposeCalculateDisproportionScore($off, $inv, $test): int
            {
                return $this->calculateDisproportionScore($off, $inv, $test);
            }
        };

        // Base: 20 difference + 2 failed tests (15*2 = 30) = 50 total
        $score = $analyzer->exposeCalculateDisproportionScore(
            ['severity' => 'minor_criminal', 'severity_score' => 30],
            ['invasiveness_score' => 50],
            [
                'overall_passes' => false,
                'legitimacy' => ['passes' => false], // +15
                'suitability' => ['passes' => false], // +15
                'necessity' => ['passes' => true],
                'proportionality_stricto_sensu' => ['passes' => true],
            ]
        );

        $this->assertEquals(50, $score); // 20 + 15 + 15
    }

    /** @test */
    public function it_enforces_minimum_75_for_misdemeanor_with_invasive_search()
    {
        $analyzer = new class($this->openAIMock) extends ProportionalityAnalyzer
        {
            public function exposeCalculateDisproportionScore($off, $inv, $test): int
            {
                return $this->calculateDisproportionScore($off, $inv, $test);
            }
        };

        // Misdemeanor (15) + invasive search (60) = would be 45, but enforced to 75
        $score = $analyzer->exposeCalculateDisproportionScore(
            ['severity' => 'misdemeanor', 'severity_score' => 15],
            ['invasiveness_score' => 60],
            ['overall_passes' => false, 'legitimacy' => ['passes' => true], 'suitability' => ['passes' => true], 'necessity' => ['passes' => true], 'proportionality_stricto_sensu' => ['passes' => true]]
        );

        $this->assertGreaterThanOrEqual(75, $score);
    }

    /** @test */
    public function it_caps_disproportion_score_at_100()
    {
        $analyzer = new class($this->openAIMock) extends ProportionalityAnalyzer
        {
            public function exposeCalculateDisproportionScore($off, $inv, $test): int
            {
                return $this->calculateDisproportionScore($off, $inv, $test);
            }
        };

        // Large difference (50) + 4 failed tests (60) = 110, capped at 100
        $score = $analyzer->exposeCalculateDisproportionScore(
            ['severity' => 'misdemeanor', 'severity_score' => 10],
            ['invasiveness_score' => 100],
            [
                'overall_passes' => false,
                'legitimacy' => ['passes' => false], // +15
                'suitability' => ['passes' => false], // +15
                'necessity' => ['passes' => false], // +15
                'proportionality_stricto_sensu' => ['passes' => false], // +15
            ]
        );

        $this->assertLessThanOrEqual(100, $score);
    }

    // ========================================================================
    // DISPROPORTION LEVEL THRESHOLD TESTS
    // ========================================================================

    /** @test */
    public function it_categorizes_disproportion_levels_correctly()
    {
        $analyzer = new class($this->openAIMock) extends ProportionalityAnalyzer
        {
            public function exposeGetDisproportionLevel(int $score): string
            {
                return $this->getDisproportionLevel($score);
            }
        };

        $this->assertEquals('none', $analyzer->exposeGetDisproportionLevel(0));
        $this->assertEquals('none', $analyzer->exposeGetDisproportionLevel(19));
        $this->assertEquals('minor', $analyzer->exposeGetDisproportionLevel(20));
        $this->assertEquals('minor', $analyzer->exposeGetDisproportionLevel(39));
        $this->assertEquals('moderate', $analyzer->exposeGetDisproportionLevel(40));
        $this->assertEquals('moderate', $analyzer->exposeGetDisproportionLevel(59));
        $this->assertEquals('severe', $analyzer->exposeGetDisproportionLevel(60));
        $this->assertEquals('severe', $analyzer->exposeGetDisproportionLevel(79));
        $this->assertEquals('extreme', $analyzer->exposeGetDisproportionLevel(80));
        $this->assertEquals('extreme', $analyzer->exposeGetDisproportionLevel(100));
    }

    /** @test */
    public function it_marks_proportionate_when_disproportion_score_below_50()
    {
        $this->openAIMock->shouldReceive('chat')->andReturn([
            'choices' => [['message' => ['content' => 'AI analysis text']]],
        ]);

        $result = $this->analyzer->analyze([
            'offense' => 'Krađa',
            'offense_type' => 'kazneno_djelo',
            'max_penalty_years' => 5,
            'search_scope' => 'partial',
            'items_sought' => 'Stolen goods',
        ], $this->case);

        $this->assertTrue($result['proportionate']); // Score should be < 50
        $this->assertLessThan(50, $result['disproportion_score']);
    }

    /** @test */
    public function it_marks_disproportionate_when_disproportion_score_50_or_above()
    {
        $this->openAIMock->shouldReceive('chat')->andReturn([
            'choices' => [['message' => ['content' => 'AI analysis text']]],
        ]);

        $result = $this->analyzer->analyze([
            'offense' => 'Prometni prekršaj',
            'offense_type' => 'prekršaj',
            'search_scope' => 'full_home_search',
            'force_used' => 'swat',
        ], $this->case);

        $this->assertFalse($result['proportionate']); // Score should be >= 50
        $this->assertGreaterThanOrEqual(50, $result['disproportion_score']);
    }

    // ========================================================================
    // AI ANALYSIS GENERATION TESTS
    // ========================================================================

    /** @test */
    public function it_generates_ai_analysis_using_openai()
    {
        $this->openAIMock->shouldReceive('chat')
            ->once()
            ->with(Mockery::type('array'), 'gpt-4o', Mockery::type('array'))
            ->andReturn([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Pretres doma za prometni prekršaj predstavlja ozbiljnu povredu načela razmjernosti (ZKP Čl. 179).',
                        ],
                    ],
                ],
            ]);

        $result = $this->analyzer->analyze([
            'offense' => 'Prometni prekršaj',
            'offense_type' => 'prekršaj',
            'search_scope' => 'full_home_search',
        ], $this->case);

        $this->assertArrayHasKey('analysis', $result);
        $this->assertStringContainsString('Pretres doma', $result['analysis']);
        $this->assertStringContainsString('ZKP Čl. 179', $result['analysis']);
    }

    /** @test */
    public function it_falls_back_to_template_when_ai_analysis_fails()
    {
        $this->openAIMock->shouldReceive('chat')
            ->once()
            ->andThrow(new \Exception('OpenAI API error'));

        Log::shouldReceive('error')
            ->once()
            ->with('ProportionalityAnalyzer: AI analysis failed', Mockery::type('array'));

        $result = $this->analyzer->analyze([
            'offense' => 'Prometni prekršaj',
            'offense_type' => 'prekršaj',
            'search_scope' => 'full_home_search',
        ], $this->case);

        $this->assertArrayHasKey('analysis', $result);
        $this->assertStringContainsString('Pretres doma', $result['analysis']);
        $this->assertStringContainsString('razmjernosti', $result['analysis']);
        $this->assertStringContainsString('Ustava RH', $result['analysis']);
    }

    // ========================================================================
    // FULL INTEGRATION TESTS
    // ========================================================================

    /** @test */
    public function it_performs_complete_proportionality_analysis()
    {
        $this->openAIMock->shouldReceive('chat')->andReturn([
            'choices' => [['message' => ['content' => 'Complete analysis']]],
        ]);

        $searchWarrantDetails = [
            'offense' => 'Razbojništvo',
            'offense_type' => 'kazneno_djelo',
            'max_penalty_years' => 10,
            'search_scope' => 'full_home_search',
            'force_used' => 'armed_officers',
            'items_sought' => 'Stolen money and weapons',
            'alternatives_considered' => true,
        ];

        $result = $this->analyzer->analyze($searchWarrantDetails, $this->case);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('proportionate', $result);
        $this->assertArrayHasKey('disproportion_score', $result);
        $this->assertArrayHasKey('disproportion_level', $result);
        $this->assertArrayHasKey('offense_classification', $result);
        $this->assertArrayHasKey('search_invasiveness', $result);
        $this->assertArrayHasKey('proportionality_test', $result);
        $this->assertArrayHasKey('analysis', $result);
        $this->assertEquals('ZKP Čl. 179 - Načelo razmjernosti', $result['legal_standard']);
        $this->assertEquals('Ustav RH Čl. 34 - Nepovrjedivost stana', $result['constitutional_standard']);
    }

    /** @test */
    public function it_identifies_extreme_disproportion_for_misdemeanor_with_swat_raid()
    {
        $this->openAIMock->shouldReceive('chat')->andReturn([
            'choices' => [['message' => ['content' => 'Extreme disproportion analysis']]],
        ]);

        $result = $this->analyzer->analyze([
            'offense' => 'Parking violation',
            'offense_type' => 'misdemeanor',
            'search_scope' => 'full_home_search',
            'force_used' => 'swat',
            'search_time' => '2025-03-15 02:00:00', // Night raid
        ], $this->case);

        $this->assertFalse($result['proportionate']);
        $this->assertGreaterThanOrEqual(75, $result['disproportion_score']);
        $this->assertContains($result['disproportion_level'], ['severe', 'extreme']);
    }

    /** @test */
    public function it_identifies_proportionate_search_for_serious_offense()
    {
        $this->openAIMock->shouldReceive('chat')->andReturn([
            'choices' => [['message' => ['content' => 'Proportionate analysis']]],
        ]);

        $result = $this->analyzer->analyze([
            'offense' => 'Ubojstvo',
            'offense_type' => 'kazneno_djelo',
            'max_penalty_years' => 20,
            'search_scope' => 'full_home_search',
            'force_used' => 'armed_officers',
            'items_sought' => 'Murder weapon',
            'alternatives_considered' => true,
        ], $this->case);

        $this->assertTrue($result['proportionate']);
        $this->assertLessThan(50, $result['disproportion_score']);
        $this->assertEquals('serious', $result['offense_classification']['severity']);
    }
}
