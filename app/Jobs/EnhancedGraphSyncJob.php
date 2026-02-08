<?php

namespace App\Jobs;

use App\Events\Graph\BatchStarted;
use App\Events\Graph\BatchCompleted;
use App\Events\Graph\SyncFinished;
use App\Models\CourtDecision;
use App\Services\Graph\DecisionGraphSyncService;
use App\Services\Graph\DocumentTypeDetector;
use App\Services\Graph\ParallelExtractionService;
use App\Services\GraphDatabaseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EnhancedGraphSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public array $decisionIds = [],
        public int $batchSize = 100,
        public bool $allDecisions = false
    ) {}

    public function handle(
        DecisionGraphSyncService $syncService,
        DocumentTypeDetector $typeDetector,
        ParallelExtractionService $parallelExtraction,
        GraphDatabaseService $graphService
    ): void
    {
        $startTime = microtime(true);

        $query = CourtDecision::query();

        if (!$this->allDecisions && !empty($this->decisionIds)) {
            $query->whereIn('id', $this->decisionIds);
        }

        $totalDecisions = $query->count();
        $totalBatches = (int) ceil($totalDecisions / $this->batchSize);

        $totalNodes = 0;
        $totalRelationships = 0;
        $totalErrors = 0;
        $batchNumber = 0;

        $query->chunkById($this->batchSize, function ($decisions) use (
            $syncService,
            $typeDetector,
            $parallelExtraction,
            $graphService,
            &$batchNumber,
            $totalBatches,
            &$totalNodes,
            &$totalRelationships,
            &$totalErrors
        ) {
            $batchNumber++;
            $batchStart = microtime(true);
            $batchIds = $decisions->pluck('id')->toArray();

            event(new BatchStarted(
                $batchNumber,
                $totalBatches,
                count($batchIds),
                $batchIds
            ));

            $batchNodes = 0;
            $batchRelationships = 0;
            $batchErrors = 0;

            // Collect all extracted entities for batch operations
            $extractedNodes = [];

            foreach ($decisions as $decision) {
                try {
                    // Get decision documents
                    $documents = $decision->documents;

                    // Process each document with parallel extraction (if documents exist)
                    if (!$documents->isEmpty()) {
                        foreach ($documents as $document) {
                            if (empty($document->content)) {
                                continue;
                            }

                            // Detect document type
                            $docType = $typeDetector->detect($document->content);

                            // Get selective extractors for document type
                            $extractorNames = $typeDetector->getExtractorsForType($docType);

                            if (empty($extractorNames)) {
                                Log::debug('No extractors for document type', [
                                    'doc_type' => $docType,
                                    'document_id' => $document->id,
                                ]);
                                continue;
                            }

                            // Convert extractor names to fully-qualified class names
                            $extractorClasses = array_map(
                                fn($name) => "App\\Services\\Graph\\Extractors\\{$name}",
                                $extractorNames
                            );

                            // Run extractors in parallel
                            try {
                                $results = $parallelExtraction->extractInParallel($document, $extractorClasses);

                                // Collect extracted entities for batch creation
                                foreach ($results as $result) {
                                    if (isset($result['error']) && $result['error']) {
                                        Log::warning('Extractor failed', [
                                            'extractor' => $result['extractor'],
                                            'message' => $result['message'] ?? 'Unknown error',
                                            'document_id' => $document->id,
                                        ]);
                                        continue;
                                    }

                                    // Process extracted data into nodes
                                    $this->collectExtractedNodes($result['data'], $document->id, $extractedNodes);
                                }
                            } catch (\Throwable $e) {
                                Log::error('Parallel extraction failed', [
                                    'document_id' => $document->id,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        }
                    }

                    // Perform standard sync for relationships and other operations
                    $metrics = $syncService->sync($decision->id);

                    // Count total nodes from the nodes array
                    $batchNodes += array_sum($metrics['nodes'] ?? []);

                    // Count total relationships from the relationships array
                    $batchRelationships += array_sum($metrics['relationships'] ?? []);

                    // Count errors
                    $batchErrors += count($metrics['errors'] ?? []);
                } catch (\Throwable $e) {
                    Log::error('Graph sync failed for decision', [
                        'decision_id' => $decision->id,
                        'error' => $e->getMessage(),
                    ]);
                    $batchErrors++;
                }
            }

            // Batch create extracted entity nodes
            if (!empty($extractedNodes)) {
                try {
                    foreach ($extractedNodes as $label => $nodes) {
                        if (!empty($nodes)) {
                            $count = $graphService->batchUpsertNodes($label, $nodes, 'id');
                            $batchNodes += $count;

                            Log::debug('Batch upserted extracted nodes', [
                                'label' => $label,
                                'count' => $count,
                            ]);
                        }
                    }
                } catch (\Throwable $e) {
                    Log::error('Batch node creation failed', [
                        'error' => $e->getMessage(),
                    ]);
                    $batchErrors++;
                }
            }

            $totalNodes += $batchNodes;
            $totalRelationships += $batchRelationships;
            $totalErrors += $batchErrors;

            $batchDuration = microtime(true) - $batchStart;

            event(new BatchCompleted(
                $batchNumber,
                $totalBatches,
                $batchNodes,
                $batchRelationships,
                $batchErrors,
                $batchDuration
            ));

            Log::info('Graph sync batch completed', [
                'batch' => "{$batchNumber}/{$totalBatches}",
                'nodes' => $batchNodes,
                'relationships' => $batchRelationships,
                'errors' => $batchErrors,
                'duration' => round($batchDuration, 2) . 's',
            ]);
        });

        $totalDuration = microtime(true) - $startTime;
        $status = $totalErrors === 0 ? 'completed' : ($totalErrors < $totalDecisions ? 'partial' : 'failed');

        event(new SyncFinished(
            $totalDecisions,
            $totalNodes,
            $totalRelationships,
            $totalErrors,
            $totalDuration,
            $status
        ));

        Log::info('Graph sync finished', [
            'total_decisions' => $totalDecisions,
            'total_nodes' => $totalNodes,
            'total_relationships' => $totalRelationships,
            'total_errors' => $totalErrors,
            'duration' => round($totalDuration, 2) . 's',
            'status' => $status,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('EnhancedGraphSyncJob failed', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Collect extracted entities into batch structure for batch node creation
     *
     * @param array $data Extracted data from extractor
     * @param string $documentId Source document ID
     * @param array &$extractedNodes Reference to collected nodes array
     */
    protected function collectExtractedNodes(array $data, string $documentId, array &$extractedNodes): void
    {
        // Process different types of extracted entities
        // Each extractor may return different structures

        // Example: Topics
        if (isset($data['topics'])) {
            foreach ($data['topics'] as $topic) {
                if (is_array($topic) && isset($topic['name'])) {
                    $topicId = 'topic_' . md5($topic['name']);
                    $extractedNodes['Topic'][$topicId] = [
                        'id' => $topicId,
                        'name' => $topic['name'],
                        'category' => $topic['category'] ?? null,
                        'source_document' => $documentId,
                    ];
                }
            }
        }

        // Example: Concepts
        if (isset($data['concepts'])) {
            foreach ($data['concepts'] as $concept) {
                if (is_array($concept) && isset($concept['name'])) {
                    $conceptId = 'concept_' . md5($concept['name']);
                    $extractedNodes['LegalConcept'][$conceptId] = [
                        'id' => $conceptId,
                        'name' => $concept['name'],
                        'definition' => $concept['definition'] ?? null,
                        'source_document' => $documentId,
                    ];
                }
            }
        }

        // Example: Articles
        if (isset($data['articles'])) {
            foreach ($data['articles'] as $article) {
                if (is_array($article) && isset($article['reference'])) {
                    $articleId = 'article_' . md5($article['reference']);
                    $extractedNodes['Article'][$articleId] = [
                        'id' => $articleId,
                        'reference' => $article['reference'],
                        'law' => $article['law'] ?? null,
                        'text' => $article['text'] ?? null,
                        'source_document' => $documentId,
                    ];
                }
            }
        }

        // Example: Legal Arguments
        if (isset($data['arguments'])) {
            foreach ($data['arguments'] as $argument) {
                if (is_array($argument) && isset($argument['text'])) {
                    $argumentId = 'argument_' . md5($argument['text']);
                    $extractedNodes['LegalArgument'][$argumentId] = [
                        'id' => $argumentId,
                        'text' => $argument['text'],
                        'type' => $argument['type'] ?? null,
                        'source_document' => $documentId,
                    ];
                }
            }
        }

        // Example: Evidence
        if (isset($data['evidence'])) {
            foreach ($data['evidence'] as $evidence) {
                if (is_array($evidence) && isset($evidence['description'])) {
                    $evidenceId = 'evidence_' . md5($evidence['description']);
                    $extractedNodes['Evidence'][$evidenceId] = [
                        'id' => $evidenceId,
                        'description' => $evidence['description'],
                        'type' => $evidence['type'] ?? null,
                        'source_document' => $documentId,
                    ];
                }
            }
        }

        // Example: Dates/Events
        if (isset($data['events'])) {
            foreach ($data['events'] as $event) {
                if (is_array($event) && isset($event['date'])) {
                    $eventId = 'event_' . md5($event['date'] . ($event['description'] ?? ''));
                    $extractedNodes['DateEvent'][$eventId] = [
                        'id' => $eventId,
                        'date' => $event['date'],
                        'description' => $event['description'] ?? null,
                        'source_document' => $documentId,
                    ];
                }
            }
        }
    }
}
