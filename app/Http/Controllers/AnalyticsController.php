<?php

namespace App\Http\Controllers;

use App\Http\Requests\Analytics\BatchPredictRequest;
use App\Services\LegalReasoning\DurationEstimator;
use App\Services\LegalReasoning\ImpactAnalyzer;
use App\Services\LegalReasoning\PredictiveAnalytics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AnalyticsController extends Controller
{
    public function __construct(
        protected PredictiveAnalytics $predictiveAnalytics,
        protected DurationEstimator $durationEstimator,
        protected ImpactAnalyzer $impactAnalyzer
    ) {}

    /**
     * Generate a unique request ID for tracking
     */
    protected function generateRequestId(): string
    {
        return 'analytics_'.Str::uuid();
    }

    /**
     * Predict case outcome
     *
     * POST /api/analytics/predict-outcome/{caseId}
     */
    public function predictOutcome(string $caseId): JsonResponse
    {
        $requestId = $this->generateRequestId();

        try {
            Log::info('Analytics API - Predict Outcome', [
                'request_id' => $requestId,
                'case_id' => $caseId,
            ]);

            $prediction = $this->predictiveAnalytics->predictOutcome($caseId);

            return response()->json([
                'success' => true,
                'request_id' => $requestId,
                'case_id' => $caseId,
                'data' => $prediction,
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Case not found',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Analytics API Error - Predict Outcome', [
                'request_id' => $requestId,
                'case_id' => $caseId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Estimate case duration
     *
     * POST /api/analytics/estimate-duration/{caseId}
     */
    public function estimateDuration(string $caseId): JsonResponse
    {
        $requestId = $this->generateRequestId();

        try {
            Log::info('Analytics API - Estimate Duration', [
                'request_id' => $requestId,
                'case_id' => $caseId,
            ]);

            $estimate = $this->durationEstimator->estimateDuration($caseId);

            return response()->json([
                'success' => true,
                'request_id' => $requestId,
                'case_id' => $caseId,
                'data' => $estimate,
            ]);

        } catch (\Exception $e) {
            Log::error('Analytics API Error - Estimate Duration', [
                'request_id' => $requestId,
                'case_id' => $caseId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Analyze decision impact
     *
     * POST /api/analytics/analyze-impact/{decisionId}
     */
    public function analyzeImpact(string $decisionId): JsonResponse
    {
        $requestId = $this->generateRequestId();

        try {
            Log::info('Analytics API - Analyze Impact', [
                'request_id' => $requestId,
                'decision_id' => $decisionId,
            ]);

            $impact = $this->impactAnalyzer->analyzeDecisionImpact($decisionId);

            return response()->json([
                'success' => true,
                'request_id' => $requestId,
                'decision_id' => $decisionId,
                'impact' => $impact,
            ]);

        } catch (\Exception $e) {
            Log::error('Analytics API Error - Analyze Impact', [
                'request_id' => $requestId,
                'decision_id' => $decisionId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get comprehensive analytics for a case
     *
     * POST /api/analytics/comprehensive/{caseId}
     */
    public function comprehensiveAnalytics(string $caseId): JsonResponse
    {
        $requestId = $this->generateRequestId();

        try {
            Log::info('Analytics API - Comprehensive', [
                'request_id' => $requestId,
                'case_id' => $caseId,
            ]);

            // Run all analytics in parallel
            $outcome = $this->predictiveAnalytics->predictOutcome($caseId);
            $duration = $this->durationEstimator->estimateDuration($caseId);

            return response()->json([
                'success' => true,
                'request_id' => $requestId,
                'case_id' => $caseId,
                'data' => [
                    'outcome_prediction' => $outcome,
                    'duration_estimate' => $duration,
                    'generated_at' => now()->toIso8601String(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Analytics API Error - Comprehensive', [
                'request_id' => $requestId,
                'case_id' => $caseId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Batch predict outcomes for multiple cases
     *
     * POST /api/analytics/batch-predict
     * Body: { "case_ids": ["01J...", "01K..."] }
     */
    public function batchPredict(BatchPredictRequest $request): JsonResponse
    {
        $requestId = $this->generateRequestId();

        $validated = $request->validated();

        try {
            Log::info('Analytics API - Batch Predict', [
                'request_id' => $requestId,
                'case_count' => count($validated['case_ids']),
            ]);

            $predictions = [];
            $successful = 0;
            $failed = 0;

            foreach ($validated['case_ids'] as $caseId) {
                try {
                    $prediction = $this->predictiveAnalytics->predictOutcome($caseId);
                    $predictions[] = [
                        'case_id' => $caseId,
                        'prediction' => $prediction,
                    ];
                    $successful++;
                } catch (\Exception $e) {
                    $predictions[] = [
                        'case_id' => $caseId,
                        'error' => $e->getMessage(),
                    ];
                    $failed++;
                }
            }

            return response()->json([
                'success' => true,
                'request_id' => $requestId,
                'data' => [
                    'predictions' => $predictions,
                    'total' => count($predictions),
                    'successful' => $successful,
                    'failed' => $failed,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Analytics API Error - Batch Predict', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
