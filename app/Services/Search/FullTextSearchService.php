<?php

namespace App\Services\Search;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Full-text search service using PostgreSQL tsvector.
 *
 * Provides full-text search across laws, decisions, and cases corpora.
 * Falls back to ILIKE search when content_tsv column is unavailable.
 * Extracted from UnifiedSearchService as part of Phase 3 decomposition.
 */
class FullTextSearchService
{
    /**
     * Cache for full-text search column availability per table.
     * Uses instance property to reset between requests in Octane/queue workers.
     *
     * @var array<string, bool>
     */
    protected array $ftsColumnCache = [];

    /**
     * Track if we've logged the FTS fallback warning (once per request).
     */
    protected bool $ftsWarningLogged = false;

    /**
     * Search across specified corpora using full-text search.
     */
    public function search(string $query, array $corpora, array $filters, int $limit): array
    {
        $driver = DB::connection()->getDriverName();

        if ($driver !== 'pgsql') {
            Log::warning('Non-PostgreSQL driver detected, full-text search not supported', [
                'driver' => $driver,
            ]);

            return [];
        }

        $results = [];

        foreach ($corpora as $corpus) {
            $corpusResults = match ($corpus) {
                'laws' => $this->fullTextSearchLaws($query, $limit, $filters),
                'decisions' => $this->fullTextSearchDecisions($query, $limit, $filters),
                'cases' => $this->fullTextSearchCases($query, $limit, $filters),
                default => [],
            };

            $results = array_merge($results, $corpusResults);
        }

        return $results;
    }

    /**
     * Full-text search on laws table
     *
     * Falls back to ILIKE search if content_tsv column doesn't exist.
     */
    public function fullTextSearchLaws(string $query, int $limit, array $filters): array
    {
        $tableName = 'laws';

        // Check if full-text search is available
        if (! $this->hasFullTextColumn($tableName)) {
            return $this->fallbackSearchLaws($query, $limit, $filters);
        }

        // Create tsquery from search query
        $tsquery = $this->createTsQuery($query);

        // Return empty if query is invalid
        if (empty($tsquery)) {
            return [];
        }

        try {
            $queryBuilder = DB::table($tableName)
                ->selectRaw('id, doc_id, title, law_number, jurisdiction, country, language, content, metadata, content_hash, chunk_index, promulgation_date, effective_date, ts_rank(content_tsv, to_tsquery(\'simple\', ?)) as rank', [$tsquery])
                ->whereRaw("content_tsv @@ to_tsquery('simple', ?)", [$tsquery]);

            // Apply filters
            $this->applyFiltersToQuery($queryBuilder, $filters, [
                'jurisdiction' => ['column' => 'jurisdiction'],
                'country' => ['column' => 'country'],
                'language' => ['column' => 'language'],
                'date_from' => ['column' => 'promulgation_date', 'operator' => '>='],
                'date_to' => ['column' => 'promulgation_date', 'operator' => '<='],
            ]);

            $results = $queryBuilder
                ->orderByDesc('rank')
                ->limit($limit)
                ->get();

            return $results->map(function ($row) {
                $metadata = is_string($row->metadata) ? json_decode($row->metadata, true) : $row->metadata;

                return $this->normalizeResult(
                    type: 'law',
                    id: $row->id,
                    title: $row->title ?? "Law {$row->law_number}",
                    snippet: $this->extractSnippet($row->content, 200),
                    score: $row->rank,
                    metadata: [
                        'doc_id' => $row->doc_id,
                        'law_number' => $row->law_number,
                        'jurisdiction' => $row->jurisdiction,
                        'country' => $row->country,
                        'language' => $row->language,
                        'content_hash' => $row->content_hash,
                        'chunk_index' => $row->chunk_index,
                        'promulgation_date' => $row->promulgation_date,
                        'effective_date' => $row->effective_date,
                        'article_number' => $metadata['article_number'] ?? null,
                    ]
                );
            })->toArray();
        } catch (\Exception $e) {
            Log::error('Full-text search failed on laws table', [
                'error' => $e->getMessage(),
                'query' => $query,
                'tsquery' => $tsquery,
            ]);

            return [];
        }
    }

