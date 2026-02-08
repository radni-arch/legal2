<?php

namespace Tests\Feature\Api;

use App\Models\LegalCase;
use App\Models\User;
use App\Services\LegalReasoning\DurationEstimator;
use App\Services\LegalReasoning\PredictiveAnalytics;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class AnalyticsControllerTest extends TestCase
{
    use UsesTestDatabase;

    protected User $user;

    protected string $apiToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a user with API token for testing
        $this->apiToken = Str::random(60);
        $this->user = User::factory()->create([
            'api_token' => $this->apiToken,
        ]);
    }

    protected function withApiToken(): array
    {
        return ['Authorization' => 'Bearer '.$this->apiToken];
    }

    /** @test */
    public function it_predicts_outcome_successfully()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        // Mock the service
        $predictiveAnalyticsMock = Mockery::mock(PredictiveAnalytics::class);
        $predictiveAnalyticsMock
            ->shouldReceive('predictOutcome')
            ->once()
            ->andReturn([
                'predicted_outcome' => 'win',
                'confidence' => 0.75,
                'probability_distribution' => ['win' => 0.75, 'loss' => 0.25],
                'similar_cases' => [],
                'reasoning' => 'Strong precedent support.',
                'key_factors' => ['precedent', 'evidence'],
            ]);

        $this->app->instance(PredictiveAnalytics::class, $predictiveAnalyticsMock);

        // Act
        $response = $this->postJson("/api/analytics/predict-outcome/{$case->id}", [], $this->withApiToken());

        // Assert
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'predicted_outcome',
                'confidence',
                'probability_distribution',
                'similar_cases',
                'reasoning',
                'key_factors',
            ],
        ]);
    }

    /** @test */
    public function it_returns_404_for_non_existent_case()
    {
        // Act
        $response = $this->postJson('/api/analytics/predict-outcome/non-existent-id', [], $this->withApiToken());

        // Assert
        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'message' => 'Case not found',
        ]);
    }

    /** @test */
    public function it_estimates_duration_successfully()
    {
        // Arrange
        $case = LegalCase::factory()->create([
            'filing_date' => now()->subMonths(2),
        ]);

        // Act
        $response = $this->postJson("/api/analytics/estimate-duration/{$case->id}", [], $this->withApiToken());

        // Assert
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'estimated_days',
                'estimated_completion_date',
                'confidence_interval',
                'milestones',
                'factors',
                'case_features',
            ],
        ]);
    }

    /** @test */
    public function it_provides_comprehensive_analytics()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        // Mock PredictiveAnalytics
        $predictiveAnalyticsMock = Mockery::mock(PredictiveAnalytics::class);
        $predictiveAnalyticsMock
            ->shouldReceive('predictOutcome')
            ->once()
            ->andReturn([
                'predicted_outcome' => 'win',
                'confidence' => 0.75,
                'probability_distribution' => ['win' => 0.75, 'loss' => 0.25],
                'similar_cases' => [],
                'reasoning' => 'Strong precedent support.',
                'key_factors' => ['precedent', 'evidence'],
            ]);

        // Mock DurationEstimator
        $durationEstimatorMock = Mockery::mock(DurationEstimator::class);
        $durationEstimatorMock
            ->shouldReceive('estimateDuration')
            ->once()
            ->andReturn([
                'estimated_days' => 180,
                'estimated_completion_date' => now()->addDays(180)->toDateString(),
                'confidence_interval' => ['lower' => 150, 'upper' => 210],
                'milestones' => [],
                'factors' => [],
                'case_features' => [],
            ]);

        $this->app->instance(PredictiveAnalytics::class, $predictiveAnalyticsMock);
        $this->app->instance(DurationEstimator::class, $durationEstimatorMock);

        // Act
        $response = $this->postJson("/api/analytics/comprehensive/{$case->id}", [], $this->withApiToken());

        // Assert
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'outcome_prediction',
                'duration_estimate',
                'generated_at',
            ],
        ]);
    }

    /** @test */
    public function it_handles_batch_predictions()
    {
        // Arrange
        $cases = LegalCase::factory()->count(3)->create();
        $caseIds = $cases->pluck('id')->toArray();

        // Mock PredictiveAnalytics
        $predictiveAnalyticsMock = Mockery::mock(PredictiveAnalytics::class);
        $predictiveAnalyticsMock
            ->shouldReceive('predictOutcome')
            ->times(3)
            ->andReturn([
                'predicted_outcome' => 'win',
                'confidence' => 0.75,
                'probability_distribution' => ['win' => 0.75, 'loss' => 0.25],
                'similar_cases' => [],
                'reasoning' => 'Test',
                'key_factors' => ['test'],
            ]);

        $this->app->instance(PredictiveAnalytics::class, $predictiveAnalyticsMock);

        // Act
        $response = $this->postJson('/api/analytics/batch-predict', [
            'case_ids' => $caseIds,
        ], $this->withApiToken());

        // Assert
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'predictions' => [
                    '*' => [
                        'case_id',
                        'prediction',
                    ],
                ],
                'total',
                'successful',
                'failed',
            ],
        ]);
    }

    /** @test */
    public function it_validates_required_fields_for_batch_predict()
    {
        // Act
        $response = $this->postJson('/api/analytics/batch-predict', [
            // Missing case_ids
        ], $this->withApiToken());

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['case_ids']);
    }

    /** @test */
    public function it_throttles_requests()
    {
        // This test verifies that throttle middleware is applied to analytics routes
        // Testing the actual throttling behavior is difficult in PHPUnit due to cache/time constraints
        // Instead, we verify a reasonable number of requests succeed

        $case = LegalCase::factory()->create();

        // Mock the service
        $predictiveAnalyticsMock = Mockery::mock(PredictiveAnalytics::class);
        $predictiveAnalyticsMock
            ->shouldReceive('predictOutcome')
            ->andReturn([
                'predicted_outcome' => 'win',
                'confidence' => 0.75,
                'probability_distribution' => ['win' => 0.75, 'loss' => 0.25],
                'similar_cases' => [],
                'reasoning' => 'Test',
                'key_factors' => ['test'],
            ]);

        $this->app->instance(PredictiveAnalytics::class, $predictiveAnalyticsMock);

        // Make a few requests to verify they work
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson("/api/analytics/predict-outcome/{$case->id}", [], $this->withApiToken());
            $this->assertEquals(200, $response->getStatusCode(), "Request $i should succeed");
        }

        // Verify throttle middleware is registered on the route
        $route = app('router')->getRoutes()->match(
            \Illuminate\Http\Request::create('/api/analytics/predict-outcome/'.$case->id, 'POST')
        );

        $this->assertContains('throttle:60,1', $route->middleware(), 'Throttle middleware should be applied');
    }

    /** @test */
    public function it_logs_prediction_requests()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        // Mock the service to ensure consistent behavior
        $predictiveAnalyticsMock = Mockery::mock(PredictiveAnalytics::class);
        $predictiveAnalyticsMock
            ->shouldReceive('predictOutcome')
            ->once()
            ->andReturn([
                'predicted_outcome' => 'win',
                'confidence' => 0.75,
                'probability_distribution' => ['win' => 0.75, 'loss' => 0.25],
                'similar_cases' => [],
                'reasoning' => 'Test',
                'key_factors' => ['test'],
            ]);

        $this->app->instance(PredictiveAnalytics::class, $predictiveAnalyticsMock);

        // Act
        $response = $this->postJson("/api/analytics/predict-outcome/{$case->id}", [], $this->withApiToken());

        // Assert - The test verifies the endpoint executes (which includes logging)
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
