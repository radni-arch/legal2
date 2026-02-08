<?php

namespace App\Services\Search;

use App\Contracts\Search\SearchOrchestratorInterface;
use App\Services\Contracts\SearchServiceInterface;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates searches across multiple corpora
 *
 * Coordinates searching across different document types (laws, cases, decisions),
 * aggregates results, handles deduplication, and applies pagination.
 */
class SearchOrchestrator implements SearchOrchestratorInterface
{
    /**
     * @var array<string, SearchServiceInterface>
     */
    protected array $searchServices = [];

    public function __construct(
        protected SearchResultAggregator $aggregator,
        protected SearchResultDeduplicator $deduplicator,
        protected ?LawSearchService $lawSearch = null,
        protected ?CaseSearchService $caseSearch = null
    ) {
        // Register available search services
        if ($this->lawSearch) {
            $this->registerSearchService('laws', $this->lawSearch);
        }
        if ($this->caseSearch) {
            $this->registerSearchService('cases', $this->caseSearch);
        }
    }

    /**
     * Register a search service for a specific corpus
     *
     * @param  string  $corpus  Corpus identifier
     * @param  SearchServiceInterface  $service  Search service instance
     */
    public function registerSearchService(string $corpus, SearchServiceInterface $service): void
    {
        $this->searchServices[$corpus] = $service;
    }

    /**
     * Perform multi-corpus search with aggregation and deduplication
     *
     * @param  string  $query  Natural language query
     * @param  array  $options  Search options:
     *                          - 'corpora' (array): Corpora to search (default: all available)
     *                          - 'weights' (array): Corpus-specific score weights
     *                          - 'deduplicate' (bool): Enable deduplication (default: true)
     *                          - 'dedup_strategy' (string): 'strict' or 'fuzzy' (default: 'strict')
     *                          - 'page' (int): Page number for pagination (default: 1)
     *                          - 'per_page' (int): Results per page (default: 10)
     *                          - 'threshold' (float): Minimum similarity threshold (default: 0.7)
     *                          - 'limit' (int): Max results per corpus before aggregation (default: 20)
     *                          - 'filters' (array): Corpus-specific filters
     *                          - 'include_stats' (bool): Include aggregation statistics (default: false)
     * @return array Search results with metadata and timing
     */
    public function search(string $query, array $options = []): array
    {
        $startTime = microtime(true);

        // Determine which corpora to search
        $requestedCorpora = $options['corpora'] ?? array_keys($this->searchServices);
        $availableCorpora = $this->filterAvailableCorpora($requestedCorpora);

        if (empty($availableCorpora)) {
            return $this->emptyResult($query, 'No available search services for requested corpora');
        }

        // Extract options
        $weights = $options['weights'] ?? [];
        $deduplicate = $options['deduplicate'] ?? true;
        $dedupStrategy = $options['dedup_strategy'] ?? 'strict';
        $page = max(1, $options['page'] ?? 1);
        $perPage = max(1, min(100, $options['per_page'] ?? 10));
        $includeStats = $options['include_stats'] ?? false;

        // Search each corpus
        $corpusResults = [];
        $timings = [];

        foreach ($availableCorpora as $corpus) {
            $corpusStart = microtime(true);

            try {
                $service = $this->getSearchService($corpus);
                $corpusResults[$corpus] = $service->search($query, $this->prepareCorpusOptions($options));

                $timings[$corpus] = microtime(true) - $corpusStart;

                Log::debug('Corpus search completed', [
                    'corpus' => $corpus,
                    'results_count' => count($corpusResults[$corpus]),
                    'duration' => $timings[$corpus],
                ]);

            } catch (\Exception $e) {
                Log::error('Corpus search failed', [
                    'corpus' => $corpus,
                    'error' => $e->getMessage(),
                ]);
                $corpusResults[$corpus] = [];
                $timings[$corpus] = microtime(true) - $corpusStart;
            }
        }

        // Aggregate results
        $aggregated = $this->aggregator->aggregate($corpusResults, $weights);

        // Deduplicate if enabled
        $beforeDedup = count($aggregated);
        if ($deduplicate && ! empty($aggregated)) {
            $aggregated = $this->deduplicator->deduplicate($aggregated, [
                'strategy' => $dedupStrategy,
                'keep_highest_score' => true,
            ]);
        }
        $afterDedup = count($aggregated);

        // Apply pagination
        $totalResults = count($aggregated);
        $paginatedResults = $this->paginate($aggregated, $page, $perPage);

        // Build response
        $response = [
            'results' => $paginatedResults,
            'metadata' => [
                'query' => $query,
                'total_results' => $totalResults,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => (int) ceil($totalResults / $perPage),
                'corpora_searched' => $availableCorpora,
                'corpora_available' => array_keys($this->searchServices),
            ],
            'timing' => [
                'total_time' => microtime(true) - $startTime,
                'corpus_times' => $timings,
            ],
        ];

        // Include optional statistics
        if ($includeStats) {
            $response['statistics'] = [
                'aggregation' => $this->aggregator->getStatistics($aggregated),
                'deduplication' => [
                    'enabled' => $deduplicate,
                    'strategy' => $dedupStrategy,
                    'before' => $beforeDedup,
                    'after' => $afterDedup,
                    'removed' => $beforeDedup - $afterDedup,
                ],
            ];
        }

        return $response;
    }

