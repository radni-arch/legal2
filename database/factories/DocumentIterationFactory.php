<?php

namespace Database\Factories;

use App\Models\DocumentGenerationRun;
use App\Models\DocumentIteration;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentIterationFactory extends Factory
{
    protected $model = DocumentIteration::class;

    public function definition(): array
    {
        return [
            'generation_run_id' => DocumentGenerationRun::factory(),
            'iteration_number' => 1,
            'phase' => 'critic',
            'document_version' => null,
            'critic_feedback' => null,
            'scores' => null,
            'weighted_score' => null,
            'improvement_delta' => null,
            'ai_model_used' => 'gpt-4o',
            'tokens_used' => 1500,
            'cost_estimate' => 0.03,
            'created_at' => now(),
        ];
    }

    public function criticPhase(): static
    {
        return $this->state(fn (array $attributes) => [
            'phase' => 'critic',
            'critic_feedback' => [
                'scores' => ['legal_rigor' => 85],
                'weaknesses' => ['test'],
                'improvement_plan' => 'test plan',
            ],
        ]);
    }

    public function workerPhase(): static
    {
        return $this->state(fn (array $attributes) => [
            'phase' => 'worker',
            'document_version' => 'Test document content',
            'scores' => ['legal_rigor' => 85, 'persuasiveness' => 78, 'clarity' => 90],
            'weighted_score' => 82.50,
        ]);
    }
}
