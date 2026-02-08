<?php

namespace App\Contracts\Services;

/**
 * Interface for query rewriting and enhancement services.
 *
 * Provides methods for analyzing and rewriting search queries
 * to improve search relevance and coverage.
 */
interface QueryRewriterInterface
{
    /**
     * Rewrite a query into multiple variations.
     *
     * @param  string  $query  The original query
     * @param  string  $language  Language code (default: 'hr')
     * @return array Array of rewritten query variations
     */
    public function rewrite(string $query, string $language = 'hr'): array;

    /**
     * Get the single best rewritten version of a query.
     *
     * @param  string  $query  The original query
     * @param  string  $language  Language code (default: 'hr')
     * @return string The best rewritten query
     */
    public function rewriteBest(string $query, string $language = 'hr'): string;

    /**
     * Analyze the intent behind a search query.
     *
     * @param  string  $query  The query to analyze
     * @return array Analysis results including intent type, entities, etc.
     */
    public function analyzeIntent(string $query): array;
}