    /**
     * Full-text search on court decision documents table
     *
     * Falls back to ILIKE search if content_tsv column doesn't exist.
     */
    public function fullTextSearchDecisions(string $query, int $limit, array $filters): array
    {
        $tableName = 'court_decision_documents';

        // Check if full-text search is available
        if (! $this->hasFullTextColumn($tableName)) {
            return $this->fallbackSearchDecisions($query, $limit, $filters);
        }

        // Create tsquery from search query
        $tsquery = $this->createTsQuery($query);

        // Return empty if query is invalid
        if (empty($tsquery)) {
            return [];
        }

        try {
            $queryBuilder = DB::table("{$tableName} as cdd")
                ->join('court_decisions as cd', 'cdd.decision_id', '=', 'cd.id')
                ->selectRaw('cdd.id, cdd.decision_id, cd.case_number, cd.title, cd.court, cd.jurisdiction, cd.decision_date, cd.decision_type, cd.ecli, cdd.content, cdd.metadata, cdd.content_hash, cdd.chunk_index, ts_rank(cdd.content_tsv, to_tsquery(\'simple\', ?)) as rank', [$tsquery])
                ->whereRaw("cdd.content_tsv @@ to_tsquery('simple', ?)", [$tsquery]);

            // Apply filters
            $this->applyFiltersToQuery($queryBuilder, $filters, [
                'court' => ['column' => 'cd.court', 'operator' => 'LIKE'],
                'jurisdiction' => ['column' => 'cd.jurisdiction'],
                'decision_type' => ['column' => 'cd.decision_type'],
                'date_from' => ['column' => 'cd.decision_date', 'operator' => '>='],
                'date_to' => ['column' => 'cd.decision_date', 'operator' => '<='],
            ]);

            $results = $queryBuilder
                ->orderByDesc('rank')
                ->limit($limit)
                ->get();

            return $results->map(function ($row) {
                $metadata = is_string($row->metadata) ? json_decode($row->metadata, true) : $row->metadata;

                return $this->normalizeResult(
                    type: 'decision',
                    id: $row->id,
                    title: $row->title ?? "Case {$row->case_number}",
                    snippet: $this->extractSnippet($row->content, 200),
                    score: $row->rank,
                    metadata: [
                        'decision_id' => $row->decision_id,
                        'case_number' => $row->case_number,
                        'court' => $row->court,
                        'jurisdiction' => $row->jurisdiction,
                        'decision_date' => $row->decision_date,
                        'decision_type' => $row->decision_type,
                        'ecli' => $row->ecli,
                        'content_hash' => $row->content_hash,
                        'chunk_index' => $row->chunk_index,
                    ]
                );
            })->toArray();
        } catch (\Exception $e) {
            Log::error('Full-text search failed on court decisions table', [
                'error' => $e->getMessage(),
                'query' => $query,
                'tsquery' => $tsquery,
            ]);

            return [];
        }
    }

