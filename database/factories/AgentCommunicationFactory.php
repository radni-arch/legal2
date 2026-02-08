<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AgentCommunication>
 */
class AgentCommunicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $agentTypes = ['ResearchAgent', 'AnalysisAgent', 'CoordinatorAgent', 'VerificationAgent', 'SynthesisAgent'];
        $messageTypes = ['data_request', 'data_response', 'task_assignment', 'status_update', 'result_notification'];
        $statuses = ['pending', 'in_progress', 'completed', 'failed'];

        return [
            'sender_agent_type' => fake()->randomElement($agentTypes),
            'receiver_agent_type' => fake()->randomElement($agentTypes),
            'message_type' => fake()->randomElement($messageTypes),
            'message_data' => [
                'request' => fake()->sentence(),
                'parameters' => [
                    'priority' => fake()->randomElement(['low', 'medium', 'high']),
                    'deadline' => fake()->dateTimeBetween('now', '+7 days')->format('Y-m-d H:i:s'),
                ],
                'metadata' => [
                    'source' => fake()->word(),
                    'correlation_id' => fake()->uuid(),
                ],
            ],
            'response_data' => [
                'status' => fake()->randomElement(['success', 'error', 'partial']),
                'data' => fake()->words(10),
                'execution_time' => fake()->numberBetween(100, 5000),
            ],
            'status' => fake()->randomElement($statuses),
            'duration_ms' => fake()->numberBetween(50, 3000),
        ];
    }

    /**
     * Indicate that the communication is linked to a trace.
     */
    public function withTrace(string $traceId): static
    {
        return $this->state(fn (array $attributes) => [
            'trace_id' => $traceId,
        ]);
    }

    /**
     * Indicate that the communication is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }

    /**
     * Indicate that the communication is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }
}
