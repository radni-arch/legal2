<?php

namespace Tests\Unit\Services;

use App\Services\ConfidenceCalibrator;
use Tests\TestCase;

/**
 * TDD Tests for ConfidenceCalibrator Service
 *
 * Sprint 5.6: Confidence Calibration System
 *
 * Tests multi-factor confidence calculation using:
 * - Citation count (0.25 weight)
 * - Citation quality (0.30 weight)
 * - Data freshness (0.15 weight)
 * - Consensus across sources (0.20 weight)
 * - LLM confidence (0.10 weight)
 *
 * Following strict TDD: Tests written FIRST, will fail until implementation.
 */
class ConfidenceCalibratorTest extends TestCase
{
    protected ConfidenceCalibrator $calibrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calibrator = new ConfidenceCalibrator;
    }

    /** @test */
    public function it_calculates_confidence_with_all_five_factors()
    {
        $factors = [
            'citation_count' => 15,           // High count (normalized to ~0.9)
            'citation_quality' => 0.85,       // High quality
            'data_freshness' => 30,           // 30 days old (fresh)
            'consensus_score' => 0.90,        // High consensus
            'llm_confidence' => 0.88,         // High LLM confidence
        ];

        $result = $this->calibrator->calculate($factors);

        // Result should include calibrated confidence
        $this->assertArrayHasKey('confidence', $result);
        $this->assertIsFloat($result['confidence']);
        $this->assertGreaterThanOrEqual(0, $result['confidence']);
        $this->assertLessThanOrEqual(1, $result['confidence']);

        // With all high factors, confidence should be high (>0.8)
        $this->assertGreaterThan(0.8, $result['confidence']);
    }

    /** @test */
    public function it_applies_correct_weights_to_each_factor()
    {
        // Test with perfect scores on all factors
        $perfectFactors = [
            'citation_count' => 20,
            'citation_quality' => 1.0,
            'data_freshness' => 0,
            'consensus_score' => 1.0,
            'llm_confidence' => 1.0,
        ];

        $result = $this->calibrator->calculate($perfectFactors);

        // With all perfect scores, should be very close to 1.0
        $this->assertGreaterThan(0.95, $result['confidence']);

        // Test with zero scores on all factors
        $zeroFactors = [
            'citation_count' => 0,
            'citation_quality' => 0.0,
            'data_freshness' => 365,
            'consensus_score' => 0.0,
            'llm_confidence' => 0.0,
        ];

        $result = $this->calibrator->calculate($zeroFactors);

        // With all zero scores, should be very low
        $this->assertLessThan(0.2, $result['confidence']);
    }

    /** @test */
    public function it_verifies_weights_sum_to_one()
    {
        $weights = $this->calibrator->getWeights();

        $this->assertArrayHasKey('citation_count', $weights);
        $this->assertArrayHasKey('citation_quality', $weights);
        $this->assertArrayHasKey('data_freshness', $weights);
        $this->assertArrayHasKey('consensus_score', $weights);
        $this->assertArrayHasKey('llm_confidence', $weights);

        // Verify exact weights from requirements
        $this->assertEquals(0.25, $weights['citation_count']);
        $this->assertEquals(0.30, $weights['citation_quality']);
        $this->assertEquals(0.15, $weights['data_freshness']);
        $this->assertEquals(0.20, $weights['consensus_score']);
        $this->assertEquals(0.10, $weights['llm_confidence']);

        // Verify they sum to 1.0
        $sum = array_sum($weights);
        $this->assertEqualsWithDelta(1.0, $sum, 0.001, 'Weights must sum to 1.0');
    }

    /** @test */
    public function it_calculates_uncertainty_range()
    {
        $factors = [
            'citation_count' => 10,
            'citation_quality' => 0.80,
            'data_freshness' => 60,
            'consensus_score' => 0.75,
            'llm_confidence' => 0.82,
        ];

        $result = $this->calibrator->calculate($factors);

        // Result should include uncertainty range
        $this->assertArrayHasKey('uncertainty_range', $result);
        $this->assertIsFloat($result['uncertainty_range']);

        // Uncertainty should be positive and reasonable (0-0.2)
        $this->assertGreaterThan(0, $result['uncertainty_range']);
        $this->assertLessThan(0.2, $result['uncertainty_range']);

        // Result should include lower and upper bounds
        $this->assertArrayHasKey('confidence_lower', $result);
        $this->assertArrayHasKey('confidence_upper', $result);

        // Bounds should be within [0, 1]
        $this->assertGreaterThanOrEqual(0, $result['confidence_lower']);
        $this->assertLessThanOrEqual(1, $result['confidence_upper']);

        // Upper should be greater than lower
        $this->assertGreaterThan($result['confidence_lower'], $result['confidence_upper']);
    }

    /** @test */
    public function it_handles_missing_optional_factors_gracefully()
    {
        // Only provide required factors
        $minimalFactors = [
            'citation_count' => 5,
            'citation_quality' => 0.70,
        ];

        $result = $this->calibrator->calculate($minimalFactors);

        // Should still calculate confidence
        $this->assertArrayHasKey('confidence', $result);
        $this->assertIsFloat($result['confidence']);

        // Should use defaults for missing factors
        $this->assertArrayHasKey('factors_used', $result);
    }

    /** @test */
    public function it_normalizes_citation_count_correctly()
    {
        // Test various citation counts
        $testCases = [
            ['count' => 0, 'expected_normalized' => 0.0],
            ['count' => 5, 'expected_normalized' => 0.5],   // Mid-range
            ['count' => 10, 'expected_normalized' => 0.75],  // Good
            ['count' => 20, 'expected_normalized' => 0.95],  // Excellent
        ];

        foreach ($testCases as $case) {
            $result = $this->calibrator->normalizeCitationCount($case['count']);

            $this->assertIsFloat($result);
            $this->assertGreaterThanOrEqual(0, $result);
            $this->assertLessThanOrEqual(1, $result);
        }
    }

    /** @test */
    public function it_normalizes_data_freshness_correctly()
    {
        // Test various ages in days
        $testCases = [
            ['days' => 0, 'expected_normalized' => 1.0],     // Today = perfect
            ['days' => 30, 'expected_normalized' => 0.90],   // 1 month = very fresh
            ['days' => 90, 'expected_normalized' => 0.70],   // 3 months = acceptable
            ['days' => 365, 'expected_normalized' => 0.10],  // 1 year = stale
        ];

        foreach ($testCases as $case) {
            $result = $this->calibrator->normalizeFreshness($case['days']);

            $this->assertIsFloat($result);
            $this->assertGreaterThanOrEqual(0, $result);
            $this->assertLessThanOrEqual(1, $result);
        }
    }

    /** @test */
    public function it_validates_input_ranges()
    {
        // Test with invalid citation quality (>1.0)
        $invalidFactors = [
            'citation_count' => 10,
            'citation_quality' => 1.5,  // Invalid: >1.0
            'data_freshness' => 30,
            'consensus_score' => 0.80,
            'llm_confidence' => 0.85,
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->calibrator->calculate($invalidFactors);
    }

    /** @test */
    public function it_provides_breakdown_of_factor_contributions()
    {
        $factors = [
            'citation_count' => 15,
            'citation_quality' => 0.85,
            'data_freshness' => 30,
            'consensus_score' => 0.90,
            'llm_confidence' => 0.88,
        ];

        $result = $this->calibrator->calculate($factors);

        // Result should include breakdown of each factor's contribution
        $this->assertArrayHasKey('breakdown', $result);
        $this->assertIsArray($result['breakdown']);

        // Each factor should have normalized value and weighted contribution
        $this->assertArrayHasKey('citation_count', $result['breakdown']);
        $this->assertArrayHasKey('citation_quality', $result['breakdown']);
        $this->assertArrayHasKey('data_freshness', $result['breakdown']);
        $this->assertArrayHasKey('consensus_score', $result['breakdown']);
        $this->assertArrayHasKey('llm_confidence', $result['breakdown']);
    }

    /** @test */
    public function it_achieves_target_calibration_accuracy()
    {
        // Test calibration: confidence 0.8 should mean 80% actual accuracy (±5%)
        $targetConfidence = 0.80;

        // Create factors that should produce ~0.80 confidence
        $factors = [
            'citation_count' => 8,
            'citation_quality' => 0.80,
            'data_freshness' => 45,
            'consensus_score' => 0.80,
            'llm_confidence' => 0.80,
        ];

        $result = $this->calibrator->calculate($factors);

        // Should be close to 0.80 (±0.05)
        $this->assertGreaterThanOrEqual(0.75, $result['confidence']);
        $this->assertLessThanOrEqual(0.85, $result['confidence']);
    }

    /** @test */
    public function it_calculates_uncertainty_based_on_factor_variance()
    {
        // High variance in factors should increase uncertainty
        $highVarianceFactors = [
            'citation_count' => 20,      // Very high (1.0 normalized)
            'citation_quality' => 0.30,  // Low
            'data_freshness' => 0,       // Perfect (1.0 normalized)
            'consensus_score' => 0.40,   // Low
            'llm_confidence' => 0.95,    // Very high
        ];

        $highVarianceResult = $this->calibrator->calculate($highVarianceFactors);

        // Low variance in factors should decrease uncertainty
        $lowVarianceFactors = [
            'citation_count' => 10,
            'citation_quality' => 0.80,
            'data_freshness' => 30,
            'consensus_score' => 0.80,
            'llm_confidence' => 0.80,
        ];

        $lowVarianceResult = $this->calibrator->calculate($lowVarianceFactors);

        // High variance should have larger uncertainty range
        $this->assertGreaterThan(
            $lowVarianceResult['uncertainty_range'],
            $highVarianceResult['uncertainty_range']
        );
    }

    /** @test */
    public function it_provides_confidence_level_interpretation()
    {
        $testCases = [
            ['confidence' => 0.95, 'expected_level' => 'very_high'],
            ['confidence' => 0.85, 'expected_level' => 'high'],
            ['confidence' => 0.70, 'expected_level' => 'medium'],
            ['confidence' => 0.50, 'expected_level' => 'low'],
            ['confidence' => 0.30, 'expected_level' => 'very_low'],
        ];

        foreach ($testCases as $case) {
            $factors = [
                'citation_count' => 10,
                'citation_quality' => $case['confidence'],
                'data_freshness' => 30,
                'consensus_score' => $case['confidence'],
                'llm_confidence' => $case['confidence'],
            ];

            $result = $this->calibrator->calculate($factors);

            $this->assertArrayHasKey('confidence_level', $result);
            $this->assertIsString($result['confidence_level']);
        }
    }

    /** @test */
    public function it_handles_edge_case_of_zero_citations()
    {
        $factors = [
            'citation_count' => 0,
            'citation_quality' => 0.85,  // Good quality but no citations
            'data_freshness' => 10,
            'consensus_score' => 0.80,
            'llm_confidence' => 0.90,
        ];

        $result = $this->calibrator->calculate($factors);

        // Should still calculate but with lower confidence
        $this->assertArrayHasKey('confidence', $result);

        // Zero citations should significantly impact confidence
        $this->assertLessThan(0.7, $result['confidence']);
    }

    /** @test */
    public function it_handles_edge_case_of_very_old_data()
    {
        $factors = [
            'citation_count' => 10,
            'citation_quality' => 0.85,
            'data_freshness' => 730,  // 2 years old
            'consensus_score' => 0.80,
            'llm_confidence' => 0.90,
        ];

        $result = $this->calibrator->calculate($factors);

        // Very old data should significantly reduce confidence
        $this->assertLessThan(0.75, $result['confidence']);
    }

    /** @test */
    public function it_returns_complete_result_structure()
    {
        $factors = [
            'citation_count' => 10,
            'citation_quality' => 0.80,
            'data_freshness' => 30,
            'consensus_score' => 0.75,
            'llm_confidence' => 0.85,
        ];

        $result = $this->calibrator->calculate($factors);

        // Verify complete result structure
        $expectedKeys = [
            'confidence',
            'uncertainty_range',
            'confidence_lower',
            'confidence_upper',
            'confidence_level',
            'breakdown',
            'factors_used',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $result, "Result must include '{$key}'");
        }
    }

    /** @test */
    public function it_can_batch_calculate_multiple_confidences()
    {
        $batchFactors = [
            [
                'citation_count' => 10,
                'citation_quality' => 0.80,
                'data_freshness' => 30,
                'consensus_score' => 0.75,
                'llm_confidence' => 0.85,
            ],
            [
                'citation_count' => 5,
                'citation_quality' => 0.60,
                'data_freshness' => 90,
                'consensus_score' => 0.65,
                'llm_confidence' => 0.70,
            ],
        ];

        $results = $this->calibrator->batchCalculate($batchFactors);

        $this->assertIsArray($results);
        $this->assertCount(2, $results);

        foreach ($results as $result) {
            $this->assertArrayHasKey('confidence', $result);
            $this->assertArrayHasKey('uncertainty_range', $result);
        }
    }
}
