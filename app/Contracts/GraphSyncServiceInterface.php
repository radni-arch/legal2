<?php

namespace App\Contracts;

/**
 * Interface for services that synchronize documents to the Neo4j graph database.
 *
 * Implementations of this interface handle the synchronization of specific
 * document types (laws, court decisions, cases, etc.) to the graph database,
 * creating nodes and relationships based on the document content.
 */
interface GraphSyncServiceInterface
{
    /**
     * Synchronize a document to the graph database.
     *
     * This method should:
     * - Extract relevant data from the document
     * - Create or update graph nodes
     * - Establish relationships with other nodes
     * - Handle errors gracefully
     *
     * @param  string  $documentId  The unique identifier of the document to sync
     *
     * @throws \InvalidArgumentException If the document ID is invalid
     * @throws \RuntimeException If synchronization fails
     */
    public function sync(string $documentId): void;

    /**
     * Check if this service supports synchronizing a given document type.
     *
     * @param  string  $type  The document type (e.g., 'LawDocument', 'CourtDecision', 'Case')
     * @return bool True if this service can sync the given type, false otherwise
     */
    public function supportsType(string $type): bool;

    /**
     * Batch synchronize multiple documents to the graph database.
     *
     * This method should efficiently sync multiple documents in a single operation,
     * using batching or transactions where appropriate for performance.
     *
     * @param  array<string>  $documentIds  Array of document IDs to synchronize
     * @return array<string, bool> Map of document IDs to success status
     */
    public function syncBatch(array $documentIds): array;

    /**
     * Remove a document and its relationships from the graph database.
     *
     * @param  string  $documentId  The unique identifier of the document to remove
     * @return bool True if removed successfully, false if document not found
     *
     * @throws \RuntimeException If removal fails
     */
    public function unsync(string $documentId): bool;
}
