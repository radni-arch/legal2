<?php

namespace App\Contracts\Search;

use App\Services\Contracts\SearchServiceInterface;

/**
 * Interface for search orchestration across multiple search services.
 *
 * Manages registration of search services for different corpora
 * and orchestrates searches across them.
 */
interface SearchOrchestratorInterface
{
    /**
     * Register a search service for a specific corpus.
     *
     * @param  string  $corpus  The corpus identifier (e.g., 'laws', 'decisions')
     * @param  SearchServiceInterface  $service  The search service instance
     */
    public function registerSearchService(string $corpus, SearchServiceInterface $service): void;

    /**
     * Execute a search across registered search services.
     *
     * @param  string  $query  The search query
     * @param  array  $options  Search options including:
     *                          - corpora: array of corpora to search (optional, defaults to all)
     *                          - search_type: 'vector' | 'keyword' | 'hybrid'
     *                          - filters: array of filters per corpus
     *                          - limit: max results
     *                          - weights: scoring weights per corpus
     * @return array Search results organized by corpus
     */
    public function search(string $query, array $options = []): array;

    /**
     * Get list of available corpora.
     *
     * @return array List of registered corpus names
     */
    public function getAvailableCorpora(): array;
}
