<?php

namespace App\Observers;

use App\Models\TextractDocument;
use App\Services\Graph\TextractGraphSyncService;
use Illuminate\Support\Facades\Log;

/**
 * Observer for TextractDocument model events
 *
 * Handles Neo4j graph synchronization when TextractDocument models are deleted.
 * Ensures graph cleanup without blocking deletion if Neo4j is unavailable.
 */
class TextractDocumentObserver
{
    public function __construct(
        protected TextractGraphSyncService $syncService
    ) {}

    /**
     * Handle the TextractDocument "deleted" event.
     */
    public function deleted(TextractDocument $document): void
    {
        $this->unsyncFromGraph($document);
    }

    /**
     * Handle the TextractDocument "force deleted" event.
     */
    public function forceDeleted(TextractDocument $document): void
    {
        $this->unsyncFromGraph($document);
    }

    /**
     * Remove document from Neo4j graph
     *
     * Gracefully handles errors - deletion should succeed even if graph sync fails.
     */
    protected function unsyncFromGraph(TextractDocument $document): void
    {
        // Skip if document has no ID
        if (! $document->id) {
            Log::warning('TextractDocument deletion observer called with null ID', [
                'document' => get_class($document),
            ]);

            return;
        }

        try {
            $result = $this->syncService->unsync($document->id);

            if ($result) {
                Log::info('TextractDocument removed from Neo4j graph via observer', [
                    'textract_doc_id' => $document->id,
                ]);
            } else {
                Log::info('TextractDocument not found in Neo4j graph or Neo4j unavailable', [
                    'textract_doc_id' => $document->id,
                ]);
            }
        } catch (\Exception $e) {
            // Log warning but don't block deletion
            Log::warning('Failed to unsync TextractDocument from Neo4j graph', [
                'textract_doc_id' => $document->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
