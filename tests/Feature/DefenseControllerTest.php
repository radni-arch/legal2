<?php

namespace Tests\Feature;

use App\Models\LegalCase;
use App\Models\User;
use App\Modules\Defence\DefenceOnlyModule;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class DefenseControllerTest extends TestCase
{
    use UsesTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create authenticated user for tests
        $this->actingAs(User::factory()->create());
    }

    /** @test */
    public function it_improves_accused_status_with_valid_data()
    {
        $case = LegalCase::factory()->create();

        $mockModule = Mockery::mock(DefenceOnlyModule::class);
        $mockModule->shouldReceive('improveAccusedStatus')
            ->once()
            ->with($case->id, [
                'focus_areas' => ['constitutional_violations', 'mitigating_factors'],
                'include_mitigating' => true,
                'include_weaknesses' => true,
            ])
            ->andReturn([
                'analysis' => 'Comprehensive defense analysis',
                'opportunities' => ['weakness1', 'weakness2'],
                'score' => 0.85,
            ]);

        $this->app->instance(DefenceOnlyModule::class, $mockModule);

        $response = $this->postJson("/api/defense/improve-status/{$case->id}", [
            'focus_areas' => ['constitutional_violations', 'mitigating_factors'],
            'include_mitigating' => true,
            'include_weaknesses' => true,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'analysis' => 'Comprehensive defense analysis',
                    'score' => 0.85,
                ],
            ]);
    }

    /** @test */
    public function it_validates_improve_status_request_fields()
    {
        $case = LegalCase::factory()->create();

        $response = $this->postJson("/api/defense/improve-status/{$case->id}", [
            'focus_areas' => 'not_an_array', // Should be array
            'include_mitigating' => 'not_boolean', // Should be boolean
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['error', 'details']);
    }

    /** @test */
    public function it_handles_improve_status_errors_gracefully()
    {
        $case = LegalCase::factory()->create();

        $mockModule = Mockery::mock(DefenceOnlyModule::class);
        $mockModule->shouldReceive('improveAccusedStatus')
            ->once()
            ->andThrow(new \Exception('Analysis failed'));

        $this->app->instance(DefenceOnlyModule::class, $mockModule);

        $response = $this->postJson("/api/defense/improve-status/{$case->id}");

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'error' => 'Analysis failed',
            ]);
    }

    /** @test */
    public function it_gets_defense_strategy_successfully()
    {
        $case = LegalCase::factory()->create();

        $mockModule = Mockery::mock(DefenceOnlyModule::class);
        $mockModule->shouldReceive('generateDefenseStrategy')
            ->once()
            ->with($case->id)
            ->andReturn([
                'strategy' => 'Challenge evidence admissibility',
                'viability_score' => 0.78,
                'risks' => ['Risk 1', 'Risk 2'],
                'recommendations' => ['File motion to suppress'],
            ]);

        $this->app->instance(DefenceOnlyModule::class, $mockModule);

        $response = $this->getJson("/api/defense/strategy/{$case->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'strategy' => 'Challenge evidence admissibility',
                    'viability_score' => 0.78,
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'strategy',
                    'viability_score',
                    'risks',
                    'recommendations',
                ],
            ]);
    }

    /** @test */
    public function it_gets_defense_recommendations()
    {
        $case = LegalCase::factory()->create();

        $mockModule = Mockery::mock(DefenceOnlyModule::class);
        $mockModule->shouldReceive('getDefenseRecommendations')
            ->once()
            ->with($case->id)
            ->andReturn([
                'recommendations' => [
                    ['title' => 'Challenge search warrant', 'priority' => 'high'],
                    ['title' => 'Question witness credibility', 'priority' => 'medium'],
                ],
                'priority_actions' => ['Action 1'],
            ]);

        $this->app->instance(DefenceOnlyModule::class, $mockModule);

        $response = $this->getJson("/api/defense/recommendations/{$case->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonCount(2, 'data.recommendations');
    }

    /** @test */
    public function it_analyzes_prosecution_weaknesses()
    {
        $case = LegalCase::factory()->create();

        $mockModule = Mockery::mock(DefenceOnlyModule::class);
        $mockModule->shouldReceive('analyzeProsecutionWeaknesses')
            ->once()
            ->with($case->id)
            ->andReturn([
                ['description' => 'Weak chain of custody', 'severity' => 75],
                ['description' => 'Inconsistent witness testimony', 'severity' => 65],
                ['description' => 'Procedural error in arrest', 'severity' => 85],
            ]);

        $this->app->instance(DefenceOnlyModule::class, $mockModule);

        $response = $this->getJson("/api/defense/prosecution-weaknesses/{$case->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'count' => 3,
                    'high_severity_count' => 2,
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'weaknesses' => [
                        '*' => ['description', 'severity'],
                    ],
                    'count',
                    'high_severity_count',
                ],
            ]);
    }

    /** @test */
    public function it_identifies_mitigating_factors()
    {
        $case = LegalCase::factory()->create();

        $mockModule = Mockery::mock(DefenceOnlyModule::class);
        $mockModule->shouldReceive('identifyMitigatingFactors')
            ->once()
            ->with($case->id)
            ->andReturn([
                ['factor' => 'First-time offender', 'weight' => 0.8],
                ['factor' => 'Clean record', 'weight' => 0.7],
            ]);

        $this->app->instance(DefenceOnlyModule::class, $mockModule);

        $response = $this->getJson("/api/defense/mitigating-factors/{$case->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'count' => 2,
                ],
            ])
            ->assertJsonCount(2, 'data.factors');
    }

    /** @test */
    public function it_assesses_defense_strength()
    {
        $case = LegalCase::factory()->create();

        $mockModule = Mockery::mock(DefenceOnlyModule::class);
        $mockModule->shouldReceive('assessDefenseStrength')
            ->once()
            ->with($case->id)
            ->andReturn([
                'overall_score' => 0.72,
                'strengths' => ['Strong alibi', 'Weak prosecution evidence'],
                'weaknesses' => ['Prior conviction'],
                'confidence' => 0.85,
            ]);

        $this->app->instance(DefenceOnlyModule::class, $mockModule);

        $response = $this->getJson("/api/defense/strength/{$case->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'overall_score' => 0.72,
                    'confidence' => 0.85,
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'overall_score',
                    'strengths',
                    'weaknesses',
                    'confidence',
                ],
            ]);
    }

    /** @test */
    public function it_handles_module_exceptions_gracefully()
    {
        $case = LegalCase::factory()->create();

        $mockModule = Mockery::mock(DefenceOnlyModule::class);
        $mockModule->shouldReceive('generateDefenseStrategy')
            ->once()
            ->andThrow(new \Exception('Service unavailable'));

        $this->app->instance(DefenceOnlyModule::class, $mockModule);

        $response = $this->getJson("/api/defense/strategy/{$case->id}");

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'error' => 'Service unavailable',
            ]);
    }

    /** @test */
    public function it_uses_default_options_when_not_provided()
    {
        $case = LegalCase::factory()->create();

        $mockModule = Mockery::mock(DefenceOnlyModule::class);
        $mockModule->shouldReceive('improveAccusedStatus')
            ->once()
            ->with($case->id, [
                'focus_areas' => [],
                'include_mitigating' => true,
                'include_weaknesses' => true,
            ])
            ->andReturn(['result' => 'success']);

        $this->app->instance(DefenceOnlyModule::class, $mockModule);

        $response = $this->postJson("/api/defense/improve-status/{$case->id}", []);

        $response->assertStatus(200);
    }
}
