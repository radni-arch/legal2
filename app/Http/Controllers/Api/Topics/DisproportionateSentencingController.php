<?php

namespace App\Http\Controllers\Api\Topics;

use App\Http\Controllers\Controller;
use App\Models\LegalCase;
use App\Modules\Topics\Analyzers\DisproportionateSentencingDetector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * DisproportionateSentencingController
 *
 * API endpoints for disproportionate sentencing analysis under Croatian law
 */
class DisproportionateSentencingController extends Controller
{
    public function __construct(
        protected DisproportionateSentencingDetector $detector
    ) {}

    /**
     * Analyze case for disproportionate sentencing
     *
     * POST /api/topics/disproportionate-sentencing/analyze/{caseId}
     */
    public function analyze(Request $request, string $caseId): JsonResponse
    {
        $case = LegalCase::findOrFail($caseId);

        $validator = Validator::make($request->all(), [
            'offense_type' => 'required|string',
            'sentence_months' => 'required|integer|min:0',
            'mitigating_factors' => 'sometimes|array',
            'aggravating_factors' => 'sometimes|array',
            'regional_average' => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        try {
            $sentencingData = [
                'offense_type' => $request->input('offense_type'),
                'sentence_months' => $request->input('sentence_months'),
                'mitigating_factors' => $request->input('mitigating_factors', []),
                'aggravating_factors' => $request->input('aggravating_factors', []),
                'regional_average' => $request->input('regional_average'),
            ];

            $result = $this->detector->analyzeCase($case, $sentencingData);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get statistics for disproportionate sentencing
     *
     * POST /api/topics/disproportionate-sentencing/statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'year' => 'sometimes|integer|min:2000|max:2099',
            'region' => 'sometimes|string',
            'offense_type' => 'sometimes|string',
            'court' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        try {
            $criteria = [
                'year' => $request->input('year', date('Y')),
                'region' => $request->input('region'),
                'offense_type' => $request->input('offense_type'),
                'court' => $request->input('court'),
            ];

            $result = $this->detector->getStatistics($criteria);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Compare regions for disproportionate sentencing
     *
     * POST /api/topics/disproportionate-sentencing/compare-regions
     */
    public function compareRegions(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'region1' => 'required|string',
            'region2' => 'required|string',
            'year' => 'sometimes|integer|min:2000|max:2099',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        try {
            $region1 = $request->input('region1');
            $region2 = $request->input('region2');
            $year = $request->input('year', date('Y'));

            $result = $this->detector->compareRegions($region1, $region2, $year);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
