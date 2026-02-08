<?php

namespace App\Services;

use App\Contracts\DecisionCitationServiceInterface;
use App\Exceptions\CitationException;
use App\Exceptions\GraphException;
use App\Services\LegalCitations\HrLegalCitationsDetector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Citation-aware search service for court decisions
 * Integrates citation extraction with similarity detection
 */
class DecisionCitationService implements DecisionCitationServiceInterface
{
    public function __construct(
        protected HrLegalCitationsDetector $citationDetector,
        protected Graph\GraphRagOrchestrator $graphService
    ) {}

    /**
     * Search decisions by cited law and article
     *
     * @param  string  $lawIdentifier  Law abbreviation or number (e.g., "ZPP", "NN 53/91")
     * @param  string|null  $articleNumber  Optional article number
     * @param  array  $options  Additional filters and options
     * @return array Decisions with citation context
     *
     * @throws CitationException if search fails
     */
    public function searchByCitedLaw(string $lawIdentifier, ?string $articleNumber = null, array $options = []): array
    {
        try {
            Log::info('Citation search by law initiated', [
                'law' => $lawIdentifier,
                'article' => $articleNumber,
                'options' => $options,
            ]);

            $startTime = microtime(true);

            // Use graph service to find decisions citing this law
            $graphResults = $this->fetchGraphDecisionsCitingLaw($lawIdentifier, $articleNumber, $options);

            // Enhance results with full decision details
            $enhancedResults = $this->enhanceWithDecisionDetails($graphResults);

            Log::info('Citation search by law completed', [
                'law' => $lawIdentifier,
                'article' => $articleNumber,
                'result_count' => count($enhancedResults),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return [
                'query' => [
                    'law' => $lawIdentifier,
                    'article' => $articleNumber,
                ],
                'total_results' => count($enhancedResults),
                'results' => $enhancedResults,
                'performance' => [
                    'total_time' => round(microtime(true) - $startTime, 3),
                ],
            ];

        } catch (GraphException $e) {
            Log::error('Graph query failed in citation search by law', [
                'law' => $lawIdentifier,
                'article' => $articleNumber,
                'error' => $e->getMessage(),
            ]);

            throw new CitationException(
                'Graph citation search failed: '.$e->getMessage(),
                CitationException::GRAPH_QUERY_FAILED,
                $e
            );

        } catch (CitationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Unexpected error in citation search by law', [
                'law' => $lawIdentifier,
                'article' => $articleNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new CitationException(
                'Citation search by law failed: '.$e->getMessage(),
                CitationException::UNEXPECTED_ERROR,
                $e
            );
        }
    }

    /**
     * Extract citations from a decision
     *
     * @param  string  $decisionId  Court decision document ID
     * @return array Extracted citations with categorization
     *
     * @throws CitationException if extraction fails
     */
    public function extractCitations(string $decisionId): array
    {
        try {
            Log::info('Citation extraction initiated', ['decision_id' => $decisionId]);

            $startTime = microtime(true);

            // Get decision content
            $decisionDoc = $this->fetchDecisionDocument($decisionId);

            // Extract all citations using detector
            $citations = $this->detectAllCitations($decisionDoc->content);
            $stats = $this->citationDetector->getStatistics($decisionDoc->content);
            $canonicals = $this->citationDetector->extractCanonicalCitations($decisionDoc->content);
            $lawNumbers = $this->citationDetector->extractLawNumbers($decisionDoc->content);
            $caseIds = $this->citationDetector->extractCaseIds($decisionDoc->content);

            Log::info('Citation extraction completed', [
                'decision_id' => $decisionId,
                'total_citations' => $stats['total_citations'] ?? 0,
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return [
                'success' => true,
                'decision_id' => $decisionId,
                'citations' => $citations,
                'statistics' => $stats,
                'canonical_citations' => $canonicals,
                'law_numbers' => $lawNumbers,
                'case_identifiers' => $caseIds,
            ];

        } catch (CitationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Citation extraction failed', [
                'decision_id' => $decisionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new CitationException(
                'Failed to extract citations: '.$e->getMessage(),
                CitationException::CITATION_EXTRACTION_FAILED,
                $e
            );
        }
    }

    /**
     * Find similar decisions based on citation patterns and content
     *
     * @param  string  $decisionId  Court decision document ID
     * @param  array  $options  Search options
     * @return array Similar decisions with similarity scores
     *
     * @throws CitationException if similarity search fails
     */
    public function findSimilarDecisions(string $decisionId, array $options = []): array
    {
        try {
            Log::info('Similar decision search initiated', [
                'decision_id' => $decisionId,
                'options' => $options,
            ]);

            $startTime = microtime(true);

            // Get the source decision with full details
            $sourceDecision = $this->fetchSourceDecisionWithContent($decisionId);

            // Extract citations from source decision
            $sourceCitations = $this->detectAllCitations($sourceDecision->content);

            // Find similar decisions using multiple methods
            $similarityResults = [];

            // Method 1: Graph-based similarity (using relationships)
            $graphSimilar = $this->findGraphSimilarDecisions($decisionId, $options);
            foreach ($graphSimilar as $related) {
                $relatedId = $related['decision']['id'] ?? null;
                if ($relatedId) {
                    $similarityResults[$relatedId] = [
                        'decision' => $related['decision'],
                        'graph_similarity' => $related['strength'],
                        'relationship_types' => $related['relationship_types'],
                        'connection_count' => $related['connection_count'],
                    ];
                }
            }

            // Method 2: Vector similarity (semantic content similarity)
            if ($sourceDecision->embedding) {
                $vectorSimilar = $this->findVectorSimilarDecisions(
                    decisionId: $decisionId,
                    embedding: $sourceDecision->embedding,
                    filters: $options['filters'] ?? [],
                    limit: $options['limit'] ?? 20,
                    threshold: $options['threshold'] ?? 0.75
                );

                foreach ($vectorSimilar as $similar) {
                    $similarId = $similar['id'];
                    if (isset($similarityResults[$similarId])) {
                        $similarityResults[$similarId]['vector_similarity'] = $similar['similarity'];
                    } else {
                        $similarityResults[$similarId] = [
                            'decision' => [
                                'id' => $similar['id'],
                                'decision_id' => $similar['decision_id'],
                                'case_number' => $similar['case_number'],
                                'title' => $similar['title'],
                                'court' => $similar['court'],
                                'jurisdiction' => $similar['jurisdiction'],
                                'decision_date' => $similar['decision_date'],
                            ],
                            'vector_similarity' => $similar['similarity'],
                        ];
                    }
                }
            }

            // Method 3: Citation pattern similarity
            $this->enrichWithCitationSimilarity($similarityResults, $sourceCitations);

            // Calculate composite similarity score
            $this->calculateCompositeSimilarity($similarityResults);

            // Sort by composite similarity score
            usort($similarityResults, fn ($a, $b) => $b['composite_similarity'] <=> $a['composite_similarity']);

            // Apply limit
            $limit = $options['limit'] ?? 20;
            $similarityResults = array_slice($similarityResults, 0, $limit);

            Log::info('Similar decision search completed', [
                'decision_id' => $decisionId,
                'result_count' => count($similarityResults),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return [
                'success' => true,
                'source_decision' => [
                    'id' => $sourceDecision->id,
                    'decision_id' => $sourceDecision->decision_id,
                    'case_number' => $sourceDecision->case_number,
                    'title' => $sourceDecision->title,
                ],
                'total_results' => count($similarityResults),
                'results' => array_values($similarityResults),
                'similarity_methods' => [
                    'graph' => 'Relationship-based similarity using Neo4j graph',
                    'vector' => 'Semantic content similarity using embeddings',
                    'citation' => 'Citation pattern similarity',
                    'composite' => 'Weighted combination of all methods',
                ],
                'performance' => [
                    'total_time' => round(microtime(true) - $startTime, 3),
                ],
            ];

        } catch (CitationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Similar decision search failed', [
                'decision_id' => $decisionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new CitationException(
                'Failed to find similar decisions: '.$e->getMessage(),
                CitationException::SIMILARITY_CALCULATION_FAILED,
                $e
            );
        }
    }

    /**
     * Find vector-similar decisions using PostgreSQL pgvector
     *
     * @throws CitationException if vector query fails
     */
    protected function findVectorSimilarDecisions(
        string $decisionId,
        $embedding,
        array $filters,
        int $limit,
        float $threshold
    ): array {
        try {
            $driver = DB::connection()->getDriverName();

            if ($driver !== 'pgsql') {
                Log::debug('Skipping vector similarity - non-PostgreSQL driver', ['driver' => $driver]);

                return [];
            }

            // Validate embedding format to prevent SQL injection
            if (! $this->isValidVectorEmbedding($embedding)) {
                Log::warning('Invalid embedding format detected', [
                    'decision_id' => $decisionId,
                    'embedding_type' => gettype($embedding),
                ]);

                throw new CitationException(
                    'Invalid embedding format for vector similarity search',
                    CitationException::SIMILARITY_CALCULATION_FAILED
                );
            }

            // Build query with parameterized bindings for safety
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
                    DB::raw('1 - (cdd.embedding <=> ?::vector) as similarity', [$embedding]),
                ])
                ->where('cdd.id', '!=', $decisionId)
                ->whereRaw('1 - (cdd.embedding <=> ?::vector) >= ?', [$embedding, $threshold]);

            // Apply filters with proper escaping
            $this->applyVectorSearchFilters($queryBuilder, $filters);

            $results = $queryBuilder
                ->orderByDesc('similarity')
                ->limit($limit)
                ->get();

            Log::debug('Vector similarity search completed', [
                'decision_id' => $decisionId,
                'result_count' => $results->count(),
            ]);

            return $results->toArray();

        } catch (\Exception $e) {
            Log::warning('Vector similarity search failed, continuing without vector results', [
                'decision_id' => $decisionId,
                'error' => $e->getMessage(),
            ]);

            // Return empty array instead of throwing - this is a non-critical failure
            return [];
        }
    }

    /**
     * Calculate citation similarity between two sets of citations
     */
    protected function calculateCitationSimilarity(array $citations1, array $citations2): array
    {
        $sharedCitations = [];
        $totalCitations1 = 0;
        $totalCitations2 = 0;
        $sharedCount = 0;

        // Compare statute citations
        if (! empty($citations1['statutes']) && ! empty($citations2['statutes'])) {
            $canonicals1 = array_column($citations1['statutes'], 'canonical');
            $canonicals2 = array_column($citations2['statutes'], 'canonical');
            $shared = array_intersect($canonicals1, $canonicals2);

            $totalCitations1 += count($canonicals1);
            $totalCitations2 += count($canonicals2);
            $sharedCount += count($shared);

            if (! empty($shared)) {
                $sharedCitations['statutes'] = array_values($shared);
            }
        }

        // Compare ECLI citations
        if (! empty($citations1['ecli']) && ! empty($citations2['ecli'])) {
            $eclis1 = array_column($citations1['ecli'], 'canonical');
            $eclis2 = array_column($citations2['ecli'], 'canonical');
            $shared = array_intersect($eclis1, $eclis2);

            $totalCitations1 += count($eclis1);
            $totalCitations2 += count($eclis2);
            $sharedCount += count($shared);

            if (! empty($shared)) {
                $sharedCitations['ecli'] = array_values($shared);
            }
        }

        // Compare case number citations
        if (! empty($citations1['case_numbers']) && ! empty($citations2['case_numbers'])) {
            $cases1 = array_column($citations1['case_numbers'], 'canonical');
            $cases2 = array_column($citations2['case_numbers'], 'canonical');
            $shared = array_intersect($cases1, $cases2);

            $totalCitations1 += count($cases1);
            $totalCitations2 += count($cases2);
            $sharedCount += count($shared);

            if (! empty($shared)) {
                $sharedCitations['case_numbers'] = array_values($shared);
            }
        }

        // Calculate Jaccard similarity: intersection / union
        $totalUnique = $totalCitations1 + $totalCitations2 - $sharedCount;
        $similarityScore = $totalUnique > 0 ? $sharedCount / $totalUnique : 0;

        return [
            'similarity_score' => round($similarityScore, 4),
            'shared_citations' => $sharedCitations,
            'shared_count' => $sharedCount,
            'total_citations_1' => $totalCitations1,
            'total_citations_2' => $totalCitations2,
        ];
    }

    /**
     * Search decisions by legal concept with citation context
     *
     * @param  string  $concept  Legal concept or topic
     * @param  array  $options  Additional filters and options
     * @return array Decisions with citation analysis
     *
     * @throws CitationException if search fails
     */
    public function searchByLegalConcept(string $concept, array $options = []): array
    {
        try {
            Log::info('Citation search by legal concept initiated', [
                'concept' => $concept,
                'options' => $options,
            ]);

            $startTime = microtime(true);

            // Use graph service to find decisions related to concept
            $graphResults = $this->fetchGraphDecisionsByConcept($concept, $options);

            // Enhance with citation analysis
            $enhancedResults = $this->enrichWithCitationAnalysis($graphResults);

            Log::info('Citation search by legal concept completed', [
                'concept' => $concept,
                'result_count' => count($enhancedResults),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return [
                'concept' => $concept,
                'total_results' => count($enhancedResults),
                'results' => $enhancedResults,
                'performance' => [
                    'total_time' => round(microtime(true) - $startTime, 3),
                ],
            ];

        } catch (GraphException $e) {
            Log::error('Graph query failed in citation search by concept', [
                'concept' => $concept,
                'error' => $e->getMessage(),
            ]);

            throw new CitationException(
                'Graph concept search failed: '.$e->getMessage(),
                CitationException::GRAPH_QUERY_FAILED,
                $e
            );

        } catch (CitationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Unexpected error in citation search by concept', [
                'concept' => $concept,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new CitationException(
                'Citation search by concept failed: '.$e->getMessage(),
                CitationException::UNEXPECTED_ERROR,
                $e
            );
        }
    }

    /**
     * Get comprehensive citation analysis for a decision
     *
     * @param  string  $decisionId  Court decision document ID
     * @return array Complete citation analysis
     *
     * @throws CitationException if analysis fails
     */
    public function analyzeDecisionCitations(string $decisionId): array
    {
        try {
            Log::info('Comprehensive citation analysis initiated', ['decision_id' => $decisionId]);

            $startTime = microtime(true);

            // Extract citations
            $citations = $this->extractCitations($decisionId);

            // Get graph context (cited laws, related decisions)
            $graphContext = $this->fetchGraphCitationContext($decisionId);
            $citations['graph_context'] = $graphContext;

            // Find decisions with similar citation patterns
            $similarByCitation = $this->findDecisionsBySimilarCitations($decisionId, 10);
            $citations['similar_citation_patterns'] = $similarByCitation;

            $citations['performance'] = [
                'total_time' => round(microtime(true) - $startTime, 3),
            ];

            Log::info('Comprehensive citation analysis completed', [
                'decision_id' => $decisionId,
                'total_citations' => $citations['statistics']['total_citations'] ?? 0,
                'similar_count' => count($similarByCitation),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $citations;

        } catch (CitationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Comprehensive citation analysis failed', [
                'decision_id' => $decisionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new CitationException(
                'Citation analysis failed: '.$e->getMessage(),
                CitationException::CITATION_EXTRACTION_FAILED,
                $e
            );
        }
    }

    /**
     * Find decisions with similar citation patterns
     */
    protected function findDecisionsBySimilarCitations(string $decisionId, int $limit = 10): array
    {
        // Get source decision citations
        $sourceContent = DB::table('court_decision_documents')
            ->where('id', $decisionId)
            ->value('content');

        if (! $sourceContent) {
            return [];
        }

        $sourceCitations = $this->citationDetector->detectAll($sourceContent);
        $sourceLawNumbers = $this->citationDetector->extractLawNumbers($sourceContent);

        if (empty($sourceLawNumbers)) {
            return [];
        }

        // Find other decisions citing the same laws
        $similarDecisions = DB::table('court_decision_documents as cdd')
            ->join('court_decisions as cd', 'cdd.decision_id', '=', 'cd.id')
            ->select([
                'cdd.id',
                'cdd.decision_id',
                'cdd.content',
                'cd.case_number',
                'cd.title',
                'cd.court',
                'cd.jurisdiction',
                'cd.decision_date',
            ])
            ->where('cdd.id', '!=', $decisionId)
            ->limit($limit * 3) // Get more to filter by similarity
            ->get();

        $results = [];
        foreach ($similarDecisions as $decision) {
            $decisionCitations = $this->citationDetector->detectAll($decision->content);
            $similarity = $this->calculateCitationSimilarity($sourceCitations, $decisionCitations);

            if ($similarity['similarity_score'] > 0.1) { // Threshold for "similar"
                $results[] = [
                    'decision' => [
                        'id' => $decision->id,
                        'decision_id' => $decision->decision_id,
                        'case_number' => $decision->case_number,
                        'title' => $decision->title,
                        'court' => $decision->court,
                        'jurisdiction' => $decision->jurisdiction,
                        'decision_date' => $decision->decision_date,
                    ],
                    'citation_similarity' => $similarity['similarity_score'],
                    'shared_citations' => $similarity['shared_citations'],
                ];
            }
        }

        // Sort by similarity
        usort($results, fn ($a, $b) => $b['citation_similarity'] <=> $a['citation_similarity']);

        return array_slice($results, 0, $limit);
    }

    /**
     * Fetch graph decisions citing a specific law
     *
     * @throws GraphException
     */
    protected function fetchGraphDecisionsCitingLaw(string $lawIdentifier, ?string $articleNumber, array $options): array
    {
        try {
            return $this->graphService->findDecisionsCitingLawArticle(
                lawIdentifier: $lawIdentifier,
                articleNumber: $articleNumber,
                options: $options,
                limit: $options['limit'] ?? 50
            );
        } catch (\Exception $e) {
            Log::error('Failed to fetch graph decisions citing law', [
                'law' => $lawIdentifier,
                'article' => $articleNumber,
                'error' => $e->getMessage(),
            ]);

            throw new GraphException(
                'Graph query for law citations failed: '.$e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Enhance graph results with full decision details
     *
     * @throws CitationException
     */
    protected function enhanceWithDecisionDetails(array $graphResults): array
    {
        try {
            $enhancedResults = [];

            foreach ($graphResults as $graphResult) {
                $decision = $graphResult['decision'];
                $citation = $graphResult['citation'];
                $citedLaw = $graphResult['cited_law'];

                $decisionId = $decision['decision_id'] ?? null;
                if (! $decisionId) {
                    continue;
                }

                $decisionDetails = DB::table('court_decisions')
                    ->where('id', $decisionId)
                    ->first();

                if ($decisionDetails) {
                    $enhancedResults[] = [
                        'id' => $decision['id'],
                        'decision_id' => $decisionId,
                        'case_number' => $decisionDetails->case_number,
                        'title' => $decisionDetails->title,
                        'court' => $decisionDetails->court,
                        'jurisdiction' => $decisionDetails->jurisdiction,
                        'decision_date' => $decisionDetails->decision_date,
                        'decision_type' => $decisionDetails->decision_type,
                        'ecli' => $decisionDetails->ecli,
                        'citation' => [
                            'cited_law' => [
                                'id' => $citedLaw['id'] ?? null,
                                'title' => $citedLaw['title'] ?? null,
                                'law_number' => $citedLaw['law_number'] ?? null,
                            ],
                            'article' => $citation['article'],
                            'paragraph' => $citation['paragraph'],
                            'item' => $citation['item'],
                            'law_abbreviation' => $citation['law_abbreviation'],
                        ],
                    ];
                }
            }

            return $enhancedResults;

        } catch (\Exception $e) {
            Log::error('Failed to enhance decision details', [
                'error' => $e->getMessage(),
            ]);

            throw new CitationException(
                'Failed to enhance results: '.$e->getMessage(),
                CitationException::RESULT_ENHANCEMENT_FAILED,
                $e
            );
        }
    }

    /**
     * Fetch decision document by ID
     *
     * @throws CitationException
     */
    protected function fetchDecisionDocument(string $decisionId): object
    {
        $decisionDoc = DB::table('court_decision_documents')
            ->where('id', $decisionId)
            ->first();

        if (! $decisionDoc) {
            throw new CitationException(
                'Decision document not found',
                CitationException::DECISION_NOT_FOUND
            );
        }

        return $decisionDoc;
    }

    /**
     * Detect all citations from content
     *
     * @throws CitationException
     */
    protected function detectAllCitations(string $content): array
    {
        try {
            return $this->citationDetector->detectAll($content);
        } catch (\Exception $e) {
            Log::error('Citation detection failed', [
                'error' => $e->getMessage(),
            ]);

            throw new CitationException(
                'Citation detection failed: '.$e->getMessage(),
                CitationException::CITATION_DETECTION_FAILED,
                $e
            );
        }
    }

    /**
     * Fetch source decision with content
     *
     * @throws CitationException
     */
    protected function fetchSourceDecisionWithContent(string $decisionId): object
    {
        $sourceDecision = DB::table('court_decision_documents as cdd')
            ->join('court_decisions as cd', 'cdd.decision_id', '=', 'cd.id')
            ->where('cdd.id', $decisionId)
            ->select([
                'cdd.id',
                'cdd.decision_id',
                'cdd.content',
                'cdd.embedding',
                'cd.case_number',
                'cd.title',
                'cd.court',
                'cd.jurisdiction',
                'cd.decision_date',
            ])
            ->first();

        if (! $sourceDecision) {
            throw new CitationException(
                'Source decision not found',
                CitationException::DECISION_NOT_FOUND
            );
        }

        return $sourceDecision;
    }

    /**
     * Find graph similar decisions
     */
    protected function findGraphSimilarDecisions(string $decisionId, array $options): array
    {
        try {
            return $this->graphService->findRelatedDecisions(
                decisionId: $decisionId,
                maxDepth: 2,
                limit: $options['limit'] ?? 20
            );
        } catch (\Exception $e) {
            Log::warning('Graph-based similarity search failed, continuing without graph results', [
                'decision_id' => $decisionId,
                'error' => $e->getMessage(),
            ]);

            // Return empty array - this is a non-critical failure
            return [];
        }
    }

    /**
     * Enrich similarity results with citation similarity scores
     */
    protected function enrichWithCitationSimilarity(array &$similarityResults, array $sourceCitations): void
    {
        foreach ($similarityResults as &$result) {
            try {
                $resultContent = DB::table('court_decision_documents')
                    ->where('id', $result['decision']['id'])
                    ->value('content');

                if ($resultContent) {
                    $resultCitations = $this->citationDetector->detectAll($resultContent);
                    $citationSimilarity = $this->calculateCitationSimilarity($sourceCitations, $resultCitations);

                    $result['citation_similarity'] = $citationSimilarity['similarity_score'];
                    $result['shared_citations'] = $citationSimilarity['shared_citations'];
                }
            } catch (\Exception $e) {
                Log::debug('Failed to calculate citation similarity for decision', [
                    'decision_id' => $result['decision']['id'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
                // Continue with other results
            }
        }
    }

    /**
     * Calculate composite similarity scores
     */
    protected function calculateCompositeSimilarity(array &$similarityResults): void
    {
        $weights = [
            'graph' => 0.40,     // 40% graph relationships
            'vector' => 0.40,    // 40% semantic similarity
            'citation' => 0.20,  // 20% citation patterns
        ];

        foreach ($similarityResults as &$result) {
            $compositeScore = 0;
            $compositeScore += ($result['graph_similarity'] ?? 0) * $weights['graph'];
            $compositeScore += ($result['vector_similarity'] ?? 0) * $weights['vector'];
            $compositeScore += ($result['citation_similarity'] ?? 0) * $weights['citation'];

            $result['composite_similarity'] = round($compositeScore, 4);
        }
    }

    /**
     * Apply vector search filters to query builder
     */
    protected function applyVectorSearchFilters($queryBuilder, array $filters): void
    {
        if (! empty($filters['court'])) {
            // Escape special LIKE characters (%, _) in user input
            $escapedCourt = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['court']);
            $queryBuilder->where('cd.court', 'LIKE', "%{$escapedCourt}%");
        }
        if (! empty($filters['jurisdiction'])) {
            $queryBuilder->where('cd.jurisdiction', $filters['jurisdiction']);
        }
        if (! empty($filters['date_from'])) {
            $queryBuilder->where('cd.decision_date', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $queryBuilder->where('cd.decision_date', '<=', $filters['date_to']);
        }
    }

    /**
     * Fetch graph decisions by legal concept
     *
     * @throws GraphException
     */
    protected function fetchGraphDecisionsByConcept(string $concept, array $options): array
    {
        try {
            return $this->graphService->findDecisionsByLegalConcept(
                concept: $concept,
                options: $options,
                limit: $options['limit'] ?? 30
            );
        } catch (\Exception $e) {
            Log::error('Failed to fetch graph decisions by concept', [
                'concept' => $concept,
                'error' => $e->getMessage(),
            ]);

            throw new GraphException(
                'Graph query for legal concept failed: '.$e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Enrich results with citation analysis
     *
     * @throws CitationException
     */
    protected function enrichWithCitationAnalysis(array $graphResults): array
    {
        try {
            $enhancedResults = [];

            foreach ($graphResults as $result) {
                $decision = $result['decision'];
                $decisionId = $decision['id'] ?? null;

                if ($decisionId) {
                    try {
                        // Get citations for this decision
                        $citationData = $this->extractCitations($decisionId);

                        $enhancedResults[] = array_merge($decision, [
                            'citation_analysis' => [
                                'has_citations' => $citationData['success'] && ($citationData['statistics']['total_citations'] ?? 0) > 0,
                                'citation_stats' => $citationData['statistics'] ?? null,
                                'law_numbers' => $citationData['law_numbers'] ?? [],
                            ],
                        ]);
                    } catch (\Exception $e) {
                        // Add decision without citation analysis if extraction fails
                        Log::debug('Citation extraction failed for decision, adding without analysis', [
                            'decision_id' => $decisionId,
                            'error' => $e->getMessage(),
                        ]);

                        $enhancedResults[] = array_merge($decision, [
                            'citation_analysis' => [
                                'has_citations' => false,
                                'citation_stats' => null,
                                'law_numbers' => [],
                            ],
                        ]);
                    }
                }
            }

            return $enhancedResults;

        } catch (\Exception $e) {
            Log::error('Failed to enrich with citation analysis', [
                'error' => $e->getMessage(),
            ]);

            throw new CitationException(
                'Failed to enrich results with citation analysis: '.$e->getMessage(),
                CitationException::RESULT_ENHANCEMENT_FAILED,
                $e
            );
        }
    }

    /**
     * Fetch graph citation context
     */
    protected function fetchGraphCitationContext(string $decisionId): ?array
    {
        try {
            $citedLaws = $this->graphService->getCitedLaws('CourtDecisionDocument', $decisionId);
            $relatedDecisions = $this->graphService->findRelatedDecisions($decisionId, 2, 10);

            return [
                'cited_laws' => $citedLaws,
                'related_decisions' => $relatedDecisions,
            ];
        } catch (\Exception $e) {
            Log::warning('Failed to get graph context for citation analysis', [
                'decision_id' => $decisionId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Analyze citations with various operations
     *
     * @param  string  $decisionId  Decision ID to analyze
     * @param  array  $options  Analysis options (operation, depth, limit, etc.)
     * @return array Analysis results
     */
    public function analyzeCitations(string $decisionId, array $options = []): array
    {
        $operation = $options['operation'] ?? 'graph';

        return match ($operation) {
            'graph' => $this->buildCitationGraph($decisionId, $options),
            'authority' => $this->calculateAuthorityMetrics($decisionId),
            'patterns' => $this->analyzeCitationPatterns($decisionId),
            'influence' => $this->analyzeInfluenceSpread($decisionId),
            default => [
                'decision_id' => $decisionId,
                'operation' => $operation,
                'error' => 'Unknown operation',
            ],
        };
    }

    /**
     * Build directed graph of citations
     *
     * @param  string  $decisionId  Root decision ID
     * @param  array  $options  Options (depth, etc.)
     * @return array Graph structure with nodes and edges
     */
    private function buildCitationGraph(string $decisionId, array $options = []): array
    {
        try {
            $depth = $options['depth'] ?? 2;

            Log::debug('Building citation graph', [
                'decision_id' => $decisionId,
                'depth' => $depth,
            ]);

            // Build graph using BFS traversal from root decision
            $nodes = [];
            $edges = [];
            $nodeSet = [];
            $visited = [];
            $queue = [['id' => $decisionId, 'level' => 0]];

            // Add root node
            $nodes[] = ['id' => $decisionId, 'type' => 'root'];
            $nodeSet[$decisionId] = true;

            while (! empty($queue)) {
                $current = array_shift($queue);
                $currentId = $current['id'];
                $currentLevel = $current['level'];

                if (isset($visited[$currentId]) || $currentLevel >= $depth) {
                    continue;
                }

                $visited[$currentId] = true;

                try {
                    // Get citations to and from this decision (filtered by decision ID)
                    $citations = DB::table('citation_relationships')
                        ->where('citing_decision_id', $currentId)
                        ->orWhere('cited_decision_id', $currentId)
                        ->get();

                    foreach ($citations as $citation) {
                        // Determine the connected node
                        $connectedId = ($citation->citing_decision_id === $currentId)
                            ? $citation->cited_decision_id
                            : $citation->citing_decision_id;

                        // Add node if not already added
                        if (! isset($nodeSet[$connectedId])) {
                            $nodes[] = ['id' => $connectedId, 'type' => 'decision'];
                            $nodeSet[$connectedId] = true;

                            // Add to queue for further traversal
                            if ($currentLevel + 1 < $depth) {
                                $queue[] = ['id' => $connectedId, 'level' => $currentLevel + 1];
                            }
                        }

                        // Add edge
                        $edges[] = [
                            'from' => $citation->citing_decision_id,
                            'to' => $citation->cited_decision_id,
                            'type' => $citation->citation_type ?? 'cites',
                        ];
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to fetch citations for node in graph', [
                        'node_id' => $currentId,
                        'level' => $currentLevel,
                        'error' => $e->getMessage(),
                    ]);

                    // Continue with other nodes instead of failing completely
                    continue;
                }
            }

            Log::debug('Citation graph built successfully', [
                'decision_id' => $decisionId,
                'node_count' => count($nodes),
                'edge_count' => count($edges),
            ]);

            return [
                'root_decision' => $decisionId,
                'depth' => $depth,
                'nodes' => $nodes,
                'edges' => $edges,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to build citation graph', [
                'decision_id' => $decisionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new CitationException(
                'Failed to build citation graph: '.$e->getMessage(),
                CitationException::GRAPH_BUILDING_FAILED,
                $e
            );
        }
    }

    /**
     * Calculate authority metrics for a decision
     *
     * @param  string  $decisionId  Decision ID
     * @return array Authority metrics
     */
    private function calculateAuthorityMetrics(string $decisionId): array
    {
        // Count outgoing citations (decisions this one cites)
        $citationCount = DB::table('citation_relationships')
            ->where('citing_decision_id', $decisionId)
            ->get()
            ->count();

        // Count incoming citations (decisions that cite this one)
        $citedByCount = DB::table('citation_relationships')
            ->where('cited_decision_id', $decisionId)
            ->get()
            ->count();

        // Calculate h-index (simplified: min of citations and cited-by)
        $hIndex = min($citationCount, $citedByCount);

        // Calculate authority score (weighted combination)
        $authorityScore = ($citedByCount * 0.7) + ($citationCount * 0.3);

        return [
            'decision_id' => $decisionId,
            'authority_score' => round($authorityScore, 2),
            'citation_count' => $citationCount,
            'cited_by_count' => $citedByCount,
            'h_index' => $hIndex,
            'influence_rank' => $this->determineInfluenceRank($citedByCount),
            'citation_velocity' => 0.0,
        ];
    }

    /**
     * Determine influence rank based on citation count
     *
     * @param  int  $citedByCount  Number of incoming citations
     * @return string Rank label
     */
    private function determineInfluenceRank(int $citedByCount): string
    {
        return match (true) {
            $citedByCount >= 50 => 'highly_influential',
            $citedByCount >= 20 => 'influential',
            $citedByCount >= 10 => 'moderately_influential',
            $citedByCount >= 5 => 'somewhat_influential',
            $citedByCount > 0 => 'minimally_influential',
            default => 'not_influential',
        };
    }

    /**
     * Analyze citation patterns for a decision
     *
     * @param  string  $decisionId  Decision ID
     * @return array Pattern analysis
     */
    private function analyzeCitationPatterns(string $decisionId): array
    {
        // Get all citations to this decision
        $citations = DB::table('citation_relationships')
            ->where('cited_decision_id', $decisionId)
            ->get();

        // Analyze temporal distribution
        $temporalDistribution = [];
        $citationsByType = ['direct' => 0, 'indirect' => 0];

        foreach ($citations as $citation) {
            // Count by type
            $type = $citation->citation_type ?? 'unknown';
            if (! isset($citationsByType[$type])) {
                $citationsByType[$type] = 0;
            }
            $citationsByType[$type]++;

            // Group by month
            if (isset($citation->created_at)) {
                $month = substr($citation->created_at, 0, 7); // YYYY-MM
                if (! isset($temporalDistribution[$month])) {
                    $temporalDistribution[$month] = 0;
                }
                $temporalDistribution[$month]++;
            }
        }

        // Detect patterns
        $patterns = [];
        if ($citationsByType['direct'] > $citationsByType['indirect']) {
            $patterns[] = 'primarily_direct_citations';
        }
        if (count($temporalDistribution) > 1) {
            $patterns[] = 'citations_over_time';
        }

        return [
            'decision_id' => $decisionId,
            'patterns' => $patterns,
            'temporal_distribution' => $temporalDistribution,
            'citation_types' => $citationsByType,
        ];
    }

    /**
     * Analyze influence spread for a decision
     *
     * @param  string  $decisionId  Decision ID
     * @return array Influence spread analysis
     */
    private function analyzeInfluenceSpread(string $decisionId): array
    {
        // Get all citation relationships
        $allCitations = DB::table('citation_relationships')->get();

        // Build adjacency list for graph traversal
        $graph = [];
        foreach ($allCitations as $citation) {
            $from = $citation->citing_decision_id;
            $to = $citation->cited_decision_id;

            if (! isset($graph[$from])) {
                $graph[$from] = [];
            }
            $graph[$from][] = $to;
        }

        // Find direct influences (decisions this one cites)
        $directInfluences = $graph[$decisionId] ?? [];

        // Find indirect influences (decisions cited by direct influences)
        $indirectInfluences = [];
        foreach ($directInfluences as $directDecision) {
            if (isset($graph[$directDecision])) {
                foreach ($graph[$directDecision] as $indirectDecision) {
                    if ($indirectDecision !== $decisionId && ! in_array($indirectDecision, $directInfluences)) {
                        $indirectInfluences[] = $indirectDecision;
                    }
                }
            }
        }

        // Remove duplicates
        $indirectInfluences = array_unique($indirectInfluences);

        // Build influenced decisions list
        $influencedDecisions = array_merge(
            array_map(fn ($id) => ['id' => $id, 'level' => 'direct'], $directInfluences),
            array_map(fn ($id) => ['id' => $id, 'level' => 'indirect'], $indirectInfluences)
        );

        return [
            'decision_id' => $decisionId,
            'influenced_decisions' => $influencedDecisions,
            'influence_spread' => [
                'direct' => count($directInfluences),
                'indirect' => count($indirectInfluences),
                'total_reach' => count($directInfluences) + count($indirectInfluences),
            ],
            'key_concepts_propagated' => [],
        ];
    }

    /**
     * Validate vector embedding format to prevent SQL injection
     *
     * @param  mixed  $embedding  The embedding to validate
     * @return bool True if valid, false otherwise
     */
    private function isValidVectorEmbedding($embedding): bool
    {
        // Null or empty is invalid
        if (empty($embedding)) {
            return false;
        }

        // If it's a string, validate PostgreSQL vector format
        if (is_string($embedding)) {
            // Must start with '[' and end with ']'
            if (! str_starts_with($embedding, '[') || ! str_ends_with($embedding, ']')) {
                return false;
            }

            // Remove brackets and split by comma
            $content = substr($embedding, 1, -1);

            // Empty brackets are invalid
            if (trim($content) === '') {
                return false;
            }

            // Check for SQL injection patterns
            $dangerousPatterns = [
                ';', '--', '/*', '*/', 'DROP', 'DELETE', 'UPDATE', 'INSERT',
                'EXEC', 'EXECUTE', 'UNION', 'SELECT', 'CREATE', 'ALTER',
                'TRUNCATE', 'GRANT', 'REVOKE', '\x00', '\n', '\r',
            ];

            foreach ($dangerousPatterns as $pattern) {
                if (stripos($content, $pattern) !== false) {
                    return false;
                }
            }

            // Split by comma and validate each value is a number
            $values = explode(',', $content);
            foreach ($values as $value) {
                $trimmed = trim($value);
                // Must be a valid number (int or float, including negative and scientific notation)
                if (! is_numeric($trimmed)) {
                    return false;
                }
            }

            return true;
        }

        // If it's an array, validate all elements are numeric
        if (is_array($embedding)) {
            if (empty($embedding)) {
                return false;
            }

            foreach ($embedding as $value) {
                if (! is_numeric($value)) {
                    return false;
                }
            }

            return true;
        }

        // Any other type is invalid
        return false;
    }
}
