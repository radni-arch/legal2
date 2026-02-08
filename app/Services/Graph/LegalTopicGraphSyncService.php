<?php

namespace App\Services\Graph;

use App\Services\Graph\Extractors\LegalTopicExtractor;
use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\Log;

/**
 * Service for syncing extracted legal topics to Neo4j graph
 *
 * Task G.1 - Legal Topic Extraction and Classification
 *
 * Responsibilities:
 * - Extract legal topics from court decision text
 * - Create LegalTopic nodes in Neo4j
 * - Create RELATES_TO relationships with relevance scores
 * - Seed Croatian legal topic taxonomy
 */
class LegalTopicGraphSyncService
{
    public function __construct(
        protected GraphDatabaseService $graph,
        protected LegalTopicExtractor $extractor
    ) {}

    /**
     * Sync legal topics for a decision document
     *
     * @param string $documentId The decision document ID
     * @param string $content The document content to analyze
     * @return array Metrics ['topics_linked' => int, 'topics_created' => int]
     */
    public function sync(string $documentId, string $content): array
    {
        $topics = $this->extractor->extract($content);

        $metrics = [
            'topics_linked' => 0,
            'topics_created' => 0,
        ];

        foreach ($topics as $topic) {
            try {
                // Upsert the topic node
                $this->graph->upsertNode('LegalTopic', $topic['topic_id'], [
                    'name' => $topic['name'],
                    'parent_id' => $topic['parent_id'] ?? null,
                    'created_at' => now()->toIso8601String(),
                ]);
                $metrics['topics_created']++;

                // Create relationship
                $this->graph->createRelationship(
                    'CourtDecisionDocument',
                    $documentId,
                    'RELATES_TO',
                    'LegalTopic',
                    $topic['topic_id'],
                    [
                        'relevance' => $topic['relevance'],
                        'created_at' => now()->toIso8601String(),
                    ]
                );
                $metrics['topics_linked']++;

                Log::debug('Synced legal topic to graph', [
                    'topic' => $topic['name'],
                    'topic_id' => $topic['topic_id'],
                    'document_id' => $documentId,
                    'relevance' => $topic['relevance'],
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed to sync legal topic', [
                    'document_id' => $documentId,
                    'topic' => $topic['name'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $metrics;
    }

    /**
     * Seed the topic taxonomy into Neo4j
     *
     * @return int Number of topics seeded
     */
    public function seedTaxonomy(): int
    {
        $taxonomy = $this->extractor->getTaxonomy();
        $count = 0;

        foreach ($taxonomy as $topicId => $topic) {
            try {
                $this->graph->upsertNode('LegalTopic', 'topic_' . md5($topic['name']), [
                    'name' => $topic['name'],
                    'name_en' => $topic['name_en'] ?? null,
                    'description' => 'Croatian legal topic: ' . $topic['name'],
                    'created_at' => now()->toIso8601String(),
                ]);
                $count++;

                Log::debug('Seeded legal topic', [
                    'topic' => $topic['name'],
                    'topic_id' => 'topic_' . md5($topic['name']),
                ]);

                // Seed child topics
                foreach ($topic['children'] ?? [] as $childId => $child) {
                    $parentTopicId = 'topic_' . md5($topic['name']);
                    $childTopicId = 'topic_' . md5($child['name']);

                    $this->graph->upsertNode('LegalTopic', $childTopicId, [
                        'name' => $child['name'],
                        'parent_id' => $parentTopicId,
                        'created_at' => now()->toIso8601String(),
                    ]);
                    $count++;

                    Log::debug('Seeded child legal topic', [
                        'topic' => $child['name'],
                        'topic_id' => $childTopicId,
                        'parent_id' => $parentTopicId,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to seed legal topic', [
                    'topic' => $topic['name'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }
}
