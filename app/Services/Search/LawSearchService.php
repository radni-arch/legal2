<?php

namespace App\Services\Search;

use App\Contracts\SearchServiceInterface;
use App\Exceptions\SearchException;
use App\Services\LawVectorStoreService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Simplified Law Search Service
 *
 * Provides semantic vector search for Croatian legal statutes.
 * This is a clean, focused implementation that delegates to
 * specialized services for embedding generation and vector search.
 *
 * Hardened with comprehensive error handling and performance monitoring
 */
class LawSearchService implements SearchServiceInterface
{
    public function __construct(
        protected SearchEmbeddingService $embedder,
        protected LawVectorStoreService $vectorStore
    ) {}

    /**
     * Search laws using semantic vector similarity
     *
     * @throws SearchException
     */
    public function search(string $query, array $options = []): array
    {
        $startTime = microtime(true);

        Log::info('Starting law search', [
            'query' => substr($query, 0, 100),
            'options' => $options,
        ]);

        // Check if vector store is available before generating embeddings
        // This avoids wasting OpenAI API calls when tables don't exist
        if (! $this->vectorStore->isAvailable()) {
            Log::warning('Law search skipped: vector store not available (table missing)', [
                'query' => substr($query, 0, 100),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return [];
        }

        try {
            // Generate query embedding
            $embeddingStart = microtime(true);

            try {
                $embedding = $this->embedder->embedQuery($query, $options['model'] ?? null);
                $embeddingDuration = microtime(true) - $embeddingStart;

                Log::info('Query embedding generated', [
                    'query_length' => strlen($query),
                    'embedding_dimensions' => count($embedding),
                    'model' => $options['model'] ?? 'default',
                    'duration_ms' => round($embeddingDuration * 1000, 2),
                ]);
            } catch (\Throwable $e) {
                $embeddingDuration = microtime(true) - $embeddingStart;

                Log::error('Query embedding generation failed', [
                    'query' => substr($query, 0, 100),
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'duration_ms' => round($embeddingDuration * 1000, 2),
                    'trace' => $e->getTraceAsString(),
                ]);

                throw new SearchException(
                    "Failed to generate embedding for query: {$e->getMessage()}",
                    SearchException::EMBEDDING_FAILED,
                    $e
                );
            }

            // Extract search parameters
            $threshold = $options['threshold'] ?? 0.7;
            $limit = $options['limit'] ?? 10;
            $filters = $options['filters'] ?? [];

            // Perform vector search
            $searchStart = microtime(true);

            try {
                $results = $this->vectorStore->search($embedding, [
                    'threshold' => $threshold,
                    'limit' => $limit,
                    'filters' => $filters,
                ]);
                $searchDuration = microtime(true) - $searchStart;

                Log::info('Vector search completed', [
                    'result_count' => count($results),
                    'threshold' => $threshold,
                    'limit' => $limit,
                    'duration_ms' => round($searchDuration * 1000, 2),
                ]);
            } catch (\Throwable $e) {
                $searchDuration = microtime(true) - $searchStart;

                Log::error('Vector search failed', [
                    'threshold' => $threshold,
                    'limit' => $limit,
                    'filters' => $filters,
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'duration_ms' => round($searchDuration * 1000, 2),
                    'trace' => $e->getTraceAsString(),
                ]);

                throw new SearchException(
                    "Vector search failed: {$e->getMessage()}",
                    SearchException::VECTOR_SEARCH_FAILED,
                    $e
                );
            }

            // Normalize results to standard format
            $normalizeStart = microtime(true);

            try {
                $normalizedResults = $this->normalizeResults($results, 'laws', $options);
                $normalizeDuration = microtime(true) - $normalizeStart;

                Log::debug('Results normalized', [
                    'result_count' => count($normalizedResults),
                    'duration_ms' => round($normalizeDuration * 1000, 2),
                ]);
            } catch (\Throwable $e) {
                $normalizeDuration = microtime(true) - $normalizeStart;

                Log::error('Result normalization failed', [
                    'raw_result_count' => count($results),
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'duration_ms' => round($normalizeDuration * 1000, 2),
                    'trace' => $e->getTraceAsString(),
                ]);

                throw new SearchException(
                    "Failed to normalize search results: {$e->getMessage()}",
                    SearchException::RESULT_NORMALIZATION_FAILED,
                    $e
                );
            }

            $totalDuration = microtime(true) - $startTime;

            Log::info('Law search completed successfully', [
                'query' => substr($query, 0, 100),
                'result_count' => count($normalizedResults),
                'total_duration_ms' => round($totalDuration * 1000, 2),
                'timing_breakdown' => [
                    'embedding_ms' => round($embeddingDuration * 1000, 2),
                    'search_ms' => round($searchDuration * 1000, 2),
                    'normalize_ms' => round($normalizeDuration * 1000, 2),
                ],
            ]);

            return $normalizedResults;

        } catch (SearchException $e) {
            $totalDuration = microtime(true) - $startTime;

            Log::error('Law search failed', [
                'query' => substr($query, 0, 100),
                'error_code' => $e->getCode(),
                'error' => $e->getMessage(),
                'total_duration_ms' => round($totalDuration * 1000, 2),
            ]);

            // Return empty result set on error (graceful degradation)
            return [];
        } catch (\Throwable $e) {
            $totalDuration = microtime(true) - $startTime;

            Log::error('Law search failed with unexpected error', [
                'query' => substr($query, 0, 100),
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'total_duration_ms' => round($totalDuration * 1000, 2),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return empty result set on error (graceful degradation)
            return [];
        }
    }

    /**
     * Perform vector search on law documents
     *
     * @throws SearchException
     */
    protected function performVectorSearch(
        array $queryEmbedding,
        int $limit,
        float $threshold,
        array $filters
    ): array {
        $startTime = microtime(true);

        $driver = DB::connection()->getDriverName();

        if ($driver !== 'pgsql') {
            Log::warning('Non-PostgreSQL driver detected, vector search not supported', [
                'driver' => $driver,
                'service' => 'LawSearchService',
            ]);

            return [];
        }

        try {
            // Build query on laws table
            $query = DB::table('laws');

            // Select fields
            $query->select([
                'id',
                'title',
                'short_title',
                'official_gazette',
                'jurisdiction',
                'country',
                'language',
                'promulgation_date',
                'content',
                'metadata',
                DB::raw("1 - (embedding <=> '{$this->vectorToString($queryEmbedding)}') as similarity"),
            ]);

            // Apply similarity threshold
            $query->whereRaw(
                "1 - (embedding <=> '{$this->vectorToString($queryEmbedding)}') >= ?",
                [$threshold]
            );

            // Apply filters
            $filterStart = microtime(true);
            try {
                $this->applyFilters($query, $filters);
                $filterDuration = microtime(true) - $filterStart;

                Log::debug('Filters applied', [
                    'filter_count' => count($filters),
                    'duration_ms' => round($filterDuration * 1000, 2),
                ]);
            } catch (\Throwable $e) {
                $filterDuration = microtime(true) - $filterStart;

                Log::error('Filter application failed', [
                    'filters' => $filters,
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'duration_ms' => round($filterDuration * 1000, 2),
                    'trace' => $e->getTraceAsString(),
                ]);

                throw new SearchException(
                    "Failed to apply filters: {$e->getMessage()}",
                    SearchException::FILTER_APPLICATION_FAILED,
                    $e
                );
            }

            // Order by similarity and limit
            $results = $query
                ->orderByDesc('similarity')
                ->limit($limit)
                ->get();

            $totalDuration = microtime(true) - $startTime;

            Log::debug('Vector search query executed', [
                'result_count' => count($results),
                'threshold' => $threshold,
                'limit' => $limit,
                'duration_ms' => round($totalDuration * 1000, 2),
            ]);

            // Normalize results
            return $this->normalizeResults($results);

        } catch (SearchException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $totalDuration = microtime(true) - $startTime;

            Log::error('Law vector search failed', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'filters' => $filters,
                'duration_ms' => round($totalDuration * 1000, 2),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new SearchException(
                "Vector search query failed: {$e->getMessage()}",
                SearchException::DATABASE_CONNECTION_FAILED,
                $e
            );
        }
    }

    /**
     * Check if this service supports a specific corpus
     */
    public function supportsCorpus(string $corpus): bool
    {
        return in_array($corpus, ['law', 'laws', 'LawDocument']);
    }

    /**
     * Normalize search results to standardized format
     */
    protected function normalizeResults(array $results, string $corpus = 'laws', array $options = []): array
    {
        $includeMetadata = $options['include_metadata'] ?? true;
        $includeContent = $options['include_content'] ?? true;

        // Map corpus to type (singular form)
        $type = $corpus === 'laws' ? 'law' : $corpus;

        return array_map(function ($result) use ($type, $includeMetadata) {
            $content = $result['content'] ?? ($result->content ?? '');

            $normalized = [
                'id' => $result['id'] ?? ($result->id ?? null),
                'type' => $type,
                'score' => $result['score'] ?? ($result->similarity ?? 0.0),
                'title' => $result['title'] ?? ($result->title ?? ''),
                'snippet' => $this->extractSnippet($content, 200),
            ];

            // Conditionally include metadata
            if ($includeMetadata) {
                $normalized['metadata'] = array_merge(
                    $result['metadata'] ?? ($result->metadata ?? []),
                    $this->extractCorpusFields($result)
                );
            }

            return $normalized;
        }, is_array($results) ? $results : $results->toArray());
    }

    /**
     * Extract corpus-specific fields from result
     */
    protected function extractCorpusFields($result): array
    {
        $fields = [];

        // Convert to array if needed
        if (is_object($result)) {
            $result = (array) $result;
        }

        // Extract law-specific fields if present
        $lawFields = [
            'doc_id', 'title', 'law_number', 'jurisdiction',
            'country', 'language', 'chunk_index',
            'effective_date', 'promulgation_date',
        ];

        foreach ($lawFields as $field) {
            if (isset($result[$field])) {
                $fields[$field] = $result[$field];
            }
        }

        return $fields;
    }

    /**
     * Apply filters to the query
     */
    protected function applyFilters($query, array $filters): void
    {
        // Jurisdiction filter (exact match)
        if (isset($filters['jurisdiction'])) {
            $query->where('jurisdiction', '=', $filters['jurisdiction']);
        }

        // Country filter (exact match)
        if (isset($filters['country'])) {
            $query->where('country', '=', $filters['country']);
        }

        // Language filter (exact match)
        if (isset($filters['language'])) {
            $query->where('language', '=', $filters['language']);
        }

        // Date range filters (promulgation_date)
        if (isset($filters['date_from'])) {
            $query->where('promulgation_date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('promulgation_date', '<=', $filters['date_to']);
        }
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
        $lastPeriod = mb_strrpos($truncated, '.');
        $lastQuestion = mb_strrpos($truncated, '?');
        $lastExclaim = mb_strrpos($truncated, '!');

        $boundary = max($lastPeriod, $lastQuestion, $lastExclaim);

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
     * Get supported filters for this service
     */
    public function getSupportedFilters(): array
    {
        return [
            'jurisdiction',
            'country',
            'language',
            'date_from',
            'date_to',
        ];
    }
}
