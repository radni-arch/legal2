<?php

namespace Database\Factories;

use App\Models\Law;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class LawFactory extends Factory
{
    protected $model = Law::class;

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
            'promulgation_date' => fake()->dateTimeBetween('-10 years', '-1 year'),
            'effective_date' => fake()->dateTimeBetween('-9 years', 'now'),
            'repeal_date' => null,
            'version' => '1.0',
            'chapter' => fake()->optional()->numerify('Chapter ##'),
            'section' => fake()->optional()->numerify('Section ##'),
            'tags' => fake()->randomElements(['civil', 'commercial', 'administrative', 'tax'], fake()->numberBetween(1, 3)),
            'source_url' => fake()->url(),
            'chunk_index' => 0,
            'content' => fake()->paragraphs(5, true),
            'metadata' => [
                'source' => 'NN',
                'type' => 'law',
            ],
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-3-small',
            'embedding_dimensions' => 1536,
            'embedding_norm' => fake()->randomFloat(4, 0.9, 1.0),
            'content_hash' => hash('sha256', fake()->text()),
            'token_count' => fake()->numberBetween(100, 1000),
            'embedding_vector' => array_fill(0, 1536, fake()->randomFloat(4, -1, 1)),
        ];
    }

    /**
     * Indicate a national-level law
     */
    public function national(): static
    {
        return $this->state(fn (array $attributes) => [
            'jurisdiction' => 'HR',
        ]);
    }

    /**
     * Indicate an EU-level law
     */
    public function eu(): static
    {
        return $this->state(fn (array $attributes) => [
            'jurisdiction' => 'EU',
        ]);
    }

    /**
     * Indicate a regional law
     */
    public function regional(): static
    {
        return $this->state(fn (array $attributes) => [
            'jurisdiction' => 'regional',
        ]);
    }

    /**
     * Indicate a law that has been repealed
     */
    public function repealed(): static
    {
        return $this->state(fn (array $attributes) => [
            'repeal_date' => fake()->dateTimeBetween('-1 year', 'now'),
        ]);
    }

    /**
     * Indicate a law with high specificity
     */
    public function specific(): static
    {
        return $this->state(fn (array $attributes) => [
            'content' => fake()->paragraphs(20, true), // Longer content
            'chapter' => 'Chapter '.fake()->numberBetween(1, 50),
            'section' => 'Section '.fake()->numberBetween(1, 100),
            'tags' => ['civil', 'contract', 'commercial', 'international'],
        ]);
    }

    /**
     * Indicate a law with express repeal provision
     */
    public function withRepealProvision(): static
    {
        return $this->state(fn (array $attributes) => [
            'content' => $attributes['content']."\n\nOvaj zakon repeals and stavlja van snage sve prethodne odredbe koje su u suprotnosti s ovim zakonom.",
        ]);
    }

    /**
     * Indicate a recent law
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'promulgation_date' => fake()->dateTimeBetween('-6 months', 'now'),
            'effective_date' => fake()->dateTimeBetween('-3 months', 'now'),
        ]);
    }

    /**
     * Indicate an old law
     */
    public function old(): static
    {
        return $this->state(fn (array $attributes) => [
            'promulgation_date' => fake()->dateTimeBetween('-20 years', '-10 years'),
            'effective_date' => fake()->dateTimeBetween('-19 years', '-9 years'),
        ]);
    }

    /**
     * Indicate a law without embeddings
     */
    public function withoutEmbeddings(): static
    {
        return $this->state(fn (array $attributes) => [
            // 'embedding_vector' => null,
            // 'embedding_provider' => 'none',
            // 'embedding_model' => 'none',
            // 'embedding_dimensions' => 0,
            'embedding_vector' => array_fill(0, 1536, 0.0),  // Use zero vector to avoid NOT NULL violation
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-3-small',
            'embedding_dimensions' => 1536,
        ]);
    }
}
