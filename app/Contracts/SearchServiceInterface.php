<?php

namespace App\Contracts;

use App\Services\Contracts\SearchServiceInterface as BaseSearchServiceInterface;

/**
 * Interface for search services across different document corpora
 *
 * This interface defines the contract for search services that operate
 * on different document types (laws, court decisions, cases, textract documents).
 *
 * Implementations should provide semantic/vector search capabilities
 * and support corpus-specific filtering and options.
 */
interface SearchServiceInterface extends BaseSearchServiceInterface
{
    /**
     * Perform a search query against the document corpus
     *
     * Returns an array of search results with relevance scores and metadata.
     *
     * @param  string  $query  The search query (natural language or keywords)
     * @param  array  $options  Search options including:
     *                          - 'limit' (int): Maximum number of results (default varies by implementation)
     *                          - 'threshold' (float): Minimum similarity/relevance threshold (0.0-1.0)
     *                          - 'filters' (array): Corpus-specific filters (e.g., jurisdiction, date range, court)
     *                          - 'include_metadata' (bool): Whether to include full metadata in results
     *                          - 'include_content' (bool): Whether to include document content in results
     * @return array Array of search results, each containing:
     *               - 'id' (string): Document identifier
     *               - 'score' (float): Relevance/similarity score
     *               - 'content' (string|null): Document content (if include_content is true)
     *               - 'metadata' (array|null): Document metadata (if include_metadata is true)
     *               - Additional corpus-specific fields
     */
    public function search(string $query, array $options = []): array;

    /**
     * Check if this service supports searching a specific corpus
     *
     * Corpus types typically include:
     * - 'law' or 'laws': Croatian legal statutes and regulations
     * - 'decision' or 'court_decision': Court decisions from odluke.sudovi.hr
     * - 'case' or 'case_document': Internal case documents
     * - 'textract' or 'textract_document': OCR'd documents from AWS Textract
     *
     * @param  string  $corpus  The corpus identifier to check
     * @return bool True if this service supports the corpus, false otherwise
     */
    public function supportsCorpus(string $corpus): bool;
}
