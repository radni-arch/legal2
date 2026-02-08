<?php

namespace Database\Factories;

use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DocumentAnalysis>
 */
class DocumentAnalysisFactory extends Factory
{
    protected $model = DocumentAnalysis::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'case_document_id' => CaseDocument::factory(),
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => $this->faker->randomElement([
                DocumentAnalysis::TYPE_KEYWORDS,
                DocumentAnalysis::TYPE_ENTITIES,
                DocumentAnalysis::TYPE_DATES,
                DocumentAnalysis::TYPE_STATISTICS,
            ]),
            'status' => DocumentAnalysis::STATUS_PENDING,
            'results' => null,
            'metadata' => null,
            'error_message' => null,
            'version' => 1,
        ];
    }

    /**
     * Indicate that the analysis is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => ['keywords' => ['test', 'document']],
            'metadata' => ['processing_time_seconds' => 0.5],
            'completed_at' => now(),
        ]);
    }

    /**
     * Indicate that the analysis failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentAnalysis::STATUS_FAILED,
            'error_message' => 'Test error message',
            'completed_at' => now(),
        ]);
    }

    /**
     * Indicate that the analysis is processing.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentAnalysis::STATUS_PROCESSING,
            'started_at' => now(),
        ]);
    }
}
