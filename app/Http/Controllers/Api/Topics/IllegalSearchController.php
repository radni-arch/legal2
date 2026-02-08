<?php

namespace App\Http\Controllers\Api\Topics;

use App\Http\Controllers\Controller;
use App\Models\CourtDecision;
use App\Models\LegalCase;
use App\Modules\Topics\Analyzers\IllegalSearchDetector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * IllegalSearchController
 *
 * API endpoints for illegal search and seizure analysis.
 *
 * This controller provides endpoints for:
 * - Analyzing specific cases for search violations
 * - Getting statistics on illegal searches by region/year
 * - Comparing regional search violation rates
 *
 * ENDPOINTS:
 * - POST /api/topics/illegal_search/analyze/{caseId} - Analyze case for search violations
 * - GET /api/topics/illegal_search/statistics - Get search violation statistics
 * - GET /api/topics/illegal_search/compare-regions - Compare two regions
 *
 * EXAMPLE QUESTIONS ANSWERED:
 * - "How many illegal searches in Osijek in 2025?"
 *   GET /api/topics/illegal_search/statistics?region=Osijek&year=2025
 *
 * - "Is Osijek worse than Zagreb for search violations?"
 *   GET /api/topics/illegal_search/compare-regions?region1=Osijek&region2=Zagreb&year=2025
 *
 * - "Does this case have illegal search violations?"
 *   POST /api/topics/illegal_search/analyze/123 with search data
 *
 * All endpoints return JSON responses with standard structure:
 * {
 *     "success": true|false,
 *     "data": {...},
 *     "error": "..." (only on failure)
 * }
 */
class IllegalSearchController extends Controller
{
    public function __construct(
        protected IllegalSearchDetector $detector
    ) {}

