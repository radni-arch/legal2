<?php

namespace Database\Factories;

use App\Models\LearningOpportunity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for LearningOpportunity model
 *
 * Sprint 5.2: Human Feedback Integration
 */
class LearningOpportunityFactory extends Factory
{
    protected $model = LearningOpportunity::class;

    public function definition(): array
    {
        $types = ['decision_discovery', 'precedent_analysis'];
        $opportunityType = $this->faker->randomElement($types);

        $aiOutput = $opportunityType === 'decision_discovery'
            ? [
                'id' => 'dec-'.$this->faker->unique()->numberBetween(1000, 9999),
                'score' => $this->faker->numberBetween(30, 59),
                'reasoning' => $this->faker->sentence(),
                'topic' => $this->faker->randomElement(['contract law', 'property law', 'criminal procedure']),
            ]
            : [
                'decision_id' => 'dec-'.$this->faker->unique()->numberBetween(1000, 9999),
                'applicability_score' => $this->faker->numberBetween(30, 59),
                'binding_authority' => $this->faker->randomElement(['binding', 'persuasive', 'informative']),
                'key_factors' => [$this->faker->word(), $this->faker->word()],
                'reasoning' => $this->faker->sentence(),
            ];

        $sourceType = $opportunityType === 'decision_discovery'
            ? 'decision_score'
            : 'applicability_check';

        return [
            'opportunity_type' => $opportunityType,
            'source_type' => $sourceType,
            'source_id' => $this->faker->unique()->numberBetween(10000, 99999),
            'ai_output' => $aiOutput,
            'confidence_score' => $this->faker->randomFloat(2, 0.30, 0.59),
            'uncertainty_reason' => 'Low confidence score: '.$this->faker->randomFloat(2, 0.30, 0.59),
            'status' => 'pending',
        ];
    }

    /**
     * Indicate that the opportunity has been reviewed.
     */
    public function reviewed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'reviewed',
            'reviewed_by' => \App\Models\User::factory(),
            'reviewed_at' => now(),
            'human_label' => [
                'correct_score' => $this->faker->numberBetween(60, 100),
                'reasoning' => $this->faker->sentence(),
            ],
        ]);
    }

    /**
     * Indicate that the opportunity is for decision discovery.
     */
    public function decisionDiscovery(): static
    {
        return $this->state(fn (array $attributes) => [
            'opportunity_type' => 'decision_discovery',
            'source_type' => 'decision_score',
            'ai_output' => [
                'id' => 'dec-'.$this->faker->unique()->numberBetween(1000, 9999),
                'score' => $this->faker->numberBetween(30, 59),
                'reasoning' => $this->faker->sentence(),
                'topic' => $this->faker->randomElement(['contract law', 'property law']),
            ],
        ]);
    }

    /**
     * Indicate that the opportunity is for precedent analysis.
     */
    public function precedentAnalysis(): static
    {
        return $this->state(fn (array $attributes) => [
            'opportunity_type' => 'precedent_analysis',
            'source_type' => 'applicability_check',
            'ai_output' => [
                'decision_id' => 'dec-'.$this->faker->unique()->numberBetween(1000, 9999),
                'applicability_score' => $this->faker->numberBetween(30, 59),
                'binding_authority' => $this->faker->randomElement(['binding', 'persuasive', 'informative']),
                'key_factors' => [$this->faker->word()],
                'reasoning' => $this->faker->sentence(),
            ],
        ]);
    }
}
