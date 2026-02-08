<?php

namespace Database\Factories;

use App\Models\DocumentGenerationRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentGenerationRunFactory extends Factory
{
    protected $model = DocumentGenerationRun::class;

    public function definition(): array
    {
        return [
            'document_type' => 'suppression_motion',
            'case_id' => null,
            'status' => 'running',
            'final_document' => null,
            'final_score' => null,
            'total_iterations' => null,
            'stopped_reason' => null,
            'model_config' => ['critic_model' => 'gpt-4o', 'worker_model' => 'gpt-4o'],
            'user_id' => User::factory(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'final_document' => 'Test document content',
            'final_score' => 85.50,
            'total_iterations' => 5,
            'stopped_reason' => 'converged',
        ]);
    }
}
