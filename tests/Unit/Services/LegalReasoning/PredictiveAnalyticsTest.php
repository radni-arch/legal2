<?php

namespace Tests\Unit\Services\LegalReasoning;

use App\Models\CasePrediction;
use App\Models\LegalCase;
use App\Services\LegalReasoning\DurationEstimator;
use App\Services\LegalReasoning\ImpactAnalyzer;
use App\Services\LegalReasoning\OutcomePredictor;
use App\Services\LegalReasoning\PredictiveAnalytics;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class PredictiveAnalyticsTest extends TestCase
{
    use UsesTestDatabase;

    protected PredictiveAnalytics $predictiveAnalytics;

    protected $outcomePredictorMock;

    protected $durationEstimatorMock;

    protected $impactAnalyzerMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outcomePredictorMock = Mockery::mock(OutcomePredictor::class);
        $this->durationEstimatorMock = Mockery::mock(DurationEstimator::class);
        $this->impactAnalyzerMock = Mockery::mock(ImpactAnalyzer::class);

        $this->predictiveAnalytics = new PredictiveAnalytics(
            $this->outcomePredictorMock,
            $this->durationEstimatorMock,
            $this->impactAnalyzerMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_predicts_outcome_and_persists_to_database()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $mockPrediction = [
            'predicted_outcome' => 'favorable',
            'confidence' => 0.85,
            'probability_distribution' => [
                'favorable' => 0.85,
                'unfavorable' => 0.15,
            ],
            'features' => [
                'case_type' => 'civil',
                'complexity_score' => 0.6,
            ],
            'similar_cases' => [
                [
                    'case_id' => 'test-case-1',
                    'similarity' => 0.9,
                    'outcome' => 'favorable',
                ],
            ],
            'reasoning' => 'Strong precedent support and favorable jurisdiction.',
        ];

        $this->outcomePredictorMock
            ->shouldReceive('predictOutcome')
            ->once()
            ->with((string) $case->id)
            ->andReturn($mockPrediction);

        // Act
        $result = $this->predictiveAnalytics->predictOutcome((string) $case->id);

        // Assert
        $this->assertEquals($mockPrediction, $result);

        // Verify database persistence
        $this->assertDatabaseHas('case_predictions', [
            'case_id' => $case->id,
            'prediction_type' => 'outcome',
            'confidence' => 0.85,
            'model_version' => '1.0.0',
        ]);

        $prediction = CasePrediction::where('case_id', $case->id)
            ->where('prediction_type', 'outcome')
            ->first();

        $this->assertNotNull($prediction);
        $this->assertEquals('favorable', $prediction->prediction['predicted_outcome']);
        $this->assertEquals($mockPrediction['features'], $prediction->features);
        $this->assertEquals($mockPrediction['similar_cases'], $prediction->similar_cases);
    }

    /** @test */
    public function it_estimates_duration_and_persists_to_database()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $mockEstimate = [
            'estimated_days' => 180,
            'estimated_completion_date' => now()->addDays(180)->toDateString(),
            'confidence_interval' => [
                'min' => 150,
                'max' => 210,
            ],
            'factors' => [
                'case_complexity' => 'moderate',
                'court_backlog' => 30,
            ],
            'milestones' => [
                ['name' => 'Discovery', 'days' => 60],
                ['name' => 'Motions', 'days' => 30],
            ],
        ];

        $this->durationEstimatorMock
            ->shouldReceive('estimateDuration')
            ->once()
            ->with((string) $case->id)
            ->andReturn($mockEstimate);

        // Act
        $result = $this->predictiveAnalytics->estimateDuration((string) $case->id);

        // Assert
        $this->assertEquals($mockEstimate, $result);

        // Verify database persistence
        $this->assertDatabaseHas('case_predictions', [
            'case_id' => $case->id,
            'prediction_type' => 'duration',
            'confidence' => 0.7,
            'model_version' => '1.0.0',
        ]);

        $prediction = CasePrediction::where('case_id', $case->id)
            ->where('prediction_type', 'duration')
            ->first();

        $this->assertNotNull($prediction);
        $this->assertEquals(180, $prediction->prediction['estimated_days']);
        $this->assertEquals($mockEstimate['factors'], $prediction->features);
    }

    /** @test */
    public function it_analyzes_decision_impact()
    {
        // Arrange
        $decisionId = Str::ulid()->toString();

        $mockImpactAnalysis = [
            'decision_id' => $decisionId,
            'impact_score' => 0.75,
            'citation_count' => 25,
            'jurisdictional_reach' => ['HR', 'EU'],
            'trend' => 'increasing',
        ];

        $this->impactAnalyzerMock
            ->shouldReceive('analyzeDecisionImpact')
            ->once()
            ->with($decisionId)
            ->andReturn($mockImpactAnalysis);

        // Act
        $result = $this->predictiveAnalytics->analyzeDecisionImpact($decisionId);

        // Assert
        $this->assertEquals($mockImpactAnalysis, $result);
        $this->assertEquals(0.75, $result['impact_score']);
        $this->assertEquals(25, $result['citation_count']);
    }

    /** @test */
    public function it_gets_comprehensive_analytics_successfully()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $mockOutcome = [
            'predicted_outcome' => 'favorable',
            'confidence' => 0.85,
            'probability_distribution' => ['favorable' => 0.85, 'unfavorable' => 0.15],
            'features' => ['case_type' => 'civil'],
            'similar_cases' => [],
            'reasoning' => 'Strong case',
        ];

        $mockDuration = [
            'estimated_days' => 180,
            'estimated_completion_date' => now()->addDays(180)->toDateString(),
            'confidence_interval' => ['min' => 150, 'max' => 210],
            'factors' => ['case_complexity' => 'moderate'],
            'milestones' => [],
        ];

        $this->outcomePredictorMock
            ->shouldReceive('predictOutcome')
            ->once()
            ->with((string) $case->id)
            ->andReturn($mockOutcome);

        $this->durationEstimatorMock
            ->shouldReceive('estimateDuration')
            ->once()
            ->with((string) $case->id)
            ->andReturn($mockDuration);

        // Act
        $result = $this->predictiveAnalytics->getComprehensiveAnalytics((string) $case->id);

        // Assert
        $this->assertArrayHasKey('outcome', $result);
        $this->assertArrayHasKey('duration', $result);
        $this->assertEquals($mockOutcome, $result['outcome']);
        $this->assertEquals($mockDuration, $result['duration']);

        // Verify both predictions were persisted
        $this->assertDatabaseCount('case_predictions', 2);
    }

    /** @test */
    public function it_handles_errors_in_comprehensive_analytics_gracefully()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $this->outcomePredictorMock
            ->shouldReceive('predictOutcome')
            ->once()
            ->andThrow(new \Exception('Outcome prediction failed'));

        $this->durationEstimatorMock
            ->shouldReceive('estimateDuration')
            ->once()
            ->andThrow(new \Exception('Duration estimation failed'));

        // Act
        $result = $this->predictiveAnalytics->getComprehensiveAnalytics($case->id);

        // Assert
        $this->assertArrayHasKey('outcome', $result);
        $this->assertArrayHasKey('duration', $result);
        $this->assertArrayHasKey('error', $result['outcome']);
        $this->assertArrayHasKey('error', $result['duration']);
        $this->assertEquals('Outcome prediction failed', $result['outcome']['error']);
        $this->assertEquals('Duration estimation failed', $result['duration']['error']);
    }

    /** @test */
    public function it_gets_case_prediction_history()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        // Create multiple predictions for the case
        CasePrediction::factory()->create([
            'case_id' => $case->id,
            'prediction_type' => 'outcome',
            'predicted_at' => now()->subDays(5),
        ]);

        CasePrediction::factory()->create([
            'case_id' => $case->id,
            'prediction_type' => 'duration',
            'predicted_at' => now()->subDays(3),
        ]);

        CasePrediction::factory()->create([
            'case_id' => $case->id,
            'prediction_type' => 'outcome',
            'predicted_at' => now()->subDay(),
        ]);

        // Act
        $result = $this->predictiveAnalytics->getCasePredictionHistory($case->id);

        // Assert
        $this->assertArrayHasKey('case_id', $result);
        $this->assertArrayHasKey('total_predictions', $result);
        $this->assertArrayHasKey('predictions', $result);
        $this->assertEquals($case->id, $result['case_id']);
        $this->assertEquals(3, $result['total_predictions']);
        $this->assertCount(3, $result['predictions']);

        // Verify ordered by predicted_at desc
        $this->assertGreaterThan(
            $result['predictions'][1]['predicted_at'],
            $result['predictions'][0]['predicted_at']
        );
    }

    /** @test */
    public function it_updates_actual_outcome_with_exact_match()
    {
        // Arrange
        $prediction = CasePrediction::factory()->create([
            'prediction_type' => 'outcome',
            'prediction' => [
                'predicted_outcome' => 'favorable',
                'probability_distribution' => ['favorable' => 0.8],
            ],
            'actual_outcome' => null,
            'accuracy_score' => null,
        ]);

        $actualOutcome = [
            'outcome' => 'favorable',
            'date' => now()->toDateString(),
        ];

        // Act
        $this->predictiveAnalytics->updateActualOutcome($prediction->id, $actualOutcome);

        // Assert
        $prediction->refresh();
        $this->assertEquals($actualOutcome, $prediction->actual_outcome);
        $this->assertNotNull($prediction->actual_outcome_at);
        $this->assertEquals(1.0, $prediction->accuracy_score); // Exact match
    }

    /** @test */
    public function it_updates_actual_outcome_with_category_match()
    {
        // Arrange
        $prediction = CasePrediction::factory()->create([
            'prediction_type' => 'outcome',
            'prediction' => [
                'predicted_outcome' => 'won',
                'probability_distribution' => ['won' => 0.7],
            ],
        ]);

        $actualOutcome = [
            'outcome' => 'settled', // Both in 'favorable' category
        ];

        // Act
        $this->predictiveAnalytics->updateActualOutcome($prediction->id, $actualOutcome);

        // Assert
        $prediction->refresh();
        $this->assertEquals(0.5, $prediction->accuracy_score); // Category match
    }

    /** @test */
    public function it_updates_actual_outcome_with_no_match()
    {
        // Arrange
        $prediction = CasePrediction::factory()->create([
            'prediction_type' => 'outcome',
            'prediction' => [
                'predicted_outcome' => 'favorable',
                'probability_distribution' => ['favorable' => 0.8],
            ],
        ]);

        $actualOutcome = [
            'outcome' => 'lost', // Different category
        ];

        // Act
        $this->predictiveAnalytics->updateActualOutcome($prediction->id, $actualOutcome);

        // Assert
        $prediction->refresh();
        $this->assertEquals(0.0, $prediction->accuracy_score); // No match
    }

    /** @test */
    public function it_handles_missing_prediction_fields_in_accuracy_calculation()
    {
        // Arrange
        $prediction = CasePrediction::factory()->create([
            'prediction_type' => 'duration',
            'prediction' => [
                'estimated_days' => 180,
                // No 'predicted_outcome' field
            ],
        ]);

        $actualOutcome = [
            'duration_days' => 175, // No 'outcome' field
        ];

        // Act
        $this->predictiveAnalytics->updateActualOutcome($prediction->id, $actualOutcome);

        // Assert
        $prediction->refresh();
        $this->assertEquals(0.0, $prediction->accuracy_score); // Missing fields
    }

    /** @test */
    public function it_propagates_exception_when_outcome_prediction_fails()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $this->outcomePredictorMock
            ->shouldReceive('predictOutcome')
            ->once()
            ->andThrow(new \Exception('AI service unavailable'));

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('AI service unavailable');

        $this->predictiveAnalytics->predictOutcome($case->id);
    }

    /** @test */
    public function it_propagates_exception_when_duration_estimation_fails()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $this->durationEstimatorMock
            ->shouldReceive('estimateDuration')
            ->once()
            ->andThrow(new \Exception('Historical data unavailable'));

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Historical data unavailable');

        $this->predictiveAnalytics->estimateDuration($case->id);
    }

    /** @test */
    public function it_propagates_exception_when_impact_analysis_fails()
    {
        // Arrange
        $decisionId = Str::ulid()->toString();

        $this->impactAnalyzerMock
            ->shouldReceive('analyzeDecisionImpact')
            ->once()
            ->andThrow(new \Exception('Graph database unavailable'));

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Graph database unavailable');

        $this->predictiveAnalytics->analyzeDecisionImpact($decisionId);
    }
}
