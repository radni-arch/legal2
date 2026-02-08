<?php

namespace App\Listeners;

use App\Events\CourtDecisionIngested;
use App\Jobs\SyncGraphDataJob;
use App\Services\GraphRelationshipUpdater;
use Illuminate\Support\Facades\Log;

class SyncDecisionToGraph
{
    /**
     * Handle the event.
     */
    public function handle(CourtDecisionIngested $event): void
    {
        if (! config('neo4j.sync.enabled')) {
            Log::debug('Neo4j sync disabled, skipping decision sync', ['decision_id' => $event->decisionId]);

            return;
        }

        if (config('neo4j.sync.auto_sync_on_ingest', true)) {
            Log::info('Auto-syncing decision to graph', ['decision_id' => $event->decisionId]);

            SyncGraphDataJob::dispatchWithEnvDetection('decision', $event->decisionId);

            // Update relationships with existing decisions that cite this decision
            if (config('neo4j.sync.update_relationships', true)) {
                try {
                    $updater = app(GraphRelationshipUpdater::class);
                    $updater->updateRelationshipsForNewDecision($event->decisionId);
                } catch (\Exception $e) {
                    Log::warning('Failed to update relationships for new decision', [
                        'decision_id' => $event->decisionId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }
}