    /**
     * Get search service for a specific corpus
     *
     * @param  string  $corpus  Corpus identifier
     *
     * @throws \InvalidArgumentException if corpus not available
     */
    protected function getSearchService(string $corpus): SearchServiceInterface
    {
        if (! isset($this->searchServices[$corpus])) {
            throw new \InvalidArgumentException("Unknown or unavailable corpus: {$corpus}");
        }

        return $this->searchServices[$corpus];
    }

    /**
     * Filter requested corpora to only include available ones
     *
     * @param  array  $requestedCorpora  Corpora requested by user
     * @return array Available corpora from the request
     */
    protected function filterAvailableCorpora(array $requestedCorpora): array
    {
        return array_values(array_intersect(
            $requestedCorpora,
            array_keys($this->searchServices)
        ));
    }

    /**
     * Prepare options for individual corpus search
     *
     * Extracts relevant options and applies sensible defaults
     *
     * @param  array  $options  Original options
     * @return array Corpus-specific options
     */
    protected function prepareCorpusOptions(array $options): array
    {
        return [
            'threshold' => $options['threshold'] ?? 0.7,
            'limit' => $options['limit'] ?? 20,
            'filters' => $options['filters'] ?? [],
            'model' => $options['model'] ?? null,
            'include_metadata' => $options['include_metadata'] ?? true,
            'include_content' => $options['include_content'] ?? true,
        ];
    }

    /**
     * Paginate results
     *
     * @param  array  $results  Results to paginate
     * @param  int  $page  Page number (1-indexed)
     * @param  int  $perPage  Results per page
     * @return array Paginated slice of results
     */
    protected function paginate(array $results, int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;

        return array_slice($results, $offset, $perPage);
    }

    /**
     * Create empty result response
     *
     * @param  string  $query  Original query
     * @param  string  $message  Error message
     * @return array Empty result structure
     */
    protected function emptyResult(string $query, string $message): array
    {
        return [
            'results' => [],
            'metadata' => [
                'query' => $query,
                'total_results' => 0,
                'page' => 1,
                'per_page' => 10,
                'total_pages' => 0,
                'corpora_searched' => [],
                'corpora_available' => array_keys($this->searchServices),
                'message' => $message,
            ],
            'timing' => [
                'total_time' => 0,
            ],
        ];
    }

    /**
     * Get list of available corpora
     *
     * @return array Available corpus identifiers
     */
    public function getAvailableCorpora(): array
    {
        return array_keys($this->searchServices);
    }
}
