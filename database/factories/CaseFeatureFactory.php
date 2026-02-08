<?php

namespace Database\Factories;

use App\Models\CaseFeature;
use App\Models\LegalCase;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CaseFeatureFactory extends Factory
{
    protected $model = CaseFeature::class;

    public function definition(): array
    {
        return [
            'id' => Str::ulid(),
            'case_id' => LegalCase::factory(),
            'case_type' => fake()->randomElement(['civil', 'criminal', 'administrative', 'commercial']),
            'case_category' => fake()->randomElement(['contract', 'tort', 'property', 'employment']),
            'complexity_level' => fake()->randomElement(['simple', 'moderate', 'complex', 'very_complex']),
            'complexity_score' => fake()->randomFloat(2, 0, 1),
            'document_count' => fake()->numberBetween(1, 50),
            'precedent_count' => fake()->numberBetween(0, 20),
            'client_type' => fake()->randomElement(['individual', 'company', 'government']),
            'opponent_type' => fake()->randomElement(['individual', 'company', 'government']),
            'legal_issues' => [
                fake()->word(),
                fake()->word(),
            ],
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-3-small',
            'embedding_dimensions' => 1536,
            'embedding_vector' => array_fill(0, 1536, fake()->randomFloat(4, -1, 1)),
            'embedding_norm' => fake()->randomFloat(4, 0.9, 1.0),
            'features_extracted_at' => now(),
        ];
    }

    /**
     * Indicate that the features are recent (< 7 days old)
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'features_extracted_at' => now()->subDays(fake()->numberBetween(1, 6)),
        ]);
    }

    /**
     * Indicate that the features are old (> 7 days old)
     */
    public function old(): static
    {
        return $this->state(fn (array $attributes) => [
            'features_extracted_at' => now()->subDays(fake()->numberBetween(8, 30)),
        ]);
    }

    /**
     * Indicate a complex case
     */
    public function complex(): static
    {
        return $this->state(fn (array $attributes) => [
            'complexity_level' => 'very_complex',
            'complexity_score' => fake()->randomFloat(2, 0.7, 1.0),
            'document_count' => fake()->numberBetween(30, 100),
            'precedent_count' => fake()->numberBetween(10, 30),
        ]);
    }

    /**
     * Indicate a simple case
     */
    public function simple(): static
    {
        return $this->state(fn (array $attributes) => [
            'complexity_level' => 'simple',
            'complexity_score' => fake()->randomFloat(2, 0.1, 0.3),
            'document_count' => fake()->numberBetween(1, 5),
            'precedent_count' => fake()->numberBetween(0, 3),
        ]);
    }
}
