<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Confidence Calibrator Service
 *
 * Sprint 5.6: Confidence Calibration System
 *
 * Provides multi-factor confidence calculation for AI agents:
 * - Citation count (0.25 weight)
 * - Citation quality (0.30 weight)
 * - Data freshness (0.15 weight)
 * - Consensus across sources (0.20 weight)
 * - LLM confidence (0.10 weight)
 *
 * Calculates calibrated confidence scores with uncertainty ranges.
 */
class ConfidenceCalibrator
{
    /**
     * Factor weights (must sum to 1.0)
     */
    protected array $weights = [
        'citation_count' => 0.25,
        'citation_quality' => 0.30,
        'data_freshness' => 0.15,
        'consensus_score' => 0.20,
        'llm_confidence' => 0.10,
    ];

    /**
     * Default values for optional factors
     */
    protected array $defaults = [
        'data_freshness' => 30,       // 30 days (reasonable default)
        'consensus_score' => 0.50,    // Neutral consensus
        'llm_confidence' => 0.50,     // Neutral LLM confidence
    ];

    /**
     * Citation count normalization parameters
     */
    protected int $maxCitationCount = 20;  // Citations beyond this get max score

    /**
     * Data freshness normalization parameters (in days)
     */
    protected int $maxFreshnessDays = 365;  // 1 year

    /**
     * Get factor weights
     */
    public function getWeights(): array
    {
        return $this->weights;
    }

    /**
     * Calculate calibrated confidence from multiple factors
     *
     * @param  array  $factors  Associative array of factor values
     * @return array Result with confidence, uncertainty, and breakdown
     *
     * @throws InvalidArgumentException
     */
    public function calculate(array $factors): array
    {
        // Validate and normalize factors
        $normalizedFactors = $this->normalizeFactors($factors);

        // Validate input ranges
        $this->validateFactors($normalizedFactors);

        // Calculate weighted confidence
        $confidence = $this->calculateWeightedConfidence($normalizedFactors);

        // Calculate uncertainty range based on factor variance
        $variance = $this->calculateFactorVariance($normalizedFactors);
        $uncertaintyRange = $this->calculateUncertaintyRange($variance);

        // Calculate bounds
        $confidenceLower = max(0, $confidence - $uncertaintyRange);
        $confidenceUpper = min(1, $confidence + $uncertaintyRange);

        // Get confidence level interpretation
        $confidenceLevel = $this->getConfidenceLevel($confidence);

        // Build breakdown
        $breakdown = $this->buildBreakdown($normalizedFactors);

        // Track which factors were actually used
        $factorsUsed = array_keys($normalizedFactors);

        return [
            'confidence' => round($confidence, 4),
            'uncertainty_range' => round($uncertaintyRange, 4),
            'confidence_lower' => round($confidenceLower, 4),
            'confidence_upper' => round($confidenceUpper, 4),
            'confidence_level' => $confidenceLevel,
            'breakdown' => $breakdown,
            'factors_used' => $factorsUsed,
        ];
    }

    /**
     * Batch calculate confidence for multiple factor sets
     *
     * @param  array  $batchFactors  Array of factor arrays
     * @return array Array of calculation results
     */
    public function batchCalculate(array $batchFactors): array
    {
        $results = [];

        foreach ($batchFactors as $factors) {
            $results[] = $this->calculate($factors);
        }

        return $results;
    }

    /**
     * Normalize citation count to [0, 1]
     *
     * @param  int  $count  Number of citations
     * @return float Normalized score
     */
    public function normalizeCitationCount(int $count): float
    {
        if ($count <= 0) {
            return 0.0;
        }

        if ($count >= $this->maxCitationCount) {
            return 1.0;
        }

        // Logarithmic scaling to reward citations with diminishing returns
        // log(count + 1) / log(maxCount + 1)
        return log($count + 1) / log($this->maxCitationCount + 1);
    }

    /**
     * Normalize data freshness (age in days) to [0, 1]
     *
     * @param  int  $days  Age of data in days
     * @return float Normalized score (1.0 = fresh, 0.0 = stale)
     */
    public function normalizeFreshness(int $days): float
    {
        if ($days <= 0) {
            return 1.0;  // Today = perfect freshness
        }

        if ($days >= $this->maxFreshnessDays) {
            return 0.0;  // 1+ year old = stale
        }

        // Exponential decay: fresh^(days/maxDays)
        // This gives: 0 days=1.0, 30 days≈0.92, 90 days≈0.74, 180 days≈0.50, 365 days=0.0
        return 1.0 - ($days / $this->maxFreshnessDays);
    }

