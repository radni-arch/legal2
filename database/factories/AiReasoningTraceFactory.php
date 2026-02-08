<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AiReasoningTrace>
 */
class AiReasoningTraceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $agentTypes = ['AutonomousResearchAgent', 'DecisionDiscoveryAgent', 'OdlukeAgent', 'AnalysisAgent', 'CoordinatorAgent'];
        $stepTypes = ['research', 'analysis', 'synthesis', 'verification', 'decision'];
        $operations = ['search_laws', 'analyze_evidence', 'find_citations', 'compare_cases', 'generate_argument'];

        return [
            'agent_type' => fake()->randomElement($agentTypes),
            'step_type' => fake()->randomElement($stepTypes),
            'operation' => fake()->randomElement($operations),
            'input_data' => [
                'query' => fake()->sentence(),
                'context' => fake()->text(100),
                'parameters' => [
                    'depth' => fake()->randomElement(['shallow', 'medium', 'deep']),
                    'focus' => fake()->randomElement(['constitutional', 'procedural', 'substantive']),
                ],
            ],
            'output_data' => [
                'results' => fake()->words(5),
                'count' => fake()->numberBetween(1, 20),
                'confidence' => fake()->randomFloat(2, 0.5, 1.0),
            ],
            'reasoning' => fake()->paragraph(),
            'confidence' => fake()->randomFloat(2, 0.7, 0.99),
            'tokens_used' => fake()->numberBetween(50, 2000),
            'duration_ms' => fake()->numberBetween(100, 5000),
        ];
    }

    /**
     * Indicate that the trace is a child of another trace.
     */
    public function withParent(string $parentTraceId): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_trace_id' => $parentTraceId,
        ]);
    }

    /**
     * Indicate that the trace has high confidence.
     */
    public function highConfidence(): static
    {
        return $this->state(fn (array $attributes) => [
            'confidence' => fake()->randomFloat(2, 0.90, 0.99),
        ]);
    }

    /**
     * Indicate that the trace has low confidence.
     */
    public function lowConfidence(): static
    {
        return $this->state(fn (array $attributes) => [
            'confidence' => fake()->randomFloat(2, 0.50, 0.70),
        ]);
    }
}
