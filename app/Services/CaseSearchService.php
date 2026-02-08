<?php

namespace App\Services;

use App\Exceptions\EmbeddingException;
use App\Exceptions\SearchException;
use App\Models\CaseDocument;
use App\Models\LegalCase;
use App\Services\Contracts\SearchServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CaseSearchService extends BaseSearchService implements SearchServiceInterface
{
    /**
     * Constructor
     *
     * Note: CaseSearchTool (MCP) was removed from constructor injection
     * because it depends on this service, creating a circular dependency that
     * caused infinite recursion and memory exhaustion (segfault).
     */
    public function __construct(
        OpenAIService $openAI
    ) {
        parent::__construct($openAI);
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
            Log::info('Case search initiated', [
                'query' => substr($query, 0, 100),
                'options' => $options,
                'service' => static::class,
            ]);

            $startTime = microtime(true);

            $searchType = $options['search_type'] ?? 'cases';
            $filters = $options['filters'] ?? [];

            // Merge top-level options into filters
            $filters = array_merge($filters, [
                'limit' => $options['limit'] ?? 10,
                'page' => $options['page'] ?? 1,
                'jurisdiction' => $options['jurisdiction'] ?? null,
            ]);

            $results = match ($searchType) {
                'vector' => $this->vectorSearch($query, $filters),
                'cases' => $this->searchCases($query, $filters),
                'documents' => $this->searchDocuments($query, $filters),
                default => throw new \InvalidArgumentException("Invalid search_type: {$searchType}"),
            };

            Log::info('Case search completed', [
                'query' => substr($query, 0, 100),
                'search_type' => $searchType,
                'result_count' => $results['data'] ? count($results['data']) : 0,
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $results;

        } catch (\InvalidArgumentException $e) {
            Log::warning('Invalid case search parameters', [
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
            Log::error('Unexpected error in case search', [
                'query' => substr($query, 0, 100),
                'options' => $options,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new SearchException(
                'Case search failed: '.$e->getMessage(),
                SearchException::UNEXPECTED_ERROR,
                $e
            );
        }
    }

    /**
     * Perform vector-based semantic search on case documents
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
            Log::info('Case vector search initiated', [
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

            Log::info('Case vector search completed', [
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
            Log::error('Embedding generation failed in case vector search', [
                'query' => substr($query, 0, 100),
                'error' => $e->getMessage(),
            ]);

            throw new SearchException(
                'Failed to generate search embedding: '.$e->getMessage(),
                SearchException::EMBEDDING_FAILED,
                $e
            );

        } catch (\Exception $e) {
            Log::error('Case vector search failed', [
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
            $queryBuilder = DB::table('cases_documents')
                ->select([
                    'cases_documents.id',
                    'cases_documents.case_id',
                    'cases_documents.doc_id',
                    'cases_documents.title',
                    'cases_documents.content',
                    'cases_documents.chunk_index',
                    'cases_documents.metadata',
                    'cases.case_number',
                    'cases.jurisdiction',
                    'cases.court',
                    'cases.status',
                ])
                ->leftJoin('cases', 'cases_documents.case_id', '=', 'cases.id');

            // Apply jurisdiction filter
            if ($jurisdiction) {
                $queryBuilder->where('cases.jurisdiction', $jurisdiction);
            }

            // Use pgvector for PostgreSQL
            if ($driver === 'pgsql') {
                $vectorLiteral = $this->toPgVectorLiteral($queryVector);
                $queryBuilder->selectRaw("1 - (cases_documents.embedding_vector <=> '{$vectorLiteral}'::vector) as similarity")
                    ->whereRaw("1 - (cases_documents.embedding_vector <=> '{$vectorLiteral}'::vector) >= ?", [$minSimilarity])
                    ->orderByRaw("cases_documents.embedding_vector <=> '{$vectorLiteral}'::vector")
                    ->limit($limit);
            } else {
                // Fallback for non-PostgreSQL databases
                $queryBuilder->whereNotNull('cases_documents.embedding_vector')->limit($limit * 3);
            }

            $results = $queryBuilder->get()->map(function ($case) use ($queryVector, $driver) {
                if ($driver !== 'pgsql') {
                    $caseVector = json_decode($case->embedding_vector ?? '[]', true);
                    $case->similarity = $this->cosineSimilarity($queryVector, $caseVector);
                }
                $case->metadata = json_decode($case->metadata ?? '{}', true);

                return $case;
            });

            // Filter and sort for non-PostgreSQL databases
            if ($driver !== 'pgsql') {
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
     * Search legal cases
     *
     * @param  string  $query  Search query
     * @param  array  $filters  Additional filters
     * @return array Search results
     *
     * @throws SearchException if case search fails
     */
    public function searchCases(string $query, array $filters = []): array
    {
        try {
            Log::info('Legal case search initiated', [
                'query' => substr($query, 0, 100),
                'filters' => $filters,
            ]);

            $queryBuilder = LegalCase::query();

            // Apply filters
            $this->applyCaseFilters($queryBuilder, $filters);

            // Free text search
            if (! empty($query)) {
                $queryBuilder->where(function ($q) use ($query) {
                    $q->where('title', 'like', '%'.$query.'%')
                        ->orWhere('description', 'like', '%'.$query.'%')
                        ->orWhere('case_number', 'like', '%'.$query.'%');
                });
            }

            // Pagination
            $limit = min((int) ($filters['limit'] ?? 10), 100);
            $page = (int) ($filters['page'] ?? 1);
            $offset = ($page - 1) * $limit;

            $total = $queryBuilder->count();

            $cases = $queryBuilder->select([
                'id', 'case_number', 'title', 'client_name', 'opponent_name',
                'court', 'jurisdiction', 'judge', 'filing_date', 'status', 'tags',
            ])
                ->orderBy('filing_date', 'desc')
                ->skip($offset)
                ->take($limit)
                ->get();

            Log::info('Legal case search completed', [
                'query' => substr($query, 0, 100),
                'total_results' => $total,
                'returned_results' => $cases->count(),
            ]);

            return [
                'success' => true,
                'search_type' => 'cases',
                'data' => $cases->toArray(),
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit),
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Legal case search failed', [
                'query' => substr($query, 0, 100),
                'filters' => $filters,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new SearchException(
                'Case search failed: '.$e->getMessage(),
                SearchException::UNEXPECTED_ERROR,
                $e
            );
        }
    }

    /**
     * Apply case search filters to query builder
     */
    protected function applyCaseFilters($queryBuilder, array $filters): void
    {
        try {
            if (! empty($filters['case_id'])) {
                $queryBuilder->where('id', $filters['case_id']);
            }

            if (! empty($filters['case_number'])) {
                $queryBuilder->where('case_number', 'like', '%'.$filters['case_number'].'%');
            }

            if (! empty($filters['client_name'])) {
                $queryBuilder->where('client_name', 'like', '%'.$filters['client_name'].'%');
            }

            if (! empty($filters['opponent_name'])) {
                $queryBuilder->where('opponent_name', 'like', '%'.$filters['opponent_name'].'%');
            }

            if (! empty($filters['court'])) {
                $queryBuilder->where('court', 'like', '%'.$filters['court'].'%');
            }

            if (! empty($filters['jurisdiction'])) {
                $queryBuilder->where('jurisdiction', $filters['jurisdiction']);
            }

            if (! empty($filters['status'])) {
                $queryBuilder->where('status', $filters['status']);
            }

            if (! empty($filters['tags'])) {
                $tags = array_map('trim', explode(',', $filters['tags']));
                foreach ($tags as $tag) {
                    if (! empty($tag)) {
                        $queryBuilder->whereJsonContains('tags', $tag);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to apply case filters', [
                'filters' => $filters,
                'error' => $e->getMessage(),
            ]);
            // Don't throw - allow query to proceed without filters
        }
    }

    /**
     * Search case documents
     *
     * @param  string  $query  Search query
     * @param  array  $filters  Additional filters
     * @return array Search results
     *
     * @throws SearchException if document search fails
     */
    public function searchDocuments(string $query, array $filters = []): array
    {
        try {
            Log::info('Case document search initiated', [
                'query' => substr($query, 0, 100),
                'filters' => $filters,
            ]);

            $queryBuilder = CaseDocument::query();

            // Filter by case_id if provided
            if (! empty($filters['case_id'])) {
                $queryBuilder->where('case_id', $filters['case_id']);
            }

            // Apply case-related filters via relationship
            $this->applyDocumentCaseFilters($queryBuilder, $filters);

            // Search within document content
            if (! empty($query)) {
                $queryBuilder->where(function ($q) use ($query) {
                    $q->where('content', 'like', '%'.$query.'%')
                        ->orWhere('title', 'like', '%'.$query.'%');
                });
            }

            if (! empty($filters['tags'])) {
                $tags = array_map('trim', explode(',', $filters['tags']));
                foreach ($tags as $tag) {
                    $queryBuilder->whereJsonContains('tags', $tag);
                }
            }

            // Pagination
            $limit = min((int) ($filters['limit'] ?? 10), 100);
            $page = (int) ($filters['page'] ?? 1);
            $offset = ($page - 1) * $limit;

            $total = $queryBuilder->count();

            // Select fields based on include_content flag
            if ($filters['include_content'] ?? false) {
                $documents = $queryBuilder->with('case:id,case_number,title')
                    ->orderBy('chunk_index')
                    ->skip($offset)
                    ->take($limit)
                    ->get();
            } else {
                $documents = $queryBuilder->select([
                    'id', 'case_id', 'doc_id', 'title', 'category', 'author',
                    'language', 'tags', 'chunk_index', 'metadata', 'source',
                ])
                    ->with('case:id,case_number,title')
                    ->orderBy('chunk_index')
                    ->skip($offset)
                    ->take($limit)
                    ->get();
            }

            Log::info('Case document search completed', [
                'query' => substr($query, 0, 100),
                'total_results' => $total,
                'returned_results' => $documents->count(),
            ]);

            return [
                'success' => true,
                'search_type' => 'documents',
                'data' => $documents->toArray(),
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit),
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Case document search failed', [
                'query' => substr($query, 0, 100),
                'filters' => $filters,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new SearchException(
                'Document search failed: '.$e->getMessage(),
                SearchException::UNEXPECTED_ERROR,
                $e
            );
        }
    }

    /**
     * Apply case-related filters to document query via relationship
     */
    protected function applyDocumentCaseFilters($queryBuilder, array $filters): void
    {
        try {
            if (! empty($filters['case_number']) || ! empty($filters['client_name']) ||
                ! empty($filters['opponent_name']) || ! empty($filters['court']) ||
                ! empty($filters['jurisdiction']) || ! empty($filters['status'])) {

                $queryBuilder->whereHas('case', function ($q) use ($filters) {
                    if (! empty($filters['case_number'])) {
                        $q->where('case_number', 'like', '%'.$filters['case_number'].'%');
                    }
                    if (! empty($filters['client_name'])) {
                        $q->where('client_name', 'like', '%'.$filters['client_name'].'%');
                    }
                    if (! empty($filters['opponent_name'])) {
                        $q->where('opponent_name', 'like', '%'.$filters['opponent_name'].'%');
                    }
                    if (! empty($filters['court'])) {
                        $q->where('court', 'like', '%'.$filters['court'].'%');
                    }
                    if (! empty($filters['jurisdiction'])) {
                        $q->where('jurisdiction', $filters['jurisdiction']);
                    }
                    if (! empty($filters['status'])) {
                        $q->where('status', $filters['status']);
                    }
                });
            }
        } catch (\Exception $e) {
            Log::error('Failed to apply document case filters', [
                'filters' => $filters,
                'error' => $e->getMessage(),
            ]);
            // Don't throw - allow query to proceed without filters
        }
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
            Log::info('Case search with rewriting initiated', [
                'query' => substr($query, 0, 100),
            ]);

            $rewriter = app(QueryRewriter::class);

            // Get 3 query variants
            $variants = $rewriter->rewrite($query, $options['language'] ?? 'hr');

            // Search with each variant
            $allResults = [];
            $searchType = $options['search_type'] ?? 'vector';

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
                    Log::warning('Case variant search failed, continuing', [
                        'variant' => $variant,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Deduplicate and finalize
            $deduped = $this->deduplicateResults($allResults);
            $limit = $options['limit'] ?? 10;
            $final = array_slice($deduped, 0, $limit);

            Log::info('Case search with rewriting completed', [
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
            Log::error('Case search with rewriting failed', [
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
        try {
            if (empty($results)) {
                return [];
            }

            $seen = [];
            $deduped = [];

            foreach ($results as $result) {
                if (! is_array($result)) {
                    Log::debug('Skipping non-array result in deduplication');

                    continue;
                }

                $id = $result['case_id'] ?? $result['id'] ?? null;

                if ($id && ! isset($seen[$id])) {
                    $seen[$id] = true;
                    $deduped[] = $result;
                }
            }

            // Sort by score
            usort($deduped, function ($a, $b) {
                try {
                    $scoreA = $a['similarity'] ?? $a['score'] ?? 0;
                    $scoreB = $b['similarity'] ?? $b['score'] ?? 0;

                    return $scoreB <=> $scoreA;
                } catch (\Exception $e) {
                    Log::debug('Score comparison failed during sort', [
                        'error' => $e->getMessage(),
                    ]);

                    return 0;
                }
            });

            return $deduped;

        } catch (\Exception $e) {
            Log::error('Failed to deduplicate results', [
                'result_count' => count($results),
                'error' => $e->getMessage(),
            ]);

            // Return original results on error
            return $results;
        }
    }

    /**
     * Generate embedding vector for a text query
     *
     * @param  string  $text  Text to generate embedding for
     * @return array|null Embedding vector or null on failure
     */
    protected function generateEmbedding(string $text): ?array
    {
        try {
            return $this->openAI->createEmbedding($text);
        } catch (\Exception $e) {
            Log::error('Failed to generate embedding', [
                'text' => substr($text, 0, 100),
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Convert vector array to PostgreSQL pgvector literal format
     *
     * @param  array  $vector  Embedding vector
     * @return string Vector in PostgreSQL format
     *
     * @throws SearchException if vector is invalid
     */
    protected function toPgVectorLiteral(array $vector): string
    {
        try {
            if (empty($vector)) {
                throw new SearchException(
                    'Cannot convert empty vector to pgvector literal',
                    SearchException::VECTOR_SEARCH_FAILED
                );
            }

            $parts = [];
            foreach ($vector as $v) {
                if (! is_numeric($v)) {
                    throw new SearchException(
                        'Vector contains non-numeric value',
                        SearchException::VECTOR_SEARCH_FAILED
                    );
                }
                $parts[] = rtrim(rtrim(number_format((float) $v, 8, '.', ''), '0'), '.');
            }

            return '['.implode(',', $parts).']';

        } catch (SearchException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to convert vector to pgvector literal', [
                'vector_length' => count($vector),
                'error' => $e->getMessage(),
            ]);
            throw new SearchException(
                'Vector conversion failed: '.$e->getMessage(),
                SearchException::VECTOR_SEARCH_FAILED,
                $e
            );
        }
    }

    /**
     * Calculate cosine similarity between two vectors
     *
     * @param  array  $a  First vector
     * @param  array  $b  Second vector
     * @return float Cosine similarity score (0-1)
     */
    protected function cosineSimilarity(array $a, array $b): float
    {
        try {
            if (empty($a) || empty($b)) {
                Log::debug('Empty vector in cosine similarity calculation');

                return 0.0;
            }

            if (count($a) !== count($b)) {
                Log::warning('Vector dimension mismatch in cosine similarity', [
                    'vec_a_length' => count($a),
                    'vec_b_length' => count($b),
                ]);

                return 0.0;
            }

            $dotProduct = 0.0;
            $normA = 0.0;
            $normB = 0.0;

            for ($i = 0; $i < count($a); $i++) {
                if (! is_numeric($a[$i]) || ! is_numeric($b[$i])) {
                    Log::warning('Non-numeric value in cosine similarity calculation', [
                        'index' => $i,
                    ]);

                    continue;
                }

                $valA = (float) $a[$i];
                $valB = (float) $b[$i];

                $dotProduct += $valA * $valB;
                $normA += $valA * $valA;
                $normB += $valB * $valB;
            }

            $normA = sqrt($normA);
            $normB = sqrt($normB);

            if ($normA == 0 || $normB == 0) {
                Log::debug('Zero norm in cosine similarity calculation');

                return 0.0;
            }

            $similarity = $dotProduct / ($normA * $normB);

            // Clamp to [0, 1] range
            return max(0.0, min(1.0, $similarity));

        } catch (\Exception $e) {
            Log::error('Cosine similarity calculation failed', [
                'vec_a_length' => count($a),
                'vec_b_length' => count($b),
                'error' => $e->getMessage(),
            ]);

            return 0.0;
        }
    }

    /**
     * Analyze case with various analysis types
     *
     * @param  string  $caseId  Case ID to analyze
     * @param  array  $options  Analysis options (analysis_type, depth, etc.)
     * @return array Analysis results
     */
    public function analyzeCase(string $caseId, array $options = []): array
    {
        $analysisType = $options['analysis_type'] ?? 'strength';

        try {
            Log::info('Case analysis initiated', [
                'case_id' => $caseId,
                'analysis_type' => $analysisType,
            ]);

            return match ($analysisType) {
                'strength' => $this->analyzeCaseStrength($caseId),
                'risk' => $this->analyzeCaseRisk($caseId),
                'timeline' => $this->analyzeCaseTimeline($caseId),
                'evidence' => $this->analyzeCaseEvidence($caseId),
                default => [
                    'case_id' => $caseId,
                    'analysis_type' => $analysisType,
                    'error' => 'Unknown analysis type',
                ],
            };

        } catch (\Exception $e) {
            Log::error('Case analysis failed', [
                'case_id' => $caseId,
                'error' => $e->getMessage(),
            ]);

            return [
                'case_id' => $caseId,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Analyze case strength using AI and case data
     *
     * @param  string  $caseId  Case ID to analyze
     * @return array Strength analysis results
     */
    private function analyzeCaseStrength(string $caseId): array
    {
        // Eager load only necessary relationships to avoid N+1 queries
        $case = LegalCase::select(['id', 'case_number', 'title', 'client_name', 'opponent_name', 'court', 'status'])
            ->with(['documents:id,case_id,title,category'])
            ->find($caseId);

        if (! $case) {
            throw new \Exception('Case not found');
        }

        // Build analysis prompt
        $prompt = $this->buildCaseStrengthPrompt($case);

        // Call OpenAI for analysis
        $response = $this->openAI->chat([
            ['role' => 'system', 'content' => 'You are a Croatian criminal defense attorney analyzing case strength. Respond with valid JSON only.'],
            ['role' => 'user', 'content' => $prompt],
        ], config('openai.models.chat', 'gpt-4o-mini'), ['temperature' => 0.3]);

        // Parse response
        $content = preg_replace('/^```json\s*|\s*```$/m', '', trim($response['choices'][0]['message']['content'] ?? '{}'));
        $analysis = json_decode($content, true) ?? [];

        return [
            'case_id' => $caseId,
            'strength_score' => $analysis['strength_score'] ?? 50,
            'strengths' => $analysis['strengths'] ?? [],
            'weaknesses' => $analysis['weaknesses'] ?? [],
            'overall_assessment' => $analysis['overall_assessment'] ?? 'Analysis unavailable',
        ];
    }

    /**
     * Build case strength analysis prompt
     *
     * @param  LegalCase  $case  Case to analyze
     * @return string Prompt for AI analysis
     */
    private function buildCaseStrengthPrompt(LegalCase $case): string
    {
        $docCount = $case->documents->count();
        $status = $case->status ?? 'unknown';

        return <<<PROMPT
Analyze case strength for: {$case->case_number}

Case details:
- Client: {$case->client_name}
- Opponent: {$case->opponent_name}
- Court: {$case->court}
- Status: {$status}
- Documents: {$docCount}

Provide JSON with:
1. "strength_score": 0-100 score
2. "strengths": Array of case strengths
3. "weaknesses": Array of case weaknesses
4. "overall_assessment": Text assessment

Return only valid JSON.
PROMPT;
    }

    /**
     * Analyze case risk and provide mitigation strategies
     *
     * @param  string  $caseId  Case ID to analyze
     * @return array Risk analysis results
     */
    private function analyzeCaseRisk(string $caseId): array
    {
        // Eager load only necessary fields to avoid N+1 queries
        $case = LegalCase::select(['id', 'case_number', 'filing_date', 'status'])
            ->withCount('documents')
            ->find($caseId);

        if (! $case) {
            throw new \Exception('Case not found');
        }

        // Analyze risks based on case data
        $risks = [];
        $mitigationStrategies = [];

        // Check for approaching deadlines
        if ($case->filing_date && $case->filing_date->diffInDays(now()) > 180) {
            $risks[] = 'Case has been pending for over 6 months';
            $mitigationStrategies[] = 'Schedule status conference with court';
        }

        // Check document completeness using withCount to avoid loading all documents
        $docCount = $case->documents_count;
        if ($docCount < 3) {
            $risks[] = 'Limited documentation available';
            $mitigationStrategies[] = 'Request additional documents from client';
        }

        // Determine risk level
        $riskLevel = count($risks) > 3 ? 'high' : (count($risks) > 1 ? 'medium' : 'low');

        return [
            'case_id' => $caseId,
            'risk_level' => $riskLevel,
            'risks' => $risks,
            'mitigation_strategies' => $mitigationStrategies,
        ];
    }

    /**
     * Analyze case timeline and identify key dates
     *
     * @param  string  $caseId  Case ID to analyze
     * @return array Timeline analysis results
     */
    private function analyzeCaseTimeline(string $caseId): array
    {
        // Eager load only necessary fields for timeline analysis
        $case = LegalCase::select(['id', 'case_number', 'filing_date'])
            ->with(['documents:id,case_id,title,created_at'])
            ->find($caseId);

        if (! $case) {
            return ['case_id' => $caseId, 'error' => 'Case not found'];
        }

        $events = [];

        // Add filing date if available
        if ($case->filing_date) {
            $events[] = [
                'date' => $case->filing_date->toDateString(),
                'event' => 'Case filed',
                'type' => 'filing',
            ];
        }

        // Add document dates - use map for better performance
        $docEvents = $case->documents
            ->filter(fn ($doc) => $doc->created_at)
            ->map(fn ($doc) => [
                'date' => $doc->created_at->toDateString(),
                'event' => 'Document added: '.($doc->title ?? 'Untitled'),
                'type' => 'document',
            ])
            ->all();

        $events = array_merge($events, $docEvents);

        // Sort events by date
        usort($events, function ($a, $b) {
            return strcmp($a['date'], $b['date']);
        });

        return [
            'case_id' => $caseId,
            'events' => $events,
            'gaps' => [],
            'key_dates' => array_column($events, 'date'),
        ];
    }

    /**
     * Analyze case evidence quality and completeness
     *
     * @param  string  $caseId  Case ID to analyze
     * @return array Evidence analysis results
     */
    private function analyzeCaseEvidence(string $caseId): array
    {
        // Eager load only necessary fields for evidence analysis
        $case = LegalCase::select(['id', 'case_number'])
            ->with(['documents:id,case_id,category'])
            ->find($caseId);

        if (! $case) {
            return ['case_id' => $caseId, 'error' => 'Case not found'];
        }

        // Filter documents by evidence category - use filter for efficiency
        $evidenceDocs = $case->documents->filter(function ($doc) {
            return in_array($doc->category, ['evidence', 'exhibit']);
        });

        // Determine evidence quality
        $evidenceCount = $evidenceDocs->count();
        $evidenceQuality = $evidenceCount > 5 ? 'good' : ($evidenceCount > 2 ? 'adequate' : 'limited');

        // Extract categories
        $categories = $evidenceDocs->pluck('category')->unique()->values()->toArray();

        // Generate recommendations
        $recommendations = [];
        if ($evidenceCount < 3) {
            $recommendations[] = 'Obtain additional documentary evidence';
        }
        if (empty($categories)) {
            $recommendations[] = 'Categorize existing documents as evidence';
        }
        $recommendations[] = 'Prepare exhibits for trial presentation';

        return [
            'case_id' => $caseId,
            'evidence_count' => $evidenceCount,
            'evidence_quality' => $evidenceQuality,
            'categories' => $categories,
            'admissibility_issues' => [],
            'recommendations' => $recommendations,
        ];
    }
}
