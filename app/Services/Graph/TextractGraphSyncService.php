<?php

namespace App\Services\Graph;

use App\Contracts\GraphSyncServiceInterface;
use App\Services\GraphDatabaseService;
use App\Services\TaggingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for syncing Textract documents to the graph database
 *
 * Handles synchronization of OCR'd documents from AWS Textract to Neo4j,
 * creating nodes and relationships for document analysis and retrieval.
 *
 * Responsibilities:
 * - Create/update Textract document nodes in Neo4j
 * - Delegate keyword extraction to GraphKeywordLinker
 * - Delegate citation extraction to GraphCitationLinker
 * - Delegate similarity calculation to GraphSimilarityLinker
 * - Auto-tag documents with metadata
 */
class TextractGraphSyncService implements GraphSyncServiceInterface
{
    public function __construct(
        protected GraphDatabaseService $graph,
        protected GraphKeywordLinker $keywordLinker,
        protected GraphCitationLinker $citationLinker,
        protected GraphSimilarityLinker $similarityLinker,
        protected TaggingService $tagging
    ) {}

    /**
     * Sync a Textract document to the graph database
     *
     * @param  string  $textractDocId  The Textract document ID
     */
    public function sync(string $textractDocId): void
    {
        $textractDoc = DB::table('textract_documents')->where('id', $textractDocId)->first();

        if (! $textractDoc) {
            Log::warning('Textract document not found for sync', [
                'textract_doc_id' => $textractDocId,
            ]);

            return;
        }

        // Skip if Neo4j is not available
        if (! $this->graph->isAvailable()) {
            Log::info('Skipping Textract document sync - Neo4j is not available', [
                'textract_doc_id' => $textractDocId,
            ]);

            return;
        }

        // Create Textract document node
        $this->createTextractDocumentNode($textractDoc);

        // Extract and link keywords
        $this->keywordLinker->link('TextractDocument', $textractDoc->id, $textractDoc->content);

        // Extract and create citation relationships
        $this->citationLinker->link('TextractDocument', $textractDoc->id, $textractDoc->content);

        // Find and create similarity relationships
        $embeddingVector = $textractDoc->embedding ?? null;
        if ($embeddingVector) {
            // Decode JSON embedding if needed
            if (is_string($embeddingVector)) {
                $embeddingVector = json_decode($embeddingVector, true);
            }
        }
        $this->similarityLinker->link('TextractDocument', $textractDoc->id, $embeddingVector);

        // Auto-tag the document
        $metadata = json_decode($textractDoc->metadata ?? '[]', true);
        if (! is_array($metadata)) {
            $metadata = [];
        }
        $this->tagging->autoTag('TextractDocument', $textractDoc->id, $textractDoc->content, $metadata);

        Log::info('Textract document synced to graph successfully', [
            'textract_doc_id' => $textractDoc->id,
            'case_id' => $textractDoc->case_id,
            'chunk_index' => $textractDoc->chunk_index,
        ]);
    }

    /**
     * Check if this service supports a given document type
     *
     * @param  string  $type  The document type
     */
    public function supportsType(string $type): bool
    {
        return $type === 'TextractDocument';
    }

    /**
     * Batch synchronize multiple Textract documents to the graph database
     *
     * @param  array<string>  $documentIds  Array of Textract document IDs to synchronize
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
                Log::warning('Failed to sync Textract document in batch', [
                    'textract_doc_id' => $documentId,
                    'error' => $e->getMessage(),
                ]);
                $results[$documentId] = false;
            }
        }

        Log::info('Textract document batch sync completed', [
            'total' => count($documentIds),
            'successful' => count(array_filter($results)),
            'failed' => count(array_filter($results, fn ($v) => ! $v)),
        ]);

        return $results;
    }

    /**
     * Remove a Textract document and its relationships from the graph database
     *
     * @param  string  $documentId  The Textract document ID to remove
     * @return bool True if removed successfully, false if document not found
     *
     * @throws \RuntimeException If removal fails
     */
    public function unsync(string $documentId): bool
    {
        if (empty($documentId)) {
            return false;
        }

        // Skip if Neo4j is not available
        if (! $this->graph->isAvailable()) {
            Log::info('Skipping Textract document unsync - Neo4j is not available', [
                'textract_doc_id' => $documentId,
            ]);

            return false;
        }

        try {
            $this->graph->deleteNode('TextractDocument', $documentId);

            Log::info('Textract document removed from graph successfully', [
                'textract_doc_id' => $documentId,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to remove Textract document from graph', [
                'textract_doc_id' => $documentId,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException("Failed to unsync Textract document: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Create the Textract document node in the graph
     *
     * @param  object  $textractDoc  The Textract document database record
     */
    protected function createTextractDocumentNode($textractDoc): void
    {
        $this->graph->upsertNode('TextractDocument', $textractDoc->id, [
            'textract_job_id' => $textractDoc->textract_job_id,
            'case_id' => $textractDoc->case_id,
            'chunk_index' => $textractDoc->chunk_index,
            'chunk_overlap' => $textractDoc->chunk_overlap ?? 0,
            'token_count' => $textractDoc->token_count,
            'processing_status' => $textractDoc->processing_status,
            'embedding_provider' => $textractDoc->embedding_provider,
            'embedding_model' => $textractDoc->embedding_model,
            'embedding_dimensions' => $textractDoc->embedding_dimensions,
            'embedded_at' => $textractDoc->embedded_at,
        ]);
    }
}
