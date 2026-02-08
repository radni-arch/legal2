<?php

namespace App\Services;

use App\Contracts\RagOrchestratorInterface;
use App\Exceptions\AnalysisException;
use App\Services\LegalCitations\HrLegalCitationsDetector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RAG Orchestrator for hybrid retrieval and result fusion
 *
 * Coordinates:
 * - Query normalization and citation detection
 * - Hybrid retrieval (vector + keyword search)
 * - MMR (Maximal Marginal Relevance) for diversity
 * - RRF (Reciprocal Rank Fusion) for result merging
 * - Per-corpus caps and confidence scoring
 */
class RagOrchestrator implements RagOrchestratorInterface
{
    // Default retrieval parameters
    private const DEFAULT_TOP_K = 20;

    private const DEFAULT_MMR_LAMBDA = 0.5; // Balance between relevance and diversity

    private const DEFAULT_RRF_K = 60; // Constant for RRF formula

    private const MIN_CONFIDENCE_THRESHOLD = 0.3;

    public function __construct(
        protected QueryNormalizer $queryNormalizer,
        protected HrLegalCitationsDetector $citationDetector,
        protected Graph\GraphRagOrchestrator $graphRag,
        protected OpenAIService $openAI
    ) {}

    /**
     * Main orchestration method for RAG retrieval
     *
     * @param  string  $query  User's query text
     * @param  array  $options  Retrieval options
     * @return array Retrieved chunks with metadata and confidence scores
     *
     * @throws AnalysisException if retrieval fails
     */
    public function retrieve(string $query, array $options = []): array
    {
        try {
            Log::info('RAG retrieval initiated', [
                'query' => substr($query, 0, 100),
                'options' => $options,
            ]);

            $startTime = microtime(true);

            if (empty(trim($query))) {
                throw new AnalysisException(
                    'Query cannot be empty',
                    AnalysisException::EXTRACTION_FAILED
                );
            }

            // Step 1: Normalize and analyze query
            $normalizedQuery = $this->queryNormalizer->normalize($query, $options);

            // Step 2: Detect citations in query
            $citations = $this->citationDetector->detectAll($query);

            // Step 3: Generate query embedding
            $queryEmbedding = $this->openAI->createEmbedding($query);
            if (! $queryEmbedding || ! is_array($queryEmbedding)) {
                throw new AnalysisException(
                    'Failed to generate query embedding',
                    AnalysisException::EXTRACTION_FAILED
                );
            }

            // Step 4: Hybrid retrieval (vector + keyword + graph)
            $vectorResults = $this->vectorSearch($queryEmbedding, $normalizedQuery, $options);
            $keywordResults = $this->keywordSearch($normalizedQuery, $options);
            $graphResults = $this->graphSearch($normalizedQuery, $citations, $options);

            // Step 5: Merge results using RRF (Reciprocal Rank Fusion)
            $mergedResults = $this->reciprocalRankFusion([
                'vector' => $vectorResults,
                'keyword' => $keywordResults,
                'graph' => $graphResults,
            ], $options['rrf_k'] ?? self::DEFAULT_RRF_K);

            // Step 6: Apply MMR for diversity
            $diverseResults = $this->maximalMarginalRelevance(
                $mergedResults,
                $queryEmbedding,
                $options['mmr_lambda'] ?? self::DEFAULT_MMR_LAMBDA,
                $options['top_k'] ?? self::DEFAULT_TOP_K
            );

            // Step 7: Apply per-corpus caps
            $cappedResults = $this->applyCorpusCaps($diverseResults, $options['corpus_caps'] ?? []);

            // Step 8: Calculate confidence scores
            $scoredResults = $this->calculateConfidence($cappedResults, $normalizedQuery, $citations);

            // Step 9: Enrich with metadata
            $enrichedResults = $this->enrichMetadata($scoredResults, $normalizedQuery);

            Log::info('RAG retrieval completed', [
                'query' => substr($query, 0, 100),
                'final_results' => count($enrichedResults),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return [
                'query_analysis' => $normalizedQuery,
                'citations_detected' => $citations,
                'chunks' => $enrichedResults,
                'retrieval_stats' => [
                    'vector_results' => count($vectorResults),
                    'keyword_results' => count($keywordResults),
                    'graph_results' => count($graphResults),
                    'merged_results' => count($mergedResults),
                    'final_results' => count($enrichedResults),
                ],
            ];

        } catch (AnalysisException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('RAG retrieval failed', [
                'query' => substr($query, 0, 100),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new AnalysisException(
                'RAG retrieval failed: '.$e->getMessage(),
                AnalysisException::EXTRACTION_FAILED,
                $e
            );
        }
    }

    /**
     * Vector similarity search across all corpora
     */
    protected function vectorSearch(array $queryEmbedding, array $normalizedQuery, array $options): array
    {
        try {
            if (empty($queryEmbedding)) {
                Log::warning('Empty query embedding in vectorSearch');

                return [];
            }

            $results = [];
            $limit = $options['vector_limit'] ?? 50;
            $similarityThreshold = $options['similarity_threshold'] ?? 0.7;

            // Search laws corpus
            if (! isset($options['exclude_corpora']) || ! in_array('laws', $options['exclude_corpora'])) {
                try {
                    $lawResults = $this->vectorSearchCorpus(
                        'laws',
                        $queryEmbedding,
                        $limit,
                        $similarityThreshold,
                        $normalizedQuery
                    );
                    $results = array_merge($results, $lawResults);
                } catch (\Exception $e) {
                    Log::warning('Laws corpus vector search failed', ['error' => $e->getMessage()]);
                }
            }

            // Search cases corpus
            if (! isset($options['exclude_corpora']) || ! in_array('cases', $options['exclude_corpora'])) {
                try {
                    $caseResults = $this->vectorSearchCorpus(
                        'cases_documents',
                        $queryEmbedding,
                        $limit,
                        $similarityThreshold,
                        $normalizedQuery
                    );
                    $results = array_merge($results, $caseResults);
                } catch (\Exception $e) {
                    Log::warning('Cases corpus vector search failed', ['error' => $e->getMessage()]);
                }
            }

            // Search court decisions corpus
            if (! isset($options['exclude_corpora']) || ! in_array('decisions', $options['exclude_corpora'])) {
                try {
                    $decisionResults = $this->vectorSearchCorpus(
                        'court_decision_documents',
                        $queryEmbedding,
                        $limit,
                        $similarityThreshold,
                        $normalizedQuery
                    );
                    $results = array_merge($results, $decisionResults);
                } catch (\Exception $e) {
                    Log::warning('Decisions corpus vector search failed', ['error' => $e->getMessage()]);
                }
            }

            return $results;

        } catch (\Exception $e) {
            Log::error('Vector search failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Vector search within a specific corpus
     */
    protected function vectorSearchCorpus(
        string $table,
        array $queryEmbedding,
        int $limit,
        float $threshold,
        array $normalizedQuery
    ): array {
        $embeddingJson = json_encode($queryEmbedding);

        // Check if using pgvector or JSON embeddings
        $usingPgVector = DB::connection()->getDriverName() === 'pgsql'
            && DB::select("SELECT 1 FROM pg_extension WHERE extname = 'vector'");

        if ($usingPgVector) {
            // Use pgvector for efficient similarity search
            $sql = "SELECT
                id, doc_id, title, content, metadata, chunk_index,
                1 - (embedding <=> ?::vector) as similarity,
                ? as corpus
            FROM {$table}
            WHERE 1 - (embedding <=> ?::vector) >= ?
            ORDER BY embedding <=> ?::vector
            LIMIT ?";

            $results = DB::select($sql, [
                $embeddingJson,
                $table,
                $embeddingJson,
                $threshold,
                $embeddingJson,
                $limit,
            ]);
        } else {
            // Fallback to computing cosine similarity in PHP
            $documents = DB::table($table)
                ->whereNotNull('embedding')
                ->limit($limit * 3) // Get more to compensate for filtering
                ->get();

            $results = [];
            foreach ($documents as $doc) {
                $docEmbedding = json_decode($doc->embedding_vector ?? '[]', true);
                $similarity = $this->cosineSimilarity($queryEmbedding, $docEmbedding);

                if ($similarity >= $threshold) {
                    $results[] = (object) [
                        'id' => $doc->id,
                        'doc_id' => $doc->doc_id,
                        'title' => $doc->title,
                        'content' => $doc->content,
                        'metadata' => $doc->metadata,
                        'chunk_index' => $doc->chunk_index,
                        'similarity' => $similarity,
                        'corpus' => $table,
                    ];
                }
            }

            // Sort by similarity and limit
            usort($results, fn ($a, $b) => $b->similarity <=> $a->similarity);
            $results = array_slice($results, 0, $limit);
        }

        return array_map(fn ($r) => [
            'id' => $r->id,
            'doc_id' => $r->doc_id,
            'title' => $r->title,
            'content' => $r->content,
            'metadata' => is_string($r->metadata) ? json_decode($r->metadata, true) : $r->metadata,
            'chunk_index' => $r->chunk_index,
            'score' => $r->similarity,
            'corpus' => $r->corpus,
            'retrieval_method' => 'vector',
        ], $results);
    }

    /**
     * Keyword-based search using PostgreSQL full-text search or simple LIKE
     */
    protected function keywordSearch(array $normalizedQuery, array $options): array
    {
        $results = [];
        $keywords = $normalizedQuery['ključne_riječi'] ?? [];
        $limit = $options['keyword_limit'] ?? 30;

        if (empty($keywords)) {
            return [];
        }

        // Build search query
        $searchTerms = array_map(fn ($kw) => "%{$kw}%", $keywords);

        // Search laws
        if (! isset($options['exclude_corpora']) || ! in_array('laws', $options['exclude_corpora'])) {
            $lawResults = DB::table('laws')
                ->where(function ($query) use ($searchTerms) {
                    foreach ($searchTerms as $term) {
                        $query->orWhere('content', 'ILIKE', $term)
                            ->orWhere('title', 'ILIKE', $term);
                    }
                })
                ->limit($limit)
                ->get();

            foreach ($lawResults as $result) {
                $results[] = [
                    'id' => $result->id,
                    'doc_id' => $result->doc_id,
                    'title' => $result->title,
                    'content' => $result->content,
                    'metadata' => json_decode($result->metadata ?? '{}', true),
                    'chunk_index' => $result->chunk_index,
                    'score' => $this->calculateKeywordScore($result->content, $keywords),
                    'corpus' => 'laws',
                    'retrieval_method' => 'keyword',
                ];
            }
        }

        // Search cases
        if (! isset($options['exclude_corpora']) || ! in_array('cases', $options['exclude_corpora'])) {
            $caseResults = DB::table('cases_documents')
                ->where(function ($query) use ($searchTerms) {
                    foreach ($searchTerms as $term) {
                        $query->orWhere('content', 'ILIKE', $term)
                            ->orWhere('title', 'ILIKE', $term);
                    }
                })
                ->limit($limit)
                ->get();

            foreach ($caseResults as $result) {
                $results[] = [
                    'id' => $result->id,
                    'doc_id' => $result->doc_id,
                    'title' => $result->title,
                    'content' => $result->content,
                    'metadata' => json_decode($result->metadata ?? '{}', true),
                    'chunk_index' => $result->chunk_index,
                    'score' => $this->calculateKeywordScore($result->content, $keywords),
                    'corpus' => 'cases_documents',
                    'retrieval_method' => 'keyword',
                ];
            }
        }

        return $results;
    }

    /**
     * Graph-based retrieval using citations and relationships
     * Enhanced to use GraphRagOrchestrator for decision-specific queries
     */
    protected function graphSearch(array $normalizedQuery, array $citations, array $options): array
    {
        $results = [];
        $limit = $options['graph_limit'] ?? 20;

        // Extract law numbers from citations
        $lawNumbers = [];
        foreach ($citations['narodne_novine'] ?? [] as $nn) {
            if (isset($nn['issues'])) {
                $lawNumbers = array_merge($lawNumbers, $nn['issues']);
            }
        }

        // Extract case numbers
        $caseNumbers = [];
        foreach ($citations['case_numbers'] ?? [] as $case) {
            if (isset($case['canonical'])) {
                $caseNumbers[] = $case['canonical'];
            }
        }

        // Extract statute citations (law + article)
        $statuteCitations = $citations['statutes'] ?? [];

        // Strategy 1: Find decisions citing specific law articles (new graph query)
        if (! empty($statuteCitations)) {
            try {
                foreach (array_slice($statuteCitations, 0, 3) as $statute) {
                    $lawIdentifier = $statute['law'] ?? null;
                    $article = $statute['article'] ?? null;

                    if ($lawIdentifier) {
                        // Build options array, only including non-null values
                        $graphOptions = [];
                        if (! empty($normalizedQuery['jurisdikcija'])) {
                            $graphOptions['jurisdiction'] = $normalizedQuery['jurisdikcija'];
                        }

                        $graphDecisions = $this->graphRag->findDecisionsCitingLawArticle(
                            $lawIdentifier,
                            $article,
                            $graphOptions,
                            15
                        );

                        foreach ($graphDecisions as $graphDecision) {
                            $decision = $graphDecision['decision'];
                            $citedLaw = $graphDecision['cited_law'];
                            $citation = $graphDecision['citation'];

                            // Fetch the full document from PostgreSQL
                            $doc = DB::table('court_decision_documents')
                                ->where('id', $decision['id'])
                                ->first();

                            if ($doc) {
                                $results[] = [
                                    'id' => $doc->id,
                                    'doc_id' => $doc->doc_id,
                                    'title' => $doc->title,
                                    'content' => $doc->content,
                                    'metadata' => json_decode($doc->metadata ?? '{}', true),
                                    'chunk_index' => $doc->chunk_index,
                                    'score' => 0.95, // High score for article-specific citation
                                    'corpus' => 'court_decision_documents',
                                    'retrieval_method' => 'graph_decision_citation',
                                    'graph_context' => [
                                        'cited_law' => $citedLaw['title'] ?? 'Unknown',
                                        'article' => $citation['article'],
                                        'paragraph' => $citation['paragraph'],
                                        'law_abbreviation' => $citation['law_abbreviation'],
                                    ],
                                ];
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Graph query failed for statute citations', [
                    'error' => $e->getMessage(),
                ]);
                // Continue with other strategies even if graph query fails
            }
        }

        // Strategy 2: Find laws by citation (existing logic, but enhanced)
        if (! empty($lawNumbers)) {
            foreach ($lawNumbers as $lawNumber) {
                $laws = DB::table('laws')
                    ->where('law_number', $lawNumber)
                    ->limit(10)
                    ->get();

                foreach ($laws as $law) {
                    $results[] = [
                        'id' => $law->id,
                        'doc_id' => $law->doc_id,
                        'title' => $law->title,
                        'content' => $law->content,
                        'metadata' => json_decode($law->metadata ?? '{}', true),
                        'chunk_index' => $law->chunk_index,
                        'score' => 0.95, // High score for direct citation match
                        'corpus' => 'laws',
                        'retrieval_method' => 'graph_citation',
                    ];
                }
            }
        }

        // Strategy 3: Find cases by citation (existing logic)
        if (! empty($caseNumbers)) {
            foreach ($caseNumbers as $caseNumber) {
                $cases = DB::table('cases_documents')
                    ->where('doc_id', 'LIKE', "%{$caseNumber}%")
                    ->limit(10)
                    ->get();

                foreach ($cases as $case) {
                    $results[] = [
                        'id' => $case->id,
                        'doc_id' => $case->doc_id,
                        'title' => $case->title,
                        'content' => $case->content,
                        'metadata' => json_decode($case->metadata ?? '{}', true),
                        'chunk_index' => $case->chunk_index,
                        'score' => 0.95, // High score for direct citation match
                        'corpus' => 'cases_documents',
                        'retrieval_method' => 'graph_citation',
                    ];
                }
            }
        }

        // Strategy 4: Search by legal concepts extracted from query (new graph query)
        $keywords = $normalizedQuery['ključne_riječi'] ?? [];
        if (! empty($keywords) && ! isset($options['skip_concept_search'])) {
            try {
                // Take the top 2 most relevant keywords
                $topKeywords = array_slice($keywords, 0, 2);

                foreach ($topKeywords as $concept) {
                    // Build options array, only including non-null values
                    $conceptOptions = [];
                    if (! empty($normalizedQuery['jurisdikcija'])) {
                        $conceptOptions['jurisdiction'] = $normalizedQuery['jurisdikcija'];
                    }

                    $conceptDecisions = $this->graphRag->findDecisionsByLegalConcept(
                        $concept,
                        $conceptOptions,
                        10
                    );

                    foreach ($conceptDecisions as $conceptDecision) {
                        $decision = $conceptDecision['decision'];

                        // Fetch the full document from PostgreSQL
                        $doc = DB::table('court_decision_documents')
                            ->where('id', $decision['id'])
                            ->first();

                        if ($doc) {
                            $results[] = [
                                'id' => $doc->id,
                                'doc_id' => $doc->doc_id,
                                'title' => $doc->title,
                                'content' => $doc->content,
                                'metadata' => json_decode($doc->metadata ?? '{}', true),
                                'chunk_index' => $doc->chunk_index,
                                'score' => 0.7, // Lower score for concept match
                                'corpus' => 'court_decision_documents',
                                'retrieval_method' => 'graph_concept',
                                'graph_context' => [
                                    'matched_concept' => $concept,
                                ],
                            ];
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Graph query failed for concept search', [
                    'error' => $e->getMessage(),
                ]);
                // Continue with other strategies even if graph query fails
            }
        }

        // Deduplicate results by id
        $uniqueResults = [];
        $seen = [];

        foreach ($results as $result) {
            $key = $result['corpus'].':'.$result['id'];
            if (! isset($seen[$key])) {
                $uniqueResults[] = $result;
                $seen[$key] = true;
            }
        }

        return array_slice($uniqueResults, 0, $limit);
    }

    /**
     * Reciprocal Rank Fusion (RRF) - merges multiple ranked lists
     * Formula: RRF(d) = Σ 1/(k + rank(d))
     */
    protected function reciprocalRankFusion(array $rankedLists, int $k = 60): array
    {
        $documentScores = [];

        foreach ($rankedLists as $listName => $documents) {
            foreach ($documents as $rank => $doc) {
                $docKey = $doc['corpus'].':'.$doc['id'];

                if (! isset($documentScores[$docKey])) {
                    $documentScores[$docKey] = [
                        'doc' => $doc,
                        'rrf_score' => 0,
                        'sources' => [],
                    ];
                }

                // RRF formula: 1 / (k + rank)
                $rrfContribution = 1.0 / ($k + $rank + 1);
                $documentScores[$docKey]['rrf_score'] += $rrfContribution;
                $documentScores[$docKey]['sources'][] = [
                    'method' => $listName,
                    'rank' => $rank,
                    'original_score' => $doc['score'],
                    'rrf_contribution' => $rrfContribution,
                ];
            }
        }

        // Sort by RRF score
        uasort($documentScores, fn ($a, $b) => $b['rrf_score'] <=> $a['rrf_score']);

        // Extract documents and add RRF score
        $mergedDocs = [];
        foreach ($documentScores as $docData) {
            $doc = $docData['doc'];
            $doc['rrf_score'] = $docData['rrf_score'];
            $doc['rrf_sources'] = $docData['sources'];
            $mergedDocs[] = $doc;
        }

        return $mergedDocs;
    }

    /**
     * Maximal Marginal Relevance (MMR) - balances relevance and diversity
     * MMR = λ * Sim(D, Q) - (1-λ) * max Sim(D, Di)
     */
    protected function maximalMarginalRelevance(
        array $candidates,
        array $queryEmbedding,
        float $lambda,
        int $topK
    ): array {
        if (empty($candidates)) {
            return [];
        }

        $selected = [];
        $remaining = $candidates;

        // Precompute embeddings for all candidates
        $candidateEmbeddings = [];
        foreach ($remaining as $idx => $doc) {
            // Try to get embedding from database or compute on the fly
            $embedding = $this->getDocumentEmbedding($doc);
            if ($embedding) {
                $candidateEmbeddings[$idx] = $embedding;
            }
        }

        while (count($selected) < $topK && ! empty($remaining)) {
            $bestScore = -INF;
            $bestIdx = null;

            foreach ($remaining as $idx => $doc) {
                if (! isset($candidateEmbeddings[$idx])) {
                    continue;
                }

                $docEmbedding = $candidateEmbeddings[$idx];

                // Relevance to query
                $relevance = $this->cosineSimilarity($docEmbedding, $queryEmbedding);

                // Diversity (similarity to already selected documents)
                $maxSimilarity = 0;
                foreach ($selected as $selectedDoc) {
                    $selectedIdx = $selectedDoc['_original_idx'];
                    if (isset($candidateEmbeddings[$selectedIdx])) {
                        $similarity = $this->cosineSimilarity(
                            $docEmbedding,
                            $candidateEmbeddings[$selectedIdx]
                        );
                        $maxSimilarity = max($maxSimilarity, $similarity);
                    }
                }

                // MMR formula
                $mmrScore = $lambda * $relevance - (1 - $lambda) * $maxSimilarity;

                if ($mmrScore > $bestScore) {
                    $bestScore = $mmrScore;
                    $bestIdx = $idx;
                }
            }

            if ($bestIdx !== null) {
                $selectedDoc = $remaining[$bestIdx];
                $selectedDoc['mmr_score'] = $bestScore;
                $selectedDoc['_original_idx'] = $bestIdx;
                $selected[] = $selectedDoc;
                unset($remaining[$bestIdx]);
            } else {
                break; // No valid candidates remaining
            }
        }

        return $selected;
    }

    /**
     * Apply per-corpus result caps
     */
    protected function applyCorpusCaps(array $results, array $corpusCaps): array
    {
        try {
            if (empty($corpusCaps)) {
                return $results;
            }

            if (empty($results)) {
                return [];
            }

            $corpusCounts = [];
            $cappedResults = [];

            foreach ($results as $result) {
                if (! is_array($result) || ! isset($result['corpus'])) {
                    Log::debug('Skipping invalid result in corpus capping');

                    continue;
                }

                $corpus = $result['corpus'];
                $cap = $corpusCaps[$corpus] ?? PHP_INT_MAX;

                $currentCount = $corpusCounts[$corpus] ?? 0;

                if ($currentCount < $cap) {
                    $cappedResults[] = $result;
                    $corpusCounts[$corpus] = $currentCount + 1;
                }
            }

            return $cappedResults;

        } catch (\Exception $e) {
            Log::error('Failed to apply corpus caps', ['error' => $e->getMessage()]);

            return $results; // Return original results on error
        }
    }

    /**
     * Calculate confidence scores for retrieved chunks
     */
    protected function calculateConfidence(array $results, array $normalizedQuery, array $citations): array
    {
        foreach ($results as &$result) {
            $confidence = 0;

            // Base confidence from retrieval score
            if (isset($result['mmr_score'])) {
                $confidence += $result['mmr_score'] * 0.4;
            } elseif (isset($result['rrf_score'])) {
                $confidence += min($result['rrf_score'] / 10, 1.0) * 0.4;
            } elseif (isset($result['score'])) {
                $confidence += $result['score'] * 0.4;
            }

            // Boost for citation matches
            if ($result['retrieval_method'] === 'graph_citation') {
                $confidence += 0.3;
            }

            // Boost for keyword matches
            $keywordScore = $this->calculateKeywordScore(
                $result['content'],
                $normalizedQuery['ključne_riječi'] ?? []
            );
            $confidence += $keywordScore * 0.2;

            // Boost for multiple retrieval methods
            if (isset($result['rrf_sources']) && count($result['rrf_sources']) > 1) {
                $confidence += 0.1;
            }

            // Normalize to [0, 1]
            $result['confidence'] = min(max($confidence, 0), 1.0);
        }

        // Filter out low-confidence results
        $results = array_filter($results, fn ($r) => $r['confidence'] >= self::MIN_CONFIDENCE_THRESHOLD
        );

        return array_values($results);
    }

    /**
     * Enrich results with additional metadata
     */
    protected function enrichMetadata(array $results, array $normalizedQuery): array
    {
        foreach ($results as &$result) {
            $result['_metadata'] = [
                'query_jurisdiction' => $normalizedQuery['jurisdikcija'] ?? null,
                'query_case_id' => $normalizedQuery['case_id'] ?? null,
                'chunk_length' => mb_strlen($result['content']),
                'corpus_type' => $this->getCorpusType($result['corpus']),
            ];
        }

        return $results;
    }

    /**
     * Calculate keyword score for a document
     */
    protected function calculateKeywordScore(string $content, array $keywords): float
    {
        try {
            if (empty($keywords)) {
                return 0.0;
            }

            if (empty($content)) {
                return 0.0;
            }

            $contentLower = mb_strtolower($content);
            $matches = 0;

            foreach ($keywords as $keyword) {
                if (! is_string($keyword) || empty($keyword)) {
                    continue;
                }

                $keywordLower = mb_strtolower($keyword);
                if (mb_strpos($contentLower, $keywordLower) !== false) {
                    $matches++;
                }
            }

            return count($keywords) > 0 ? ($matches / count($keywords)) : 0.0;

        } catch (\Exception $e) {
            Log::debug('Keyword score calculation failed', ['error' => $e->getMessage()]);

            return 0.0;
        }
    }

    /**
     * Get document embedding (from cache or compute)
     */
    protected function getDocumentEmbedding(array $doc): ?array
    {
        try {
            // Check if embedding is already in the document
            if (isset($doc['embedding'])) {
                if (is_array($doc['embedding'])) {
                    return $doc['embedding'];
                }
                if (is_string($doc['embedding'])) {
                    $embedding = json_decode($doc['embedding'], true);
                    if (is_array($embedding)) {
                        return $embedding;
                    }
                }
            }

            // Try to fetch from database if we have the ID
            if (isset($doc['id']) && isset($doc['corpus'])) {
                $result = DB::table($doc['corpus'])
                    ->where('id', $doc['id'])
                    ->first(['embedding_vector']);

                if ($result && isset($result->embedding_vector)) {
                    $embedding = json_decode($result->embedding_vector, true);
                    if (is_array($embedding)) {
                        return $embedding;
                    }
                }
            }

            return null;

        } catch (\Exception $e) {
            Log::debug('Failed to get document embedding', [
                'doc_id' => $doc['id'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Calculate cosine similarity between two vectors
     */
    protected function cosineSimilarity(array $vec1, array $vec2): float
    {
        try {
            if (empty($vec1) || empty($vec2)) {
                return 0.0;
            }

            if (count($vec1) !== count($vec2)) {
                Log::warning('Vector dimension mismatch in cosine similarity', [
                    'vec1_length' => count($vec1),
                    'vec2_length' => count($vec2),
                ]);

                return 0.0;
            }

            $dotProduct = 0.0;
            $norm1 = 0.0;
            $norm2 = 0.0;

            for ($i = 0; $i < count($vec1); $i++) {
                if (! is_numeric($vec1[$i]) || ! is_numeric($vec2[$i])) {
                    Log::warning('Non-numeric value in cosine similarity calculation');

                    continue;
                }

                $val1 = (float) $vec1[$i];
                $val2 = (float) $vec2[$i];

                $dotProduct += $val1 * $val2;
                $norm1 += $val1 * $val1;
                $norm2 += $val2 * $val2;
            }

            $norm1 = sqrt($norm1);
            $norm2 = sqrt($norm2);

            if ($norm1 == 0 || $norm2 == 0) {
                return 0.0;
            }

            $similarity = $dotProduct / ($norm1 * $norm2);

            return max(0.0, min(1.0, $similarity));

        } catch (\Exception $e) {
            Log::error('Cosine similarity calculation failed', [
                'error' => $e->getMessage(),
            ]);

            return 0.0;
        }
    }

    /**
     * Get corpus type for metadata
     */
    protected function getCorpusType(string $corpus): string
    {
        return match ($corpus) {
            'laws' => 'legislation',
            'cases_documents' => 'case_law',
            'court_decision_documents' => 'court_decisions',
            default => 'unknown',
        };
    }
}
