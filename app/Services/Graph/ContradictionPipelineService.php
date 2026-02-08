<?php

namespace App\Services\Graph;

use App\Models\CourtDecision;
use App\Services\CourtDecisionVectorStoreService;
use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\Log;

/**
 * Contradiction Detection Pipeline (Sprint 4.6)
 *
 * Automatically detects contradictions between court decisions during ingestion.
 *
 * Pipeline Flow:
 * 1. New decision is ingested into vector store
 * 2. Find similar decisions using vector search
 * 3. Use LLM to compare for contradictions
 * 4. Create CONTRADICTS relationships for high-confidence matches (>0.75)
 * 5. Generate alerts for conflicting precedents
 *
 * Usage:
 *   $pipeline = app(ContradictionPipelineService::class);
 *   $result = $pipeline->checkNewDecision($decisionId);
 */
class ContradictionPipelineService
{
    /**
     * Minimum confidence threshold for creating CONTRADICTS relationship
     */
    protected const CONFIDENCE_THRESHOLD = 0.75;

    /**
     * Number of similar decisions to check for contradictions
     */
    protected const SIMILARITY_CHECK_LIMIT = 10;

    public function __construct(
        protected ContradictionDetectionService $contradictionService,
        protected CourtDecisionVectorStoreService $vectorStore,
        protected GraphDatabaseService $graphDb
    ) {}

