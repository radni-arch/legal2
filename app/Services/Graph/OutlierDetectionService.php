<?php

namespace App\Services\Graph;

use InvalidArgumentException;

/**
 * Outlier Detection Service
 *
 * Sprint 8.3: Outlier Prosecution Detection
 *
 * Statistical analysis for identifying prosecutors and courts with anomalous
 * evidence suppression or rights violation rates using z-score analysis.
 *
 * Key Features:
 * - Z-score calculation for outlier detection (threshold |z| > 2.0)
 * - Multi-metric analysis (suppression, violation, appeal overturn rates)
 * - Sample size filtering (minimum 10 cases)
 * - Severity classification (normal, moderate, high, extreme)
 * - Regional disparity detection
 * - Composite scoring across multiple metrics
 */
class OutlierDetectionService
{
    /**
     * Calculate z-score for a value within a population
     *
     * Z-score = (value - mean) / standard_deviation
     *
     * @param  float  $value  Value to analyze
     * @param  array  $population  Population values for comparison
     * @return float Z-score
     */
    public function calculateZScore(float $value, array $population): float
    {
        $stats = $this->calculatePopulationStats($population);

        // Handle edge case: zero standard deviation
        if ($stats['stddev'] == 0) {
            return 0.0;
        }

        return ($value - $stats['mean']) / $stats['stddev'];
    }

    /**
     * Analyze prosecutors for outlier suppression rates
     *
     * @param  array  $prosecutors  Array of prosecutor data with suppression_rate and total_cases
     * @param  int  $minSampleSize  Minimum number of cases for statistical validity
     * @return array Array of outliers with z-scores
     *
     * @throws InvalidArgumentException
     */
    public function analyzeProsecutorOutliers(array $prosecutors, int $minSampleSize = 10): array
    {
        // Validate minimum population size
        if (count($prosecutors) < 2) {
            throw new InvalidArgumentException('Insufficient data for statistical analysis - need at least 2 prosecutors');
        }

        // Filter by minimum sample size
        $filteredProsecutors = array_filter($prosecutors, function ($prosecutor) use ($minSampleSize) {
            return ($prosecutor['total_cases'] ?? 0) >= $minSampleSize;
        });

        if (empty($filteredProsecutors)) {
            return [];
        }

        // Extract suppression rates for population analysis
        $suppressionRates = array_column($filteredProsecutors, 'suppression_rate');

        // Calculate population statistics
        $stats = $this->calculatePopulationStats($suppressionRates);

        // Identify outliers (|z| > 2.0)
        $outliers = [];
        foreach ($filteredProsecutors as $prosecutor) {
            $zScore = $this->calculateZScore($prosecutor['suppression_rate'], $suppressionRates);

            if (abs($zScore) > 2.0) {
                $outliers[] = [
                    'prosecutor_name' => $prosecutor['name'],
                    'prosecutor_id' => $prosecutor['prosecutor_id'] ?? null,
                    'suppression_rate' => $prosecutor['suppression_rate'],
                    'z_score' => $zScore,
                    'severity' => $this->classifySeverity(abs($zScore)),
                    'sample_size' => $prosecutor['total_cases'],
                    'population_mean' => $stats['mean'],
                    'population_stddev' => $stats['stddev'],
                    'confidence_level' => abs($zScore) > 2.0 ? 95 : 90,
                    'detected_at' => now()->toIso8601String(),
                ];
            }
        }

        // Sort by z-score descending (most severe first)
        usort($outliers, function ($a, $b) {
            return $b['z_score'] <=> $a['z_score'];
        });

        return $outliers;
    }

    /**
     * Analyze courts for outlier violation rates
     *
     * @param  array  $courts  Array of court data with violation_rate and total_cases
     * @param  int  $minSampleSize  Minimum number of cases
     * @return array Array of court outliers
     */
    public function analyzeCourtOutliers(array $courts, int $minSampleSize = 10): array
    {
        // Filter by minimum sample size
        $filteredCourts = array_filter($courts, function ($court) use ($minSampleSize) {
            return ($court['total_cases'] ?? 0) >= $minSampleSize;
        });

        if (count($filteredCourts) < 2) {
            return [];
        }

        // Extract violation rates
        $violationRates = array_column($filteredCourts, 'violation_rate');

        // Calculate population statistics
        $stats = $this->calculatePopulationStats($violationRates);

        // Identify outliers
        $outliers = [];
        foreach ($filteredCourts as $court) {
            $zScore = $this->calculateZScore($court['violation_rate'], $violationRates);

            if (abs($zScore) > 2.0) {
                $outliers[] = [
                    'court' => $court['court'],
                    'violation_rate' => $court['violation_rate'],
                    'z_score' => $zScore,
                    'severity' => $this->classifySeverity(abs($zScore)),
                    'sample_size' => $court['total_cases'],
                    'population_mean' => $stats['mean'],
                    'population_stddev' => $stats['stddev'],
                    'detected_at' => now()->toIso8601String(),
                ];
            }
        }

        // Sort by z-score descending
        usort($outliers, function ($a, $b) {
            return $b['z_score'] <=> $a['z_score'];
        });

        return $outliers;
    }

    /**
     * Calculate suppression rate for a prosecutor
     *
     * @param  array  $prosecutorData  Prosecutor data with total_cases and suppressed_evidence_cases
     * @return float Suppression rate (0.0 to 1.0)
     */
    public function calculateSuppressionRate(array $prosecutorData): float
    {
        $totalCases = $prosecutorData['total_cases'] ?? 0;
        $suppressedCases = $prosecutorData['suppressed_evidence_cases'] ?? 0;

        if ($totalCases == 0) {
            return 0.0;
        }

        return $suppressedCases / $totalCases;
    }

