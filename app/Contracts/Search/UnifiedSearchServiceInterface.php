<?php

namespace App\Contracts\Search;

/**
 * Interface for unified search across multiple corpora.
 *
 * Provides a single entry point for searching across laws,
 * decisions, cases, and other legal content.
 */
interface UnifiedSearchServiceInterface
{
    /**
     * Perform a unified search across all registered corpora.
     *
     * @param  string  $query  The search query
     * @param  array  $options  Search options including:
     *                          - corpora: array of corpus names to search
     *                          - search_type: 'vector' | 'keyword' | 'hybrid'
     *                          - filters: corpus-specific filters
     *                          - weights: scoring weights per corpus
     *                          - limit: max results per corpus
     * @return array Unified search results
     */
    public function search(string $query, array $options = []): array;

    /**
     * Get list of available search corpora.
     *
     * @return array List of corpus names
     */
    public function getAvailableCorpora(): array;
}
