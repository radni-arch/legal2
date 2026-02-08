<?php

namespace App\Services\Graph;

use App\Services\Graph\Extractors\LegalConceptExtractor;
use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\Log;

class LegalConceptGraphSyncService
{
    public function __construct(
        protected GraphDatabaseService $graph,
        protected LegalConceptExtractor $extractor
    ) {}

    /**
     * Sync legal concepts for a document
     */
    public function sync(string $documentId, string $content): array
    {
        $concepts = $this->extractor->extract($content);

        $metrics = [
            'concepts_linked' => 0,
            'concepts_created' => 0,
        ];

        foreach ($concepts as $concept) {
            try {
                // Upsert concept node
                $this->graph->upsertNode('LegalConcept', $concept['concept_id'], [
                    'name' => $concept['name'],
                    'definition' => $concept['definition'],
                    'category' => $concept['category'],
                    'created_at' => now()->toIso8601String(),
                ]);
                $metrics['concepts_created']++;

                // Create MENTIONS relationship
                $this->graph->createRelationship(
                    'CourtDecisionDocument',
                    $documentId,
                    'MENTIONS',
                    'LegalConcept',
                    $concept['concept_id'],
                    [
                        'frequency' => $concept['frequency'],
                        'created_at' => now()->toIso8601String(),
                    ]
                );
                $metrics['concepts_linked']++;

            } catch (\Throwable $e) {
                Log::warning('Failed to sync legal concept', [
                    'document_id' => $documentId,
                    'concept' => $concept['name'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $metrics;
    }

    /**
     * Seed all concepts to Neo4j
     */
    public function seedConcepts(): int
    {
        $concepts = $this->extractor->getAllConcepts();
        $count = 0;

        foreach ($concepts as $conceptKey => $data) {
            $name = $this->mbUcfirst(str_replace('_', ' ', $conceptKey));
            $this->graph->upsertNode('LegalConcept', 'concept_' . md5($name), [
                'name' => $name,
                'definition' => $data['definition'],
                'category' => $data['category'],
                'created_at' => now()->toIso8601String(),
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * Multibyte-safe ucfirst for Croatian characters
     */
    protected function mbUcfirst(string $string): string
    {
        $firstChar = mb_substr($string, 0, 1);
        $rest = mb_substr($string, 1);
        return mb_strtoupper($firstChar) . $rest;
    }
}
