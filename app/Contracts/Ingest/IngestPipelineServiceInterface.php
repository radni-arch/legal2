<?php

namespace App\Contracts\Ingest;

/**
 * Ingest Pipeline Service Interface
 *
 * Defines the contract for generic document ingestion pipeline
 * that processes text, files, and documents into vector storage.
 */
interface IngestPipelineServiceInterface
{
    /**
     * Ingest text content
     *
     * @param  string  $agent  Agent identifier
     * @param  string  $namespace  Namespace for organization
     * @param  string  $text  Text content to ingest
     * @param  array  $options  Ingestion options
     * @return array Ingestion result
     */
    public function ingestText(string $agent, string $namespace, string $text, array $options = []): array;

    /**
     * Ingest a file
     *
     * @param  string  $agent  Agent identifier
     * @param  string  $namespace  Namespace for organization
     * @param  string  $path  File path
     * @param  string|null  $mime  MIME type
     * @param  array  $options  Ingestion options
     * @return array Ingestion result
     */
    public function ingestFile(string $agent, string $namespace, string $path, ?string $mime = null, array $options = []): array;

    /**
     * Ingest multiple documents
     *
     * @param  string  $agent  Agent identifier
     * @param  string  $namespace  Namespace for organization
     * @param  array  $docs  Array of documents
     * @param  array  $options  Ingestion options
     * @return array Ingestion result
     */
    public function ingestDocuments(string $agent, string $namespace, array $docs, array $options = []): array;

    /**
     * Chunk text into segments
     *
     * @param  string  $text  Text to chunk
     * @param  int  $chunkChars  Characters per chunk
     * @param  int  $overlap  Overlap between chunks
     * @return array Array of text chunks
     */
    public function chunkText(string $text, int $chunkChars = 2000, int $overlap = 200): array;

    /**
     * Search ingested content
     *
     * @param  string  $agent  Agent identifier
     * @param  string|null  $namespace  Namespace filter
     * @param  string  $query  Search query
     * @param  int  $limit  Result limit
     * @return array Search results
     */
    public function search(string $agent, ?string $namespace, string $query, int $limit = 5): array;
}
