<?php

namespace App\Services\Search;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * DecisionSearchService
 *
 * Responsible for searching court decision documents using vector similarity.
 * Extracted from UnifiedSearchService as part of Phase 2 refactoring.
 *
 * Target: 250-300 lines
 */
class DecisionSearchService
{
    /**
     * Cache for table existence checks to avoid repeated schema queries
     * Key: cache key, Value: ['exists' => bool, 'checked_at' => timestamp]
     */
    protected static array $tableExistsCache = [];

    /**
     * How long to cache table existence results (in seconds)
     */
    protected const TABLE_EXISTS_CACHE_TTL = 60;

    /**
     * Create a new DecisionSearchService instance
     *
     * @param  SearchEmbeddingService  $embeddingService  Service for generating embeddings
     */
    public function __construct(
        protected SearchEmbeddingService $embeddingService
    ) {}

    /**
     * Search court decision documents by vector similarity
     *
     * @param  string  $query  The search query text
     * @param  array  $options  Search options:
     *                          - model: embedding model to use (default from config)
     *                          - threshold: minimum similarity score (default: 0.7)
     *                          - limit: max results to return (default: 10)
     *                          - filters: array of filters (court, jurisdiction, decision_type, date_from, date_to)
     * @return array Array of normalized search results
     */
    public function search(string $query, array $options = []): array
    {
        // Check if required tables exist before generating embeddings
        // This avoids wasting OpenAI API calls when tables don't exist
        if (! $this->tablesExist()) {
            Log::warning('Decision search skipped: required tables do not exist', [
                'query' => substr($query, 0, 100),
            ]);

            return [];
        }

        // Extract options
        $model = $options['model'] ?? null;
        $threshold = max(0.0, min(1.0, $options['threshold'] ?? 0.7));
        $limit = max(1, $options['limit'] ?? 10);
        $filters = $options['filters'] ?? [];

        // Generate embedding for query
        $embedding = $this->embeddingService->embedQuery($query, $model);

        // Validate embedding
        if (empty($embedding)) {
            Log::error('Failed to generate embedding for decision search', [
                'query' => $query,
                'model' => $model,
            ]);

            return [];
        }

        // Perform vector search
        return $this->performVectorSearch($embedding, $limit, $threshold, $filters);
    }

    /**
     * Perform vector search on court decision documents
     *
     * @param  array  $queryEmbedding  Query embedding vector
     * @param  int  $limit  Max results to return
     * @param  float  $threshold  Minimum similarity threshold
     * @param  array  $filters  Search filters
     * @return array Normalized search results
     */
    protected function performVectorSearch(
        array $queryEmbedding,
        int $limit,
        float $threshold,
        array $filters
    ): array {
        $driver = DB::connection()->getDriverName();

        if ($driver !== 'pgsql') {
            Log::warning('Non-PostgreSQL driver detected, vector search not supported', [
                'driver' => $driver,
                'service' => 'DecisionSearchService',
            ]);

            return [];
        }

        // Check if required tables exist before attempting search
        if (! $this->tablesExist()) {
            Log::warning('Decision vector search skipped: required tables do not exist', [
                'service' => 'DecisionSearchService',
            ]);

            return [];
        }

        try {
            // Build query with join to court_decisions table
            $query = DB::table('court_decision_documents as cdd')
                ->join('court_decisions as cd', 'cdd.decision_id', '=', 'cd.id');

            // Select fields
            $query->select([
                'cdd.id',
                'cdd.decision_id',
                'cd.case_number',
                'cd.title',
                'cd.court',
                'cd.jurisdiction',
                'cd.decision_date',
                'cd.decision_type',
                'cd.ecli',
                'cdd.content',
                'cdd.metadata',
                'cdd.content_hash',
                'cdd.chunk_index',
                DB::raw("1 - (cdd.embedding <=> '{$this->vectorToString($queryEmbedding)}') as similarity"),
            ]);

            // Apply similarity threshold
            $query->whereRaw(
                "1 - (cdd.embedding <=> '{$this->vectorToString($queryEmbedding)}') >= ?",
                [$threshold]
            );

            // Apply filters
            $this->applyFilters($query, $filters);

            // Order by similarity and limit
            $results = $query
                ->orderByDesc('similarity')
                ->limit($limit)
                ->get();

            // Normalize results
            return $this->normalizeResults($results);
        } catch (\Exception $e) {
            Log::error('Decision vector search failed', [
                'error' => $e->getMessage(),
                'filters' => $filters,
            ]);

            return [];
        }
    }

