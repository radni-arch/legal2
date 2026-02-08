<?php

namespace App\Services\LegalReasoning;

use App\Exceptions\LegalReasoningException;
use App\Models\LegalCase;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Duration Estimator for Legal Cases
 *
 * Predicts case duration based on:
 * - Historical data from similar cases
 * - Case complexity features
 * - Court backlog analysis
 * - Multi-phase milestone planning
 *
 * Hardened with comprehensive error handling and performance monitoring
 */
class DurationEstimator
{
    public function __construct(
        protected FeatureExtractor $featureExtractor
    ) {}

    /**
     * Estimate case duration based on historical data and complexity
     *
     * @throws LegalReasoningException
     */
    public function estimateDuration(string $caseId): array
    {
        $startTime = microtime(true);

        Log::info('Starting case duration estimation', [
            'case_id' => $caseId,
        ]);

        try {
            // Retrieve case with relationships
            $caseLoadStart = microtime(true);

            try {
                $case = LegalCase::with('documents')->findOrFail($caseId);
                $caseLoadDuration = microtime(true) - $caseLoadStart;

                Log::info('Case loaded successfully', [
                    'case_id' => $caseId,
                    'document_count' => $case->documents->count(),
                    'duration_ms' => round($caseLoadDuration * 1000, 2),
                ]);
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                $caseLoadDuration = microtime(true) - $caseLoadStart;

                Log::error('Case not found', [
                    'case_id' => $caseId,
                    'duration_ms' => round($caseLoadDuration * 1000, 2),
                    'trace' => $e->getTraceAsString(),
                ]);

                throw new LegalReasoningException(
                    "Case not found: {$caseId}",
                    LegalReasoningException::CASE_NOT_FOUND,
                    $e
                );
            } catch (\Throwable $e) {
                $caseLoadDuration = microtime(true) - $caseLoadStart;

                Log::error('Failed to load case', [
                    'case_id' => $caseId,
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'duration_ms' => round($caseLoadDuration * 1000, 2),
                    'trace' => $e->getTraceAsString(),
                ]);

                throw new LegalReasoningException(
                    "Failed to load case {$caseId}: {$e->getMessage()}",
                    LegalReasoningException::INVALID_CASE_DATA,
                    $e
                );
            }

            // Extract case features for analysis
            $featureStart = microtime(true);

            try {
                $features = $this->extractFeatures($case);
                $featureDuration = microtime(true) - $featureStart;

                Log::info('Case features extracted', [
                    'case_id' => $caseId,
                    'complexity_score' => $features['complexity_score'],
                    'document_count' => $features['document_count'],
                    'duration_ms' => round($featureDuration * 1000, 2),
                ]);
            } catch (\Throwable $e) {
                $featureDuration = microtime(true) - $featureStart;

                Log::error('Feature extraction failed', [
                    'case_id' => $caseId,
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'duration_ms' => round($featureDuration * 1000, 2),
                    'trace' => $e->getTraceAsString(),
                ]);

                throw new LegalReasoningException(
                    "Failed to extract features for case {$caseId}: {$e->getMessage()}",
                    LegalReasoningException::FEATURE_EXTRACTION_FAILED,
                    $e
                );
            }

            // Query historical data from similar cases
            $historyStart = microtime(true);

            try {
                $historicalData = $this->getHistoricalDurations([
                    'court' => $case->court,
                    'jurisdiction' => $case->jurisdiction,
                    'case_type' => $features['case_type'],
                ]);
                $historyDuration = microtime(true) - $historyStart;

                Log::info('Historical data retrieved', [
                    'case_id' => $caseId,
                    'historical_cases_count' => count($historicalData),
                    'duration_ms' => round($historyDuration * 1000, 2),
                ]);
            } catch (\Throwable $e) {
                $historyDuration = microtime(true) - $historyStart;

                Log::error('Historical data query failed', [
                    'case_id' => $caseId,
                    'court' => $case->court,
                    'jurisdiction' => $case->jurisdiction,
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'duration_ms' => round($historyDuration * 1000, 2),
                    'trace' => $e->getTraceAsString(),
                ]);

                throw new LegalReasoningException(
                    "Failed to query historical data for case {$caseId}: {$e->getMessage()}",
                    LegalReasoningException::HISTORICAL_DATA_QUERY_FAILED,
                    $e
                );
            }

            // Calculate baseline from historical averages
            $baselineStart = microtime(true);

            try {
                $baseDuration = $this->calculateBaseline($historicalData);
                $baselineDuration = microtime(true) - $baselineStart;

                Log::info('Baseline calculated', [
                    'case_id' => $caseId,
                    'baseline_days' => round($baseDuration),
                    'duration_ms' => round($baselineDuration * 1000, 2),
                ]);
            } catch (\Throwable $e) {
                $baselineDuration = microtime(true) - $baselineStart;

                Log::error('Baseline calculation failed', [
                    'case_id' => $caseId,
                    'historical_count' => count($historicalData),
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'duration_ms' => round($baselineDuration * 1000, 2),
                    'trace' => $e->getTraceAsString(),
                ]);

                throw new LegalReasoningException(
                    "Failed to calculate baseline for case {$caseId}: {$e->getMessage()}",
                    LegalReasoningException::BASELINE_CALCULATION_FAILED,
                    $e
                );
            }

            // Adjust for case complexity
            $complexityStart = microtime(true);

            try {
                $complexityMultiplier = $this->calculateComplexityMultiplier($features);
                $complexityDuration = microtime(true) - $complexityStart;

                Log::info('Complexity multiplier calculated', [
                    'case_id' => $caseId,
                    'multiplier' => round($complexityMultiplier, 2),
                    'duration_ms' => round($complexityDuration * 1000, 2),
                ]);
            } catch (\Throwable $e) {
                $complexityDuration = microtime(true) - $complexityStart;

                Log::error('Complexity calculation failed', [
                    'case_id' => $caseId,
                    'features' => $features,
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'duration_ms' => round($complexityDuration * 1000, 2),
                    'trace' => $e->getTraceAsString(),
                ]);

                throw new LegalReasoningException(
                    "Failed to calculate complexity for case {$caseId}: {$e->getMessage()}",
                    LegalReasoningException::COMPLEXITY_CALCULATION_FAILED,
                    $e
                );
            }

            // Account for court backlog
            $backlogStart = microtime(true);

            try {
                $backlogDelay = $this->estimateBacklog($case->court, $case->jurisdiction);
                $backlogDuration = microtime(true) - $backlogStart;

                Log::info('Backlog estimated', [
                    'case_id' => $caseId,
                    'court' => $case->court,
                    'backlog_delay_days' => round($backlogDelay),
                    'duration_ms' => round($backlogDuration * 1000, 2),
                ]);
            } catch (\Throwable $e) {
                $backlogDuration = microtime(true) - $backlogStart;

                Log::error('Backlog estimation failed', [
                    'case_id' => $caseId,
                    'court' => $case->court,
                    'jurisdiction' => $case->jurisdiction,
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'duration_ms' => round($backlogDuration * 1000, 2),
                    'trace' => $e->getTraceAsString(),
                ]);

                throw new LegalReasoningException(
                    "Failed to estimate backlog for case {$caseId}: {$e->getMessage()}",
                    LegalReasoningException::BACKLOG_ESTIMATION_FAILED,
                    $e
                );
            }

            // Calculate final estimate
            $estimatedDays = ($baseDuration * $complexityMultiplier) + $backlogDelay;

            // Generate milestones
            $milestoneStart = microtime(true);

            try {
                $milestones = $this->estimateMilestones($estimatedDays, $case->filing_date);
                $milestoneDuration = microtime(true) - $milestoneStart;

                Log::info('Milestones generated', [
                    'case_id' => $caseId,
                    'milestone_count' => count($milestones),
                    'duration_ms' => round($milestoneDuration * 1000, 2),
                ]);
            } catch (\Throwable $e) {
                $milestoneDuration = microtime(true) - $milestoneStart;

                Log::error('Milestone generation failed', [
                    'case_id' => $caseId,
                    'estimated_days' => $estimatedDays,
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'duration_ms' => round($milestoneDuration * 1000, 2),
                    'trace' => $e->getTraceAsString(),
                ]);

                throw new LegalReasoningException(
                    "Failed to generate milestones for case {$caseId}: {$e->getMessage()}",
                    LegalReasoningException::MILESTONE_GENERATION_FAILED,
                    $e
                );
            }

            $result = [
                'estimated_days' => round($estimatedDays),
                'estimated_completion_date' => Carbon::parse($case->filing_date)->addDays($estimatedDays)->toDateString(),
                'confidence_interval' => [
                    'min_days' => round($estimatedDays * 0.7),
                    'max_days' => round($estimatedDays * 1.5),
                    'min_date' => Carbon::parse($case->filing_date)->addDays($estimatedDays * 0.7)->toDateString(),
                    'max_date' => Carbon::parse($case->filing_date)->addDays($estimatedDays * 1.5)->toDateString(),
                ],
                'milestones' => $milestones,
                'factors' => [
                    'baseline_days' => round($baseDuration),
                    'complexity_multiplier' => round($complexityMultiplier, 2),
                    'backlog_delay_days' => round($backlogDelay),
                    'historical_cases_analyzed' => count($historicalData),
                ],
                'case_features' => [
                    'case_type' => $features['case_type'],
                    'complexity_score' => $features['complexity_score'],
                    'document_count' => $features['document_count'],
                    'party_count' => $features['party_count'],
                ],
                'comparable_cases' => array_slice($historicalData, 0, 5),
            ];

            $totalDuration = microtime(true) - $startTime;

            Log::info('Case duration estimation completed successfully', [
                'case_id' => $caseId,
                'estimated_days' => $result['estimated_days'],
                'total_duration_ms' => round($totalDuration * 1000, 2),
                'timing_breakdown' => [
                    'case_load_ms' => round($caseLoadDuration * 1000, 2),
                    'feature_extraction_ms' => round($featureDuration * 1000, 2),
                    'historical_query_ms' => round($historyDuration * 1000, 2),
                    'baseline_calc_ms' => round($baselineDuration * 1000, 2),
                    'complexity_calc_ms' => round($complexityDuration * 1000, 2),
                    'backlog_estimate_ms' => round($backlogDuration * 1000, 2),
                    'milestone_gen_ms' => round($milestoneDuration * 1000, 2),
                ],
            ]);

            return $result;

        } catch (LegalReasoningException $e) {
            $totalDuration = microtime(true) - $startTime;

            Log::error('Case duration estimation failed', [
                'case_id' => $caseId,
                'error_code' => $e->getCode(),
                'error' => $e->getMessage(),
                'total_duration_ms' => round($totalDuration * 1000, 2),
            ]);

            throw $e;
        } catch (\Throwable $e) {
            $totalDuration = microtime(true) - $startTime;

            Log::error('Case duration estimation failed with unexpected error', [
                'case_id' => $caseId,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'total_duration_ms' => round($totalDuration * 1000, 2),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new LegalReasoningException(
                "Unexpected error in duration estimation for case {$caseId}: {$e->getMessage()}",
                LegalReasoningException::UNEXPECTED_ERROR,
                $e
            );
        }
    }

    /**
     * Extract relevant features from case for duration estimation
     *
     * @throws LegalReasoningException
     */
    protected function extractFeatures(LegalCase $case): array
    {
        $startTime = microtime(true);

        try {
            // Use FeatureExtractor for comprehensive features
            $extractedFeatures = $this->featureExtractor->getCaseFeatures($case);

            $features = [
                'case_type' => $extractedFeatures['case_type'] ?? 'unknown',
                'complexity_score' => $extractedFeatures['complexity_score'] ?? 0.5,
                'document_count' => $case->documents->count(),
                'party_count' => $this->countParties($case),
                'has_expert_witnesses' => $this->hasExpertWitnesses($case),
                'requires_discovery' => $this->requiresDiscovery($case),
            ];

            $duration = microtime(true) - $startTime;

            Log::debug('Features extracted from case', [
                'case_id' => $case->id,
                'features' => $features,
                'duration_ms' => round($duration * 1000, 2),
            ]);

            return $features;
        } catch (\Throwable $e) {
            $duration = microtime(true) - $startTime;

            Log::error('Feature extraction failed', [
                'case_id' => $case->id,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'duration_ms' => round($duration * 1000, 2),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new LegalReasoningException(
                "Feature extraction failed: {$e->getMessage()}",
                LegalReasoningException::FEATURE_EXTRACTION_FAILED,
                $e
            );
        }
    }

    /**
     * Query historical duration data from similar cases
     *
     * @throws \Exception
     */
    protected function getHistoricalDurations(array $criteria): array
    {
        $startTime = microtime(true);

        try {
            $query = DB::table('cases')
                ->select(
                    'id',
                    'case_number',
                    'court',
                    'jurisdiction',
                    'filing_date',
                    'status',
                    DB::raw('EXTRACT(EPOCH FROM (updated_at - filing_date)) / 86400 as duration_days')
                )
                ->where('status', 'closed')
                ->whereNotNull('filing_date');

            // Apply filters
            if (! empty($criteria['court'])) {
                $query->where('court', $criteria['court']);
            }

            if (! empty($criteria['jurisdiction'])) {
                $query->where('jurisdiction', $criteria['jurisdiction']);
            }

            // Get closed cases from last 5 years for relevance
            $query->where('filing_date', '>=', now()->subYears(5));

            $results = $query
                ->orderBy('filing_date', 'desc')
                ->limit(100)
                ->get()
                ->toArray();

            $duration = microtime(true) - $startTime;

            Log::debug('Historical durations retrieved', [
                'criteria' => $criteria,
                'result_count' => count($results),
                'duration_ms' => round($duration * 1000, 2),
            ]);

            return array_map(function ($row) {
                return [
                    'case_id' => $row->id,
                    'case_number' => $row->case_number,
                    'court' => $row->court,
                    'duration_days' => (float) $row->duration_days,
                    'filing_date' => $row->filing_date,
                ];
            }, $results);
        } catch (\Throwable $e) {
            $duration = microtime(true) - $startTime;

            Log::error('Historical data query failed', [
                'criteria' => $criteria,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'duration_ms' => round($duration * 1000, 2),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Calculate baseline duration from historical data
     */
    protected function calculateBaseline(array $historicalData): float
    {
        if (empty($historicalData)) {
            // Default baseline if no historical data (1 year)
            Log::warning('No historical data available, using default baseline', [
                'default_days' => 365,
            ]);

            return 365.0;
        }

        // Calculate weighted average (more recent cases weighted higher)
        $durations = array_column($historicalData, 'duration_days');

        // Remove outliers (beyond 3 standard deviations)
        $mean = array_sum($durations) / count($durations);
        $stdDev = $this->calculateStdDev($durations, $mean);

        $filtered = array_filter($durations, function ($duration) use ($mean, $stdDev) {
            return abs($duration - $mean) <= (3 * $stdDev);
        });

        if (empty($filtered)) {
            Log::debug('All historical data filtered as outliers, using mean', [
                'mean' => $mean,
                'std_dev' => $stdDev,
            ]);

            return $mean;
        }

        // Return median for robustness
        sort($filtered);
        $count = count($filtered);
        $middle = floor($count / 2);

        $median = ($count % 2 === 0)
            ? ($filtered[$middle - 1] + $filtered[$middle]) / 2
            : $filtered[$middle];

        Log::debug('Baseline calculated from historical data', [
            'total_cases' => count($durations),
            'filtered_cases' => $count,
            'mean' => round($mean, 2),
            'median' => round($median, 2),
            'std_dev' => round($stdDev, 2),
        ]);

        return $median;
    }

    /**
     * Calculate complexity multiplier based on case features
     */
    protected function calculateComplexityMultiplier(array $features): float
    {
        $multiplier = 1.0;

        // Base complexity score (0-1 scale from FeatureExtractor)
        $complexityScore = $features['complexity_score'] ?? 0.5;
        $multiplier += ($complexityScore * 0.5); // Up to +50%

        // Document volume factor
        $docCount = $features['document_count'] ?? 0;
        if ($docCount > 100) {
            $multiplier += 0.3;
        } elseif ($docCount > 50) {
            $multiplier += 0.2;
        } elseif ($docCount > 20) {
            $multiplier += 0.1;
        }

        // Multi-party cases take longer
        $partyCount = $features['party_count'] ?? 2;
        if ($partyCount > 2) {
            $multiplier += (($partyCount - 2) * 0.1); // +10% per additional party
        }

        // Expert witnesses extend timeline
        if ($features['has_expert_witnesses'] ?? false) {
            $multiplier += 0.25;
        }

        // Discovery phase adds time
        if ($features['requires_discovery'] ?? true) {
            $multiplier += 0.2;
        }

        // Cap multiplier at reasonable bounds
        $finalMultiplier = min(max($multiplier, 0.5), 3.0);

        Log::debug('Complexity multiplier calculated', [
            'base_multiplier' => 1.0,
            'complexity_score' => $complexityScore,
            'doc_count' => $docCount,
            'party_count' => $partyCount,
            'has_expert_witnesses' => $features['has_expert_witnesses'] ?? false,
            'requires_discovery' => $features['requires_discovery'] ?? true,
            'final_multiplier' => round($finalMultiplier, 2),
        ]);

        return $finalMultiplier;
    }

    /**
     * Estimate court backlog delay
     */
    protected function estimateBacklog(string $court, string $jurisdiction): float
    {
        try {
            // Query recent case volumes to estimate backlog
            $recentCases = DB::table('cases')
                ->where('court', $court)
                ->where('jurisdiction', $jurisdiction)
                ->where('filing_date', '>=', now()->subMonths(6))
                ->count();

            $closedCases = DB::table('cases')
                ->where('court', $court)
                ->where('jurisdiction', $jurisdiction)
                ->where('status', 'closed')
                ->where('filing_date', '>=', now()->subMonths(6))
                ->count();

            // Calculate backlog ratio
            $backlogRatio = $closedCases > 0 ? ($recentCases - $closedCases) / $closedCases : 1.0;

            // Convert to delay days (higher backlog = more delay)
            $baseDelay = 30; // Base 30 days processing time
            $backlogDelay = $baseDelay * (1 + $backlogRatio);

            // Cap at reasonable maximum (1 year)
            $finalDelay = min($backlogDelay, 365);

            Log::debug('Backlog estimated', [
                'court' => $court,
                'jurisdiction' => $jurisdiction,
                'recent_cases' => $recentCases,
                'closed_cases' => $closedCases,
                'backlog_ratio' => round($backlogRatio, 2),
                'estimated_delay_days' => round($finalDelay),
            ]);

            return $finalDelay;
        } catch (\Throwable $e) {
            Log::warning('Backlog estimation failed, using default', [
                'court' => $court,
                'jurisdiction' => $jurisdiction,
                'error' => $e->getMessage(),
                'default_delay' => 30,
            ]);

            // Return default on error
            return 30.0;
        }
    }

    /**
     * Estimate case milestones and phases
     */
    protected function estimateMilestones(float $totalDays, $filingDate): array
    {
        $start = Carbon::parse($filingDate);
        $milestones = [];

        // Phase durations as percentage of total
        $phases = [
            ['name' => 'Initial Filing & Response', 'percentage' => 0.10],
            ['name' => 'Discovery Phase', 'percentage' => 0.30],
            ['name' => 'Motion Practice', 'percentage' => 0.20],
            ['name' => 'Pre-Trial Preparation', 'percentage' => 0.15],
            ['name' => 'Trial/Hearing', 'percentage' => 0.15],
            ['name' => 'Post-Trial/Decision', 'percentage' => 0.10],
        ];

        $cumulativeDays = 0;
        foreach ($phases as $phase) {
            $phaseDays = $totalDays * $phase['percentage'];
            $cumulativeDays += $phaseDays;

            $milestones[] = [
                'phase' => $phase['name'],
                'estimated_duration_days' => round($phaseDays),
                'estimated_date' => $start->copy()->addDays($cumulativeDays)->toDateString(),
                'days_from_filing' => round($cumulativeDays),
                'percentage_complete' => round(($cumulativeDays / $totalDays) * 100),
            ];
        }

        Log::debug('Milestones generated', [
            'total_days' => round($totalDays),
            'milestone_count' => count($milestones),
        ]);

        return $milestones;
    }

    /**
     * Helper: Calculate standard deviation
     */
    protected function calculateStdDev(array $values, float $mean): float
    {
        if (count($values) <= 1) {
            return 0;
        }

        $variance = array_sum(array_map(function ($val) use ($mean) {
            return pow($val - $mean, 2);
        }, $values)) / count($values);

        return sqrt($variance);
    }

    /**
     * Helper: Count parties involved in case
     */
    protected function countParties(LegalCase $case): int
    {
        $count = 0;
        if (! empty($case->client_name)) {
            $count++;
        }
        if (! empty($case->opponent_name)) {
            $count++;
        }

        // Could be extended to check related models for additional parties
        return max($count, 2); // Minimum 2 parties
    }

    /**
     * Helper: Detect if case requires expert witnesses
     */
    protected function hasExpertWitnesses(LegalCase $case): bool
    {
        // Check case description for keywords
        $description = strtolower($case->description ?? '');
        $expertKeywords = ['expert', 'medical', 'technical', 'forensic', 'scientific', 'valuation'];

        foreach ($expertKeywords as $keyword) {
            if (str_contains($description, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Helper: Detect if case requires discovery phase
     */
    protected function requiresDiscovery(LegalCase $case): bool
    {
        // Simple cases may skip extensive discovery
        $caseType = strtolower($case->title ?? '');
        $simpleTypes = ['small claims', 'summary', 'default', 'consent'];

        foreach ($simpleTypes as $type) {
            if (str_contains($caseType, $type)) {
                return false;
            }
        }

        return true; // Most cases require discovery
    }
}