    /**
     * Calculate violation rate for a prosecutor
     *
     * @param  string  $prosecutorId  Prosecutor ID
     * @param  array  $data  Data with total_cases and rights_violations
     * @return float Violation rate (0.0 to 1.0)
     */
    public function calculateViolationRate(string $prosecutorId, array $data): float
    {
        $totalCases = $data['total_cases'] ?? 0;
        $violations = $data['rights_violations'] ?? 0;

        if ($totalCases == 0) {
            return 0.0;
        }

        return $violations / $totalCases;
    }

    /**
     * Calculate appeal overturn rate for a prosecutor
     *
     * @param  array  $prosecutorData  Data with total_appeals and overturned_appeals
     * @return float Appeal overturn rate (0.0 to 1.0)
     */
    public function calculateAppealOverturnRate(array $prosecutorData): float
    {
        $totalAppeals = $prosecutorData['total_appeals'] ?? 0;
        $overturnedAppeals = $prosecutorData['overturned_appeals'] ?? 0;

        if ($totalAppeals == 0) {
            return 0.0;
        }

        return $overturnedAppeals / $totalAppeals;
    }

    /**
     * Classify severity level based on z-score
     *
     * @param  float  $zScore  Absolute z-score value
     * @return string Severity level: normal, moderate, high, extreme
     */
    public function classifySeverity(float $zScore): string
    {
        $absZ = abs($zScore);

        if ($absZ < 2.0) {
            return 'normal';
        } elseif ($absZ < 2.5) {
            return 'moderate';
        } elseif ($absZ < 3.0) {
            return 'high';
        } else {
            return 'extreme';
        }
    }

    /**
     * Identify systemic patterns across multiple metrics
     *
     * @param  array  $prosecutorData  Prosecutor data with multiple metrics
     * @return array Analysis result with is_systemic flag
     */
    public function identifySystemicPatterns(array $prosecutorData): array
    {
        $metricsExceeded = 0;
        $flaggedMetrics = [];

        // Define thresholds for each metric (these are high values indicating problems)
        $thresholds = [
            'suppression_rate' => 0.20,      // 20% suppression rate
            'violation_rate' => 0.15,        // 15% violation rate
            'appeal_overturn_rate' => 0.30,  // 30% overturn rate
        ];

        foreach ($thresholds as $metric => $threshold) {
            if (isset($prosecutorData[$metric]) && $prosecutorData[$metric] > $threshold) {
                $metricsExceeded++;
                $flaggedMetrics[] = $metric;
            }
        }

        return [
            'is_systemic' => $metricsExceeded >= 2,
            'metrics_exceeded' => $metricsExceeded,
            'flagged_metrics' => $flaggedMetrics,
            'prosecutor_id' => $prosecutorData['prosecutor_id'] ?? null,
            'prosecutor_name' => $prosecutorData['prosecutor_name'] ?? null,
            'total_cases' => $prosecutorData['total_cases'] ?? 0,
        ];
    }

    /**
     * Calculate population statistics
     *
     * Uses sample standard deviation (Bessel's correction) for better accuracy
     * with small sample sizes, which is typical in outlier detection.
     *
     * @param  array  $values  Numeric values
     * @return array Statistics: mean, stddev, min, max
     */
    public function calculatePopulationStats(array $values): array
    {
        if (empty($values)) {
            return [
                'mean' => 0,
                'stddev' => 0,
                'min' => 0,
                'max' => 0,
            ];
        }

        $count = count($values);
        $mean = array_sum($values) / $count;

        // Calculate sample standard deviation (Bessel's correction: n-1)
        // This provides better estimates for small samples
        $variance = 0;
        foreach ($values as $value) {
            $variance += pow($value - $mean, 2);
        }

        // Use sample variance (n-1) for small samples, population variance (n) for large samples
        $divisor = $count > 30 ? $count : ($count > 1 ? $count - 1 : $count);
        $variance = $variance / $divisor;
        $stddev = sqrt($variance);

        return [
            'mean' => $mean,
            'stddev' => $stddev,
            'min' => min($values),
            'max' => max($values),
        ];
    }

    /**
     * Detect regional disparities in violation rates
     *
     * @param  array  $regionalData  Regional data with violation_rate and case_count
     * @return array Array of regions with disparities
     */
    public function detectRegionalDisparities(array $regionalData): array
    {
        if (count($regionalData) < 2) {
            return [];
        }

        // Extract violation rates
        $violationRates = [];
        foreach ($regionalData as $region => $data) {
            $violationRates[] = $data['violation_rate'];
        }

        // Calculate population statistics
        $stats = $this->calculatePopulationStats($violationRates);

        // Identify regions with disparities
        $disparities = [];
        foreach ($regionalData as $region => $data) {
            $zScore = $this->calculateZScore($data['violation_rate'], $violationRates);

            if (abs($zScore) > 2.0) {
                $disparities[] = [
                    'region' => $region,
                    'violation_rate' => $data['violation_rate'],
                    'case_count' => $data['case_count'],
                    'z_score' => $zScore,
                    'severity' => $this->classifySeverity(abs($zScore)),
                    'population_mean' => $stats['mean'],
                    'population_stddev' => $stats['stddev'],
                ];
            }
        }

        return $disparities;
    }

    /**
     * Calculate composite score from multiple z-scores
     *
     * @param  array  $metrics  Array of metric z-scores
     * @return float Composite score (average of z-scores)
     */
    public function calculateCompositeScore(array $metrics): float
    {
        if (empty($metrics)) {
            return 0.0;
        }

        return array_sum($metrics) / count($metrics);
    }
}
