<?php

namespace Tests\Unit\Services\LegalReasoning;

use App\Models\CourtDecision;
use App\Models\Law;
use App\Models\LegalCase;
use App\Services\LegalReasoning\ArgumentGenerator;
use App\Services\LegalReasoning\CitationAnalyzer;
use App\Services\OpenAIService;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class ArgumentGeneratorTest extends TestCase
{
    use UsesTestDatabase;

    protected ArgumentGenerator $argumentGenerator;

    protected $citationAnalyzerMock;

    protected $openAIMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->openAIMock = Mockery::mock(OpenAIService::class);
        $this->citationAnalyzerMock = Mockery::mock(CitationAnalyzer::class);

        $this->argumentGenerator = new ArgumentGenerator(
            $this->openAIMock,
            $this->citationAnalyzerMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_generates_arguments_successfully()
    {
        // Arrange
        $case = LegalCase::factory()->create([
            'description' => 'Contract breach case with clear evidence of non-performance.',
        ]);

        // Create precedents in database for keyword search
        $precedent = CourtDecision::factory()->create([
            'title' => 'Breach of Contract Precedent Case',
            'description' => 'Contract law requires performance and breach is established when defendant fails to perform',
            'court' => 'Supreme Court',
            'decision_date' => '2023-01-15',
        ]);

        $objectives = ['Prove breach of contract', 'Establish damages'];

        // Mock CitationAnalyzer
        $this->citationAnalyzerMock
            ->shouldReceive('analyzeAuthority')
            ->andReturn(['authority_score' => 0.9]);

        // Mock OpenAI chat calls in sequence: issue extraction, then for each of 2 issues: apply law, enhance, counter, rebuttal
        $this->openAIMock
            ->shouldReceive('chat')
            ->andReturn(
                // 1. Extract issues
                [
                    'choices' => [[
                        'message' => [
                            'content' => json_encode([
                                ['issue' => 'Breach of contract', 'claim' => 'Defendant breached contract', 'desired_outcome' => 'Find breach', 'priority' => 'high'],
                                ['issue' => 'Damages calculation', 'claim' => 'Client entitled to damages', 'desired_outcome' => 'Award damages', 'priority' => 'high'],
                            ]),
                        ],
                    ]],
                ],
                // 2. Apply law to facts (issue 1)
                ['choices' => [['message' => ['content' => 'The law applies to these facts because the defendant failed to perform their contractual obligations.']]]],
                // 3. Enhance argument (issue 1)
                ['choices' => [['message' => ['content' => 'Enhanced legal argument demonstrating breach of contract with supporting precedents and applicable law.']]]],
                // 4. Counter-arguments (issue 1)
                ['choices' => [['message' => ['content' => json_encode([['argument' => 'Statute of limitations', 'legal_basis' => 'Time barred', 'strength' => 'medium']])]]]],
                // 5. Rebuttal (issue 1, counter 1)
                ['choices' => [['message' => ['content' => 'The statute of limitations does not apply because the breach occurred within the statutory period.']]]],
                // 6. Apply law to facts (issue 2)
                ['choices' => [['message' => ['content' => 'The law applies to these facts regarding damages calculation.']]]],
                // 7. Enhance argument (issue 2)
                ['choices' => [['message' => ['content' => 'Enhanced legal argument for damages calculation.']]]],
                // 8. Counter-arguments (issue 2)
                ['choices' => [['message' => ['content' => json_encode([['argument' => 'Mitigation required', 'legal_basis' => 'Duty to mitigate', 'strength' => 'medium']])]]]],
                // 9. Rebuttal (issue 2, counter 1)
                ['choices' => [['message' => ['content' => 'Mitigation efforts were properly undertaken.']]]],
            );

        // Act
        $result = $this->argumentGenerator->generateArguments($case->id, $objectives);

        // Assert
        $this->assertArrayHasKey('arguments', $result);
        $this->assertArrayHasKey('total_arguments', $result);
        $this->assertArrayHasKey('strongest_argument', $result);

        $this->assertNotEmpty($result['arguments']);
        $this->assertEquals(2, $result['total_arguments']);
    }

    /** @test */
    public function it_structures_arguments_using_irac_method()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $objectives = ['Test issue'];

        // Mock OpenAI for issue extraction
        $this->openAIMock
            ->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            ['issue' => 'Test issue', 'claim' => 'Test claim', 'desired_outcome' => 'Test outcome', 'priority' => 'high'],
                        ]),
                    ],
                ]],
            ]);

        // Mock OpenAI for apply law to facts
        $this->openAIMock
            ->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [[
                    'message' => ['content' => 'Test application'],
                ]],
            ]);

        // Mock OpenAI for enhance argument
        $this->openAIMock
            ->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [[
                    'message' => ['content' => 'Enhanced argument'],
                ]],
            ]);

        // Mock OpenAI for counter-arguments
        $this->openAIMock
            ->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [[
                    'message' => ['content' => '[]'],
                ]],
            ]);

        // Act
        $result = $this->argumentGenerator->generateArguments($case->id, $objectives);

        // Assert
        $argument = $result['arguments'][0];
        $this->assertArrayHasKey('irac_structure', $argument);
        $irac = $argument['irac_structure'];

        $this->assertArrayHasKey('issue', $irac);
        $this->assertArrayHasKey('rule', $irac);
        $this->assertArrayHasKey('application', $irac);
        $this->assertArrayHasKey('conclusion', $irac);
    }

    /** @test */
    public function it_includes_supporting_precedents()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        // Create actual precedent decisions
        $precedent = CourtDecision::factory()->create([
            'case_number' => 'Prev-123/2020',
            'title' => 'Similar Case About Test Objective',
            'description' => 'Test objective was successfully argued',
            'court' => 'High Court',
            'decision_date' => '2023-06-15',
        ]);

        // Mock CitationAnalyzer
        $this->citationAnalyzerMock
            ->shouldReceive('analyzeAuthority')
            ->andReturn(['authority_score' => 0.9]);

        // Mock OpenAI for issue extraction
        $this->openAIMock
            ->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            ['issue' => 'Test objective', 'claim' => 'Test claim', 'desired_outcome' => 'Test outcome', 'priority' => 'high'],
                        ]),
                    ],
                ]],
            ]);

        // Mock OpenAI for other calls
        $this->openAIMock
            ->shouldReceive('chat')
            ->andReturn([
                'choices' => [[
                    'message' => ['content' => 'Test response'],
                ]],
            ]);

        // Act
        $result = $this->argumentGenerator->generateArguments($case->id, ['Test objective']);

        // Assert
        $argument = $result['arguments'][0];
        $this->assertArrayHasKey('supporting_precedents', $argument);
        $this->assertNotEmpty($argument['supporting_precedents']);
    }

    /** @test */
    public function it_calculates_argument_strength_score()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        // Create strong precedent
        CourtDecision::factory()->create([
            'title' => 'Strong Test Precedent',
            'description' => 'Test objective analysis',
            'decision_date' => now()->subYear(),
        ]);

        // Mock CitationAnalyzer with high authority score
        $this->citationAnalyzerMock
            ->shouldReceive('analyzeAuthority')
            ->andReturn(['authority_score' => 0.95]);

        // Mock OpenAI for issue extraction
        $this->openAIMock
            ->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            ['issue' => 'Test', 'claim' => 'Test', 'desired_outcome' => 'Test', 'priority' => 'high'],
                        ]),
                    ],
                ]],
            ]);

        // Mock other OpenAI calls
        $this->openAIMock
            ->shouldReceive('chat')
            ->andReturn([
                'choices' => [[
                    'message' => ['content' => 'Test response'],
                ]],
            ]);

        // Act
        $result = $this->argumentGenerator->generateArguments($case->id, ['Test objective']);

        // Assert
        $argument = $result['arguments'][0];
        $this->assertArrayHasKey('strength_score', $argument);
        $this->assertGreaterThan(0, $argument['strength_score']);
        $this->assertLessThanOrEqual(1.0, $argument['strength_score']);
    }

    /** @test */
    public function it_identifies_strongest_argument()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        // Create precedents for keyword matching
        CourtDecision::factory()->create([
            'title' => 'Strong Issue Precedent',
            'description' => 'Analysis of strong issue',
            'decision_date' => now()->subYear(),
        ]);

        CourtDecision::factory()->create([
            'title' => 'Weak Issue Precedent',
            'description' => 'Analysis of weak issue',
            'decision_date' => now()->subYears(10),
        ]);

        // Mock CitationAnalyzer
        $this->citationAnalyzerMock
            ->shouldReceive('analyzeAuthority')
            ->andReturn(
                ['authority_score' => 0.9],
                ['authority_score' => 0.5]
            );

        // Mock OpenAI for issue extraction
        $this->openAIMock
            ->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            ['issue' => 'Strong issue', 'claim' => 'Strong claim', 'desired_outcome' => 'Strong outcome', 'priority' => 'high'],
                            ['issue' => 'Weak issue', 'claim' => 'Weak claim', 'desired_outcome' => 'Weak outcome', 'priority' => 'low'],
                        ]),
                    ],
                ]],
            ]);

        // Mock other OpenAI calls
        $this->openAIMock
            ->shouldReceive('chat')
            ->andReturn([
                'choices' => [[
                    'message' => ['content' => 'Test response'],
                ]],
            ]);

        // Act
        $result = $this->argumentGenerator->generateArguments($case->id, ['Strong issue', 'Weak issue']);

        // Assert
        $this->assertArrayHasKey('strongest_argument', $result);
        $this->assertArrayHasKey('issue', $result['strongest_argument']);
        $this->assertArrayHasKey('strength_score', $result['strongest_argument']);
    }

    /** @test */
    public function it_generates_counter_arguments()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        // Mock OpenAI for issue extraction
        $this->openAIMock
            ->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            ['issue' => 'Test', 'claim' => 'Test', 'desired_outcome' => 'Test', 'priority' => 'high'],
                        ]),
                    ],
                ]],
            ]);

        // Mock OpenAI for apply law
        $this->openAIMock
            ->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [[
                    'message' => ['content' => 'Application'],
                ]],
            ]);

        // Mock OpenAI for enhance
        $this->openAIMock
            ->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [[
                    'message' => ['content' => 'Enhanced'],
                ]],
            ]);

        // Mock OpenAI for counter-arguments
        $this->openAIMock
            ->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            ['argument' => 'Statute of limitations', 'legal_basis' => 'Time barred', 'strength' => 'medium'],
                        ]),
                    ],
                ]],
            ]);

        // Mock OpenAI for rebuttal
        $this->openAIMock
            ->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [[
                    'message' => ['content' => 'Rebuttal text'],
                ]],
            ]);

        // Act
        $result = $this->argumentGenerator->generateArguments($case->id, ['Test issue']);

        // Assert
        $argument = $result['arguments'][0];
        $this->assertArrayHasKey('potential_counterarguments', $argument);
    }

    /** @test */
    public function it_generates_rebuttals_to_counter_arguments()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        // Mock OpenAI for issue extraction
        $this->openAIMock
            ->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            ['issue' => 'Test', 'claim' => 'Test', 'desired_outcome' => 'Test', 'priority' => 'high'],
                        ]),
                    ],
                ]],
            ]);

        // Mock OpenAI for apply law
        $this->openAIMock
            ->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [[
                    'message' => ['content' => 'Application'],
                ]],
            ]);

        // Mock OpenAI for enhance
        $this->openAIMock
            ->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [[
                    'message' => ['content' => 'Enhanced'],
                ]],
            ]);

        // Mock OpenAI for counter-arguments
        $this->openAIMock
            ->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            ['argument' => 'Counter', 'legal_basis' => 'Basis', 'strength' => 'medium'],
                        ]),
                    ],
                ]],
            ]);

        // Mock OpenAI for rebuttal
        $this->openAIMock
            ->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [[
                    'message' => ['content' => 'Rebuttal text'],
                ]],
            ]);

        // Act
        $result = $this->argumentGenerator->generateArguments($case->id, ['Test issue']);

        // Assert
        $argument = $result['arguments'][0];
        $this->assertArrayHasKey('rebuttal_strategy', $argument);
    }

    /** @test */
    public function it_handles_llm_failure_gracefully()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $this->openAIMock
            ->shouldReceive('chat')
            ->once()
            ->andThrow(new \Exception('OpenAI API unavailable'));

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->argumentGenerator->generateArguments($case->id, ['Test issue']);
    }

    /** @test */
    public function it_handles_empty_objectives()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        // Mock OpenAI to return empty issues array
        $this->openAIMock
            ->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [[
                    'message' => ['content' => '[]'],
                ]],
            ]);

        // Act
        $result = $this->argumentGenerator->generateArguments($case->id, []);

        // Assert
        $this->assertEmpty($result['arguments']);
        $this->assertEquals(0, $result['total_arguments']);
    }

    /** @test */
    public function it_includes_applicable_laws_in_arguments()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        // Create relevant laws
        $law = Law::factory()->create([
            'law_number' => 'NN 100/2020',
            'title' => 'Contract Law Test',
            'content' => 'Contract law requires performance and adherence to terms',
        ]);

        // Mock OpenAI for issue extraction
        $this->openAIMock
            ->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            ['issue' => 'Contract test', 'claim' => 'Test', 'desired_outcome' => 'Test', 'priority' => 'high'],
                        ]),
                    ],
                ]],
            ]);

        // Mock other OpenAI calls
        $this->openAIMock
            ->shouldReceive('chat')
            ->andReturn([
                'choices' => [[
                    'message' => ['content' => 'Test response'],
                ]],
            ]);

        // Act
        $result = $this->argumentGenerator->generateArguments($case->id, ['Contract test issue']);

        // Assert
        $argument = $result['arguments'][0];
        $this->assertArrayHasKey('applicable_law', $argument);
    }
}
