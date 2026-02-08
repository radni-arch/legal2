<?php

namespace App\Http\Controllers;

use App\Http\Requests\Defense\ImproveAccusedStatusRequest;
use App\Models\LegalCase;
use App\Modules\Defence\DefenceOnlyModule;
use Illuminate\Http\JsonResponse;

/**
 * DefenseController
 *
 * API endpoints for defense-focused legal analysis and recommendations
 */
class DefenseController extends Controller
{
    public function __construct(
        protected DefenceOnlyModule $defenseModule
    ) {}

    /**
     * Improve accused status - comprehensive defense analysis
     *
     * POST /api/defense/improve-status/{caseId}
     */
    public function improveAccusedStatus(ImproveAccusedStatusRequest $request, string $caseId): JsonResponse
    {
        try {
            $validated = $request->validated();

            $options = [
                'focus_areas' => $validated['focus_areas'] ?? [],
                'include_mitigating' => $validated['include_mitigating'] ?? true,
                'include_weaknesses' => $validated['include_weaknesses'] ?? true,
            ];

            $result = $this->defenseModule->improveAccusedStatus($caseId, $options);

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
     * Get defense strategy
     *
     * GET /api/defense/strategy/{caseId}
     */
    public function getDefenseStrategy(string $caseId): JsonResponse
    {
        $case = LegalCase::findOrFail($caseId);
        $this->authorize('view', $case);

        try {
            $strategy = $this->defenseModule->generateDefenseStrategy($caseId);

            return response()->json([
                'success' => true,
                'data' => $strategy,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get defense recommendations
     *
     * GET /api/defense/recommendations/{caseId}
     */
    public function getRecommendations(string $caseId): JsonResponse
    {
        $case = LegalCase::findOrFail($caseId);
        $this->authorize('view', $case);

        try {
            $recommendations = $this->defenseModule->getDefenseRecommendations($caseId);

            return response()->json([
                'success' => true,
                'data' => $recommendations,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Analyze prosecution weaknesses
     *
     * GET /api/defense/prosecution-weaknesses/{caseId}
     */
    public function analyzeProsecutionWeaknesses(string $caseId): JsonResponse
    {
        $case = LegalCase::findOrFail($caseId);
        $this->authorize('view', $case);

        try {
            $weaknesses = $this->defenseModule->analyzeProsecutionWeaknesses($caseId);

            return response()->json([
                'success' => true,
                'data' => [
                    'weaknesses' => $weaknesses,
                    'count' => count($weaknesses),
                    'high_severity_count' => count(array_filter($weaknesses, fn ($w) => ($w['severity'] ?? 0) >= 70)),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Identify mitigating factors
     *
     * GET /api/defense/mitigating-factors/{caseId}
     */
    public function getMitigatingFactors(string $caseId): JsonResponse
    {
        $case = LegalCase::findOrFail($caseId);
        $this->authorize('view', $case);

        try {
            $factors = $this->defenseModule->identifyMitigatingFactors($caseId);

            return response()->json([
                'success' => true,
                'data' => [
                    'factors' => $factors,
                    'count' => count($factors),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Assess defense strength
     *
     * GET /api/defense/strength/{caseId}
     */
    public function assessDefenseStrength(string $caseId): JsonResponse
    {
        $case = LegalCase::findOrFail($caseId);
        $this->authorize('view', $case);

        try {
            $strength = $this->defenseModule->assessDefenseStrength($caseId);

            return response()->json([
                'success' => true,
                'data' => $strength,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
