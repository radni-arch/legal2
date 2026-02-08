<?php

namespace Tests\Unit\Modules\Defence;

use App\Models\LegalCase;
use App\Modules\Defence\Services\DefenseRecommendationService;
use App\Services\OpenAIService;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class DefenseRecommendationServiceTest extends TestCase
{
    use UsesTestDatabase;

    protected DefenseRecommendationService $service;

    protected $openAIMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->openAIMock = Mockery::mock(OpenAIService::class);
        $this->service = new DefenseRecommendationService($this->openAIMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_generates_comprehensive_recommendations()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $context = [
            'prosecution_weaknesses' => [
                ['description' => 'Weak evidence', 'severity' => 75],
            ],
            'defense_strengths' => [
                'strong_points' => [
                    ['description' => 'Alibi witness'],
                ],
            ],
            'mitigating_factors' => [
                ['description' => 'First-time offender'],
            ],
        ];

        // Act
        $result = $this->service->generateRecommendations($case, $context);

        // Assert
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        foreach ($result as $recommendation) {
            $this->assertArrayHasKey('category', $recommendation);
            $this->assertArrayHasKey('action', $recommendation);
            $this->assertArrayHasKey('priority', $recommendation);
            $this->assertArrayHasKey('impact', $recommendation);
            $this->assertArrayHasKey('score', $recommendation);
        }
    }

    /** @test */
    public function it_generates_immediate_action_recommendations()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        // Act
        $result = $this->service->generateRecommendations($case, []);

        // Assert
        $immediateActions = array_filter($result, fn ($r) => $r['category'] === 'immediate');
        $this->assertCount(3, $immediateActions);

        $actions = array_column($immediateActions, 'action');
        $this->assertContains('Preserve all potential evidence', $actions);
        $this->assertContains('Interview the accused thoroughly', $actions);
        $this->assertContains('Review all prosecution discovery', $actions);
    }

    /** @test */
    public function it_marks_immediate_actions_as_urgent()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        // Act
        $result = $this->service->generateRecommendations($case, []);

        // Assert
        $immediateActions = array_filter($result, fn ($r) => $r['category'] === 'immediate');
        foreach ($immediateActions as $action) {
            $this->assertEquals('urgent', $action['priority']);
            $this->assertEquals('immediate', $action['timeline']);
        }
    }

    /** @test */
    public function it_generates_evidence_recommendations_based_on_weaknesses()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $context = [
            'prosecution_weaknesses' => [
                [
                    'description' => 'Chain of custody broken',
                    'severity' => 85,
                    'exploitation_strategy' => 'File suppression motion',
                ],
                [
                    'description' => 'Witness credibility issues',
                    'severity' => 70,
                    'exploitation_strategy' => 'Cross-examine witness',
                ],
                [
                    'description' => 'Minor procedural issue',
                    'severity' => 45, // Below threshold
                    'exploitation_strategy' => 'Note in brief',
                ],
            ],
        ];

        // Act
        $result = $this->service->generateRecommendations($case, $context);

        // Assert
        $evidenceRecs = array_filter($result, fn ($r) => $r['category'] === 'evidence');
        $this->assertCount(2, $evidenceRecs); // Only severity >= 70

        $descriptions = array_column($evidenceRecs, 'action');
        $this->assertStringContainsString('Chain of custody broken', implode(' ', $descriptions));
        $this->assertStringContainsString('Witness credibility issues', implode(' ', $descriptions));
    }

    /** @test */
    public function it_generates_witness_recommendations()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        // Act
        $result = $this->service->generateRecommendations($case, []);

        // Assert
        $witnessRecs = array_filter($result, fn ($r) => $r['category'] === 'witnesses');
        $this->assertCount(2, $witnessRecs);

        $actions = array_column($witnessRecs, 'action');
        $this->assertContains('Identify and interview alibi witnesses', $actions);
        $this->assertContains('Prepare character witnesses', $actions);
    }

    /** @test */
    public function it_generates_motion_recommendations()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        // Act
        $result = $this->service->generateRecommendations($case, []);

        // Assert
        $motionRecs = array_filter($result, fn ($r) => $r['category'] === 'motions');
        $this->assertCount(2, $motionRecs);

        $actions = array_column($motionRecs, 'action');
        $this->assertContains('File motion to suppress illegally obtained evidence', $actions);
        $this->assertContains('File motion to dismiss for lack of probable cause', $actions);
    }

    /** @test */
    public function it_generates_negotiation_recommendations_with_leverage()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $context = [
            'prosecution_weaknesses' => [
                ['description' => 'Weakness 1', 'severity' => 80],
                ['description' => 'Weakness 2', 'severity' => 75],
            ],
            'defense_strengths' => [
                'strong_points' => [
                    ['description' => 'Strength 1'],
                    ['description' => 'Strength 2'],
                    ['description' => 'Strength 3'],
                ],
            ],
            'mitigating_factors' => [
                ['description' => 'Factor 1'],
            ],
        ];

        // Act
        $result = $this->service->generateRecommendations($case, $context);

        // Assert
        $negotiationRecs = array_filter($result, fn ($r) => $r['category'] === 'negotiation');
        $this->assertCount(1, $negotiationRecs);

        $negotiation = array_values($negotiationRecs)[0];
        $this->assertEquals('high', $negotiation['priority']);
        $this->assertStringContainsString('Strong leverage', $negotiation['rationale']);
    }

    /** @test */
    public function it_generates_negotiation_recommendations_without_leverage()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $context = [
            'prosecution_weaknesses' => [],
            'defense_strengths' => ['strong_points' => []],
            'mitigating_factors' => [],
        ];

        // Act
        $result = $this->service->generateRecommendations($case, $context);

        // Assert
        $negotiationRecs = array_filter($result, fn ($r) => $r['category'] === 'negotiation');
        $this->assertCount(1, $negotiationRecs);

        $negotiation = array_values($negotiationRecs)[0];
        $this->assertEquals('medium', $negotiation['priority']);
        $this->assertStringContainsString('Explore options', $negotiation['rationale']);
    }

    /** @test */
    public function it_generates_expert_recommendations()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        // Act
        $result = $this->service->generateRecommendations($case, []);

        // Assert
        $expertRecs = array_filter($result, fn ($r) => $r['category'] === 'experts');
        $this->assertCount(1, $expertRecs);

        $expert = array_values($expertRecs)[0];
        $this->assertEquals('Retain forensic expert to challenge prosecution evidence', $expert['action']);
        $this->assertEquals('medium', $expert['priority']);
    }

    /** @test */
    public function it_prioritizes_recommendations_by_score()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $context = [
            'prosecution_weaknesses' => [
                ['description' => 'High severity weakness', 'severity' => 90, 'exploitation_strategy' => 'File motion'],
            ],
        ];

        // Act
        $result = $this->service->generateRecommendations($case, $context);

        // Assert
        $this->assertGreaterThan(0, count($result));

        // Verify descending order by score
        for ($i = 0; $i < count($result) - 1; $i++) {
            $this->assertGreaterThanOrEqual(
                $result[$i + 1]['score'],
                $result[$i]['score'],
                'Recommendations should be sorted by score descending'
            );
        }
    }

    /** @test */
    public function it_scores_urgent_priority_highest()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        // Act
        $result = $this->service->generateRecommendations($case, []);

        // Assert
        $urgentRecs = array_filter($result, fn ($r) => $r['priority'] === 'urgent');
        $highRecs = array_filter($result, fn ($r) => $r['priority'] === 'high');

        if (! empty($urgentRecs) && ! empty($highRecs)) {
            $urgentScore = array_values($urgentRecs)[0]['score'];
            $highScore = array_values($highRecs)[0]['score'];
            $this->assertGreaterThan($highScore, $urgentScore);
        }
    }

    /** @test */
    public function it_scores_very_high_impact_higher_than_high_impact()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        // Act
        $result = $this->service->generateRecommendations($case, []);

        // Assert
        $veryHighImpact = array_filter($result, fn ($r) => $r['impact'] === 'Very High');
        $highImpact = array_filter($result, fn ($r) => $r['impact'] === 'High' && $r['priority'] === array_values($veryHighImpact)[0]['priority'] ?? 'high');

        if (! empty($veryHighImpact) && ! empty($highImpact)) {
            $veryHighScore = array_values($veryHighImpact)[0]['score'];
            $highScore = array_values($highImpact)[0]['score'];
            $this->assertGreaterThan($highScore, $veryHighScore);
        }
    }

    /** @test */
    public function it_includes_required_fields_in_all_recommendations()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        // Act
        $result = $this->service->generateRecommendations($case, []);

        // Assert
        foreach ($result as $recommendation) {
            $this->assertArrayHasKey('category', $recommendation);
            $this->assertArrayHasKey('action', $recommendation);
            $this->assertArrayHasKey('priority', $recommendation);
            $this->assertArrayHasKey('impact', $recommendation);
            $this->assertArrayHasKey('timeline', $recommendation);
            $this->assertArrayHasKey('resources', $recommendation);
            $this->assertArrayHasKey('rationale', $recommendation);
            $this->assertArrayHasKey('score', $recommendation);
        }
    }

    /** @test */
    public function it_calculates_leverage_correctly()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $context = [
            'prosecution_weaknesses' => [
                ['description' => 'W1'],
                ['description' => 'W2'],
            ],
            'defense_strengths' => [
                'strong_points' => [
                    ['description' => 'S1'],
                    ['description' => 'S2'],
                ],
            ],
            'mitigating_factors' => [
                ['description' => 'M1'],
            ],
        ];

        // Act
        $result = $this->service->generateRecommendations($case, $context);

        // Assert
        // Total leverage = 2 + 2 + 1 = 5 (exactly at threshold)
        $negotiationRecs = array_filter($result, fn ($r) => $r['category'] === 'negotiation');
        $negotiation = array_values($negotiationRecs)[0];
        $this->assertEquals('high', $negotiation['priority']);
    }

    /** @test */
    public function it_handles_empty_context()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        // Act
        $result = $this->service->generateRecommendations($case, []);

        // Assert
        $this->assertNotEmpty($result);
        // Should still have immediate actions, witnesses, motions, negotiation, experts
        $categories = array_unique(array_column($result, 'category'));
        $this->assertContains('immediate', $categories);
        $this->assertContains('witnesses', $categories);
        $this->assertContains('motions', $categories);
        $this->assertContains('negotiation', $categories);
        $this->assertContains('experts', $categories);
    }

    /** @test */
    public function it_handles_context_with_missing_keys()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $context = [
            'prosecution_weaknesses' => [
                ['description' => 'Weakness', 'severity' => 80],
            ],
            // Missing defense_strengths and mitigating_factors
        ];

        // Act
        $result = $this->service->generateRecommendations($case, $context);

        // Assert
        $this->assertNotEmpty($result);
        $negotiationRecs = array_filter($result, fn ($r) => $r['category'] === 'negotiation');
        $this->assertCount(1, $negotiationRecs);
    }

    /** @test */
    public function it_includes_resources_in_recommendations()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        // Act
        $result = $this->service->generateRecommendations($case, []);

        // Assert
        foreach ($result as $recommendation) {
            $this->assertIsArray($recommendation['resources']);
            $this->assertNotEmpty($recommendation['resources']);
        }
    }

    /** @test */
    public function it_provides_rationale_for_all_recommendations()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        // Act
        $result = $this->service->generateRecommendations($case, []);

        // Assert
        foreach ($result as $recommendation) {
            $this->assertNotEmpty($recommendation['rationale']);
            $this->assertIsString($recommendation['rationale']);
        }
    }

    /** @test */
    public function it_assigns_appropriate_timelines()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        // Act
        $result = $this->service->generateRecommendations($case, []);

        // Assert
        $validTimelines = ['immediate', 'short_term', 'medium_term', 'long_term'];
        foreach ($result as $recommendation) {
            $this->assertContains($recommendation['timeline'], $validTimelines);
        }
    }

    /** @test */
    public function it_generates_evidence_recommendations_with_correct_priority()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $context = [
            'prosecution_weaknesses' => [
                [
                    'description' => 'Critical evidence flaw',
                    'severity' => 95,
                    'exploitation_strategy' => 'File immediate suppression motion',
                ],
            ],
        ];

        // Act
        $result = $this->service->generateRecommendations($case, $context);

        // Assert
        $evidenceRecs = array_filter($result, fn ($r) => $r['category'] === 'evidence');
        $this->assertNotEmpty($evidenceRecs);

        $evidence = array_values($evidenceRecs)[0];
        $this->assertEquals('high', $evidence['priority']);
        $this->assertEquals('High', $evidence['impact']);
    }

    /** @test */
    public function it_filters_low_severity_weaknesses()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $context = [
            'prosecution_weaknesses' => [
                ['description' => 'Low severity issue', 'severity' => 50],
                ['description' => 'Medium severity issue', 'severity' => 69],
                ['description' => 'High severity issue', 'severity' => 70],
                ['description' => 'Very high severity issue', 'severity' => 90],
            ],
        ];

        // Act
        $result = $this->service->generateRecommendations($case, $context);

        // Assert
        $evidenceRecs = array_filter($result, fn ($r) => $r['category'] === 'evidence');
        $this->assertCount(2, $evidenceRecs); // Only >= 70

        $descriptions = implode(' ', array_column($evidenceRecs, 'action'));
        $this->assertStringContainsString('High severity issue', $descriptions);
        $this->assertStringContainsString('Very high severity issue', $descriptions);
        $this->assertStringNotContainsString('Low severity issue', $descriptions);
        $this->assertStringNotContainsString('Medium severity issue', $descriptions);
    }

    /** @test */
    public function it_merges_all_recommendation_categories()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $context = [
            'prosecution_weaknesses' => [
                ['description' => 'Weakness', 'severity' => 80, 'exploitation_strategy' => 'Challenge'],
            ],
        ];

        // Act
        $result = $this->service->generateRecommendations($case, $context);

        // Assert
        $categories = array_unique(array_column($result, 'category'));
        $this->assertContains('immediate', $categories);
        $this->assertContains('evidence', $categories);
        $this->assertContains('witnesses', $categories);
        $this->assertContains('motions', $categories);
        $this->assertContains('negotiation', $categories);
        $this->assertContains('experts', $categories);
    }

    /** @test */
    public function it_uses_exploitation_strategy_in_evidence_recommendations()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $context = [
            'prosecution_weaknesses' => [
                [
                    'description' => 'Illegal search and seizure',
                    'severity' => 90,
                    'exploitation_strategy' => 'File Fourth Amendment suppression motion',
                ],
            ],
        ];

        // Act
        $result = $this->service->generateRecommendations($case, $context);

        // Assert
        $evidenceRecs = array_filter($result, fn ($r) => $r['category'] === 'evidence');
        $evidence = array_values($evidenceRecs)[0];
        $this->assertEquals('File Fourth Amendment suppression motion', $evidence['rationale']);
    }

    /** @test */
    public function it_assigns_correct_score_ranges()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        // Act
        $result = $this->service->generateRecommendations($case, []);

        // Assert
        foreach ($result as $recommendation) {
            $this->assertGreaterThanOrEqual(0, $recommendation['score']);
            $this->assertLessThanOrEqual(150, $recommendation['score']); // Max: 100 (urgent) + 50 (very high)
        }
    }
}
