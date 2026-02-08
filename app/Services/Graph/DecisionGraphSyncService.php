<?php

namespace App\Services\Graph;

use App\Services\GraphDatabaseService;
use App\Services\TaggingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for syncing court decision documents to the graph database
 *
 * Extracted from GraphRagService to provide focused, single-responsibility
 * service for court decision document synchronization.
 *
 * Responsibilities:
 * - Create/update court decision document nodes in Neo4j
 * - Create court nodes and DECIDED_BY relationships
 * - Create jurisdiction nodes and BELONGS_TO_JURISDICTION relationships
 * - Delegate keyword extraction to GraphKeywordLinker
 * - Delegate citation extraction to GraphCitationLinker
 * - Delegate similarity calculation to GraphSimilarityLinker
 * - Delegate judge extraction to JudgeGraphSyncService
 * - Delegate party extraction to PartyGraphSyncService
 * - Delegate legal principle extraction to LegalPrincipleGraphSyncService
 * - Delegate precedent detection to PrecedentDetector
 * - Auto-tag decisions with metadata
 * - Track sync metrics for observability
 */
class DecisionGraphSyncService implements GraphSyncServiceInterface
{
    /**
     * Metrics tracked during sync operation
     *
     * @var array{nodes: array<string, int>, relationships: array<string, int>, errors: array<string, int>, duration_ms: int}
     */
    protected array $metrics = [
        'nodes' => [],
        'relationships' => [],
        'errors' => [],
        'duration_ms' => 0,
    ];

    public function __construct(
        protected GraphDatabaseService $graph,
        protected GraphKeywordLinker $keywordLinker,
        protected GraphCitationLinker $citationLinker,
        protected GraphSimilarityLinker $similarityLinker,
        protected TaggingService $tagging,
        protected JudgeGraphSyncService $judgeSync,
        protected PartyGraphSyncService $partySync,
        protected LegalPrincipleGraphSyncService $principleSync,
        protected PrecedentDetector $precedentDetector
    ) {}

    /**
     * Sync a court decision to the graph database
     *
     * @param  string  $decisionId  The court decision ID
     * @return array{nodes: array<string, int>, relationships: array<string, int>, errors: array<string, int>, duration_ms: int}
     */
    public function sync(string $decisionId): array
    {
        // Reset metrics for this sync operation
        $this->metrics = [
            'nodes' => [],
            'relationships' => [],
            'errors' => [],
            'duration_ms' => 0,
        ];

        // Track duration
        $startTime = microtime(true);

        // Get the CourtDecision parent record
        $decision = DB::table('court_decisions')->where('id', $decisionId)->first();

        if (! $decision) {
            $this->metrics['duration_ms'] = (int) ((microtime(true) - $startTime) * 1000);

            return $this->metrics;
        }

        // Get all document chunks for this decision
        $documents = DB::table('court_decision_documents')
            ->where('decision_id', $decisionId)
            ->get();

        // Process each document chunk
        foreach ($documents as $doc) {
            $this->syncDecisionDocument($decision, $doc);
        }

        // Calculate final duration
        $this->metrics['duration_ms'] = (int) ((microtime(true) - $startTime) * 1000);

        // Log metrics summary
        Log::info('Sync completed', [
            'decision_id' => $decisionId,
            'nodes' => $this->metrics['nodes'],
            'relationships' => $this->metrics['relationships'],
            'errors' => count($this->metrics['errors']),
            'duration_ms' => $this->metrics['duration_ms'],
        ]);

        return $this->metrics;
    }

    /**
     * Check if this service supports a given document type
     *
     * @param  string  $type  The document type
     */
    public function supportsType(string $type): bool
    {
        return $type === 'CourtDecisionDocument';
    }

