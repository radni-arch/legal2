<?php

namespace Tests\Unit\Services\LegalReasoning;

use App\Models\LegalCase;
use App\Services\LegalReasoning\ArgumentGenerator;
use App\Services\LegalReasoning\CitationAnalyzer;
use App\Services\LegalReasoning\OutcomePredictor;
use App\Services\LegalReasoning\RiskAssessor;
use App\Services\LegalReasoning\StrategicPlanner;
use App\Services\LegalReasoning\StrategyBuilder;
use App\Services\OpenAIService;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class StrategyBuilderTest extends TestCase
{
    use UsesTestDatabase;

    protected StrategyBuilder $strategyBuilder;

    protected $openAIMock;

    protected $outcomePredictorMock;

    protected $argumentGeneratorMock;

    protected $riskAssessorMock;

    protected $strategicPlannerMock;

    protected $citationAnalyzerMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock all dependencies
        $this->openAIMock = Mockery::mock(OpenAIService::class);
        $this->outcomePredictorMock = Mockery::mock(OutcomePredictor::class);
        $this->argumentGeneratorMock = Mockery::mock(ArgumentGenerator::class);
        $this->riskAssessorMock = Mockery::mock(RiskAssessor::class);
        $this->strategicPlannerMock = Mockery::mock(StrategicPlanner::class);
        $this->citationAnalyzerMock = Mockery::mock(CitationAnalyzer::class);

        $this->strategyBuilder = new StrategyBuilder(
            $this->openAIMock,
            $this->outcomePredictorMock,
            $this->argumentGeneratorMock,
            $this->riskAssessorMock,
            $this->strategicPlannerMock,
            $this->citationAnalyzerMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_builds_comprehensive_strategy_successfully()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        // Mock OutcomePredictor
        $this->outcomePredictorMock
            ->shouldReceive('predictOutcome')
            ->once()
            ->andReturn([
                'predicted_outcome' => 'win',
                'confidence' => 0.75,
                'probability_distribution' => ['win' => 0.75, 'loss' => 0.25],
                'similar_cases' => [],
            ]);

        // Mock ArgumentGenerator
        $this->argumentGeneratorMock
            ->shouldReceive('generateArguments')
            ->once()
            ->andReturn([
                'arguments' => [
                    [
                        'issue' => 'Contract breach',
                        'strength_score' => 0.8,
                        'supporting_precedents' => [
                            ['decision_id' => '123', 'authority_score' => 0.9],
                        ],
                    ],
                ],
                'strongest_argument' => [
                    'issue' => 'Contract breach',
                    'strength_score' => 0.8,
                ],
            ]);

        // Mock RiskAssessor
        $this->riskAssessorMock
            ->shouldReceive('assessRisks')
            ->once()
            ->andReturn([
                'risk_level' => 'MEDIUM',
                'risk_scores' => [
                    'legal' => 0.5,
                    'evidentiary' => 0.4,
                    'strategic' => 0.3,
                    'overall' => 0.4,
                ],
                'mitigation_strategies' => [],
            ]);

        // Mock StrategicPlanner
        $this->strategicPlannerMock
            ->shouldReceive('createActionPlan')
            ->once()
            ->andReturn([
                'phases' => [],
                'total_duration_days' => 300,
                'settlement_windows' => [],
                'resources_needed' => [],
            ]);

        // Mock LLM calls for SWOT and synthesis
        $this->openAIMock
            ->shouldReceive('complete')
            ->times(3) // SWOT, opponent analysis, synthesis
            ->andReturn([
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'strengths' => ['Strong evidence'],
                        'weaknesses' => ['Time constraints'],
                        'opportunities' => ['Settlement'],
                        'threats' => ['Opponent resources'],
                    ])],
                ]],
            ]);

        // Act
        $strategy = $this->strategyBuilder->buildCaseStrategy($case->id, ['Win the case']);

        // Assert
        $this->assertIsArray($strategy);
        $this->assertArrayHasKey('case_id', $strategy);
        $this->assertArrayHasKey('case_analysis', $strategy);
        $this->assertArrayHasKey('arguments', $strategy);
        $this->assertArrayHasKey('risk_assessment', $strategy);
        $this->assertArrayHasKey('action_plan', $strategy);
        $this->assertArrayHasKey('strategic_recommendations', $strategy);
        $this->assertArrayHasKey('executive_summary', $strategy);
        $this->assertArrayHasKey('confidence_score', $strategy);

        // Verify strategy was persisted
        $this->assertDatabaseHas('case_strategies', [
            'case_id' => $case->id,
            'status' => 'active',
        ]);
    }

    /** @test */
    public function it_calculates_confidence_score_correctly()
    {
        // Arrange
        $components = [
            'analysis' => [
                'case_strength_score' => 0.8,
                'win_probability' => 0.75,
            ],
            'risks' => [
                'risk_scores' => ['overall' => 0.3],
            ],
            'arguments' => [
                'strongest_argument' => ['strength_score' => 0.85],
            ],
            'precedents' => array_fill(0, 8, ['decision_id' => '123']),
        ];

        // Act
        $confidence = $this->invokePrivateMethod(
            $this->strategyBuilder,
            'calculateConfidenceScore',
            [$components]
        );

        // Assert
        $this->assertGreaterThan(0, $confidence);
        $this->assertLessThanOrEqual(1, $confidence);
        $this->assertGreaterThan(0.6, $confidence); // High confidence expected
    }

    /** @test */
    public function it_performs_swot_analysis()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $similarCases = [];

        $this->openAIMock
            ->shouldReceive('complete')
            ->once()
            ->andReturn([
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'strengths' => ['Strong precedent', 'Good evidence'],
                        'weaknesses' => ['Limited time'],
                        'opportunities' => ['Settlement possible'],
                        'threats' => ['Opponent has resources'],
                    ])],
                ]],
            ]);

        // Act
        $swot = $this->invokePrivateMethod(
            $this->strategyBuilder,
            'performSWOT',
            [$case, $similarCases]
        );

        // Assert
        $this->assertIsArray($swot);
        $this->assertArrayHasKey('strengths', $swot);
        $this->assertArrayHasKey('weaknesses', $swot);
        $this->assertArrayHasKey('opportunities', $swot);
        $this->assertArrayHasKey('threats', $swot);
        $this->assertCount(2, $swot['strengths']);
    }

    /** @test */
    public function it_generates_strategic_recommendations()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $components = [
            'analysis' => [
                'case_strength_score' => 0.7,
                'win_probability' => 0.65,
            ],
            'risks' => [
                'risk_level' => 'MEDIUM',
                'mitigation_strategies' => [
                    ['risk' => 'Test risk', 'strategy' => 'Test strategy'],
                ],
            ],
            'timeline' => [
                'resources_needed' => ['total_estimated_cost' => 150000],
            ],
        ];

        // Act
        $recommendations = $this->invokePrivateMethod(
            $this->strategyBuilder,
            'generateRecommendations',
            [$components, $case]
        );

        // Assert
        $this->assertIsArray($recommendations);
        $this->assertNotEmpty($recommendations);
        $this->assertArrayHasKey('category', $recommendations[0]);
        $this->assertArrayHasKey('recommendation', $recommendations[0]);
        $this->assertArrayHasKey('priority', $recommendations[0]);
    }

    /** @test */
    public function it_handles_high_risk_cases_with_settlement_recommendation()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $components = [
            'analysis' => [
                'case_strength_score' => 0.4,
                'win_probability' => 0.45,
            ],
            'risks' => [
                'risk_level' => 'HIGH', // High risk
                'mitigation_strategies' => [],
            ],
            'timeline' => [
                'resources_needed' => ['total_estimated_cost' => 100000],
            ],
        ];

        // Act
        $recommendations = $this->invokePrivateMethod(
            $this->strategyBuilder,
            'generateRecommendations',
            [$components, $case]
        );

        // Assert
        $settlementRec = collect($recommendations)->firstWhere('category', 'Overall Approach');
        $this->assertNotNull($settlementRec);
        $this->assertStringContainsString('settlement', strtolower($settlementRec['recommendation']));
        $this->assertEquals('high', $settlementRec['priority']);
    }

    /**
     * Helper method to invoke private methods
     */
    protected function invokePrivateMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