    /**
     * Analyze case for illegal search violations
     *
     * POST /api/topics/illegal_search/analyze/{caseId}
     *
     * Request body:
     * {
     *     "has_warrant": true|false,
     *     "search_type": "home|vehicle|person",
     *     "warrant_defects": ["vague description", "wrong address"],
     *     "force_used": ["breaking doors", "terrorizing family"],
     *     "exigency_details": {
     *         "claimed_reason": "emergency",
     *         "actual_emergency": false
     *     },
     *     "scope_violations": ["searched beyond authorization"]
     * }
     *
     * @param  string  $caseId  Case ID or UUID
     */
    public function analyze(Request $request, string $caseId): JsonResponse
    {
        Log::info('IllegalSearchController: analyze called', [
            'case_id' => $caseId,
            'ip' => $request->ip(),
        ]);

        try {
            // Find the case
            $case = $this->findCase($caseId);
            $this->authorize('view', $case);

            // Validate request
            $validated = $request->validate([
                'has_warrant' => 'required|boolean',
                'search_type' => 'required|string|in:home,vehicle,person',
                'warrant_defects' => 'nullable|array',
                'warrant_defects.*' => 'string',
                'force_used' => 'nullable|array',
                'force_used.*' => 'string',
                'exigency_details' => 'nullable|array',
                'exigency_details.claimed_reason' => 'nullable|string',
                'exigency_details.actual_emergency' => 'nullable|boolean',
                'scope_violations' => 'nullable|array',
                'scope_violations.*' => 'string',
            ]);

            // Analyze case
            $result = $this->detector->analyzeCase($case, $validated);

            Log::info('IllegalSearchController: Analysis completed', [
                'case_id' => $caseId,
                'violation_detected' => $result['violation_detected'],
                'violation_severity' => $result['violation_severity'],
            ]);

            return response()->json([
                'success' => true,
                'data' => $result,
            ], 200);

        } catch (ValidationException $e) {
            Log::warning('IllegalSearchController: Validation failed', [
                'case_id' => $caseId,
                'errors' => $e->errors(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('IllegalSearchController: Analysis failed', [
                'case_id' => $caseId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Analysis failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get statistical analysis for illegal searches
     *
     * GET /api/topics/illegal_search/statistics
     *
     * Query parameters:
     * - year: int (required) - Year to analyze (e.g., 2025)
     * - region: string (optional) - Region name (e.g., "Osijek", "Zagreb")
     * - search_type: string (optional) - "home|vehicle|person"
     *
     * Example:
     * GET /api/topics/illegal_search/statistics?year=2025&region=Osijek&search_type=home
     */
    public function statistics(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CourtDecision::class);

        Log::info('IllegalSearchController: statistics called', [
            'query' => $request->query(),
            'ip' => $request->ip(),
        ]);

        try {
            // Validate query parameters
            $validated = $request->validate([
                'year' => 'required|integer|min:2020|max:2030',
                'region' => 'nullable|string',
                'search_type' => 'nullable|string|in:home,vehicle,person',
            ]);

            // Build criteria
            $criteria = [
                'year' => $validated['year'],
            ];

            if (! empty($validated['region'])) {
                $criteria['region'] = $validated['region'];
            }

            if (! empty($validated['search_type'])) {
                $criteria['search_type'] = $validated['search_type'];
            }

            // Get statistics
            $statistics = $this->detector->getStatistics($criteria);

            Log::info('IllegalSearchController: Statistics retrieved', [
                'criteria' => $criteria,
                'total_cases' => $statistics['total_cases'] ?? 0,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'topic' => 'illegal_search',
                    'criteria' => $criteria,
                    'statistics' => $statistics,
                ],
            ], 200);

        } catch (ValidationException $e) {
            Log::warning('IllegalSearchController: Validation failed', [
                'errors' => $e->errors(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('IllegalSearchController: Statistics retrieval failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Statistics retrieval failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Compare two regions for illegal search violations
     *
     * GET /api/topics/illegal_search/compare-regions
     *
     * Query parameters:
     * - region1: string (required) - First region name (e.g., "Osijek")
     * - region2: string (required) - Second region name (e.g., "Zagreb")
     * - year: int (required) - Year to compare (e.g., 2025)
     *
     * Example:
     * GET /api/topics/illegal_search/compare-regions?region1=Osijek&region2=Zagreb&year=2025
     *
     * Answers questions like:
     * - "Is Osijek worse than Zagreb for search violations?"
     * - "Which region has higher rates of illegal searches?"
     */
    public function compareRegions(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CourtDecision::class);

        Log::info('IllegalSearchController: compareRegions called', [
            'query' => $request->query(),
            'ip' => $request->ip(),
        ]);

        try {
            // Validate query parameters
            $validated = $request->validate([
                'region1' => 'required|string',
                'region2' => 'required|string',
                'year' => 'required|integer|min:2020|max:2030',
            ]);

            $region1 = $validated['region1'];
            $region2 = $validated['region2'];
            $year = $validated['year'];

            // Compare regions
            $comparison = $this->detector->compareRegions($region1, $region2, $year);

            Log::info('IllegalSearchController: Regional comparison completed', [
                'region1' => $region1,
                'region2' => $region2,
                'year' => $year,
                'worse_region' => $comparison['worse_region']['worse_region'] ?? 'unknown',
            ]);

            return response()->json([
                'success' => true,
                'data' => $comparison,
            ], 200);

        } catch (ValidationException $e) {
            Log::warning('IllegalSearchController: Validation failed', [
                'errors' => $e->errors(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('IllegalSearchController: Regional comparison failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Regional comparison failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Find case by ID (ULID or numeric)
     *
     * @param  string  $caseId  Case ID (ULID or numeric)
     *
     * @throws \Exception If case not found
     */
    protected function findCase(string $caseId): LegalCase
    {
        // Try numeric ID first
        if (is_numeric($caseId)) {
            $case = LegalCase::find((int) $caseId);
            if ($case) {
                return $case;
            }
        }

        // Try ULID/UUID
        $case = LegalCase::where('id', $caseId)->first();

        if (! $case) {
            throw new \Exception("Case not found: {$caseId}");
        }

        return $case;
    }
}
