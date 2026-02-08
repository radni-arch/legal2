<?php

namespace Tests\Unit\Modules\Defence;

use App\Models\LegalCase;
use App\Modules\Defence\Actions\ImproveAccusedStatusAction;
use App\Modules\Defence\Services\DefenseRecommendationService;
use App\Modules\Defence\Services\DefenseStrategyAnalyzer;
use App\Services\LegalReasoning\ArgumentGenerator;
use App\Services\LegalReasoning\RiskAssessor;
use App\Services\OpenAIService;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class ImproveAccusedStatusActionTest extends TestCase
{
    use UsesTestDatabase;

    protected ImproveAccusedStatusAction $action;

    protected $openAIMock;

    protected $strategyAnalyzerMock;

    protected $recommendationServiceMock;

    protected $argumentGeneratorMock;

    protected $riskAssessorMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->openAIMock = Mockery::mock(OpenAIService::class);
        $this->strategyAnalyzerMock = Mockery::mock(DefenseStrategyAnalyzer::class);
        $this->recommendationServiceMock = Mockery::mock(DefenseRecommendationService::class);
        $this->argumentGeneratorMock = Mockery::mock(ArgumentGenerator::class);
        $this->riskAssessorMock = Mockery::mock(RiskAssessor::class);

        $this->action = new ImproveAccusedStatusAction(
            $this->openAIMock,
            $this->strategyAnalyzerMock,
            $this->recommendationServiceMock,
            $this->argumentGeneratorMock,
            $this->riskAssessorMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_executes_complete_status_improvement_analysis()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $this->mockAllPhases($case->id);

        // Act
        $result = $this->action->execute($case->id);

        // Assert
        $this->assertArrayHasKey('case_id', $result);
        $this->assertArrayHasKey('execution_time', $result);
        $this->assertArrayHasKey('timestamp', $result);
        $this->assertArrayHasKey('current_status', $result);
        $this->assertArrayHasKey('defense_strengths', $result);
        $this->assertArrayHasKey('mitigating_factors', $result);
        $this->assertArrayHasKey('defense_arguments', $result);
        $this->assertArrayHasKey('prosecution_weaknesses', $result);
        $this->assertArrayHasKey('exploitable_points', $result);
        $this->assertArrayHasKey('risk_analysis', $result);
        $this->assertArrayHasKey('recommendations', $result);
        $this->assertArrayHasKey('action_plan', $result);
        $this->assertArrayHasKey('improvement_score', $result);
        $this->assertArrayHasKey('status_upgrade_potential', $result);
        $this->assertArrayHasKey('executive_summary', $result);
    }

    /** @test */
    public function it_assesses_current_status_with_llm()
    {
        // Arrange
        $case = LegalCase::factory()->create([
            'title' => 'Theft Case',
            'description' => 'Accused of stealing from store',
        ]);

        $this->mockCurrentStatusAssessment();
        $this->mockAllOtherPhases($case->id);

        // Act
        $result = $this->action->execute($case->id);

        // Assert
        $this->assertArrayHasKey('current_status', $result);
        $this->assertArrayHasKey('charges', $result['current_status']);
        $this->assertArrayHasKey('evidence_strength_against', $result['current_status']);
        $this->assertArrayHasKey('current_legal_position', $result['current_status']);
        $this->assertArrayHasKey('likely_outcome_baseline', $result['current_status']);
    }

    /** @test */
    public function it_identifies_prosecution_weaknesses()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $weaknesses = [
            [
                'description' => 'Weak evidence chain',
                'severity' => 85,
                'exploitability' => 'High',
                'impact' => 'Significant',
                'exploitation_strategy' => 'File suppression motion',
            ],
        ];

        $this->mockAllPhases($case->id, $weaknesses);

        // Act
        $result = $this->action->execute($case->id);

        // Assert
        $this->assertEquals($weaknesses, $result['prosecution_weaknesses']);
    }

    /** @test */
    public function it_identifies_exploitable_points()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $weaknesses = [
            ['description' => 'Critical flaw', 'severity' => 90, 'exploitation_strategy' => 'Motion to dismiss', 'impact' => 'Case dismissal'],
            ['description' => 'High severity issue', 'severity' => 75, 'exploitation_strategy' => 'Challenge', 'impact' => 'High'],
            ['description' => 'Medium issue', 'severity' => 60, 'exploitation_strategy' => 'Note', 'impact' => 'Medium'],
        ];

        $this->mockAllPhases($case->id, $weaknesses);

        // Act
        $result = $this->action->execute($case->id);

        // Assert
        $exploitablePoints = $result['exploitable_points'];
        $this->assertCount(2, $exploitablePoints); // Only severity >= 70

        $this->assertEquals('Critical flaw', $exploitablePoints[0]['weakness']);
        $this->assertEquals(90, $exploitablePoints[0]['severity']);
        $this->assertEquals('Motion to dismiss', $exploitablePoints[0]['exploitation_strategy']);
    }

    /** @test */
    public function it_generates_defense_arguments()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $arguments = [
            [
                'argument' => 'Lack of evidence',
                'strength_score' => 0.85,
                'irac_structure' => ['issue' => 'Evidence', 'rule' => 'Beyond reasonable doubt'],
            ],
        ];

        $this->mockAllPhases($case->id, [], [], $arguments);

        // Act
        $result = $this->action->execute($case->id);

        // Assert
        $this->assertCount(1, $result['defense_arguments']);
        $this->assertArrayHasKey('prosecution_weaknesses_addressed', $result['defense_arguments'][0]);
    }

    /** @test */
    public function it_calculates_improvement_potential()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $currentStatus = ['legal_position_strength' => 30];
        $defenseStrengths = ['strong_points' => [
            ['description' => 'Point 1'],
            ['description' => 'Point 2'],
        ]];
        $mitigatingFactors = [
            ['description' => 'Factor 1'],
            ['description' => 'Factor 2'],
            ['description' => 'Factor 3'],
        ];
        $weaknesses = [
            ['description' => 'W1', 'severity' => 80],
            ['description' => 'W2', 'severity' => 75],
        ];

        $this->mockAllPhases($case->id, $weaknesses, $defenseStrengths, [], $mitigatingFactors, $currentStatus);

        // Act
        $result = $this->action->execute($case->id);

        // Assert
        $score = $result['improvement_score'];
        $this->assertArrayHasKey('baseline_score', $score);
        $this->assertArrayHasKey('defense_contribution', $score);
        $this->assertArrayHasKey('mitigation_contribution', $score);
        $this->assertArrayHasKey('prosecution_weakness_contribution', $score);
        $this->assertArrayHasKey('maximum_potential_score', $score);
        $this->assertArrayHasKey('realistic_improved_score', $score);
        $this->assertArrayHasKey('overall_score', $score);

        // Verify calculations
        $this->assertEquals(30, $score['baseline_score']);
        $this->assertEquals(10, $score['defense_contribution']); // 2 * 5
        $this->assertEquals(9, $score['mitigation_contribution']); // 3 * 3
        $this->assertEquals(8, $score['prosecution_weakness_contribution']); // 2 * 4
    }

    /** @test */
    public function it_caps_improvement_score_at_100()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $currentStatus = ['legal_position_strength' => 80];
        $defenseStrengths = ['strong_points' => array_fill(0, 10, ['description' => 'Strong point'])];
        $mitigatingFactors = array_fill(0, 10, ['description' => 'Factor']);
        $weaknesses = array_fill(0, 10, ['description' => 'Weakness', 'severity' => 90]);

        $this->mockAllPhases($case->id, $weaknesses, $defenseStrengths, [], $mitigatingFactors, $currentStatus);

        // Act
        $result = $this->action->execute($case->id);

        // Assert
        $this->assertLessThanOrEqual(100, $result['improvement_score']['maximum_potential_score']);
        $this->assertLessThanOrEqual(100, $result['improvement_score']['realistic_improved_score']);
    }

    /** @test */
    public function it_determines_status_upgrade_potential_for_high_score()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $currentStatus = ['legal_position_strength' => 50];
        $defenseStrengths = ['strong_points' => array_fill(0, 8, ['description' => 'Point'])];
        $mitigatingFactors = array_fill(0, 5, ['description' => 'Factor']);
        $weaknesses = array_fill(0, 5, ['description' => 'Weakness', 'severity' => 85]);

        $this->mockAllPhases($case->id, $weaknesses, $defenseStrengths, [], $mitigatingFactors, $currentStatus);

        // Act
        $result = $this->action->execute($case->id);

        // Assert
        $potential = $result['status_upgrade_potential'];
        $this->assertNotEmpty($potential);

        // Score should be >= 80, so should include "Case Dismissal"
        $outcomes = array_column($potential, 'outcome');
        $this->assertContains('Case Dismissal', $outcomes);
    }

    /** @test */
    public function it_determines_status_upgrade_potential_for_medium_score()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $currentStatus = ['legal_position_strength' => 40];
        $defenseStrengths = ['strong_points' => [['description' => 'Point 1'], ['description' => 'Point 2']]];
        $mitigatingFactors = [['description' => 'Factor 1'], ['description' => 'Factor 2']];
        $weaknesses = [['description' => 'W1', 'severity' => 75]];

        $this->mockAllPhases($case->id, $weaknesses, $defenseStrengths, [], $mitigatingFactors, $currentStatus);

        // Act
        $result = $this->action->execute($case->id);

        // Assert
        $potential = $result['status_upgrade_potential'];
        $outcomes = array_column($potential, 'outcome');

        // Score should be in 50-65 range
        $this->assertContains('Favorable Plea Deal', $outcomes);
        $this->assertContains('Improved Trial Position', $outcomes);
    }

    /** @test */
    public function it_creates_action_plan_with_proper_categorization()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $recommendations = [
            ['action' => 'Urgent action', 'priority' => 'urgent', 'timeline' => 'immediate', 'impact' => 'High', 'resources' => []],
            ['action' => 'Short term action', 'priority' => 'high', 'timeline' => 'short_term', 'impact' => 'Medium', 'resources' => []],
            ['action' => 'Long term action', 'priority' => 'medium', 'timeline' => 'long_term', 'impact' => 'Low', 'resources' => []],
        ];

        $this->mockAllPhases($case->id, [], [], [], [], null, $recommendations);

        // Act
        $result = $this->action->execute($case->id);

        // Assert
        $plan = $result['action_plan'];
        $this->assertArrayHasKey('immediate_actions', $plan);
        $this->assertArrayHasKey('short_term_actions', $plan);
        $this->assertArrayHasKey('long_term_actions', $plan);

        $this->assertCount(1, $plan['immediate_actions']);
        $this->assertCount(1, $plan['short_term_actions']);
        $this->assertCount(1, $plan['long_term_actions']);
    }

    /** @test */
    public function it_generates_executive_summary()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $currentStatus = ['legal_position_strength' => 40];
        $defenseStrengths = ['strong_points' => [['description' => 'S1'], ['description' => 'S2']]];
        $weaknesses = [['description' => 'W1', 'severity' => 80], ['description' => 'W2', 'severity' => 75]];

        $this->mockAllPhases($case->id, $weaknesses, $defenseStrengths, [], [], $currentStatus);

        // Act
        $result = $this->action->execute($case->id);

        // Assert
        $summary = $result['executive_summary'];
        $this->assertStringContainsString('DEFENSE STATUS IMPROVEMENT ANALYSIS', $summary);
        $this->assertStringContainsString('Current Position:', $summary);
        $this->assertStringContainsString('Improved Position', $summary);
        $this->assertStringContainsString('KEY FINDINGS:', $summary);
        $this->assertStringContainsString('RECOMMENDATION:', $summary);
    }

    /** @test */
    public function it_provides_aggressive_recommendation_for_strong_position()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $currentStatus = ['legal_position_strength' => 50];
        $defenseStrengths = ['strong_points' => array_fill(0, 6, ['description' => 'Point'])];
        $weaknesses = array_fill(0, 4, ['description' => 'W', 'severity' => 85]);

        $this->mockAllPhases($case->id, $weaknesses, $defenseStrengths, [], [], $currentStatus);

        // Act
        $result = $this->action->execute($case->id);

        // Assert
        $this->assertStringContainsString('aggressive defense strategy', $result['executive_summary']);
    }

    /** @test */
    public function it_provides_negotiation_recommendation_for_moderate_position()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $currentStatus = ['legal_position_strength' => 40];
        $defenseStrengths = ['strong_points' => [['description' => 'Point']]];
        $weaknesses = [['description' => 'W', 'severity' => 70]];
        $mitigatingFactors = [
            ['description' => 'Factor 1'],
            ['description' => 'Factor 2'],
        ];

        $this->mockAllPhases($case->id, $weaknesses, $defenseStrengths, [], $mitigatingFactors, $currentStatus);

        // Act
        $result = $this->action->execute($case->id);

        // Assert
        $summary = $result['executive_summary'];
        $this->assertStringContainsString('negotiated settlement', $summary);
    }

    /** @test */
    public function it_provides_mitigation_recommendation_for_weak_position()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $currentStatus = ['legal_position_strength' => 20];
        $defenseStrengths = ['strong_points' => []];
        $weaknesses = [];

        $this->mockAllPhases($case->id, $weaknesses, $defenseStrengths, [], [], $currentStatus);

        // Act
        $result = $this->action->execute($case->id);

        // Assert
        $summary = $result['executive_summary'];
        $this->assertStringContainsString('damage mitigation', $summary);
    }

    /** @test */
    public function it_tracks_execution_time()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $this->mockAllPhases($case->id);

        // Act
        $result = $this->action->execute($case->id);

        // Assert
        $this->assertArrayHasKey('execution_time', $result);
        $this->assertIsFloat($result['execution_time']);
        $this->assertGreaterThan(0, $result['execution_time']);
    }

    /** @test */
    public function it_includes_timestamp_in_results()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $this->mockAllPhases($case->id);

        // Act
        $result = $this->action->execute($case->id);

        // Assert
        $this->assertArrayHasKey('timestamp', $result);
        $this->assertNotEmpty($result['timestamp']);
    }

    /** @test */
    public function it_handles_case_not_found()
    {
        // Arrange
        $nonExistentId = 'non-existent-id';

        // Act & Assert
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->action->execute($nonExistentId);
    }

    /** @test */
    public function it_handles_exception_during_execution()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $this->openAIMock
            ->shouldReceive('chat')
            ->andThrow(new \Exception('API failure'));

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('API failure');
        $this->action->execute($case->id);
    }

    /** @test */
    public function it_maps_weaknesses_to_arguments()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $weaknesses = [
            ['description' => 'Lack of physical evidence', 'severity' => 80],
        ];

        $arguments = [
            [
                'argument' => 'The prosecution has failed to provide physical evidence',
                'strength_score' => 0.85,
            ],
        ];

        $this->mockAllPhases($case->id, $weaknesses, [], $arguments);

        // Act
        $result = $this->action->execute($case->id);

        // Assert
        $this->assertNotEmpty($result['defense_arguments']);
        $this->assertArrayHasKey('prosecution_weaknesses_addressed', $result['defense_arguments'][0]);
    }

    /** @test */
    public function it_executes_all_nine_phases()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $this->mockAllPhases($case->id);

        // Act
        $result = $this->action->execute($case->id);

        // Assert
        // Verify all 9 phases produced results
        $this->assertNotNull($result['current_status']); // Phase 1
        $this->assertNotNull($result['prosecution_weaknesses']); // Phase 2
        $this->assertNotNull($result['defense_strengths']); // Phase 3
        $this->assertNotNull($result['mitigating_factors']); // Phase 4
        $this->assertNotNull($result['defense_arguments']); // Phase 5
        $this->assertNotNull($result['risk_analysis']); // Phase 6
        $this->assertNotNull($result['recommendations']); // Phase 7
        $this->assertNotNull($result['action_plan']); // Phase 8
        $this->assertNotNull($result['improvement_score']); // Phase 9
    }

    /** @test */
    public function it_loads_case_with_documents_relationship()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $this->mockAllPhases($case->id);

        // Act
        $result = $this->action->execute($case->id);

        // Assert
        $this->assertEquals($case->id, $result['case_id']);
    }

    /** @test */
    public function it_calculates_realistic_improved_score()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $currentStatus = ['legal_position_strength' => 30];
        $defenseStrengths = ['strong_points' => [['description' => 'Point']]];

        $this->mockAllPhases($case->id, [], $defenseStrengths, [], [], $currentStatus);

        // Act
        $result = $this->action->execute($case->id);

        // Assert
        $score = $result['improvement_score'];
        $baseScore = $score['baseline_score'];
        $maxScore = $score['maximum_potential_score'];
        $realisticScore = $score['realistic_improved_score'];

        // Realistic should be 70% of the improvement + baseline
        $expectedRealistic = $baseScore + ($maxScore - $baseScore) * 0.7;
        $this->assertEquals(round($expectedRealistic, 1), $realisticScore);
    }

    /** @test */
    public function it_includes_improvement_range_in_score()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $this->mockAllPhases($case->id);

        // Act
        $result = $this->action->execute($case->id);

        // Assert
        $score = $result['improvement_score'];
        $this->assertArrayHasKey('improvement_range', $score);
        $this->assertArrayHasKey('best_case', $score['improvement_range']);
        $this->assertArrayHasKey('likely_case', $score['improvement_range']);
        $this->assertArrayHasKey('worst_case', $score['improvement_range']);
    }

    // Helper Methods

    protected function mockAllPhases(
        string $caseId,
        array $weaknesses = [],
        array $defenseStrengths = [],
        array $arguments = [],
        array $mitigatingFactors = [],
        ?array $currentStatus = null,
        array $recommendations = []
    ): void {
        $this->mockCurrentStatusAssessment($currentStatus);
        $this->mockProsecutionWeaknesses($weaknesses);
        $this->mockDefenseStrengths($defenseStrengths);
        $this->mockMitigatingFactors($mitigatingFactors);
        $this->mockDefenseArguments($caseId, $arguments);
        $this->mockRiskAssessment($caseId);
        $this->mockRecommendations($recommendations);
    }

    protected function mockAllOtherPhases(string $caseId): void
    {
        $this->mockProsecutionWeaknesses();
        $this->mockDefenseStrengths();
        $this->mockMitigatingFactors();
        $this->mockDefenseArguments($caseId);
        $this->mockRiskAssessment($caseId);
        $this->mockRecommendations();
    }

    protected function mockCurrentStatusAssessment(?array $override = null): void
    {
        $status = $override ?? [
            'charges' => ['Theft'],
            'evidence_strength' => 60,
            'legal_position_strength' => 50,
            'likely_outcome' => 'Conviction likely',
            'immediate_concerns' => ['Strong evidence'],
            'vulnerabilities' => ['Weak alibi'],
        ];

        $this->openAIMock
            ->shouldReceive('chat')
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode($status)]],
                ],
            ]);
    }

    protected function mockProsecutionWeaknesses(array $weaknesses = []): void
    {
        $default = $weaknesses ?: [
            ['description' => 'Weak evidence', 'severity' => 70],
        ];

        $this->strategyAnalyzerMock
            ->shouldReceive('findProsecutionWeaknesses')
            ->andReturn($default);
    }

    protected function mockDefenseStrengths(array $strengths = []): void
    {
        $default = $strengths ?: [
            'overall_strength' => 50,
            'strong_points' => [['description' => 'Alibi']],
            'available_defenses' => ['Alibi'],
            'evidence_support' => [],
        ];

        $this->strategyAnalyzerMock
            ->shouldReceive('assessDefenseStrength')
            ->andReturn($default);
    }

    protected function mockMitigatingFactors(array $factors = []): void
    {
        $default = $factors ?: [
            ['description' => 'First-time offender'],
        ];

        $this->strategyAnalyzerMock
            ->shouldReceive('identifyMitigatingFactors')
            ->andReturn($default);
    }

    protected function mockDefenseArguments(string $caseId, array $arguments = []): void
    {
        $default = $arguments ?: [
            [
                'argument' => 'Lack of evidence',
                'strength_score' => 0.8,
            ],
        ];

        $this->argumentGeneratorMock
            ->shouldReceive('generateArguments')
            ->with($caseId, Mockery::any())
            ->andReturn(['arguments' => $default]);
    }

    protected function mockRiskAssessment(string $caseId): void
    {
        $this->riskAssessorMock
            ->shouldReceive('assessRisks')
            ->with($caseId)
            ->andReturn([
                'overall_risk' => 'Medium',
                'risk_factors' => [],
            ]);
    }

    protected function mockRecommendations(array $recommendations = []): void
    {
        $default = $recommendations ?: [
            [
                'action' => 'File motion',
                'priority' => 'high',
                'timeline' => 'short_term',
                'impact' => 'High',
                'resources' => ['Attorney'],
                'score' => 100,
            ],
        ];

        $this->recommendationServiceMock
            ->shouldReceive('generateRecommendations')
            ->andReturn($default);
    }
}
