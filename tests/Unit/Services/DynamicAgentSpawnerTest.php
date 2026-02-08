<?php

namespace Tests\Unit\Services;

use App\Services\Agents\DynamicAgentSpawner;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class DynamicAgentSpawnerTest extends TestCase
{
    use UsesTestDatabase;

    private DynamicAgentSpawner $spawner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->spawner = app(DynamicAgentSpawner::class);
    }

    /** @test */
    public function it_can_spawn_agent_dynamically(): void
    {
        // Arrange
        $problemType = 'legal_research';
        $context = ['case_id' => 'case-123', 'query' => 'Find precedents'];
        $budget = ['token_budget' => 5000, 'cost_budget' => 1.0];

        // Act
        $result = $this->spawner->spawnAgent($problemType, $context, $budget);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('agent_type', $result);
        $this->assertArrayHasKey('spawned', $result);
        $this->assertTrue($result['spawned']);
        $this->assertArrayHasKey('estimated_cost', $result);
        $this->assertArrayHasKey('estimated_tokens', $result);
    }

    /** @test */
    public function it_estimates_cost_accurately(): void
    {
        // Arrange
        $problemType = 'legal_research';
        $context = ['complexity' => 'high'];

        // Act
        $estimation = $this->spawner->estimateCost($problemType, $context);

        // Assert
        $this->assertIsArray($estimation);
        $this->assertArrayHasKey('estimated_tokens', $estimation);
        $this->assertArrayHasKey('estimated_cost', $estimation);
        $this->assertArrayHasKey('confidence', $estimation);

        // Verify reasonable ranges
        $this->assertGreaterThan(0, $estimation['estimated_tokens']);
        $this->assertGreaterThan(0, $estimation['estimated_cost']);
        $this->assertGreaterThanOrEqual(0.0, $estimation['confidence']);
        $this->assertLessThanOrEqual(1.0, $estimation['confidence']);
    }

    /** @test */
    public function it_prevents_spawning_when_budget_exceeded(): void
    {
        // Arrange
        $problemType = 'complex_analysis';
        $context = ['complexity' => 'very_high'];
        $lowBudget = ['token_budget' => 10, 'cost_budget' => 0.001]; // Very low budget

        // Act
        $result = $this->spawner->spawnAgent($problemType, $context, $lowBudget);

        // Assert
        $this->assertFalse($result['spawned']);
        $this->assertArrayHasKey('reason', $result);
        $this->assertStringContainsString('budget', strtolower($result['reason']));
    }

    /** @test */
    public function it_enforces_max_depth_limit(): void
    {
        // Arrange
        $problemType = 'legal_research';
        $context = ['case_id' => 'case-123'];
        $budget = ['token_budget' => 10000, 'cost_budget' => 5.0];
        $options = ['current_depth' => 3]; // At max depth

        // Act
        $result = $this->spawner->spawnAgent($problemType, $context, $budget, $options);

        // Assert
        $this->assertFalse($result['spawned']);
        $this->assertArrayHasKey('reason', $result);
        $this->assertStringContainsString('depth', strtolower($result['reason']));
    }

    /** @test */
    public function it_prevents_infinite_spawn_loops(): void
    {
        // Arrange
        $problemType = 'legal_research';
        $context = ['case_id' => 'case-123'];
        $budget = ['token_budget' => 10000, 'cost_budget' => 5.0];

        // Simulate spawn chain that would create a loop
        $options = [
            'spawn_chain' => [
                'ResearchAgent',
                'AnalysisAgent',
                'ResearchAgent', // Attempting to spawn ResearchAgent again
            ],
        ];

        // Act
        $result = $this->spawner->spawnAgent($problemType, $context, $budget, $options);

        // Assert
        $this->assertFalse($result['spawned']);
        $this->assertArrayHasKey('reason', $result);
        $this->assertStringContainsString('loop', strtolower($result['reason']));
    }

    /** @test */
    public function it_has_agent_type_registry(): void
    {
        // Act
        $registry = $this->spawner->getAgentTypeRegistry();

        // Assert
        $this->assertIsArray($registry);
        $this->assertNotEmpty($registry);

        // Check expected mappings
        $this->assertArrayHasKey('legal_research', $registry);
        $this->assertArrayHasKey('precedent_analysis', $registry);
        $this->assertArrayHasKey('case_analysis', $registry);
    }

    /** @test */
    public function it_maps_problem_types_to_agent_types(): void
    {
        // Arrange
        $problemTypes = [
            'legal_research' => 'ResearchAgent',
            'precedent_analysis' => 'PrecedentAgent',
            'case_analysis' => 'CaseAnalysisAgent',
        ];

        // Act & Assert
        foreach ($problemTypes as $problemType => $expectedAgent) {
            $agentType = $this->spawner->getAgentTypeForProblem($problemType);
            $this->assertEquals($expectedAgent, $agentType);
        }
    }

    /** @test */
    public function it_tracks_spawn_history(): void
    {
        // Arrange
        $problemType = 'legal_research';
        $context = ['case_id' => 'case-123'];
        $budget = ['token_budget' => 5000, 'cost_budget' => 1.0];

        // Act
        $result1 = $this->spawner->spawnAgent($problemType, $context, $budget);
        $result2 = $this->spawner->spawnAgent($problemType, $context, $budget);

        $history = $this->spawner->getSpawnHistory();

        // Assert
        $this->assertIsArray($history);
        $this->assertCount(2, $history);

        foreach ($history as $entry) {
            $this->assertArrayHasKey('agent_type', $entry);
            $this->assertArrayHasKey('problem_type', $entry);
            $this->assertArrayHasKey('spawned_at', $entry);
            $this->assertArrayHasKey('estimated_cost', $entry);
        }
    }

    /** @test */
    public function it_calculates_remaining_budget(): void
    {
        // Arrange
        $totalBudget = ['token_budget' => 10000, 'cost_budget' => 5.0];
        $usedBudget = ['tokens_used' => 3000, 'cost_spent' => 1.5];

        // Act
        $remaining = $this->spawner->calculateRemainingBudget($totalBudget, $usedBudget);

        // Assert
        $this->assertEquals(7000, $remaining['token_budget']);
        $this->assertEquals(3.5, $remaining['cost_budget']);
    }

    /** @test */
    public function it_validates_spawn_request(): void
    {
        // Arrange - Valid request
        $validRequest = [
            'problem_type' => 'legal_research',
            'context' => ['case_id' => 'case-123'],
            'budget' => ['token_budget' => 5000, 'cost_budget' => 1.0],
        ];

        // Act
        $isValid = $this->spawner->validateSpawnRequest($validRequest);

        // Assert
        $this->assertTrue($isValid);
    }

    /** @test */
    public function it_rejects_invalid_spawn_request(): void
    {
        // Arrange - Missing required fields
        $invalidRequest = [
            'problem_type' => 'legal_research',
            // Missing context and budget
        ];

        // Act
        $isValid = $this->spawner->validateSpawnRequest($invalidRequest);

        // Assert
        $this->assertFalse($isValid);
    }

    /** @test */
    public function it_allows_spawning_at_depth_below_max(): void
    {
        // Arrange
        $problemType = 'legal_research';
        $context = ['case_id' => 'case-123'];
        $budget = ['token_budget' => 5000, 'cost_budget' => 1.0];
        $options = ['current_depth' => 1]; // Below max depth of 3

        // Act
        $result = $this->spawner->spawnAgent($problemType, $context, $budget, $options);

        // Assert
        $this->assertTrue($result['spawned']);
    }

    /** @test */
    public function it_includes_spawn_metadata_in_result(): void
    {
        // Arrange
        $problemType = 'legal_research';
        $context = ['case_id' => 'case-123'];
        $budget = ['token_budget' => 5000, 'cost_budget' => 1.0];

        // Act
        $result = $this->spawner->spawnAgent($problemType, $context, $budget);

        // Assert
        $this->assertArrayHasKey('spawn_id', $result);
        $this->assertArrayHasKey('spawned_at', $result);
        $this->assertArrayHasKey('depth', $result);
        $this->assertArrayHasKey('parent_chain', $result);
    }

    /** @test */
    public function it_adjusts_estimates_based_on_context_complexity(): void
    {
        // Arrange
        $problemType = 'legal_research';
        $simpleContext = ['complexity' => 'low'];
        $complexContext = ['complexity' => 'high'];

        // Act
        $simpleEstimate = $this->spawner->estimateCost($problemType, $simpleContext);
        $complexEstimate = $this->spawner->estimateCost($problemType, $complexContext);

        // Assert - Complex problems should cost more
        $this->assertGreaterThan(
            $simpleEstimate['estimated_tokens'],
            $complexEstimate['estimated_tokens']
        );
        $this->assertGreaterThan(
            $simpleEstimate['estimated_cost'],
            $complexEstimate['estimated_cost']
        );
    }

    /** @test */
    public function it_provides_spawn_recommendations(): void
    {
        // Arrange
        $problemType = 'legal_research';
        $context = ['case_id' => 'case-123', 'complexity' => 'medium'];
        $budget = ['token_budget' => 5000, 'cost_budget' => 1.0];

        // Act
        $recommendation = $this->spawner->getSpawnRecommendation($problemType, $context, $budget);

        // Assert
        $this->assertIsArray($recommendation);
        $this->assertArrayHasKey('should_spawn', $recommendation);
        $this->assertArrayHasKey('confidence', $recommendation);
        $this->assertArrayHasKey('reasoning', $recommendation);
        $this->assertArrayHasKey('alternative_agents', $recommendation);
    }
}
