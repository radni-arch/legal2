<?php

namespace App\Services;

use App\Contracts\VectorStore\LawVectorStoreInterface;
use App\Exceptions\EmbeddingException;
use App\Exceptions\IngestException;
use App\Exceptions\VectorStoreException;
use App\Models\Law;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LawVectorStoreService implements LawVectorStoreInterface
{
    /**
     * Cache for table existence checks to avoid repeated schema queries
     * Key: table name, Value: ['exists' => bool, 'checked_at' => timestamp]
     */
    protected static array $tableExistsCache = [];

    /**
     * How long to cache table existence results (in seconds)
     */
    protected const TABLE_EXISTS_CACHE_TTL = 60;

    public function __construct(
        protected OpenAIService $openai,
        protected ?Graph\GraphRagOrchestrator $graphRag = null
    ) {}

    /**
     * Ingest pre-chunked law articles into laws table
     *
     * @param  string  $docId  Stable identifier grouping chunks
     * @param  array  $docs  Array of law chunks with content
     * @param  array  $options  Ingestion options
     * @return array Ingestion results
     *
     * @throws IngestException if ingestion fails
     */
    public function ingest(string $docId, array $docs, array $options = []): array
    {
        try {
            Log::info('Law ingestion initiated', [
                'doc_id' => $docId,
                'chunk_count' => count($docs),
                'options' => $options,
            ]);

            $startTime = microtime(true);

            // Validate and filter input
            $docs = $this->validateAndFilterDocs($docs);

            if (empty($docs)) {
                Log::warning('No valid documents to ingest', [
                    'doc_id' => $docId,
                ]);

                return ['count' => 0, 'inserted' => 0];
            }

            $model = $options['model'] ?? config('openai.models.embeddings');
            $provider = $options['provider'] ?? 'openai';
            $baseMeta = (array) ($options['base_meta'] ?? []);
            $ingestedId = $options['ingested_law_id'] ?? null;

            // Generate embeddings
            $inputs = array_map(fn ($d) => (string) $d['content'], $docs);
            $emb = $this->callEmbeddingsWithRetry($inputs, $model, $docId);
            $data = $emb['data'] ?? [];

            if (count($data) !== count($docs)) {
                Log::warning('Embedding count mismatch', [
                    'docs' => count($docs),
                    'embeddings' => count($data),
                    'doc_id' => $docId,
                ]);
            }

            $dims = isset($data[0]['embedding']) ? count($data[0]['embedding']) : null;

            $driver = DB::connection()->getDriverName();
            $table = (new Law)->getTable();

            // Check pgvector availability
            $usePgVector = $this->checkPgVectorAvailability($table, $driver);

            // Insert documents
            $result = $this->insertDocumentsInTransaction(
                $docs,
                $data,
                $docId,
                $dims,
                $model,
                $provider,
                $table,
                $baseMeta,
                $ingestedId,
                $usePgVector
            );

            // Sync to graph database if enabled
            $this->syncToGraphDatabase($result['inserted_ids']);

            Log::info('Law ingestion completed', [
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
            Log::error('Embedding generation failed during law ingestion', [
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
            Log::error('Law ingestion failed', [
                'doc_id' => $docId,
                'chunk_count' => count($docs),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new IngestException(
                'Law ingestion failed: '.$e->getMessage(),
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
     * Check if pgvector is available
     */
    protected function checkPgVectorAvailability(string $table, string $driver): bool
    {
        if ($driver !== 'pgsql') {
            return false;
        }

        try {
            $columns = DB::select("SELECT column_name FROM information_schema.columns WHERE table_name = ? AND column_name = 'embedding'", [$table]);

            return ! empty($columns);
        } catch (\Exception $e) {
            Log::debug('pgvector availability check failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Check if a table exists in the database with caching
     *
     * Uses a static cache to avoid repeated schema queries within the same process.
     * Cache TTL is configurable via TABLE_EXISTS_CACHE_TTL constant.
     */
    protected function tableExists(string $table): bool
    {
        $now = time();

        // Check cache first
        if (isset(self::$tableExistsCache[$table])) {
            $cached = self::$tableExistsCache[$table];
            $age = $now - $cached['checked_at'];

            if ($age < self::TABLE_EXISTS_CACHE_TTL) {
                return $cached['exists'];
            }
        }

        // Cache miss or expired - query the database
        try {
            $exists = \Illuminate\Support\Facades\Schema::hasTable($table);

            // Cache the result
            self::$tableExistsCache[$table] = [
                'exists' => $exists,
                'checked_at' => $now,
            ];

            return $exists;
        } catch (\Exception $e) {
            Log::debug('Table existence check failed', [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);

            // Cache the failure as non-existent
            self::$tableExistsCache[$table] = [
                'exists' => false,
                'checked_at' => $now,
            ];

            return false;
        }
    }

    /**
     * Clear the table existence cache (useful for testing or after migrations)
     */
    public static function clearTableExistsCache(): void
    {
        self::$tableExistsCache = [];
    }

    /**
     * Check if the vector store is available for searches
     * Use this before generating embeddings to avoid wasted API calls
     */
    public function isAvailable(): bool
    {
        $table = (new Law)->getTable();

        return $this->tableExists($table);
    }

    /**
     * Insert documents in database transaction
     *
     * @throws IngestException
     */
    protected function insertDocumentsInTransaction(
        array $docs,
        array $data,
        string $docId,
        ?int $dims,
        string $model,
        string $provider,
        string $table,
        array $baseMeta,
        $ingestedId,
        bool $usePgVector
    ): array {
        try {
            $inserted = 0;
            $insertedIds = [];

            DB::transaction(function () use ($docs, $data, $docId, $dims, $model, $provider, $table, $baseMeta, $ingestedId, $usePgVector, &$inserted, &$insertedIds) {
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
                        ->where('doc_id', $docId)
                        ->where('content_hash', $hash)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    $rowMeta = (array) ($doc['law_meta'] ?? []);

                    $lawId = (string) Str::ulid();
                    $payload = array_merge([
                        'id' => $lawId,
                        'doc_id' => $docId,
                        'ingested_law_id' => $ingestedId,
                        'content' => $content,
                        'metadata' => isset($doc['metadata']) ? json_encode($doc['metadata'], JSON_UNESCAPED_UNICODE) : null,
                        'chunk_index' => (int) ($doc['chunk_index'] ?? 0),
                        'embedding_provider' => $provider,
                        'embedding_model' => $model,
                        'embedding_dimensions' => $dims ?? count($vec),
                        'embedding_norm' => $this->norm($vec),
                        'content_hash' => $hash,
                        'token_count' => $this->estimateTokens($content),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ], $this->mapLawColumns(array_merge($baseMeta, $rowMeta)));

                    // Set appropriate embedding column
                    if ($usePgVector) {
                        $payload['embedding'] = json_encode($vec);
                    } else {
                        $payload['embedding_vector'] = json_encode($vec);
                    }

                    DB::table($table)->insert($payload);
                    $inserted++;
                    $insertedIds[] = $lawId;
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
     * Sync inserted laws to graph database
     */
    protected function syncToGraphDatabase(array $insertedIds): void
    {
        if (! $this->graphRag || ! config('neo4j.sync.auto_sync', true) || ! config('neo4j.sync.enabled', true)) {
            return;
        }

        foreach ($insertedIds as $lawId) {
            try {
                $this->graphRag->syncLaw($lawId);
            } catch (\Exception $e) {
                Log::warning('Failed to sync law to graph database', [
                    'law_id' => $lawId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Map law metadata to database columns
     *
     * @throws IngestException
     */
    protected function mapLawColumns(array $meta): array
    {
        try {
            $map = [];

            // Map scalar text columns
            foreach ([
                'title', 'law_number', 'jurisdiction', 'country', 'language', 'version', 'chapter', 'section', 'source_url',
            ] as $key) {
                if (isset($meta[$key])) {
                    // Ensure value is scalar or can be cast to string
                    if (is_scalar($meta[$key]) || (is_object($meta[$key]) && method_exists($meta[$key], '__toString'))) {
                        $map[$key] = (string) $meta[$key];
                    } else {
                        Log::debug('Skipping non-scalar metadata value', [
                            'key' => $key,
                            'type' => gettype($meta[$key]),
                        ]);
                    }
                }
            }

            // Map date columns
            foreach ([
                'promulgation_date', 'effective_date', 'repeal_date',
            ] as $dateKey) {
                if (! empty($meta[$dateKey])) {
                    $map[$dateKey] = $meta[$dateKey];
                }
            }

            // Ensure tags are always stored as JSON array
            if (isset($meta['tags'])) {
                try {
                    $tags = is_array($meta['tags']) ? $meta['tags'] : [$meta['tags']];
                    $encoded = json_encode(array_values(array_filter($tags)), JSON_UNESCAPED_UNICODE);
                    if ($encoded === false) {
                        throw new \RuntimeException('JSON encoding failed: '.json_last_error_msg());
                    }
                    $map['tags'] = $encoded;
                } catch (\Exception $e) {
                    Log::warning('Failed to encode tags as JSON', [
                        'error' => $e->getMessage(),
                    ]);
                    $map['tags'] = '[]';
                }
            }

            return $map;

        } catch (\Exception $e) {
            Log::error('Failed to map law columns', [
                'error' => $e->getMessage(),
            ]);
            throw new IngestException(
                'Failed to map law columns: '.$e->getMessage(),
                IngestException::FILE_PARSING_FAILED,
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
            if (empty($s)) {
                return 0;
            }

            return (int) ceil(strlen($s) / 4);
        } catch (\Exception $e) {
            Log::debug('Token estimation failed', [
                'error' => $e->getMessage(),
            ]);

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
                    Log::warning('Non-numeric value in vector norm calculation', [
                        'value' => $v,
                        'type' => gettype($v),
                    ]);

                    continue;
                }
                $sum += ((float) $v * (float) $v);
            }

            return sqrt($sum);

        } catch (\Exception $e) {
            Log::warning('Vector norm calculation failed', [
                'error' => $e->getMessage(),
                'vector_length' => count($vec),
            ]);

            return 0.0;
        }
    }

    /**
     * Convert vector to pgvector literal format
     *
     * @throws VectorStoreException
     */
    protected function toPgVectorLiteral(array $vec): string
    {
        try {
            if (empty($vec)) {
                throw new VectorStoreException(
                    'Cannot convert empty vector to pgvector literal',
                    VectorStoreException::QUERY_FAILED
                );
            }

            $parts = [];
            foreach ($vec as $v) {
                if (! is_numeric($v)) {
                    throw new VectorStoreException(
                        'Vector contains non-numeric value',
                        VectorStoreException::QUERY_FAILED
                    );
                }
                $parts[] = rtrim(rtrim(number_format((float) $v, 8, '.', ''), '0'), '.');
            }

            return '['.implode(',', $parts).']';

        } catch (VectorStoreException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to convert vector to pgvector literal', [
                'vector_length' => count($vec),
                'error' => $e->getMessage(),
            ]);
            throw new VectorStoreException(
                'Failed to convert vector to pgvector literal: '.$e->getMessage(),
                VectorStoreException::QUERY_FAILED,
                $e
            );
        }
    }

    /**
     * Convert vector to pgvector cast literal
     */
    protected function toPgVectorCastLiteral(array $vec): string
    {
        return "'".$this->toPgVectorLiteral($vec)."'::vector";
    }

    /**
     * Call embeddings API with exponential backoff and jitter
     *
     * @param  array  $inputs  Array of text inputs to embed
     * @param  string  $model  Embedding model to use
     * @param  string  $docId  Document ID for logging context
     * @param  int  $maxRetries  Maximum number of retry attempts
     * @return array Embeddings response from API
     *
     * @throws EmbeddingException If all retries are exhausted
     */
    protected function callEmbeddingsWithRetry(array $inputs, string $model, string $docId, int $maxRetries = 3): array
    {
        $attempt = 0;
        $lastException = null;
        $inputCount = count($inputs);

        while ($attempt < $maxRetries) {
            $attempt++;

            try {
                $startTime = microtime(true);
                $result = $this->openai->embeddings($inputs, $model);
                $duration = microtime(true) - $startTime;

                if ($attempt > 1) {
                    Log::info('Embeddings call succeeded after retry', [
                        'doc_id' => $docId,
                        'attempt' => $attempt,
                        'input_count' => $inputCount,
                        'model' => $model,
                        'duration_seconds' => round($duration, 3),
                    ]);
                }

                return $result;

            } catch (\Throwable $e) {
                $lastException = $e;

                Log::warning('Embeddings call failed', [
                    'doc_id' => $docId,
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries,
                    'input_count' => $inputCount,
                    'model' => $model,
                    'error' => $e->getMessage(),
                ]);

                // Don't sleep after the last attempt
                if ($attempt < $maxRetries) {
                    $delay = $this->calculateBackoffDelay($attempt);
                    Log::debug('Retrying embeddings call after delay', [
                        'doc_id' => $docId,
                        'delay_ms' => $delay,
                        'next_attempt' => $attempt + 1,
                    ]);
                    usleep($delay * 1000);
                }
            }
        }

        // All retries exhausted
        Log::error('Embeddings call failed after all retries', [
            'doc_id' => $docId,
            'total_attempts' => $attempt,
            'input_count' => $inputCount,
            'model' => $model,
            'error' => $lastException->getMessage(),
        ]);

        throw new EmbeddingException(
            "Failed to generate embeddings after {$maxRetries} attempts for doc_id: {$docId}",
            EmbeddingException::API_CONNECTION_FAILED,
            $lastException
        );
    }

    /**
     * Calculate exponential backoff delay with jitter
     *
     * @param  int  $attempt  Attempt number (1-based)
     * @return int Delay in milliseconds
     */
    protected function calculateBackoffDelay(int $attempt): int
    {
        // Exponential backoff: base_delay * 2^(attempt-1)
        $baseDelay = config('services.embeddings.retry_base_delay', 1000);
        $exponentialDelay = $baseDelay * pow(2, $attempt - 1);

        // Add jitter
        $jitterPercent = config('services.embeddings.retry_jitter_percent', 0.5);
        $jitter = rand(0, (int) ($exponentialDelay * $jitterPercent));

        return (int) ($exponentialDelay + $jitter);
    }

    /**
     * Search laws using vector similarity
     *
     * @param  array  $embedding  The query embedding vector
     * @param  array  $options  Search options
     * @return array Array of search results
     *
     * @throws VectorStoreException if search fails
     */
    public function search(array $embedding, array $options = []): array
    {
        try {
            Log::info('Law vector search initiated', [
                'embedding_dimensions' => count($embedding),
                'options' => $options,
            ]);

            $startTime = microtime(true);

            $threshold = $options['threshold'] ?? 0.7;
            $limit = $options['limit'] ?? 10;
            $filters = $options['filters'] ?? [];

            $driver = DB::connection()->getDriverName();
            $table = (new Law)->getTable();

            // Check if table exists before attempting search
            if (! $this->tableExists($table)) {
                Log::warning('Law vector search skipped: table does not exist', [
                    'table' => $table,
                    'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ]);

                return [];
            }

            // Build query with filters
            $queryBuilder = $this->buildSearchQuery($table, $filters);

            // Check pgvector availability
            $usePgVector = $this->checkPgVectorAvailability($table, $driver);

            // Execute search
            if ($usePgVector) {
                $results = $this->executePgVectorSearch($queryBuilder, $embedding, $threshold, $limit);
            } else {
                $results = $this->executeFallbackSearch($queryBuilder, $embedding, $threshold, $limit);
            }

            // Normalize results
            $formattedResults = $this->formatSearchResults($results);

            Log::info('Law vector search completed', [
                'result_count' => count($formattedResults),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $formattedResults;

        } catch (VectorStoreException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Law vector search failed', [
                'embedding_dimensions' => count($embedding),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new VectorStoreException(
                'Law vector search failed: '.$e->getMessage(),
                VectorStoreException::QUERY_FAILED,
                $e
            );
        }
    }

    /**
     * Build search query with filters
     *
     * @throws VectorStoreException
     */
    protected function buildSearchQuery(string $table, array $filters)
    {
        try {
            if (empty(trim($table))) {
                throw new VectorStoreException(
                    'Table name cannot be empty',
                    VectorStoreException::QUERY_FAILED
                );
            }

            Log::debug('Building search query', [
                'table' => $table,
                'filter_count' => count($filters),
            ]);

            $queryBuilder = DB::table($table)
                ->select([
                    'id',
                    'doc_id',
                    'title',
                    'content',
                    'law_number',
                    'jurisdiction',
                    'country',
                    'language',
                    'chunk_index',
                    'metadata',
                    'effective_date',
                    'promulgation_date',
                ]);

            $appliedFilters = 0;

            // Apply filters with validation
            if (! empty($filters['jurisdiction']) && is_string($filters['jurisdiction'])) {
                $queryBuilder->where('jurisdiction', $filters['jurisdiction']);
                $appliedFilters++;
            }

            if (! empty($filters['law_number']) && is_string($filters['law_number'])) {
                $queryBuilder->where('law_number', 'like', '%'.$filters['law_number'].'%');
                $appliedFilters++;
            }

            if (! empty($filters['country']) && is_string($filters['country'])) {
                $queryBuilder->where('country', $filters['country']);
                $appliedFilters++;
            }

            if (! empty($filters['language']) && is_string($filters['language'])) {
                $queryBuilder->where('language', $filters['language']);
                $appliedFilters++;
            }

            Log::debug('Search query built', [
                'table' => $table,
                'applied_filters' => $appliedFilters,
            ]);

            return $queryBuilder;

        } catch (VectorStoreException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to build search query', [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);
            throw new VectorStoreException(
                'Failed to build search query: '.$e->getMessage(),
                VectorStoreException::QUERY_FAILED,
                $e
            );
        }
    }

    /**
     * Execute pgvector search
     *
     * @throws VectorStoreException
     */
    protected function executePgVectorSearch($queryBuilder, array $embedding, float $threshold, int $limit)
    {
        try {
            $vectorLiteral = $this->toPgVectorLiteral($embedding);
            $queryBuilder->selectRaw("1 - (embedding <=> '{$vectorLiteral}'::vector) as score")
                ->whereRaw("1 - (embedding <=> '{$vectorLiteral}'::vector) >= ?", [$threshold])
                ->orderByRaw("embedding <=> '{$vectorLiteral}'::vector")
                ->limit($limit);

            return $queryBuilder->get();

        } catch (\Exception $e) {
            throw new VectorStoreException(
                'pgvector query failed: '.$e->getMessage(),
                VectorStoreException::QUERY_FAILED,
                $e
            );
        }
    }

    /**
     * Execute fallback JSON-based search
     *
     * @throws VectorStoreException
     */
    protected function executeFallbackSearch($queryBuilder, array $embedding, float $threshold, int $limit)
    {
        try {
            $queryBuilder->whereNotNull('embedding_vector')->limit($limit * 3);
            $results = $queryBuilder->get();

            // Calculate similarity for each result
            $results = $results->map(function ($law) use ($embedding) {
                $lawVector = json_decode($law->embedding_vector ?? '[]', true);
                $law->score = $this->cosineSimilarity($embedding, $lawVector);

                return $law;
            });

            // Filter by threshold and sort
            return $results->filter(fn ($r) => $r->score >= $threshold)
                ->sortByDesc('score')
                ->take($limit)
                ->values();

        } catch (\Exception $e) {
            throw new VectorStoreException(
                'Fallback vector search failed: '.$e->getMessage(),
                VectorStoreException::QUERY_FAILED,
                $e
            );
        }
    }

    /**
     * Format search results to array
     *
     * @throws VectorStoreException
     */
    protected function formatSearchResults($results): array
    {
        try {
            Log::debug('Formatting search results', [
                'result_count' => is_countable($results) ? count($results) : 0,
            ]);

            return $results->map(function ($law) {
                try {
                    // Safely decode metadata JSON
                    $metadata = '{}';
                    if (! empty($law->metadata)) {
                        $metadata = $law->metadata;
                    }

                    $decodedMetadata = json_decode($metadata, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        Log::warning('Failed to decode law metadata JSON', [
                            'law_id' => $law->id ?? 'unknown',
                            'json_error' => json_last_error_msg(),
                        ]);
                        $decodedMetadata = [];
                    }

                    return [
                        'id' => $law->id,
                        'score' => $law->score ?? 0.0,
                        'content' => $law->content,
                        'metadata' => $decodedMetadata,
                        'doc_id' => $law->doc_id,
                        'title' => $law->title,
                        'law_number' => $law->law_number,
                        'jurisdiction' => $law->jurisdiction,
                        'country' => $law->country,
                        'language' => $law->language,
                        'chunk_index' => $law->chunk_index,
                        'effective_date' => $law->effective_date,
                        'promulgation_date' => $law->promulgation_date,
                    ];
                } catch (\Exception $e) {
                    Log::warning('Failed to format individual law result', [
                        'law_id' => $law->id ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);

                    // Return minimal result on error
                    return [
                        'id' => $law->id ?? null,
                        'score' => 0.0,
                        'content' => $law->content ?? '',
                        'metadata' => [],
                        'error' => 'Formatting failed',
                    ];
                }
            })->toArray();

        } catch (\Exception $e) {
            Log::error('Failed to format search results', [
                'error' => $e->getMessage(),
            ]);
            throw new VectorStoreException(
                'Failed to format search results: '.$e->getMessage(),
                VectorStoreException::QUERY_FAILED,
                $e
            );
        }
    }

    /**
     * Calculate cosine similarity between two vectors
     *
     * @param  array  $vec1  First vector
     * @param  array  $vec2  Second vector
     * @return float Similarity score between 0 and 1
     */
    protected function cosineSimilarity(array $vec1, array $vec2): float
    {
        try {
            // Validate inputs
            if (empty($vec1) || empty($vec2)) {
                Log::debug('Empty vector in cosine similarity calculation');

                return 0.0;
            }

            if (count($vec1) !== count($vec2)) {
                Log::warning('Vector dimension mismatch in cosine similarity', [
                    'vec1_length' => count($vec1),
                    'vec2_length' => count($vec2),
                ]);

                return 0.0;
            }

            $dotProduct = 0.0;
            $norm1 = 0.0;
            $norm2 = 0.0;

            for ($i = 0; $i < count($vec1); $i++) {
                if (! is_numeric($vec1[$i]) || ! is_numeric($vec2[$i])) {
                    Log::warning('Non-numeric value in cosine similarity calculation', [
                        'index' => $i,
                        'vec1_type' => gettype($vec1[$i]),
                        'vec2_type' => gettype($vec2[$i]),
                    ]);

                    continue;
                }

                $v1 = (float) $vec1[$i];
                $v2 = (float) $vec2[$i];

                $dotProduct += $v1 * $v2;
                $norm1 += $v1 * $v1;
                $norm2 += $v2 * $v2;
            }

            $norm1 = sqrt($norm1);
            $norm2 = sqrt($norm2);

            if ($norm1 == 0 || $norm2 == 0) {
                Log::debug('Zero norm in cosine similarity calculation');

                return 0.0;
            }

            $similarity = $dotProduct / ($norm1 * $norm2);

            // Clamp to [0, 1] range (should already be in [-1, 1] but ensure non-negative)
            return max(0.0, min(1.0, $similarity));

        } catch (\Exception $e) {
            Log::error('Cosine similarity calculation failed', [
                'vec1_length' => count($vec1),
                'vec2_length' => count($vec2),
                'error' => $e->getMessage(),
            ]);

            return 0.0;
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
            $deleted = Law::where('doc_id', $docId)->delete();

            return $deleted > 0;
        } catch (\Exception $e) {
            Log::error('Failed to delete law documents', [
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
            return Law::where('doc_id', $docId)->exists();
        } catch (\Exception $e) {
            Log::error('Failed to check if law exists', [
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
            // Check if 'embedding' column exists (pgvector) or use 'embedding_vector' (JSON)
            $law = Law::where('id', $documentId)->first();

            if (! $law) {
                Log::debug('No law found', [
                    'document_id' => $documentId,
                ]);

                return null;
            }

            // Try pgvector column first
            if (isset($law->embedding) && $law->embedding) {
                if (is_array($law->embedding)) {
                    return $law->embedding;
                }

                if (is_string($law->embedding)) {
                    $embedding = json_decode($law->embedding, true);
                    if (is_array($embedding)) {
                        return $embedding;
                    }
                }
            }

            // Fallback to embedding_vector column
            if (isset($law->embedding_vector) && $law->embedding_vector) {
                if (is_string($law->embedding_vector)) {
                    $embedding = json_decode($law->embedding_vector, true);
                    if (is_array($embedding)) {
                        return $embedding;
                    }
                }
            }

            Log::debug('No embedding found for law', [
                'document_id' => $documentId,
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Failed to get embedding for law', [
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
            Log::debug('Finding similar laws', [
                'source_document_id' => $documentId,
                'threshold' => $threshold,
                'limit' => $limit,
            ]);

            $startTime = microtime(true);

            $source = Law::where('id', $documentId)->first();

            if (! $source) {
                Log::debug('Source law not found', [
                    'document_id' => $documentId,
                ]);

                return [];
            }

            $driver = DB::connection()->getDriverName();
            $table = (new Law)->getTable();

            // Check if pgvector is available
            $usePgVector = $this->checkPgVectorAvailability($table, $driver);

            if (! $usePgVector) {
                Log::warning('pgvector not available for law similarity search', [
                    'driver' => $driver,
                ]);

                return [];
            }

            // Use pgvector cosine similarity operator (<=>)
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

            Log::info('Similar laws found', [
                'source_document_id' => $documentId,
                'result_count' => count($results),
                'duration_ms' => round($duration, 2),
            ]);

            return $results;

        } catch (\Exception $e) {
            Log::error('Failed to find similar laws', [
                'source_document_id' => $documentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [];
        }
    }
}
