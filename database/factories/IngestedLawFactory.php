<?php

namespace Database\Factories;

use App\Models\IngestedLaw;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class IngestedLawFactory extends Factory
{
    protected $model = IngestedLaw::class;

    public function definition(): array
    {
        return [
            'id' => Str::ulid(),
            'doc_id' => Str::ulid(),
            'title' => fake()->sentence(),
            'law_number' => 'NN '.fake()->numberBetween(1, 200).'/'.fake()->year(),
            'jurisdiction' => fake()->randomElement(['HR', 'EU', 'regional', 'county', 'local']),
            'country' => 'HR',
            'language' => 'hr',
            'source_url' => fake()->url(),
            'aliases' => [
                fake()->words(2, true),
                fake()->words(3, true),
            ],
            'keywords' => fake()->randomElements(
                ['criminal', 'civil', 'administrative', 'procedure', 'rights', 'obligations'],
                fake()->numberBetween(2, 4)
            ),
            'keywords_text' => fake()->words(5, true),
            'metadata' => [
                'source' => 'NN',
                'type' => 'law',
                'ingestion_method' => 'manual',
            ],
            'ingested_at' => fake()->dateTimeBetween('-1 year', 'now'),
        ];
    }

    /**
     * Indicate a Croatian national law
     */
    public function croatian(): static
    {
        return $this->state(fn (array $attributes) => [
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
        ]);
    }

    /**
     * Indicate an EU law
     */
    public function european(): static
    {
        return $this->state(fn (array $attributes) => [
            'jurisdiction' => 'EU',
            'country' => 'EU',
            'language' => 'en',
        ]);
    }

    /**
     * Set specific law number
     */
    public function withLawNumber(string $lawNumber): static
    {
        return $this->state(fn (array $attributes) => [
            'law_number' => $lawNumber,
        ]);
    }

    /**
     * Set specific title
     */
    public function withTitle(string $title): static
    {
        return $this->state(fn (array $attributes) => [
            'title' => $title,
        ]);
    }
}