    /**
     * Full-text search on case documents table
     *
     * Falls back to ILIKE search if content_tsv column doesn't exist.
     */
    public function fullTextSearchCases(string $query, int $limit, array $filters): array
    {
        $tableName = 'cases_documents';

        // Check if full-text search is available
        if (! $this->hasFullTextColumn($tableName)) {
            return $this->fallbackSearchCases($query, $limit, $filters);
        }

        // Create tsquery from search query
        $tsquery = $this->createTsQuery($query);

        // Return empty if query is invalid
        if (empty($tsquery)) {
            return [];
        }

        try {
            $queryBuilder = DB::table($tableName)
                ->selectRaw('id, case_id, doc_id, title, category, language, content, metadata, content_hash, chunk_index, source, ts_rank(content_tsv, to_tsquery(\'simple\', ?)) as rank', [$tsquery])
                ->whereRaw("content_tsv @@ to_tsquery('simple', ?)", [$tsquery]);

            // Apply filters
            $this->applyFiltersToQuery($queryBuilder, $filters, [
                'category' => ['column' => 'category'],
                'language' => ['column' => 'language'],
                'source' => ['column' => 'source'],
            ]);

            $results = $queryBuilder
                ->orderByDesc('rank')
                ->limit($limit)
                ->get();

            return $results->map(function ($row) {
                return $this->normalizeResult(
                    type: 'case',
                    id: $row->id,
                    title: $row->title ?? "Case Document {$row->doc_id}",
                    snippet: $this->extractSnippet($row->content, 200),
                    score: $row->rank,
                    metadata: [
                        'case_id' => $row->case_id,
                        'doc_id' => $row->doc_id,
                        'category' => $row->category,
                        'language' => $row->language,
                        'content_hash' => $row->content_hash,
                        'chunk_index' => $row->chunk_index,
                        'source' => $row->source,
                    ]
                );
            })->toArray();
        } catch (\Exception $e) {
            Log::error('Full-text search failed on cases documents table', [
                'error' => $e->getMessage(),
                'query' => $query,
                'tsquery' => $tsquery,
            ]);

            return [];
        }
    }

    /**
     * Check if full-text search column (content_tsv) exists in a table.
     *
     * Results are cached to avoid repeated schema lookups.
     */
    protected function hasFullTextColumn(string $tableName): bool
    {
        if (! isset($this->ftsColumnCache[$tableName])) {
            $this->ftsColumnCache[$tableName] = Schema::hasColumn($tableName, 'content_tsv');

            // Log warning once if full-text search is unavailable
            if (! $this->ftsColumnCache[$tableName] && ! $this->ftsWarningLogged) {
                Log::warning('Full-text search column (content_tsv) not found, using fallback ILIKE search', [
                    'table' => $tableName,
                    'hint' => 'Run migration: php artisan migrate (2025_10_26_160047_add_fulltext_search_columns)',
                ]);
                $this->ftsWarningLogged = true;
            }
        }

        return $this->ftsColumnCache[$tableName];
    }

    /**
     * Create a tsquery from user search query
     * Handles multiple words and creates an OR query
     *
     * @param  string  $query  The search query
     * @return string PostgreSQL tsquery string, empty if no valid words found
     */
    protected function createTsQuery(string $query): string
    {
        // Escape special characters and split into words
        $query = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $query);
        $words = array_filter(array_map('trim', explode(' ', $query)));

        // Filter out empty words and words that are too short
        $words = array_filter($words, fn ($word) => mb_strlen($word) >= 2);

        if (empty($words)) {
            return '';
        }

