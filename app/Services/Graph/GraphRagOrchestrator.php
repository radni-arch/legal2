<?php

namespace App\Services\Graph;

use App\Services\AdvancedKeywordExtractor;
use App\Services\GraphDatabaseService;
use App\Services\TaggingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrator for Graph RAG operations
 *
 * This service coordinates graph synchronization and querying operations,
 * delegating to specialized services where available.
 *
 * Architecture:
 * - Delegates case document sync to CaseGraphSyncService
 * - Directly implements law, decision, and textract sync (to be extracted)
 * - Provides enhanced RAG query capabilities
 * - Manages citation networks and relationships
 */
class GraphRagOrchestrator
{
    public function __construct(
        protected GraphDatabaseService $graph,
        protected TaggingService $tagging,
        protected CaseGraphSyncService $caseSync,
        protected ?TextractGraphSyncService $textractSync = null,
        protected ?AdvancedKeywordExtractor $advancedExtractor = null,
        protected ?GraphCitationLinker $citationLinker = null,
        protected ?GraphSimilarityLinker $similarityLinker = null
    ) {}

    /**
     * Sync a law document to graph database
     */
    public function syncLaw(string $lawId): void
    {
        // Skip sync if Neo4j sync is disabled
        if (! config('neo4j.sync.enabled', true)) {
            return;
        }

        $law = DB::table('laws')->where('id', $lawId)->first();

        if (! $law) {
            return;
        }

        // Create law document node
        $this->graph->upsertNode('LawDocument', $law->id, [
            'doc_id' => $law->doc_id,
            'title' => $law->title,
            'law_number' => $law->law_number,
            'jurisdiction' => $law->jurisdiction,
            'country' => $law->country,
            'language' => $law->language,
            'chunk_index' => $law->chunk_index,
            'content_hash' => $law->content_hash,
            'effective_date' => $law->effective_date,
            'promulgation_date' => $law->promulgation_date,
        ]);

        // Create jurisdiction node if exists
        if ($law->jurisdiction) {
            $this->graph->upsertNode('Jurisdiction', 'jurisdiction_'.$law->jurisdiction, [
                'name' => $law->jurisdiction,
            ]);

            $this->graph->createRelationship(
                'LawDocument',
                $law->id,
                'BELONGS_TO_JURISDICTION',
                'Jurisdiction',
                'jurisdiction_'.$law->jurisdiction
            );
        }

        // Auto-tag the law
        $metadata = json_decode($law->metadata ?? '[]', true);
        if (! is_array($metadata)) {
            $metadata = [];
        }
        $this->tagging->autoTag('LawDocument', $law->id, $law->content, array_merge($metadata, [
            'jurisdiction' => $law->jurisdiction,
            'law_number' => $law->law_number,
        ]));

        // Create keyword relationships
        $this->extractAndLinkKeywords('LawDocument', $law->id, $law->content);

        // Extract and create citation relationships
        if ($this->citationLinker) {
            $this->citationLinker->linkCitations('LawDocument', $law->id, $law->content);
        }

        // Find and create similarity relationships
        if ($this->similarityLinker && $law->embedding_vector) {
            $this->similarityLinker->linkSimilar('LawDocument', $law->id);
        }
    }

