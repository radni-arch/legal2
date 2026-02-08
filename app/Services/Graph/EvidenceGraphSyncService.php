<?php

namespace App\Services\Graph;

use App\Services\Graph\Extractors\EvidenceExtractor;
use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\Log;

class EvidenceGraphSyncService
{
    public function __construct(
        protected GraphDatabaseService $graph,
        protected EvidenceExtractor $extractor
    ) {}

    public function sync(string $documentId, string $content, ?string $caseId = null): array
    {
        $evidenceList = $this->extractor->extract($content, $caseId);

        $metrics = [
            'evidence_created' => 0,
            'evidence_linked' => 0,
        ];

        foreach ($evidenceList as $evidence) {
            try {
                $this->graph->upsertNode('Evidence', $evidence['id'], [
                    'case_id' => $evidence['case_id'],
                    'evidence_type' => $evidence['evidence_type'],
                    'description' => $evidence['description'],
                    'admitted' => $evidence['admitted'],
                    'weight' => $evidence['weight'],
                    'created_at' => now()->toIso8601String(),
                ]);
                $metrics['evidence_created']++;

                $this->graph->createRelationship(
                    'CourtDecisionDocument',
                    $documentId,
                    'CONSIDERS_EVIDENCE',
                    'Evidence',
                    $evidence['id'],
                    [
                        'ruling' => $evidence['ruling'],
                        'created_at' => now()->toIso8601String(),
                    ]
                );
                $metrics['evidence_linked']++;

            } catch (\Throwable $e) {
                Log::warning('Failed to sync evidence', [
                    'document_id' => $documentId,
                    'evidence_id' => $evidence['id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $metrics;
    }
}
