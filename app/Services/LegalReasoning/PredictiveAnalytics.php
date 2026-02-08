<?php

namespace App\Services\LegalReasoning;

use App\Models\CasePrediction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Predictive Analytics Orchestrator
 *
 * Coordinates outcome prediction, duration estimation, and impact analysis
 */
class PredictiveAnalytics
{
    public function __construct(
        protected OutcomePredictor $outcomePredictor,
        protected DurationEstimator $durationEstimator,
        protected ImpactAnalyzer $impactAnalyzer
    ) {}

    /**
     * Predict case outcome and persist to database
     */
    public function predictOutcome(string $caseId): array
    {
        Log::info('PredictiveAnalytics - Predicting outcome', ['case_id' => $caseId]);

        try {
            // Get prediction from OutcomePredictor
            $prediction = $this->outcomePredictor->predictOutcome($caseId);

            // Persist prediction to database
            $casePrediction = CasePrediction::create([
                'id' => Str::ulid(),
                'case_id' => $caseId,
                'prediction_type' => 'outcome',
                'features' => $prediction['features'],
                'prediction' => [
                    'predicted_outcome' => $prediction['predicted_outcome'],
                    'probability_distribution' => $prediction['probability_distribution'],
                ],
                'confidence' => $prediction['confidence'],
                'model_version' => '1.0.0',
                'similar_cases' => $prediction['similar_cases'],
                'reasoning' => $prediction['reasoning'],
                'predicted_at' => now(),
            ]);

            Log::info('PredictiveAnalytics - Outcome prediction saved', [
                'case_id' => $caseId,
                'prediction_id' => $casePrediction->id,
                'outcome' => $prediction['predicted_outcome'],
                'confidence' => $prediction['confidence'],
            ]);

            return $prediction;
        } catch (\Exception $e) {
            Log::error('PredictiveAnalytics - Outcome prediction failed', [
                'case_id' => $caseId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Estimate case duration and persist to database
     */
    public function estimateDuration(string $caseId): array
    {
        Log::info('PredictiveAnalytics - Estimating duration', ['case_id' => $caseId]);

        try {
            // Get estimation from DurationEstimator
            $estimate = $this->durationEstimator->estimateDuration($caseId);

            // Persist prediction to database
            CasePrediction::create([
                'id' => Str::ulid(),
                'case_id' => $caseId,
                'prediction_type' => 'duration',
                'features' => $estimate['factors'] ?? [],
                'prediction' => [
                    'estimated_days' => $estimate['estimated_days'],
                    'estimated_completion_date' => $estimate['estimated_completion_date'],
                    'confidence_interval' => $estimate['confidence_interval'],
                ],
                'confidence' => 0.7, // Default confidence for duration estimates
                'model_version' => '1.0.0',
                'reasoning' => 'Duration estimate based on historical data and case complexity.',
                'predicted_at' => now(),
            ]);

            return $estimate;
        } catch (\Exception $e) {
            Log::error('PredictiveAnalytics - Duration estimation failed', [
                'case_id' => $caseId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Analyze decision impact
     */
    public function analyzeDecisionImpact(string $decisionId): array
    {
        Log::info('PredictiveAnalytics - Analyzing decision impact', ['decision_id' => $decisionId]);

        try {
            return $this->impactAnalyzer->analyzeDecisionImpact($decisionId);
        } catch (\Exception $e) {
            Log::error('PredictiveAnalytics - Impact analysis failed', [
                'decision_id' => $decisionId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get comprehensive analytics for a case
     */
    public function getComprehensiveAnalytics(string $caseId): array
    {
        Log::info('PredictiveAnalytics - Getting comprehensive analytics', ['case_id' => $caseId]);

        $analytics = [];

        // Outcome prediction
        try {
            $analytics['outcome'] = $this->predictOutcome($caseId);
        } catch (\Exception $e) {
            $analytics['outcome'] = ['error' => $e->getMessage()];
        }

        // Duration estimation
        try {
            $analytics['duration'] = $this->estimateDuration($caseId);
        } catch (\Exception $e) {
            $analytics['duration'] = ['error' => $e->getMessage()];
        }

        return $analytics;
    }

    /**
     * Get historical predictions for a case
     */
    public function getCasePredictionHistory(string $caseId): array
    {
        $predictions = CasePrediction::where('case_id', $caseId)
            ->orderBy('predicted_at', 'desc')
            ->get();

        return [
            'case_id' => $caseId,
            'total_predictions' => $predictions->count(),
            'predictions' => $predictions->toArray(),
        ];
    }

    /**
     * Update actual outcome for accuracy tracking
     */
    public function updateActualOutcome(string $predictionId, array $actualOutcome): void
    {
        $prediction = CasePrediction::findOrFail($predictionId);

        // Calculate accuracy score
        $accuracyScore = $this->calculateAccuracyScore($prediction->prediction, $actualOutcome);

        $prediction->update([
            'actual_outcome' => $actualOutcome,
            'actual_outcome_at' => now(),
            'accuracy_score' => $accuracyScore,
        ]);

        Log::info('PredictiveAnalytics - Actual outcome updated', [
            'prediction_id' => $predictionId,
            'accuracy_score' => $accuracyScore,
        ]);
    }

    /**
     * Calculate accuracy score by comparing prediction vs actual
     */
    protected function calculateAccuracyScore(array $prediction, array $actual): float
    {
        // Simple accuracy calculation - can be enhanced
        if (! isset($prediction['predicted_outcome'], $actual['outcome'])) {
            return 0.0;
        }

        $predictedOutcome = $prediction['predicted_outcome'];
        $actualOutcome = $actual['outcome'];

        // Exact match
        if ($predictedOutcome === $actualOutcome) {
            return 1.0;
        }

        // Partial match (favorable/unfavorable categories)
        $favorable = ['favorable', 'won', 'settled'];
        $unfavorable = ['unfavorable', 'lost'];

        $predictedCategory = in_array($predictedOutcome, $favorable) ? 'favorable' : 'unfavorable';
        $actualCategory = in_array($actualOutcome, $favorable) ? 'favorable' : 'unfavorable';

        if ($predictedCategory === $actualCategory) {
            return 0.5;
        }

        return 0.0;
    }
}
