<?php

namespace App\Services\Graph;

use App\Services\Graph\Extractors\DateEventExtractor;
use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\Log;

class DateEventGraphSyncService
{
    public function __construct(
        protected GraphDatabaseService $graph,
        protected DateEventExtractor $extractor
    ) {}

    public function sync(string $documentId, string $content, string $sourceType = 'decision'): array
    {
        $events = $this->extractor->extract($content, $documentId, $sourceType);

        $metrics = [
            'events_created' => 0,
            'events_linked' => 0,
        ];

        foreach ($events as $event) {
            try {
                $this->graph->upsertNode('DateEvent', $event['id'], [
                    'date' => $event['date'],
                    'event_type' => $event['event_type'],
                    'description' => $event['description'],
                    'source_type' => $event['source_type'],
                    'source_id' => $event['source_id'],
                    'created_at' => now()->toIso8601String(),
                ]);
                $metrics['events_created']++;

                $this->graph->createRelationship(
                    'CourtDecisionDocument',
                    $documentId,
                    'HAS_EVENT',
                    'DateEvent',
                    $event['id'],
                    [
                        'created_at' => now()->toIso8601String(),
                    ]
                );
                $metrics['events_linked']++;

            } catch (\Throwable $e) {
                Log::warning('Failed to sync date event', [
                    'document_id' => $documentId,
                    'event_id' => $event['id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $metrics;
    }
}
