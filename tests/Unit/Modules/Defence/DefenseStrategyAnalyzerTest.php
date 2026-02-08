<?php

namespace Tests\Unit\Modules\Defence;

use App\Models\LegalCase;
use App\Modules\Defence\Services\DefenseStrategyAnalyzer;
use App\Services\OpenAIService;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class DefenseStrategyAnalyzerTest extends TestCase
{
    use UsesTestDatabase;

    protected DefenseStrategyAnalyzer $analyzer;

    protected $openAIMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->openAIMock = Mockery::mock(OpenAIService::class);
        $this->analyzer = new DefenseStrategyAnalyzer($this->openAIMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_performs_comprehensive_defense_analysis()
    {
        // Arrange
        $case = LegalCase::factory()->create([
            'title' => 'Theft Case',
            'description' => 'Client accused of theft from retail store',
        ]);

        $this->mockProsecutionWeaknesses();
        $this->mockDefenseStrengths();
        $this->mockMitigatingFactors();

        // Act
        $result = $this->analyzer->analyze($case);

        // Assert
        $this->assertArrayHasKey('defense_strengths', $result);
        $this->assertArrayHasKey('prosecution_weaknesses', $result);
        $this->assertArrayHasKey('mitigating_factors', $result);
        $this->assertArrayHasKey('strategic_options', $result);
        $this->assertArrayHasKey('recommended_approach', $result);
    }

    /** @test */
    public function it_finds_prosecution_weaknesses()
    {
        // Arrange
        $case = LegalCase::factory()->create([
            'title' => 'Assault Case',
            'description' => 'Client accused of assault with limited evidence',
        ]);

        $weaknesses = [
            [
                'description' => 'No physical evidence linking accused to crime',
                'severity' => 85,
                'exploitability' => 'High',
                'impact' => 'Could result in case dismissal',
                'exploitation_strategy' => 'File motion to dismiss for lack of evidence',
            ],
            [
                'description' => 'Witness credibility issues',
                'severity' => 70,
                'exploitability' => 'Medium',
                'impact' => 'Could weaken prosecution case',
                'exploitation_strategy' => 'Cross-examine witness on inconsistencies',
            ],
        ];

        $this->mockOpenAIWeaknessResponse($weaknesses);

        // Act
        $result = $this->analyzer->findProsecutionWeaknesses($case);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals(85, $result[0]['severity']);
        $this->assertArrayHasKey('exploitation_strategy', $result[0]);
    }

    /** @test */
    public function it_categorizes_weaknesses_correctly()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $weaknesses = [
            [
                'category' => 'evidentiary',
                'description' => 'Chain of custody broken',
                'severity' => 90,
                'exploitability' => 'High',
                'impact' => 'Evidence suppression',
                'exploitation_strategy' => 'Motion to suppress evidence',
            ],
            [
                'category' => 'procedural',
                'description' => 'Rights not read during arrest',
                'severity' => 95,
                'exploitability' => 'Very High',
                'impact' => 'Case dismissal',
                'exploitation_strategy' => 'Motion to dismiss based on constitutional violation',
            ],
        ];

        $this->mockOpenAIWeaknessResponse($weaknesses);

        // Act
        $result = $this->analyzer->findProsecutionWeaknesses($case);

        // Assert
        $this->assertEquals('evidentiary', $result[0]['category']);
        $this->assertEquals('procedural', $result[1]['category']);
    }

    /** @test */
    public function it_assesses_defense_strength()
    {
        // Arrange
        $case = LegalCase::factory()->create([
            'description' => 'Client has strong alibi and character witnesses',
        ]);

        $strengthResponse = [
            'overall_strength' => 75,
            'strong_points' => [
                [
                    'description' => 'Credible alibi witness',
                    'strength_score' => 85,
                    'leverage' => 'Can establish defendant was elsewhere',
                    'supporting_evidence' => 'Video footage, witness statements',
                ],
                [
                    'description' => 'Good character evidence',
                    'strength_score' => 70,
                    'leverage' => 'Supports credibility',
                    'supporting_evidence' => 'Letters of recommendation',
                ],
            ],
            'available_defenses' => ['Alibi', 'Mistaken identity'],
            'evidence_support' => ['Video footage', 'Witness testimony'],
        ];

        $this->mockOpenAIStrengthResponse($strengthResponse);

        // Act
        $result = $this->analyzer->assessDefenseStrength($case);

        // Assert
        $this->assertArrayHasKey('overall_strength', $result);
        $this->assertArrayHasKey('strong_points', $result);
        $this->assertArrayHasKey('available_defenses', $result);
        $this->assertEquals(75, $result['overall_strength']);
        $this->assertCount(2, $result['strong_points']);
    }

    /** @test */
    public function it_identifies_mitigating_factors()
    {
        // Arrange
        $case = LegalCase::factory()->create([
            'description' => 'First-time offender with family obligations',
        ]);

        $mitigatingFactors = [
            [
                'description' => 'First-time offender',
                'impact_level' => 'High',
                'evidence_needed' => 'Criminal history check',
                'presentation' => 'Emphasize clean record and rehabilitation potential',
            ],
            [
                'description' => 'Primary caregiver for elderly parent',
                'impact_level' => 'Medium',
                'evidence_needed' => 'Medical records, dependency documentation',
                'presentation' => 'Highlight family obligations and impact of incarceration',
            ],
            [
                'description' => 'Expressed genuine remorse',
                'impact_level' => 'Medium',
                'evidence_needed' => 'Apology letter, restitution efforts',
                'presentation' => 'Show acceptance of responsibility',
            ],
        ];

        $this->mockOpenAIMitigatingResponse($mitigatingFactors);

        // Act
        $result = $this->analyzer->identifyMitigatingFactors($case);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertEquals('First-time offender', $result[0]['description']);
        $this->assertArrayHasKey('impact_level', $result[0]);
        $this->assertArrayHasKey('evidence_needed', $result[0]);
    }

    /** @test */
    public function it_identifies_strategic_options()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        // Mock other required methods
        $this->mockProsecutionWeaknesses();
        $this->mockDefenseStrengths();
        $this->mockMitigatingFactors();

        // Act
        $result = $this->analyzer->analyze($case);

        // Assert
        $this->assertCount(4, $result['strategic_options']);

        $strategies = array_column($result['strategic_options'], 'strategy');
        $this->assertContains('Aggressive Motion Practice', $strategies);
        $this->assertContains('Plea Negotiation', $strategies);
        $this->assertContains('Trial by Jury', $strategies);
        $this->assertContains('Alternative Dispute Resolution', $strategies);
    }

    /** @test */
    public function it_evaluates_aggressive_motion_practice_strategy()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $this->mockProsecutionWeaknesses();
        $this->mockDefenseStrengths();
        $this->mockMitigatingFactors();

        // Act
        $result = $this->analyzer->analyze($case);
        $aggressiveStrategy = collect($result['strategic_options'])
            ->firstWhere('strategy', 'Aggressive Motion Practice');

        // Assert
        $this->assertNotNull($aggressiveStrategy);
        $this->assertArrayHasKey('description', $aggressiveStrategy);
        $this->assertArrayHasKey('suitability', $aggressiveStrategy);
        $this->assertArrayHasKey('risk', $aggressiveStrategy);
        $this->assertEquals('Medium - may antagonize prosecution', $aggressiveStrategy['risk']);
    }

    /** @test */
    public function it_evaluates_plea_negotiation_strategy()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $this->mockProsecutionWeaknesses();
        $this->mockDefenseStrengths();
        $this->mockMitigatingFactors();

        // Act
        $result = $this->analyzer->analyze($case);
        $pleaStrategy = collect($result['strategic_options'])
            ->firstWhere('strategy', 'Plea Negotiation');

        // Assert
        $this->assertNotNull($pleaStrategy);
        $this->assertEquals('Low - preserves certainty, but accepts conviction', $pleaStrategy['risk']);
    }

    /** @test */
    public function it_recommends_overall_approach()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $this->mockProsecutionWeaknesses();
        $this->mockDefenseStrengths();
        $this->mockMitigatingFactors();

        // Act
        $result = $this->analyzer->analyze($case);

        // Assert
        $this->assertArrayHasKey('primary_strategy', $result['recommended_approach']);
        $this->assertArrayHasKey('backup_strategy', $result['recommended_approach']);
        $this->assertArrayHasKey('key_priorities', $result['recommended_approach']);
        $this->assertIsArray($result['recommended_approach']['key_priorities']);
    }

    /** @test */
    public function it_handles_case_with_no_weaknesses()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        // Mock in the order they're called: defenseStrength, weaknesses, mitigatingFactors
        $this->mockDefenseStrengths();
        $this->mockOpenAIWeaknessResponse([]);
        $this->mockMitigatingFactors();

        // Act
        $result = $this->analyzer->analyze($case);

        // Assert
        $this->assertEmpty($result['prosecution_weaknesses']);
        $this->assertNotEmpty($result['strategic_options']);
    }

    /** @test */
    public function it_handles_case_with_weak_defense()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        // Mock in the correct order: defenseStrength, weaknesses, mitigatingFactors
        $this->mockOpenAIStrengthResponse([
            'overall_strength' => 20,
            'strong_points' => [],
            'available_defenses' => [],
            'evidence_support' => [],
        ]);
        $this->mockProsecutionWeaknesses();
        $this->mockMitigatingFactors();

        // Act
        $result = $this->analyzer->analyze($case);

        // Assert
        $this->assertEquals(20, $result['defense_strengths']['overall_strength']);
        $this->assertEmpty($result['defense_strengths']['strong_points']);
    }

    /** @test */
    public function it_uses_gpt_4o_for_prosecution_weaknesses()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $this->openAIMock
            ->shouldReceive('chat')
            ->with(
                Mockery::any(),
                'gpt-4o',
                Mockery::any()
            )
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode(['weaknesses' => []])]],
                ],
            ]);

        // Act
        $this->analyzer->findProsecutionWeaknesses($case);

        // Assert - Mock expectation is verified in tearDown
    }

    /** @test */
    public function it_uses_gpt_4o_mini_for_defense_strengths()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $this->openAIMock
            ->shouldReceive('chat')
            ->with(
                Mockery::any(),
                'gpt-4o-mini',
                Mockery::any()
            )
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => [
                        'content' => json_encode([
                            'overall_strength' => 50,
                            'strong_points' => [],
                            'available_defenses' => [],
                            'evidence_support' => [],
                        ]),
                    ]],
                ],
            ]);

        // Act
        $this->analyzer->assessDefenseStrength($case);

        // Assert - Mock expectation is verified in tearDown
    }

    /** @test */
    public function it_handles_llm_failure_for_weaknesses()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $this->openAIMock
            ->shouldReceive('chat')
            ->andThrow(new \Exception('OpenAI API unavailable'));

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->analyzer->findProsecutionWeaknesses($case);
    }

    /** @test */
    public function it_handles_invalid_json_response()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $this->openAIMock
            ->shouldReceive('chat')
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => 'Invalid JSON']],
                ],
            ]);

        // Act
        $result = $this->analyzer->findProsecutionWeaknesses($case);

        // Assert
        $this->assertEmpty($result);
    }

    /** @test */
    public function it_identifies_all_weakness_categories()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $weaknesses = [
            ['category' => 'evidentiary', 'description' => 'Missing evidence', 'severity' => 80, 'exploitability' => 'High', 'impact' => 'High', 'exploitation_strategy' => 'Challenge'],
            ['category' => 'procedural', 'description' => 'Rights violation', 'severity' => 90, 'exploitability' => 'High', 'impact' => 'Very High', 'exploitation_strategy' => 'Motion to dismiss'],
            ['category' => 'witness_credibility', 'description' => 'Unreliable witness', 'severity' => 70, 'exploitability' => 'Medium', 'impact' => 'Medium', 'exploitation_strategy' => 'Cross-examine'],
            ['category' => 'burden_of_proof', 'description' => 'Weak case', 'severity' => 75, 'exploitability' => 'High', 'impact' => 'High', 'exploitation_strategy' => 'Highlight gaps'],
            ['category' => 'constitutional', 'description' => 'Illegal search', 'severity' => 95, 'exploitability' => 'Very High', 'impact' => 'Evidence suppression', 'exploitation_strategy' => 'Suppression motion'],
            ['category' => 'statute_of_limitations', 'description' => 'Possible expiration', 'severity' => 85, 'exploitability' => 'High', 'impact' => 'Dismissal', 'exploitation_strategy' => 'Motion to dismiss'],
            ['category' => 'factual_inconsistencies', 'description' => 'Conflicting accounts', 'severity' => 65, 'exploitability' => 'Medium', 'impact' => 'Medium', 'exploitation_strategy' => 'Highlight contradictions'],
        ];

        $this->mockOpenAIWeaknessResponse($weaknesses);

        // Act
        $result = $this->analyzer->findProsecutionWeaknesses($case);

        // Assert
        $categories = array_column($result, 'category');
        $this->assertContains('evidentiary', $categories);
        $this->assertContains('procedural', $categories);
        $this->assertContains('witness_credibility', $categories);
        $this->assertContains('burden_of_proof', $categories);
        $this->assertContains('constitutional', $categories);
        $this->assertContains('statute_of_limitations', $categories);
        $this->assertContains('factual_inconsistencies', $categories);
    }

    /** @test */
    public function it_prioritizes_high_severity_weaknesses()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $weaknesses = [
            ['description' => 'Minor issue', 'severity' => 30, 'exploitability' => 'Low', 'impact' => 'Low', 'exploitation_strategy' => 'Note in brief'],
            ['description' => 'Major issue', 'severity' => 95, 'exploitability' => 'Very High', 'impact' => 'Case dismissal', 'exploitation_strategy' => 'File motion immediately'],
            ['description' => 'Medium issue', 'severity' => 60, 'exploitability' => 'Medium', 'impact' => 'Medium', 'exploitation_strategy' => 'Address in trial'],
        ];

        $this->mockOpenAIWeaknessResponse($weaknesses);

        // Act
        $result = $this->analyzer->findProsecutionWeaknesses($case);

        // Assert
        $highSeverity = array_filter($result, fn ($w) => $w['severity'] >= 70);
        $this->assertNotEmpty($highSeverity);
    }

    // Helper Methods

    protected function mockProsecutionWeaknesses(): void
    {
        $this->openAIMock
            ->shouldReceive('chat')
            ->with(
                Mockery::any(),
                'gpt-4o',
                Mockery::any()
            )
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => [
                        'content' => json_encode([
                            'weaknesses' => [
                                [
                                    'description' => 'Weak evidence',
                                    'severity' => 75,
                                    'exploitability' => 'High',
                                    'impact' => 'Significant',
                                    'exploitation_strategy' => 'Challenge evidence',
                                ],
                            ],
                        ]),
                    ]],
                ],
            ]);
    }

    protected function mockDefenseStrengths(): void
    {
        $this->openAIMock
            ->shouldReceive('chat')
            ->with(
                Mockery::any(),
                'gpt-4o-mini',
                Mockery::any()
            )
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => [
                        'content' => json_encode([
                            'overall_strength' => 60,
                            'strong_points' => [['description' => 'Alibi', 'strength_score' => 70]],
                            'available_defenses' => ['Alibi'],
                            'evidence_support' => ['Witness testimony'],
                        ]),
                    ]],
                ],
            ]);
    }

    protected function mockMitigatingFactors(): void
    {
        $this->openAIMock
            ->shouldReceive('chat')
            ->with(
                Mockery::any(),
                'gpt-4o-mini',
                Mockery::any()
            )
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => [
                        'content' => json_encode([
                            'mitigating_factors' => [
                                [
                                    'description' => 'First-time offender',
                                    'impact_level' => 'High',
                                    'evidence_needed' => 'Criminal record',
                                    'presentation' => 'Emphasize clean record',
                                ],
                            ],
                        ]),
                    ]],
                ],
            ]);
    }

    protected function mockOpenAIWeaknessResponse(array $weaknesses): void
    {
        $this->openAIMock
            ->shouldReceive('chat')
            ->with(
                Mockery::any(),
                'gpt-4o',
                Mockery::any()
            )
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode(['weaknesses' => $weaknesses])]],
                ],
            ]);
    }

    protected function mockOpenAIStrengthResponse(array $strength): void
    {
        $this->openAIMock
            ->shouldReceive('chat')
            ->with(
                Mockery::any(),
                'gpt-4o-mini',
                Mockery::any()
            )
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode($strength)]],
                ],
            ]);
    }

    protected function mockOpenAIMitigatingResponse(array $factors): void
    {
        $this->openAIMock
            ->shouldReceive('chat')
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode(['mitigating_factors' => $factors])]],
                ],
            ]);
    }
}