        // Join words with OR operator for broader matching
        return implode(' | ', $words);
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
     * Fallback ILIKE search on laws table when content_tsv is unavailable.
     */
    protected function fallbackSearchLaws(string $query, int $limit, array $filters): array
    {
        try {
            // Build search terms from query
            $searchTerms = $this->buildSearchTerms($query);
            if (empty($searchTerms)) {
                return [];
            }

            $queryBuilder = DB::table('laws')
                ->select([
                    'id', 'doc_id', 'title', 'law_number', 'jurisdiction', 'country',
                    'language', 'content', 'metadata', 'content_hash', 'chunk_index',
                    'promulgation_date', 'effective_date',
                ]);

            // ILIKE search on title and content
            $queryBuilder->where(function ($q) use ($searchTerms) {
                foreach ($searchTerms as $term) {
                    $escapedTerm = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
                    $q->orWhere('title', 'ILIKE', "%{$escapedTerm}%")
                      ->orWhere('content', 'ILIKE', "%{$escapedTerm}%");
                }
            });

            // Apply filters
            $this->applyFiltersToQuery($queryBuilder, $filters, [
                'jurisdiction' => ['column' => 'jurisdiction'],
                'country' => ['column' => 'country'],
                'language' => ['column' => 'language'],
                'date_from' => ['column' => 'promulgation_date', 'operator' => '>='],
                'date_to' => ['column' => 'promulgation_date', 'operator' => '<='],
            ]);

            $results = $queryBuilder->limit($limit)->get();

            return $results->map(function ($row) use ($searchTerms) {
                $metadata = is_string($row->metadata) ? json_decode($row->metadata, true) : $row->metadata;

                // Calculate simple relevance score based on term matches
                $score = $this->calculateFallbackScore($row->title ?? '', $row->content ?? '', $searchTerms);

                return $this->normalizeResult(
                    type: 'law',
                    id: $row->id,
                    title: $row->title ?? "Law {$row->law_number}",
                    snippet: $this->extractSnippet($row->content, 200),
                    score: $score,
                    metadata: [
                        'doc_id' => $row->doc_id,
                        'law_number' => $row->law_number,
                        'jurisdiction' => $row->jurisdiction,
                        'country' => $row->country,
                        'language' => $row->language,
                        'content_hash' => $row->content_hash,
                        'chunk_index' => $row->chunk_index,
                        'promulgation_date' => $row->promulgation_date,
                        'effective_date' => $row->effective_date,
                        'article_number' => $metadata['article_number'] ?? null,
                        'search_mode' => 'fallback_ilike',
                    ]
                );
            })->sortByDesc('score')->values()->toArray();
        } catch (\Exception $e) {
            Log::error('Fallback ILIKE search failed on laws table', [
                'error' => $e->getMessage(),
                'query' => $query,
            ]);

            return [];
        }
    }

    /**
     * Fallback ILIKE search on court decisions table when content_tsv is unavailable.
     */
    protected function fallbackSearchDecisions(string $query, int $limit, array $filters): array
    {
        try {
            $searchTerms = $this->buildSearchTerms($query);
            if (empty($searchTerms)) {
                return [];
            }

            $queryBuilder = DB::table('court_decision_documents as cdd')
                ->join('court_decisions as cd', 'cdd.decision_id', '=', 'cd.id')
                ->select([
                    'cdd.id', 'cdd.decision_id', 'cd.case_number', 'cd.title', 'cd.court',
                    'cd.jurisdiction', 'cd.decision_date', 'cd.decision_type', 'cd.ecli',
                    'cdd.content', 'cdd.metadata', 'cdd.content_hash', 'cdd.chunk_index',
                ]);

            // ILIKE search on title and content
            $queryBuilder->where(function ($q) use ($searchTerms) {
                foreach ($searchTerms as $term) {
                    $escapedTerm = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
                    $q->orWhere('cd.title', 'ILIKE', "%{$escapedTerm}%")
                      ->orWhere('cdd.content', 'ILIKE', "%{$escapedTerm}%");
                }
            });

            // Apply filters
            $this->applyFiltersToQuery($queryBuilder, $filters, [
                'court' => ['column' => 'cd.court', 'operator' => 'LIKE'],
                'jurisdiction' => ['column' => 'cd.jurisdiction'],
                'decision_type' => ['column' => 'cd.decision_type'],
                'date_from' => ['column' => 'cd.decision_date', 'operator' => '>='],
                'date_to' => ['column' => 'cd.decision_date', 'operator' => '<='],
            ]);

            $results = $queryBuilder->limit($limit)->get();

            return $results->map(function ($row) use ($searchTerms) {
                $metadata = is_string($row->metadata) ? json_decode($row->metadata, true) : $row->metadata;
                $score = $this->calculateFallbackScore($row->title ?? '', $row->content ?? '', $searchTerms);

                return $this->normalizeResult(
                    type: 'decision',
                    id: $row->id,
                    title: $row->title ?? "Case {$row->case_number}",
                    snippet: $this->extractSnippet($row->content, 200),
                    score: $score,
                    metadata: [
                        'decision_id' => $row->decision_id,
                        'case_number' => $row->case_number,
                        'court' => $row->court,
                        'jurisdiction' => $row->jurisdiction,
                        'decision_date' => $row->decision_date,
                        'decision_type' => $row->decision_type,
                        'ecli' => $row->ecli,
                        'content_hash' => $row->content_hash,
                        'chunk_index' => $row->chunk_index,
                        'search_mode' => 'fallback_ilike',
                    ]
                );
            })->sortByDesc('score')->values()->toArray();
        } catch (\Exception $e) {
            Log::error('Fallback ILIKE search failed on court decisions table', [
                'error' => $e->getMessage(),
                'query' => $query,
            ]);

            return [];
        }
    }

