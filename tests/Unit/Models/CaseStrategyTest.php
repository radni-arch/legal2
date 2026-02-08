<?php

namespace Tests\Unit\Models;

use App\Models\CaseStrategy;
use App\Models\LegalCase;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class CaseStrategyTest extends TestCase
{
    use UsesTestDatabase;

    /** @test */
    public function it_belongs_to_legal_case()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $strategy = CaseStrategy::factory()->create(['case_id' => $case->id]);

        // Act
        $strategy->load('case');

        // Assert
        $this->assertInstanceOf(LegalCase::class, $strategy->case);
        $this->assertEquals($case->id, $strategy->case->id);
    }

    /** @test */
    public function it_casts_case_analysis_to_array()
    {
        // Arrange
        $strategy = CaseStrategy::factory()->create([
            'analysis' => ['strength' => 0.8, 'win_probability' => 0.75],
        ]);

        // Assert
        $this->assertIsArray($strategy->analysis);
        $this->assertArrayHasKey('strength', $strategy->analysis);
    }

    /** @test */
    public function it_casts_arguments_to_array()
    {
        // Arrange
        $strategy = CaseStrategy::factory()->create([
            'arguments' => [
                ['issue' => 'Test', 'strength' => 0.9],
            ],
        ]);

        // Assert
        $this->assertIsArray($strategy->arguments);
        $this->assertCount(1, $strategy->arguments);
    }

    /** @test */
    public function it_casts_risk_assessment_to_array()
    {
        // Arrange
        $strategy = CaseStrategy::factory()->create([
            'risks' => ['overall_risk' => 'MEDIUM', 'risk_score' => 0.5],
        ]);

        // Assert
        $this->assertIsArray($strategy->risks);
        $this->assertEquals('MEDIUM', $strategy->risks['overall_risk']);
    }

    /** @test */
    public function it_casts_action_plan_to_array()
    {
        // Arrange
        $strategy = CaseStrategy::factory()->create([
            'action_plan' => ['phases' => [], 'milestones' => []],
        ]);

        // Assert
        $this->assertIsArray($strategy->action_plan);
    }

    /** @test */
    public function it_stores_confidence_score_as_float()
    {
        // Arrange
        $strategy = CaseStrategy::factory()->create([
            'confidence_score' => 0.82,
        ]);

        // Assert
        $this->assertIsFloat($strategy->confidence_score);
        $this->assertEquals(0.82, $strategy->confidence_score);
    }

    /** @test */
    public function it_casts_created_at_correctly()
    {
        // Arrange
        $strategy = CaseStrategy::factory()->create();

        // Assert
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $strategy->created_at);
    }

    /** @test */
    public function it_can_filter_by_status()
    {
        // Arrange
        CaseStrategy::factory()->count(3)->create(['status' => 'active']);
        CaseStrategy::factory()->count(2)->create(['status' => 'archived']);

        // Act
        $activeStrategies = CaseStrategy::where('status', 'active')->get();

        // Assert
        $this->assertCount(3, $activeStrategies);
    }

    /** @test */
    public function it_can_filter_by_version()
    {
        // Arrange
        CaseStrategy::factory()->create(['version' => '1.0']);
        CaseStrategy::factory()->create(['version' => '1.1']);
        CaseStrategy::factory()->create(['version' => '2.0']);

        // Act
        $v1Strategies = CaseStrategy::where('version', 'like', '1.%')->get();

        // Assert
        $this->assertCount(2, $v1Strategies);
    }
}
