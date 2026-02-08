<?php

namespace App\Services\Graph;

use App\Services\Graph\Extractors\VerdictExtractor;
use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\Log;

class VerdictGraphSyncService
{
    public function __construct(
        protected GraphDatabaseService $graph,
        protected VerdictExtractor $extractor
    ) {}

    /**
     * Sync verdict for a decision document
     *
     * @param string $documentId The document ID
     * @param string $content The decision text content
     * @param string $decisionId The decision ID
     * @return array Metrics about what was synced
     */
    public function sync(string $documentId, string $content, string $decisionId): array
    {
        $verdict = $this->extractor->extract($content, $decisionId);

        $metrics = [
            'verdict_created' => 0,
            'verdict_linked' => 0,
        ];

        if (!$verdict) {
            return $metrics;
        }

        try {
            // Create Verdict node
            $this->graph->upsertNode('Verdict', $verdict['id'], [
                'decision_id' => $verdict['decision_id'],
                'outcome_type' => $verdict['outcome_type'],
                'raw_text' => substr($verdict['raw_text'] ?? '', 0, 1000),
                'relief_granted' => $verdict['relief_granted'],
                'relief_denied' => $verdict['relief_denied'],
                'damages_amount' => $verdict['damages_amount'],
                'damages_currency' => $verdict['damages_currency'],
                'created_at' => now()->toIso8601String(),
            ]);
            $metrics['verdict_created'] = 1;

            // Create HAS_VERDICT relationship
            $this->graph->createRelationship(
                'CourtDecisionDocument',
                $documentId,
                'HAS_VERDICT',
                'Verdict',
                $verdict['id'],
                [
                    'created_at' => now()->toIso8601String(),
                ]
            );
            $metrics['verdict_linked'] = 1;

        } catch (\Throwable $e) {
            Log::warning('Failed to sync verdict', [
                'document_id' => $documentId,
                'decision_id' => $decisionId,
                'error' => $e->getMessage(),
            ]);
        }

        return $metrics;
    }
}
