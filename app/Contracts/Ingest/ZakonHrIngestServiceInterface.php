<?php

namespace App\Contracts\Ingest;

/**
 * Zakon.hr Ingest Service Interface
 *
 * Defines the contract for ingesting Croatian laws from zakon.hr
 * into the vector store.
 */
interface ZakonHrIngestServiceInterface
{
    /**
     * Ingest laws from URLs
     *
     * @param  array<string>  $urls  Law URLs to ingest
     * @param  array  $options  Ingestion options: force_refresh, chunk_size, etc.
     * @return array{ingested: int, failed: int, urls: array} Ingestion results
     */
    public function ingestUrls(array $urls, array $options = []): array;

    /**
     * Ingest law from HTML content
     *
     * @param  string  $html  HTML content
     * @param  array  $options  Ingestion options
     * @param  string  $sourceUrl  Source URL for reference
     * @return array Ingestion result
     */
    public function ingestHtml(string $html, array $options = [], string $sourceUrl = 'offline://zakonhr-sample'): array;
}
