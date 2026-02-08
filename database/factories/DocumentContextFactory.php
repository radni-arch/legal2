<?php

namespace Database\Factories;

use App\Models\DocumentContext;
use App\Models\DocumentGenerationRun;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentContextFactory extends Factory
{
    protected $model = DocumentContext::class;

    public function definition(): array
    {
        return [
            'generation_run_id' => DocumentGenerationRun::factory(),
            'context_type' => 'standalone',
            'raw_input' => 'Test context input',
            'assembled_context' => 'Assembled test context',
            'case_ids' => null,
            'evidence_ids' => null,
            'decision_ids' => null,
            'law_ids' => null,
            'created_at' => now(),
        ];
    }

    public function caseData(): static
    {
        return $this->state(fn (array $attributes) => [
            'context_type' => 'case_data',
            'case_ids' => ['uuid-1'],
        ]);
    }

    public function mixed(): static
    {
        return $this->state(fn (array $attributes) => [
            'context_type' => 'mixed',
            'case_ids' => ['uuid-1'],
            'evidence_ids' => ['ev-1', 'ev-2'],
        ]);
    }
}
