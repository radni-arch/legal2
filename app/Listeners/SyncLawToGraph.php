<?php

namespace App\Listeners;

use App\Events\LawIngested;
use App\Jobs\SyncGraphDataJob;
use App\Services\GraphRelationshipUpdater;
use Illuminate\Support\Facades\Log;

class SyncLawToGraph
{
    /**
     * Handle the event.
     */
    public function handle(LawIngested $event): void
    {
        if (! config('neo4j.sync.enabled')) {
            Log::debug('Neo4j sync disabled, skipping law sync', ['law_id' => $event->lawId]);

            return;
        }

        if (config('neo4j.sync.auto_sync_on_ingest', true)) {
            Log::info('Auto-syncing law to graph', ['law_id' => $event->lawId]);

            SyncGraphDataJob::dispatchWithEnvDetection('law', $event->lawId);

            // Update relationships with existing decisions that cite this law
            if (config('neo4j.sync.update_relationships', true)) {
                try {
                    $updater = app(GraphRelationshipUpdater::class);
                    $updater->updateRelationshipsForNewLaw($event->lawId);
                } catch (\Exception $e) {
                    Log::warning('Failed to update relationships for new law', [
                        'law_id' => $event->lawId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }
}
