<?php

namespace App\Services\Search;

use App\Services\LegalCitations\HrLegalCitationsDetector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Citation-based search service.
 *
 * Searches for documents containing specific legal citations
 * (statutes, ECLI numbers, case numbers) using ILIKE pattern matching.
 * Extracted from UnifiedSearchService as part of Phase 3 decomposition.
 */
class CitationSearchService
{
    public function __construct(
        protected HrLegalCitationsDetector $citationDetector
    ) {}

    /**
     * Search for documents containing citations found in the query.
     *
     * Detects legal citations in the query string, then searches the specified
     * corpora for documents that contain those citation patterns.
     *
     * @param  string  $query  The search query text
     * @param  array  $corpora  List of corpora to search ('laws', 'decisions', 'cases')
     * @param  array  $filters  Key-value filters to narrow results
     * @param  int  $limit  Maximum results per corpus
     * @return array Normalized search results
     */
    public function search(string $query, array $corpora, array $filters, int $limit): array
    {
        $citations = $this->citationDetector->detectAll($query);

        if (empty(array_filter($citations))) {
            return [];
        }

        $results = [];

        foreach ($corpora as $corpus) {
            $corpusResults = match ($corpus) {
                'laws' => $this->citationSearchLaws($citations, $limit, $filters),
                'decisions' => $this->citationSearchDecisions($citations, $limit, $filters),
                'cases' => $this->citationSearchCases($citations, $limit, $filters),
                default => [],
            };

            $results = array_merge($results, $corpusResults);
        }

        return $results;
    }

