<?php

namespace App\Services\Graph;

/**
 * Interface for graph synchronization services
 *
 * Defines the contract for services that sync data to the graph database.
 */
interface GraphSyncServiceInterface
{
    /**
     * Sync a document to the graph database
     *
     * Returns metrics about the sync operation including nodes created,
     * relationships created, errors encountered, and duration.
     *
     * @param  string  $id  The document ID
     * @return array{nodes: array<string, int>, relationships: array<string, int>, errors: array, duration_ms: int}
     */
    public function sync(string $id): array;

    /**
     * Check if this service supports a given document type
     *
     * @param  string  $type  The document type
     */
    public function supportsType(string $type): bool;
}
