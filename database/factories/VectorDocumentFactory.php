<?php

namespace Database\Factories;

use App\Models\VectorDocument;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class VectorDocumentFactory extends Factory
{
    protected $model = VectorDocument::class;

    public function definition(): array
    {
        return [
            'file_name' => fake()->uuid().'.pdf',
            'file_path' => '/storage/documents/'.fake()->uuid().'.pdf',
            'openai_file_id' => 'file-'.Str::random(24),
            'vector_store_id' => 'vs_'.Str::random(32),
            'case_id' => 'KP-'.fake()->numberBetween(100, 999).'-'.fake()->year(),
            'status' => VectorDocument::STATUS_PENDING,
            'metadata' => [
                'case_id' => 'KP-'.fake()->numberBetween(100, 999).'-'.fake()->year(),
                'vrsta' => fake()->randomElement(['odluka', 'rjesenje', 'nalog', 'zapisnik']),
                'artifact' => fake()->randomElement(['sudska_odluka', 'izvjestak_vjestaka', 'zapisnik']),
                'ključne_riječi' => [fake()->word(), fake()->word(), fake()->word()],
            ],
            'attributes' => null,
            'catalog_entry' => null,
            'tagged_at' => null,
            'uploaded_at' => null,
            'cataloged_at' => null,
            'tagger_model' => null,
            'confidence' => null,
        ];
    }

    /**
     * Mark the document as tagged.
     */
    public function tagged(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VectorDocument::STATUS_TAGGED,
            'tagged_at' => now(),
            'tagger_model' => 'gpt-4o',
            'confidence' => fake()->randomFloat(2, 0.7, 0.99),
        ]);
    }

    /**
     * Mark the document as uploaded.
     */
    public function uploaded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VectorDocument::STATUS_UPLOADED,
            'tagged_at' => now()->subHour(),
            'uploaded_at' => now(),
            'tagger_model' => 'gpt-4o',
            'confidence' => fake()->randomFloat(2, 0.7, 0.99),
            'openai_file_id' => 'file-'.Str::random(24),
        ]);
    }

    /**
     * Mark the document as cataloged.
     */
    public function cataloged(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VectorDocument::STATUS_CATALOGED,
            'tagged_at' => now()->subHours(2),
            'uploaded_at' => now()->subHour(),
            'cataloged_at' => now(),
            'tagger_model' => 'gpt-4o',
            'confidence' => fake()->randomFloat(2, 0.7, 0.99),
            'openai_file_id' => 'file-'.Str::random(24),
            'catalog_entry' => [
                'type' => 'catalog_entry',
                'file_name' => $attributes['file_name'] ?? fake()->uuid().'.pdf',
            ],
        ]);
    }

    /**
     * Mark the document as having an error.
     */
    public function error(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VectorDocument::STATUS_ERROR,
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'last_error' => 'Processing failed',
                'error_at' => now()->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Assign to a specific vector store.
     */
    public function forVectorStore(string $vsId): static
    {
        return $this->state(fn (array $attributes) => [
            'vector_store_id' => $vsId,
        ]);
    }
}
