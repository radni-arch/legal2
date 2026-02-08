<?php

namespace Database\Factories;

use App\Models\CaseStrategy;
use App\Models\LegalCase;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CaseStrategyFactory extends Factory
{
    protected $model = CaseStrategy::class;

    public function definition(): array
    {
        return [
            'id' => Str::ulid(),
            'case_id' => LegalCase::factory(),
            'version' => '1.0',
            'status' => 'draft',
            'objectives' => [
                ['goal' => fake()->sentence(), 'priority' => 'HIGH'],
                ['goal' => fake()->sentence(), 'priority' => 'MEDIUM'],
            ],
            'analysis' => [
                'strength' => fake()->randomFloat(2, 0, 1),
                'win_probability' => fake()->randomFloat(2, 0, 1),
                'swot' => [
                    'strengths' => [fake()->sentence()],
                    'weaknesses' => [fake()->sentence()],
                    'opportunities' => [fake()->sentence()],
                    'threats' => [fake()->sentence()],
                ],
            ],
            'arguments' => [
                [
                    'issue' => fake()->sentence(),
                    'strength' => fake()->randomFloat(2, 0, 1),
                    'precedents' => [fake()->word()],
                ],
            ],
            'risks' => [
                'overall_risk' => fake()->randomElement(['LOW', 'MEDIUM', 'HIGH']),
                'risk_score' => fake()->randomFloat(2, 0, 1),
                'identified_risks' => [
                    ['description' => fake()->sentence(), 'impact' => 'MEDIUM'],
                ],
            ],
            'precedents' => [
                [
                    'case_name' => fake()->words(3, true),
                    'relevance' => fake()->randomFloat(2, 0, 1),
                    'outcome' => fake()->word(),
                ],
            ],
            'action_plan' => [
                'phases' => [
                    ['phase' => 'Discovery', 'duration_days' => 30],
                    ['phase' => 'Motion Filing', 'duration_days' => 15],
                ],
                'milestones' => [
                    ['milestone' => fake()->sentence(), 'deadline' => now()->addDays(30)->toDateString()],
                ],
            ],
            'timeline' => [
                'estimated_duration_days' => fake()->numberBetween(60, 365),
                'key_dates' => [
                    ['event' => 'Trial Date', 'date' => now()->addDays(90)->toDateString()],
                ],
            ],
            'recommendations' => [
                ['recommendation' => fake()->sentence(), 'priority' => 'HIGH'],
                ['recommendation' => fake()->sentence(), 'priority' => 'MEDIUM'],
            ],
            'summary' => fake()->paragraph(),
            'confidence_score' => fake()->randomFloat(2, 0.5, 0.95),
            'metrics' => [
                'estimated_cost' => fake()->numberBetween(5000, 50000),
                'resource_hours' => fake()->numberBetween(100, 500),
            ],
            'created_by' => null,
            'approved_by' => null,
            'approved_at' => null,
        ];
    }

    /**
     * Indicate an active strategy
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Indicate an archived strategy
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'archived',
        ]);
    }

    /**
     * Indicate a high confidence strategy
     */
    public function highConfidence(): static
    {
        return $this->state(fn (array $attributes) => [
            'confidence_score' => fake()->randomFloat(2, 0.8, 0.95),
        ]);
    }

    /**
     * Indicate a low confidence strategy
     */
    public function lowConfidence(): static
    {
        return $this->state(fn (array $attributes) => [
            'confidence_score' => fake()->randomFloat(2, 0.3, 0.5),
        ]);
    }
}
