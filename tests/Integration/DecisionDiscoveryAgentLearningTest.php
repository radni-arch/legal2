<?php

namespace Tests\Integration;

use App\Agents\DecisionDiscoveryAgent;
use App\Models\DecisionUsageTracking;
use App\Models\LegalCase;
use App\Services\DecisionUsageLearningService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * TDD Integration Tests for DecisionDiscoveryAgent Active Learning
 *
 * Sprint 5.4: DecisionDiscoveryAgent Active Learning
 *
 * Tests the complete learning loop: usage tracking → scoring adjustment →
 * A/B testing → weekly retraining.
 *
 * Following strict TDD: Tests written FIRST, will fail until implementation.
 */
class DecisionDiscoveryAgentLearningTest extends TestCase
{
    use DatabaseTransactions;

    protected DecisionUsageLearningService $learningService;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock OpenAI API to avoid real API calls
        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response([
                'data' => [['embedding' => array_fill(0, 1536, 0.1)]],
            ], 200),
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'decisions' => [
                                    ['id' => 'dec-001', 'score' => 75, 'reasoning' => 'Relevant'],
                                ],
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->learningService = app(DecisionUsageLearningService::class);
    }

    /** @test */
    public function it_tracks_decision_usage_in_database()
    {
        // Create a case
        $case = LegalCase::factory()->create();

        // Track decision usage
        $result = $this->learningService->trackUsage(
            decisionId: 'dec-osijek-2024-001',
            caseId: $case->id,
            usageType: 'cited',
            usedAt: now()
        );

        // Verify tracked in database
        $this->assertTrue($result['tracked']);
        $this->assertDatabaseHas('decision_usage_tracking', [
            'decision_id' => 'dec-osijek-2024-001',
            'used_in_case_id' => $case->id,
            'usage_type' => 'cited',
        ]);

        // Verify can retrieve usage
        $usage = DecisionUsageTracking::forDecision('dec-osijek-2024-001')->first();
        $this->assertNotNull($usage);
        $this->assertEquals('cited', $usage->usage_type);
    }

    /** @test */
    public function it_tracks_different_usage_types()
    {
        $case = LegalCase::factory()->create();

        // Track different usage types
        $this->learningService->trackUsage('dec-001', $case->id, 'cited', now());
        $this->learningService->trackUsage('dec-002', $case->id, 'motion', now());
        $this->learningService->trackUsage('dec-003', $case->id, 'brief', now());

        // Verify all types tracked
        $this->assertEquals(1, DecisionUsageTracking::ofType('cited')->count());
        $this->assertEquals(1, DecisionUsageTracking::ofType('motion')->count());
        $this->assertEquals(1, DecisionUsageTracking::ofType('brief')->count());
    }

    /** @test */
    public function it_calculates_decision_usage_score()
    {
        $case = LegalCase::factory()->create();

        // Track usage for a decision multiple times
        $this->learningService->trackUsage('dec-popular', $case->id, 'cited', now()->subDays(5));
        $this->learningService->trackUsage('dec-popular', $case->id, 'motion', now()->subDays(3));
        $this->learningService->trackUsage('dec-popular', $case->id, 'brief', now()->subDays(1));

        // Calculate usage score
        $score = $this->learningService->calculateUsageScore('dec-popular');

        // Verify score calculated based on usage frequency
        $this->assertGreaterThan(0, $score);
        $this->assertLessThanOrEqual(100, $score);
    }

    /** @test */
    public function it_adjusts_scoring_based_on_usage_data()
    {
        $case = LegalCase::factory()->create();

        // Create scenario: dec-001 has high usage, dec-002 has no usage
        $this->learningService->trackUsage('dec-001', $case->id, 'cited', now()->subDays(10));
        $this->learningService->trackUsage('dec-001', $case->id, 'cited', now()->subDays(8));
        $this->learningService->trackUsage('dec-001', $case->id, 'motion', now()->subDays(5));
        $this->learningService->trackUsage('dec-001', $case->id, 'brief', now()->subDays(2));

        // Get original scores
        $originalScores = [
            'dec-001' => 60, // Low AI score but high actual usage
            'dec-002' => 80, // High AI score but no actual usage
        ];

        // Adjust scores based on usage
        $adjustedScores = $this->learningService->adjustScoresBasedOnUsage($originalScores);

        // Verify scores adjusted correctly
        $this->assertGreaterThan($originalScores['dec-001'], $adjustedScores['dec-001']);
        $this->assertLessThan($originalScores['dec-002'], $adjustedScores['dec-002']);
    }

    /** @test */
    public function it_performs_ab_test_comparing_old_vs_new_scoring()
    {
        $case = LegalCase::factory()->create([
            'description' => 'Criminal procedure violation, illegal search and seizure',
        ]);

        // Track usage data for training
        $this->learningService->trackUsage('dec-highly-used', $case->id, 'cited', now()->subDays(20));
        $this->learningService->trackUsage('dec-highly-used', $case->id, 'cited', now()->subDays(15));
        $this->learningService->trackUsage('dec-highly-used', $case->id, 'motion', now()->subDays(10));

        // Run A/B test
        $abTestResult = $this->learningService->runABTest(
            testCases: [
                ['id' => $case->id, 'summary' => $case->description],
            ],
            sampleSize: 1
        );

        // Verify A/B test results
        $this->assertArrayHasKey('old_model_accuracy', $abTestResult);
        $this->assertArrayHasKey('new_model_accuracy', $abTestResult);
        $this->assertArrayHasKey('improvement_percent', $abTestResult);
        $this->assertArrayHasKey('test_passed', $abTestResult);

        // Verify improvement calculated
        $improvement = $abTestResult['improvement_percent'];
        $this->assertIsNumeric($improvement);
    }

    /** @test */
    public function it_passes_ab_test_with_greater_than_10_percent_improvement()
    {
        $case = LegalCase::factory()->create();

        // Create strong usage signal for specific decisions
        for ($i = 0; $i < 10; $i++) {
            $this->learningService->trackUsage('dec-excellent', $case->id, 'cited', now()->subDays(20 - $i));
        }

        // Run A/B test with enough data to show >10% improvement
        $abTestResult = $this->learningService->runABTest(
            testCases: [['id' => $case->id, 'summary' => 'Test case']],
            sampleSize: 1
        );

        // Verify test passes with >10% improvement
        $this->assertTrue($abTestResult['test_passed']);
        $this->assertGreaterThan(10, $abTestResult['improvement_percent']);
    }

    /** @test */
    public function it_trains_scoring_model_from_usage_data()
    {
        $case = LegalCase::factory()->create();

        // Create training data - usage patterns
        $trainingData = [
            'dec-001' => 5, // Used 5 times
            'dec-002' => 0, // Never used
            'dec-003' => 3, // Used 3 times
            'dec-004' => 8, // Used 8 times
        ];

        foreach ($trainingData as $decisionId => $usageCount) {
            for ($i = 0; $i < $usageCount; $i++) {
                $this->learningService->trackUsage($decisionId, $case->id, 'cited', now()->subDays(20 - $i));
            }
        }

        // Train model
        $trainingResult = $this->learningService->trainModel();

        // Verify training succeeded
        $this->assertTrue($trainingResult['success']);
        $this->assertArrayHasKey('model_version', $trainingResult);
        $this->assertArrayHasKey('training_metrics', $trainingResult);
        $this->assertGreaterThan(0, $trainingResult['training_metrics']['samples_count']);
    }

    /** @test */
    public function it_schedules_weekly_retraining()
    {
        // Get retraining schedule
        $schedule = $this->learningService->getRetrainingSchedule();

        // Verify weekly schedule configured
        $this->assertArrayHasKey('frequency', $schedule);
        $this->assertEquals('weekly', $schedule['frequency']);
        $this->assertArrayHasKey('next_run', $schedule);
        $this->assertArrayHasKey('last_run', $schedule);
    }

    /** @test */
    public function it_executes_weekly_retraining_automatically()
    {
        $case = LegalCase::factory()->create();

        // Create usage data
        for ($i = 0; $i < 5; $i++) {
            $this->learningService->trackUsage("dec-00{$i}", $case->id, 'cited', now()->subDays(10 - $i));
        }

        // Execute scheduled retraining
        $result = $this->learningService->executeScheduledRetraining();

        // Verify retraining executed
        $this->assertTrue($result['executed']);
        $this->assertArrayHasKey('model_version', $result);
        $this->assertArrayHasKey('trained_at', $result);
        $this->assertArrayHasKey('samples_used', $result);
    }

    /** @test */
    public function it_integrates_full_learning_loop_end_to_end()
    {
        $case = LegalCase::factory()->create([
            'description' => 'Search warrant proportionality challenge',
        ]);

        // Step 1: Track usage over time
        $usageData = [
            ['dec-001', 'cited', now()->subDays(20)],
            ['dec-001', 'motion', now()->subDays(18)],
            ['dec-002', 'cited', now()->subDays(15)],
            ['dec-001', 'brief', now()->subDays(12)],
            ['dec-003', 'cited', now()->subDays(10)],
            ['dec-001', 'cited', now()->subDays(8)],
        ];

        foreach ($usageData as [$decisionId, $usageType, $usedAt]) {
            $this->learningService->trackUsage($decisionId, $case->id, $usageType, $usedAt);
        }

        // Step 2: Train model from usage data
        $trainingResult = $this->learningService->trainModel();
        $this->assertTrue($trainingResult['success']);

        // Step 3: Run A/B test
        $abTestResult = $this->learningService->runABTest(
            testCases: [['id' => $case->id, 'summary' => $case->description]],
            sampleSize: 1
        );

        // Step 4: Verify learning loop completed
        $this->assertTrue($abTestResult['test_passed']);
        $this->assertGreaterThan(10, $abTestResult['improvement_percent']);

        // Step 5: Verify model deployed
        $deploymentStatus = $this->learningService->getModelDeploymentStatus();
        $this->assertTrue($deploymentStatus['deployed']);
        $this->assertArrayHasKey('version', $deploymentStatus);
        $this->assertArrayHasKey('accuracy', $deploymentStatus);
    }

    /** @test */
    public function it_prevents_duplicate_usage_tracking()
    {
        $case = LegalCase::factory()->create();

        // Track usage
        $result1 = $this->learningService->trackUsage('dec-001', $case->id, 'cited', now());

        // Attempt to track same usage again
        $result2 = $this->learningService->trackUsage('dec-001', $case->id, 'cited', now());

        // Verify first tracked, second prevented
        $this->assertTrue($result1['tracked']);
        $this->assertFalse($result2['tracked']);
        $this->assertEquals('duplicate', $result2['reason']);

        // Verify only one entry in database
        $this->assertEquals(1, DecisionUsageTracking::forDecision('dec-001')->count());
    }

    /** @test */
    public function it_filters_usage_by_date_range()
    {
        $case = LegalCase::factory()->create();

        // Track usage across different dates
        $this->learningService->trackUsage('dec-001', $case->id, 'cited', now()->subDays(30));
        $this->learningService->trackUsage('dec-001', $case->id, 'cited', now()->subDays(15));
        $this->learningService->trackUsage('dec-001', $case->id, 'cited', now()->subDays(5));

        // Filter by last 7 days
        $recentUsage = DecisionUsageTracking::forDecision('dec-001')
            ->usedBetween(now()->subDays(7), now())
            ->count();

        // Filter by last 30 days
        $monthUsage = DecisionUsageTracking::forDecision('dec-001')
            ->usedBetween(now()->subDays(30), now())
            ->count();

        // Verify filtering works
        $this->assertEquals(1, $recentUsage);
        $this->assertEquals(3, $monthUsage);
    }

    /** @test */
    public function it_calculates_usage_statistics()
    {
        $case = LegalCase::factory()->create();

        // Track varied usage
        $this->learningService->trackUsage('dec-001', $case->id, 'cited', now()->subDays(10));
        $this->learningService->trackUsage('dec-001', $case->id, 'motion', now()->subDays(8));
        $this->learningService->trackUsage('dec-002', $case->id, 'cited', now()->subDays(5));
        $this->learningService->trackUsage('dec-003', $case->id, 'brief', now()->subDays(3));

        // Get statistics
        $stats = $this->learningService->getUsageStatistics();

        // Verify statistics calculated
        $this->assertArrayHasKey('total_usage_count', $stats);
        $this->assertArrayHasKey('unique_decisions', $stats);
        $this->assertArrayHasKey('by_type', $stats);
        $this->assertArrayHasKey('most_used_decisions', $stats);

        $this->assertEquals(4, $stats['total_usage_count']);
        $this->assertEquals(3, $stats['unique_decisions']);
    }
}
