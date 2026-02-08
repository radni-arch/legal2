<?php

namespace App\Services;

use App\Contracts\Search\UnifiedSearchServiceInterface;
use App\Services\LegalCitations\HrLegalCitationsDetector;
use App\Services\Search\CaseSearchService;
use App\Services\Search\CitationSearchService;
use App\Services\Search\DecisionSearchService;
use App\Services\Search\FullTextSearchService;
use App\Services\Search\LawSearchService;
use App\Services\Search\SearchEmbeddingService;
use App\Services\Search\SearchResultAggregator;
use App\Services\Search\SearchResultDeduplicator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Unified search service across multiple legal corpora:
 * - Laws (from laws table)
 * - Court Decisions (from court_decision_documents table)
 * - Case Documents (from cases_documents table)
 *
 * Orchestrates vector search, full-text search, and citation search
 * via dedicated sub-services. Handles aggregation, deduplication,
 * sorting, pagination, and hybrid search with RRF merging.
 */
class UnifiedSearchService implements UnifiedSearchServiceInterface
{
    public function __construct(
        protected OpenAIService $openai,
        protected HrLegalCitationsDetector $citationDetector,
        protected SearchEmbeddingService $embeddingService,
        protected LawSearchService $lawSearchService,
        protected DecisionSearchService $decisionSearchService,
        protected CaseSearchService $caseSearchService,
        protected SearchResultAggregator $aggregator,
        protected SearchResultDeduplicator $deduplicator,
        protected FullTextSearchService $fullTextSearchService,
        protected CitationSearchService $citationSearchService
    ) {}

