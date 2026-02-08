<?php

namespace App\Services;

use App\Contracts\VectorStore\TextractVectorStoreInterface;
use App\Exceptions\EmbeddingException;
use App\Exceptions\IngestException;
use App\Exceptions\TextractException;
use App\Exceptions\VectorStoreException;
use App\Models\TextractDocument;
use App\Models\TextractJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TextractVectorStoreService implements TextractVectorStoreInterface
{
    public function __construct(
        protected OpenAIService $openai,
        protected ?Graph\GraphRagOrchestrator $graphRag = null
    ) {}

    /**
     * Main entry point: ingest a TextractJob's content into vector store
     *
     * @param  int  $textractJobId  TextractJob ID
     * @param  array  $options  Configuration options
     * @return array Result summary
     *
     * @throws TextractException if job not found or has no content
     * @throws IngestException if ingestion fails
     */
    public function ingestTextractJob(int $textractJobId, array $options = []): array
    {
        try {
            Log::info('Textract job ingestion initiated', [
                'job_id' => $textractJobId,
                'options' => $options,
            ]);

            $startTime = microtime(true);

            // Validate job exists
            $job = TextractJob::find($textractJobId);

            if (! $job) {
                throw new TextractException(
                    "TextractJob {$textractJobId} not found",
                    TextractException::JOB_NOT_FOUND
                );
            }

            // Get effective content (manual if edited, otherwise extracted)
            $content = $job->effective_content;

            if (empty($content)) {
                Log::warning('TextractJob has no content to ingest', ['job_id' => $textractJobId]);

                throw new TextractException(
                    "TextractJob {$textractJobId} has no content available",
                    TextractException::NO_CONTENT
                );
            }

            // Log memory usage before processing
            $this->logMemoryUsage($textractJobId, $content);

            // Update status to processing
            $job->update(['embedding_status' => 'processing']);

            // Delete old chunks for this job before processing to free memory
            TextractDocument::where('textract_job_id', $textractJobId)->delete();

            // Process chunks using generator to avoid loading all into memory
            $result = $this->processChunksWithGenerator($job, $content, $options);

            // Free memory
            unset($content);
            gc_collect_cycles();

            // Mark job as synced
            $job->markEmbeddingSynced();

            // Graph sync is chained from RegenerateTextractEmbeddings job (single source of truth).
            // Do NOT dispatch or call graph sync here. See Task 3.1.

            Log::info('Textract job ingestion completed', [
                'job_id' => $textractJobId,
                'inserted' => $result['inserted'] ?? 0,
                'total_chunks' => $result['count'] ?? 0,
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $result;

        } catch (TextractException $e) {
            if ($job ?? null) {
                $job->update([
                    'embedding_status' => 'failed',
                    'error' => 'Embedding failed: '.$e->getMessage(),
                ]);
            }

            Log::error('Textract job ingestion failed', [
                'job_id' => $textractJobId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } catch (EmbeddingException $e) {
            if ($job ?? null) {
                $job->update([
                    'embedding_status' => 'failed',
                    'error' => 'Embedding failed: '.$e->getMessage(),
                ]);
            }

            Log::error('Embedding generation failed during textract job ingestion', [
                'job_id' => $textractJobId,
                'error' => $e->getMessage(),
            ]);

            throw new IngestException(
                'Failed to generate embeddings: '.$e->getMessage(),
                IngestException::EMBEDDING_FAILED,
                $e
            );

        } catch (IngestException $e) {
            if ($job ?? null) {
                $job->update([
                    'embedding_status' => 'failed',
                    'error' => 'Embedding failed: '.$e->getMessage(),
                ]);
            }

            throw $e;
        } catch (\Exception $e) {
            if ($job ?? null) {
                $job->update([
                    'embedding_status' => 'failed',
                    'error' => 'Embedding failed: '.$e->getMessage(),
                ]);
            }

            Log::error('Textract job ingestion failed', [
                'job_id' => $textractJobId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new IngestException(
                'Textract job ingestion failed: '.$e->getMessage(),
                IngestException::PIPELINE_FAILED,
                $e
            );
        }
    }

    /**
     * Process chunks using generator pattern for memory efficiency
     *
     * @param  TextractJob  $job  Parent job
     * @param  string  $content  Full text content
     * @param  array  $options  Processing options
     * @return array Result summary
     */
    protected function processChunksWithGenerator(TextractJob $job, string $content, array $options = []): array
    {
        $model = $options['model'] ?? config('openai.models.embeddings', 'text-embedding-3-small');
        $provider = $options['provider'] ?? 'openai';
        $maxRetries = $options['max_retries'] ?? 3;

        // Use smaller batch size for synchronous/localhost execution
        $batchSize = $options['batch_size'] ?? 20; // Reduced from 100 to 20

        $inserted = 0;
        $failed = 0;
        $totalChunks = 0;
        $dims = null;

        // Collect chunks in small batches
        $batch = [];
        $batchIndex = 0;

        foreach ($this->chunkTextGenerator($content, $options) as $chunk) {
            $batch[] = $chunk;
            $totalChunks++;

            // Process when batch is full
            if (count($batch) >= $batchSize) {
                $result = $this->processBatch($job, $batch, $model, $provider, $maxRetries, $batchIndex);
                $inserted += $result['inserted'];
                $failed += $result['failed'];
                $dims = $result['dims'] ?? $dims;

                // Clear batch and force garbage collection
                $batch = [];
                gc_collect_cycles();
                $batchIndex++;

                // Log progress
                Log::debug('TextractVectorStoreService: Batch processed', [
                    'batch_index' => $batchIndex,
                    'inserted' => $inserted,
                    'total_chunks' => $totalChunks,
                    'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                ]);
            }
        }

        // Process remaining chunks
        if (! empty($batch)) {
            $result = $this->processBatch($job, $batch, $model, $provider, $maxRetries, $batchIndex);
            $inserted += $result['inserted'];
            $failed += $result['failed'];
            $dims = $result['dims'] ?? $dims;

            // Clear and free memory
            unset($batch);
            gc_collect_cycles();
        }

        Log::info('Text chunking and embedding complete', [
            'total_chunks' => $totalChunks,
            'inserted' => $inserted,
            'failed' => $failed,
        ]);

        return [
            'count' => $totalChunks,
            'inserted' => $inserted,
            'failed' => $failed,
            'dimensions' => $dims ?? 0,
            'model' => $model,
            'provider' => $provider,
        ];
    }

    /**
     * Process a batch of chunks
     *
     * @param  TextractJob  $job  Parent job
     * @param  array  $batch  Chunks to process
     * @param  string  $model  Model name
     * @param  string  $provider  Provider name
     * @param  int  $maxRetries  Max retry attempts
     * @param  int  $batchIndex  Batch index for logging
     * @return array Processing result
     */
    protected function processBatch(TextractJob $job, array $batch, string $model, string $provider, int $maxRetries, int $batchIndex): array
    {
        $inserted = 0;
        $failed = 0;
        $dims = null;

        $inputs = array_map(fn ($chunk) => $chunk['content'], $batch);

        // Generate embeddings with retry logic
        $embeddings = $this->generateEmbeddingWithRetry($inputs, $model, $maxRetries);

        if (! $embeddings) {
            Log::error('Failed to generate embeddings for batch', [
                'batch_index' => $batchIndex,
                'batch_size' => count($batch),
            ]);

            return ['inserted' => 0, 'failed' => count($batch), 'dims' => null];
        }

        $data = $embeddings['data'] ?? [];
        $dims = isset($data[0]['embedding']) ? count($data[0]['embedding']) : null;

        // Create TextractDocument records
        DB::transaction(function () use ($job, $batch, $data, $dims, $model, $provider, &$inserted) {
            foreach ($batch as $i => $chunk) {
                $vec = $data[$i]['embedding'] ?? null;

                if (! is_array($vec)) {
                    Log::warning('Missing embedding for chunk', ['chunk_index' => $chunk['chunk_index']]);

                    continue;
                }

                TextractDocument::create([
                    'textract_job_id' => $job->id,
                    'case_id' => $job->case_id,
                    'content' => $chunk['content'],
                    'chunk_index' => $chunk['chunk_index'],
                    'chunk_overlap' => $chunk['chunk_overlap'],
                    'embedding' => $vec,
                    'embedding_provider' => $provider,
                    'embedding_model' => $model,
                    'embedding_dimensions' => $dims ?? count($vec),
                    'token_count' => $this->estimateTokens($chunk['content']),
                    'processing_status' => 'completed',
                    'embedded_at' => now(),
                    'metadata' => $chunk['metadata'] ?? [],
                ]);

                $inserted++;
            }
        });

        // Small delay to avoid rate limits
        usleep(100000); // 100ms

        return ['inserted' => $inserted, 'failed' => $failed, 'dims' => $dims];
    }

    /**
     * Generate chunks using generator pattern (memory-efficient)
     *
     * @param  string  $content  Full text content
     * @param  array  $options  Chunking options
     * @return \Generator Yields chunks one at a time
     */
    protected function chunkTextGenerator(string $content, array $options = []): \Generator
    {
        $targetSize = $options['chunk_size'] ?? 1000;
        $overlap = $options['chunk_overlap'] ?? 200;

        $position = 0;
        $contentLength = mb_strlen($content);
        $chunkIndex = 0;

        while ($position < $contentLength) {
            // Extract chunk
            $chunkText = mb_substr($content, $position, $targetSize);

            // Try to break at sentence boundary if not at end
            if ($position + $targetSize < $contentLength) {
                // Look for sentence endings: . ! ? followed by space or newline
                // The 's' (DOTALL) flag makes . match newlines, so we find the
                // LAST sentence boundary in the full window, not just the first line.
                if (preg_match('/(.+[.!?])\s/su', $chunkText, $matches)) {
                    $chunkText = $matches[1];
                } elseif (preg_match('/(.+)\n/su', $chunkText, $matches)) {
                    // Fallback to paragraph break
                    $chunkText = $matches[1];
                }
            }

            $actualSize = mb_strlen($chunkText);

            yield [
                'content' => $chunkText,
                'chunk_index' => $chunkIndex,
                'chunk_overlap' => $chunkIndex > 0 ? $overlap : 0,
                'metadata' => [
                    'position_start' => $position,
                    'position_end' => $position + $actualSize,
                    'size' => $actualSize,
                ],
            ];

            // Move position forward, accounting for overlap.
            // Only apply overlap when the chunk is large enough to warrant it;
            // otherwise advance by the full chunk size to prevent crawling.
            $effectiveOverlap = ($chunkIndex > 0 && $actualSize > $overlap) ? $overlap : 0;
            $position += max($actualSize - $effectiveOverlap, 1);
            $chunkIndex++;

            // Safety check to prevent infinite loops
            if ($actualSize == 0) {
                break;
            }
        }
    }

    /**
     * Split content into chunks of approximately targetSize characters
     *
     * @param  string  $content  Full text content
     * @param  array  $options  Chunking options
     * @return array Array of chunks with metadata
     */
    public function chunkText(string $content, array $options = []): array
    {
        $targetSize = $options['chunk_size'] ?? 1000;
        $overlap = $options['chunk_overlap'] ?? 200;

        $chunks = [];
        $position = 0;
        $contentLength = mb_strlen($content);
        $chunkIndex = 0;

        while ($position < $contentLength) {
            // Extract chunk
            $chunkText = mb_substr($content, $position, $targetSize);

            // Try to break at sentence boundary if not at end
            if ($position + $targetSize < $contentLength) {
                // Look for sentence endings: . ! ? followed by space or newline
                // The 's' (DOTALL) flag makes . match newlines, so we find the
                // LAST sentence boundary in the full window, not just the first line.
                if (preg_match('/(.+[.!?])\s/su', $chunkText, $matches)) {
                    $chunkText = $matches[1];
                } elseif (preg_match('/(.+)\n/su', $chunkText, $matches)) {
                    // Fallback to paragraph break
                    $chunkText = $matches[1];
                }
            }

            $actualSize = mb_strlen($chunkText);

            $chunks[] = [
                'content' => $chunkText,
                'chunk_index' => $chunkIndex,
                'chunk_overlap' => $chunkIndex > 0 ? $overlap : 0,
                'metadata' => [
                    'position_start' => $position,
                    'position_end' => $position + $actualSize,
                    'size' => $actualSize,
                ],
            ];

            // Move position forward, accounting for overlap.
            // Only apply overlap when the chunk is large enough to warrant it;
            // otherwise advance by the full chunk size to prevent crawling.
            $effectiveOverlap = ($chunkIndex > 0 && $actualSize > $overlap) ? $overlap : 0;
            $position += max($actualSize - $effectiveOverlap, 1);
            $chunkIndex++;

            // Safety check to prevent infinite loops
            if ($actualSize == 0) {
                break;
            }
        }

        Log::info('Text chunked successfully', [
            'total_length' => $contentLength,
            'chunk_count' => count($chunks),
            'target_size' => $targetSize,
            'overlap' => $overlap,
        ]);

        return $chunks;
    }

    /**
     * Generate embeddings for chunks with retry logic
     *
     * @param  TextractJob  $job  Parent job
     * @param  array  $chunks  Text chunks to embed
     * @param  array  $options  Options
     * @return array Result summary
     */
    protected function generateEmbeddingsForChunks(TextractJob $job, array $chunks, array $options = []): array
    {
        $model = $options['model'] ?? config('openai.models.embeddings', 'text-embedding-3-small');
        $provider = $options['provider'] ?? 'openai';
        $maxRetries = $options['max_retries'] ?? 3;

        $inserted = 0;
        $failed = 0;
        $dims = null;

        // Process chunks in batches to avoid rate limits and memory issues
        $batchSize = $options['batch_size'] ?? 20; // Reduced from 100 to 20
        $batches = array_chunk($chunks, $batchSize);

        foreach ($batches as $batchIndex => $batch) {
            $inputs = array_map(fn ($chunk) => $chunk['content'], $batch);

            // Generate embeddings with retry logic
            $embeddings = $this->generateEmbeddingWithRetry($inputs, $model, $maxRetries);

            if (! $embeddings) {
                Log::error('Failed to generate embeddings for batch', [
                    'batch_index' => $batchIndex,
                    'batch_size' => count($batch),
                ]);
                $failed += count($batch);

                continue;
            }

            $data = $embeddings['data'] ?? [];
            $dims = isset($data[0]['embedding']) ? count($data[0]['embedding']) : null;

            // Create TextractDocument records
            DB::transaction(function () use ($job, $batch, $data, $dims, $model, $provider, &$inserted) {
                foreach ($batch as $i => $chunk) {
                    $vec = $data[$i]['embedding'] ?? null;

                    if (! is_array($vec)) {
                        Log::warning('Missing embedding for chunk', ['chunk_index' => $chunk['chunk_index']]);

                        continue;
                    }

                    TextractDocument::create([
                        'textract_job_id' => $job->id,
                        'case_id' => $job->case_id,
                        'content' => $chunk['content'],
                        'chunk_index' => $chunk['chunk_index'],
                        'chunk_overlap' => $chunk['chunk_overlap'],
                        'embedding' => $vec,
                        'embedding_provider' => $provider,
                        'embedding_model' => $model,
                        'embedding_dimensions' => $dims ?? count($vec),
                        'token_count' => $this->estimateTokens($chunk['content']),
                        'processing_status' => 'completed',
                        'embedded_at' => now(),
                        'metadata' => $chunk['metadata'] ?? [],
                    ]);

                    $inserted++;
                }
            });

            // Free memory after each batch
            unset($inputs, $embeddings, $data);
            gc_collect_cycles();

            // Add delay between batches to avoid rate limits
            if ($batchIndex < count($batches) - 1) {
                usleep(100000); // 100ms delay
            }

            // Log progress every 5 batches
            if (($batchIndex + 1) % 5 === 0) {
                Log::debug('TextractVectorStoreService: Progress update', [
                    'batch_index' => $batchIndex + 1,
                    'total_batches' => count($batches),
                    'inserted' => $inserted,
                    'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                ]);
            }
        }

        // Free chunks array
        unset($chunks, $batches);
        gc_collect_cycles();

        return [
            'count' => $inserted + $failed,
            'inserted' => $inserted,
            'failed' => $failed,
            'dimensions' => $dims ?? 0,
            'model' => $model,
            'provider' => $provider,
        ];
    }

    /**
     * Generate embedding with exponential backoff retry logic
     *
     * @param  string|array  $input  Text(s) to embed
     * @param  string  $model  Model name
     * @param  int  $maxRetries  Maximum retry attempts
     * @return array|null Embedding response or null on failure
     */
    public function generateEmbeddingWithRetry(string|array $input, string $model, int $maxRetries = 3): ?array
    {
        $attempt = 0;
        $lastError = null;

        while ($attempt < $maxRetries) {
            try {
                return $this->openai->embeddings($input, $model);
            } catch (\Exception $e) {
                $lastError = $e;
                $attempt++;

                // Check if it's a rate limit error
                $isRateLimit = str_contains($e->getMessage(), 'rate_limit') ||
                              str_contains($e->getMessage(), '429') ||
                              str_contains($e->getMessage(), 'Too Many Requests');

                if ($attempt < $maxRetries && $isRateLimit) {
                    // Exponential backoff: 1s, 2s, 4s
                    $delay = pow(2, $attempt - 1);
                    Log::warning('Rate limit hit, retrying after delay', [
                        'attempt' => $attempt,
                        'delay_seconds' => $delay,
                        'error' => $e->getMessage(),
                    ]);
                    sleep($delay);
                } else {
                    // Non-rate-limit error or max retries reached
                    break;
                }
            }
        }

        Log::error('Failed to generate embeddings after retries', [
            'attempts' => $attempt,
            'last_error' => $lastError?->getMessage(),
        ]);

        return null;
    }

    /**
     * Estimate token count (rough approximation)
     *
     * @param  string  $text  Input text
     * @return int Estimated tokens
     */
    protected function estimateTokens(string $text): int
    {
        return (int) ceil(mb_strlen($text) / 4);
    }

    /**
     * Search similar documents by query text using pgvector
     *
     * Uses pgvector's <=> cosine distance operator for efficient similarity search.
     * PostgreSQL with pgvector extension is REQUIRED.
     *
     * @param  string  $query  Search query
     * @param  array  $options  Search options (limit, case_id, threshold, model)
     * @return array Similar documents with scores
     *
     * @throws \RuntimeException if pgvector extension is not available
     * @throws VectorStoreException if search fails
     */
    public function searchSimilar(string $query, array $options = []): array
    {
        try {
            Log::info('Textract similarity search initiated', [
                'query' => substr($query, 0, 100),
                'options' => $options,
            ]);

            $startTime = microtime(true);

            // Verify PostgreSQL with pgvector is available (required)
            $driver = DB::connection()->getDriverName();
            if ($driver !== 'pgsql') {
                throw new \RuntimeException(
                    'pgvector extension is required for similarity search. PostgreSQL is required but current driver is: '.$driver
                );
            }

            if (! $this->isPgVectorAvailable()) {
                throw new \RuntimeException(
                    'pgvector extension is required for similarity search. Please install the pgvector extension in PostgreSQL.'
                );
            }

            $limit = $options['limit'] ?? 10;
            $caseId = $options['case_id'] ?? null;
            $threshold = $options['threshold'] ?? 0.7;

            // Generate query embedding
            $model = $options['model'] ?? config('openai.models.embeddings', 'text-embedding-3-small');
            $queryVector = $this->generateQueryEmbedding($query, $model);

            if (! $queryVector) {
                Log::warning('Failed to generate query embedding', ['query' => substr($query, 0, 100)]);

                return [];
            }

            // Use pgvector's native <=> cosine distance operator
            $results = $this->searchWithPgVector($queryVector, $limit, $threshold, $caseId);

            Log::info('Textract similarity search completed', [
                'result_count' => count($results),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'method' => 'pgvector',
            ]);

            return $results;

        } catch (\RuntimeException $e) {
            // Re-throw RuntimeException for pgvector requirement
            throw $e;
        } catch (VectorStoreException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Textract similarity search failed', [
                'query' => substr($query, 0, 100),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new VectorStoreException(
                'Textract similarity search failed: '.$e->getMessage(),
                VectorStoreException::QUERY_FAILED,
                $e
            );
        }
    }

    /**
     * Check if pgvector extension is available for the textract_documents table
     *
     * @return bool True if pgvector is available
     */
    protected function isPgVectorAvailable(): bool
    {
        try {
            $result = DB::select("SELECT 1 FROM pg_extension WHERE extname = 'vector' LIMIT 1");

            return ! empty($result);
        } catch (\Exception $e) {
            Log::debug('pgvector availability check failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Search using pgvector's native cosine distance operator
     *
     * Uses the <=> operator which computes cosine distance.
     * Similarity = 1 - distance, so we filter where distance <= (1 - threshold)
     *
     * The embedding column may be stored as JSON or native vector type.
     * We use embedding::text::vector to handle both cases (JSON -> text -> vector).
     *
     * @param  array  $queryVector  The query embedding vector
     * @param  int  $limit  Maximum number of results
     * @param  float  $threshold  Minimum similarity threshold (0.0 to 1.0)
     * @param  string|int|null  $caseId  Optional case ID to filter by (ULID or integer)
     * @return array Search results with similarity scores
     */
    protected function searchWithPgVector(array $queryVector, int $limit, float $threshold, string|int|null $caseId): array
    {
        // Convert vector to pgvector literal format: [0.1,0.2,0.3,...]
        $vectorString = $this->toPgVectorLiteral($queryVector);

        // pgvector distance = 1 - cosine_similarity
        // So for threshold (min similarity), we need distance <= (1 - threshold)
        $maxDistance = 1 - $threshold;

        // Determine the column type and build appropriate cast expression
        // For JSON columns: embedding::text::vector (JSON -> text -> vector)
        // For native vector columns: embedding (no cast needed)
        $embeddingExpr = $this->getEmbeddingCastExpression();

        $query = DB::table('textract_documents')
            ->select([
                'id',
                'textract_job_id',
                'case_id',
                'content',
                'chunk_index',
                'embedding_provider',
                'embedding_model',
                'metadata',
            ])
            ->selectRaw("(1 - ({$embeddingExpr} <=> ?::vector)) as similarity", [$vectorString])
            ->whereNotNull('embedding')
            ->where('processing_status', 'completed');

        if ($caseId !== null) {
            $query->where('case_id', $caseId);
        }

        // Filter by max distance (which corresponds to min similarity)
        // distance <= maxDistance means similarity >= threshold
        $query->whereRaw("({$embeddingExpr} <=> ?::vector) <= ?", [$vectorString, $maxDistance]);

        return $query
            ->orderByRaw("({$embeddingExpr} <=> ?::vector) ASC", [$vectorString])
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                $doc = TextractDocument::find($row->id);

                return [
                    'document' => $doc,
                    'similarity' => min(1.0, max(0.0, (float) $row->similarity)), // Clamp to [0, 1]
                    'textract_job_id' => $row->textract_job_id,
                ];
            })
            ->toArray();
    }

    /**
     * Get the SQL expression to cast the embedding column to vector type
     *
     * Checks the column type and returns appropriate cast expression:
     * - For JSON/JSONB: embedding::text::vector
     * - For native vector: embedding
     *
     * @return string SQL expression for embedding column
     */
    protected function getEmbeddingCastExpression(): string
    {
        try {
            $columnInfo = DB::select(
                "SELECT data_type FROM information_schema.columns WHERE table_name = 'textract_documents' AND column_name = 'embedding'"
            );

            if (! empty($columnInfo)) {
                $dataType = strtolower($columnInfo[0]->data_type ?? '');

                // JSON/JSONB needs text conversion first
                if (in_array($dataType, ['json', 'jsonb'])) {
                    return 'embedding::text::vector';
                }

                // user-defined type (vector) can be used directly
                if ($dataType === 'user-defined') {
                    return 'embedding';
                }
            }

            // Default to JSON cast (safer fallback)
            return 'embedding::text::vector';

        } catch (\Exception $e) {
            Log::debug('Failed to determine embedding column type', ['error' => $e->getMessage()]);

            return 'embedding::text::vector';
        }
    }

    /**
     * Convert PHP array to pgvector literal format
     *
     * @param  array  $vector  The embedding vector
     * @return string pgvector literal format: [0.1,0.2,0.3]
     */
    protected function toPgVectorLiteral(array $vector): string
    {
        $parts = [];
        foreach ($vector as $v) {
            // Format with precision but remove trailing zeros
            $parts[] = rtrim(rtrim(number_format((float) $v, 8, '.', ''), '0'), '.');
        }

        return '['.implode(',', $parts).']';
    }

    /**
     * Re-generate embeddings for a specific job
     *
     * @param  int  $textractJobId  Job ID
     * @param  array  $options  Options
     * @return array Result
     *
     * @throws TextractException if job not found
     * @throws IngestException if regeneration fails
     */
    public function regenerateEmbeddings(int $textractJobId, array $options = []): array
    {
        try {
            Log::info('Regenerating embeddings for TextractJob', [
                'job_id' => $textractJobId,
                'options' => $options,
            ]);

            $startTime = microtime(true);

            $result = $this->ingestTextractJob($textractJobId, $options);

            Log::info('Embeddings regenerated successfully', [
                'job_id' => $textractJobId,
                'inserted' => $result['inserted'] ?? 0,
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $result;

        } catch (TextractException|IngestException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to regenerate embeddings', [
                'job_id' => $textractJobId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new IngestException(
                'Failed to regenerate embeddings: '.$e->getMessage(),
                IngestException::PIPELINE_FAILED,
                $e
            );
        }
    }

    /**
     * Get embedding statistics for a job
     *
     * @param  int  $textractJobId  Job ID
     * @return array Statistics
     *
     * @throws TextractException if job not found
     */
    public function getEmbeddingStats(int $textractJobId): array
    {
        try {
            Log::debug('Fetching embedding stats', ['job_id' => $textractJobId]);

            $job = TextractJob::find($textractJobId);

            if (! $job) {
                throw new TextractException(
                    "TextractJob {$textractJobId} not found",
                    TextractException::JOB_NOT_FOUND
                );
            }

            $documents = TextractDocument::where('textract_job_id', $textractJobId)->get();

            return [
                'job_id' => $textractJobId,
                'job_status' => $job->status,
                'embedding_status' => $job->embedding_status,
                'total_chunks' => $documents->count(),
                'completed_chunks' => $documents->where('processing_status', 'completed')->count(),
                'failed_chunks' => $documents->where('processing_status', 'failed')->count(),
                'pending_chunks' => $documents->where('processing_status', 'pending')->count(),
                'total_tokens' => $documents->sum('token_count'),
                'embedding_model' => $documents->first()?->embedding_model,
                'embedding_dimensions' => $documents->first()?->embedding_dimensions,
                'last_embedded_at' => $job->embedding_synced_at,
            ];

        } catch (TextractException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to fetch embedding stats', [
                'job_id' => $textractJobId,
                'error' => $e->getMessage(),
            ]);

            throw new TextractException(
                'Failed to fetch embedding stats: '.$e->getMessage(),
                TextractException::UNEXPECTED_ERROR,
                $e
            );
        }
    }

    /**
     * Log memory usage for debugging
     */
    protected function logMemoryUsage(int $jobId, string $content): void
    {
        $memoryBefore = memory_get_usage(true);
        Log::info('TextractVectorStoreService: Memory usage', [
            'job_id' => $jobId,
            'memory_before_mb' => round($memoryBefore / 1024 / 1024, 2),
            'memory_limit' => ini_get('memory_limit'),
            'content_size_mb' => round(strlen($content) / 1024 / 1024, 2),
        ]);
    }

    // syncToGraphDatabase() removed in Task 3.1 (Unified Job Chaining).
    // Graph sync is now the sole responsibility of RegenerateTextractEmbeddings,
    // which dispatches SyncTextractToGraph with a 5s delay after embedding success.

    /**
     * Generate query embedding with error handling
     *
     * @throws EmbeddingException
     */
    protected function generateQueryEmbedding(string $query, string $model): ?array
    {
        try {
            $embeddingResult = $this->openai->embeddings($query, $model);

            return $embeddingResult['data'][0]['embedding'] ?? null;

        } catch (\Exception $e) {
            Log::error('Failed to generate query embedding', [
                'query' => substr($query, 0, 100),
                'error' => $e->getMessage(),
            ]);

            throw new EmbeddingException(
                'Failed to generate query embedding: '.$e->getMessage(),
                EmbeddingException::API_CONNECTION_FAILED,
                $e
            );
        }
    }

}
