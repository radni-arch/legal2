<?php

namespace Database\Factories;

use App\Models\LawUpload;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class LawUploadFactory extends Factory
{
    protected $model = LawUpload::class;

    public function definition(): array
    {
        return [
            'id' => Str::ulid(),
            'doc_id' => Str::ulid(),
            'disk' => 'local',
            'local_path' => '/uploads/laws/'.fake()->uuid().'.pdf',
            'original_filename' => 'law_'.fake()->numberBetween(1, 1000).'.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => fake()->numberBetween(10000, 5000000), // 10KB to 5MB
            'sha256' => hash('sha256', fake()->text(200)),
            'source_url' => fake()->url(),
            'downloaded_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'status' => 'stored',
            'error' => null,
        ];
    }

    /**
     * Indicate an upload with error status
     */
    public function error(string $errorMessage = 'Download failed'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'error',
            'error' => $errorMessage,
        ]);
    }

    /**
     * Indicate an upload with pending status
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'downloaded_at' => null,
        ]);
    }

    /**
     * Indicate an upload with processing status
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
        ]);
    }

    /**
     * Set specific file path
     */
    public function withPath(string $path): static
    {
        return $this->state(fn (array $attributes) => [
            'local_path' => $path,
        ]);
    }
}
