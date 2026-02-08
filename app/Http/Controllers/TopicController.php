<?php

namespace App\Http\Controllers;

use App\Http\Requests\Topic\AnalyzeTopicRequest;
use App\Models\CourtDecision;
use App\Models\LegalCase;
use App\Modules\HomeSearch\Services\HomeSearchAbuseDetector;
use App\Modules\Topics\Analyzers\DrugChargeAbuseDetector;
use App\Modules\Topics\TopicAnalyzer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * TopicController
 *
 * API endpoints for topic-based prosecutorial abuse analysis.
 *
 * TOPICS SUPPORTED:
 * - drug_charge_severity: Overcharging in drug cases (dealing charges for personal use amounts)
 * - home_search_abuse: Disproportionate home search warrants for minor offenses
 * - bail_denial: Excessive bail denial or unreasonable amounts (planned)
 * - pretrial_detention: Excessive pre-trial detention for minor offenses (planned)
 * - witness_intimidation: Prosecutorial intimidation of defense witnesses (planned)
 *
 * ENDPOINTS:
 * - GET /api/topics - List all available topics
 * - POST /api/topics/{topic}/analyze/{caseId} - Analyze case for specific topic
 * - GET /api/topics/{topic}/statistics - Get statistical analysis for topic
 * - GET /api/topics/{topic}/compare-regions - Compare two regions for topic
 *
 * EXAMPLE QUESTIONS ANSWERED:
 * - "How many dealing charges for <30g cannabis in Osijek in 2025?"
 *   GET /api/topics/drug_charge_severity/statistics?region=Osijek&year=2025
 *
 * - "Is Osijek worse than Zadar for drug overcharging?"
 *   GET /api/topics/drug_charge_severity/compare-regions?region1=Osijek&region2=Zadar&year=2025
 *
 * - "How many home search warrants for misdemeanors in 2025?"
 *   GET /api/topics/home_search_abuse/statistics?year=2025
 *
 * All endpoints return JSON responses with standard structure:
 * {
 *     "success": true|false,
 *     "data": {...},
 *     "error": "..." (only on failure)
 * }
 */
class TopicController extends Controller
{
    public function __construct(
        protected DrugChargeAbuseDetector $drugChargeDetector,
        protected HomeSearchAbuseDetector $homeSearchDetector
    ) {}

    /**
     * List all available topics
     *
     * GET /api/topics
     */
    public function listTopics(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CourtDecision::class);

        Log::info('TopicController: listTopics called', [
            'ip' => $request->ip(),
        ]);

        $topics = TopicAnalyzer::getAvailableTopics();

