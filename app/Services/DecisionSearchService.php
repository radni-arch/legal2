<?php

namespace App\Services;

use App\Exceptions\EmbeddingException;
use App\Exceptions\SearchException;
use App\Mcp\Tools\DecisionGetTool;
use App\Models\CourtDecision;
use App\Services\Contracts\SearchServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DecisionSearchService extends BaseSearchService implements SearchServiceInterface
{
    /**
     * Decision retrieval MCP tool (lazy-resolved to avoid circular dependency)
     */
    protected ?DecisionGetTool $decisionGetTool = null;

    /**
     * Constructor
     *
     * Note: DecisionSearchTool (MCP) was removed from constructor injection
     * because it depends on this service, creating a circular dependency that
     * caused infinite recursion and memory exhaustion (segfault).
     * DecisionGetTool is lazy-resolved for the same reason (it's safe, but
     * consistent lazy resolution prevents future circular dependency issues).
     */
    public function __construct(
        OpenAIService $openAI
    ) {
        parent::__construct($openAI);
    }

    /**
     * Get the DecisionGetTool instance, lazily resolved to avoid circular dependency.
     */
    protected function decisionGetTool(): DecisionGetTool
    {
        return $this->decisionGetTool ??= app(DecisionGetTool::class);
    }

    /**
     * Main search entry point with configurable search type
     *
     * @param  string  $query  Search query
     * @param  array  $options  Search options
     * @return array Search results
     *
     * @throws SearchException if search fails
     */
    public function search(string $query, array $options = []): array
    {
        try {
            Log::info('Decision search initiated', [
                'query' => substr($query, 0, 100),
                'options' => $options,
                'service' => static::class,
            ]);

            $startTime = microtime(true);

            $searchType = $options['search_type'] ?? 'keyword';
            $filters = $options['filters'] ?? [];

            // Merge top-level options into filters
            $filters = array_merge($filters, [
                'limit' => $options['limit'] ?? 10,
                'page' => $options['page'] ?? 1,
                'jurisdiction' => $options['jurisdiction'] ?? null,
            ]);

            $results = match ($searchType) {
                'vector' => $this->vectorSearch($query, $filters),
                'keyword' => $this->keywordSearch($query, $filters),
                'hybrid' => $this->hybridSearch($query, $filters),
                default => throw new \InvalidArgumentException("Invalid search_type: {$searchType}"),
            };

            Log::info('Decision search completed', [
                'query' => substr($query, 0, 100),
                'search_type' => $searchType,
                'result_count' => $results['count'] ?? 0,
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $results;

        } catch (\InvalidArgumentException $e) {
            Log::warning('Invalid search parameters', [
                'query' => substr($query, 0, 100),
                'error' => $e->getMessage(),
            ]);

            throw new SearchException(
                'Invalid search parameters: '.$e->getMessage(),
                SearchException::INVALID_QUERY,
                $e
            );

        } catch (SearchException $e) {
            // Re-throw SearchExceptions
            throw $e;
        } catch (\Exception $e) {
            Log::error('Unexpected error in decision search', [
                'query' => substr($query, 0, 100),
                'options' => $options,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new SearchException(
                'Decision search failed: '.$e->getMessage(),
                SearchException::UNEXPECTED_ERROR,
                $e
            );
        }
    }

    /**
     * Perform vector-based semantic search
     *
     * @param  string  $query  Search query
     * @param  array  $filters  Additional filters
     * @return array Search results
     *
     * @throws SearchException if vector search fails
     */
    public function vectorSearch(string $query, array $filters = []): array
    {
        try {
            Log::info('Vector search initiated', [
                'query' => substr($query, 0, 100),
                'filters' => $filters,
            ]);

            // Generate embedding for the query
            $queryVector = $this->generateEmbeddingWithErrorHandling($query);

            // Extract filter parameters
            $jurisdiction = $filters['jurisdiction'] ?? null;
            $limit = $filters['limit'] ?? 10;
            $minSimilarity = $filters['min_similarity'] ?? 0.7;

            // Perform vector search
            $results = $this->executeVectorSearchQuery($queryVector, $jurisdiction, $limit, $minSimilarity);

            Log::info('Vector search completed', [
                'query' => substr($query, 0, 100),
                'result_count' => count($results),
            ]);

            return [
                'success' => true,
                'data' => $results,
                'search_type' => 'vector',
                'count' => count($results),
            ];

        } catch (EmbeddingException $e) {
            Log::error('Embedding generation failed in vector search', [
                'query' => substr($query, 0, 100),
                'error' => $e->getMessage(),
            ]);

            throw new SearchException(
                'Failed to generate search embedding: '.$e->getMessage(),
                SearchException::EMBEDDING_FAILED,
                $e
            );

        } catch (\Exception $e) {
            Log::error('Vector search failed', [
                'query' => substr($query, 0, 100),
                'filters' => $filters,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new SearchException(
                'Vector search execution failed: '.$e->getMessage(),
                SearchException::VECTOR_SEARCH_FAILED,
                $e
            );
        }
    }

    /**
     * Generate embedding with proper error handling
     *
     * @throws EmbeddingException
     */
    protected function generateEmbeddingWithErrorHandling(string $query): array
    {
        try {
            $queryVector = $this->generateEmbedding($query);

            if (! $queryVector) {
                throw new EmbeddingException(
                    'Embedding generation returned null or empty result',
                    EmbeddingException::RESPONSE_PARSING_FAILED
                );
            }

            return $queryVector;

        } catch (\Exception $e) {
            throw new EmbeddingException(
                'Failed to generate embedding: '.$e->getMessage(),
                EmbeddingException::UNEXPECTED_ERROR,
                $e
            );
        }
    }

    /**
     * Execute vector search query with error handling
     *
     * @throws SearchException
     */
    protected function executeVectorSearchQuery(
        array $queryVector,
        ?string $jurisdiction,
        int $limit,
        float $minSimilarity
    ): array {
        try {
            $driver = DB::connection()->getDriverName();
            $queryBuilder = DB::table('court_decision_documents')
                ->select([
                    'court_decision_documents.id',
                    'court_decision_documents.decision_id',
                    'court_decision_documents.doc_id',
                    'court_decision_documents.title',
                    'court_decision_documents.content',
                    'court_decision_documents.chunk_index',
                    'court_decision_documents.metadata',
                    'court_decision_documents.embedding_vector',
                    'court_decisions.case_number',
                    'court_decisions.court',
                    'court_decisions.jurisdiction',
                    'court_decisions.decision_date',
                    'court_decisions.decision_type',
                ])
                ->leftJoin('court_decisions', 'court_decision_documents.decision_id', '=', 'court_decisions.id');

            // Apply jurisdiction filter
            if ($jurisdiction) {
                $queryBuilder->where('court_decisions.jurisdiction', $jurisdiction);
            }

            // Check if pgvector extension is available
            $hasPgVector = $driver === 'pgsql' && $this->hasPgvectorExtension();

            // Use pgvector for PostgreSQL with extension
            if ($hasPgVector) {
                $vectorLiteral = $this->toPgVectorLiteral($queryVector);
                $queryBuilder->selectRaw("1 - (court_decision_documents.embedding <=> '{$vectorLiteral}'::vector) as similarity")
                    ->whereRaw("1 - (court_decision_documents.embedding <=> '{$vectorLiteral}'::vector) >= ?", [$minSimilarity])
                    ->orderByRaw("court_decision_documents.embedding <=> '{$vectorLiteral}'::vector")
                    ->limit($limit);
            } else {
                // Fallback for non-pgvector databases
                $queryBuilder->whereNotNull('court_decision_documents.embedding_vector')->limit($limit * 3);
            }

            $results = $queryBuilder->get()->map(function ($decision) use ($queryVector, $hasPgVector) {
                if (! $hasPgVector) {
                    $decisionVector = json_decode($decision->embedding_vector ?? '[]', true);
                    $decision->similarity = $this->cosineSimilarity($queryVector, $decisionVector);
                }
                $decision->metadata = json_decode($decision->metadata ?? '{}', true);

                return $decision;
            });

            // Filter and sort for non-pgvector databases
            if (! $hasPgVector) {
                $results = $results->filter(fn ($r) => $r->similarity >= $minSimilarity)
                    ->sortByDesc('similarity')
                    ->take($limit)
                    ->values();
            }

            return $results->toArray();

        } catch (\Exception $e) {
            throw new SearchException(
                'Vector database query failed: '.$e->getMessage(),
                SearchException::VECTOR_SEARCH_FAILED,
                $e
            );
        }
    }

    /**
     * Perform keyword-based search
     *
     * @param  string  $query  Search query
     * @param  array  $filters  Additional filters
     * @return array Search results
     *
     * @throws SearchException if keyword search fails
     */
    public function keywordSearch(string $query, array $filters = []): array
    {
        try {
            Log::info('Keyword search initiated', [
                'query' => substr($query, 0, 100),
                'filters' => $filters,
            ]);

            $queryBuilder = CourtDecision::query();
            $likeOperator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

            // Apply filters
            $this->applyKeywordFilters($queryBuilder, $filters, $likeOperator);

            // Free text search on title, description, and case number
            if (! empty($query)) {
                $queryBuilder->where(function ($q) use ($query, $likeOperator) {
                    $q->where('title', $likeOperator, '%'.$query.'%')
                        ->orWhere('description', $likeOperator, '%'.$query.'%')
                        ->orWhere('case_number', $likeOperator, '%'.$query.'%');
                });
            }

            // Pagination
            $limit = min((int) ($filters['limit'] ?? 10), 100);
            $page = (int) ($filters['page'] ?? 1);
            $offset = ($page - 1) * $limit;

            // Get total count for pagination
            $total = $queryBuilder->count();

            // Get results
            $decisions = $queryBuilder->select([
                'id', 'case_number', 'title', 'court', 'jurisdiction',
                'judge', 'decision_date', 'publication_date', 'decision_type',
                'register', 'finality', 'ecli', 'tags',
            ])
                ->orderBy('decision_date', 'desc')
                ->skip($offset)
                ->take($limit)
                ->get();

            Log::info('Keyword search completed', [
                'query' => substr($query, 0, 100),
                'total_results' => $total,
                'returned_results' => $decisions->count(),
            ]);

            return [
                'success' => true,
                'data' => $decisions->toArray(),
                'search_type' => 'keyword',
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit),
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Keyword search failed', [
                'query' => substr($query, 0, 100),
                'filters' => $filters,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new SearchException(
                'Keyword search failed: '.$e->getMessage(),
                SearchException::UNEXPECTED_ERROR,
                $e
            );
        }
    }

    /**
     * Apply keyword search filters to query builder
     */
    protected function applyKeywordFilters($queryBuilder, array $filters, string $likeOperator): void
    {
        if (! empty($filters['case_number'])) {
            $queryBuilder->where('case_number', $likeOperator, '%'.$filters['case_number'].'%');
        }

        if (! empty($filters['court'])) {
            $queryBuilder->where('court', $likeOperator, '%'.$filters['court'].'%');
        }

        if (! empty($filters['jurisdiction'])) {
            $queryBuilder->where('jurisdiction', $filters['jurisdiction']);
        }

        if (! empty($filters['judge'])) {
            $queryBuilder->where('judge', $likeOperator, '%'.$filters['judge'].'%');
        }

        if (! empty($filters['decision_type'])) {
            $queryBuilder->where('decision_type', $filters['decision_type']);
        }

        if (! empty($filters['register'])) {
            $queryBuilder->where('register', $filters['register']);
        }

        if (! empty($filters['ecli'])) {
            $queryBuilder->where('ecli', $filters['ecli']);
        }

        if (! empty($filters['finality'])) {
            $queryBuilder->where('finality', $filters['finality']);
        }

        if (! empty($filters['tags'])) {
            $tags = array_map('trim', explode(',', $filters['tags']));
            foreach ($tags as $tag) {
                $queryBuilder->whereJsonContains('tags', $tag);
            }
        }

        // Date range filters
        if (! empty($filters['date_from'])) {
            $queryBuilder->where('decision_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $queryBuilder->where('decision_date', '<=', $filters['date_to']);
        }
    }

    /**
     * Perform hybrid search (combination of vector and keyword)
     *
     * @param  string  $query  Search query
     * @param  array  $filters  Additional filters
     * @return array Search results
     *
     * @throws SearchException if hybrid search fails
     */
    public function hybridSearch(string $query, array $filters = []): array
    {
        try {
            Log::info('Hybrid search initiated', [
                'query' => substr($query, 0, 100),
                'filters' => $filters,
            ]);

            // Execute both searches in parallel
            $vectorResults = $this->vectorSearch($query, $filters);
            $keywordResults = $this->keywordSearch($query, $filters);

            // Merge and deduplicate
            $merged = $this->mergeSearchResults($vectorResults['data'], $keywordResults['data']);

            Log::info('Hybrid search completed', [
                'query' => substr($query, 0, 100),
                'vector_count' => count($vectorResults['data']),
                'keyword_count' => count($keywordResults['data']),
                'merged_count' => count($merged),
            ]);

            return [
                'success' => true,
                'data' => $merged,
                'search_type' => 'hybrid',
                'count' => count($merged),
            ];

        } catch (SearchException $e) {
            // Re-throw search exceptions
            throw $e;
        } catch (\Exception $e) {
            Log::error('Hybrid search failed', [
                'query' => substr($query, 0, 100),
                'filters' => $filters,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new SearchException(
                'Hybrid search failed: '.$e->getMessage(),
                SearchException::UNEXPECTED_ERROR,
                $e
            );
        }
    }

    /**
     * Merge and deduplicate search results from vector and keyword searches
     */
    protected function mergeSearchResults(array $vectorResults, array $keywordResults): array
    {
        $merged = [];
        $seen = [];

        // Vector results get higher weight
        foreach ($vectorResults as $item) {
            $key = $item['decision_id'] ?? $item['id'];
            if (! isset($seen[$key])) {
                $item['match_type'] = 'vector';
                $item['score'] = $item['similarity'] ?? 0;
                $merged[] = $item;
                $seen[$key] = true;
            }
        }

        // Add keyword results not already seen
        foreach ($keywordResults as $item) {
            $key = $item['decision_id'] ?? $item['id'];
            if (! isset($seen[$key])) {
                $item['match_type'] = 'keyword';
                $item['score'] = 0.5; // Default keyword score
                $merged[] = $item;
                $seen[$key] = true;
            }
        }

        // Sort by score descending
        usort($merged, fn ($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));

        return $merged;
    }

    /**
     * Search with query rewriting for improved results
     *
     * @param  string  $query  Original user query
     * @param  array  $options  Search options
     * @return array Search results
     *
     * @throws SearchException if search with rewriting fails
     */
    public function searchWithRewriting(string $query, array $options = []): array
    {
        try {
            Log::info('Search with rewriting initiated', [
                'query' => substr($query, 0, 100),
            ]);

            $rewriter = app(QueryRewriter::class);

            // Get 3 query variants
            $variants = $rewriter->rewrite($query, $options['language'] ?? 'hr');

            // Search with each variant
            $allResults = [];
            $searchType = $options['search_type'] ?? 'hybrid';

            foreach ($variants as $variant) {
                try {
                    $results = $this->search($variant, array_merge($options, [
                        'search_type' => $searchType,
                        'limit' => $options['limit'] ?? 10,
                    ]));

                    if ($results['success'] ?? false) {
                        $allResults = array_merge($allResults, $results['data'] ?? []);
                    }
                } catch (\Exception $e) {
                    Log::warning('Variant search failed, continuing', [
                        'variant' => $variant,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Deduplicate and finalize
            $deduped = $this->deduplicateResults($allResults);
            $limit = $options['limit'] ?? 10;
            $final = array_slice($deduped, 0, $limit);

            Log::info('Search with rewriting completed', [
                'query' => substr($query, 0, 100),
                'variants_count' => count($variants),
                'final_count' => count($final),
            ]);

            return [
                'success' => true,
                'data' => $final,
                'search_type' => 'rewritten',
                'count' => count($final),
                'variants_used' => $variants,
            ];

        } catch (\Exception $e) {
            Log::error('Search with rewriting failed', [
                'query' => substr($query, 0, 100),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new SearchException(
                'Search with query rewriting failed: '.$e->getMessage(),
                SearchException::UNEXPECTED_ERROR,
                $e
            );
        }
    }

    /**
     * Deduplicate search results
     */
    protected function deduplicateResults(array $results): array
    {
        $seen = [];
        $deduped = [];

        foreach ($results as $result) {
            $id = $result['decision_id'] ?? $result['id'] ?? null;

            if ($id && ! isset($seen[$id])) {
                $seen[$id] = true;
                $deduped[] = $result;
            }
        }

        // Sort by score
        usort($deduped, function ($a, $b) {
            $scoreA = $a['similarity'] ?? $a['score'] ?? 0;
            $scoreB = $b['similarity'] ?? $b['score'] ?? 0;

            return $scoreB <=> $scoreA;
        });

        return $deduped;
    }

    /**
     * Lookup decisions by multiple criteria
     *
     * @param  array  $criteria  Search criteria
     * @return array Decision information
     *
     * @throws SearchException if lookup fails
     */
    public function lookupByCriteria(array $criteria): array
    {
        try {
            Log::info('Lookup by criteria initiated', [
                'criteria' => $criteria,
            ]);

            $query = CourtDecision::with('documents');

            if (! empty($criteria['case_number'])) {
                $query->where('case_number', 'LIKE', "%{$criteria['case_number']}%");
            }

            if (! empty($criteria['court'])) {
                $query->where('court', 'LIKE', "%{$criteria['court']}%");
            }

            if (! empty($criteria['jurisdiction'])) {
                $query->where('jurisdiction', $criteria['jurisdiction']);
            }

            if (! empty($criteria['from_date'])) {
                $query->where('decision_date', '>=', $criteria['from_date']);
            }

            if (! empty($criteria['to_date'])) {
                $query->where('decision_date', '<=', $criteria['to_date']);
            }

            if (! empty($criteria['decision_type'])) {
                $query->where('decision_type', $criteria['decision_type']);
            }

            $limit = (int) ($criteria['limit'] ?? 20);
            $decisions = $query->orderBy('decision_date', 'desc')->limit($limit)->get();

            $results = $decisions->map(fn ($decision) => [
                'id' => $decision->id,
                'case_number' => $decision->case_number,
                'title' => $decision->title,
                'court' => $decision->court,
                'jurisdiction' => $decision->jurisdiction,
                'decision_date' => $decision->decision_date,
                'publication_date' => $decision->publication_date,
                'decision_type' => $decision->decision_type,
                'ecli' => $decision->ecli,
                'judge' => $decision->judge,
                'documents_count' => $decision->documents->count(),
                'summary' => $decision->documents->first()?->content ?
                    Str::limit($decision->documents->first()->content, 500) : null,
            ])->toArray();

            Log::info('Lookup by criteria completed', [
                'result_count' => count($results),
            ]);

            return $results;

        } catch (\Exception $e) {
            Log::error('Lookup by criteria failed', [
                'criteria' => $criteria,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new SearchException(
                'Decision lookup failed: '.$e->getMessage(),
                SearchException::UNEXPECTED_ERROR,
                $e
            );
        }
    }

    /**
     * Get decision by ID
     *
     * @param  string  $id  Decision ID
     * @param  bool  $includeContent  Include full content
     * @param  bool  $includeDocuments  Include documents
     * @return array Decision information
     *
     * @throws SearchException if decision not found
     */
    public function getById(string $id, bool $includeContent = false, bool $includeDocuments = true): array
    {
        try {
            Log::info('Get decision by ID', [
                'id' => $id,
                'include_content' => $includeContent,
                'include_documents' => $includeDocuments,
            ]);

            $arguments = [
                'id' => $id,
                'include_content' => $includeContent,
                'include_documents' => $includeDocuments,
            ];

            $result = $this->decisionGetTool()->handle($arguments);

            // Parse result
            $payload = $this->parseToolResult($result);

            if (empty($payload)) {
                throw new SearchException(
                    "Decision not found: {$id}",
                    SearchException::CORPUS_NOT_FOUND
                );
            }

            Log::info('Decision retrieved successfully', [
                'id' => $id,
            ]);

            return $payload;

        } catch (SearchException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Get decision by ID failed', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new SearchException(
                'Failed to retrieve decision: '.$e->getMessage(),
                SearchException::UNEXPECTED_ERROR,
                $e
            );
        }
    }

    /**
     * Parse tool result into array
     */
    protected function parseToolResult($result): array
    {
        $raw = null;
        if (is_object($result)) {
            if (property_exists($result, 'text') && is_string($result->text)) {
                $raw = $result->text;
            } elseif (property_exists($result, 'result')) {
                $raw = $result->result;
            }
        }

        if (is_string($raw)) {
            $payload = json_decode($raw, true) ?? [];
        } elseif (is_array($raw)) {
            $payload = $raw;
        } else {
            $payload = [];
        }

        // Normalize nested structure
        if (isset($payload['decision'])) {
            $decision = $payload['decision'] ?? [];
            $documents = $payload['documents']['items'] ?? ($payload['documents'] ?? []);

            return array_merge($decision, [
                'documents' => $documents,
            ]);
        }

        return $payload;
    }

    /**
     * Extract citations from a decision
     *
     * @param  string  $decisionId  Decision ID
     * @return array Extracted citations
     *
     * @throws SearchException if extraction fails
     */
    public function extractCitations(string $decisionId): array
    {
        try {
            $citationService = app(\App\Services\DecisionCitationService::class);

            return $citationService->extractCitations($decisionId);

        } catch (\Exception $e) {
            Log::error('Citation extraction failed', [
                'decision_id' => $decisionId,
                'error' => $e->getMessage(),
            ]);

            throw new SearchException(
                'Failed to extract citations: '.$e->getMessage(),
                SearchException::UNEXPECTED_ERROR,
                $e
            );
        }
    }

    /**
     * Find similar decisions
     *
     * @param  string  $decisionId  Decision ID
     * @param  array  $options  Search options
     * @return array Similar decisions
     *
     * @throws SearchException if search fails
     */
    public function findSimilar(string $decisionId, array $options = []): array
    {
        try {
            $citationService = app(\App\Services\DecisionCitationService::class);

            return $citationService->findSimilarDecisions($decisionId, $options);

        } catch (\Exception $e) {
            Log::error('Find similar decisions failed', [
                'decision_id' => $decisionId,
                'error' => $e->getMessage(),
            ]);

            throw new SearchException(
                'Failed to find similar decisions: '.$e->getMessage(),
                SearchException::UNEXPECTED_ERROR,
                $e
            );
        }
    }

    /**
     * Search decisions by cited law
     *
     * @param  string  $lawIdentifier  Law identifier
     * @param  string|null  $articleNumber  Article number
     * @param  array  $options  Additional options
     * @return array Decisions citing the law
     *
     * @throws SearchException if search fails
     */
    public function searchByCitedLaw(string $lawIdentifier, ?string $articleNumber = null, array $options = []): array
    {
        try {
            $citationService = app(\App\Services\DecisionCitationService::class);

            return $citationService->searchByCitedLaw($lawIdentifier, $articleNumber, $options);

        } catch (\Exception $e) {
            Log::error('Search by cited law failed', [
                'law' => $lawIdentifier,
                'article' => $articleNumber,
                'error' => $e->getMessage(),
            ]);

            throw new SearchException(
                'Failed to search by cited law: '.$e->getMessage(),
                SearchException::UNEXPECTED_ERROR,
                $e
            );
        }
    }

    /**
     * Analyze citations for a decision
     *
     * @param  string  $decisionId  Decision ID
     * @return array Citation analysis
     *
     * @throws SearchException if analysis fails
     */
    public function analyzeCitations(string $decisionId): array
    {
        try {
            $citationService = app(\App\Services\DecisionCitationService::class);

            return $citationService->analyzeDecisionCitations($decisionId);

        } catch (\Exception $e) {
            Log::error('Citation analysis failed', [
                'decision_id' => $decisionId,
                'error' => $e->getMessage(),
            ]);

            throw new SearchException(
                'Failed to analyze citations: '.$e->getMessage(),
                SearchException::UNEXPECTED_ERROR,
                $e
            );
        }
    }

    /**
     * Search with citation-aware ranking
     *
     * @param  string  $query  Search query
     * @param  array  $options  Search options
     * @return array Search results with citation analysis
     *
     * @throws SearchException if search fails
     */
    public function searchWithCitations(string $query, array $options = []): array
    {
        try {
            Log::info('Search with citations initiated', [
                'query' => substr($query, 0, 100),
            ]);

            $startTime = microtime(true);

            // Perform hybrid search
            $searchResults = $this->hybridSearch($query, $options);

            // Check for citations in query
            $citationDetector = app(\App\Services\LegalCitations\HrLegalCitationsDetector::class);
            $queryCitations = $citationDetector->detectAll($query);
            $hasCitations = count(array_filter($queryCitations)) > 0;

            // Boost results with matching citations
            if ($hasCitations && isset($searchResults['data'])) {
                foreach ($searchResults['data'] as &$result) {
                    $content = DB::table('court_decision_documents')
                        ->where('id', $result['id'])
                        ->value('content');

                    if ($content) {
                        $resultCitations = $citationDetector->detectAll($content);
                        $citationMatches = $this->countCitationMatches($queryCitations, $resultCitations);

                        if ($citationMatches > 0) {
                            $result['original_score'] = $result['score'] ?? 0;
                            $result['citation_boost'] = $citationMatches * 0.1;
                            $result['score'] = ($result['score'] ?? 0) + $result['citation_boost'];
                            $result['citation_matches'] = $citationMatches;
                        }

                        $result['has_citations'] = count(array_filter($resultCitations)) > 0;
                    }
                }

                // Re-sort by updated scores
                usort($searchResults['data'], fn ($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));
            }

            $searchResults['query_has_citations'] = $hasCitations;
            $searchResults['query_citations'] = $queryCitations;
            $searchResults['citation_enhancement_time'] = round(microtime(true) - $startTime - ($searchResults['performance']['total_time'] ?? 0), 3);

            Log::info('Search with citations completed', [
                'query' => substr($query, 0, 100),
                'has_citations' => $hasCitations,
            ]);

            return $searchResults;

        } catch (SearchException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Search with citations failed', [
                'query' => substr($query, 0, 100),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new SearchException(
                'Citation-aware search failed: '.$e->getMessage(),
                SearchException::UNEXPECTED_ERROR,
                $e
            );
        }
    }

    /**
     * Count matching citations
     */
    protected function countCitationMatches(array $queryCitations, array $resultCitations): int
    {
        $matches = 0;

        if (! empty($queryCitations['statutes']) && ! empty($resultCitations['statutes'])) {
            $queryCanonicals = array_column($queryCitations['statutes'], 'canonical');
            $resultCanonicals = array_column($resultCitations['statutes'], 'canonical');
            $matches += count(array_intersect($queryCanonicals, $resultCanonicals));
        }

        if (! empty($queryCitations['ecli']) && ! empty($resultCitations['ecli'])) {
            $queryEclis = array_column($queryCitations['ecli'], 'canonical');
            $resultEclis = array_column($resultCitations['ecli'], 'canonical');
            $matches += count(array_intersect($queryEclis, $resultEclis));
        }

        if (! empty($queryCitations['case_numbers']) && ! empty($resultCitations['case_numbers'])) {
            $queryCases = array_column($queryCitations['case_numbers'], 'canonical');
            $resultCases = array_column($resultCitations['case_numbers'], 'canonical');
            $matches += count(array_intersect($queryCases, $resultCases));
        }

        return $matches;
    }

    /**
     * Extract legal facts from a decision
     *
     * @param  string  $decisionId  Decision ID
     * @param  array  $options  Extraction options
     * @return array Extracted facts
     *
     * @throws SearchException if extraction fails
     */
    public function extractFacts(string $decisionId, array $options = []): array
    {
        try {
            $factService = app(\App\Services\FactExtractionService::class);

            return $factService->extractFacts($decisionId, $options);

        } catch (\Exception $e) {
            Log::error('Fact extraction failed', [
                'decision_id' => $decisionId,
                'error' => $e->getMessage(),
            ]);

            throw new SearchException(
                'Failed to extract facts: '.$e->getMessage(),
                SearchException::UNEXPECTED_ERROR,
                $e
            );
        }
    }

    /**
     * Compare legal facts between decisions
     *
     * @param  string  $decisionId1  First decision ID
     * @param  string  $decisionId2  Second decision ID
     * @return array Comparison results
     *
     * @throws SearchException if comparison fails
     */
    public function compareFacts(string $decisionId1, string $decisionId2): array
    {
        try {
            $factService = app(\App\Services\FactExtractionService::class);

            return $factService->compareDecisionFacts($decisionId1, $decisionId2);

        } catch (\Exception $e) {
            Log::error('Fact comparison failed', [
                'decision_1' => $decisionId1,
                'decision_2' => $decisionId2,
                'error' => $e->getMessage(),
            ]);

            throw new SearchException(
                'Failed to compare facts: '.$e->getMessage(),
                SearchException::UNEXPECTED_ERROR,
                $e
            );
        }
    }

    /**
     * Comprehensive analysis including citations and facts
     *
     * @param  string  $decisionId  Decision ID
     * @param  array  $options  Analysis options
     * @return array Complete analysis
     *
     * @throws SearchException if analysis fails
     */
    public function comprehensiveAnalysis(string $decisionId, array $options = []): array
    {
        try {
            Log::info('Comprehensive analysis initiated', [
                'decision_id' => $decisionId,
            ]);

            $startTime = microtime(true);

            // Extract citations
            $citations = $this->analyzeCitations($decisionId);

            // Extract facts
            $facts = $this->extractFacts($decisionId, $options);

            Log::info('Comprehensive analysis completed', [
                'decision_id' => $decisionId,
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return [
                'success' => $citations['success'] && $facts['success'],
                'decision_id' => $decisionId,
                'citations' => $citations,
                'facts' => $facts,
                'performance' => [
                    'total_time' => round(microtime(true) - $startTime, 3),
                ],
            ];

        } catch (SearchException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Comprehensive analysis failed', [
                'decision_id' => $decisionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new SearchException(
                'Comprehensive analysis failed: '.$e->getMessage(),
                SearchException::UNEXPECTED_ERROR,
                $e
            );
        }
    }
}