    /**
     * Apply filters to the query
     *
     * @param  \Illuminate\Database\Query\Builder  $query  Query builder instance
     * @param  array  $filters  Filters to apply
     */
    protected function applyFilters($query, array $filters): void
    {
        // Court filter (LIKE search)
        if (isset($filters['court'])) {
            $escapedValue = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['court']);
            $query->where('cd.court', 'LIKE', "%{$escapedValue}%");
        }

        // Jurisdiction filter (exact match)
        if (isset($filters['jurisdiction'])) {
            $query->where('cd.jurisdiction', '=', $filters['jurisdiction']);
        }

        // Decision type filter (exact match)
        if (isset($filters['decision_type'])) {
            $query->where('cd.decision_type', '=', $filters['decision_type']);
        }

        // Date range filters
        if (isset($filters['date_from'])) {
            $query->where('cd.decision_date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('cd.decision_date', '<=', $filters['date_to']);
        }
    }

    /**
     * Normalize search results to common format
     *
     * @param  \Illuminate\Support\Collection  $results  Raw database results
     * @return array Normalized results
     */
    protected function normalizeResults($results): array
    {
        return $results->map(function ($row) {
            $metadata = is_string($row->metadata) ? json_decode($row->metadata, true) : $row->metadata;

            return [
                'type' => 'decision',
                'id' => $row->id,
                'title' => $row->title ?? "Case {$row->case_number}",
                'snippet' => $this->extractSnippet($row->content, 200),
                'score' => round($row->similarity, 4),
                'metadata' => [
                    'decision_id' => $row->decision_id,
                    'case_number' => $row->case_number,
                    'court' => $row->court,
                    'jurisdiction' => $row->jurisdiction,
                    'decision_date' => $row->decision_date,
                    'decision_type' => $row->decision_type,
                    'ecli' => $row->ecli,
                    'content_hash' => $row->content_hash,
                    'chunk_index' => $row->chunk_index,
                ],
            ];
        })->toArray();
    }

    /**
     * Extract snippet from content
     *
     * @param  string  $content  Full content text
     * @param  int  $maxLength  Maximum snippet length
     * @return string Truncated snippet
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
     *
     * @param  array  $vector  Embedding vector
     * @return string PostgreSQL vector format string
     */
    protected function vectorToString(array $vector): string
    {
        $parts = array_map(function ($v) {
            return rtrim(rtrim(number_format((float) $v, 8, '.', ''), '0'), '.');
        }, $vector);

        return '['.implode(',', $parts).']';
    }

    /**
     * Check if this service supports a given corpus
     *
     * @param  string  $corpus  Corpus name
     * @return bool True if this service handles the corpus
     */
    public function supportsCorpus(string $corpus): bool
    {
        return $corpus === 'decisions';
    }

    /**
     * Get supported filters for this service
     *
     * @return array Array of supported filter names
     */
    public function getSupportedFilters(): array
    {
        return [
            'court',
            'jurisdiction',
            'decision_type',
            'date_from',
            'date_to',
        ];
    }

    /**
     * Check if the required tables exist for decision search with caching
     *
     * Uses a static cache to avoid repeated schema queries within the same process.
     * Cache TTL is configurable via TABLE_EXISTS_CACHE_TTL constant.
     *
     * @return bool True if both tables exist
     */
    protected function tablesExist(): bool
    {
        $cacheKey = 'decision_tables';
        $now = time();

        // Check cache first
        if (isset(self::$tableExistsCache[$cacheKey])) {
            $cached = self::$tableExistsCache[$cacheKey];
            $age = $now - $cached['checked_at'];

            if ($age < self::TABLE_EXISTS_CACHE_TTL) {
                return $cached['exists'];
            }
        }

        // Cache miss or expired - query the database
        try {
            $exists = Schema::hasTable('court_decision_documents')
                && Schema::hasTable('court_decisions');

            // Cache the result
            self::$tableExistsCache[$cacheKey] = [
                'exists' => $exists,
                'checked_at' => $now,
            ];

            return $exists;
        } catch (\Exception $e) {
            Log::debug('Table existence check failed', [
                'service' => 'DecisionSearchService',
                'error' => $e->getMessage(),
            ]);

            // Cache the failure as non-existent
            self::$tableExistsCache[$cacheKey] = [
                'exists' => false,
                'checked_at' => $now,
            ];

            return false;
        }
    }

    /**
     * Clear the table existence cache (useful for testing or after migrations)
     */
    public static function clearTableExistsCache(): void
    {
        self::$tableExistsCache = [];
    }
}
