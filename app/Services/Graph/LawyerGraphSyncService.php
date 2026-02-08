<?php

namespace App\Services\Graph;

use App\Services\Graph\Extractors\LawyerExtractor;
use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\Log;

class LawyerGraphSyncService
{
    public function __construct(
        protected GraphDatabaseService $graph,
        protected LawyerExtractor $extractor
    ) {}

    /**
     * Sync lawyers for a decision document
     */
    public function sync(string $documentId, string $content, ?string $caseId = null): array
    {
        $lawyers = $this->extractor->extract($content, $caseId);

        $metrics = [
            'lawyers_created' => 0,
            'representations_linked' => 0,
        ];

        foreach ($lawyers as $lawyer) {
            try {
                // Upsert Lawyer node
                $this->graph->upsertNode('Lawyer', $lawyer['id'], [
                    'name' => $lawyer['name'],
                    'normalized_name' => $lawyer['normalized_name'],
                    'firm' => $lawyer['firm'],
                    'bar_number' => $lawyer['bar_number'],
                    'created_at' => now()->toIso8601String(),
                ]);
                $metrics['lawyers_created']++;

                // If we can identify the party, create REPRESENTED_BY relationship
                if ($lawyer['role']) {
                    $partyId = $this->findPartyId($documentId, $lawyer['role']);
                    if ($partyId) {
                        $this->graph->createRelationship(
                            'Party',
                            $partyId,
                            'REPRESENTED_BY',
                            'Lawyer',
                            $lawyer['id'],
                            [
                                'case_id' => $lawyer['case_id'],
                                'created_at' => now()->toIso8601String(),
                            ]
                        );
                        $metrics['representations_linked']++;
                    }
                }

            } catch (\Throwable $e) {
                Log::warning('Failed to sync lawyer', [
                    'document_id' => $documentId,
                    'lawyer' => $lawyer['name'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $metrics;
    }

    /**
     * Find party ID by role for a document
     */
    protected function findPartyId(string $documentId, string $role): ?string
    {
        try {
            $result = $this->graph->run(
                'MATCH (d:CourtDecisionDocument {id: $docId})-[:HAS_PARTY]->(p:Party {role: $role})
                 RETURN p.id as id LIMIT 1',
                ['docId' => $documentId, 'role' => $role]
            );

            return $result[0]['id'] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
