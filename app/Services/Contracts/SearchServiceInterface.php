<?php

namespace App\Services\Contracts;

interface SearchServiceInterface
{
    /**
     * Search with configurable search type
     *
     * @param  string  $query  Search query
     * @param  array  $options  Search options:
     *                          - search_type: 'vector' | 'keyword' | 'hybrid'
     *                          - filters: array of filters (jurisdiction, date_range, etc.)
     *                          - limit: max results
     *                          - page: page number
     */
    public function search(string $query, array $options = []): array;
}
