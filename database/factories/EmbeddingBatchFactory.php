<?php

namespace Database\Factories;

use App\Models\EmbeddingBatch;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class EmbeddingBatchFactory extends Factory
{
    protected $model = EmbeddingBatch::class;

    public function definition(): array
    {
        $totalItems = fake()->numberBetween(10, 100);

        return [
            'id' => Str::uuid(),
            'source_type' => fake()->randomElement(['laws', 'cases_documents', 'court_decisions_documents']),
            'total_items' => $totalItems,
            'processed_items' => 0,
            'failed_items' => 0,
            'status' => 'pending',
            'embedding_model' => 'text-embedding-3-small',
            'item_ids' => array_map(fn () => Str::uuid(), range(1, $totalItems)),
            'configuration' => [
                'batch_size' => 20,
                'timeout' => 300,
                'retry_attempts' => 3,
            ],
            'tokens_used' => 0,
            'cost' => '0.0000',
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
            'processed_items' => fake()->numberBetween(1, $attributes['total_items'] - 1),
            'started_at' => now()->subMinutes(fake()->numberBetween(5, 30)),
            'tokens_used' => fake()->numberBetween(1000, 50000),
            'cost' => fake()->randomFloat(4, 0.01, 1.0),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'processed_items' => $attributes['total_items'],
            'failed_items' => fake()->numberBetween(0, 5),
            'started_at' => now()->subHour(),
            'completed_at' => now()->subMinutes(30),
            'tokens_used' => fake()->numberBetween(10000, 100000),
            'cost' => fake()->randomFloat(4, 0.2, 2.0),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'processed_items' => fake()->numberBetween(0, $attributes['total_items'] / 2),
            'failed_items' => fake()->numberBetween(5, 20),
            'started_at' => now()->subHour(),
            'completed_at' => now()->subMinutes(45),
        ]);
    }
}
