<?php

namespace App\Services\Graph;

use App\Services\Graph\Extractors\LegalDefinitionExtractor;
use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\Log;

class LegalDefinitionGraphSyncService
{
    public function __construct(
        protected GraphDatabaseService $graph,
        protected LegalDefinitionExtractor $extractor
    ) {}

    /**
     * Sync definitions from a law document
     */
    public function syncFromLaw(string $lawId, string $content, ?string $articleNumber = null): array
    {
        $definitions = $this->extractor->extract($content, $lawId, $articleNumber);

        $metrics = [
            'definitions_created' => 0,
            'defines_relationships' => 0,
        ];

        foreach ($definitions as $definition) {
            try {
                $this->graph->upsertNode('LegalDefinition', $definition['id'], [
                    'term' => $definition['term'],
                    'definition' => $definition['definition'],
                    'source_article' => $definition['source_article'],
                    'law_id' => $definition['law_id'],
                    'scope' => $definition['scope'],
                    'created_at' => now()->toIso8601String(),
                ]);
                $metrics['definitions_created']++;

                $this->graph->createRelationship(
                    'LawDocument',
                    $lawId,
                    'DEFINES',
                    'LegalDefinition',
                    $definition['id'],
                    [
                        'article' => $definition['source_article'],
                        'created_at' => now()->toIso8601String(),
                    ]
                );
                $metrics['defines_relationships']++;

            } catch (\Throwable $e) {
                Log::warning('Failed to sync legal definition', [
                    'law_id' => $lawId,
                    'term' => $definition['term'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $metrics;
    }

    /**
     * Link a court decision to definitions it applies
     */
    public function linkDecisionToDefinitions(string $documentId, string $content): array
    {
        // Find definition terms mentioned in the decision
        $definitions = $this->extractor->extract($content);

        $metrics = ['definitions_linked' => 0];

        foreach ($definitions as $definition) {
            try {
                // Try to find existing definition node
                $result = $this->graph->run(
                    'MATCH (d:LegalDefinition) WHERE toLower(d.term) = toLower($term) RETURN d.id as id LIMIT 1',
                    ['term' => $definition['term']]
                );

                if (!empty($result) && isset($result[0]['id'])) {
                    $this->graph->createRelationship(
                        'CourtDecisionDocument',
                        $documentId,
                        'APPLIES_DEFINITION',
                        'LegalDefinition',
                        $result[0]['id'],
                        [
                            'interpretation' => null,
                            'created_at' => now()->toIso8601String(),
                        ]
                    );
                    $metrics['definitions_linked']++;
                }
            } catch (\Throwable $e) {
                // Silently continue
            }
        }

        return $metrics;
    }
}
