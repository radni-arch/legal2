<?php

namespace App\Http\Controllers;

use App\Http\Requests\CaseSearchRequest;
use App\Http\Requests\CitationSearchRequest;
use App\Http\Requests\DecisionSearchRequest;
use App\Http\Requests\HybridSearchRequest;
use App\Http\Requests\LawSearchRequest;
use App\Http\Requests\UnifiedSearchRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\ErrorCode;
use App\Models\Law;
use App\Services\UnifiedSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SearchController extends Controller
{
    public function __construct(
        protected UnifiedSearchService $searchService
    ) {}

    /**
     * Log search request with request ID
     */
    protected function logSearchRequest(string $endpoint, array $params): void
    {
        Log::info('Search API Request', [
            'request_id' => request()->header('X-Request-ID'),
            'endpoint' => $endpoint,
            'query' => $params['query'] ?? null,
            'corpora' => $params['corpora'] ?? null,
            'filters' => $params['filters'] ?? [],
            'limit' => $params['limit'] ?? 10,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Handle and log search errors
     */
    protected function handleSearchError(\Exception $e, string $endpoint): JsonResponse
    {
        Log::error('Search API Error', [
            'request_id' => request()->header('X-Request-ID'),
            'endpoint' => $endpoint,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        // Don't expose internal error details in production
        $message = config('app.debug')
            ? $e->getMessage()
            : 'An error occurred while processing your search request';

        $details = config('app.debug') ? [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ] : null;

        return ApiResponse::error(
            ErrorCode::SEARCH_ERROR,
            $message,
            $details,
            500
        );
    }

    /**
     * Execute a search with caching, logging, and error handling
     */
    protected function executeSearch(
        string $endpoint,
        string $cachePrefix,
        array $validated,
        callable $searchCallback
    ): JsonResponse {
        // Log the search request
        $this->logSearchRequest($endpoint, $validated);

        // Generate cache key based on request parameters
        $cacheKey = $cachePrefix.':'.md5(json_encode($validated));
        $wasCached = Cache::has($cacheKey);

        try {
            // Cache results for 5 minutes (300 seconds)
            $results = Cache::remember($cacheKey, 300, $searchCallback);

            return ApiResponse::success($results, null, 200, [
                'cached' => $wasCached,
            ]);
        } catch (\Exception $e) {
            return $this->handleSearchError($e, $endpoint);
        }
    }

    /**
     * Unified search across all legal corpora
     *
     * POST /api/search
     */
    public function search(UnifiedSearchRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Law::class);

        return $this->executeSearch(
            '/api/search',
            'search',
            $request->validated(),
            function () use ($request) {
                $query = $request->input('query');
                $options = [
                    'corpora' => $request->input('corpora', ['laws', 'decisions', 'cases']),
                    'weights' => $request->input('weights', [
                        'laws' => 1.0,
                        'decisions' => 1.0,
                        'cases' => 1.0,
                    ]),
                    'filters' => $request->input('filters', []),
                    'limit' => $request->input('limit', 10),
                    'page' => $request->input('page', 1),
                    'per_page' => $request->input('per_page', $request->input('limit', 10)),
                    'offset' => $request->input('offset'),
                    'threshold' => $request->input('threshold', 0.7),
                    'model' => $request->input('model', config('openai.models.embeddings')),
                    'sort_by' => $request->input('sort_by', 'score'),
                    'sort_order' => $request->input('sort_order', 'desc'),
                    'deduplicate' => $request->input('deduplicate', true),
                ];

                return $this->searchService->search($query, $options);
            }
        );
    }

    /**
     * Search laws only
     *
     * POST /api/search/laws
     */
    public function searchLaws(LawSearchRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Law::class);

        return $this->executeSearch(
            '/api/search/laws',
            'search:laws',
            $request->validated(),
            function () use ($request) {
                return $this->searchService->search($request->input('query'), [
                    'corpora' => ['laws'],
                    'filters' => $request->input('filters', []),
                    'limit' => $request->input('limit', 10),
                    'page' => $request->input('page', 1),
                    'per_page' => $request->input('per_page', $request->input('limit', 10)),
                    'offset' => $request->input('offset'),
                    'threshold' => $request->input('threshold', 0.7),
                    'sort_by' => $request->input('sort_by', 'score'),
                    'sort_order' => $request->input('sort_order', 'desc'),
                    'deduplicate' => $request->input('deduplicate', true),
                ]);
            }
        );
    }

    /**
     * Search court decisions only
     *
     * POST /api/search/decisions
     */
    public function searchDecisions(DecisionSearchRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Law::class);

        return $this->executeSearch(
            '/api/search/decisions',
            'search:decisions',
            $request->validated(),
            function () use ($request) {
                return $this->searchService->search($request->input('query'), [
                    'corpora' => ['decisions'],
                    'filters' => $request->input('filters', []),
                    'limit' => $request->input('limit', 10),
                    'page' => $request->input('page', 1),
                    'per_page' => $request->input('per_page', $request->input('limit', 10)),
                    'offset' => $request->input('offset'),
                    'threshold' => $request->input('threshold', 0.7),
                    'sort_by' => $request->input('sort_by', 'score'),
                    'sort_order' => $request->input('sort_order', 'desc'),
                    'deduplicate' => $request->input('deduplicate', true),
                ]);
            }
        );
    }

    /**
     * Search case documents only
     *
     * POST /api/search/cases
     */
    public function searchCases(CaseSearchRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Law::class);

        return $this->executeSearch(
            '/api/search/cases',
            'search:cases',
            $request->validated(),
            function () use ($request) {
                return $this->searchService->search($request->input('query'), [
                    'corpora' => ['cases'],
                    'filters' => $request->input('filters', []),
                    'limit' => $request->input('limit', 10),
                    'page' => $request->input('page', 1),
                    'per_page' => $request->input('per_page', $request->input('limit', 10)),
                    'offset' => $request->input('offset'),
                    'threshold' => $request->input('threshold', 0.7),
                    'sort_by' => $request->input('sort_by', 'score'),
                    'sort_order' => $request->input('sort_order', 'desc'),
                    'deduplicate' => $request->input('deduplicate', true),
                ]);
            }
        );
    }

    /**
     * Hybrid search (vector + keyword)
     *
     * POST /api/search/hybrid
     */
    public function hybridSearch(HybridSearchRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Law::class);

        return $this->executeSearch(
            '/api/search/hybrid',
            'search:hybrid',
            $request->validated(),
            function () use ($request) {
                return $this->searchService->hybridSearch($request->input('query'), [
                    'corpora' => $request->input('corpora', ['laws', 'decisions', 'cases']),
                    'filters' => $request->input('filters', []),
                    'limit' => $request->input('limit', 10),
                    'page' => $request->input('page', 1),
                    'per_page' => $request->input('per_page', $request->input('limit', 10)),
                    'offset' => $request->input('offset'),
                    'threshold' => $request->input('threshold', 0.7),
                    'sort_by' => $request->input('sort_by', 'score'),
                    'sort_order' => $request->input('sort_order', 'desc'),
                    'deduplicate' => $request->input('deduplicate', true),
                ]);
            }
        );
    }

    /**
     * Search with citation context
     *
     * POST /api/search/with-citations
     */
    public function searchWithCitations(CitationSearchRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Law::class);

        return $this->executeSearch(
            '/api/search/with-citations',
            'search:citations',
            $request->validated(),
            function () use ($request) {
                return $this->searchService->searchWithCitations($request->input('query'), [
                    'corpora' => $request->input('corpora', ['laws', 'decisions', 'cases']),
                    'filters' => $request->input('filters', []),
                    'limit' => $request->input('limit', 10),
                    'page' => $request->input('page', 1),
                    'per_page' => $request->input('per_page', $request->input('limit', 10)),
                    'offset' => $request->input('offset'),
                    'threshold' => $request->input('threshold', 0.7),
                    'sort_by' => $request->input('sort_by', 'score'),
                    'sort_order' => $request->input('sort_order', 'desc'),
                    'deduplicate' => $request->input('deduplicate', true),
                ]);
            }
        );
    }
}
