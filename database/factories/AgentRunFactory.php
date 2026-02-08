<?php

namespace Database\Factories;

use App\Models\AgentRun;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class AgentRunFactory extends Factory
{
    protected $model = AgentRun::class;

    public function definition(): array
    {
        return [
            'agent_name' => fake()->randomElement([
                'research',
                'analysis',
                'synthesis',
                'review',
                'validation',
            ]),
            'objective' => fake()->sentence(),
            'status' => 'pending',
            'context' => [
                'jurisdiction' => 'HR',
                'case_type' => fake()->randomElement(['civil', 'commercial', 'labor']),
            ],
            'topics' => fake()->randomElements(
                ['contract', 'dispute', 'evidence', 'precedent', 'compliance'],
                fake()->numberBetween(1, 3)
            ),
            'iterations' => [],
            'checkpoint_state' => null,
            'can_resume' => true,
            'current_iteration' => 0,
            'max_iterations' => fake()->numberBetween(5, 20),
            'score' => 0.0,
            'threshold' => 0.8,
            'token_budget' => 100000.00,
            'tokens_used' => 0.00,
            'cost_budget' => 10.0000,
            'cost_spent' => 0.0000,
            'elapsed_seconds' => null,
            'job_id' => null,
            'queue' => 'agents',
            'error' => null,
            'final_output' => null,
            'started_at' => null,
            'completed_at' => null,
            'last_checkpoint_at' => null,
        ];
    }

    /**
     * Indicate a pending run
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'current_iteration' => 0,
            'started_at' => null,
        ]);
    }

    /**
     * @return $this
     */
    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'running',
            'started_at' => fake()->dateTimeBetween('-2 hours', '-5 minutes'),
            'current_iteration' => fake()->numberBetween(1, $attributes['max_iterations'] - 1),
            'tokens_used' => fake()->randomFloat(2, 1000, 50000),
            'cost_spent' => fake()->randomFloat(4, 0.15, 7.5),
            'iterations' => [
                ['iteration' => 1, 'action' => 'search', 'result' => 'found cases'],
                ['iteration' => 2, 'action' => 'analyze', 'result' => 'analyzed data'],
            ],
        ]);
    }

    /**
     * Indicate a paused run
     */
    public function paused(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paused',
            'started_at' => fake()->dateTimeBetween('-24 hours', '-1 hour'),
            'current_iteration' => fake()->numberBetween(1, $attributes['max_iterations'] - 1),
            'checkpoint_state' => [
                'iteration' => fake()->numberBetween(3, 7),
                'last_action' => 'research',
                'findings' => ['case1', 'case2'],
                'partial_data' => ['findings' => fake()->paragraph()],
            ],
            'can_resume' => true,
            'last_checkpoint_at' => fake()->dateTimeBetween('-2 hours', '-30 minutes'),
        ]);
    }

    /**
     * Indicate a completed run
     */
    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $startTime = fake()->dateTimeBetween('-24 hours', '-1 hour');
            $startTime = Carbon::parse($startTime)->subMinutes(fake()->numberBetween(1, 5));
            $endTime = fake()->dateTimeBetween($startTime, 'now');
            $endTime = Carbon::parse($endTime)->addMinutes(fake()->numberBetween(1, 5));

            return [
                'status' => 'completed',
                'started_at' => $startTime,
                'completed_at' => $endTime,
                'current_iteration' => $attributes['max_iterations'],
                'score' => fake()->randomFloat(2, 0.8, 0.99),
                'tokens_used' => fake()->randomFloat(2, 10000, 100000),
                'cost_spent' => fake()->randomFloat(4, 1.5, 15.0),
                'elapsed_seconds' => (int) $startTime->diffInSeconds($endTime),
                'final_output' => 'Research completed: Found '.fake()->numberBetween(5, 50).' relevant cases',
                'iterations' => [
                    ['iteration' => 1, 'action' => 'search', 'result' => 'found cases'],
                    ['iteration' => 2, 'action' => 'analyze', 'result' => 'analyzed data'],
                    ['iteration' => 3, 'action' => 'summarize', 'result' => 'generated summary'],
                ],
            ];
        });
    }

    /**
     * Indicate a failed run
     */
    public function failed(): static
    {
        return $this->state(function (array $attributes) {
            $startTime = Carbon::parse(fake()->dateTimeBetween('-24 hours', '-1 hour'));
            $endTime = Carbon::parse(fake()->dateTimeBetween($startTime, 'now'));

            return [
                'status' => 'failed',
                'started_at' => $startTime,
                'completed_at' => $endTime,
                'error' => fake()->randomElement([
                    'API timeout after 30 seconds',
                    'Maximum iterations exceeded',
                    'Insufficient token budget',
                    'External service unavailable',
                ]),
                'elapsed_seconds' => (int) $startTime->diffInSeconds($endTime),
            ];
        });
    }

    /**
     * Indicate a research agent run
     */
    public function research(): static
    {
        return $this->state(fn (array $attributes) => [
            'agent_name' => 'research',
            'objective' => 'Research '.fake()->randomElement([
                'contract law precedents',
                'labor law cases',
                'commercial disputes',
            ]),
            'topics' => ['research', 'case-law', 'precedent'],
        ]);
    }

    /**
     * Indicate an analysis agent run
     */
    public function analysis(): static
    {
        return $this->state(fn (array $attributes) => [
            'agent_name' => 'analysis',
            'objective' => 'Analyze '.fake()->randomElement([
                'contract compliance',
                'legal risks',
                'case strategy',
            ]),
            'topics' => ['analysis', 'compliance', 'risk'],
        ]);
    }

    /**
     * Indicate a run with checkpoint state
     */
    public function withCheckpoint(?array $state = null): static
    {
        return $this->state(fn (array $attributes) => [
            'checkpoint_state' => $state ?? [
                'iteration' => fake()->numberBetween(1, 10),
                'last_query' => 'contract disputes',
                'findings' => ['case1', 'case2', 'case3'],
                'next_action' => 'analyze_findings',
            ],
            'last_checkpoint_at' => now()->subMinutes(fake()->numberBetween(5, 60)),
        ]);
    }

    /**
     * Indicate a run that can be resumed
     */
    public function resumable(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paused',
            'can_resume' => true,
            'checkpoint_state' => [
                'iteration' => fake()->numberBetween(1, 5),
                'state' => 'saved',
            ],
        ]);
    }

    /**
     * Indicate a run that cannot be resumed
     */
    public function notResumable(): static
    {
        return $this->state(fn (array $attributes) => [
            'can_resume' => false,
        ]);
    }

    /**
     * Indicate a run with high token usage
     */
    public function highTokenUsage(): static
    {
        return $this->state(fn (array $attributes) => [
            'token_budget' => 500000.00,
            'tokens_used' => fake()->randomFloat(2, 200000, 450000),
            'cost_budget' => 75.0000,
            'cost_spent' => fake()->randomFloat(4, 30.0, 67.5),
        ]);
    }

    /**
     * Indicate a run with specific progress
     */
    public function withProgress(int $currentIteration, ?int $maxIterations = null): static
    {
        return $this->state(fn (array $attributes) => [
            'current_iteration' => $currentIteration,
            'max_iterations' => $maxIterations ?? $attributes['max_iterations'] ?? 10,
        ]);
    }

    /**
     * Indicate a run with high score
     */
    public function highScore(): static
    {
        return $this->state(fn (array $attributes) => [
            'score' => fake()->randomFloat(2, 0.9, 0.99),
            'threshold' => 0.8,
        ]);
    }

    /**
     * Indicate a run with low score
     */
    public function lowScore(): static
    {
        return $this->state(fn (array $attributes) => [
            'score' => fake()->randomFloat(2, 0.5, 0.75),
            'threshold' => 0.8,
        ]);
    }

    /**
     * Indicate a run in a specific queue
     */
    public function inQueue(string $queue): static
    {
        return $this->state(fn (array $attributes) => [
            'queue' => $queue,
        ]);
    }

    /**
     * Indicate a run with job ID
     */
    public function withJobId(?string $jobId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'job_id' => $jobId ?? 'job-'.fake()->uuid(),
        ]);
    }

    /**
     * Indicate a long-running agent run
     */
    public function longRunning(): static
    {
        return $this->state(function (array $attributes) {
            $startTime = fake()->dateTimeBetween('-12 hours', '-6 hours');

            return [
                'status' => 'running',
                'started_at' => $startTime,
                'max_iterations' => fake()->numberBetween(50, 100),
                'current_iteration' => fake()->numberBetween(25, 75),
                'tokens_used' => fake()->randomFloat(2, 200000, 800000),
                'cost_spent' => fake()->randomFloat(4, 30.0, 120.0),
            ];
        });
    }

    /**
     * Indicate a run with iterations history
     */
    public function withIterations(): static
    {
        return $this->state(fn (array $attributes) => [
            'iterations' => [
                ['iteration' => 1, 'action' => 'search', 'result' => 'found 10 cases', 'score' => 0.85],
                ['iteration' => 2, 'action' => 'filter', 'result' => 'filtered to 5 relevant', 'score' => 0.88],
                ['iteration' => 3, 'action' => 'analyze', 'result' => 'analyzed findings', 'score' => 0.92],
                ['iteration' => 4, 'action' => 'summarize', 'result' => 'generated summary', 'score' => 0.95],
            ],
            'started_at' => now()->subMinutes(5),
            'current_iteration' => fake()->numberBetween(1, 3),
            'tokens_used' => fake()->randomFloat(2, 1000, 50000),
            'cost_spent' => fake()->randomFloat(4, 0.5, 5),
        ]);
    }
}