    /**
     * Fallback ILIKE search on case documents table when content_tsv is unavailable.
     */
    protected function fallbackSearchCases(string $query, int $limit, array $filters): array
    {
        try {
            $searchTerms = $this->buildSearchTerms($query);
            if (empty($searchTerms)) {
                return [];
            }

            $queryBuilder = DB::table('cases_documents')
                ->select([
                    'id', 'case_id', 'doc_id', 'title', 'category', 'language',
                    'content', 'metadata', 'content_hash', 'chunk_index', 'source',
                ]);

            // ILIKE search on title and content
            $queryBuilder->where(function ($q) use ($searchTerms) {
                foreach ($searchTerms as $term) {
                    $escapedTerm = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
                    $q->orWhere('title', 'ILIKE', "%{$escapedTerm}%")
                      ->orWhere('content', 'ILIKE', "%{$escapedTerm}%");
                }
            });

            // Apply filters
            $this->applyFiltersToQuery($queryBuilder, $filters, [
                'category' => ['column' => 'category'],
                'language' => ['column' => 'language'],
                'source' => ['column' => 'source'],
            ]);

            $results = $queryBuilder->limit($limit)->get();

            return $results->map(function ($row) use ($searchTerms) {
                $score = $this->calculateFallbackScore($row->title ?? '', $row->content ?? '', $searchTerms);

                return $this->normalizeResult(
                    type: 'case',
                    id: $row->id,
                    title: $row->title ?? "Case Document {$row->doc_id}",
                    snippet: $this->extractSnippet($row->content, 200),
                    score: $score,
                    metadata: [
                        'case_id' => $row->case_id,
                        'doc_id' => $row->doc_id,
                        'category' => $row->category,
                        'language' => $row->language,
                        'content_hash' => $row->content_hash,
                        'chunk_index' => $row->chunk_index,
                        'source' => $row->source,
                        'search_mode' => 'fallback_ilike',
                    ]
                );
            })->sortByDesc('score')->values()->toArray();
        } catch (\Exception $e) {
            Log::error('Fallback ILIKE search failed on case documents table', [
                'error' => $e->getMessage(),
                'query' => $query,
            ]);

            return [];
        }
    }

    /**
     * Build search terms from a query string.
     *
     * @return array<string> Non-empty search terms
     */
    protected function buildSearchTerms(string $query): array
    {
        // Split on whitespace and filter empty terms
        $terms = preg_split('/\s+/', trim($query), -1, PREG_SPLIT_NO_EMPTY);

        // Filter out very short terms (less than 2 chars)
        return array_filter($terms, fn ($term) => mb_strlen($term) >= 2);
    }

    /**
     * Calculate a simple relevance score for fallback search results.
     *
     * Higher score for more term matches and title matches.
     */
    protected function calculateFallbackScore(string $title, string $content, array $searchTerms): float
    {
        $score = 0.0;
        $titleLower = mb_strtolower($title);
        $contentLower = mb_strtolower($content);

        foreach ($searchTerms as $term) {
            $termLower = mb_strtolower($term);

            // Title matches are weighted higher
            if (str_contains($titleLower, $termLower)) {
                $score += 2.0;
            }

            // Content matches
            $contentMatches = mb_substr_count($contentLower, $termLower);
            $score += min($contentMatches * 0.1, 1.0); // Cap content contribution per term
        }

        // Normalize score to 0-1 range (approximate)
        return min($score / (count($searchTerms) * 3), 1.0);
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
}
