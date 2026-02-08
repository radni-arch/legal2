<?php

namespace Database\Factories;

use App\Models\CourtDecision;
use App\Models\CourtDecisionDocument;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CourtDecisionDocumentFactory extends Factory
{
    protected $model = CourtDecisionDocument::class;

    public function definition(): array
    {
        return [
            'id' => Str::ulid()->toString(),
            'decision_id' => CourtDecision::factory(),
            'doc_id' => Str::ulid()->toString(),
            'title' => fake()->sentence(),
            'category' => fake()->randomElement(['decision', 'reasoning', 'opinion', 'order']),
            'author' => fake()->optional()->name(),
            'language' => 'hr',
            'tags' => fake()->randomElements(['judgment', 'ruling', 'commercial', 'civil'], fake()->numberBetween(1, 2)),
            'chunk_index' => 0,
            'content' => fake()->paragraphs(5, true),
            'metadata' => [
                'source' => 'court',
                'type' => 'decision',
            ],
            'source' => 'court_database',
            'source_id' => fake()->uuid(),
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-3-small',
            'embedding_dimensions' => 1536,
            'embedding_norm' => fake()->randomFloat(4, 0.9, 1.0),
            'content_hash' => hash('sha256', fake()->text()),
            'token_count' => fake()->numberBetween(500, 2000),
            // 'embedding_vector' does not exist in schema - use 'embedding' instead
            'embedding' => array_fill(0, 1536, fake()->randomFloat(4, -1, 1)),
        ];
    }

    /**
     * Indicate a document with specific content for citation testing
     */
    public function withCitation(string $caseNumber): static
    {
        return $this->state(fn (array $attributes) => [
            'content' => "This decision follows precedent set in case {$caseNumber}. ".fake()->paragraph(),
        ]);
    }

    /**
     * Indicate a document without embeddings (not allowed - embedding is required)
     * Use a zero vector instead
     */
    public function withoutEmbeddings(): static
    {
        return $this->state(fn (array $attributes) => [
            'embedding' => array_fill(0, 1536, 0.0),
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-3-small',
            'embedding_dimensions' => 1536,
        ]);
    }
}
