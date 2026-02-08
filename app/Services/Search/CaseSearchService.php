<?php

namespace App\Services\Search;

use App\Contracts\SearchServiceInterface;
use App\Services\CaseVectorStoreService;
use Illuminate\Support\Facades\Log;

/**
 * Simplified Case Search Service
 *
 * Provides semantic vector search for internal case documents.
 * This is a clean, focused implementation that delegates to
 * specialized services for embedding generation and vector search.
 */
class CaseSearchService implements SearchServiceInterface
{
    public function __construct(
        protected SearchEmbeddingService $embedder,
        protected CaseVectorStoreService $vectorStore
    ) {}

    /**
     * Search case documents using semantic vector similarity
     *
     * @param  string  $query  Natural language query
     * @param  array  $options  Search options:
     *                          - 'threshold' (float): Minimum similarity threshold (default: 0.7)
     *                          - 'limit' (int): Maximum number of results (default: 10)
     *                          - 'model' (string|null): Embedding model to use (optional)
     *                          - 'filters' (array): Additional filters:
     *                          - 'case_id' (string): Filter by specific case
     *                          - 'source' (string): Filter by document source
     *                          - 'category' (string): Filter by category
     *                          - 'language' (string): Filter by language
     *                          - 'include_metadata' (bool): Include full metadata (default: true)
     *                          - 'include_content' (bool): Include document content (default: true)
     * @return array Array of search results with standardized format
     */
    public function search(string $query, array $options = []): array
    {
        // Check if vector store is available before generating embeddings
        // This avoids wasting OpenAI API calls when tables don't exist
        if (! $this->vectorStore->isAvailable()) {
            Log::warning('Case search skipped: vector store not available (table missing)', [
                'query' => substr($query, 0, 100),
            ]);

            return [];
        }

        try {
            // Generate query embedding
            $embedding = $this->embedder->embedQuery($query, $options['model'] ?? null);

            // Extract search parameters
            $threshold = $options['threshold'] ?? 0.7;
            $limit = $options['limit'] ?? 10;
            $filters = $options['filters'] ?? [];

            // Perform vector search
            $results = $this->vectorStore->search($embedding, [
                'threshold' => $threshold,
                'limit' => $limit,
                'filters' => $filters,
            ]);

            // Normalize results to standard format
            return $this->normalizeResults($results, 'cases', $options);

        } catch (\Exception $e) {
            Log::error('Case search failed', [
                'query' => substr($query, 0, 100),
                'options' => $options,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    // Return empty result set on error

    /**
     * Perform vector search on case documents
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
                'service' => 'CaseSearchService',
            ]);

            return [];
        }

        try {
            // Build query on cases_documents table
            $query = DB::table('cases_documents');

            // Select fields
            $query->select([
                'id',
                'title',
                'category',
                'language',
                'source',
                'content',
                'metadata',
                DB::raw("1 - (embedding_vector <=> '{$this->vectorToString($queryEmbedding)}') as similarity"),
            ]);

            // Apply similarity threshold
            $query->whereRaw(
                "1 - (embedding_vector <=> '{$this->vectorToString($queryEmbedding)}') >= ?",
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
            Log::error('Case vector search failed', [
                'error' => $e->getMessage(),
                'filters' => $filters,
            ]);

            return [];
        }
    }

    /**
     * Check if this service supports a specific corpus
     *
     * @param  string  $corpus  The corpus identifier
     * @return bool True if supported, false otherwise
     */
    public function supportsCorpus(string $corpus): bool
    {
        return in_array($corpus, ['case', 'cases', 'case_document', 'CaseDocument']);
    }

    /**
     * Normalize search results to standardized format
     *
     * @param  array  $results  Raw search results from vector store
     * @param  string  $corpus  Corpus identifier
     * @param  array  $options  Original search options for content/metadata inclusion
     * @return array Normalized results with id, type, score, snippet, metadata
     */
    protected function normalizeResults(array $results, string $corpus, array $options = []): array
    {
        $includeMetadata = $options['include_metadata'] ?? true;
        $includeContent = $options['include_content'] ?? true;

        // Map corpus to type (singular form)
        $type = $corpus === 'cases' ? 'case' : $corpus;

        return array_map(function ($result) use ($type, $includeMetadata) {
            $normalized = [
                'id' => $result['id'],
                'type' => $type,
                'score' => $result['score'] ?? 0.0,
                'title' => $result['title'] ?? '',
                'snippet' => $this->extractSnippet($result['content'] ?? '', 200),
            ];

            // Conditionally include metadata
            if ($includeMetadata) {
                $normalized['metadata'] = array_merge(
                    $result['metadata'] ?? [],
                    $this->extractCorpusFields($result)
                );
            }

            return $normalized;
        }, $results);
    }

    /**
     * Extract corpus-specific fields from result
     *
     * @param  array  $result  Raw result from vector store
     * @return array Corpus-specific metadata fields
     */
    protected function extractCorpusFields(array $result): array
    {
        $fields = [];

        // Extract case-specific fields if present
        $caseFields = [
            'case_id', 'doc_id', 'title', 'category',
            'language', 'source', 'source_id', 'chunk_index', 'actual',
        ];

        foreach ($caseFields as $field) {
            if (isset($result[$field])) {
                $fields[$field] = $result[$field];
            }
        }

        return $fields;
    }

    /*
     * Apply filters to the query
     *
     * @param  \Illuminate\Database\Query\Builder  $query  Query builder instance
     * @param  array  $filters  Filters to apply
     * @return void
     */
    protected function applyFilters($query, array $filters): void
    {
        // Category filter (exact match)
        if (isset($filters['category'])) {
            $query->where('category', '=', $filters['category']);
        }

        // Language filter (exact match)
        if (isset($filters['language'])) {
            $query->where('language', '=', $filters['language']);
        }

        // Source filter (exact match)
        if (isset($filters['source'])) {
            $query->where('source', '=', $filters['source']);
        }
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
}