        return response()->json([
            'success' => true,
            'data' => [
                'topics' => $topics,
                'total_topics' => count($topics),
            ],
        ], 200);
    }

    /**
     * Analyze case for specific topic
     *
     * POST /api/topics/{topic}/analyze/{caseId}
     *
     * Request body (topic-specific):
     *
     * For drug_charge_severity:
     * {
     *     "drug_type": "cannabis",
     *     "amount": 30,
     *     "charged_as": "dealing",
     *     "evidence_of_dealing": ["scales", "baggies"]
     * }
     *
     * For home_search_abuse:
     * {
     *     "offense_type": "misdemeanor",
     *     "search_type": "full_home_search",
     *     "items_sought": ["marijuana", "paraphernalia"],
     *     "items_found": ["20g cannabis"]
     * }
     *
     * @param  string  $topic  Topic name
     * @param  string  $caseId  Case ID or UUID
     */
    public function analyzeTopic(AnalyzeTopicRequest $request, string $topic, string $caseId): JsonResponse
    {
        Log::info('TopicController: analyzeTopic called', [
            'topic' => $topic,
            'case_id' => $caseId,
            'ip' => $request->ip(),
        ]);

        try {
            // Find the case
            $case = $this->findCase($caseId);
            $this->authorize('view', $case);

            // Get validated data
            $validated = $request->validated();

            // Get topic analyzer
            $analyzer = $this->getTopicAnalyzer($topic);

            // Analyze case
            $result = $analyzer->analyzeCase($case, $validated);

            Log::info('TopicController: Analysis completed', [
                'topic' => $topic,
                'case_id' => $caseId,
                'abuse_detected' => $result['overcharge_detected'] ?? $result['abuse_detected'] ?? false,
            ]);

            return response()->json([
                'success' => true,
                'data' => $result,
            ], 200);

        } catch (ValidationException $e) {
            Log::warning('TopicController: Validation failed', [
                'topic' => $topic,
                'case_id' => $caseId,
                'errors' => $e->errors(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('TopicController: Analysis failed', [
                'topic' => $topic,
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
     * Get statistical analysis for topic
     *
     * GET /api/topics/{topic}/statistics
     *
     * Query parameters:
     * - year: int (required) - Year to analyze (e.g., 2025)
     * - region: string (optional) - Region name (e.g., "Osijek")
     * - offense_type: string (optional, for home_search_abuse) - "misdemeanor|kazneno_djelo"
     * - drug_type: string (optional, for drug_charge_severity) - "cannabis|cocaine|heroin"
     *
     * Example:
     * GET /api/topics/drug_charge_severity/statistics?year=2025&region=Osijek
     *
     * @param  string  $topic  Topic name
     */
    public function getStatistics(Request $request, string $topic): JsonResponse
    {
        $this->authorize('viewAny', CourtDecision::class);

        Log::info('TopicController: getStatistics called', [
            'topic' => $topic,
            'query' => $request->query(),
            'ip' => $request->ip(),
        ]);

        try {
            // Validate query parameters
            $validated = $request->validate([
                'year' => 'required|integer|min:2020|max:2030',
                'region' => 'nullable|string',
                'offense_type' => 'nullable|string',
                'drug_type' => 'nullable|string',
            ]);

            // Build criteria
            $criteria = [
                'year' => $validated['year'],
            ];

            if (! empty($validated['region'])) {
                $criteria['region'] = $validated['region'];
            }

            if (! empty($validated['offense_type'])) {
                $criteria['offense_type'] = $validated['offense_type'];
            }

            if (! empty($validated['drug_type'])) {
                $criteria['drug_type'] = $validated['drug_type'];
            }

            // Get topic analyzer
            $analyzer = $this->getTopicAnalyzer($topic);

            // Get statistics
            $statistics = $analyzer->getStatistics($criteria);

            Log::info('TopicController: Statistics retrieved', [
                'topic' => $topic,
                'criteria' => $criteria,
                'total_cases' => $statistics['total_cases'] ?? 0,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'topic' => $topic,
                    'criteria' => $criteria,
                    'statistics' => $statistics,
                ],
            ], 200);

        } catch (ValidationException $e) {
            Log::warning('TopicController: Validation failed', [
                'topic' => $topic,
                'errors' => $e->errors(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('TopicController: Statistics retrieval failed', [
                'topic' => $topic,
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
     * Compare two regions for topic
     *
     * GET /api/topics/{topic}/compare-regions
     *
     * Query parameters:
     * - region1: string (required) - First region name (e.g., "Osijek")
     * - region2: string (required) - Second region name (e.g., "Zadar")
     * - year: int (required) - Year to compare (e.g., 2025)
     *
     * Example:
     * GET /api/topics/drug_charge_severity/compare-regions?region1=Osijek&region2=Zadar&year=2025
     *
     * Answers questions like:
     * - "Is Osijek worse than Zadar for drug overcharging?"
     * - "Which region has higher rates of home search abuse?"
     *
     * @param  string  $topic  Topic name
     */
    public function compareRegions(Request $request, string $topic): JsonResponse
    {
        $this->authorize('viewAny', CourtDecision::class);

        Log::info('TopicController: compareRegions called', [
            'topic' => $topic,
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

            // Get topic analyzer
            $analyzer = $this->getTopicAnalyzer($topic);

            // Compare regions
            $comparison = $analyzer->compareRegions($region1, $region2, $year);

            Log::info('TopicController: Regional comparison completed', [
                'topic' => $topic,
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
            Log::warning('TopicController: Validation failed', [
                'topic' => $topic,
                'errors' => $e->errors(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('TopicController: Regional comparison failed', [
                'topic' => $topic,
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
     * Get topic analyzer by name
     *
     * @param  string  $topic  Topic name
     * @return TopicAnalyzer|DrugChargeAbuseDetector|HomeSearchAbuseDetector
     *
     * @throws \Exception If topic not found
     */
    protected function getTopicAnalyzer(string $topic)
    {
        return match ($topic) {
            'drug_charge_severity' => $this->drugChargeDetector,
            'home_search_abuse' => $this->homeSearchDetector,
            default => throw new \Exception("Topic '{$topic}' not supported. Available topics: drug_charge_severity, home_search_abuse"),
        };
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
