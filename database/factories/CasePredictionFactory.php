<?php

namespace Database\Factories;

use App\Models\CasePrediction;
use App\Models\LegalCase;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CasePredictionFactory extends Factory
{
    protected $model = CasePrediction::class;

    public function definition(): array
    {
        $outcome = fake()->randomElement(['win', 'loss', 'settlement', 'dismissed']);

        return [
            'id' => Str::ulid(),
            'case_id' => LegalCase::factory(),
            'prediction_type' => 'outcome',
            'features' => [
                'case_type' => 'civil',
                'complexity_score' => fake()->randomFloat(2, 0, 1),
                'document_count' => fake()->numberBetween(1, 50),
            ],
            'prediction' => [
                'predicted_outcome' => $outcome,
                'probability_distribution' => [
                    'win' => fake()->randomFloat(2, 0, 1),
                    'loss' => fake()->randomFloat(2, 0, 1),
                    'settlement' => fake()->randomFloat(2, 0, 1),
                ],
            ],
            'confidence' => fake()->randomFloat(2, 0.5, 0.95),
            'model_version' => '1.0.0',
            'similar_cases' => [
                [
                    'case_id' => 'test-case-1',
                    'similarity' => 0.85,
                    'outcome' => $outcome,
                ],
            ],
            'reasoning' => fake()->paragraph(),
            'predicted_at' => now(),
            'actual_outcome' => null,
            'actual_outcome_at' => null,
            'accuracy_score' => null,
        ];
    }

    /**
     * Indicate a high confidence prediction
     */
    public function highConfidence(): static
    {
        return $this->state(fn (array $attributes) => [
            'confidence' => fake()->randomFloat(2, 0.8, 0.95),
        ]);
    }

    /**
     * Indicate a low confidence prediction
     */
    public function lowConfidence(): static
    {
        return $this->state(fn (array $attributes) => [
            'confidence' => fake()->randomFloat(2, 0.3, 0.5),
        ]);
    }

    /**
     * Indicate prediction with actual outcome
     */
    public function withActualOutcome(): static
    {
        return $this->state(function (array $attributes) {
            $predictedOutcome = $attributes['prediction']['predicted_outcome'];
            $actualOutcome = fake()->boolean(70) ? $predictedOutcome : fake()->randomElement(['win', 'loss', 'settlement']);

            $accuracy = $actualOutcome === $predictedOutcome ? 1.0 : 0.0;

            return [
                'actual_outcome' => ['outcome' => $actualOutcome],
                'actual_outcome_at' => now()->addMonths(fake()->numberBetween(3, 12)),
                'accuracy_score' => $accuracy,
            ];
        });
    }

    /**
     * Duration prediction type
     */
    public function durationType(): static
    {
        return $this->state(fn (array $attributes) => [
            'prediction_type' => 'duration',
            'prediction' => [
                'estimated_days' => fake()->numberBetween(30, 730),
                'estimated_completion_date' => now()->addDays(fake()->numberBetween(30, 730))->toDateString(),
            ],
        ]);
    }

    /**
     * Risk prediction type
     */
    public function riskType(): static
    {
        return $this->state(fn (array $attributes) => [
            'prediction_type' => 'risk',
            'prediction' => [
                'risk_level' => fake()->randomElement(['HIGH', 'MEDIUM', 'LOW']),
                'risk_score' => fake()->randomFloat(2, 0, 1),
            ],
        ]);
    }
}
