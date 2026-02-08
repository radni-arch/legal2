<?php

namespace App\Http\Controllers\Api\Topics;

use App\Http\Controllers\Controller;
use App\Models\LegalCase;
use App\Modules\Topics\Analyzers\ExcessivePretensionDetector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * ExcessivePretensionController
 *
 * API endpoints for analyzing excessive pretrial detention violations.
 *
 * Provides:
 * - Case-specific detention analysis
 * - Regional detention statistics
 * - Comparative regional analysis
 */
class ExcessivePretensionController extends Controller
{
    protected ExcessivePretensionDetector $detector;

    public function __construct(ExcessivePretensionDetector $detector)
    {
        $this->detector = $detector;
    }

    /**
     * Analyze a case for excessive pretrial detention violations
     *
     * POST /api/topics/excessive_pretension/analyze
     *
     * Request body:
     * {
     *   "case_id": "integer (required)",
     *   "detention_months": "integer (required)",
     *   "offense_severity": "general|serious (required)",
     *   "justification_provided": "boolean (optional)",
     *   "justification_text": "string (optional)",
     *   "monthly_reviews_conducted": "integer (optional)",
     *   "detention_grounds": "array (optional)",
     *   "offense_max_penalty_years": "integer (optional)"
     * }
     */
    public function analyze(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'case_id' => 'required|integer|exists:legal_cases,id',
            'detention_months' => 'required|integer|min:0',
            'offense_severity' => 'required|in:general,serious',
            'justification_provided' => 'boolean',
            'justification_text' => 'string|nullable',
            'monthly_reviews_conducted' => 'integer|min:0',
            'detention_grounds' => 'array',
            'detention_grounds.*' => 'string|in:flight_risk,repeat_risk,evidence_destruction,public_order',
            'offense_max_penalty_years' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $validated = $validator->validated();
            $case = LegalCase::findOrFail($validated['case_id']);

            // Extract detention data
            $detentionData = [
                'detention_months' => $validated['detention_months'],
                'offense_severity' => $validated['offense_severity'],
                'justification_provided' => $validated['justification_provided'] ?? false,
                'justification_text' => $validated['justification_text'] ?? '',
                'monthly_reviews_conducted' => $validated['monthly_reviews_conducted'] ?? 0,
                'detention_grounds' => $validated['detention_grounds'] ?? [],
                'offense_max_penalty_years' => $validated['offense_max_penalty_years'] ?? 0,
            ];

            $analysis = $this->detector->analyzeCase($case, $detentionData);

            return response()->json([
                'success' => true,
                'analysis' => $analysis,
            ]);

        } catch (\Throwable $e) {
            Log::error('[ExcessivePretensionController] Analysis failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'case_id' => $request->input('case_id'),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Analysis failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get statistics for excessive pretrial detention violations
     *
     * GET /api/topics/excessive_pretension/statistics
     *
     * Query parameters:
     * - region: string (optional) - Court region (e.g., "Osijek", "Zadar")
     * - year: integer (optional) - Year to analyze
     * - court: string (optional) - Specific court name
     */
    public function statistics(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'region' => 'string|nullable',
            'year' => 'integer|min:2000|max:'.date('Y'),
            'court' => 'string|nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $criteria = $validator->validated();

            $statistics = $this->detector->getStatistics($criteria);

            return response()->json([
                'success' => true,
                'statistics' => $statistics,
                'criteria' => $criteria,
            ]);

        } catch (\Throwable $e) {
            Log::error('[ExcessivePretensionController] Statistics failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'criteria' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Statistics retrieval failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Compare excessive pretrial detention between two regions
     *
     * GET /api/topics/excessive_pretension/compare-regions
     *
     * Query parameters:
     * - region1: string (required) - First region (e.g., "Osijek")
     * - region2: string (required) - Second region (e.g., "Zadar")
     * - year: integer (required) - Year to compare
     */
    public function compareRegions(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'region1' => 'required|string',
            'region2' => 'required|string',
            'year' => 'required|integer|min:2000|max:'.date('Y'),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $validated = $validator->validated();

            $comparison = $this->detector->compareRegions(
                $validated['region1'],
                $validated['region2'],
                $validated['year']
            );

            return response()->json([
                'success' => true,
                'comparison' => $comparison,
            ]);

        } catch (\Throwable $e) {
            Log::error('[ExcessivePretensionController] Regional comparison failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'region1' => $request->input('region1'),
                'region2' => $request->input('region2'),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Regional comparison failed: '.$e->getMessage(),
            ], 500);
        }
    }
}
