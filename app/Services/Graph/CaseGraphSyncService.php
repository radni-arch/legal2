<?php

namespace App\Services\Graph;

use App\Contracts\GraphSyncServiceInterface;
use App\Services\GraphDatabaseService;
use App\Services\TaggingService;
use Illuminate\Support\Facades\DB;

/**
 * Service for syncing case documents to the graph database
 *
 * Extracted from GraphRagService to provide focused, single-responsibility
 * service for case document synchronization.
 *
 * Responsibilities:
 * - Create/update case document nodes in Neo4j
 * - Delegate keyword extraction to GraphKeywordLinker
 * - Delegate citation extraction to GraphCitationLinker
 * - Delegate similarity calculation to GraphSimilarityLinker
 * - Auto-tag cases with metadata
 */
class CaseGraphSyncService implements GraphSyncServiceInterface
{
    public function __construct(
        protected GraphDatabaseService $graph,
        protected GraphKeywordLinker $keywordLinker,
        protected GraphCitationLinker $citationLinker,
        protected GraphSimilarityLinker $similarityLinker,
        protected TaggingService $tagging
    ) {}

    /**
     * Sync a case document to the graph database
     *
     * @param  string  $caseDocId  The case document ID
     */
    public function sync(string $caseDocId): void
    {
        $caseDoc = DB::table('cases_documents')->where('id', $caseDocId)->first();

        if (! $caseDoc) {
            return;
        }

        // Create case document node
        $this->createCaseDocumentNode($caseDoc);

        // Extract and link keywords
        $this->keywordLinker->link('CaseDocument', $caseDoc->id, $caseDoc->content);

        // Extract and create citation relationships (cases can cite laws and reference other cases)
        $this->citationLinker->link('CaseDocument', $caseDoc->id, $caseDoc->content);

        // Find and create similarity relationships
        $this->similarityLinker->link('CaseDocument', $caseDoc->id, $caseDoc->embedding_vector ?? null);

        // Auto-tag the case (fallback to empty array if metadata decodes to null)
        $metadata = json_decode($caseDoc->metadata ?? '[]', true) ?? [];
        $this->tagging->autoTag('CaseDocument', $caseDoc->id, $caseDoc->content, $metadata);
    }

    /**
     * Check if this service supports a given document type
     *
     * @param  string  $type  The document type
     */
    public function supportsType(string $type): bool
    {
        return $type === 'CaseDocument';
    }

    /**
     * Batch synchronize multiple case documents to the graph database
     *
     * @param  array<string>  $documentIds  Array of case document IDs to synchronize
     * @return array<string, bool> Map of document IDs to success status
     */
    public function syncBatch(array $documentIds): array
    {
        $results = [];

        foreach ($documentIds as $documentId) {
            try {
                $this->sync($documentId);
                $results[$documentId] = true;
            } catch (\Exception $e) {
                $results[$documentId] = false;
            }
        }

        return $results;
    }

    /**
     * Remove a case document and its relationships from the graph database
     *
     * @param  string  $documentId  The unique identifier of the case document to remove
     * @return bool True if removed successfully, false if document not found
     *
     * @throws \RuntimeException If removal fails
     */
    public function unsync(string $documentId): bool
    {
        try {
            $this->graph->deleteNode('CaseDocument', $documentId);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Create the case document node in the graph
     *
     * @param  object  $caseDoc  The case document database record
     */
    protected function createCaseDocumentNode($caseDoc): void
    {
        $this->graph->upsertNode('CaseDocument', $caseDoc->id, [
            'case_id' => $caseDoc->case_id,
            'doc_id' => $caseDoc->doc_id,
            'title' => $caseDoc->title,
            'category' => $caseDoc->category,
            'language' => $caseDoc->language,
            'chunk_index' => $caseDoc->chunk_index,
            'content_hash' => $caseDoc->content_hash,
            'source' => $caseDoc->source,
        ]);
    }
}
