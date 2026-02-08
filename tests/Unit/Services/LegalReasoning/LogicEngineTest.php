<?php

namespace Tests\Unit\Services\LegalReasoning;

use App\Services\LegalReasoning\LogicEngine;
use App\Services\OpenAIService;
use Mockery;
use Tests\TestCase;

class LogicEngineTest extends TestCase
{
    protected LogicEngine $logicEngine;

    protected $openAIMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->openAIMock = Mockery::mock(OpenAIService::class);
        $this->logicEngine = new LogicEngine($this->openAIMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_parses_logic_structure_successfully()
    {
        // Arrange
        $lawText = 'If the debtor fails to pay within 30 days, then the creditor may sue for damages.';

        $mockLLMResponse = [
            'content' => json_encode([
                'premises' => [
                    ['id' => 'P1', 'statement' => 'debtor fails to pay within 30 days', 'type' => 'condition'],
                ],
                'conclusions' => [
                    ['id' => 'C1', 'statement' => 'creditor may sue for damages', 'type' => 'right'],
                ],
                'conditionals' => [
                    ['if' => 'P1', 'then' => 'C1', 'logical_operator' => 'AND'],
                ],
                'exceptions' => [],
                'definitions' => [],
            ]),
        ];

        $this->openAIMock
            ->shouldReceive('complete')
            ->once()
            ->andReturn($mockLLMResponse);

        // Act
        $result = $this->logicEngine->parseLogicStructure($lawText);

        // Assert
        $this->assertArrayHasKey('premises', $result);
        $this->assertArrayHasKey('conclusions', $result);
        $this->assertArrayHasKey('conditionals', $result);
        $this->assertArrayHasKey('exceptions', $result);
        $this->assertArrayHasKey('definitions', $result);
        $this->assertArrayHasKey('metadata', $result);

        $this->assertCount(1, $result['premises']);
        $this->assertCount(1, $result['conclusions']);
        $this->assertEquals('P1', $result['premises'][0]['id']);
        $this->assertEquals('debtor fails to pay within 30 days', $result['premises'][0]['statement']);
    }

    /** @test */
    public function it_enriches_structure_with_metadata()
    {
        // Arrange
        $lawText = 'Test law';

        $mockLLMResponse = [
            'content' => json_encode([
                'premises' => [['id' => 'P1', 'statement' => 'test', 'type' => 'condition']],
                'conclusions' => [['id' => 'C1', 'statement' => 'result', 'type' => 'obligation']],
                'conditionals' => [['if' => 'P1', 'then' => 'C1', 'logical_operator' => 'AND']],
                'exceptions' => [],
                'definitions' => [],
            ]),
        ];

        $this->openAIMock
            ->shouldReceive('complete')
            ->once()
            ->andReturn($mockLLMResponse);

        // Act
        $result = $this->logicEngine->parseLogicStructure($lawText);

        // Assert
        $this->assertArrayHasKey('metadata', $result);
        $this->assertArrayHasKey('parsed_at', $result['metadata']);
        $this->assertArrayHasKey('complexity_score', $result['metadata']);
        $this->assertArrayHasKey('total_components', $result['metadata']);
        $this->assertEquals(3, $result['metadata']['total_components']); // 1 premise + 1 conclusion + 1 conditional
    }

    /** @test */
    public function it_calculates_complexity_score_correctly()
    {
        // Arrange
        $lawText = 'Complex law with multiple conditions';

        $mockLLMResponse = [
            'content' => json_encode([
                'premises' => [
                    ['id' => 'P1', 'statement' => 'condition 1', 'type' => 'condition'],
                    ['id' => 'P2', 'statement' => 'condition 2', 'type' => 'condition'],
                    ['id' => 'P3', 'statement' => 'condition 3', 'type' => 'condition'],
                ],
                'conclusions' => [
                    ['id' => 'C1', 'statement' => 'result 1', 'type' => 'obligation'],
                    ['id' => 'C2', 'statement' => 'result 2', 'type' => 'obligation'],
                ],
                'conditionals' => [
                    ['if' => 'P1', 'then' => 'C1', 'logical_operator' => 'AND'],
                    ['if' => 'P2', 'then' => 'C2', 'logical_operator' => 'OR'],
                ],
                'exceptions' => [
                    ['exception_to' => 'C1', 'condition' => 'unless X applies'],
                ],
                'definitions' => [],
            ]),
        ];

        $this->openAIMock
            ->shouldReceive('complete')
            ->once()
            ->andReturn($mockLLMResponse);

        // Act
        $result = $this->logicEngine->parseLogicStructure($lawText);

        // Assert
        // Complexity = (3 * 0.1) + (2 * 0.1) + (2 * 0.15) + (1 * 0.2) = 0.3 + 0.2 + 0.3 + 0.2 = 1.0
        $this->assertEquals(1.0, $result['metadata']['complexity_score']);
    }

    /** @test */
    public function it_returns_empty_structure_on_llm_failure()
    {
        // Arrange
        $lawText = 'Test law';

        $this->openAIMock
            ->shouldReceive('complete')
            ->once()
            ->andThrow(new \Exception('OpenAI API unavailable'));

        // Act
        $result = $this->logicEngine->parseLogicStructure($lawText);

        // Assert
        $this->assertArrayHasKey('premises', $result);
        $this->assertArrayHasKey('conclusions', $result);
        $this->assertEmpty($result['premises']);
        $this->assertEmpty($result['conclusions']);
        $this->assertEquals(0.0, $result['metadata']['complexity_score']);
        $this->assertTrue($result['metadata']['parsing_error']);
    }

    /** @test */
    public function it_returns_empty_structure_on_invalid_json()
    {
        // Arrange
        $lawText = 'Test law';

        $this->openAIMock
            ->shouldReceive('complete')
            ->once()
            ->andReturn(['content' => 'Invalid JSON response']);

        // Act
        $result = $this->logicEngine->parseLogicStructure($lawText);

        // Assert
        $this->assertEmpty($result['premises']);
        $this->assertEmpty($result['conclusions']);
        $this->assertTrue($result['metadata']['parsing_error']);
    }

    /** @test */
    public function it_applies_deductive_reasoning_successfully()
    {
        // Arrange
        $facts = [
            ['id' => 'F1', 'statement' => 'The debtor failed to pay within 30 days'],
            ['id' => 'F2', 'statement' => 'The contract is valid'],
        ];

        $rules = [
            [
                'id' => 'R1',
                'premises' => [
                    ['id' => 'P1', 'statement' => 'debtor failed to pay within 30 days', 'type' => 'condition'],
                    ['id' => 'P2', 'statement' => 'contract is valid', 'type' => 'condition'],
                ],
                'conclusion' => 'Creditor may sue for damages',
                'logical_operator' => 'AND',
            ],
        ];

        // Act
        $result = $this->logicEngine->applyDeductiveReasoning($facts, $rules);

        // Assert
        $this->assertNotEmpty($result);
        $this->assertCount(1, $result);
        $this->assertEquals('R1', $result[0]['rule_id']);
        $this->assertEquals('Creditor may sue for damages', $result[0]['conclusion']);
        $this->assertGreaterThan(0, $result[0]['confidence']);
        $this->assertArrayHasKey('supporting_facts', $result[0]);
        $this->assertArrayHasKey('reasoning_chain', $result[0]);
    }

    /** @test */
    public function it_does_not_infer_when_premises_not_satisfied()
    {
        // Arrange
        $facts = [
            ['id' => 'F1', 'statement' => 'The debtor paid on time'],
        ];

        $rules = [
            [
                'id' => 'R1',
                'premises' => [
                    ['id' => 'P1', 'statement' => 'debtor failed to pay within 30 days', 'type' => 'condition'],
                ],
                'conclusion' => 'Creditor may sue for damages',
            ],
        ];

        // Act
        $result = $this->logicEngine->applyDeductiveReasoning($facts, $rules);

        // Assert
        $this->assertEmpty($result); // No inferences should be made
    }

    /** @test */
    public function it_requires_all_premises_to_be_satisfied()
    {
        // Arrange
        $facts = [
            ['id' => 'F1', 'statement' => 'The debtor failed to pay within 30 days'],
            // Missing F2: contract is valid
        ];

        $rules = [
            [
                'id' => 'R1',
                'premises' => [
                    ['id' => 'P1', 'statement' => 'debtor failed to pay within 30 days', 'type' => 'condition'],
                    ['id' => 'P2', 'statement' => 'contract is valid', 'type' => 'condition'],
                ],
                'conclusion' => 'Creditor may sue for damages',
            ],
        ];

        // Act
        $result = $this->logicEngine->applyDeductiveReasoning($facts, $rules);

        // Assert
        $this->assertEmpty($result); // All premises must be satisfied
    }

    /** @test */
    public function it_calculates_confidence_based_on_premise_satisfaction()
    {
        // Arrange
        $facts = [
            ['id' => 'F1', 'statement' => 'The debtor failed to pay within 30 days'],
            ['id' => 'F2', 'statement' => 'The contract is valid'],
        ];

        $rules = [
            [
                'id' => 'R1',
                'premises' => [
                    ['id' => 'P1', 'statement' => 'debtor failed to pay within 30 days', 'type' => 'condition'],
                    ['id' => 'P2', 'statement' => 'contract is valid', 'type' => 'condition'],
                ],
                'conclusion' => 'Creditor may sue for damages',
            ],
        ];

        // Act
        $result = $this->logicEngine->applyDeductiveReasoning($facts, $rules);

        // Assert
        $this->assertGreaterThan(0.5, $result[0]['confidence']); // High confidence when all premises satisfied
    }

    /** @test */
    public function it_includes_specificity_bonus_in_confidence()
    {
        // Arrange
        $facts = [
            ['id' => 'F1', 'statement' => 'The debtor failed to pay within 30 days'],
        ];

        $rules = [
            [
                'id' => 'R1',
                'premises' => [
                    ['id' => 'P1', 'statement' => 'debtor failed to pay within 30 days', 'type' => 'condition'],
                ],
                'conclusion' => 'Creditor may sue for damages',
                'specificity' => 0.9, // Highly specific rule
            ],
        ];

        // Act
        $result = $this->logicEngine->applyDeductiveReasoning($facts, $rules);

        // Assert
        $this->assertGreaterThan(0.8, $result[0]['confidence']); // Base + specificity bonus
    }

    /** @test */
    public function it_builds_reasoning_chain_correctly()
    {
        // Arrange
        $facts = [
            ['id' => 'F1', 'statement' => 'The debtor failed to pay'],
            ['id' => 'F2', 'statement' => 'The contract is valid'],
        ];

        $rules = [
            [
                'id' => 'R1',
                'premises' => [
                    ['id' => 'P1', 'statement' => 'debtor failed to pay', 'type' => 'condition'],
                    ['id' => 'P2', 'statement' => 'contract is valid', 'type' => 'condition'],
                ],
                'conclusion' => 'Creditor may sue for damages',
                'logical_operator' => 'AND',
            ],
        ];

        // Act
        $result = $this->logicEngine->applyDeductiveReasoning($facts, $rules);

        // Assert
        $chain = $result[0]['reasoning_chain'];
        $this->assertGreaterThan(0, count($chain));

        // Should have premises, operation, and conclusion
        $types = array_column($chain, 'type');
        $this->assertContains('premise', $types);
        $this->assertContains('operation', $types);
        $this->assertContains('conclusion', $types);
    }

    /** @test */
    public function it_finds_matching_facts_for_premises()
    {
        // Arrange - Use very similar text to ensure match above 0.7 threshold
        $facts = [
            ['id' => 'F1', 'statement' => 'The debtor failed to pay the amount'],
            ['id' => 'F2', 'statement' => 'The contract is valid'],
        ];

        $rules = [
            [
                'id' => 'R1',
                'premises' => [
                    ['id' => 'P1', 'statement' => 'debtor failed to pay the amount', 'type' => 'condition'],
                ],
                'conclusion' => 'Creditor may sue',
            ],
        ];

        // Act
        $result = $this->logicEngine->applyDeductiveReasoning($facts, $rules);

        // Assert
        $this->assertNotEmpty($result, 'Result should not be empty');
        $this->assertNotEmpty($result[0]['supporting_facts'], 'Should have supporting facts');
        $this->assertEquals('F1', $result[0]['supporting_facts'][0]['fact_id']);
    }

    /** @test */
    public function it_calculates_text_similarity_using_jaccard()
    {
        // Arrange - Use text with >70% overlap to test Jaccard similarity
        $facts = [
            ['id' => 'F1', 'statement' => 'The debtor failed to pay the creditor'],
        ];

        $rules = [
            [
                'id' => 'R1',
                'premises' => [
                    // 5 common words out of 6 total = 83% similarity
                    ['id' => 'P1', 'statement' => 'debtor failed to pay the creditor', 'type' => 'condition'],
                ],
                'conclusion' => 'Sue for damages',
            ],
        ];

        // Act
        $result = $this->logicEngine->applyDeductiveReasoning($facts, $rules);

        // Assert
        // Should match due to high word overlap (>70%)
        $this->assertNotEmpty($result, 'Result should not be empty with high similarity');
    }

    /** @test */
    public function it_handles_empty_facts()
    {
        // Arrange
        $facts = [];

        $rules = [
            [
                'id' => 'R1',
                'premises' => [
                    ['id' => 'P1', 'statement' => 'condition', 'type' => 'condition'],
                ],
                'conclusion' => 'result',
            ],
        ];

        // Act
        $result = $this->logicEngine->applyDeductiveReasoning($facts, $rules);

        // Assert
        $this->assertEmpty($result);
    }

    /** @test */
    public function it_handles_empty_rules()
    {
        // Arrange
        $facts = [
            ['id' => 'F1', 'statement' => 'fact'],
        ];

        $rules = [];

        // Act
        $result = $this->logicEngine->applyDeductiveReasoning($facts, $rules);

        // Assert
        $this->assertEmpty($result);
    }

    /** @test */
    public function it_handles_rules_without_premises()
    {
        // Arrange
        $facts = [
            ['id' => 'F1', 'statement' => 'fact'],
        ];

        $rules = [
            [
                'id' => 'R1',
                'premises' => [], // No premises
                'conclusion' => 'result',
            ],
        ];

        // Act
        $result = $this->logicEngine->applyDeductiveReasoning($facts, $rules);

        // Assert
        $this->assertEmpty($result); // Rules without premises should not apply
    }

    /** @test */
    public function it_handles_multiple_valid_inferences()
    {
        // Arrange
        $facts = [
            ['id' => 'F1', 'statement' => 'The debtor failed to pay'],
            ['id' => 'F2', 'statement' => 'The contract is valid'],
            ['id' => 'F3', 'statement' => 'Notice was given'],
        ];

        $rules = [
            [
                'id' => 'R1',
                'premises' => [
                    ['id' => 'P1', 'statement' => 'debtor failed to pay', 'type' => 'condition'],
                ],
                'conclusion' => 'Creditor may sue',
            ],
            [
                'id' => 'R2',
                'premises' => [
                    ['id' => 'P2', 'statement' => 'contract is valid', 'type' => 'condition'],
                    ['id' => 'P3', 'statement' => 'notice was given', 'type' => 'condition'],
                ],
                'conclusion' => 'Enforcement is valid',
            ],
        ];

        // Act
        $result = $this->logicEngine->applyDeductiveReasoning($facts, $rules);

        // Assert
        $this->assertCount(2, $result); // Both rules should fire
        $this->assertEquals('R1', $result[0]['rule_id']);
        $this->assertEquals('R2', $result[1]['rule_id']);
    }

    /** @test */
    public function it_handles_facts_as_simple_strings()
    {
        // Arrange
        $facts = [
            'The debtor failed to pay',
            'The contract is valid',
        ];

        $rules = [
            [
                'id' => 'R1',
                'premises' => [
                    ['id' => 'P1', 'statement' => 'debtor failed to pay', 'type' => 'condition'],
                ],
                'conclusion' => 'Creditor may sue',
            ],
        ];

        // Act
        $result = $this->logicEngine->applyDeductiveReasoning($facts, $rules);

        // Assert
        $this->assertNotEmpty($result);
        $this->assertEquals('R1', $result[0]['rule_id']);
    }

    /** @test */
    public function it_handles_premises_as_simple_strings()
    {
        // Arrange
        $facts = [
            ['id' => 'F1', 'statement' => 'The debtor failed to pay the amount'],
        ];

        $rules = [
            [
                'id' => 'R1',
                'premises' => [
                    ['id' => 'P1', 'statement' => 'debtor failed to pay the amount', 'type' => 'condition'],
                ],
                'conclusion' => 'Creditor may sue',
            ],
        ];

        // Act
        $result = $this->logicEngine->applyDeductiveReasoning($facts, $rules);

        // Assert
        $this->assertNotEmpty($result, 'Should match with array premise structure');
    }

    /** @test */
    public function it_uses_70_percent_similarity_threshold()
    {
        // Arrange
        $facts = [
            ['id' => 'F1', 'statement' => 'The debtor failed to pay the creditor'],
        ];

        // This premise has high overlap (>70%): 5 of 6 words match
        $similarRule = [
            [
                'id' => 'R1',
                'premises' => [
                    ['id' => 'P1', 'statement' => 'debtor failed to pay the creditor', 'type' => 'condition'],
                ],
                'conclusion' => 'Sue for damages',
            ],
        ];

        // This premise has very little overlap (<30%): only 1 of 8 words match
        $dissimilarRule = [
            [
                'id' => 'R2',
                'premises' => [
                    ['id' => 'P2', 'statement' => 'contract was terminated by mutual agreement', 'type' => 'condition'],
                ],
                'conclusion' => 'No damages',
            ],
        ];

        // Act
        $similarResult = $this->logicEngine->applyDeductiveReasoning($facts, $similarRule);
        $dissimilarResult = $this->logicEngine->applyDeductiveReasoning($facts, $dissimilarRule);

        // Assert
        $this->assertNotEmpty($similarResult); // Should match
        $this->assertEmpty($dissimilarResult); // Should not match
    }
}
