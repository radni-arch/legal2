<?php

namespace App\Services\Graph;

use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Models\LegalCase;
use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Analysis Graph Sync Service
 *
 * Sprint 5 - Task 19: Neo4j Graph Sync for Analysis Results
 *
 * Syncs document analysis results to Neo4j graph database:
 * - Timeline events as TimelineEvent nodes with MENTIONS_DATE relationships
 * - Contradictions as KeyFact nodes with CONTRADICTS relationships
 * - Cross-references as REFERENCES relationships between CaseDocument nodes
 */
class AnalysisGraphSyncService
{
    public function __construct(
        protected GraphDatabaseService $graph
    ) {}

    /**
     * Sync timeline events from a dates_with_context analysis to Neo4j.
     *
     * Creates TimelineEvent nodes and MENTIONS_DATE relationships.
     *
     * @param DocumentAnalysis $analysis
     * @return array{success: bool, nodes_created: int, relationships_created: int, error?: string}
     */
    public function syncTimelineEvents(DocumentAnalysis $analysis): array
    {
        // Skip if not completed
        if ($analysis->status !== DocumentAnalysis::STATUS_COMPLETED) {
            return [
                'success' => true,
                'nodes_created' => 0,
                'relationships_created' => 0,
            ];
        }

        $results = $analysis->results ?? [];
        $dates = $results['dates'] ?? [];

        if (empty($dates)) {
            return [
                'success' => true,
                'nodes_created' => 0,
                'relationships_created' => 0,
            ];
        }

        $nodesCreated = 0;
        $relationshipsCreated = 0;

        foreach ($dates as $dateEntry) {
            $date = $dateEntry['date'] ?? '';
            $context = $dateEntry['context'] ?? '';

            if (empty($date)) {
                continue;
            }

            $eventId = $this->generateEventId($analysis->case_document_id, $date, $context);

            try {
                // Create TimelineEvent node
                $this->graph->upsertNode('TimelineEvent', $eventId, [
                    'date' => $date,
                    'context' => $context,
                    'source_document_id' => $analysis->case_document_id,
                    'analysis_id' => $analysis->id,
                    'created_at' => now()->toIso8601String(),
                ]);
                $nodesCreated++;

                // Create MENTIONS_DATE relationship
                $this->graph->createRelationship(
                    'CaseDocument',
                    $analysis->case_document_id,
                    'MENTIONS_DATE',
                    'TimelineEvent',
                    $eventId,
                    [
                        'extracted_at' => now()->toIso8601String(),
                    ]
                );
                $relationshipsCreated++;

            } catch (\Throwable $e) {
                Log::error('Failed to sync timeline event to Neo4j', [
                    'analysis_id' => $analysis->id,
                    'document_id' => $analysis->case_document_id,
                    'date' => $date,
                    'error' => $e->getMessage(),
                ]);

                return [
                    'success' => false,
                    'nodes_created' => $nodesCreated,
                    'relationships_created' => $relationshipsCreated,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'success' => true,
            'nodes_created' => $nodesCreated,
            'relationships_created' => $relationshipsCreated,
        ];
    }

    /**
     * Sync contradictions from analysis to Neo4j.
     *
     * Creates KeyFact nodes and CLAIMS + CONTRADICTS relationships.
     *
     * @param DocumentAnalysis $analysis
     * @return array{success: bool, nodes_created: int, relationships_created: int, error?: string}
     */
    public function syncContradictions(DocumentAnalysis $analysis): array
    {
        // Skip if not completed
        if ($analysis->status !== DocumentAnalysis::STATUS_COMPLETED) {
            return [
                'success' => true,
                'nodes_created' => 0,
                'relationships_created' => 0,
            ];
        }

        $results = $analysis->results ?? [];
        $contradictions = $results['contradictions'] ?? [];

        if (empty($contradictions)) {
            return [
                'success' => true,
                'nodes_created' => 0,
                'relationships_created' => 0,
            ];
        }

        $nodesCreated = 0;
        $relationshipsCreated = 0;

        foreach ($contradictions as $contradiction) {
            $claim1 = $contradiction['claim_1'] ?? [];
            $claim2 = $contradiction['claim_2'] ?? [];
            $severity = $contradiction['severity'] ?? 'medium';
            $explanation = $contradiction['explanation'] ?? '';

            if (empty($claim1) || empty($claim2)) {
                continue;
            }

            $factId1 = $this->generateFactId($claim1);
            $factId2 = $this->generateFactId($claim2);

            try {
                // Create KeyFact node for claim 1
                $this->graph->upsertNode('KeyFact', $factId1, [
                    'text' => $claim1['text'] ?? '',
                    'source_document_id' => $claim1['document_id'] ?? '',
                    'created_at' => now()->toIso8601String(),
                ]);
                $nodesCreated++;

                // Create KeyFact node for claim 2
                $this->graph->upsertNode('KeyFact', $factId2, [
                    'text' => $claim2['text'] ?? '',
                    'source_document_id' => $claim2['document_id'] ?? '',
                    'created_at' => now()->toIso8601String(),
                ]);
                $nodesCreated++;

                // Create CLAIMS relationship from document 1 to fact 1
                $this->graph->createRelationship(
                    'CaseDocument',
                    $claim1['document_id'] ?? '',
                    'CLAIMS',
                    'KeyFact',
                    $factId1,
                    ['extracted_at' => now()->toIso8601String()]
                );
                $relationshipsCreated++;

                // Create CLAIMS relationship from document 2 to fact 2
                $this->graph->createRelationship(
                    'CaseDocument',
                    $claim2['document_id'] ?? '',
                    'CLAIMS',
                    'KeyFact',
                    $factId2,
                    ['extracted_at' => now()->toIso8601String()]
                );
                $relationshipsCreated++;

                // Create CONTRADICTS relationship between facts
                $this->graph->createRelationship(
                    'KeyFact',
                    $factId1,
                    'CONTRADICTS',
                    'KeyFact',
                    $factId2,
                    [
                        'severity' => $severity,
                        'explanation' => $explanation,
                        'analysis_id' => $analysis->id,
                        'detected_at' => now()->toIso8601String(),
                    ]
                );
                $relationshipsCreated++;

            } catch (\Throwable $e) {
                Log::error('Failed to sync contradiction to Neo4j', [
                    'analysis_id' => $analysis->id,
                    'error' => $e->getMessage(),
                ]);

                return [
                    'success' => false,
                    'nodes_created' => $nodesCreated,
                    'relationships_created' => $relationshipsCreated,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'success' => true,
            'nodes_created' => $nodesCreated,
            'relationships_created' => $relationshipsCreated,
        ];
    }

    /**
     * Sync cross-references from analysis to Neo4j.
     *
     * Creates REFERENCES relationships between CaseDocument nodes.
     *
     * @param DocumentAnalysis $analysis
     * @return array{success: bool, relationships_created: int, error?: string}
     */
    public function syncCrossReferences(DocumentAnalysis $analysis): array
    {
        // Skip if not completed
        if ($analysis->status !== DocumentAnalysis::STATUS_COMPLETED) {
            return [
                'success' => true,
                'relationships_created' => 0,
            ];
        }

        $results = $analysis->results ?? [];
        $references = $results['references'] ?? [];

        if (empty($references)) {
            return [
                'success' => true,
                'relationships_created' => 0,
            ];
        }

        $relationshipsCreated = 0;

        foreach ($references as $reference) {
            $targetId = $reference['target_document_id'] ?? '';
            $refType = $reference['reference_type'] ?? 'mentions';
            $context = $reference['context'] ?? '';

            if (empty($targetId)) {
                continue;
            }

            try {
                $this->graph->createRelationship(
                    'CaseDocument',
                    $analysis->case_document_id,
                    'REFERENCES',
                    'CaseDocument',
                    $targetId,
                    [
                        'reference_type' => $refType,
                        'context' => $context,
                        'analysis_id' => $analysis->id,
                        'detected_at' => now()->toIso8601String(),
                    ]
                );
                $relationshipsCreated++;

            } catch (\Throwable $e) {
                Log::error('Failed to sync cross-reference to Neo4j', [
                    'analysis_id' => $analysis->id,
                    'source_document_id' => $analysis->case_document_id,
                    'target_document_id' => $targetId,
                    'error' => $e->getMessage(),
                ]);

                return [
                    'success' => false,
                    'relationships_created' => $relationshipsCreated,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'success' => true,
            'relationships_created' => $relationshipsCreated,
        ];
    }

    /**
     * Sync all completed analyses for a document to Neo4j.
     *
     * @param CaseDocument $document
     * @return array{success: bool, nodes_created: int, relationships_created: int, errors: array}
     */
    public function syncDocumentAnalyses(CaseDocument $document): array
    {
        $nodesCreated = 0;
        $relationshipsCreated = 0;
        $errors = [];

        $analyses = $document->analyses()
            ->where('status', DocumentAnalysis::STATUS_COMPLETED)
            ->get();

        foreach ($analyses as $analysis) {
            $result = $this->syncAnalysis($analysis);

            $nodesCreated += $result['nodes_created'] ?? 0;
            $relationshipsCreated += $result['relationships_created'] ?? 0;

            if (!$result['success'] && isset($result['error'])) {
                $errors[] = $result['error'];
            }
        }

        return [
            'success' => empty($errors),
            'nodes_created' => $nodesCreated,
            'relationships_created' => $relationshipsCreated,
            'errors' => $errors,
        ];
    }

    /**
     * Sync all completed analyses for all documents in a case to Neo4j.
     *
     * @param LegalCase $case
     * @return array{success: bool, documents_synced: int, nodes_created: int, relationships_created: int, errors: array}
     */
    public function syncCaseAnalyses(LegalCase $case): array
    {
        $documentsSynced = 0;
        $nodesCreated = 0;
        $relationshipsCreated = 0;
        $errors = [];

        foreach ($case->documents as $document) {
            $result = $this->syncDocumentAnalyses($document);

            if ($result['nodes_created'] > 0 || $result['relationships_created'] > 0) {
                $documentsSynced++;
            }

            $nodesCreated += $result['nodes_created'];
            $relationshipsCreated += $result['relationships_created'];

            if (!empty($result['errors'])) {
                $errors = array_merge($errors, $result['errors']);
            }
        }

        return [
            'success' => empty($errors),
            'documents_synced' => $documentsSynced,
            'nodes_created' => $nodesCreated,
            'relationships_created' => $relationshipsCreated,
            'errors' => $errors,
        ];
    }

    /**
     * Sync a single analysis to Neo4j based on its type.
     *
     * @param DocumentAnalysis $analysis
     * @return array
     */
    protected function syncAnalysis(DocumentAnalysis $analysis): array
    {
        return match ($analysis->analysis_type) {
            DocumentAnalysis::TYPE_DATES_WITH_CONTEXT => $this->syncTimelineEvents($analysis),
            DocumentAnalysis::TYPE_CONTRADICTIONS => $this->syncContradictions($analysis),
            DocumentAnalysis::TYPE_CASE_REFERENCES => $this->syncCrossReferences($analysis),
            default => [
                'success' => true,
                'nodes_created' => 0,
                'relationships_created' => 0,
            ],
        };
    }

    /**
     * Generate a unique ID for a timeline event.
     */
    protected function generateEventId(string $documentId, string $date, string $context): string
    {
        $hash = md5($documentId . $date . $context);
        return 'evt_' . substr($hash, 0, 16);
    }

    /**
     * Generate a unique ID for a key fact.
     */
    protected function generateFactId(array $claim): string
    {
        $documentId = $claim['document_id'] ?? '';
        $text = $claim['text'] ?? '';
        $hash = md5($documentId . $text);
        return 'fact_' . substr($hash, 0, 16);
    }
}
