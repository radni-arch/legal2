<?php

namespace App\Http\Controllers;

use App\Models\LegalFactPattern;
use App\Services\LegalReasoning\FactPatternExtractor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Fact Pattern API Controller
 *
 * Endpoints for extracting, managing, and analyzing legal fact patterns.
 * Integrates with the FactPatternExtractor service to convert raw legal
 * narratives into structured, queryable data.
 */
class FactPatternController extends Controller
{
    public function __construct(
        protected FactPatternExtractor $extractor
    ) {}

    /**
     * Generate a unique request ID for tracking
     */
    protected function generateRequestId(): string
    {
        return 'fact_pattern_'.Str::uuid();
    }

    /**
     * Extract fact pattern from narrative
     *
     * POST /api/fact-patterns/extract
     * Body: {
     *   "narrative": "Your legal situation description...",
     *   "save": true|false,
     *   "user_id": 123 (optional, defaults to authenticated user)
     * }
     */
    public function extract(Request $request): JsonResponse
    {
        $requestId = $this->generateRequestId();

        try {
            $validated = $request->validate([
                'narrative' => 'required|string|min:50|max:50000',
                'save' => 'boolean',
                'user_id' => 'sometimes|integer|exists:users,id',
            ]);

            $userId = $validated['user_id'] ?? auth()->id();
            $save = $validated['save'] ?? false;

            Log::info('FactPattern API - Extract', [
                'request_id' => $requestId,
                'user_id' => $userId,
                'save' => $save,
                'narrative_length' => strlen($validated['narrative']),
            ]);

            $result = $this->extractor->extract(
                $validated['narrative'],
                $userId,
                ['save' => $save]
            );

            // If saved, convert model to array for consistent response
            if ($result instanceof LegalFactPattern) {
                $responseData = [
                    'id' => $result->id,
                    'user_id' => $result->user_id,
                    'legal_area' => $result->legal_area,
                    'extraction_confidence' => $result->extraction_confidence,
                    'structured_facts' => $result->structured_facts,
                    'created_at' => $result->created_at->toISOString(),
                ];
            } else {
                $responseData = $result;
            }

            return response()->json([
                'success' => true,
                'request_id' => $requestId,
                'data' => $responseData,
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'request_id' => $requestId,
                'error' => 'Validation failed',
                'details' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('FactPattern API Error - Extract', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'request_id' => $requestId,
                'error' => 'Extraction failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Batch extract multiple narratives
     *
     * POST /api/fact-patterns/batch-extract
     * Body: {
     *   "narratives": {
     *     "case1": "narrative 1...",
     *     "case2": "narrative 2..."
     *   },
     *   "save": true|false
     * }
     */
    public function batchExtract(Request $request): JsonResponse
    {
        $requestId = $this->generateRequestId();

        try {
            $validated = $request->validate([
                'narratives' => 'required|array|min:1|max:50',
                'narratives.*' => 'required|string|min:50|max:50000',
                'save' => 'boolean',
            ]);

            $userId = auth()->id();
            $save = $validated['save'] ?? false;

            Log::info('FactPattern API - Batch Extract', [
                'request_id' => $requestId,
                'user_id' => $userId,
                'count' => count($validated['narratives']),
                'save' => $save,
            ]);

            $result = $this->extractor->batchExtract(
                $validated['narratives'],
                $userId,
                ['save' => $save]
            );

            return response()->json([
                'success' => $result['success'],
                'request_id' => $requestId,
                'summary' => [
                    'total' => $result['total'],
                    'extracted' => $result['extracted'],
                    'failed' => $result['failed'],
                ],
                'results' => $result['results'],
                'errors' => $result['errors'] ?? [],
            ]);

        } catch (\Exception $e) {
            Log::error('FactPattern API Error - Batch Extract', [
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

    /**
     * Get fact pattern by ID
     *
     * GET /api/fact-patterns/{id}
     */
    public function show(string $id): JsonResponse
    {
        $requestId = $this->generateRequestId();

        try {
            $factPattern = $this->extractor->getFactPattern($id);

            if (! $factPattern) {
                return response()->json([
                    'success' => false,
                    'request_id' => $requestId,
                    'error' => 'Fact pattern not found',
                ], 404);
            }

            // Check authorization
            if ($factPattern->user_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'request_id' => $requestId,
                    'error' => 'Unauthorized',
                ], 403);
            }

            return response()->json([
                'success' => true,
                'request_id' => $requestId,
                'data' => [
                    'id' => $factPattern->id,
                    'user_id' => $factPattern->user_id,
                    'raw_narrative' => $factPattern->raw_narrative,
                    'legal_area' => $factPattern->legal_area,
                    'extraction_confidence' => $factPattern->extraction_confidence,
                    'structured_facts' => $factPattern->structured_facts,
                    'created_at' => $factPattern->created_at->toISOString(),
                    'updated_at' => $factPattern->updated_at->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('FactPattern API Error - Show', [
                'request_id' => $requestId,
                'id' => $id,
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
     * List user's fact patterns
     *
     * GET /api/fact-patterns
     * Query params: ?limit=50&legal_area=contract&min_confidence=0.7
     */
    public function index(Request $request): JsonResponse
    {
        $requestId = $this->generateRequestId();

        try {
            $validated = $request->validate([
                'limit' => 'sometimes|integer|min:1|max:100',
                'legal_area' => 'sometimes|string',
                'min_confidence' => 'sometimes|numeric|min:0|max:1',
            ]);

            $userId = auth()->id();
            $limit = $validated['limit'] ?? 50;

            Log::info('FactPattern API - Index', [
                'request_id' => $requestId,
                'user_id' => $userId,
                'limit' => $limit,
            ]);

            // Apply filters
            if (isset($validated['legal_area'])) {
                $patterns = $this->extractor->searchByLegalArea($validated['legal_area'], $limit);
                // Filter by user
                $patterns = $patterns->where('user_id', $userId);
            } elseif (isset($validated['min_confidence'])) {
                $patterns = $this->extractor->getHighConfidencePatterns($validated['min_confidence'], $limit);
                // Filter by user
                $patterns = $patterns->where('user_id', $userId);
            } else {
                $patterns = $this->extractor->getUserFactPatterns($userId, $limit);
            }

            return response()->json([
                'success' => true,
                'request_id' => $requestId,
                'count' => $patterns->count(),
                'data' => $patterns->map(fn ($p) => [
                    'id' => $p->id,
                    'legal_area' => $p->legal_area,
                    'extraction_confidence' => $p->extraction_confidence,
                    'created_at' => $p->created_at->toISOString(),
                    'has_high_confidence' => $p->hasHighConfidence(),
                ]),
            ]);

        } catch (\Exception $e) {
            Log::error('FactPattern API Error - Index', [
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

    /**
     * Compare two fact patterns
     *
     * POST /api/fact-patterns/compare
     * Body: {
     *   "pattern_id_1": "uuid-1",
     *   "pattern_id_2": "uuid-2"
     * }
     */
    public function compare(Request $request): JsonResponse
    {
        $requestId = $this->generateRequestId();

        try {
            $validated = $request->validate([
                'pattern_id_1' => 'required|uuid|exists:legal_fact_patterns,id',
                'pattern_id_2' => 'required|uuid|exists:legal_fact_patterns,id',
            ]);

            Log::info('FactPattern API - Compare', [
                'request_id' => $requestId,
                'pattern_1' => $validated['pattern_id_1'],
                'pattern_2' => $validated['pattern_id_2'],
            ]);

            $pattern1 = $this->extractor->getFactPattern($validated['pattern_id_1']);
            $pattern2 = $this->extractor->getFactPattern($validated['pattern_id_2']);

            // Check authorization
            if ($pattern1->user_id !== auth()->id() || $pattern2->user_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'request_id' => $requestId,
                    'error' => 'Unauthorized',
                ], 403);
            }

            $similarity = $this->extractor->comparePatterns($pattern1, $pattern2);

            return response()->json([
                'success' => true,
                'request_id' => $requestId,
                'patterns' => [
                    'pattern_1' => [
                        'id' => $pattern1->id,
                        'legal_area' => $pattern1->legal_area,
                        'confidence' => $pattern1->extraction_confidence,
                    ],
                    'pattern_2' => [
                        'id' => $pattern2->id,
                        'legal_area' => $pattern2->legal_area,
                        'confidence' => $pattern2->extraction_confidence,
                    ],
                ],
                'similarity' => $similarity,
                'recommendation' => $this->getSimilarityRecommendation($similarity),
            ]);

        } catch (\Exception $e) {
            Log::error('FactPattern API Error - Compare', [
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

    /**
     * Find similar fact patterns
     *
     * POST /api/fact-patterns/{id}/find-similar
     * Query params: ?min_similarity=0.6&limit=10
     */
    public function findSimilar(string $id, Request $request): JsonResponse
    {
        $requestId = $this->generateRequestId();

        try {
            $validated = $request->validate([
                'min_similarity' => 'sometimes|numeric|min:0|max:1',
                'limit' => 'sometimes|integer|min:1|max:50',
            ]);

            $minSimilarity = $validated['min_similarity'] ?? 0.6;
            $limit = $validated['limit'] ?? 10;

            $targetPattern = $this->extractor->getFactPattern($id);

            if (! $targetPattern) {
                return response()->json([
                    'success' => false,
                    'request_id' => $requestId,
                    'error' => 'Fact pattern not found',
                ], 404);
            }

            // Check authorization
            if ($targetPattern->user_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'request_id' => $requestId,
                    'error' => 'Unauthorized',
                ], 403);
            }

            Log::info('FactPattern API - Find Similar', [
                'request_id' => $requestId,
                'target_id' => $id,
                'min_similarity' => $minSimilarity,
            ]);

            // Get candidates from same legal area
            $candidates = $this->extractor->searchByLegalArea(
                $targetPattern->legal_area,
                100
            )->where('user_id', auth()->id());

            $similar = [];
            foreach ($candidates as $candidate) {
                if ($candidate->id === $id) {
                    continue;
                }

                $similarity = $this->extractor->comparePatterns($targetPattern, $candidate);

                if ($similarity['overall_similarity'] >= $minSimilarity) {
                    $similar[] = [
                        'id' => $candidate->id,
                        'legal_area' => $candidate->legal_area,
                        'confidence' => $candidate->extraction_confidence,
                        'similarity_score' => $similarity['overall_similarity'],
                        'same_legal_area' => $similarity['same_legal_area'],
                        'party_overlap' => $similarity['party_overlap'],
                        'legal_issue_overlap' => $similarity['legal_issue_overlap'],
                        'created_at' => $candidate->created_at->toISOString(),
                    ];
                }
            }

            // Sort by similarity (descending)
            usort($similar, fn ($a, $b) => $b['similarity_score'] <=> $a['similarity_score']);

            // Limit results
            $similar = array_slice($similar, 0, $limit);

            return response()->json([
                'success' => true,
                'request_id' => $requestId,
                'target_pattern_id' => $id,
                'count' => count($similar),
                'similar_patterns' => $similar,
            ]);

        } catch (\Exception $e) {
            Log::error('FactPattern API Error - Find Similar', [
                'request_id' => $requestId,
                'id' => $id,
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
     * Get similarity recommendation text
     */
    protected function getSimilarityRecommendation(array $similarity): string
    {
        $score = $similarity['overall_similarity'];

        if ($score >= 0.8) {
            return 'These fact patterns are highly similar. Consider reviewing precedents from one case when working on the other.';
        } elseif ($score >= 0.6) {
            return 'These fact patterns share significant similarities. Some strategies and precedents may be transferable.';
        } elseif ($score >= 0.4) {
            return 'These fact patterns have moderate similarities. Review carefully to identify applicable strategies.';
        } else {
            return 'These fact patterns are largely different. Limited strategy transferability expected.';
        }
    }

    /**
     * Delete a fact pattern
     *
     * DELETE /api/fact-patterns/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        $requestId = $this->generateRequestId();

        try {
            $factPattern = $this->extractor->getFactPattern($id);

            if (! $factPattern) {
                return response()->json([
                    'success' => false,
                    'request_id' => $requestId,
                    'error' => 'Fact pattern not found',
                ], 404);
            }

            // Check authorization
            if ($factPattern->user_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'request_id' => $requestId,
                    'error' => 'Unauthorized',
                ], 403);
            }

            $factPattern->delete();

            Log::info('FactPattern API - Deleted', [
                'request_id' => $requestId,
                'id' => $id,
            ]);

            return response()->json([
                'success' => true,
                'request_id' => $requestId,
                'message' => 'Fact pattern deleted successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('FactPattern API Error - Destroy', [
                'request_id' => $requestId,
                'id' => $id,
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
