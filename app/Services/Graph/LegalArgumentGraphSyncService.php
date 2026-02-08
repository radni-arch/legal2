<?php

namespace App\Services\Graph;

use App\Services\Graph\Extractors\LegalArgumentExtractor;
use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\Log;

class LegalArgumentGraphSyncService
{
    public function __construct(
        protected GraphDatabaseService $graph,
        protected LegalArgumentExtractor $extractor
    ) {}

    /**
     * Sync legal arguments from decision content to Neo4j graph
     */
    public function sync(string $documentId, string $content, string $decisionId): array
    {
        $arguments = $this->extractor->extract($content, $decisionId);

        $metrics = [
            'arguments_created' => 0,
            'arguments_linked' => 0,
        ];

        foreach ($arguments as $argument) {
            try {
                $this->graph->upsertNode('LegalArgument', $argument['id'], [
                    'decision_id' => $argument['decision_id'],
                    'argument_type' => $argument['argument_type'],
                    'position' => $argument['position'],
                    'summary' => $argument['summary'],
                    'full_text' => $argument['full_text'],
                    'accepted' => $argument['accepted'],
                    'created_at' => now()->toIso8601String(),
                ]);
                $metrics['arguments_created']++;

                $this->graph->createRelationship(
                    'CourtDecisionDocument',
                    $documentId,
                    'CONTAINS_ARGUMENT',
                    'LegalArgument',
                    $argument['id'],
                    [
                        'sequence' => $argument['sequence'],
                        'created_at' => now()->toIso8601String(),
                    ]
                );
                $metrics['arguments_linked']++;

            } catch (\Throwable $e) {
                Log::warning('Failed to sync legal argument', [
                    'document_id' => $documentId,
                    'argument_id' => $argument['id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $metrics;
    }
}
