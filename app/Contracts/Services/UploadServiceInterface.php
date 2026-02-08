<?php

namespace App\Contracts\Services;

use Illuminate\Http\UploadedFile;

/**
 * UploadServiceInterface
 *
 * Handles chunked file uploads with manifest tracking.
 * Provides:
 * - Chunked upload initiation and management
 * - Chunk assembly and validation
 * - Direct upload for small files
 */
interface UploadServiceInterface
{
    /**
     * Start a new chunked upload
     *
     * Initializes a new upload session, creates manifest tracking file,
     * and prepares chunk storage directory.
     *
     * Returns manifest with:
     * - id: ULID upload identifier
     * - filename: Original filename
     * - mime: MIME type (if provided)
     * - total_size: Total file size in bytes
     * - chunk_size: Size of each chunk
     * - created_at: ISO 8601 timestamp
     * - received: Empty array (tracks received chunk indices)
     * - completed: false
     *
     * @param  string  $filename  Original filename
     * @param  int  $totalSize  Total file size in bytes
     * @param  int  $chunkSize  Size of each chunk in bytes
     * @param  string|null  $mime  MIME type
     * @return array Upload manifest
     */
    public function start(string $filename, int $totalSize, int $chunkSize, ?string $mime = null): array;

    /**
     * Upload a file chunk
     *
     * Stores a chunk for an existing upload session and updates manifest
     * to track received chunks.
     *
     * @param  string  $uploadId  Upload session ID from start()
     * @param  int  $index  Zero-based chunk index
     * @param  UploadedFile  $chunk  Uploaded chunk file
     * @return array Result with 'id', 'index', 'received' array
     *
     * @throws \InvalidArgumentException If upload ID is invalid
     */
    public function uploadChunk(string $uploadId, int $index, UploadedFile $chunk): array;

    /**
     * Complete chunked upload
     *
     * Validates all chunks are received, assembles them into final file,
     * stores on public disk, and cleans up temporary chunks.
     *
     * Returns:
     * - status: 'completed' or 'incomplete'
     * - id: Upload ID
     * - filename: Original filename
     * - path: Stored file path (if completed)
     * - url: Public URL (if completed)
     * - expected: Number of expected chunks (if incomplete)
     * - received: Array of received chunk indices (if incomplete)
     *
     * @param  string  $uploadId  Upload session ID
     * @return array Completion result
     *
     * @throws \InvalidArgumentException If upload ID is invalid
     */
    public function complete(string $uploadId): array;

    /**
     * Cancel upload and cleanup
     *
     * Deletes all chunks and manifest for an upload session.
     *
     * @param  string  $uploadId  Upload session ID
     * @return bool True if canceled successfully
     */
    public function cancel(string $uploadId): bool;

    /**
     * Direct store for non-chunked uploads
     *
     * Stores a complete file directly without chunking.
     * Returns file metadata including path, URL, size, and MIME type.
     *
     * @param  UploadedFile  $file  File to store
     * @return array File metadata (path, url, size, mime, name)
     */
    public function directStore(UploadedFile $file): array;
}
