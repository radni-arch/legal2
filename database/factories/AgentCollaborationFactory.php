<?php

namespace Database\Factories;

use App\Models\AgentCollaboration;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AgentCollaborationFactory extends Factory
{
    protected $model = AgentCollaboration::class;

    public function definition(): array
    {
        return [
            'id' => Str::uuid(),
            'session_id' => 'collab_'.Str::random(16),
            'orchestrator' => fake()->randomElement(['main-orchestrator', 'legal-orchestrator', 'research-orchestrator']),
            'problem_type' => fake()->randomElement([
                'legal-research',
                'contract-analysis',
                'case-strategy',
                'document-review',
                'precedent-search',
            ]),
            'problem_statement' => fake()->sentence(),
            'context' => [
                'jurisdiction' => 'HR',
                'case_type' => fake()->randomElement(['civil', 'commercial', 'labor']),
                'priority' => fake()->randomElement(['low', 'medium', 'high']),
            ],
            'status' => 'pending',
            'agents_involved' => fake()->randomElements(
                ['research-agent', 'analysis-agent', 'synthesis-agent', 'review-agent'],
                fake()->numberBetween(2, 4)
            ),
            'execution_plan' => [
                ['step' => 1, 'agent' => 'research-agent', 'task' => 'Find relevant cases'],
                ['step' => 2, 'agent' => 'analysis-agent', 'task' => 'Analyze findings'],
            ],
            'shared_memory' => null,
            'agent_outputs' => null,
            'final_result' => null,
            'synthesis' => null,
            'total_steps' => fake()->numberBetween(3, 10),
            'completed_steps' => 0,
            'tokens_used' => 0,
            'cost_spent' => 0,

            'duration_seconds' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    /**
     * Indicate a pending collaboration
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'started_at' => null,
            'completed_at' => null,
            'completed_steps' => 0,
        ]);
    }

    /**
     * Indicate an in-progress collaboration
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in_progress',
            'started_at' => fake()->dateTimeBetween('-2 hours', '-5 minutes'),
            'completed_steps' => fake()->numberBetween(1, $attributes['total_steps'] - 1),
            'tokens_used' => fake()->numberBetween(1000, 50000),
            'cost_spent' => fake()->randomFloat(4, 0.1, 5.0),
        ]);
    }

    /**
     * Indicate a completed collaboration
     */
    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $startTime = fake()->dateTimeBetween('-24 hours', '-1 hour');
            $endTime = fake()->dateTimeBetween($startTime, 'now');

            return [
                'status' => 'completed',
                'started_at' => $startTime,
                'completed_at' => $endTime,
                'completed_steps' => $attributes['total_steps'],
                'tokens_used' => fake()->numberBetween(10000, 100000),
                'cost_spent' => fake()->randomFloat(4, 1.0, 15.0),
                'duration_seconds' => $endTime->getTimestamp() - $startTime->getTimestamp(),
                'final_result' => [
                    'conclusion' => fake()->sentence(),
                    'confidence' => fake()->randomFloat(2, 0.7, 0.99),
                    'findings' => fake()->paragraphs(2, true),
                ],
                'synthesis' => fake()->paragraph(),
                'agent_outputs' => [
                    'research-agent' => ['cases_found' => fake()->numberBetween(5, 20)],
                    'analysis-agent' => ['analysis_complete' => true],
                ],
            ];
        });
    }

    /**
     * Indicate a failed collaboration
     */
    public function failed(): static
    {
        return $this->state(function (array $attributes) {
            $startTime = fake()->dateTimeBetween('-24 hours', '-1 hour');
            $endTime = fake()->dateTimeBetween($startTime, 'now');

            return [
                'status' => 'failed',
                'started_at' => $startTime,
                'completed_at' => $endTime,
                'duration_seconds' => $endTime->getTimestamp() - $startTime->getTimestamp(),
                'synthesis' => 'Collaboration failed: '.fake()->randomElement([
                    'API timeout exceeded',
                    'Agent execution error',
                    'Insufficient resources',
                ]),
            ];
        });
    }

    /**
     * Indicate a legal research collaboration
     */
    public function legalResearch(): static
    {
        return $this->state(fn (array $attributes) => [
            'problem_type' => 'legal-research',
            'problem_statement' => 'Research '.fake()->randomElement([
                'contract law precedents',
                'labor law cases',
                'commercial dispute rulings',
            ]),
            'agents_involved' => ['research-agent', 'analysis-agent', 'synthesis-agent'],
        ]);
    }

    /**
     * Indicate a contract analysis collaboration
     */
    public function contractAnalysis(): static
    {
        return $this->state(fn (array $attributes) => [
            'problem_type' => 'contract-analysis',
            'problem_statement' => 'Analyze employment contract for compliance',
            'agents_involved' => ['analysis-agent', 'review-agent'],
        ]);
    }

    /**
     * Indicate a collaboration with shared memory
     */
    public function withSharedMemory(?array $memory = null): static
    {
        return $this->state(fn (array $attributes) => [
            'shared_memory' => $memory ?? [
                'findings' => ['case1', 'case2', 'case3'],
                'context' => 'contract dispute',
                'current_step' => 2,
            ],
        ]);
    }

    /**
     * Indicate a collaboration with high token usage
     */
    public function highTokenUsage(): static
    {
        return $this->state(fn (array $attributes) => [
            'tokens_used' => fake()->numberBetween(100000, 500000),
            'cost_spent' => fake()->randomFloat(4, 15.0, 75.0),
        ]);
    }

    /**
     * Indicate a recent collaboration
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'started_at' => fake()->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    /**
     * Indicate a long-running collaboration
     */
    public function longRunning(): static
    {
        return $this->state(function (array $attributes) {
            $startTime = fake()->dateTimeBetween('-12 hours', '-6 hours');

            return [
                'status' => 'in_progress',
                'started_at' => $startTime,
                'total_steps' => fake()->numberBetween(20, 50),
                'completed_steps' => fake()->numberBetween(10, 25),
                'tokens_used' => fake()->numberBetween(200000, 1000000),
                'cost_spent' => fake()->randomFloat(4, 30.0, 150.0),
            ];
        });
    }

    /**
     * Indicate a collaboration with specific progress
     */
    public function withProgress(int $completedSteps, ?int $totalSteps = null): static
    {
        return $this->state(fn (array $attributes) => [
            'total_steps' => $totalSteps ?? $attributes['total_steps'] ?? 10,
            'completed_steps' => $completedSteps,
        ]);
    }

    /**
     * Indicate a multi-agent collaboration
     */
    public function multiAgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'agents_involved' => [
                'research-agent',
                'analysis-agent',
                'synthesis-agent',
                'review-agent',
                'validation-agent',
            ],
            'total_steps' => fake()->numberBetween(10, 20),
            'execution_plan' => [
                ['step' => 1, 'agent' => 'research-agent', 'task' => 'Gather information'],
                ['step' => 2, 'agent' => 'analysis-agent', 'task' => 'Analyze data'],
                ['step' => 3, 'agent' => 'synthesis-agent', 'task' => 'Generate summary'],
                ['step' => 4, 'agent' => 'review-agent', 'task' => 'Review findings'],
                ['step' => 5, 'agent' => 'validation-agent', 'task' => 'Validate results'],
            ],
            'started_at' => now()->subMinutes(10),
            'completed_steps' => fake()->numberBetween(1, 2),
            'tokens_used' => fake()->numberBetween(1000, 10000),
            'cost_spent' => fake()->randomFloat(4, 0.1, 1.0),
        ]);
    }
}
