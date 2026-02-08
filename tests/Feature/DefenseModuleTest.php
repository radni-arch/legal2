<?php

namespace Tests\Feature;

use App\Models\LegalCase;
use App\Modules\Defence\DefenceOnlyModule;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class DefenseModuleTest extends TestCase
{
    use UsesTestDatabase;

    protected LegalCase $testCase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test case
        $this->testCase = LegalCase::create([
            'title' => 'Test Criminal Defense Case',
            'description' => 'Defendant charged with theft. Claims alibi and questions chain of custody of evidence.',
            'legal_area' => 'criminal',
            'case_type' => 'criminal',
            'status' => 'active',
        ]);
    }

    /** @test */
    public function it_can_improve_accused_status()
    {
        $defense = app(DefenceOnlyModule::class);

        $result = $defense->improveAccusedStatus($this->testCase->id);

        $this->assertArrayHasKey('current_status', $result);
        $this->assertArrayHasKey('defense_strengths', $result);
        $this->assertArrayHasKey('prosecution_weaknesses', $result);
        $this->assertArrayHasKey('improvement_score', $result);
        $this->assertArrayHasKey('recommendations', $result);
        $this->assertArrayHasKey('action_plan', $result);
    }

    /** @test */
    public function it_identifies_prosecution_weaknesses()
    {
        $defense = app(DefenceOnlyModule::class);

        $weaknesses = $defense->analyzeProsecutionWeaknesses($this->testCase->id);

        $this->assertIsArray($weaknesses);
        // Should identify at least some weaknesses
        $this->assertNotEmpty($weaknesses);
    }

    /** @test */
    public function it_identifies_mitigating_factors()
    {
        $defense = app(DefenceOnlyModule::class);

        $factors = $defense->identifyMitigatingFactors($this->testCase->id);

        $this->assertIsArray($factors);
    }

    /** @test */
    public function it_assesses_defense_strength()
    {
        $defense = app(DefenceOnlyModule::class);

        $strength = $defense->assessDefenseStrength($this->testCase->id);

        $this->assertArrayHasKey('overall_strength', $strength);
        $this->assertArrayHasKey('strong_points', $strength);
    }

    /** @test */
    public function improvement_score_is_calculated_correctly()
    {
        $defense = app(DefenceOnlyModule::class);

        $result = $defense->improveAccusedStatus($this->testCase->id);

        $score = $result['improvement_score'];

        $this->assertArrayHasKey('baseline_score', $score);
        $this->assertArrayHasKey('realistic_improved_score', $score);
        $this->assertArrayHasKey('overall_score', $score);

        // Improved score should be >= baseline
        $this->assertGreaterThanOrEqual(
            $score['baseline_score'],
            $score['realistic_improved_score']
        );
    }

    /** @test */
    public function action_plan_contains_prioritized_actions()
    {
        $defense = app(DefenceOnlyModule::class);

        $result = $defense->improveAccusedStatus($this->testCase->id);

        $actionPlan = $result['action_plan'];

        $this->assertArrayHasKey('immediate_actions', $actionPlan);
        $this->assertArrayHasKey('short_term_actions', $actionPlan);
        $this->assertArrayHasKey('long_term_actions', $actionPlan);
    }

    /** @test */
    public function api_endpoint_returns_status_improvement()
    {
        $response = $this->postJson("/api/defense/improve-status/{$this->testCase->id}", [
            'include_mitigating' => true,
            'include_weaknesses' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'current_status',
                    'defense_strengths',
                    'prosecution_weaknesses',
                    'improvement_score',
                    'recommendations',
                    'action_plan',
                ],
            ]);
    }

    /** @test */
    public function api_endpoint_returns_prosecution_weaknesses()
    {
        $response = $this->getJson("/api/defense/prosecution-weaknesses/{$this->testCase->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'weaknesses',
                    'count',
                    'high_severity_count',
                ],
            ]);
    }

    /** @test */
    public function api_endpoint_returns_recommendations()
    {
        $response = $this->getJson("/api/defense/recommendations/{$this->testCase->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }

    /** @test */
    public function high_severity_weaknesses_are_prioritized()
    {
        $defense = app(DefenceOnlyModule::class);

        $result = $defense->improveAccusedStatus($this->testCase->id);

        $highSeverity = array_filter(
            $result['prosecution_weaknesses'] ?? [],
            fn ($w) => ($w['severity'] ?? 0) >= 70
        );

        // These should be in the exploitable points
        $exploitable = $result['exploitable_points'] ?? [];

        $this->assertGreaterThanOrEqual(count($highSeverity), count($exploitable));
    }
}
