<?php

namespace App\Contracts\Ingest;

/**
 * Odluke Ingest Service Interface
 *
 * Defines the contract for ingesting court decisions from odluke.sudovi.hr
 * into the vector store and graph database.
 */
interface OdlukeIngestServiceInterface
{
    /**
     * Ingest court decisions by their IDs
     *
     * @param  array<string>  $ids  Decision IDs to ingest
     * @param  array  $options  Ingestion options: sync_graph, force_refresh, etc.
     * @return array{ingested: int, failed: int, skipped: int, ids: array} Ingestion results
     */
    public function ingestByIds(array $ids, array $options = []): array;

    /**
     * Ingest court decisions by IDs using queue
     *
     * @param  array<string>  $ids  Decision IDs to ingest
     * @param  array  $options  Ingestion options
     * @return array{queued: int, ids: array} Queue results
     */
    public function ingestByIdsQueued(array $ids, array $options = []): array;

    /**
     * Ingest raw text as a court decision
     *
     * @param  string  $text  Decision text content
     * @param  array  $meta  Metadata for the decision
     * @param  array  $options  Ingestion options
     * @return array Ingestion result
     */
    public function ingestText(string $text, array $meta = [], array $options = []): array;

    /**
     * Get metadata for decision IDs
     *
     * @param  array<string>  $ids  Decision IDs
     * @param  string|null  $baseUrl  Base URL for odluke.sudovi.hr
     * @return array Metadata for each decision
     */
    public function getMetadataForIds(array $ids, ?string $baseUrl = null): array;

    /**
     * Download decision content
     *
     * @param  string  $id  Decision ID
     * @param  array  $options  Download options
     * @return array{content: string, metadata: array} Downloaded content and metadata
     */
    public function download(string $id, array $options = []): array;
}
