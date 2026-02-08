<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UnifiedSearchRequest;
use App\Services\UnifiedSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Unified Search API Controller
 *
 * Provides RESTful API endpoints for cross-vector-store search
 * with result merging and ranking.
 *
 * Supports searching across:
 * - Laws (Croatian legislation)
 * - Court Decisions (odluke.sudovi.hr)
 * - Case Documents (internal case files)
 */
class UnifiedSearchController extends Controller
{
    public function __construct(
        protected UnifiedSearchService $searchService
    ) {}

    /**
     * Search across all corpora with optional filters and pagination.
     */
    public function search(UnifiedSearchRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $query = $validated['query'];

        $options = [
            'corpora' => $validated['corpora'] ?? ['laws', 'decisions', 'cases'],
            'weights' => $validated['weights'] ?? [],
            'filters' => $validated['filters'] ?? [],
            'limit' => $validated['limit'] ?? 10,
            'page' => $validated['page'] ?? 1,
            'per_page' => $validated['per_page'] ?? 10,
            'threshold' => $validated['threshold'] ?? 0.7,
            'sort_by' => $validated['sort_by'] ?? 'score',
            'sort_order' => $validated['sort_order'] ?? 'desc',
            'deduplicate' => $validated['deduplicate'] ?? true,
        ];

        try {
            $results = $this->searchService->search($query, $options);

            return response()->json($results);
        } catch (\Exception $e) {
            Log::error('API search failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'An error occurred while processing your search request.',
            ], 500);
        }
    }
}
