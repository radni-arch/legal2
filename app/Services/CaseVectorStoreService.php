<?php

namespace App\Services;

use App\Contracts\VectorStore\CaseVectorStoreInterface;
use App\Exceptions\EmbeddingException;
use App\Exceptions\IngestException;
use App\Exceptions\VectorStoreException;
use App\Models\CaseDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CaseVectorStoreService implements CaseVectorStoreInterface
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
     * Ingest case documents into vector store
     *
     * @param  string  $caseId  ULID of the LegalCase
     * @param  string  $docId  Group identifier for this document within the case (e.g., decision ID or ECLI)
     * @param  array<int,array{content:string,metadata?:array,actual?:array,chunk_index?:int}>  $docs
     * @param  array  $options  model, provider, upload_id
     * @return array Ingestion results
     *
     * @throws IngestException if ingestion fails
     */
    public function ingest(string $caseId, string $docId, array $docs, array $options = []): array
    {
        $correlationId = request()->header('X-Request-ID') ?? Str::uuid()->toString();
        $startTime = microtime(true);

        Log::withContext(['correlation_id' => $correlationId]);

        Log::info('CaseVectorStore: ingest initiated', [
            'case_id' => $caseId,
            'doc_id' => $docId,
            'chunk_count' => count($docs),
            'options_keys' => array_keys($options),
            'user_id' => auth()->id(),
        ]);

        try {

            // Validate and filter input
            $docs = $this->validateAndFilterDocs($docs);

            if (empty($docs)) {
                Log::warning('No valid documents to ingest', [
                    'case_id' => $caseId,
                    'doc_id' => $docId,
                ]);

                return ['count' => 0, 'inserted' => 0];
            }

            $model = $options['model'] ?? config('openai.models.embeddings');
            $provider = $options['provider'] ?? 'openai';
            $uploadId = $options['upload_id'] ?? null;

            // Generate embeddings
            $inputs = array_map(fn ($d) => (string) $d['content'], $docs);
            $emb = $this->generateEmbeddingsWithRetry($inputs, $model, $caseId);
            $data = $emb['data'] ?? [];

            if (count($data) !== count($docs)) {
                Log::warning('Embedding count mismatch', [
                    'docs' => count($docs),
                    'embeddings' => count($data),
                    'case_id' => $caseId,
                ]);
            }

            $dims = isset($data[0]['embedding']) ? count($data[0]['embedding']) : null;

            $driver = DB::connection()->getDriverName();
            $table = (new CaseDocument)->getTable();

            // Check if pgvector extension is available
            $useVector = $this->checkPgVectorAvailability($driver, $table);

            // Insert documents
            $result = $this->insertDocumentsInTransaction(
                $docs,
                $data,
                $caseId,
                $docId,
                $dims,
                $model,
                $provider,
                $driver,
                $table,
                $uploadId,
                $useVector
            );

            // Sync to graph database if enabled
            $this->syncToGraphDatabase($result['inserted_ids']);

            $duration = (microtime(true) - $startTime) * 1000;

            Log::info('CaseVectorStore: ingest completed', [
                'case_id' => $caseId,
                'doc_id' => $docId,
                'inserted' => $result['inserted'],
                'total_chunks' => count($docs),
                'duration_ms' => round($duration, 2),
            ]);

            return [
                'count' => count($docs),
                'inserted' => $result['inserted'],
                'dimensions' => $dims,
                'model' => $model,
            ];

        } catch (EmbeddingException $e) {
            Log::error('CaseVectorStore: ingest failed with EmbeddingException', [
                'case_id' => $caseId,
                'doc_id' => $docId,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new IngestException(
                'Failed to generate embeddings: '.$e->getMessage(),
                IngestException::EMBEDDING_FAILED,
                $e
            );

        } catch (IngestException $e) {
            Log::error('CaseVectorStore: ingest failed with IngestException', [
                'case_id' => $caseId,
                'doc_id' => $docId,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        } catch (\Exception $e) {
            Log::error('CaseVectorStore: ingest failed with unexpected exception', [
                'case_id' => $caseId,
                'doc_id' => $docId,
                'chunk_count' => count($docs),
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new IngestException(
                'Case ingestion failed: '.$e->getMessage(),
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
    protected function generateEmbeddingsWithRetry(array $inputs, string $model, string $caseId): array
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
                        'case_id' => $caseId,
                        'attempt' => $attempt,
                        'input_count' => count($inputs),
                        'duration_seconds' => round($duration, 3),
                    ]);
                }

                return $result;

            } catch (\Throwable $e) {
                $lastException = $e;

                Log::warning('Embeddings call failed', [
                    'case_id' => $caseId,
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
            'case_id' => $caseId,
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
     * Check if pgvector extension is available
     */
    protected function checkPgVectorAvailability(string $driver, string $table): bool
    {
        if ($driver !== 'pgsql') {
            return false;
        }

        try {
            $columnType = DB::select(
                'SELECT data_type FROM information_schema.columns WHERE table_name = ? AND column_name = ?',
                [$table, 'embedding_vector']
            );

            return isset($columnType[0]->data_type) && strtolower($columnType[0]->data_type) === 'user-defined';
        } catch (\Exception $e) {
            Log::debug('pgvector availability check failed', [
                'table' => $table,
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
        $table = (new CaseDocument)->getTable();

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
        string $caseId,
        string $docId,
        ?int $dims,
        string $model,
        string $provider,
        string $driver,
        string $table,
        $uploadId,
        bool $useVector
    ): array {
        try {
            $inserted = 0;
            $insertedIds = [];

            DB::transaction(function () use ($docs, $data, $caseId, $docId, $dims, $model, $provider, $table, $uploadId, $useVector, &$inserted, &$insertedIds) {
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
                        ->where('case_id', $caseId)
                        ->where('content_hash', $hash)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    $caseDocId = (string) Str::ulid();
                    $payload = [
                        'id' => $caseDocId,
                        'case_id' => $caseId,
                        'doc_id' => $docId,
                        'upload_id' => $uploadId,
                        'content' => $content,
                        'metadata' => isset($doc['metadata']) ? json_encode($doc['metadata'], JSON_UNESCAPED_UNICODE) : null,
                        'actual' => isset($doc['actual']) ? json_encode($doc['actual'], JSON_UNESCAPED_UNICODE) : null,
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

                    if ($useVector) {
                        $payload['embedding_vector'] = DB::raw($this->toPgVectorCastLiteral($vec));
                    } else {
                        $payload['embedding_vector'] = json_encode($vec);
                    }

                    DB::table($table)->insert($payload);
                    $inserted++;
                    $insertedIds[] = $caseDocId;
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
     * Sync inserted cases to graph database
     */
    protected function syncToGraphDatabase(array $insertedIds): void
    {
        if (! $this->graphRag || ! config('neo4j.sync.auto_sync', true) || ! config('neo4j.sync.enabled', true)) {
            return;
        }

        foreach ($insertedIds as $caseDocId) {
            try {
                if (method_exists($this->graphRag, 'syncCase')) {
                    $this->graphRag->syncCase($caseDocId);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to sync case to graph database', [
                    'case_doc_id' => $caseDocId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Build base search query with filters
     */
    protected function buildSearchQuery(string $table, array $filters)
    {
        $queryBuilder = DB::table($table)
            ->select([
                'id',
                'case_id',
                'doc_id',
                'title',
                'content',
                'category',
                'language',
                'source',
                'source_id',
                'chunk_index',
                'metadata',
                'actual',
            ]);

        // Apply filters
        if (! empty($filters['case_id'])) {
            $queryBuilder->where('case_id', $filters['case_id']);
        }
        if (! empty($filters['source'])) {
            $queryBuilder->where('source', $filters['source']);
        }
        if (! empty($filters['category'])) {
            $queryBuilder->where('category', $filters['category']);
        }
        if (! empty($filters['language'])) {
            $queryBuilder->where('language', $filters['language']);
        }

        return $queryBuilder;
    }

    /**
     * Execute pgvector-based search
     */
    protected function executePgVectorSearch($queryBuilder, array $embedding, float $threshold, int $limit)
    {
        try {
            $vectorLiteral = $this->toPgVectorLiteral($embedding);
            $queryBuilder->selectRaw("1 - (embedding_vector <=> '{$vectorLiteral}'::vector) as score")
                ->whereRaw("1 - (embedding_vector <=> '{$vectorLiteral}'::vector) >= ?", [$threshold])
                ->orderByRaw("embedding_vector <=> '{$vectorLiteral}'::vector")
                ->limit($limit);

            return $queryBuilder->get();

        } catch (\Exception $e) {
            throw new VectorStoreException(
                'pgvector search failed: '.$e->getMessage(),
                VectorStoreException::QUERY_FAILED,
                $e
            );
        }
    }

    /**
     * Execute fallback JSON-based search
     */
    protected function executeFallbackSearch($queryBuilder, array $embedding, float $threshold, int $limit)
    {
        try {
            $queryBuilder->whereNotNull('embedding_vector')->limit($limit * 3);
            $results = $queryBuilder->get();

            // Calculate similarity for each result
            $results = $results->map(function ($caseDoc) use ($embedding) {
                $caseVector = json_decode($caseDoc->embedding_vector ?? '[]', true);
                $caseDoc->score = $this->cosineSimilarity($embedding, $caseVector);

                return $caseDoc;
            });

            // Filter by threshold and sort
            return $results->filter(fn ($r) => $r->score >= $threshold)
                ->sortByDesc('score')
                ->take($limit)
                ->values();

        } catch (\Exception $e) {
            throw new VectorStoreException(
                'Fallback search failed: '.$e->getMessage(),
                VectorStoreException::QUERY_FAILED,
                $e
            );
        }
    }

    /**
     * Format search results to array format
     */
    protected function formatSearchResults($results): array
    {
        return $results->map(function ($caseDoc) {
            return [
                'id' => $caseDoc->id,
                'score' => $caseDoc->score ?? 0.0,
                'content' => $caseDoc->content,
                'metadata' => json_decode($caseDoc->metadata ?? '{}', true),
                'actual' => json_decode($caseDoc->actual ?? 'null', true),
                'case_id' => $caseDoc->case_id,
                'doc_id' => $caseDoc->doc_id,
                'title' => $caseDoc->title,
                'category' => $caseDoc->category,
                'language' => $caseDoc->language,
                'source' => $caseDoc->source,
                'source_id' => $caseDoc->source_id,
                'chunk_index' => $caseDoc->chunk_index,
            ];
        })->toArray();
    }

    protected function estimateTokens(string $s): int
    {
        return (int) ceil(strlen($s) / 4);
    }

    protected function norm(array $vec): float
    {
        $sum = 0.0;
        foreach ($vec as $v) {
            $sum += ($v * $v);
        }

        return sqrt($sum);
    }

    protected function toPgVectorLiteral(array $vec): string
    {
        $parts = [];
        foreach ($vec as $v) {
            $parts[] = rtrim(rtrim(number_format((float) $v, 8, '.', ''), '0'), '.');
        }

        return '['.implode(',', $parts).']';
    }

    protected function toPgVectorCastLiteral(array $vec): string
    {
        return "'".$this->toPgVectorLiteral($vec)."'::vector";
    }

    /**
     * Search case documents using vector similarity
     *
     * @param  array  $embedding  The query embedding vector
     * @param  array  $options  Search options:
     *                          - 'threshold' (float): Minimum similarity threshold (default: 0.7)
     *                          - 'limit' (int): Maximum number of results (default: 10)
     *                          - 'filters' (array): Additional filters (case_id, source, category, etc.)
     * @return array Array of search results with id, score, content, and metadata
     *
     * @throws VectorStoreException if search fails
     */
    public function search(array $embedding, array $options = []): array
    {
        $correlationId = request()->header('X-Request-ID') ?? Str::uuid()->toString();
        $startTime = microtime(true);

        Log::withContext(['correlation_id' => $correlationId]);

        Log::info('CaseVectorStore: search initiated', [
            'embedding_dimensions' => count($embedding),
            'options_keys' => array_keys($options),
            'user_id' => auth()->id(),
        ]);

        try {

            $threshold = $options['threshold'] ?? 0.7;
            $limit = $options['limit'] ?? 10;
            $filters = $options['filters'] ?? [];

            $driver = DB::connection()->getDriverName();
            $table = (new CaseDocument)->getTable();

            // Check if table exists before attempting search
            if (! $this->tableExists($table)) {
                Log::warning('CaseVectorStore: search skipped - table does not exist', [
                    'table' => $table,
                    'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ]);

                return [];
            }

            // Build base query with filters
            $queryBuilder = $this->buildSearchQuery($table, $filters);

            // Check if pgvector is available
            $usePgVector = $this->checkPgVectorAvailability($driver, $table);

            // Execute search
            if ($usePgVector) {
                $results = $this->executePgVectorSearch($queryBuilder, $embedding, $threshold, $limit);
            } else {
                $results = $this->executeFallbackSearch($queryBuilder, $embedding, $threshold, $limit);
            }

            // Format results
            $formattedResults = $this->formatSearchResults($results);

            $duration = (microtime(true) - $startTime) * 1000;

            Log::info('CaseVectorStore: search completed', [
                'result_count' => count($formattedResults),
                'duration_ms' => round($duration, 2),
            ]);

            return $formattedResults;

        } catch (VectorStoreException $e) {
            Log::error('CaseVectorStore: search failed with VectorStoreException', [
                'embedding_dimensions' => count($embedding),
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        } catch (\Exception $e) {
            Log::error('CaseVectorStore: search failed with unexpected exception', [
                'embedding_dimensions' => count($embedding),
                'options_keys' => array_keys($options),
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new VectorStoreException(
                'Case vector search failed: '.$e->getMessage(),
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
        if (count($vec1) !== count($vec2) || empty($vec1)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $norm1 = 0.0;
        $norm2 = 0.0;

        for ($i = 0; $i < count($vec1); $i++) {
            $dotProduct += $vec1[$i] * $vec2[$i];
            $norm1 += $vec1[$i] * $vec1[$i];
            $norm2 += $vec2[$i] * $vec2[$i];
        }

        $norm1 = sqrt($norm1);
        $norm2 = sqrt($norm2);

        if ($norm1 == 0 || $norm2 == 0) {
            return 0.0;
        }

        return $dotProduct / ($norm1 * $norm2);
    }

    /**
     * Clean up orphaned embeddings (documents without associated cases)
     *
     * @return array{removed_count: int, orphaned_ids: array}
     */
    public function cleanupOrphanedEmbeddings(): array
    {
        // Find documents whose case_id doesn't exist in cases table
        $orphanedDocs = DB::table('cases_documents as cd')
            ->leftJoin('cases as c', 'cd.case_id', '=', 'c.id')
            ->whereNull('c.id')
            ->select('cd.id')
            ->pluck('id')
            ->toArray();

        if (empty($orphanedDocs)) {
            return [
                'removed_count' => 0,
                'orphaned_ids' => [],
            ];
        }

        // Delete orphaned documents
        DB::table('cases_documents')
            ->whereIn('id', $orphanedDocs)
            ->delete();

        Log::info('CaseVectorStore: Cleaned up orphaned embeddings', [
            'removed_count' => count($orphanedDocs),
            'orphaned_ids' => $orphanedDocs,
        ]);

        return [
            'removed_count' => count($orphanedDocs),
            'orphaned_ids' => $orphanedDocs,
        ];
    }

    /**
     * Detect duplicate documents based on content hash
     *
     * @param  array  $options  Options: ['auto_merge' => bool]
     * @return array{duplicate_groups: array, total_duplicates: int, merged_count?: int}
     */
    public function detectDuplicates(array $options = []): array
    {
        $autoMerge = $options['auto_merge'] ?? false;

        // Find documents with same content_hash
        $duplicates = DB::table('cases_documents')
            ->select('content_hash', DB::raw('COUNT(*) as count'), DB::raw('array_agg(id ORDER BY created_at ASC) as doc_ids'))
            ->whereNotNull('content_hash')
            ->groupBy('content_hash')
            ->having(DB::raw('COUNT(*)'), '>', 1)
            ->get();

        $duplicateGroups = [];
        $totalDuplicates = 0;
        $mergedCount = 0;

        foreach ($duplicates as $duplicate) {
            $docIds = explode(',', trim($duplicate->doc_ids, '{}'));
            $duplicateGroups[] = [
                'content_hash' => $duplicate->content_hash,
                'count' => $duplicate->count,
                'doc_ids' => $docIds,
            ];
            $totalDuplicates += $duplicate->count;

            // Auto-merge: keep oldest (first in array), delete others
            if ($autoMerge && count($docIds) > 1) {
                $toDelete = array_slice($docIds, 1); // All except first (oldest)
                DB::table('cases_documents')
                    ->whereIn('id', $toDelete)
                    ->delete();
                $mergedCount += count($toDelete);
            }
        }

        $result = [
            'duplicate_groups' => $duplicateGroups,
            'total_duplicates' => $totalDuplicates,
        ];

        if ($autoMerge) {
            $result['merged_count'] = $mergedCount;
            Log::info('CaseVectorStore: Merged duplicate documents', [
                'merged_count' => $mergedCount,
            ]);
        }

        return $result;
    }

    /**
     * Reindex corrupted embeddings (null, invalid JSON, or wrong dimensions)
     *
     * @param  array  $options  Options: ['dry_run' => bool]
     * @return array{corrupted_ids: array, reindexed_count?: int}
     */
    public function reindexCorrupted(array $options = []): array
    {
        $dryRun = $options['dry_run'] ?? false;

        // Find corrupted embeddings
        $corruptedDocs = DB::table('cases_documents')
            ->select('id', 'content', 'embedding_vector', 'embedding_dimensions')
            ->get()
            ->filter(function ($doc) {
                // Null vector
                if (is_null($doc->embedding_vector)) {
                    return true;
                }

                // Try to decode JSON
                $vector = json_decode($doc->embedding_vector, true);
                if (! is_array($vector)) {
                    return true;
                }

                // Wrong dimensions
                if ($doc->embedding_dimensions && count($vector) !== $doc->embedding_dimensions) {
                    return true;
                }

                return false;
            })
            ->pluck('id')
            ->toArray();

        if ($dryRun || empty($corruptedDocs)) {
            return [
                'corrupted_ids' => $corruptedDocs,
            ];
        }

        // Regenerate embeddings for corrupted documents
        $reindexedCount = 0;
        foreach ($corruptedDocs as $docId) {
            $doc = DB::table('cases_documents')->where('id', $docId)->first();
            if (! $doc || empty($doc->content)) {
                continue;
            }

            try {
                // Generate new embedding
                $response = $this->openai->embeddings([
                    'model' => 'text-embedding-3-small',
                    'input' => $doc->content,
                ]);

                $embedding = $response['data'][0]['embedding'];

                // Update document with new embedding
                DB::table('cases_documents')
                    ->where('id', $docId)
                    ->update([
                        'embedding_vector' => json_encode($embedding),
                        'embedding_dimensions' => count($embedding),
                        'embedding_norm' => sqrt(array_sum(array_map(fn ($v) => $v * $v, $embedding))),
                        'updated_at' => now(),
                    ]);

                $reindexedCount++;
            } catch (\Exception $e) {
                Log::error('CaseVectorStore: Failed to reindex corrupted document', [
                    'doc_id' => $docId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('CaseVectorStore: Reindexed corrupted embeddings', [
            'corrupted_count' => count($corruptedDocs),
            'reindexed_count' => $reindexedCount,
        ]);

        return [
            'corrupted_ids' => $corruptedDocs,
            'reindexed_count' => $reindexedCount,
        ];
    }

    /**
     * Optimize vector store (VACUUM, ANALYZE, rebuild indexes)
     *
     * @param  array  $options  Options: ['rebuild_indexes' => bool]
     * @return array{status: string, operations: array, table_stats?: array, indexes_rebuilt?: array}
     */
    public function optimizeVectorStore(array $options = []): array
    {
        $rebuildIndexes = $options['rebuild_indexes'] ?? false;
        $operations = [];

        // Get table stats before optimization
        $statsBefore = DB::select("
            SELECT n_dead_tup as dead_tuples
            FROM pg_stat_user_tables
            WHERE schemaname = 'public' AND relname = 'cases_documents'
        ");
        $deadTuplesBefore = $statsBefore[0]->dead_tuples ?? 0;

        // Run VACUUM (skip if in transaction - common in tests)
        $inTransaction = false;
        try {
            DB::unprepared('VACUUM cases_documents');
            $operations[] = 'vacuum';
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'transaction block') || str_contains($e->getMessage(), 'failed sql transaction')) {
                Log::debug('CaseVectorStore: Skipped VACUUM (in transaction)');
                $operations[] = 'vacuum_skipped';
                $inTransaction = true;
            } else {
                throw $e;
            }
        }

        // Run ANALYZE (also skip if we detected transaction issue)
        if (! $inTransaction) {
            try {
                DB::statement('ANALYZE cases_documents');
                $operations[] = 'analyze';
            } catch (\Exception $e) {
                if (str_contains($e->getMessage(), 'failed sql transaction')) {
                    Log::debug('CaseVectorStore: Skipped ANALYZE (transaction failed)');
                    $operations[] = 'analyze_skipped';
                } else {
                    throw $e;
                }
            }
        } else {
            $operations[] = 'analyze_skipped';
        }

        // Get table stats after optimization (skip if transaction failed)
        $deadTuplesAfter = 0;
        if (! $inTransaction) {
            try {
                $statsAfter = DB::select("
                    SELECT n_dead_tup as dead_tuples
                    FROM pg_stat_user_tables
                    WHERE schemaname = 'public' AND relname = 'cases_documents'
                ");
                $deadTuplesAfter = $statsAfter[0]->dead_tuples ?? 0;
            } catch (\Exception $e) {
                if (! str_contains($e->getMessage(), 'failed sql transaction')) {
                    throw $e;
                }
            }
        }

        $result = [
            'status' => 'completed',
            'operations' => $operations,
            'table_stats' => [
                'dead_tuples_before' => $deadTuplesBefore,
                'dead_tuples_after' => $deadTuplesAfter,
            ],
        ];

        // Rebuild indexes if requested (skip if transaction failed)
        if ($rebuildIndexes && ! $inTransaction) {
            try {
                $indexes = DB::select("
                    SELECT indexname
                    FROM pg_indexes
                    WHERE schemaname = 'public' AND tablename = 'cases_documents'
                ");

                $rebuiltIndexes = [];
                foreach ($indexes as $index) {
                    try {
                        DB::unprepared("REINDEX INDEX {$index->indexname}");
                        $rebuiltIndexes[] = $index->indexname;
                    } catch (\Exception $e) {
                        if (str_contains($e->getMessage(), 'transaction block')) {
                            Log::debug('CaseVectorStore: Skipped REINDEX (in transaction)', [
                                'index' => $index->indexname,
                            ]);
                        } else {
                            Log::warning('CaseVectorStore: Failed to rebuild index', [
                                'index' => $index->indexname,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }

                $result['indexes_rebuilt'] = $rebuiltIndexes;
                Log::info('CaseVectorStore: Rebuilt indexes', [
                    'count' => count($rebuiltIndexes),
                    'indexes' => $rebuiltIndexes,
                ]);
            } catch (\Exception $e) {
                if (str_contains($e->getMessage(), 'failed sql transaction')) {
                    $result['indexes_rebuilt'] = [];
                    Log::debug('CaseVectorStore: Skipped index rebuild (transaction failed)');
                } else {
                    throw $e;
                }
            }
        } elseif ($rebuildIndexes && $inTransaction) {
            $result['indexes_rebuilt'] = [];
            Log::debug('CaseVectorStore: Skipped index rebuild (in transaction)');
        }

        Log::info('CaseVectorStore: Optimized vector store', [
            'operations' => $operations,
            'dead_tuples_removed' => $deadTuplesBefore - $deadTuplesAfter,
        ]);

        return $result;
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
            $deleted = CaseDocument::where('case_id', $docId)->delete();

            return $deleted > 0;
        } catch (\Exception $e) {
            Log::error('Failed to delete case documents', [
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
            return CaseDocument::where('case_id', $docId)->exists();
        } catch (\Exception $e) {
            Log::error('Failed to check if case exists', [
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
            $caseDoc = CaseDocument::where('id', $documentId)
                ->whereNotNull('embedding_vector')
                ->first();

            if (! $caseDoc || ! $caseDoc->embedding_vector) {
                Log::debug('No embedding found for case document', [
                    'document_id' => $documentId,
                ]);

                return null;
            }

            // Check if embedding_vector is already an array (pgvector type)
            if (is_array($caseDoc->embedding_vector)) {
                return $caseDoc->embedding_vector;
            }

            // Fallback: if it's JSON string, decode it
            if (is_string($caseDoc->embedding_vector)) {
                $embedding = json_decode($caseDoc->embedding_vector, true);

                return is_array($embedding) ? $embedding : null;
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Failed to get embedding for case document', [
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
            Log::debug('Finding similar case documents', [
                'source_document_id' => $documentId,
                'threshold' => $threshold,
                'limit' => $limit,
            ]);

            $startTime = microtime(true);

            $source = CaseDocument::where('id', $documentId)->first();

            if (! $source || ! $source->embedding_vector) {
                Log::debug('Source case document not found or has no embedding', [
                    'document_id' => $documentId,
                ]);

                return [];
            }

            $driver = DB::connection()->getDriverName();
            $table = (new CaseDocument)->getTable();

            // Check if pgvector is available
            $usePgVector = $this->checkPgVectorAvailability($driver, $table);

            if (! $usePgVector) {
                Log::warning('pgvector not available for case similarity search', [
                    'driver' => $driver,
                ]);

                return [];
            }

            // Use pgvector cosine similarity operator (<=>)
            $results = DB::table($table)
                ->whereNotNull('embedding_vector')
                ->where('id', '!=', $documentId)
                ->selectRaw('id, (1 - (embedding_vector <=> (SELECT embedding_vector FROM '.$table.' WHERE id = ?))) as similarity_score', [$documentId])
                ->havingRaw('(1 - (embedding_vector <=> (SELECT embedding_vector FROM '.$table.' WHERE id = ?))) >= ?', [$documentId, $threshold])
                ->orderByDesc('similarity_score')
                ->limit($limit)
                ->get()
                ->map(fn ($doc) => [
                    'id' => $doc->id,
                    'similarity_score' => (float) $doc->similarity_score,
                ])
                ->toArray();

            $duration = (microtime(true) - $startTime) * 1000;

            Log::info('Similar case documents found', [
                'source_document_id' => $documentId,
                'result_count' => count($results),
                'duration_ms' => round($duration, 2),
            ]);

            return $results;

        } catch (\Exception $e) {
            Log::error('Failed to find similar case documents', [
                'source_document_id' => $documentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [];
        }
    }
}
