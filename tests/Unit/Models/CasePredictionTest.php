<?php

namespace Tests\Unit\Models;

use App\Models\CasePrediction;
use App\Models\LegalCase;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class CasePredictionTest extends TestCase
{
    use UsesTestDatabase;

    /** @test */
    public function it_belongs_to_legal_case()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $prediction = CasePrediction::factory()->create(['case_id' => $case->id]);

        // Act
        $prediction->load('case');

        // Assert
        $this->assertInstanceOf(LegalCase::class, $prediction->case);
        $this->assertEquals($case->id, $prediction->case->id);
    }

    /** @test */
    public function it_casts_features_to_array()
    {
        // Arrange
        $prediction = CasePrediction::factory()->create([
            'features' => ['case_type' => 'civil', 'complexity' => 0.7],
        ]);

        // Assert
        $this->assertIsArray($prediction->features);
        $this->assertArrayHasKey('case_type', $prediction->features);
    }

    /** @test */
    public function it_casts_prediction_to_array()
    {
        // Arrange
        $prediction = CasePrediction::factory()->create([
            'prediction' => ['predicted_outcome' => 'favorable', 'probability' => 0.85],
        ]);

        // Assert
        $this->assertIsArray($prediction->prediction);
        $this->assertEquals('favorable', $prediction->prediction['predicted_outcome']);
    }

    /** @test */
    public function it_casts_similar_cases_to_array()
    {
        // Arrange
        $prediction = CasePrediction::factory()->create([
            'similar_cases' => [
                ['case_id' => '123', 'similarity' => 0.9],
            ],
        ]);

        // Assert
        $this->assertIsArray($prediction->similar_cases);
        $this->assertCount(1, $prediction->similar_cases);
    }

    /** @test */
    public function it_casts_dates_correctly()
    {
        // Arrange
        $prediction = CasePrediction::factory()->create([
            'predicted_at' => now(),
            'actual_outcome_at' => now()->addMonths(6),
        ]);

        // Assert
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $prediction->predicted_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $prediction->actual_outcome_at);
    }

    /** @test */
    public function it_stores_confidence_as_float()
    {
        // Arrange
        $prediction = CasePrediction::factory()->create([
            'confidence' => 0.85,
        ]);

        // Assert
        $this->assertIsFloat($prediction->confidence);
        $this->assertEquals(0.85, $prediction->confidence);
    }

    /** @test */
    public function it_can_filter_by_prediction_type()
    {
        // Arrange
        CasePrediction::factory()->count(3)->create(['prediction_type' => 'outcome']);
        CasePrediction::factory()->durationType()->count(2)->create();

        // Act
        $outcomePredictions = CasePrediction::where('prediction_type', 'outcome')->get();

        // Assert
        $this->assertCount(3, $outcomePredictions);
    }

    /** @test */
    public function it_can_filter_high_confidence_predictions()
    {
        // Arrange
        CasePrediction::factory()->highConfidence()->count(3)->create();
        CasePrediction::factory()->lowConfidence()->count(2)->create();

        // Act
        $highConfidence = CasePrediction::where('confidence', '>=', 0.8)->get();

        // Assert
        $this->assertGreaterThanOrEqual(3, $highConfidence->count());
    }
}
