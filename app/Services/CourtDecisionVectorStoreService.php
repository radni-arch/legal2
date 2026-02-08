<?php

namespace App\Services;

use App\Contracts\VectorStore\CourtDecisionVectorStoreInterface;
use App\Exceptions\EmbeddingException;
use App\Exceptions\IngestException;
use App\Exceptions\VectorStoreException;
use App\Models\CourtDecisionDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CourtDecisionVectorStoreService implements CourtDecisionVectorStoreInterface
{
    public function __construct(
        protected OpenAIService $openai,
        protected ?Graph\GraphRagOrchestrator $graphRag = null
    ) {}

    /**
     * Ingest court decision documents into vector store
     *
     * @param  string  $decisionId  ULID of the CourtDecision
     * @param  string  $docId  Group identifier for this document
     * @param  array  $docs  Array of document chunks with content
     * @param  array  $options  Ingestion options
     * @return array Ingestion results
     *
     * @throws IngestException if ingestion fails
     */
    public function ingest(string $decisionId, string $docId, array $docs, array $options = []): array
    {
        try {
            Log::info('Court decision ingestion initiated', [
                'decision_id' => $decisionId,
                'doc_id' => $docId,
                'chunk_count' => count($docs),
                'options' => $options,
            ]);

            $startTime = microtime(true);

            // Validate and filter input
            $docs = $this->validateAndFilterDocs($docs);

            if (empty($docs)) {
                Log::warning('No valid documents to ingest', [
                    'decision_id' => $decisionId,
                    'doc_id' => $docId,
                ]);

                return ['count' => 0, 'inserted' => 0];
            }

            $model = $options['model'] ?? config('openai.models.embeddings');
            $provider = $options['provider'] ?? 'openai';
            $uploadId = $options['upload_id'] ?? null;

            // Generate embeddings
            $inputs = array_map(fn ($d) => (string) $d['content'], $docs);
            $emb = $this->generateEmbeddingsWithRetry($inputs, $model, $decisionId);
            $data = $emb['data'] ?? [];

            if (count($data) !== count($docs)) {
                Log::warning('Embedding count mismatch', [
                    'docs' => count($docs),
                    'embeddings' => count($data),
                    'decision_id' => $decisionId,
                ]);
            }

            $dims = isset($data[0]['embedding']) ? count($data[0]['embedding']) : null;

            $driver = DB::connection()->getDriverName();
            $table = (new CourtDecisionDocument)->getTable();

            // Insert documents
            $result = $this->insertDocumentsInTransaction(
                $docs,
                $data,
                $decisionId,
                $docId,
                $dims,
                $model,
                $provider,
                $driver,
                $table,
                $uploadId
            );

            // Sync to graph database if enabled
            $this->syncToGraphDatabase($result['inserted_ids']);

            Log::info('Court decision ingestion completed', [
                'decision_id' => $decisionId,
                'doc_id' => $docId,
                'inserted' => $result['inserted'],
                'total_chunks' => count($docs),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return [
                'count' => count($docs),
                'inserted' => $result['inserted'],
                'dimensions' => $dims,
                'model' => $model,
            ];

        } catch (EmbeddingException $e) {
            Log::error('Embedding generation failed during court decision ingestion', [
                'decision_id' => $decisionId,
                'doc_id' => $docId,
                'error' => $e->getMessage(),
            ]);

            throw new IngestException(
                'Failed to generate embeddings: '.$e->getMessage(),
                IngestException::EMBEDDING_FAILED,
                $e
            );

        } catch (IngestException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Court decision ingestion failed', [
                'decision_id' => $decisionId,
                'doc_id' => $docId,
                'chunk_count' => count($docs),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new IngestException(
                'Court decision ingestion failed: '.$e->getMessage(),
                IngestException::PIPELINE_FAILED,
                $e
            );
        }
    }

    /**
     * Validate and filter documents
     *
     * @throws IngestException
     */
    protected function validateAndFilterDocs(array $docs): array
    {
        try {
            return array_values(array_filter($docs, fn ($d) => isset($d['content']) && trim((string) $d['content']) !== ''));
        } catch (\Exception $e) {
            throw new IngestException(
                'Failed to validate documents: '.$e->getMessage(),
                IngestException::FILE_PARSING_FAILED,
                $e
            );
        }
    }

    /**
     * Generate embeddings with error handling and retry logic
     *
     * @throws EmbeddingException
     */
    protected function generateEmbeddingsWithRetry(array $inputs, string $model, string $decisionId): array
    {
        $maxRetries = 3;
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxRetries) {
            $attempt++;

            try {
                $startTime = microtime(true);
                $result = $this->openai->embeddings($inputs, $model);
                $duration = microtime(true) - $startTime;

                if ($attempt > 1) {
                    Log::info('Embeddings succeeded after retry', [
                        'decision_id' => $decisionId,
                        'attempt' => $attempt,
                        'input_count' => count($inputs),
                        'duration_seconds' => round($duration, 3),
                    ]);
                }

                return $result;

            } catch (\Throwable $e) {
                $lastException = $e;

                Log::warning('Embeddings call failed', [
                    'decision_id' => $decisionId,
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries,
                    'error' => $e->getMessage(),
                ]);

                if ($attempt < $maxRetries) {
                    $delay = 1000 * pow(2, $attempt - 1);
                    usleep($delay * 1000);
                }
            }
        }

        Log::error('Embeddings failed after all retries', [
            'decision_id' => $decisionId,
            'total_attempts' => $attempt,
            'error' => $lastException->getMessage(),
        ]);

        throw new EmbeddingException(
            "Failed to generate embeddings after {$maxRetries} attempts",
            EmbeddingException::API_CONNECTION_FAILED,
            $lastException
        );
    }

    /**
     * Insert documents in database transaction
     *
     * @throws IngestException
     */
    protected function insertDocumentsInTransaction(
        array $docs,
        array $data,
        string $decisionId,
        string $docId,
        ?int $dims,
        string $model,
        string $provider,
        string $driver,
        string $table,
        $uploadId
    ): array {
        try {
            $inserted = 0;
            $insertedIds = [];

            DB::transaction(function () use ($docs, $data, $decisionId, $docId, $dims, $model, $provider, $driver, $table, $uploadId, &$inserted, &$insertedIds) {
                $now = now();

                foreach ($docs as $i => $doc) {
                    $content = (string) $doc['content'];
                    $vec = $data[$i]['embedding'] ?? null;

                    if (! is_array($vec)) {
                        continue;
                    }

                    $hash = hash('sha256', $content);

                    // Skip if already exists
                    $exists = DB::table($table)
                        ->where('decision_id', $decisionId)
                        ->where('content_hash', $hash)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    $decisionDocId = (string) Str::ulid();
                    $payload = [
                        'id' => $decisionDocId,
                        'decision_id' => $decisionId,
                        'doc_id' => $docId,
                        'upload_id' => $uploadId,
                        'content' => $content,
                        'metadata' => isset($doc['metadata']) ? json_encode($doc['metadata'], JSON_UNESCAPED_UNICODE) : null,
                        'source' => $doc['source'] ?? null,
                        'source_id' => $doc['source_id'] ?? null,
                        'chunk_index' => (int) ($doc['chunk_index'] ?? 0),
                        'embedding_provider' => $provider,
                        'embedding_model' => $model,
                        'embedding_dimensions' => $dims ?? count($vec),
                        'embedding_norm' => $this->norm($vec),
                        'content_hash' => $hash,
                        'token_count' => $this->estimateTokens($content),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    if ($driver === 'pgsql') {
                        $payload['embedding'] = DB::raw($this->toPgVectorCastLiteral($vec));
                    } else {
                        $payload['embedding'] = json_encode($vec);
                    }

                    DB::table($table)->insert($payload);
                    $inserted++;
                    $insertedIds[] = $decisionDocId;
                }
            });

            return [
                'inserted' => $inserted,
                'inserted_ids' => $insertedIds,
            ];

        } catch (\Exception $e) {
            throw new IngestException(
                'Failed to insert documents: '.$e->getMessage(),
                IngestException::STORAGE_FAILED,
                $e
            );
        }
    }

    /**
     * Sync inserted court decisions to graph database
     */
    protected function syncToGraphDatabase(array $insertedIds): void
    {
        if (! $this->graphRag || ! config('neo4j.sync.auto_sync', true) || ! config('neo4j.sync.enabled', true)) {
            return;
        }

        foreach ($insertedIds as $decisionDocId) {
            try {
                if (method_exists($this->graphRag, 'syncCourtDecision')) {
                    $this->graphRag->syncCourtDecision($decisionDocId);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to sync court decision to graph database', [
                    'decision_doc_id' => $decisionDocId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Upsert embeddings into the vector store
     *
     * @param  array  $vectors  Array of vectors with id, vector, and metadata
     * @param  array  $options  Additional options
     * @return array Result summary
     *
     * @throws VectorStoreException if upsert fails
     */
    public function upsert(array $vectors, array $options = []): array
    {
        try {
            Log::info('Court decision vector upsert initiated', [
                'vector_count' => count($vectors),
                'options' => $options,
            ]);

            $startTime = microtime(true);

            if (empty($vectors)) {
                Log::warning('No vectors to upsert');

                return ['count' => 0, 'inserted' => 0, 'updated' => 0];
            }

            $driver = DB::connection()->getDriverName();
            $table = (new CourtDecisionDocument)->getTable();

            $result = $this->executeUpsertTransaction($vectors, $driver, $table);

            Log::info('Court decision vector upsert completed', [
                'total' => count($vectors),
                'inserted' => $result['inserted'],
                'updated' => $result['updated'],
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return [
                'count' => count($vectors),
                'inserted' => $result['inserted'],
                'updated' => $result['updated'],
            ];

        } catch (VectorStoreException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Court decision vector upsert failed', [
                'vector_count' => count($vectors),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new VectorStoreException(
                'Vector upsert failed: '.$e->getMessage(),
                VectorStoreException::BATCH_OPERATION_FAILED,
                $e
            );
        }
    }

    /**
     * Execute upsert transaction
     *
     * @throws VectorStoreException
     */
    protected function executeUpsertTransaction(array $vectors, string $driver, string $table): array
    {
        try {
            $inserted = 0;
            $updated = 0;

            DB::transaction(function () use ($vectors, $driver, $table, &$inserted, &$updated) {
                $now = now();

                foreach ($vectors as $item) {
                    if (! isset($item['id'], $item['vector'], $item['metadata'])) {
                        Log::warning('Invalid vector format in upsert', [
                            'keys' => array_keys($item),
                        ]);

                        continue;
                    }

                    $id = $item['id'];
                    $vector = $item['vector'];
                    $metadata = $item['metadata'];

                    // Extract metadata fields
                    $source = $metadata['source'] ?? 'unknown';
                    $jobId = $metadata['job_id'] ?? null;
                    $driveFileId = $metadata['drive_file_id'] ?? null;
                    $driveFileName = $metadata['drive_file_name'] ?? null;
                    $caseId = $metadata['case_id'] ?? null;
                    $textLength = $metadata['text_length'] ?? 0;
                    $manuallyEdited = $metadata['manually_edited'] ?? false;

                    $contentHash = hash('sha256', $id);

                    // Check if record exists
                    $exists = DB::table($table)
                        ->where('id', $id)
                        ->exists();

                    if ($exists) {
                        // Update existing record
                        $updatePayload = [
                            'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
                            'updated_at' => $now,
                        ];

                        if ($driver === 'pgsql') {
                            $updatePayload['embedding'] = DB::raw($this->toPgVectorCastLiteral($vector));
                        } else {
                            $updatePayload['embedding'] = json_encode($vector);
                        }

                        DB::table($table)
                            ->where('id', $id)
                            ->update($updatePayload);

                        $updated++;
                    } else {
                        // Insert new record
                        $insertPayload = [
                            'id' => $id,
                            'decision_id' => $jobId,
                            'doc_id' => $driveFileId ?? $id,
                            'upload_id' => null,
                            'content' => '',
                            'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
                            'source' => $source,
                            'source_id' => $jobId,
                            'chunk_index' => 0,
                            'embedding_provider' => 'openai',
                            'embedding_model' => config('openai.models.embeddings', 'text-embedding-3-small'),
                            'embedding_dimensions' => count($vector),
                            'embedding_norm' => $this->norm($vector),
                            'content_hash' => $contentHash,
                            'token_count' => $this->estimateTokens($driveFileName ?? ''),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];

                        if ($driver === 'pgsql') {
                            $insertPayload['embedding'] = DB::raw($this->toPgVectorCastLiteral($vector));
                        } else {
                            $insertPayload['embedding'] = json_encode($vector);
                        }

                        DB::table($table)->insert($insertPayload);
                        $inserted++;
                    }
                }
            });

            return [
                'inserted' => $inserted,
                'updated' => $updated,
            ];

        } catch (\Exception $e) {
            throw new VectorStoreException(
                'Upsert transaction failed: '.$e->getMessage(),
                VectorStoreException::BATCH_OPERATION_FAILED,
                $e
            );
        }
    }

    /**
     * Estimate token count
     */
    protected function estimateTokens(string $s): int
    {
        try {
            return (int) ceil(strlen($s) / 4);
        } catch (\Exception $e) {
            Log::debug('Failed to estimate tokens', ['error' => $e->getMessage()]);

            return 0;
        }
    }

    /**
     * Calculate vector norm
     */
    protected function norm(array $vec): float
    {
        try {
            if (empty($vec)) {
                return 0.0;
            }

            $sum = 0.0;
            foreach ($vec as $v) {
                if (! is_numeric($v)) {
                    Log::debug('Non-numeric value in vector norm calculation', ['value' => $v]);

                    continue;
                }
                $sum += ($v * $v);
            }

            return sqrt($sum);

        } catch (\Exception $e) {
            Log::warning('Failed to calculate vector norm', [
                'error' => $e->getMessage(),
                'vector_length' => count($vec),
            ]);

            return 0.0;
        }
    }

    /**
     * Convert vector to pgvector literal format
     */
    protected function toPgVectorLiteral(array $vec): string
    {
        try {
            if (empty($vec)) {
                return '[]';
            }

            $parts = [];
            foreach ($vec as $v) {
                if (! is_numeric($v)) {
                    Log::debug('Non-numeric value in pgvector literal', ['value' => $v]);
                    $v = 0.0;
                }

                $parts[] = rtrim(rtrim(number_format((float) $v, 8, '.', ''), '0'), '.');
            }

            return '['.implode(',', $parts).']';

        } catch (\Exception $e) {
            Log::error('Failed to convert vector to pgvector literal', [
                'error' => $e->getMessage(),
                'vector_length' => count($vec),
            ]);

            return '[]';
        }
    }

    /**
     * Convert vector to pgvector cast literal
     */
    protected function toPgVectorCastLiteral(array $vec): string
    {
        try {
            return "'".$this->toPgVectorLiteral($vec)."'::vector";
        } catch (\Exception $e) {
            Log::error('Failed to convert vector to pgvector cast literal', [
                'error' => $e->getMessage(),
            ]);

            return "'[]'::vector";
        }
    }

    /**
     * Delete documents from vector store
     *
     * @param  string  $docId  Document identifier to delete
     * @return bool True if deleted successfully, false if not found
     */
    public function delete(string $docId): bool
    {
        try {
            $deleted = CourtDecisionDocument::where('decision_id', $docId)->delete();

            return $deleted > 0;
        } catch (\Exception $e) {
            Log::error('Failed to delete court decision documents', [
                'doc_id' => $docId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Check if document exists in vector store
     *
     * @param  string  $docId  Document identifier to check
     * @return bool True if document exists, false otherwise
     */
    public function exists(string $docId): bool
    {
        try {
            return CourtDecisionDocument::where('decision_id', $docId)->exists();
        } catch (\Exception $e) {
            Log::error('Failed to check if court decision exists', [
                'doc_id' => $docId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get embedding dimensions for this vector store
     *
     * @return int Embedding dimension count
     */
    public function getEmbeddingDimensions(): int
    {
        return 1536; // text-embedding-3-small default
    }

    /**
     * Get the embedding vector for a specific document.
     *
     * @param  string  $documentId  The document identifier
     * @return array|null The embedding vector or null if not found
     */
    public function getEmbedding(string $documentId): ?array
    {
        try {
            $decision = CourtDecisionDocument::where('id', $documentId)
                ->whereNotNull('embedding')
                ->first();

            if (! $decision || ! $decision->embedding) {
                Log::debug('No embedding found for court decision document', [
                    'document_id' => $documentId,
                ]);

                return null;
            }

            // The embedding column is already a vector type in PostgreSQL
            // Laravel will return it as an array
            if (is_array($decision->embedding)) {
                return $decision->embedding;
            }

            // Fallback: if it's JSON string, decode it
            if (is_string($decision->embedding)) {
                $embedding = json_decode($decision->embedding, true);

                return is_array($embedding) ? $embedding : null;
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Failed to get embedding for court decision document', [
                'document_id' => $documentId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Find similar documents using pgvector cosine similarity.
     *
     * @param  string  $documentId  The source document ID
     * @param  float  $threshold  Minimum similarity score (0.0-1.0)
     * @param  int  $limit  Maximum number of results
     * @return array Array of ['id' => string, 'similarity_score' => float]
     */
    public function findSimilar(string $documentId, float $threshold = 0.8, int $limit = 20): array
    {
        try {
            Log::debug('Finding similar court decision documents', [
                'source_document_id' => $documentId,
                'threshold' => $threshold,
                'limit' => $limit,
            ]);

            $startTime = microtime(true);

            $source = CourtDecisionDocument::where('id', $documentId)->first();

            if (! $source || ! $source->embedding) {
                Log::debug('Source document not found or has no embedding', [
                    'document_id' => $documentId,
                ]);

                return [];
            }

            $driver = DB::connection()->getDriverName();
            $table = (new CourtDecisionDocument)->getTable();

            // Check if pgvector is available
            if ($driver !== 'pgsql') {
                Log::warning('pgvector similarity search requires PostgreSQL', [
                    'driver' => $driver,
                ]);

                return [];
            }

            // Use pgvector cosine similarity operator (<=>)
            // Note: <=> returns distance, so we use (1 - distance) for similarity
            $results = DB::table($table)
                ->whereNotNull('embedding')
                ->where('id', '!=', $documentId)
                ->selectRaw('id, (1 - (embedding <=> (SELECT embedding FROM '.$table.' WHERE id = ?))) as similarity_score', [$documentId])
                ->havingRaw('(1 - (embedding <=> (SELECT embedding FROM '.$table.' WHERE id = ?))) >= ?', [$documentId, $threshold])
                ->orderByDesc('similarity_score')
                ->limit($limit)
                ->get()
                ->map(fn ($doc) => [
                    'id' => $doc->id,
                    'similarity_score' => (float) $doc->similarity_score,
                ])
                ->toArray();

            $duration = (microtime(true) - $startTime) * 1000;

            Log::info('Similar court decision documents found', [
                'source_document_id' => $documentId,
                'result_count' => count($results),
                'duration_ms' => round($duration, 2),
            ]);

            return $results;

        } catch (\Exception $e) {
            Log::error('Failed to find similar court decision documents', [
                'source_document_id' => $documentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [];
        }
    }
}