    /**
     * Normalize and validate factors
     *
     * @param  array  $factors  Raw factor values
     * @return array Normalized factors
     */
    protected function normalizeFactors(array $factors): array
    {
        $normalized = [];

        // Citation count (required)
        if (! isset($factors['citation_count'])) {
            throw new InvalidArgumentException('citation_count is required');
        }
        $normalized['citation_count'] = $this->normalizeCitationCount($factors['citation_count']);

        // Citation quality (required, already 0-1)
        if (! isset($factors['citation_quality'])) {
            throw new InvalidArgumentException('citation_quality is required');
        }
        $normalized['citation_quality'] = (float) $factors['citation_quality'];

        // Data freshness (optional, normalize from days)
        if (isset($factors['data_freshness'])) {
            $normalized['data_freshness'] = $this->normalizeFreshness((int) $factors['data_freshness']);
        } else {
            $normalized['data_freshness'] = $this->normalizeFreshness($this->defaults['data_freshness']);
        }

        // Consensus score (optional, already 0-1)
        $normalized['consensus_score'] = isset($factors['consensus_score'])
            ? (float) $factors['consensus_score']
            : $this->defaults['consensus_score'];

        // LLM confidence (optional, already 0-1)
        $normalized['llm_confidence'] = isset($factors['llm_confidence'])
            ? (float) $factors['llm_confidence']
            : $this->defaults['llm_confidence'];

        return $normalized;
    }

    /**
     * Validate that all normalized factors are in [0, 1]
     *
     * @param  array  $factors  Normalized factors
     *
     * @throws InvalidArgumentException
     */
    protected function validateFactors(array $factors): void
    {
        foreach ($factors as $key => $value) {
            if ($value < 0 || $value > 1) {
                throw new InvalidArgumentException(
                    "Factor '{$key}' must be between 0 and 1, got: {$value}"
                );
            }
        }
    }

    /**
     * Calculate weighted confidence from normalized factors
     *
     * @param  array  $normalizedFactors  Normalized factors [0, 1]
     * @return float Weighted confidence score
     */
    protected function calculateWeightedConfidence(array $normalizedFactors): float
    {
        $weightedSum = 0.0;

        foreach ($this->weights as $factor => $weight) {
            if (isset($normalizedFactors[$factor])) {
                $weightedSum += $normalizedFactors[$factor] * $weight;
            }
        }

        return $weightedSum;
    }

    /**
     * Calculate variance across normalized factors
     *
     * @param  array  $normalizedFactors  Normalized factors
     * @return float Variance
     */
    protected function calculateFactorVariance(array $normalizedFactors): float
    {
        $values = array_values($normalizedFactors);
        $mean = array_sum($values) / count($values);

        $squaredDiffs = array_map(fn ($value) => pow($value - $mean, 2), $values);
        $variance = array_sum($squaredDiffs) / count($values);

        return $variance;
    }

    /**
     * Calculate uncertainty range based on factor variance
     *
     * Higher variance = higher uncertainty
     *
     * @param  float  $variance  Factor variance
     * @return float Uncertainty range
     */
    protected function calculateUncertaintyRange(float $variance): float
    {
        // Base uncertainty (minimum)
        $baseUncertainty = 0.02;

        // Variance-based uncertainty (0-0.15)
        // sqrt(variance) to convert variance to standard deviation
        $varianceUncertainty = sqrt($variance) * 0.3;

        return $baseUncertainty + $varianceUncertainty;
    }

    /**
     * Get confidence level interpretation
     *
     * @param  float  $confidence  Confidence score
     * @return string Level name
     */
    protected function getConfidenceLevel(float $confidence): string
    {
        if ($confidence >= 0.90) {
            return 'very_high';
        }

        if ($confidence >= 0.75) {
            return 'high';
        }

        if ($confidence >= 0.55) {
            return 'medium';
        }

        if ($confidence >= 0.35) {
            return 'low';
        }

        return 'very_low';
    }

    /**
     * Build breakdown of factor contributions
     *
     * @param  array  $normalizedFactors  Normalized factors
     * @return array Breakdown with normalized values and weighted contributions
     */
    protected function buildBreakdown(array $normalizedFactors): array
    {
        $breakdown = [];

        foreach ($this->weights as $factor => $weight) {
            if (isset($normalizedFactors[$factor])) {
                $normalized = $normalizedFactors[$factor];
                $contribution = $normalized * $weight;

                $breakdown[$factor] = [
                    'normalized_value' => round($normalized, 4),
                    'weight' => $weight,
                    'weighted_contribution' => round($contribution, 4),
                ];
            }
        }

        return $breakdown;
    }
}