    /**
     * Sync a single decision document chunk
     *
     * @param  object  $decision  The parent court decision record
     * @param  object  $doc  The document chunk record
     */
    protected function syncDecisionDocument($decision, $doc): void
    {
        // Create court decision document node
        $this->createDecisionDocumentNode($decision, $doc);

        // Create court node and relationship
        $this->createCourtRelationship($decision, $doc->id);

        // Create jurisdiction node and relationship
        $this->createJurisdictionRelationship($decision, $doc->id);

        // Extract and link keywords
        try {
            $this->keywordLinker->link('CourtDecisionDocument', $doc->id, $doc->content);
        } catch (\Throwable $e) {
            $this->trackError('keyword_linking', $e);
            Log::warning('Sync error in keyword_linking', ['error' => $e->getMessage()]);
        }

        // Extract and create citation relationships
        try {
            $this->citationLinker->link('CourtDecisionDocument', $doc->id, $doc->content);
        } catch (\Throwable $e) {
            $this->trackError('citation_linking', $e);
            Log::warning('Sync error in citation_linking', ['error' => $e->getMessage()]);
        }

        // Find and create similarity relationships
        try {
            $this->similarityLinker->link('CourtDecisionDocument', $doc->id, $doc->embedding ?? null);
        } catch (\Throwable $e) {
            $this->trackError('similarity_linking', $e);
            Log::warning('Sync error in similarity_linking', ['error' => $e->getMessage()]);
        }

        // Auto-tag the decision
        try {
            $metadata = json_decode($doc->metadata ?? '[]', true);
            // Ensure metadata is always an array (json_decode returns null for invalid JSON)
            if (! is_array($metadata)) {
                $metadata = [];
            }

            $this->tagging->autoTag('CourtDecisionDocument', $doc->id, $doc->content, array_merge($metadata, [
                'court' => $decision->court,
                'case_number' => $decision->case_number,
                'decision_type' => $decision->decision_type,
            ]));
        } catch (\Throwable $e) {
            $this->trackError('tagging', $e);
            Log::warning('Sync error in tagging', ['error' => $e->getMessage()]);
        }

        // Sync judge information if available and feature is enabled
        if (config('graph.features.sync_judges', true) && ! empty($decision->judge)) {
            try {
                $this->judgeSync->syncJudgeFromDecision(
                    $doc->id,
                    $decision->judge,
                    $decision->court ?? 'Unknown Court'
                );
            } catch (\Throwable $e) {
                $this->trackError('judge_sync', $e);
                Log::warning('Sync error in judge_sync', ['error' => $e->getMessage()]);
            }
        }

        // Sync party information if available and feature is enabled
        if (config('graph.features.sync_parties', true)) {
            if (! empty($decision->plaintiff)) {
                try {
                    $this->partySync->syncParty(
                        $doc->id,
                        $decision->plaintiff,
                        'plaintiff'
                    );
                } catch (\Throwable $e) {
                    $this->trackError('party_sync_plaintiff', $e);
                    Log::warning('Sync error in party_sync_plaintiff', ['error' => $e->getMessage()]);
                }
            }

            if (! empty($decision->defendant)) {
                try {
                    $this->partySync->syncParty(
                        $doc->id,
                        $decision->defendant,
                        'defendant'
                    );
                } catch (\Throwable $e) {
                    $this->trackError('party_sync_defendant', $e);
                    Log::warning('Sync error in party_sync_defendant', ['error' => $e->getMessage()]);
                }
            }
        }

        // Sync legal principles if feature is enabled
        if (config('graph.features.sync_legal_principles', true)) {
            try {
                $this->principleSync->syncPrinciplesFromDecision(
                    $doc->id,
                    $decision->raw_text ?? '',
                    $decision->is_landmark ?? false
                );
            } catch (\Throwable $e) {
                $this->trackError('principle_sync', $e);
                Log::warning('Sync error in principle_sync', ['error' => $e->getMessage()]);
            }
        }

        // Detect and link precedents if feature is enabled and content exists
        if (config('graph.features.detect_precedents', true) && ! empty($doc->content)) {
            try {
                $detected = $this->precedentDetector->detect($doc->id, $doc->content);
                foreach ($detected as $precedent) {
                    // Resolve target case number to actual decision document ID in graph
                    $targetDecisionId = $this->findDecisionByCaseNumber($precedent['target_case_number']);

                    if ($targetDecisionId === null) {
                        // Target decision not in graph yet - skip this relationship
                        Log::debug('Precedent target not found in graph', [
                            'source_id' => $precedent['source_id'],
                            'target_case_number' => $precedent['target_case_number'],
                            'relationship_type' => $precedent['relationship_type'],
                        ]);
                        continue;
                    }

                    // Create relationship with correct parameter order:
                    // createRelationship(fromLabel, fromId, relType, toLabel, toId, properties)
                    $this->graph->createRelationship(
                        'CourtDecisionDocument',
                        $precedent['source_id'],
                        $precedent['relationship_type'],  // e.g., OVERRULES, CONFIRMS, MODIFIES
                        'CourtDecisionDocument',
                        $targetDecisionId,
                        [
                            'context' => $precedent['context'],
                            'confidence' => $precedent['confidence'],
                            'target_case_number' => $precedent['target_case_number'],
                        ]
                    );

                    $this->trackRelationship($precedent['relationship_type']);
                }
            } catch (\Throwable $e) {
                $this->trackError('precedent_detection', $e);
                Log::warning('Sync error in precedent_detection', ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Create the court decision document node in the graph
     *
     * @param  object  $decision  The parent court decision record
     * @param  object  $doc  The document chunk record
     */
    protected function createDecisionDocumentNode($decision, $doc): void
    {
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
            // Phase 4: Outcome properties
            'outcome' => $decision->outcome ?? null,
            'holding' => $decision->holding ?? null,
            'precedential_value' => $decision->precedential_value ?? null,
            // Phase 4: Dissent/Concurrence counts
            'dissent_count' => $decision->dissent_count ?? 0,
            'concurrence_count' => $decision->concurrence_count ?? 0,
        ]);

        $this->trackNode('CourtDecisionDocument');
    }

    /**
     * Create court node and relationship if court exists
     *
     * @param  object  $decision  The court decision record
     * @param  string  $docId  The document ID
     */
    protected function createCourtRelationship($decision, string $docId): void
    {
        if (! $decision->court) {
            return;
        }

        $courtId = 'court_'.md5($decision->court);

        $this->graph->upsertNode('Court', $courtId, [
            'name' => $decision->court,
            'jurisdiction' => $decision->jurisdiction,
        ]);

        $this->trackNode('Court');

        $this->graph->createRelationship(
            'CourtDecisionDocument',
            $docId,
            'DECIDED_BY',
            'Court',
            $courtId
        );

        $this->trackRelationship('DECIDED_BY');
    }

    /**
     * Create jurisdiction node and relationship if jurisdiction exists
     *
     * Uses MD5 hash for jurisdiction ID to ensure deterministic deduplication.
     * This prevents duplicate jurisdiction nodes when the same jurisdiction
     * appears with different casing or formatting variations.
     *
     * @param  object  $decision  The court decision record
     * @param  string  $docId  The document ID
     */
    protected function createJurisdictionRelationship($decision, string $docId): void
    {
        if (! $decision->jurisdiction) {
            return;
        }

        $jurisdictionId = 'jurisdiction_'.md5($decision->jurisdiction);

        $this->graph->upsertNode('Jurisdiction', $jurisdictionId, [
            'name' => $decision->jurisdiction,
        ]);

        $this->trackNode('Jurisdiction');

        $this->graph->createRelationship(
            'CourtDecisionDocument',
            $docId,
            'BELONGS_TO_JURISDICTION',
            'Jurisdiction',
            $jurisdictionId
        );

        $this->trackRelationship('BELONGS_TO_JURISDICTION');
    }

    /**
     * Track a node creation in metrics
     *
     * @param  string  $type  The node type
     */
    protected function trackNode(string $type): void
    {
        $this->metrics['nodes'][$type] = ($this->metrics['nodes'][$type] ?? 0) + 1;
    }

    /**
     * Track a relationship creation in metrics
     *
     * @param  string  $type  The relationship type
     */
    protected function trackRelationship(string $type): void
    {
        $this->metrics['relationships'][$type] = ($this->metrics['relationships'][$type] ?? 0) + 1;
    }

    /**
     * Track an error in metrics
     *
     * @param  string  $context  The context where the error occurred
     * @param  \Throwable  $exception  The exception that occurred
     */
    protected function trackError(string $context, \Throwable $exception): void
    {
        $this->metrics['errors'][] = [
            'context' => $context,
            'message' => $exception->getMessage(),
            'exception' => class_basename($exception),
        ];
    }

    /**
     * Find a decision document ID by case number in the graph
     *
     * Queries Neo4j for a CourtDecisionDocument node with the given case_number
     * property and returns its ID if found.
     *
     * @param  string  $caseNumber  The case number to search for (e.g., "Rev 123/2019")
     * @return string|null The document ID if found, null otherwise
     */
    protected function findDecisionByCaseNumber(string $caseNumber): ?string
    {
        try {
            $result = $this->graph->run(
                'MATCH (d:CourtDecisionDocument {case_number: $caseNumber}) RETURN d.id as id LIMIT 1',
                ['caseNumber' => $caseNumber]
            );

            if ($result && count($result) > 0) {
                return $result[0]['id'] ?? null;
            }

            return null;
        } catch (\Throwable $e) {
            Log::debug('Could not find decision by case number', [
                'case_number' => $caseNumber,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