    /**
     * Search across all corpora with filters and weights
     *
     * @param  string  $query  The search query text
     * @param  array  $options  Search options:
     *                          - corpora: array of corpus types to search ['laws', 'decisions', 'cases'] (default: all)
     *                          - weights: array of per-corpus weights ['laws' => 1.0, 'decisions' => 1.0, 'cases' => 1.0]
     *                          - filters: array of filters (court, date_from, date_to, jurisdiction, decision_type, etc.)
     *                          - limit: max results per corpus (default: 10)
     *                          - page: page number for pagination (default: 1)
     *                          - per_page: results per page (default: 10)
     *                          - offset: manual offset (overrides page if set)
     *                          - threshold: minimum similarity score (default: 0.7)
     *                          - model: embedding model to use
     *                          - sort_by: 'score' (default) or 'date'
     *                          - sort_order: 'desc' (default) or 'asc'
     *                          - deduplicate: enable result deduplication (default: true)
     * @return array Normalized search results with metadata
     */
    public function search(string $query, array $options = []): array
    {
        $startTime = microtime(true);

        // Extract and validate options with defaults
        $corpora = $options['corpora'] ?? ['laws', 'decisions', 'cases'];
        $weights = $options['weights'] ?? [];
        $filters = $options['filters'] ?? [];
        $limit = max(1, $options['limit'] ?? 10);
        $page = max(1, $options['page'] ?? 1);
        $perPage = max(1, $options['per_page'] ?? $limit);
        $offset = $options['offset'] ?? (($page - 1) * $perPage);
        $threshold = max(0.0, min(1.0, $options['threshold'] ?? 0.7));
        $model = $options['model'] ?? null;
        $sortBy = $options['sort_by'] ?? 'score';
        $sortOrder = $options['sort_order'] ?? 'desc';
        $deduplicate = $options['deduplicate'] ?? true;

        // Validate corpora
        $validCorpora = ['laws', 'decisions', 'cases'];
        $corpora = array_intersect($corpora, $validCorpora);

        if (empty($corpora)) {
            throw new \InvalidArgumentException('At least one valid corpus must be specified');
        }

        // Search each corpus using extracted services
        $corpusResults = [];
        $corpusTiming = [];

        foreach ($corpora as $corpus) {
            try {
                $corpusStart = microtime(true);

                $searchOptions = [
                    'model' => $model,
                    'threshold' => $threshold,
                    'limit' => $limit,
                    'filters' => $filters,
                ];

                $corpusResults[$corpus] = match ($corpus) {
                    'laws' => $this->lawSearchService->search($query, $searchOptions),
                    'decisions' => $this->decisionSearchService->search($query, $searchOptions),
                    'cases' => $this->caseSearchService->search($query, $searchOptions),
                    default => [],
                };

                $corpusTiming[$corpus] = microtime(true) - $corpusStart;
            } catch (\Exception $e) {
                Log::error("Failed to search corpus: {$corpus}", [
                    'error' => $e->getMessage(),
                    'query' => $query,
                ]);
                $corpusResults[$corpus] = [];
                $corpusTiming[$corpus] = 0;
            }
        }

        // Aggregate results with weights using SearchResultAggregator
        $results = $this->aggregator->aggregate($corpusResults, $weights);

        $totalBeforeDedup = count($results);

        // Apply deduplication if enabled using SearchResultDeduplicator
        if ($deduplicate) {
            $results = $this->deduplicator->deduplicate($results);
        }

        // Sort results
        $results = $this->sortResults($results, $sortBy, $sortOrder);

        // Apply pagination
        $totalResults = count($results);
        $results = array_slice($results, $offset, $perPage);

        $totalTime = microtime(true) - $startTime;

        // Log performance if it exceeds threshold
        if ($totalTime > 2.0) {
            Log::warning('Slow search query detected', [
                'query' => $query,
                'total_time' => round($totalTime, 3),
                'corpus_timing' => array_map(fn ($t) => round($t, 3), $corpusTiming),
                'total_results' => $totalResults,
            ]);
        }

        return [
            'success' => true,
            'data' => $results,
            'metadata' => [
                'query' => $query,
                'total_results' => $totalResults,
                'returned_results' => count($results),
                'deduplicated_count' => $deduplicate ? ($totalBeforeDedup - $totalResults) : 0,
                'corpora_searched' => $corpora,
                'filters_applied' => $filters,
                'threshold' => $threshold,
                'weights' => $weights,
                'deduplication_enabled' => $deduplicate,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'offset' => $offset,
                    'total_pages' => $perPage > 0 ? (int) ceil($totalResults / $perPage) : 0,
                ],
                'performance' => [
                    'total_time' => round($totalTime, 3),
                    'corpus_timing' => array_map(fn ($t) => round($t, 3), $corpusTiming),
                ],
            ],
        ];
    }

    /**
     * Sort results by specified field and order
     */
    protected function sortResults(array $results, string $sortBy, string $sortOrder): array
    {
        usort($results, function ($a, $b) use ($sortBy, $sortOrder) {
            $valueA = match ($sortBy) {
                'score' => $a['score'],
                'date' => $this->extractDateForSorting($a),
                default => $a['score'],
            };

            $valueB = match ($sortBy) {
                'score' => $b['score'],
                'date' => $this->extractDateForSorting($b),
                default => $b['score'],
            };

            $comparison = $valueA <=> $valueB;

            return $sortOrder === 'desc' ? -$comparison : $comparison;
        });

        return $results;
    }

    /**
     * Extract date for sorting from result metadata
     * Returns a sortable date string, with null dates sorted to the end
     */
    protected function extractDateForSorting(array $result): string
    {
        $metadata = $result['metadata'] ?? [];

        $date = match ($result['type']) {
            'law' => $metadata['promulgation_date'] ?? $metadata['effective_date'] ?? null,
            'decision' => $metadata['decision_date'] ?? null,
            'case' => $metadata['created_at'] ?? null,
            default => null,
        };

        // Return null dates as '9999-12-31' so they sort to the end (for desc) or beginning (for asc)
        return $date ?? '9999-12-31';
    }

    /**
     * Normalize search result to common format
     */
    protected function normalizeResult(
        string $type,
        string $id,
        string $title,
        string $snippet,
        float $score,
        array $metadata
    ): array {
        return [
            'type' => $type,
            'id' => $id,
            'title' => $title,
            'snippet' => $snippet,
            'score' => round($score, 4),
            'metadata' => $metadata,
        ];
    }

    /**
     * Extract snippet from content
     */
    protected function extractSnippet(string $content, int $maxLength = 200): string
    {
        $content = trim($content);

        if (mb_strlen($content) <= $maxLength) {
            return $content;
        }

        // Try to break at sentence boundary
        $truncated = mb_substr($content, 0, $maxLength);
        $positions = array_filter(
            [mb_strrpos($truncated, '.'), mb_strrpos($truncated, '?'), mb_strrpos($truncated, '!')],
            fn ($v) => $v !== false
        );

        $boundary = empty($positions) ? false : max($positions);

        if ($boundary !== false && $boundary > $maxLength * 0.6) {
            return mb_substr($content, 0, $boundary + 1);
        }

        return $truncated.'...';
    }

    /**
     * Convert vector array to PostgreSQL vector string
     */
    protected function vectorToString(array $vector): string
    {
        $parts = array_map(function ($v) {
            return rtrim(rtrim(number_format((float) $v, 8, '.', ''), '0'), '.');
        }, $vector);

        return '['.implode(',', $parts).']';
    }

    /**
     * Hybrid search: combine vector similarity with full-text search and citation matching
     *
     * Weights: 60% vector, 30% full-text, 10% citation matches
     * Uses Reciprocal Rank Fusion (RRF) to merge results from different search methods
     *
     * @param  string  $query  The search query text
     * @param  array  $options  Search options (same as search() method)
     * @return array Normalized search results with metadata
     */
    public function hybridSearch(string $query, array $options = []): array
    {
        $startTime = microtime(true);

        // Extract and validate options
        $corpora = $options['corpora'] ?? ['laws', 'decisions', 'cases'];
        $filters = $options['filters'] ?? [];
        $limit = max(1, $options['limit'] ?? 10);
        $page = max(1, $options['page'] ?? 1);
        $perPage = max(1, $options['per_page'] ?? $limit);
        $offset = $options['offset'] ?? (($page - 1) * $perPage);
        $threshold = max(0.0, min(1.0, $options['threshold'] ?? 0.7));
        $deduplicate = $options['deduplicate'] ?? true;

        // Validate corpora
        $validCorpora = ['laws', 'decisions', 'cases'];
        $corpora = array_intersect($corpora, $validCorpora);

        if (empty($corpora)) {
            throw new \InvalidArgumentException('At least one valid corpus must be specified');
        }

        $expandedLimit = $limit * 3; // Get more results for better ranking

        // Vector search: call sub-services directly (not search() pipeline)
        $vectorStart = microtime(true);
        $vectorResults = $this->getVectorSearchResults($query, $corpora, $filters, $threshold, $options['model'] ?? null, $expandedLimit);
        $vectorTime = microtime(true) - $vectorStart;

        // Full-text search via dedicated service
        $fulltextStart = microtime(true);
        $fulltextResults = $this->fullTextSearchService->search($query, $corpora, $filters, $expandedLimit);
        $fulltextTime = microtime(true) - $fulltextStart;

        // Citation search via dedicated service
        $citationStart = microtime(true);
        $citationResults = $this->citationSearchService->search($query, $corpora, $filters, $expandedLimit);
        $citationTime = microtime(true) - $citationStart;

        // Apply Reciprocal Rank Fusion (RRF) with weights
        $mergedResults = $this->applyRRF(
            vectorResults: $vectorResults,
            fulltextResults: $fulltextResults,
            citationResults: $citationResults,
            weights: [
                'vector' => 0.60,     // 60% weight
                'fulltext' => 0.30,   // 30% weight
                'citation' => 0.10,   // 10% weight
            ]
        );

        $totalBeforeDedup = count($mergedResults);

        // Apply deduplication if enabled
        if ($deduplicate) {
            $mergedResults = $this->deduplicator->deduplicate($mergedResults);
        }

        // Sort by RRF score
        usort($mergedResults, fn ($a, $b) => $b['score'] <=> $a['score']);

        // Apply pagination
        $totalResults = count($mergedResults);
        $paginatedResults = array_slice($mergedResults, $offset, $perPage);

        $totalTime = microtime(true) - $startTime;

        // Log performance if it exceeds threshold
        if ($totalTime > 2.0) {
            Log::warning('Slow hybrid search query detected', [
                'query' => $query,
                'total_time' => round($totalTime, 3),
                'vector_time' => round($vectorTime, 3),
                'fulltext_time' => round($fulltextTime, 3),
                'citation_time' => round($citationTime, 3),
                'total_results' => $totalResults,
            ]);
        }

        return [
            'success' => true,
            'data' => $paginatedResults,
            'metadata' => [
                'query' => $query,
                'search_type' => 'hybrid',
                'total_results' => $totalResults,
                'returned_results' => count($paginatedResults),
                'deduplicated_count' => $deduplicate ? ($totalBeforeDedup - $totalResults) : 0,
                'corpora_searched' => $corpora,
                'filters_applied' => $filters,
                'threshold' => $threshold,
                'deduplication_enabled' => $deduplicate,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'offset' => $offset,
                    'total_pages' => $perPage > 0 ? (int) ceil($totalResults / $perPage) : 0,
                ],
                'performance' => [
                    'total_time' => round($totalTime, 3),
                    'vector_time' => round($vectorTime, 3),
                    'fulltext_time' => round($fulltextTime, 3),
                    'citation_time' => round($citationTime, 3),
                ],
                'result_counts' => [
                    'vector' => count($vectorResults),
                    'fulltext' => count($fulltextResults),
                    'citation' => count($citationResults),
                ],
            ],
        ];
    }

    /**
     * Get vector search results for hybrid search by calling sub-services directly.
     *
     * This avoids going through the full search() pipeline (which applies
     * aggregation, deduplication, and sorting unnecessarily).
     */
    protected function getVectorSearchResults(
        string $query,
        array $corpora,
        array $filters,
        float $threshold,
        ?string $model,
        int $limit
    ): array {
        $allResults = [];

        $searchOptions = [
            'model' => $model,
            'threshold' => $threshold,
            'limit' => $limit,
            'filters' => $filters,
        ];

        foreach ($corpora as $corpus) {
            try {
                $corpusResults = match ($corpus) {
                    'laws' => $this->lawSearchService->search($query, $searchOptions),
                    'decisions' => $this->decisionSearchService->search($query, $searchOptions),
                    'cases' => $this->caseSearchService->search($query, $searchOptions),
                    default => [],
                };

                $allResults = array_merge($allResults, $corpusResults);
            } catch (\Exception $e) {
                Log::error("Vector search failed for corpus: {$corpus}", [
                    'error' => $e->getMessage(),
                    'query' => $query,
                ]);
            }
        }

        return $allResults;
    }

    /**
     * Apply Reciprocal Rank Fusion (RRF) to merge results from multiple search methods
     *
     * RRF formula: score(doc) = sum(weight / (k + rank(doc)))
     * where k is a constant (typically 60) to reduce impact of high-ranking documents
     *
     * @param  array  $vectorResults  Results from vector search
     * @param  array  $fulltextResults  Results from full-text search
     * @param  array  $citationResults  Results from citation search
     * @param  array  $weights  Weights for each search method
     * @return array Merged and scored results
     */
    protected function applyRRF(
        array $vectorResults,
        array $fulltextResults,
        array $citationResults,
        array $weights
    ): array {
        $k = 60; // RRF constant
        $scoreMap = []; // Maps unique document ID to accumulated RRF score and document data

        // Process vector results
        foreach ($vectorResults as $rank => $result) {
            $uniqueId = $this->getUniqueDocumentId($result);
            $rrfScore = $weights['vector'] / ($k + $rank + 1);

            if (! isset($scoreMap[$uniqueId])) {
                $scoreMap[$uniqueId] = [
                    'document' => $result,
                    'rrf_score' => 0,
                    'sources' => [],
                ];
            }

            $scoreMap[$uniqueId]['rrf_score'] += $rrfScore;
            $scoreMap[$uniqueId]['sources'][] = 'vector';
        }

        // Process full-text results
        foreach ($fulltextResults as $rank => $result) {
            $uniqueId = $this->getUniqueDocumentId($result);
            $rrfScore = $weights['fulltext'] / ($k + $rank + 1);

            if (! isset($scoreMap[$uniqueId])) {
                $scoreMap[$uniqueId] = [
                    'document' => $result,
                    'rrf_score' => 0,
                    'sources' => [],
                ];
            }

            $scoreMap[$uniqueId]['rrf_score'] += $rrfScore;
            $scoreMap[$uniqueId]['sources'][] = 'fulltext';
        }

        // Process citation results
        foreach ($citationResults as $rank => $result) {
            $uniqueId = $this->getUniqueDocumentId($result);
            $rrfScore = $weights['citation'] / ($k + $rank + 1);

            if (! isset($scoreMap[$uniqueId])) {
                $scoreMap[$uniqueId] = [
                    'document' => $result,
                    'rrf_score' => 0,
                    'sources' => [],
                ];
            }

            $scoreMap[$uniqueId]['rrf_score'] += $rrfScore;
            $scoreMap[$uniqueId]['sources'][] = 'citation';
        }

        // Convert score map to array and update document scores
        $mergedResults = [];
        foreach ($scoreMap as $data) {
            $document = $data['document'];
            $document['score'] = round($data['rrf_score'], 4);
            $document['rrf_sources'] = array_unique($data['sources']);
            $mergedResults[] = $document;
        }

        return $mergedResults;
    }

    /**
     * Get unique document identifier for deduplication in RRF
     */
    protected function getUniqueDocumentId(array $result): string
    {
        return $result['type'].'_'.$result['id'];
    }

    /**
     * Apply filters to a query builder
     */
    protected function applyFiltersToQuery($queryBuilder, array $filters, array $filterMappings): void
    {
        foreach ($filters as $filterKey => $filterValue) {
            if (isset($filterMappings[$filterKey])) {
                $mapping = $filterMappings[$filterKey];
                $column = $mapping['column'];
                $operator = $mapping['operator'] ?? '=';

                if ($operator === 'LIKE') {
                    // Escape special LIKE characters (%, _) in user input
                    $escapedValue = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filterValue);
                    $queryBuilder->where($column, 'LIKE', "%{$escapedValue}%");
                } elseif ($operator === '>=') {
                    $queryBuilder->where($column, '>=', $filterValue);
                } elseif ($operator === '<=') {
                    $queryBuilder->where($column, '<=', $filterValue);
                } else {
                    $queryBuilder->where($column, $operator, $filterValue);
                }
            }
        }
    }

    /**
     * Search with citation context
     * Enhances results by including related documents via citations
     *
     * @param  string  $query  The search query text
     * @param  array  $options  Search options (same as search() method)
     * @return array Search results with citation context
     */
    public function searchWithCitations(string $query, array $options = []): array
    {
        $startTime = microtime(true);

        // Perform base search
        $searchResults = $this->search($query, $options);

        // Extract citations from query
        $queryCitations = $this->citationDetector->detectAll($query);

        // Enhance each result with citation information
        $enhancedResults = [];
        $results = $searchResults['data'] ?? [];

        // Batch-fetch content to avoid N+1 queries
        $contentMap = $this->batchFetchContent($results);

        foreach ($results as $result) {
            // Get content from pre-fetched batch
            $contentKey = ($result['type'] ?? '').'_'.($result['id'] ?? '');
            $content = $contentMap[$contentKey] ?? null;
            $resultCitations = $content ? $this->citationDetector->detectAll($content) : null;

            // Add citation analysis
            $result['citation_analysis'] = [
                'has_citations' => $resultCitations && count(array_filter($resultCitations)) > 0,
                'citation_stats' => $resultCitations ? $this->citationDetector->getStatistics($content) : null,
                'extracted_citations' => $resultCitations,
                'matches_query_citations' => $this->matchesCitations($resultCitations, $queryCitations),
            ];

            // For decisions, add graph-based citation context if GraphRagOrchestrator is available
            if ($result['type'] === 'decision' && class_exists(\App\Services\Graph\GraphRagOrchestrator::class)) {
                try {
                    $graphService = app(\App\Services\Graph\GraphRagOrchestrator::class);

                    // Get cited laws for this decision
                    $citedLaws = $graphService->getCitedLaws('CourtDecisionDocument', $result['id']);

                    // Get related decisions
                    $relatedDecisions = $graphService->findRelatedDecisions($result['id'], 2, 5);

                    $result['citation_context'] = [
                        'cited_laws' => $citedLaws,
                        'related_decisions' => $relatedDecisions,
                    ];
                } catch (\Exception $e) {
                    Log::warning('Failed to fetch graph citation context', [
                        'result_id' => $result['id'],
                        'error' => $e->getMessage(),
                    ]);
                    $result['citation_context'] = null;
                }
            }

            $enhancedResults[] = $result;
        }

        // Extract base metadata and update with citation info
        $metadata = $searchResults['metadata'];
        $metadata['query_citations'] = $queryCitations;
        $metadata['citation_enhancement_time'] = round((microtime(true) - $startTime - $metadata['performance']['total_time']), 3);

        return [
            'success' => true,
            'data' => $enhancedResults,
            'metadata' => $metadata,
        ];
    }

    /**
     * Batch-fetch content for all results to avoid N+1 queries.
     *
     * @param  array  $results  Array of search results
     * @return array<string, string> Map of "type_id" => content
     */
    protected function batchFetchContent(array $results): array
    {
        $contentMap = [];
        $idsByType = ['law' => [], 'decision' => [], 'case' => []];

        foreach ($results as $result) {
            $type = $result['type'] ?? '';
            $id = $result['id'] ?? null;
            if ($id && isset($idsByType[$type])) {
                $idsByType[$type][] = $id;
            }
        }

        try {
            if (! empty($idsByType['law'])) {
                $rows = DB::table('laws')->whereIn('id', $idsByType['law'])->pluck('content', 'id');
                foreach ($rows as $id => $content) {
                    $contentMap["law_{$id}"] = $content;
                }
            }

            if (! empty($idsByType['decision'])) {
                $rows = DB::table('court_decision_documents')->whereIn('id', $idsByType['decision'])->pluck('content', 'id');
                foreach ($rows as $id => $content) {
                    $contentMap["decision_{$id}"] = $content;
                }
            }

            if (! empty($idsByType['case'])) {
                $rows = DB::table('cases_documents')->whereIn('id', $idsByType['case'])->pluck('content', 'id');
                foreach ($rows as $id => $content) {
                    $contentMap["case_{$id}"] = $content;
                }
            }
        } catch (\Exception $e) {
            Log::debug('Could not batch-fetch content for citation extraction', [
                'error' => $e->getMessage(),
            ]);
        }

        return $contentMap;
    }

    /**
     * Get content from search result for citation extraction
     */
    protected function getContentFromResult(array $result): ?string
    {
        // Try to get full content from database
        try {
            $content = match ($result['type']) {
                'law' => DB::table('laws')->where('id', $result['id'])->value('content'),
                'decision' => DB::table('court_decision_documents')->where('id', $result['id'])->value('content'),
                'case' => DB::table('cases_documents')->where('id', $result['id'])->value('content'),
                default => null,
            };

            return $content;
        } catch (\Exception $e) {
            Log::debug('Could not fetch full content for citation extraction', [
                'result_id' => $result['id'],
                'result_type' => $result['type'],
            ]);

            return null;
        }
    }

    /**
     * Check if result citations match query citations
     */
    protected function matchesCitations(?array $resultCitations, array $queryCitations): array
    {
        if (! $resultCitations || empty(array_filter($queryCitations))) {
            return [];
        }

        $matches = [];

        // Check statute citations
        if (! empty($queryCitations['statutes']) && ! empty($resultCitations['statutes'])) {
            $queryCanonicals = array_column($queryCitations['statutes'], 'canonical');
            $resultCanonicals = array_column($resultCitations['statutes'], 'canonical');
            $matches['statutes'] = array_values(array_intersect($queryCanonicals, $resultCanonicals));
        }

        // Check ECLI citations
        if (! empty($queryCitations['ecli']) && ! empty($resultCitations['ecli'])) {
            $queryEclis = array_column($queryCitations['ecli'], 'canonical');
            $resultEclis = array_column($resultCitations['ecli'], 'canonical');
            $matches['ecli'] = array_values(array_intersect($queryEclis, $resultEclis));
        }

        // Check case number citations
        if (! empty($queryCitations['case_numbers']) && ! empty($resultCitations['case_numbers'])) {
            $queryCases = array_column($queryCitations['case_numbers'], 'canonical');
            $resultCases = array_column($resultCitations['case_numbers'], 'canonical');
            $matches['case_numbers'] = array_values(array_intersect($queryCases, $resultCases));
        }

        return $matches;
    }

    /**
     * Get list of available search corpora.
     *
     * @return array List of corpus names ['laws', 'decisions', 'cases']
     */
    public function getAvailableCorpora(): array
    {
        return ['laws', 'decisions', 'cases'];
    }
}
