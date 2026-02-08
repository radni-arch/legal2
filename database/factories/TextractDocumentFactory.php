<?php

namespace Database\Factories;

use App\Models\LegalCase;
use App\Models\TextractDocument;
use App\Models\TextractJob;
use Illuminate\Database\Eloquent\Factories\Factory;

class TextractDocumentFactory extends Factory
{
    protected $model = TextractDocument::class;

    public function definition(): array
    {
        return [
            'textract_job_id' => TextractJob::factory(),
            'case_id' => LegalCase::factory(),
            'content' => fake()->paragraphs(3, true),
            'chunk_index' => 0,
            'chunk_overlap' => 100,
            'embedding' => null,
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-3-small',
            'embedding_dimensions' => 1536,
            'token_count' => fake()->numberBetween(100, 500),
            'processing_status' => 'pending',
            'processing_error' => null,
            'embedded_at' => null,
            'metadata' => [
                'page' => fake()->numberBetween(1, 50),
            ],
        ];
    }

    public function processed(): static
    {
        return $this->state(fn (array $attributes) => [
            'processing_status' => 'completed',
            'embedding' => array_fill(0, 1536, fake()->randomFloat(6, -1, 1)),
            'embedded_at' => now()->subMinutes(fake()->numberBetween(5, 60)),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'processing_status' => 'pending',
            'embedding' => null,
            'embedded_at' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'processing_status' => 'failed',
            'processing_error' => 'Failed to generate embedding: API timeout',
            'embedding' => null,
            'embedded_at' => null,
        ]);
    }

    public function withEmbedding(): static
    {
        return $this->processed();
    }
}