    /**
     * Check newly ingested decision for contradictions with existing decisions
     *
     * @param  string  $decisionId  Decision ID to check
     * @param  array  $options  Pipeline options
     * @return array Pipeline result with contradiction details
     */
    public function checkNewDecision(string $decisionId, array $options = []): array
    {
        $enabled = (bool) ($options['enabled'] ?? config('graph.contradiction_detection.enabled', true));

        if (! $enabled) {
            return [
                'enabled' => false,
                'decision_id' => $decisionId,
                'contradictions_found' => 0,
                'relationships_created' => 0,
            ];
        }

        Log::info('ContradictionPipelineService - Starting check for new decision', [
            'decision_id' => $decisionId,
        ]);

        try {
            // Get decision summary for comparison
            $decision = CourtDecision::find($decisionId);
            if (! $decision) {
                Log::warning('ContradictionPipelineService - Decision not found', [
                    'decision_id' => $decisionId,
                ]);

                return [
                    'enabled' => true,
                    'decision_id' => $decisionId,
                    'error' => 'Decision not found',
                    'contradictions_found' => 0,
                    'relationships_created' => 0,
                ];
            }

            // Build searchable text (summary + case number + court)
            $decisionText = $this->buildDecisionText($decision);

            // Find similar decisions using vector search
            $similarLimit = (int) ($options['similarity_limit'] ?? self::SIMILARITY_CHECK_LIMIT);
            $similarDecisions = $this->findSimilarDecisions($decisionId, $decisionText, $similarLimit);

            if (empty($similarDecisions)) {
                Log::info('ContradictionPipelineService - No similar decisions found', [
                    'decision_id' => $decisionId,
                ]);

                return [
                    'enabled' => true,
                    'decision_id' => $decisionId,
                    'similar_decisions_checked' => 0,
                    'contradictions_found' => 0,
                    'relationships_created' => 0,
                ];
            }

            // Check each similar decision for contradictions
            $contradictionsFound = 0;
            $relationshipsCreated = 0;
            $contradictionDetails = [];

            foreach ($similarDecisions as $similarId => $similarText) {
                try {
                    $result = $this->contradictionService->detectContradiction(
                        $decisionText,
                        $similarText,
                        $this->buildMetadata($decision, $similarId)
                    );

                    if ($result['is_contradiction'] && $result['confidence'] >= self::CONFIDENCE_THRESHOLD) {
                        $contradictionsFound++;

                        // Create CONTRADICTS relationship in graph
                        if ($this->graphDb->isAvailable()) {
                            $this->graphDb->createRelationship(
                                fromLabel: 'Decision',
                                fromId: $decisionId,
                                relType: 'CONTRADICTS',
                                toLabel: 'Decision',
                                toId: $similarId,
                                properties: [
                                    'confidence' => $result['confidence'],
                                    'contradiction_type' => $result['contradiction_type'],
                                    'severity' => $result['severity'],
                                    'explanation' => $result['explanation'],
                                    'detected_at' => now()->toIso8601String(),
                                ]
                            );
                            $relationshipsCreated++;

                            Log::warning('ContradictionPipelineService - Contradiction detected', [
                                'decision_id' => $decisionId,
                                'contradicts_with' => $similarId,
                                'confidence' => $result['confidence'],
                                'type' => $result['contradiction_type'],
                                'severity' => $result['severity'],
                            ]);
                        }

                        $contradictionDetails[] = [
                            'contradicts_with' => $similarId,
                            'confidence' => $result['confidence'],
                            'type' => $result['contradiction_type'],
                            'severity' => $result['severity'],
                            'explanation' => $result['explanation'],
                        ];
                    }
                } catch (\Exception $e) {
                    Log::error('ContradictionPipelineService - Error checking decision pair', [
                        'decision_id' => $decisionId,
                        'similar_id' => $similarId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $result = [
                'enabled' => true,
                'decision_id' => $decisionId,
                'similar_decisions_checked' => count($similarDecisions),
                'contradictions_found' => $contradictionsFound,
                'relationships_created' => $relationshipsCreated,
                'details' => $contradictionDetails,
            ];

            Log::info('ContradictionPipelineService - Check complete', $result);

            return $result;
        } catch (\Exception $e) {
            Log::error('ContradictionPipelineService - Pipeline failed', [
                'decision_id' => $decisionId,
                'error' => $e->getMessage(),
            ]);

            return [
                'enabled' => true,
                'decision_id' => $decisionId,
                'error' => $e->getMessage(),
                'contradictions_found' => 0,
                'relationships_created' => 0,
            ];
        }
    }

    /**
     * Build searchable text from decision for comparison
     */
    protected function buildDecisionText(CourtDecision $decision): string
    {
        $parts = [];

        if ($decision->case_number) {
            $parts[] = 'Case: '.$decision->case_number;
        }

        if ($decision->court) {
            $parts[] = 'Court: '.$decision->court;
        }

        if ($decision->decision_date) {
            $parts[] = 'Date: '.$decision->decision_date;
        }

        if ($decision->summary) {
            $parts[] = $decision->summary;
        } elseif ($decision->title) {
            $parts[] = $decision->title;
        }

        if ($decision->description) {
            $parts[] = $decision->description;
        }

        return implode("\n", $parts);
    }

    /**
     * Find similar decisions using vector store or fallback to recent decisions
     *
     * @return array Associative array [decision_id => decision_text]
     */
    protected function findSimilarDecisions(
        string $excludeId,
        string $queryText,
        int $limit
    ): array {
        try {
            $similarDecisions = [];

            // Try to use vector store's findSimilar if the decision has embeddings
            try {
                $similarResults = $this->vectorStore->findSimilar(
                    $excludeId,
                    threshold: 0.7,
                    limit: $limit
                );

                foreach ($similarResults as $result) {
                    $decisionId = $result['id'] ?? null;
                    if (! $decisionId || $decisionId === $excludeId) {
                        continue;
                    }

                    $decision = CourtDecision::find($decisionId);
                    if (! $decision) {
                        continue;
                    }

                    $similarDecisions[$decisionId] = $this->buildDecisionText($decision);
                }
            } catch (\Exception $vectorError) {
                // Fallback: Get recent decisions from same court/jurisdiction
                Log::debug('ContradictionPipelineService - Vector search failed, using fallback', [
                    'error' => $vectorError->getMessage(),
                ]);

                $sourceDecision = CourtDecision::find($excludeId);
                if (! $sourceDecision) {
                    return [];
                }

                $recentDecisions = CourtDecision::query()
                    ->where('id', '!=', $excludeId)
                    ->when($sourceDecision->court, fn ($q, $court) => $q->where('court', $court))
                    ->when($sourceDecision->jurisdiction, fn ($q, $j) => $q->where('jurisdiction', $j))
                    ->orderBy('decision_date', 'desc')
                    ->limit($limit)
                    ->get();

                foreach ($recentDecisions as $decision) {
                    $similarDecisions[$decision->id] = $this->buildDecisionText($decision);
                }
            }

            Log::info('ContradictionPipelineService - Found similar decisions', [
                'exclude_id' => $excludeId,
                'similar_count' => count($similarDecisions),
            ]);

            return $similarDecisions;
        } catch (\Exception $e) {
            Log::error('ContradictionPipelineService - Failed to find similar decisions', [
                'exclude_id' => $excludeId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Build metadata for LLM contradiction detection
     */
    protected function buildMetadata(CourtDecision $decision1, string $decision2Id): array
    {
        $decision2 = CourtDecision::find($decision2Id);

        $metadata = [];

        if ($decision1->decision_date && $decision2 && $decision2->decision_date) {
            $metadata['older_decision_date'] = min($decision1->decision_date, $decision2->decision_date);
            $metadata['newer_decision_date'] = max($decision1->decision_date, $decision2->decision_date);
        }

        if ($decision1->jurisdiction && $decision2 && $decision2->jurisdiction) {
            $metadata['jurisdiction_1'] = $decision1->jurisdiction;
            $metadata['jurisdiction_2'] = $decision2->jurisdiction;
        }

        if ($decision1->court && $decision2 && $decision2->court) {
            $metadata['court_level_1'] = $decision1->court;
            $metadata['court_level_2'] = $decision2->court;
        }

        return $metadata;
    }

    /**
     * Get contradiction statistics
     */
    public function getStatistics(): array
    {
        if (! $this->graphDb->isAvailable()) {
            return [
                'graph_available' => false,
                'total_contradictions' => 0,
            ];
        }

        try {
            $result = $this->graphDb->run(
                'MATCH ()-[r:CONTRADICTS]->() RETURN count(r) as total'
            );

            $total = $result->first()['total'] ?? 0;

            return [
                'graph_available' => true,
                'total_contradictions' => $total,
            ];
        } catch (\Exception $e) {
            Log::error('ContradictionPipelineService - Failed to get statistics', [
                'error' => $e->getMessage(),
            ]);

            return [
                'graph_available' => true,
                'total_contradictions' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }
}