    /**
     * Search the laws table for documents containing citation patterns.
     *
     * @param  array  $citations  Detected citations from HrLegalCitationsDetector
     * @param  int  $limit  Maximum number of results
     * @param  array  $filters  Key-value filters (jurisdiction, country, language, date_from, date_to)
     * @return array Normalized search results
     */
    protected function citationSearchLaws(array $citations, int $limit, array $filters): array
    {
        $results = [];
        $searchPatterns = $this->buildCitationSearchPatterns($citations);

        if (empty($searchPatterns)) {
            return [];
        }

        try {
            $queryBuilder = DB::table('laws')
                ->select([
                    'id', 'doc_id', 'title', 'law_number', 'jurisdiction', 'country',
                    'language', 'content', 'metadata', 'content_hash', 'chunk_index',
                    'promulgation_date', 'effective_date',
                ]);

            $queryBuilder->where(function ($q) use ($searchPatterns) {
                foreach ($searchPatterns as $pattern) {
                    $escapedPattern = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $pattern);
                    $q->orWhere('content', 'ILIKE', "%{$escapedPattern}%");
                }
            });

            $this->applyFiltersToQuery($queryBuilder, $filters, [
                'jurisdiction' => ['column' => 'jurisdiction'],
                'country' => ['column' => 'country'],
                'language' => ['column' => 'language'],
                'date_from' => ['column' => 'promulgation_date', 'operator' => '>='],
                'date_to' => ['column' => 'promulgation_date', 'operator' => '<='],
            ]);

            $dbResults = $queryBuilder->limit($limit)->get();

            foreach ($dbResults as $row) {
                $metadata = is_string($row->metadata) ? json_decode($row->metadata, true) : $row->metadata;
                $score = $this->calculateCitationScore($row->content, $citations);

                $results[] = $this->normalizeResult(
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
                    ]
                );
            }
        } catch (\Exception $e) {
            Log::error('Citation search failed on laws table', [
                'error' => $e->getMessage(),
                'patterns' => $searchPatterns,
            ]);
        }

        return $results;
    }

    /**
     * Search court decision documents for citation patterns.
     *
     * Joins court_decision_documents with court_decisions to get full metadata.
     *
     * @param  array  $citations  Detected citations from HrLegalCitationsDetector
     * @param  int  $limit  Maximum number of results
     * @param  array  $filters  Key-value filters (court, jurisdiction, decision_type, date_from, date_to)
     * @return array Normalized search results
     */
    protected function citationSearchDecisions(array $citations, int $limit, array $filters): array
    {
        $results = [];
        $searchPatterns = $this->buildCitationSearchPatterns($citations);

        if (empty($searchPatterns)) {
            return [];
        }

        try {
            $queryBuilder = DB::table('court_decision_documents as cdd')
                ->join('court_decisions as cd', 'cdd.decision_id', '=', 'cd.id')
                ->select([
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
                ]);

            // Search for citation patterns in content using parameterized queries
            $queryBuilder->where(function ($q) use ($searchPatterns) {
                foreach ($searchPatterns as $pattern) {
                    // Escape LIKE special characters for safety
                    $escapedPattern = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $pattern);
                    $q->orWhere('cdd.content', 'ILIKE', "%{$escapedPattern}%");
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

            $dbResults = $queryBuilder
                ->limit($limit)
                ->get();

            foreach ($dbResults as $row) {
                $metadata = is_string($row->metadata) ? json_decode($row->metadata, true) : $row->metadata;

                // Score based on number of citation matches
                $score = $this->calculateCitationScore($row->content, $citations);

                $results[] = $this->normalizeResult(
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
                    ]
                );
            }
        } catch (\Exception $e) {
            Log::error('Citation search failed on court decisions table', [
                'error' => $e->getMessage(),
                'patterns' => $searchPatterns,
            ]);
        }

        return $results;
    }

    /**
     * Search cases_documents table for citation patterns.
     *
     * @param  array  $citations  Detected citations from HrLegalCitationsDetector
     * @param  int  $limit  Maximum number of results
     * @param  array  $filters  Key-value filters (category, language, source)
     * @return array Normalized search results
     */
    protected function citationSearchCases(array $citations, int $limit, array $filters): array
    {
        $results = [];
        $searchPatterns = $this->buildCitationSearchPatterns($citations);

        if (empty($searchPatterns)) {
            return [];
        }

        try {
            $queryBuilder = DB::table('cases_documents')
                ->select([
                    'id',
                    'case_id',
                    'doc_id',
                    'title',
                    'category',
                    'language',
                    'content',
                    'metadata',
                    'content_hash',
                    'chunk_index',
                    'source',
                ]);

            // Search for citation patterns in content using parameterized queries
            $queryBuilder->where(function ($q) use ($searchPatterns) {
                foreach ($searchPatterns as $pattern) {
                    // Escape LIKE special characters for safety
                    $escapedPattern = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $pattern);
                    $q->orWhere('content', 'ILIKE', "%{$escapedPattern}%");
                }
            });

            // Apply filters
            $this->applyFiltersToQuery($queryBuilder, $filters, [
                'category' => ['column' => 'category'],
                'language' => ['column' => 'language'],
                'source' => ['column' => 'source'],
            ]);

            $dbResults = $queryBuilder
                ->limit($limit)
                ->get();

            foreach ($dbResults as $row) {
                // Score based on number of citation matches
                $score = $this->calculateCitationScore($row->content, $citations);

                $results[] = $this->normalizeResult(
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
                    ]
                );
            }
        } catch (\Exception $e) {
            Log::error('Citation search failed on cases documents table', [
                'error' => $e->getMessage(),
                'patterns' => $searchPatterns,
            ]);
        }

        return $results;
    }

    /**
     * Build search patterns from detected citations.
     *
     * Extracts text strings from the structured citation detection results
     * for use in ILIKE pattern matching.
     *
     * @param  array  $citations  Structured citations from HrLegalCitationsDetector::detectAll()
     * @return array Unique citation text patterns
     */
    protected function buildCitationSearchPatterns(array $citations): array
    {
        $patterns = [];
        foreach ($citations as $type => $citationList) {
            if (is_array($citationList)) {
                foreach ($citationList as $citation) {
                    if (is_array($citation) && isset($citation['text'])) {
                        $patterns[] = $citation['text'];
                    } elseif (is_string($citation)) {
                        $patterns[] = $citation;
                    }
                }
            }
        }

        return array_unique($patterns);
    }

    /**
     * Calculate a citation match score for document content.
     *
     * Scores based on the ratio of citation patterns found in the content.
     * A score of 1.0 means all citation patterns were found.
     *
     * @param  string  $content  Document content to score
     * @param  array  $citations  Detected citations to match against
     * @return float Score between 0.0 and 1.0
     */
    protected function calculateCitationScore(string $content, array $citations): float
    {
        $patterns = $this->buildCitationSearchPatterns($citations);
        $matchCount = 0;
        foreach ($patterns as $pattern) {
            if (stripos($content, $pattern) !== false) {
                $matchCount++;
            }
        }

        return min(1.0, $matchCount / max(1, count($patterns)));
    }

    /**
     * Apply filters to a query builder.
     *
     * Supports equality, LIKE, and comparison operators based on filter mappings.
     *
     * @param  \Illuminate\Database\Query\Builder  $queryBuilder  The query builder to modify
     * @param  array  $filters  Key-value filter pairs from the search request
     * @param  array  $filterMappings  Maps filter keys to column names and operators
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
     * Normalize search result to common format.
     *
     * @param  string  $type  Result type ('law', 'decision', 'case')
     * @param  string  $id  Document ID
     * @param  string  $title  Document title
     * @param  string  $snippet  Content snippet
     * @param  float  $score  Relevance score
     * @param  array  $metadata  Additional metadata
     * @return array Normalized result array
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
     * Extract snippet from content.
     *
     * Truncates content to maxLength, preferring to break at sentence boundaries.
     * Uses the fixed array_filter version to handle mb_strrpos returning false.
     *
     * @param  string  $content  Full document content
     * @param  int  $maxLength  Maximum snippet length (default 200)
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
        $positions = array_filter(
            [mb_strrpos($truncated, '.'), mb_strrpos($truncated, '?'), mb_strrpos($truncated, '!')],
            fn ($v) => $v !== false
        );

        $boundary = empty($positions) ? false : max($positions);

        if ($boundary !== false && $boundary > $maxLength * 0.6) {
            return mb_substr($content, 0, $boundary + 1);
        }

        return $truncated . '...';
    }
}