    /**
     * Sync a case document to graph database
     * Delegates to CaseGraphSyncService
     */
    public function syncCase(string $caseDocId): void
    {
        try {
            $this->caseSync->sync($caseDocId);
        } catch (\InvalidArgumentException $e) {
            // Document not found - this is expected behavior, just log debug
            Log::debug('Case document not found for sync', [
                'document_id' => $caseDocId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Sync a court decision to graph database
     */
    public function syncCourtDecision(string $decisionId): void
    {
        // Get the CourtDecision parent record
        $decision = DB::table('court_decisions')->where('id', $decisionId)->first();

        if (! $decision) {
            return;
        }

        // Get all document chunks for this decision
        $documents = DB::table('court_decision_documents')
            ->where('decision_id', $decisionId)
            ->get();

        foreach ($documents as $doc) {
            // Create court decision document node
            $this->graph->upsertNode('CourtDecisionDocument', $doc->id, [
                'decision_id' => $doc->decision_id,
                'doc_id' => $doc->doc_id,
                'title' => $doc->title ?? $decision->title,
                'case_number' => $decision->case_number,
                'court' => $decision->court,
                'jurisdiction' => $decision->jurisdiction,
                'judge' => $decision->judge,
                'decision_date' => $decision->decision_date,
                'publication_date' => $decision->publication_date,
                'decision_type' => $decision->decision_type,
                'register' => $decision->register,
                'finality' => $decision->finality,
                'ecli' => $decision->ecli,
                'chunk_index' => $doc->chunk_index,
                'content_hash' => $doc->content_hash,
            ]);

            // Create court node if exists
            if ($decision->court) {
                $courtId = 'court_'.md5($decision->court);
                $this->graph->upsertNode('Court', $courtId, [
                    'name' => $decision->court,
                    'jurisdiction' => $decision->jurisdiction,
                ]);

                $this->graph->createRelationship(
                    'CourtDecisionDocument',
                    $doc->id,
                    'DECIDED_BY',
                    'Court',
                    $courtId
                );
            }

            // Create jurisdiction node if exists
            if ($decision->jurisdiction) {
                $this->graph->upsertNode('Jurisdiction', 'jurisdiction_'.$decision->jurisdiction, [
                    'name' => $decision->jurisdiction,
                ]);

                $this->graph->createRelationship(
                    'CourtDecisionDocument',
                    $doc->id,
                    'BELONGS_TO_JURISDICTION',
                    'Jurisdiction',
                    'jurisdiction_'.$decision->jurisdiction
                );
            }

            // Auto-tag the decision
            $metadata = json_decode($doc->metadata ?? '[]', true);
            $this->tagging->autoTag('CourtDecisionDocument', $doc->id, $doc->content, array_merge($metadata, [
                'court' => $decision->court,
                'case_number' => $decision->case_number,
                'decision_type' => $decision->decision_type,
            ]));

            // Create keyword relationships
            $this->extractAndLinkKeywords('CourtDecisionDocument', $doc->id, $doc->content);

            // Extract and create citation relationships
            if ($this->citationLinker) {
                $this->citationLinker->linkCitations('CourtDecisionDocument', $doc->id, $doc->content);
            }

            // Find and create similarity relationships
            if ($this->similarityLinker && $doc->embedding_vector) {
                $this->similarityLinker->linkSimilar('CourtDecisionDocument', $doc->id);
            }
        }
    }

    /**
     * Sync a single textract document to graph database
     * Delegates to TextractGraphSyncService
     */
    public function syncTextract(string $textractDocId): void
    {
        if ($this->textractSync) {
            try {
                $this->textractSync->sync($textractDocId);
            } catch (\InvalidArgumentException $e) {
                // Document not found - this is expected behavior, just log debug
                Log::debug('Textract document not found for sync', [
                    'document_id' => $textractDocId,
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            Log::warning('TextractGraphSyncService not available', [
                'document_id' => $textractDocId,
            ]);
        }
    }

    /**
     * Sync a textract job to graph database
     */
    public function syncTextractJob(int $textractJobId): void
    {
        $job = DB::table('textract_jobs')->where('id', $textractJobId)->first();

        if (! $job) {
            return;
        }

        // Update graph sync status to processing
        DB::table('textract_jobs')
            ->where('id', $textractJobId)
            ->update(['graph_sync_status' => 'processing']);

        try {
            // Get all document chunks for this textract job
            $documents = DB::table('textract_documents')
                ->where('textract_job_id', $textractJobId)
                ->where('processing_status', 'completed')
                ->get();

            if ($documents->isEmpty()) {
                Log::info('No completed documents to sync for TextractJob', ['job_id' => $textractJobId]);

                return;
            }

            // Create a TextractDocument node for each chunk
            foreach ($documents as $doc) {
                // Create textract document node
                $this->graph->upsertNode('TextractDocument', (string) $doc->id, [
                    'textract_job_id' => $job->id,
                    'case_id' => $job->case_id,
                    'drive_file_id' => $job->drive_file_id,
                    'drive_file_name' => $job->drive_file_name,
                    'chunk_index' => $doc->chunk_index,
                    'token_count' => $doc->token_count,
                    'manually_edited' => $job->manually_edited ?? false,
                    'embedding_provider' => $doc->embedding_provider,
                    'embedding_model' => $doc->embedding_model,
                ]);

                // Link to CaseDocument if the job has a case_id
                if ($job->case_id) {
                    // Find the case document for this case
                    $caseDoc = DB::table('cases_documents')
                        ->where('case_id', $job->case_id)
                        ->first();

                    if ($caseDoc) {
                        // Ensure case node exists
                        $this->graph->upsertNode('CaseDocument', $caseDoc->id, [
                            'case_id' => $caseDoc->case_id,
                            'doc_id' => $caseDoc->doc_id,
                            'title' => $caseDoc->title,
                        ]);

                        // Create BELONGS_TO relationship
                        $this->graph->createRelationship(
                            'TextractDocument',
                            (string) $doc->id,
                            'BELONGS_TO',
                            'CaseDocument',
                            $caseDoc->id,
                            [
                                'created_at' => now()->toIso8601String(),
                            ]
                        );
                    }
                }

                // Extract keywords and create relationships
                $this->extractAndLinkKeywords('TextractDocument', (string) $doc->id, $doc->content);

                // Extract citations
                if ($this->citationLinker) {
                    $this->citationLinker->linkCitations('TextractDocument', (string) $doc->id, $doc->content);
                }

                // Find similar documents and create relationships
                if ($this->similarityLinker && $doc->embedding) {
                    $this->similarityLinker->linkSimilar('TextractDocument', (string) $doc->id);
                }

                // Auto-tag the document
                $metadata = json_decode($doc->metadata ?? '[]', true);
                $this->tagging->autoTag('TextractDocument', (string) $doc->id, $doc->content, array_merge($metadata, [
                    'drive_file_name' => $job->drive_file_name,
                    'case_id' => $job->case_id,
                ]));
            }

            // Mark job as synced
            DB::table('textract_jobs')
                ->where('id', $textractJobId)
                ->update([
                    'graph_sync_status' => 'synced',
                    'graph_synced_at' => now(),
                ]);

            Log::info('TextractJob synced to graph successfully', [
                'job_id' => $textractJobId,
                'documents_synced' => $documents->count(),
            ]);

        } catch (\Exception $e) {
            // Mark job as failed
            DB::table('textract_jobs')
                ->where('id', $textractJobId)
                ->update([
                    'graph_sync_status' => 'failed',
                    'error' => 'Graph sync failed: '.$e->getMessage(),
                ]);

            Log::error('Failed to sync TextractJob to graph', [
                'job_id' => $textractJobId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Generic sync method that routes to appropriate sync handler
     *
     * @param  string  $type  Document type (law, case, decision, textract)
     * @param  string|int  $id  Document ID
     */
    public function syncDocument(string $type, string|int $id): void
    {
        match ($type) {
            'law' => $this->syncLaw($id),
            'case' => $this->syncCase($id),
            'decision' => $this->syncCourtDecision($id),
            'textract' => $this->syncTextractJob((int) $id),
            default => throw new \InvalidArgumentException("Unknown document type: {$type}")
        };
    }

    /**
     * Extract keywords and create keyword nodes with relationships
     */
    protected function extractAndLinkKeywords(string $nodeLabel, string $nodeId, string $content): void
    {
        // Delegate to advanced extractor if available and enabled
        if ($this->advancedExtractor && config('keywords.use_hybrid', true)) {
            try {
                $keywords = $this->advancedExtractor->extract($content);

                if (config('keywords.log_extraction', false)) {
                    Log::info('GraphRagOrchestrator - Using hybrid keyword extraction', [
                        'node_label' => $nodeLabel,
                        'node_id' => $nodeId,
                        'keywords_count' => count($keywords),
                    ]);
                }
            } catch (\Exception $e) {
                // Fall back to legacy extraction on error
                if (config('keywords.log_fallback', true)) {
                    Log::warning('GraphRagOrchestrator - Hybrid extraction failed, using legacy method', [
                        'node_label' => $nodeLabel,
                        'node_id' => $nodeId,
                        'error' => $e->getMessage(),
                    ]);
                }

                $keywords = $this->extractKeywords($content);
            }
        } else {
            // Use legacy extraction when hybrid is disabled or extractor unavailable
            $keywords = $this->extractKeywords($content);
        }

        foreach ($keywords as $keyword => $weight) {
            $keywordId = 'keyword_'.md5(strtolower($keyword));

            $this->graph->upsertNode('Keyword', $keywordId, [
                'name' => $keyword,
                'normalized' => mb_strtolower($keyword),
            ]);

            $this->graph->createRelationship(
                $nodeLabel,
                $nodeId,
                'HAS_KEYWORD',
                'Keyword',
                $keywordId,
                ['weight' => $weight]
            );
        }
    }

    /**
     * Extract keywords from content with legal term boosting (LEGACY METHOD)
     */
    protected function extractKeywords(string $content, int $maxKeywords = 10): array
    {
        // Croatian legal terms with boost weights
        $legalTerms = [
            'ugovor' => 2.5, 'obveza' => 2.5, 'pravo' => 2.5, 'zakon' => 3.0,
            'odredba' => 2.5, 'postupak' => 2.5, 'naknada' => 2.0, 'presuda' => 2.5,
            'odluka' => 2.5, 'rješenje' => 2.0, 'tužitelj' => 2.0, 'tuženik' => 2.0,
            'stranka' => 2.0, 'sud' => 2.5, 'sudac' => 2.0, 'svjedok' => 2.0,
            'odvjetnik' => 2.0, 'žalba' => 2.0, 'tužba' => 2.5, 'parnica' => 2.0,
            'izvršenje' => 2.0, 'dokazivanje' => 2.0, 'saslušanje' => 2.0, 'pretres' => 2.0,
            'ništavost' => 2.0, 'poništenje' => 2.0, 'razvrgnuće' => 2.0, 'prekid' => 1.8,
            'prestanak' => 1.8, 'stupanje' => 1.8, 'kazneno' => 2.0, 'građansko' => 2.0,
            'upravno' => 2.0, 'trgovačko' => 2.0, 'radno' => 1.8, 'obiteljsko' => 1.8,
            'zakonit' => 2.0, 'nezakonit' => 2.0, 'valjan' => 1.8, 'ništav' => 2.0,
            'pravomočan' => 2.0, 'izvršan' => 1.8, 'uredba' => 2.0, 'pravilnik' => 2.0,
            'statut' => 2.0, 'protokol' => 1.8, 'sporazum' => 2.0, 'konvencija' => 2.0,
        ];

        // Remove common Croatian stopwords
        $stopwords = ['je', 'su', 'biti', 'ima', 'da', 'za', 'na', 'u', 'i', 'ili', 'te', 'se', 'by', 'the', 'of', 'and', 'to', 'a', 'in', 'koji', 'koja', 'koje', 'ovaj', 'taj'];

        // Tokenize and clean
        $words = preg_split('/\s+/', mb_strtolower($content));
        $words = array_filter($words, fn ($w) => mb_strlen($w) > 3 && ! in_array($w, $stopwords));

        // Count frequencies
        $frequencies = array_count_values($words);

        // Apply legal term boosting
        foreach ($frequencies as $word => $freq) {
            if (isset($legalTerms[$word])) {
                $frequencies[$word] = $freq * $legalTerms[$word];
            }
        }

        arsort($frequencies);

        // Get top keywords and normalize weights
        $topKeywords = array_slice($frequencies, 0, $maxKeywords, true);
        $maxFreq = max($topKeywords ?: [1]);

        return array_map(fn ($freq) => round($freq / $maxFreq, 2), $topKeywords);
    }

    /**
     * Batch sync all laws to graph database
     */
    public function syncAllLaws(): array
    {
        $batchSize = config('neo4j.sync.batch_size', 100);
        $synced = 0;
        $errors = 0;

        DB::table('laws')
            ->orderBy('id')
            ->chunk($batchSize, function ($laws) use (&$synced, &$errors) {
                foreach ($laws as $law) {
                    try {
                        $this->syncLaw($law->id);
                        $synced++;
                    } catch (\Exception $e) {
                        $errors++;
                        Log::error('Failed to sync law to graph', [
                            'law_id' => $law->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        return ['synced' => $synced, 'errors' => $errors];
    }

    /**
     * Batch sync all cases to graph database
     */
    public function syncAllCases(): array
    {
        $batchSize = config('neo4j.sync.batch_size', 100);
        $synced = 0;
        $errors = 0;

        DB::table('cases_documents')
            ->orderBy('id')
            ->chunk($batchSize, function ($cases) use (&$synced, &$errors) {
                foreach ($cases as $case) {
                    try {
                        $this->syncCase($case->id);
                        $synced++;
                    } catch (\Exception $e) {
                        $errors++;
                        Log::error('Failed to sync case to graph', [
                            'case_id' => $case->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        return ['synced' => $synced, 'errors' => $errors];
    }

    /**
     * Batch sync all court decisions to graph database
     */
    public function syncAllCourtDecisions(): array
    {
        $batchSize = config('neo4j.sync.batch_size', 100);
        $synced = 0;
        $errors = 0;

        DB::table('court_decisions')
            ->orderBy('id')
            ->chunk($batchSize, function ($decisions) use (&$synced, &$errors) {
                foreach ($decisions as $decision) {
                    try {
                        $this->syncCourtDecision($decision->id);
                        $synced++;
                    } catch (\Exception $e) {
                        $errors++;
                        Log::error('Failed to sync court decision to graph', [
                            'decision_id' => $decision->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        return ['synced' => $synced, 'errors' => $errors];
    }

    /**
     * Sync all textract jobs to graph database
     */
    public function syncAllTextractJobs(): array
    {
        $batchSize = config('neo4j.sync.batch_size', 100);
        $synced = 0;
        $errors = 0;

        DB::table('textract_jobs')
            ->where('status', 'completed')
            ->where(function ($query) {
                $query->whereNull('graph_sync_status')
                    ->orWhere('graph_sync_status', 'pending');
            })
            ->orderBy('id')
            ->chunk($batchSize, function ($jobs) use (&$synced, &$errors) {
                foreach ($jobs as $job) {
                    try {
                        $this->syncTextractJob($job->id);
                        $synced++;
                    } catch (\Exception $e) {
                        $errors++;
                        Log::error('Failed to sync textract job to graph', [
                            'job_id' => $job->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        return ['synced' => $synced, 'errors' => $errors];
    }

    /**
     * Enhanced RAG query using graph relationships
     */
    public function enhancedQuery(string $query, string $contextType = 'both', int $limit = 10): array
    {
        $results = [
            'direct_matches' => [],
            'related_via_tags' => [],
            'related_via_keywords' => [],
            'similar_documents' => [],
            'graph_context' => [],
        ];

        // Extract keywords from query
        $queryKeywords = $this->extractKeywords($query, 5);

        // Find documents by keywords
        foreach (array_keys($queryKeywords) as $keyword) {
            $keywordId = 'keyword_'.md5(strtolower($keyword));

            $nodeTypes = match ($contextType) {
                'law' => ['LawDocument'],
                'case' => ['CaseDocument'],
                default => ['LawDocument', 'CaseDocument'],
            };

            foreach ($nodeTypes as $nodeType) {
                try {
                    $cypher = "MATCH (n:$nodeType)-[r:HAS_KEYWORD]->(k:Keyword {id: \$keywordId})
                               RETURN n, r.weight as weight
                               ORDER BY weight DESC
                               LIMIT \$limit";

                    $result = $this->graph->run($cypher, [
                        'keywordId' => $keywordId,
                        'limit' => $limit,
                    ]);

                    foreach ($result as $record) {
                        $results['related_via_keywords'][] = [
                            'node' => $record->get('n')->getProperties(),
                            'weight' => $record->get('weight'),
                            'keyword' => $keyword,
                        ];
                    }
                } catch (\Exception $e) {
                    Log::warning('Graph query failed', ['error' => $e->getMessage()]);
                }
            }
        }

        return $results;
    }

    /**
     * Get graph context for a document (related documents, tags, keywords)
     */
    public function getGraphContext(string $nodeLabel, string $nodeId, int $depth = 2): array
    {
        $context = [
            'node' => null,
            'tags' => [],
            'keywords' => [],
            'similar' => [],
            'related' => [],
            'cited_laws' => [],
            'referenced_cases' => [],
        ];

        try {
            // Get the node
            $nodeData = $this->graph->getNode($nodeLabel, $nodeId);
            if ($nodeData) {
                $context['node'] = $nodeData;
            }

            // Get tags
            $context['tags'] = $this->tagging->getNodeTags($nodeLabel, $nodeId);

            // Get keywords
            $cypher = "MATCH (n:$nodeLabel {id: \$id})-[r:HAS_KEYWORD]->(k:Keyword)
                       RETURN k.name as keyword, r.weight as weight
                       ORDER BY weight DESC";

            $result = $this->graph->run($cypher, ['id' => $nodeId]);
            $context['keywords'] = $result->map(fn ($r) => [
                'keyword' => $r->get('keyword'),
                'weight' => $r->get('weight'),
            ])->toArray();

            // Get similar documents
            $cypher = "MATCH (n:$nodeLabel {id: \$id})-[r:SIMILAR_TO]->(similar)
                       RETURN similar, r.similarity as similarity
                       ORDER BY similarity DESC
                       LIMIT 10";

            $result = $this->graph->run($cypher, ['id' => $nodeId]);
            $context['similar'] = $result->map(fn ($r) => [
                'document' => $r->get('similar')->getProperties(),
                'similarity' => $r->get('similarity'),
            ])->toArray();

            // Get related documents through shared tags
            $cypher = "MATCH (n:$nodeLabel {id: \$id})-[:HAS_TAG]->(t:Tag)<-[:HAS_TAG]-(related)
                       WHERE n <> related
                       RETURN DISTINCT related, count(t) as shared_tags
                       ORDER BY shared_tags DESC
                       LIMIT 10";

            $result = $this->graph->run($cypher, ['id' => $nodeId]);
            $context['related'] = $result->map(fn ($r) => [
                'document' => $r->get('related')->getProperties(),
                'shared_tags' => $r->get('shared_tags'),
            ])->toArray();

        } catch (\Exception $e) {
            Log::warning('Failed to get graph context', [
                'node_label' => $nodeLabel,
                'node_id' => $nodeId,
                'error' => $e->getMessage(),
            ]);
        }

        return $context;
    }

    /**
     * Get all laws cited by a document
     */
    public function getCitedLaws(string $nodeLabel, string $nodeId): array
    {
        try {
            $cypher = "MATCH (n:$nodeLabel {id: \$id})-[r:CITES]->(law:LawDocument)
                       RETURN law, r.citation_type as citation_type, r.article as article
                       ORDER BY law.law_number";

            $result = $this->graph->run($cypher, ['id' => $nodeId]);

            return $result->map(fn ($r) => [
                'law' => $r->get('law')->getProperties(),
                'citation_type' => $r->get('citation_type'),
                'article' => $r->get('article'),
            ])->toArray();
        } catch (\Exception $e) {
            Log::error('Failed to get cited laws', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Get all documents that cite a specific law
     */
    public function getCitingDocuments(string $lawId): array
    {
        try {
            $cypher = 'MATCH (doc)-[r:CITES]->(law:LawDocument {id: $lawId})
                       RETURN doc, labels(doc)[0] as docType, r.citation_type as citation_type, r.article as article
                       ORDER BY doc.title';

            $result = $this->graph->run($cypher, ['lawId' => $lawId]);

            return $result->map(fn ($r) => [
                'document' => $r->get('doc')->getProperties(),
                'document_type' => $r->get('docType'),
                'citation_type' => $r->get('citation_type'),
                'article' => $r->get('article'),
            ])->toArray();
        } catch (\Exception $e) {
            Log::error('Failed to get citing documents', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Get most cited laws
     */
    public function getMostCitedLaws(int $limit = 20): array
    {
        try {
            $cypher = 'MATCH (doc)-[:CITES]->(law:LawDocument)
                       WITH law, count(doc) as citation_count
                       RETURN law, citation_count
                       ORDER BY citation_count DESC
                       LIMIT $limit';

            $result = $this->graph->run($cypher, ['limit' => $limit]);

            return $result->map(fn ($r) => [
                'law' => $r->get('law')->getProperties(),
                'citation_count' => $r->get('citation_count'),
            ])->toArray();
        } catch (\Exception $e) {
            Log::error('Failed to get most cited laws', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Find court decisions citing a specific law and article
     */
    public function findDecisionsCitingLawArticle(
        string $lawIdentifier,
        ?string $articleNumber = null,
        array $options = [],
        int $limit = 50
    ): array {
        $cacheKey = $this->generateCacheKey('decisions_citing_law', [
            'law' => $lawIdentifier,
            'article' => $articleNumber,
            'options' => $options,
            'limit' => $limit,
        ]);

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($lawIdentifier, $articleNumber, $options, $limit) {
            try {
                $cypher = 'MATCH (d:CourtDecisionDocument)-[c:CITES]->(l:LawDocument)
                           WHERE (l.law_number CONTAINS $lawId
                                  OR l.title CONTAINS $lawId
                                  OR l.doc_id CONTAINS $lawId)';

                $params = ['lawId' => $lawIdentifier];

                if ($articleNumber !== null) {
                    $cypher .= ' AND c.article = $article';
                    $params['article'] = $articleNumber;
                }

                if (! empty($options['court'])) {
                    $cypher .= ' AND d.court = $court';
                    $params['court'] = $options['court'];
                }

                $cypher .= ' RETURN d, l, c.article as article, c.paragraph as paragraph,
                                    c.item as item, c.law_abbreviation as law_abbreviation
                             ORDER BY d.decision_date DESC
                             LIMIT $limit';

                $params['limit'] = $limit;

                $result = $this->graph->run($cypher, $params);

                return $result->map(fn ($r) => [
                    'decision' => $r->get('d')->getProperties(),
                    'cited_law' => $r->get('l')->getProperties(),
                    'citation' => [
                        'article' => $r->get('article'),
                        'paragraph' => $r->get('paragraph'),
                        'item' => $r->get('item'),
                        'law_abbreviation' => $r->get('law_abbreviation'),
                    ],
                ])->toArray();
            } catch (\Exception $e) {
                Log::error('Failed to find decisions citing law article', [
                    'law' => $lawIdentifier,
                    'article' => $articleNumber,
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        });
    }

    /**
     * Clear all graph RAG caches
     */
    public function clearAllGraphCaches(): void
    {
        if (Cache::getStore() instanceof \Illuminate\Cache\RedisStore) {
            try {
                $redis = Cache::getStore()->getRedis();
                $keys = $redis->keys('graph_rag:*');

                if (! empty($keys)) {
                    $redis->del($keys);
                    Log::info('Cleared all graph RAG caches', ['count' => count($keys)]);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to clear graph RAG caches', ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Generate a cache key for graph queries
     */
    protected function generateCacheKey(string $operation, array $params): string
    {
        ksort($params);
        $paramHash = md5(json_encode($params));

        return "graph_rag:{$operation}:{$paramHash}";
    }
}
