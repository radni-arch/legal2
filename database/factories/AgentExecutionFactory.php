<?php

namespace Database\Factories;

use App\Models\AgentCollaboration;
use App\Models\AgentExecution;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AgentExecutionFactory extends Factory
{
    protected $model = AgentExecution::class;

    public function definition(): array
    {
        return [
            'id' => Str::uuid(),
            'collaboration_id' => AgentCollaboration::factory(),
            'agent_name' => fake()->randomElement(['researcher', 'analyst', 'reviewer', 'synthesizer']),
            'agent_role' => fake()->randomElement(['primary', 'supporting', 'quality_assurance']),
            'execution_order' => fake()->numberBetween(1, 10),
            'status' => 'pending',
            'task_description' => fake()->sentence(),
            'input_context' => [
                'query' => fake()->sentence(),
                'parameters' => ['depth' => 'thorough'],
            ],
            'output' => null,
            'messages_to_others' => [],
            'messages_from_others' => [],
            'tokens_used' => 0,
            'cost_spent' => '0.0000',
            'duration_ms' => null,
            'error_message' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'running',
            'started_at' => now()->subMinutes(5),
            'tokens_used' => fake()->numberBetween(100, 1000),
            'cost_spent' => fake()->randomFloat(4, 0.01, 0.1),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'started_at' => now()->subMinutes(10),
            'completed_at' => now()->subMinutes(5),
            'duration_ms' => fake()->numberBetween(30000, 300000),
            'output' => [
                'result' => fake()->paragraph(),
                'confidence' => fake()->randomFloat(2, 0.7, 1.0),
            ],
            'tokens_used' => fake()->numberBetween(1000, 10000),
            'cost_spent' => fake()->randomFloat(4, 0.1, 1.0),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'started_at' => now()->subMinutes(10),
            'completed_at' => now()->subMinutes(8),
            'duration_ms' => fake()->numberBetween(10000, 120000),
            'error_message' => 'Execution failed: '.fake()->sentence(),
        ]);
    }
}
