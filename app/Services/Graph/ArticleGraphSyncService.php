<?php

namespace App\Services\Graph;

use App\Services\Graph\Extractors\ArticleExtractor;
use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\Log;

class ArticleGraphSyncService
{
    public function __construct(
        protected GraphDatabaseService $graph,
        protected ArticleExtractor $extractor
    ) {}

    /**
     * Sync articles from a law document
     */
    public function syncLawArticles(string $lawId, string $lawText): array
    {
        $articles = $this->extractor->extractStructure($lawText, $lawId);

        $metrics = [
            'articles_created' => 0,
            'contains_relationships' => 0,
        ];

        foreach ($articles as $article) {
            try {
                // Create Article node
                $this->graph->upsertNode('Article', $article['id'], [
                    'law_id' => $article['law_id'],
                    'article_number' => $article['article_number'],
                    'title' => $article['title'],
                    'content' => substr($article['content'], 0, 10000), // Limit content size
                    'paragraph_count' => $article['paragraph_count'],
                    'created_at' => now()->toIso8601String(),
                ]);
                $metrics['articles_created']++;

                // Create CONTAINS relationship from LawDocument
                $this->graph->createRelationship(
                    'LawDocument',
                    $lawId,
                    'CONTAINS',
                    'Article',
                    $article['id'],
                    [
                        'position' => $article['position'],
                        'created_at' => now()->toIso8601String(),
                    ]
                );
                $metrics['contains_relationships']++;

            } catch (\Throwable $e) {
                Log::warning('Failed to sync article', [
                    'law_id' => $lawId,
                    'article' => $article['article_number'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $metrics;
    }

    /**
     * Link a decision document to cited articles
     */
    public function linkDecisionToArticles(string $decisionId, string $content, ?string $lawId = null): array
    {
        $references = $this->extractor->extractReferences($content, $lawId);

        $metrics = [
            'articles_linked' => 0,
        ];

        foreach ($references as $ref) {
            try {
                // First ensure the Article node exists (at least as a stub)
                $this->graph->upsertNode('Article', $ref['id'], [
                    'law_id' => $ref['law_id'],
                    'article_number' => $ref['article_number'],
                    'created_at' => now()->toIso8601String(),
                ]);

                // Create CITES_ARTICLE relationship
                $this->graph->createRelationship(
                    'CourtDecisionDocument',
                    $decisionId,
                    'CITES_ARTICLE',
                    'Article',
                    $ref['id'],
                    [
                        'paragraph' => $ref['paragraph'],
                        'context' => substr($ref['context'], 0, 500),
                        'created_at' => now()->toIso8601String(),
                    ]
                );
                $metrics['articles_linked']++;

            } catch (\Throwable $e) {
                Log::warning('Failed to link article citation', [
                    'decision_id' => $decisionId,
                    'article' => $ref['article_number'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $metrics;
    }
}
