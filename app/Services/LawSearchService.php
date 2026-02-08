<?php

namespace App\Services;

use App\Exceptions\EmbeddingException;
use App\Exceptions\SearchException;
use App\Mcp\Tools\LawGetArticleTool;
use App\Models\IngestedLaw;
use App\Models\Law;
use App\Services\Contracts\SearchServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LawSearchService extends BaseSearchService implements SearchServiceInterface
{
    /**
     * Law article retrieval MCP tool (lazy-resolved to avoid circular dependency)
     */
    protected ?LawGetArticleTool $lawGetArticleTool = null;

    /**
     * Constructor
     *
     * Note: LawSearchTool (MCP) was removed from constructor injection
     * because it depends on this service, creating a circular dependency that
     * caused infinite recursion and memory exhaustion (segfault).
     */
    public function __construct(
        OpenAIService $openAI
    ) {
        parent::__construct($openAI);
    }

    /**
     * Get the LawGetArticleTool instance, lazily resolved.
     */
    protected function lawGetArticleTool(): LawGetArticleTool
    {
        return $this->lawGetArticleTool ??= app(LawGetArticleTool::class);
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
            Log::info('Law search initiated', [
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

            Log::info('Law search completed', [
                'query' => substr($query, 0, 100),
                'search_type' => $searchType,
                'result_count' => $results['count'] ?? 0,
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $results;

        } catch (\InvalidArgumentException $e) {
            Log::warning('Invalid law search parameters', [
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
            Log::error('Unexpected error in law search', [
                'query' => substr($query, 0, 100),
                'options' => $options,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new SearchException(
                'Law search failed: '.$e->getMessage(),
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
            Log::info('Law vector search initiated', [
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

            Log::info('Law vector search completed', [
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
            Log::error('Embedding generation failed in law vector search', [
                'query' => substr($query, 0, 100),
                'error' => $e->getMessage(),
            ]);

            throw new SearchException(
                'Failed to generate search embedding: '.$e->getMessage(),
                SearchException::EMBEDDING_FAILED,
                $e
            );

        } catch (\Exception $e) {
            Log::error('Law vector search failed', [
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
            $queryBuilder = DB::table('laws')
                ->select([
                    'id',
                    'doc_id',
                    'title',
                    'law_number',
                    'jurisdiction',
                    'content',
                    'chunk_index',
                    'metadata',
                    'effective_date',
                    'promulgation_date',
                ]);

            // Apply jurisdiction filter
            if ($jurisdiction) {
                $queryBuilder->where('jurisdiction', $jurisdiction);
            }

            // Try to use pgvector for PostgreSQL if available
            $usePgVector = false;
            if ($driver === 'pgsql') {
                try {
                    // Test if pgvector is available
                    $columns = DB::select("SELECT column_name FROM information_schema.columns WHERE table_name = 'laws' AND column_name = 'embedding'");
                    $usePgVector = ! empty($columns);
                } catch (\Exception $e) {
                    // pgvector not available, fall back to JSON similarity
                    $usePgVector = false;
                }
            }

            if ($usePgVector) {
                $vectorLiteral = $this->toPgVectorLiteral($queryVector);
                $queryBuilder->selectRaw("1 - (embedding <=> '{$vectorLiteral}'::vector) as similarity")
                    ->whereRaw("1 - (embedding <=> '{$vectorLiteral}'::vector) >= ?", [$minSimilarity])
                    ->orderByRaw("embedding <=> '{$vectorLiteral}'::vector")
                    ->limit($limit);
            } else {
                // Fallback: use JSON-based similarity
                $queryBuilder->whereNotNull('embedding_vector')->limit($limit * 3);
            }

            $results = $queryBuilder->get()->map(function ($law) use ($queryVector, $usePgVector) {
                if (! $usePgVector) {
                    // Calculate similarity using JSON vectors
                    $lawVector = json_decode($law->embedding_vector ?? '[]', true);
                    $law->similarity = $this->cosineSimilarity($queryVector, $lawVector);
                }
                $law->metadata = json_decode($law->metadata ?? '{}', true);

                return $law;
            });

            // Filter and sort when not using pgvector
            if (! $usePgVector) {
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
            Log::info('Law keyword search initiated', [
                'query' => substr($query, 0, 100),
                'filters' => $filters,
            ]);

            $queryBuilder = Law::query();

            // Apply filters
            $this->applyKeywordFilters($queryBuilder, $filters);

            // Free text search on title and content
            if (! empty($query)) {
                $queryBuilder->where(function ($q) use ($query) {
                    $q->where('title', 'like', '%'.$query.'%')
                        ->orWhere('content', 'like', '%'.$query.'%');
                });
            }

            // Pagination
            $limit = min((int) ($filters['limit'] ?? 10), 100);
            $page = (int) ($filters['page'] ?? 1);
            $offset = ($page - 1) * $limit;

            // Get total count for pagination
            $total = $queryBuilder->count();

            // Get results
            $laws = $queryBuilder->select([
                'id', 'doc_id', 'title', 'law_number', 'jurisdiction',
                'country', 'language', 'promulgation_date', 'effective_date',
                'repeal_date', 'tags', 'source_url', 'chunk_index',
            ])
                ->orderBy('doc_id')
                ->orderBy('chunk_index')
                ->skip($offset)
                ->take($limit)
                ->get();

            Log::info('Law keyword search completed', [
                'query' => substr($query, 0, 100),
                'total_results' => $total,
                'returned_results' => $laws->count(),
            ]);

            return [
                'success' => true,
                'data' => $laws->toArray(),
                'search_type' => 'keyword',
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit),
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Law keyword search failed', [
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
    protected function applyKeywordFilters($queryBuilder, array $filters): void
    {
        try {
            if (! empty($filters['doc_id'])) {
                $queryBuilder->where('doc_id', $filters['doc_id']);
            }

            if (! empty($filters['law_number'])) {
                $queryBuilder->where('law_number', 'like', '%'.$filters['law_number'].'%');
            }

            if (! empty($filters['jurisdiction'])) {
                $queryBuilder->where('jurisdiction', $filters['jurisdiction']);
            }

            if (! empty($filters['country'])) {
                $queryBuilder->where('country', $filters['country']);
            }

            if (! empty($filters['language'])) {
                $queryBuilder->where('language', $filters['language']);
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
            Log::error('Failed to apply keyword filters', [
                'filters' => $filters,
                'error' => $e->getMessage(),
            ]);
            // Don't throw - allow query to proceed without filters
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
            Log::info('Law hybrid search initiated', [
                'query' => substr($query, 0, 100),
                'filters' => $filters,
            ]);

            // Execute both searches in parallel
            $vectorResults = $this->vectorSearch($query, $filters);
            $keywordResults = $this->keywordSearch($query, $filters);

            // Merge and deduplicate
            $merged = $this->mergeSearchResults($vectorResults['data'], $keywordResults['data']);

            Log::info('Law hybrid search completed', [
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
            Log::error('Law hybrid search failed', [
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
        try {
            $merged = [];
            $seen = [];

            // Vector results get higher weight
            foreach ($vectorResults as $item) {
                if (! is_array($item)) {
                    Log::debug('Skipping non-array item in merge (vector)', [
                        'type' => gettype($item),
                    ]);

                    continue;
                }

                $key = $item['doc_id'] ?? $item['id'] ?? null;
                if ($key && ! isset($seen[$key])) {
                    $item['match_type'] = 'vector';
                    $item['score'] = $item['similarity'] ?? 0;
                    $merged[] = $item;
                    $seen[$key] = true;
                }
            }

            // Add keyword results not already seen
            foreach ($keywordResults as $item) {
                if (! is_array($item)) {
                    Log::debug('Skipping non-array item in merge (keyword)', [
                        'type' => gettype($item),
                    ]);

                    continue;
                }

                $key = $item['doc_id'] ?? $item['id'] ?? null;
                if ($key && ! isset($seen[$key])) {
                    $item['match_type'] = 'keyword';
                    $item['score'] = 0.5; // Default keyword score
                    $merged[] = $item;
                    $seen[$key] = true;
                }
            }

            // Sort by score descending
            usort($merged, function ($a, $b) {
                try {
                    return ($b['score'] ?? 0) <=> ($a['score'] ?? 0);
                } catch (\Exception $e) {
                    Log::debug('Score comparison failed during merge sort', [
                        'error' => $e->getMessage(),
                    ]);

                    return 0;
                }
            });

            return $merged;

        } catch (\Exception $e) {
            Log::error('Failed to merge search results', [
                'vector_count' => count($vectorResults),
                'keyword_count' => count($keywordResults),
                'error' => $e->getMessage(),
            ]);

            // Return vector results as fallback
            return $vectorResults;
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
            Log::info('Law search with rewriting initiated', [
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
                    Log::warning('Law variant search failed, continuing', [
                        'variant' => $variant,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Deduplicate and finalize
            $deduped = $this->deduplicateResults($allResults);
            $limit = $options['limit'] ?? 10;
            $final = array_slice($deduped, 0, $limit);

            Log::info('Law search with rewriting completed', [
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
            Log::error('Law search with rewriting failed', [
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

                $id = $result['doc_id'] ?? $result['id'] ?? null;

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
     * Lookup law by law number
     *
     * @param  string  $lawNumber  Law number to lookup
     * @param  string|null  $jurisdiction  Optional jurisdiction filter
     * @return array Law information
     *
     * @throws SearchException if lookup fails
     */
    public function lookupByNumber(string $lawNumber, ?string $jurisdiction = null): array
    {
        try {
            Log::info('Law lookup by number initiated', [
                'law_number' => $lawNumber,
                'jurisdiction' => $jurisdiction,
            ]);

            $query = IngestedLaw::with('laws')
                ->where('law_number', 'LIKE', "%{$lawNumber}%");

            if ($jurisdiction) {
                $query->where('jurisdiction', $jurisdiction);
            }

            $law = $query->first();

            if (! $law) {
                Log::warning('Law not found by number', [
                    'law_number' => $lawNumber,
                    'jurisdiction' => $jurisdiction,
                ]);

                throw new SearchException(
                    "Law not found: {$lawNumber}",
                    SearchException::CORPUS_NOT_FOUND
                );
            }

            $result = [
                'doc_id' => $law->doc_id,
                'title' => $law->title,
                'law_number' => $law->law_number,
                'jurisdiction' => $law->jurisdiction,
                'country' => $law->country,
                'language' => $law->language,
                'keywords' => $law->keywords,
                'aliases' => $law->aliases,
                'metadata' => $law->metadata,
                'chunks' => $law->laws->map(fn ($chunk) => [
                    'chunk_index' => $chunk->chunk_index,
                    'content' => $chunk->content,
                    'chapter' => $chunk->chapter,
                    'section' => $chunk->section,
                    'tags' => $chunk->tags,
                    'metadata' => $chunk->metadata,
                ])->toArray(),
            ];

            Log::info('Law lookup by number completed', [
                'law_number' => $lawNumber,
                'doc_id' => $law->doc_id,
            ]);

            return $result;

        } catch (SearchException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Law lookup by number failed', [
                'law_number' => $lawNumber,
                'jurisdiction' => $jurisdiction,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new SearchException(
                'Failed to lookup law by number: '.$e->getMessage(),
                SearchException::UNEXPECTED_ERROR,
                $e
            );
        }
    }

    /**
     * Lookup law by document ID
     *
     * @param  string  $docId  Document ID
     * @param  int|null  $chunkIndex  Optional chunk index
     * @param  array  $filters  Additional filters
     * @return array Law information
     *
     * @throws SearchException if lookup fails
     */
    public function lookupByDocId(string $docId, ?int $chunkIndex = null, array $filters = []): array
    {
        try {
            Log::info('Law lookup by doc ID initiated', [
                'doc_id' => $docId,
                'chunk_index' => $chunkIndex,
                'filters' => $filters,
            ]);

            $arguments = ['doc_id' => $docId];

            if ($chunkIndex !== null) {
                $arguments['number'] = $chunkIndex;
            }
            if (isset($filters['chapter'])) {
                $arguments['chapter'] = $filters['chapter'];
            }
            if (isset($filters['section'])) {
                $arguments['section'] = $filters['section'];
            }

            $result = $this->lawGetArticleTool()->handle($arguments);

            // Parse tool result
            $data = $this->parseToolResult($result);

            if (empty($data)) {
                throw new SearchException(
                    "Law not found: {$docId}",
                    SearchException::CORPUS_NOT_FOUND
                );
            }

            Log::info('Law lookup by doc ID completed', [
                'doc_id' => $docId,
            ]);

            return $data;

        } catch (SearchException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Law lookup by doc ID failed', [
                'doc_id' => $docId,
                'chunk_index' => $chunkIndex,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new SearchException(
                'Failed to lookup law by document ID: '.$e->getMessage(),
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
        try {
            if (is_object($result) && property_exists($result, 'text')) {
                return json_decode($result->text, true) ?? [];
            }

            if (is_string($result)) {
                return json_decode($result, true) ?? [];
            }

            if (is_array($result)) {
                return $result;
            }

            return [];

        } catch (\Exception $e) {
            Log::error('Failed to parse tool result', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Analyze legal concept with various operations
     *
     * @param  string  $concept  Legal concept to analyze
     * @param  array  $options  Analysis options (operation, jurisdiction, etc.)
     * @return array Analysis results
     */
    public function analyzeConcept(string $concept, array $options = []): array
    {
        $operation = $options['operation'] ?? 'define';

        return match ($operation) {
            'define' => [
                'concept' => $concept,
                'definition' => $this->defineConceptWithAI($concept),
                'legal_basis' => $this->analyzeDoctrineOrigin($concept)['legal_basis'] ?? [],
                'examples' => $this->findConceptPrecedents($concept, 3),
                'jurisdiction' => $options['jurisdiction'] ?? 'HR',
            ],
            'related' => [
                'concept' => $concept,
                'related_concepts' => $this->findRelatedConcepts($concept),
                'count' => count($this->findRelatedConcepts($concept)),
            ],
            'precedents' => [
                'concept' => $concept,
                'precedents' => $this->findConceptPrecedents($concept),
                'count' => count($this->findConceptPrecedents($concept)),
            ],
            'doctrine' => array_merge(
                ['concept' => $concept],
                $this->analyzeDoctrineOrigin($concept)
            ),
            default => [
                'concept' => $concept,
                'operation' => $operation,
                'error' => 'Unknown operation',
            ],
        };
    }

    /**
     * Interpret statute with various operations
     *
     * @param  string  $statute  Statute reference to interpret
     * @param  array  $options  Interpretation options (operation, method, etc.)
     * @return array Interpretation results
     */
    public function interpretStatute(string $statute, array $options = []): array
    {
        $operation = $options['operation'] ?? 'history';

        return match ($operation) {
            'history' => $this->analyzeStatutoryHistory($statute),
            'construction' => array_merge(
                [
                    'statute' => $statute,
                    'method' => $options['method'] ?? 'textualist',
                ],
                $this->performStatutoryConstruction($statute, $options['method'] ?? 'textualist')
            ),
            'conflicts' => $this->resolveStatutoryConflicts($statute),
            'framework' => $this->mapRegulatoryFramework($statute),
            default => [
                'statute' => $statute,
                'operation' => $operation,
                'error' => 'Unknown operation',
            ],
        };
    }

    /**
     * Define legal concept using AI
     *
     * @param  string  $concept  Legal concept to define
     * @return string AI-generated definition (2-3 sentences)
     */
    private function defineConceptWithAI(string $concept): string
    {
        try {
            $prompt = "Definiraj pravni pojam '{$concept}' u kontekstu hrvatskog kaznenog prava. ".
                      'Daj preciznu definiciju u 2-3 rečenice.';

            $response = $this->openAI->chat([
                ['role' => 'system', 'content' => 'Ti si stručnjak za hrvatsko kazneno pravo.'],
                ['role' => 'user', 'content' => $prompt],
            ]);

            $content = $response['choices'][0]['message']['content'] ?? '';

            // Try to parse as JSON first (for structured responses)
            $decoded = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($decoded['definition'])) {
                return $decoded['definition'];
            }

            // Return raw content if not JSON
            return trim($content) ?: "Definicija pojma {$concept}";

        } catch (\Exception $e) {
            Log::error('Failed to define concept with AI', [
                'concept' => $concept,
                'error' => $e->getMessage(),
            ]);

            return "Pravni pojam: {$concept}";
        }
    }

    /**
     * Find related legal concepts using vector similarity search
     *
     * @param  string  $concept  Legal concept to find relations for
     * @param  int  $limit  Maximum number of related concepts to return
     * @return array Related concepts with relevance scores
     */
    private function findRelatedConcepts(string $concept, int $limit = 5): array
    {
        try {
            $lawVectorStore = app(\App\Services\LawVectorStoreService::class);

            // Generate embedding for the concept to search semantically
            $embedding = $this->openAI->createEmbedding($concept);

            // Search for related legal texts using vector similarity
            $results = $lawVectorStore->search($embedding, ['limit' => $limit]);

            $relatedConcepts = [];
            foreach ($results as $result) {
                // Extract potential related concepts from the content
                $content = $result['content'] ?? '';
                $score = $result['similarity'] ?? 0.0;

                // Simple concept extraction (can be enhanced with NLP)
                if (preg_match_all('/načelo\s+\w+|pravo\s+\w+|\w+\s+doktrini?/ui', $content, $matches)) {
                    foreach ($matches[0] as $match) {
                        $cleanMatch = trim($match);
                        if (stripos($cleanMatch, $concept) === false) {
                            $relatedConcepts[$cleanMatch] = max(
                                $relatedConcepts[$cleanMatch] ?? 0,
                                $score
                            );
                        }
                    }
                }
            }

            // Sort by relevance score and limit
            arsort($relatedConcepts);
            $relatedConcepts = array_slice($relatedConcepts, 0, $limit, true);

            return array_map(
                fn ($concept, $score) => [
                    'concept' => $concept,
                    'relevance_score' => round($score, 3),
                ],
                array_keys($relatedConcepts),
                $relatedConcepts
            );

        } catch (\Exception $e) {
            Log::error('Failed to find related concepts', [
                'concept' => $concept,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Find court precedents citing this legal concept
     *
     * @param  string  $concept  Legal concept to find precedents for
     * @param  int  $limit  Maximum number of precedents to return
     * @return array Precedent cases with excerpts and scores
     */
    private function findConceptPrecedents(string $concept, int $limit = 5): array
    {
        try {
            $decisionVectorStore = app(\App\Services\CourtDecisionVectorStoreService::class);

            // Search for court decisions mentioning this concept
            $results = $decisionVectorStore->search($concept, $limit);

            return array_map(function ($result) use ($concept) {
                $content = $result['content'] ?? '';
                $metadata = $result['metadata'] ?? [];

                // Extract relevant excerpt around the concept
                $excerpt = $this->extractRelevantExcerpt($content, $concept, 200);

                return [
                    'decision_id' => $metadata['decision_id'] ?? 'unknown',
                    'court' => $metadata['court'] ?? 'unknown',
                    'date' => $metadata['date'] ?? null,
                    'excerpt' => $excerpt,
                    'relevance_score' => round($result['similarity'] ?? 0.0, 3),
                ];
            }, $results);

        } catch (\Exception $e) {
            Log::error('Failed to find concept precedents', [
                'concept' => $concept,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Analyze the doctrinal origin of a legal concept
     *
     * @param  string  $concept  Legal concept to analyze
     * @return array Doctrine analysis with origin, type, and application
     */
    private function analyzeDoctrineOrigin(string $concept): array
    {
        try {
            $prompt = "Analiziraj doktrinalno podrijetlo pravnog pojma '{$concept}' u hrvatskom pravu. ".
                      "Vrati JSON sa sljedećim poljima:\n".
                      "- doctrine_type: (procedural, substantive, constitutional, evidentiary)\n".
                      "- origin: izvor doktrine (npr. 'Continental European legal tradition', 'US Common Law', 'Ustav RH Članak X')\n".
                      "- application: kako se primjenjuje u hrvatskom kaznenom pravu\n".
                      "- exceptions: iznimke od primjene\n".
                      "- croatian_equivalent: hrvatski naziv/ekvivalent\n".
                      "- legal_basis: niz zakonskih osnova (npr. ['ZKP Članak 9', 'Ustav RH Članak 29'])";

            $response = $this->openAI->chat([
                ['role' => 'system', 'content' => 'Ti si stručnjak za hrvatsko kazneno pravo i pravnu doktrinu. Odgovori isključivo u JSON formatu.'],
                ['role' => 'user', 'content' => $prompt],
            ]);

            $content = $response['choices'][0]['message']['content'] ?? '{}';

            // Clean markdown code blocks if present
            $content = preg_replace('/^```json\s*|\s*```$/m', '', $content);

            $decoded = json_decode(trim($content), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON response from AI');
            }

            return [
                'doctrine_type' => $decoded['doctrine_type'] ?? 'procedural',
                'origin' => $decoded['origin'] ?? 'Croatian legal tradition',
                'application' => $decoded['application'] ?? '',
                'exceptions' => $decoded['exceptions'] ?? [],
                'croatian_equivalent' => $decoded['croatian_equivalent'] ?? $concept,
                'legal_basis' => $decoded['legal_basis'] ?? [],
            ];

        } catch (\Exception $e) {
            Log::error('Failed to analyze doctrine origin', [
                'concept' => $concept,
                'error' => $e->getMessage(),
            ]);

            return [
                'doctrine_type' => 'procedural',
                'origin' => 'Croatian legal tradition',
                'application' => 'Applied in Croatian criminal proceedings',
                'exceptions' => [],
                'croatian_equivalent' => $concept,
                'legal_basis' => [],
            ];
        }
    }

    /**
     * Extract relevant excerpt around a concept from text
     *
     * @param  string  $text  Full text to extract from
     * @param  string  $concept  Concept to find in text
     * @param  int  $maxLength  Maximum excerpt length
     * @return string Relevant excerpt
     */
    private function extractRelevantExcerpt(string $text, string $concept, int $maxLength = 200): string
    {
        $pos = stripos($text, $concept);

        if ($pos === false) {
            // Concept not found, return beginning of text
            return mb_substr($text, 0, $maxLength).'...';
        }

        // Calculate start position (center the concept)
        $start = max(0, $pos - ($maxLength / 2));
        $excerpt = mb_substr($text, (int) $start, $maxLength);

        // Trim to word boundaries
        if ($start > 0) {
            $excerpt = preg_replace('/^\S+\s+/', '...', $excerpt);
        }
        if (mb_strlen($text) > $start + $maxLength) {
            $excerpt = preg_replace('/\s+\S+$/', '...', $excerpt);
        }

        return $excerpt;
    }

    /**
     * Analyze statutory history - track amendments and version history
     *
     * @param  string  $articleRef  Article reference (e.g., "ZKP Članak 9")
     * @return array History analysis with amendments, legislative intent, versions
     */
    private function analyzeStatutoryHistory(string $articleRef): array
    {
        try {
            // Extract law code and article number from reference
            $lawCode = $this->extractLawCode($articleRef);
            $articleNumber = $this->extractArticleNumber($articleRef);

            // Get article content
            $article = $this->lawGetArticleTool()->getArticle($lawCode, $articleNumber);

            // Use OpenAI to analyze amendments and legislative history
            $prompt = "Analiziraj zakonodavnu povijest i izmjene za '{$articleRef}'. ".
                     'Identificiraj sve izmjene zakona, zakonodavni cilj i trenutnu verziju. '.
                     'Odgovori u JSON formatu sa poljima: amendments (array sa datumom i opisom), '.
                     'legislative_intent (string), current_version (string).';

            $response = $this->openAI->chat([
                ['role' => 'system', 'content' => 'Ti si stručnjak za hrvatsko zakonodavstvo i zakonodavnu povijest.'],
                ['role' => 'user', 'content' => $prompt],
            ]);

            $content = $response['choices'][0]['message']['content'] ?? '{}';

            // Parse JSON response
            $parsed = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $amendments = $parsed['amendments'] ?? [];
                $legislativeIntent = $parsed['legislative_intent'] ?? 'Zakonodavni cilj nije dostupan';
                $currentVersion = $parsed['current_version'] ?? $article['content'] ?? '';
            } else {
                $amendments = [];
                $legislativeIntent = 'Zakonodavni cilj nije dostupan';
                $currentVersion = $article['content'] ?? '';
            }

            return [
                'statute' => $articleRef,
                'title' => $article['title'] ?? $articleRef,
                'original_text' => $article['content'] ?? '',
                'amendments' => $amendments,
                'legislative_intent' => $legislativeIntent,
                'current_version' => $currentVersion,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to analyze statutory history', [
                'article_ref' => $articleRef,
                'error' => $e->getMessage(),
            ]);

            return [
                'statute' => $articleRef,
                'title' => $articleRef,
                'original_text' => '',
                'amendments' => [],
                'legislative_intent' => 'Zakonodavni cilj nije dostupan',
                'current_version' => '',
            ];
        }
    }

    /**
     * Perform statutory construction - AI-powered interpretation using specified method
     *
     * @param  string  $articleRef  Article reference (e.g., "ZKP Članak 9")
     * @param  string  $method  Interpretation method (textualist, systematic, teleological)
     * @return array Construction analysis
     */
    private function performStatutoryConstruction(string $articleRef, string $method): array
    {
        try {
            // Get article content
            $lawCode = $this->extractLawCode($articleRef);
            $articleNumber = $this->extractArticleNumber($articleRef);
            $article = $this->lawGetArticleTool()->getArticle($lawCode, $articleNumber);

            $articleText = $article['content'] ?? '';

            // Method-specific prompts
            $methodPrompts = [
                'textualist' => 'Analiziraj literal meaning teksta zakona. Fokusiraj se na gramatiku i ključne pojmove.',
                'systematic' => 'Analiziraj članak u kontekstu cijelog zakonskog sustava. Fokusiraj se na povezanost s drugim članovima.',
                'teleological' => 'Analiziraj svrhu i cilj zakonske odredbe. Fokusiraj se na ratio legis.',
            ];

            $methodInstruction = $methodPrompts[$method] ?? $methodPrompts['textualist'];

            $prompt = "Interpretiraj '{$articleRef}' koristeći {$method} metodu. ".
                     "{$methodInstruction} ".
                     "Tekst članka: {$articleText}. ".
                     'Daj detaljnu analizu koja uključuje: plain_meaning (jednostavno značenje), '.
                     'grammatical_analysis (gramatička analiza), key_terms (ključni pojmovi), '.
                     'interpretation (tumačenje), ambiguities (nejasnoće).';

            $response = $this->openAI->chat([
                ['role' => 'system', 'content' => 'Ti si stručnjak za tumačenje hrvatskog kaznenog prava.'],
                ['role' => 'user', 'content' => $prompt],
            ]);

            $interpretation = $response['choices'][0]['message']['content'] ?? '';

            // Extract key terms from article text
            $keyTerms = $this->extractKeyTerms($articleText);

            // Identify ambiguities
            $ambiguities = $this->identifyAmbiguities($articleText, $interpretation);

            return [
                'plain_meaning' => $this->extractPlainMeaning($interpretation),
                'grammatical_analysis' => $this->extractGrammaticalAnalysis($interpretation),
                'key_terms' => $keyTerms,
                'interpretation' => $interpretation,
                'ambiguities' => $ambiguities,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to perform statutory construction', [
                'article_ref' => $articleRef,
                'method' => $method,
                'error' => $e->getMessage(),
            ]);

            return [
                'plain_meaning' => 'Analiza nije dostupna',
                'grammatical_analysis' => 'Gramatička analiza nije dostupna',
                'key_terms' => [],
                'interpretation' => 'Tumačenje nije dostupno',
                'ambiguities' => [],
            ];
        }
    }

    /**
     * Resolve statutory conflicts - find conflicting provisions using vector search
     *
     * @param  string  $articleRef  Article reference (e.g., "ZKP Članak 9")
     * @return array Conflicts analysis with resolution strategy
     */
    private function resolveStatutoryConflicts(string $articleRef): array
    {
        try {
            // Get article content
            $lawCode = $this->extractLawCode($articleRef);
            $articleNumber = $this->extractArticleNumber($articleRef);
            $article = $this->lawGetArticleTool()->getArticle($lawCode, $articleNumber);

            $articleText = $article['content'] ?? '';

            // Use vector search to find potentially conflicting articles
            $searchResults = $this->vectorSearch($articleText, [
                'limit' => 10,
                'min_similarity' => 0.7,
            ]);

            $conflicts = [];

            // Analyze search results for actual conflicts
            foreach ($searchResults['results'] ?? [] as $result) {
                $potentialConflict = $result['content'] ?? '';
                $similarity = $result['similarity'] ?? 0;

                // Use AI to determine if there's an actual conflict
                if ($this->detectConflict($articleText, $potentialConflict)) {
                    $conflicts[] = [
                        'conflicting_statute' => $result['reference'] ?? 'Nepoznat članak',
                        'conflict_type' => $this->determineConflictType($articleText, $potentialConflict),
                        'similarity_score' => $similarity,
                    ];
                }
            }

            // Generate resolution strategy
            $resolution = $this->generateConflictResolution($articleRef, $conflicts);

            return [
                'primary_statute' => $articleRef,
                'conflicts' => $conflicts,
                'resolution' => $resolution,
                'recommended_interpretation' => $this->generateRecommendedInterpretation($articleRef, $conflicts),
            ];

        } catch (\Exception $e) {
            Log::error('Failed to resolve statutory conflicts', [
                'article_ref' => $articleRef,
                'error' => $e->getMessage(),
            ]);

            return [
                'primary_statute' => $articleRef,
                'conflicts' => [],
                'resolution' => 'Analiza sukoba nije dostupna',
                'recommended_interpretation' => 'Preporuka nije dostupna',
            ];
        }
    }

    /**
     * Map regulatory framework - map related regulations and hierarchy
     *
     * @param  string  $articleRef  Article reference (e.g., "ZKP Članak 9")
     * @return array Framework map with hierarchy
     */
    private function mapRegulatoryFramework(string $articleRef): array
    {
        try {
            // Get article content
            $lawCode = $this->extractLawCode($articleRef);
            $articleNumber = $this->extractArticleNumber($articleRef);
            $article = $this->lawGetArticleTool()->getArticle($lawCode, $articleNumber);

            // Build constitutional basis (e.g., for ZKP articles, link to Ustav RH)
            $constitutionalBasis = $this->findConstitutionalBasis($articleRef);

            // Find implementing regulations
            $implementingRegulations = $this->findImplementingRegulations($articleRef);

            // Find related provisions in same law
            $relatedProvisions = $this->findRelatedProvisions($lawCode, $articleNumber);

            // Build hierarchy
            $hierarchy = $this->buildLegalHierarchy($articleRef);

            return [
                'statute' => $articleRef,
                'title' => $article['title'] ?? $articleRef,
                'framework' => [
                    'primary_law' => [
                        'code' => $lawCode,
                        'article' => $articleNumber,
                        'full_reference' => $articleRef,
                    ],
                    'constitutional_basis' => $constitutionalBasis,
                    'implementing_regulations' => $implementingRegulations,
                    'related_provisions' => $relatedProvisions,
                ],
                'hierarchy' => $hierarchy,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to map regulatory framework', [
                'article_ref' => $articleRef,
                'error' => $e->getMessage(),
            ]);

            return [
                'statute' => $articleRef,
                'title' => $articleRef,
                'framework' => [
                    'primary_law' => [],
                    'constitutional_basis' => [],
                    'implementing_regulations' => [],
                    'related_provisions' => [],
                ],
                'hierarchy' => [],
            ];
        }
    }

    /**
     * Extract law code from article reference
     *
     * @param  string  $articleRef  Article reference (e.g., "ZKP Članak 9")
     * @return string Law code
     */
    private function extractLawCode(string $articleRef): string
    {
        if (preg_match('/^([A-Z]+(?:\s+[A-Z]+)*)/i', $articleRef, $matches)) {
            return trim($matches[1]);
        }

        return 'ZKP'; // Default fallback
    }

    /**
     * Extract article number from article reference
     *
     * @param  string  $articleRef  Article reference (e.g., "ZKP Članak 9")
     * @return string Article number
     */
    private function extractArticleNumber(string $articleRef): string
    {
        if (preg_match('/Članak\s+(\d+)/i', $articleRef, $matches)) {
            return $matches[1];
        }

        return '1'; // Default fallback
    }

    /**
     * Extract key terms from article text
     *
     * @param  string  $text  Article text
     * @return array Key terms
     */
    private function extractKeyTerms(string $text): array
    {
        // Simple extraction - look for capitalized terms and legal phrases
        $terms = [];

        if (preg_match_all('/\b[A-ZČĆŠĐŽ][a-zčćšđž]+(?:\s+[a-zčćšđž]+)*\b/', $text, $matches)) {
            $terms = array_unique($matches[0]);
        }

        return array_slice($terms, 0, 10); // Limit to 10 terms
    }

    /**
     * Identify ambiguities in article text
     *
     * @param  string  $articleText  Article text
     * @param  string  $interpretation  AI interpretation
     * @return array Ambiguities
     */
    private function identifyAmbiguities(string $articleText, string $interpretation): array
    {
        $ambiguities = [];

        // Look for ambiguous phrases in interpretation
        if (str_contains(strtolower($interpretation), 'nejasn') ||
            str_contains(strtolower($interpretation), 'može se tumačiti')) {
            $ambiguities[] = 'Višeznačne odredbe identificirane u tumačenju';
        }

        return $ambiguities;
    }

    /**
     * Extract plain meaning from interpretation
     *
     * @param  string  $interpretation  AI interpretation
     * @return string Plain meaning
     */
    private function extractPlainMeaning(string $interpretation): string
    {
        // Try to extract first sentence or paragraph as plain meaning
        if (preg_match('/^([^.]+\.)/', $interpretation, $matches)) {
            return trim($matches[1]);
        }

        return mb_substr($interpretation, 0, 200);
    }

    /**
     * Extract grammatical analysis from interpretation
     *
     * @param  string  $interpretation  AI interpretation
     * @return string Grammatical analysis
     */
    private function extractGrammaticalAnalysis(string $interpretation): string
    {
        // Return full interpretation as grammatical analysis
        return $interpretation;
    }

    /**
     * Detect if there's a conflict between two article texts
     *
     * @param  string  $text1  First article text
     * @param  string  $text2  Second article text
     * @return bool True if conflict detected
     */
    private function detectConflict(string $text1, string $text2): bool
    {
        // Simple heuristic - check for contradictory keywords
        $contradictions = [
            ['zabranjeno', 'dopušteno'],
            ['obvezno', 'nije obvezno'],
            ['mora', 'ne mora'],
        ];

        foreach ($contradictions as [$negative, $positive]) {
            if ((str_contains(strtolower($text1), $negative) && str_contains(strtolower($text2), $positive)) ||
                (str_contains(strtolower($text1), $positive) && str_contains(strtolower($text2), $negative))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine conflict type between articles
     *
     * @param  string  $text1  First article text
     * @param  string  $text2  Second article text
     * @return string Conflict type
     */
    private function determineConflictType(string $text1, string $text2): string
    {
        if (str_contains(strtolower($text1), 'zabranjeno') || str_contains(strtolower($text2), 'zabranjeno')) {
            return 'prohibition_conflict';
        }

        if (str_contains(strtolower($text1), 'obvezno') || str_contains(strtolower($text2), 'obvezno')) {
            return 'obligation_conflict';
        }

        return 'general_conflict';
    }

    /**
     * Generate conflict resolution strategy
     *
     * @param  string  $articleRef  Article reference
     * @param  array  $conflicts  Array of conflicts
     * @return string Resolution strategy
     */
    private function generateConflictResolution(string $articleRef, array $conflicts): string
    {
        if (empty($conflicts)) {
            return 'Nema otkrivenih sukoba';
        }

        return 'Primijeni načelo lex specialis derogat legi generali - specifičnija odredba ima prednost. '.
               'Broj otkrivenih sukoba: '.count($conflicts);
    }

    /**
     * Generate recommended interpretation based on conflicts
     *
     * @param  string  $articleRef  Article reference
     * @param  array  $conflicts  Array of conflicts
     * @return string Recommended interpretation
     */
    private function generateRecommendedInterpretation(string $articleRef, array $conflicts): string
    {
        if (empty($conflicts)) {
            return "Primijeniti doslovno tumačenje odredbe {$articleRef}";
        }

        return 'Razmotriti sve sukobljene odredbe i primijeniti načelo harmonizacije zakonskih odredbi';
    }

    /**
     * Find constitutional basis for article
     *
     * @param  string  $articleRef  Article reference
     * @return array Constitutional basis
     */
    private function findConstitutionalBasis(string $articleRef): array
    {
        // For criminal procedure, link to relevant constitutional rights
        if (str_contains($articleRef, 'ZKP')) {
            return [
                ['law' => 'Ustav RH', 'article' => 'Članak 29', 'description' => 'Pravo na pravično suđenje'],
                ['law' => 'Ustav RH', 'article' => 'Članak 31', 'description' => 'Zakonitost kaznenog postupka'],
            ];
        }

        return [];
    }

    /**
     * Find implementing regulations for article
     *
     * @param  string  $articleRef  Article reference
     * @return array Implementing regulations
     */
    private function findImplementingRegulations(string $articleRef): array
    {
        // Placeholder - would query database for related regulations
        return [];
    }

    /**
     * Find related provisions in same law
     *
     * @param  string  $lawCode  Law code
     * @param  string  $articleNumber  Article number
     * @return array Related provisions
     */
    private function findRelatedProvisions(string $lawCode, string $articleNumber): array
    {
        try {
            // Search for articles in same law that reference this article
            $searchQuery = "{$lawCode} Članak {$articleNumber}";
            $results = $this->keywordSearch($searchQuery, ['limit' => 5]);

            $related = [];
            foreach ($results['results'] ?? [] as $result) {
                $related[] = [
                    'reference' => $result['reference'] ?? '',
                    'title' => $result['title'] ?? '',
                ];
            }

            return $related;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Build legal hierarchy for article
     *
     * @param  string  $articleRef  Article reference
     * @return array Legal hierarchy
     */
    private function buildLegalHierarchy(string $articleRef): array
    {
        $lawCode = $this->extractLawCode($articleRef);

        $hierarchy = [
            ['level' => 1, 'law' => 'Ustav Republike Hrvatske'],
        ];

        if (str_contains($lawCode, 'ZKP')) {
            $hierarchy[] = ['level' => 2, 'law' => 'Zakon o kaznenom postupku (ZKP)'];
        } elseif (str_contains($lawCode, 'KZ')) {
            $hierarchy[] = ['level' => 2, 'law' => 'Kazneni zakon (KZ)'];
        }

        $hierarchy[] = ['level' => 3, 'law' => $articleRef];

        return $hierarchy;
    }
}
