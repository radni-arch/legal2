<?php

namespace Database\Factories;

use App\Models\LegalCase;
use App\Models\TextractJob;
use Illuminate\Database\Eloquent\Factories\Factory;

class TextractJobFactory extends Factory
{
    protected $model = TextractJob::class;

    public function definition(): array
    {
        return [
            'drive_file_id' => fake()->uuid(),
            'drive_file_name' => fake()->word().'.pdf',
            'case_id' => LegalCase::factory(),
            's3_key' => fake()->optional()->regexify('textract/input/[a-z0-9]{32}\.pdf'),
            'job_id' => fake()->optional()->uuid(),
            'status' => 'queued',
            'error' => null,
            'metadata' => null,
            'extracted_content' => null,
            'manual_content' => null,
            'manually_edited' => false,
            'embedding_status' => 'pending',
            'graph_sync_status' => 'pending',
        ];
    }

    /**
     * Indicate a queued job
     */
    public function queued(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'queued',
            'job_id' => null,
            's3_key' => null,
        ]);
    }

    /**
     * Indicate a job in progress
     */
    public function analyzing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'analyzing',
            'job_id' => fake()->uuid(),
            's3_key' => fake()->regexify('textract/input/[a-z0-9]{32}\.pdf'),
        ]);
    }

    /**
     * Indicate a succeeded job
     */
    public function succeeded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'succeeded',
            'job_id' => fake()->uuid(),
            's3_key' => fake()->regexify('textract/input/[a-z0-9]{32}\.pdf'),
            'extracted_content' => fake()->paragraphs(5, true),
        ]);
    }

    /**
     * Indicate a completed job
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'job_id' => fake()->uuid(),
            's3_key' => fake()->regexify('textract/input/[a-z0-9]{32}\.pdf'),
            'extracted_content' => fake()->paragraphs(5, true),
        ]);
    }

    /**
     * Indicate a failed job
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'error' => fake()->sentence(),
        ]);
    }

    /**
     * Indicate a job with manual edits
     */
    public function manuallyEdited(): static
    {
        return $this->state(fn (array $attributes) => [
            'manually_edited' => true,
            'manual_content' => fake()->paragraphs(5, true),
            'content_edited_at' => now(),
        ]);
    }

    /**
     * Indicate a job ready for embedding
     */
    public function readyForEmbedding(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'succeeded',
            'extracted_content' => fake()->paragraphs(5, true),
            'embedding_status' => 'pending',
        ]);
    }

    /**
     * Indicate a job with synced embeddings
     */
    public function embeddingsSynced(): static
    {
        return $this->state(fn (array $attributes) => [
            'embedding_status' => 'synced',
            'embedding_synced_at' => now(),
        ]);
    }
}
