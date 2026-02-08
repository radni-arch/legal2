<?php

namespace App\Services;

use App\Exceptions\VectorStoreException;
use App\Models\CaseDocument;
use App\Models\CourtDecisionDocument;
use App\Models\Law;
use App\Models\TextractDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Vector Store Management Service
 *
 * Unified interface for managing all vector stores:
 * - Laws
 * - Court Decisions
 * - Cases
 * - Textract
 */
class VectorStoreManagementService
{
    /**
     * Available vector stores configuration
     */
    protected array $stores = [
        'laws' => [
            'name' => 'Laws',
            'description' => 'Croatian laws (ZKP, Kazneni zakon, Ustav RH)',
            'table' => 'laws',
            'model' => Law::class,
            'doc_id_column' => 'doc_id',
            'content_column' => 'content',
        ],
        'court_decisions' => [
            'name' => 'Court Decisions',
            'description' => 'Court decisions from odluke.sudovi.hr',
            'table' => 'court_decision_documents',
            'model' => CourtDecisionDocument::class,
            'doc_id_column' => 'doc_id',
            'content_column' => 'content',
        ],
        'cases' => [
            'name' => 'Cases',
            'description' => 'Internal case documents',
            'table' => 'cases_documents',
            'model' => CaseDocument::class,
            'doc_id_column' => 'doc_id',
            'content_column' => 'content',
        ],
        'textract' => [
            'name' => 'Textract',
            'description' => 'OCR\'d PDF documents from AWS Textract',
            'table' => 'textract_documents',
            'model' => TextractDocument::class,
            'doc_id_column' => 'textract_job_id',
            'content_column' => 'content',
        ],
    ];

    public function __construct(
        protected OpenAIService $openai,
        protected LawVectorStoreService $lawVectors,
        protected CourtDecisionVectorStoreService $decisionVectors,
        protected CaseVectorStoreService $caseVectors,
        protected TextractVectorStoreService $textractVectors
    ) {}

    /**
     * Get list of available vector stores
     */
    public function getStores(): array
    {
        return $this->stores;
    }

    /**
     * Get store configuration by key
     */
    public function getStore(string $storeKey): ?array
    {
        return $this->stores[$storeKey] ?? null;
    }

    /**
     * Get statistics for a vector store
     */
    public function getStatistics(string $storeKey): array
    {
        $correlationId = request()->header('X-Request-ID') ?? Str::uuid()->toString();
        $startTime = microtime(true);

        Log::withContext(['correlation_id' => $correlationId]);

        Log::info('VectorStoreManagement: getStatistics initiated', [
            'store_key' => $storeKey,
            'user_id' => auth()->id(),
        ]);

        try {
            $store = $this->getStore($storeKey);
            if (! $store) {
                Log::warning('VectorStoreManagement: Unknown store key', ['store_key' => $storeKey]);

                return [
                    'store_key' => $storeKey,
                    'total_documents' => 0,
                ];
            }

            $table = $store['table'];

            $stats = DB::table($table)
                ->selectRaw('
                    COUNT(*) as total_documents,
                    COUNT(DISTINCT '.$store['doc_id_column'].') as unique_documents,
                    AVG(token_count) as avg_tokens,
                    SUM(token_count) as total_tokens,
                    MAX(updated_at) as last_updated,
                    MIN(created_at) as first_created
                ')
                ->first();

            // Get embedding stats if available
            $embeddingStats = DB::table($table)
                ->select('embedding_model', DB::raw('COUNT(*) as count'))
                ->whereNotNull('embedding_model')
                ->groupBy('embedding_model')
                ->get();

            $result = [
                'store_key' => $storeKey,
                'total_documents' => $stats->total_documents ?? 0,
                'unique_documents' => $stats->unique_documents ?? 0,
                'avg_tokens' => round($stats->avg_tokens ?? 0, 2),
                'total_tokens' => $stats->total_tokens ?? 0,
                'last_updated' => $stats->last_updated,
                'first_created' => $stats->first_created,
                'embedding_models' => $embeddingStats->mapWithKeys(function ($item) {
                    return [$item->embedding_model => $item->count];
                })->toArray(),
            ];

            $duration = (microtime(true) - $startTime) * 1000;

            Log::info('VectorStoreManagement: getStatistics completed', [
                'store_key' => $storeKey,
                'total_documents' => $result['total_documents'],
                'unique_documents' => $result['unique_documents'],
                'duration_ms' => round($duration, 2),
            ]);

            return $result;

        } catch (VectorStoreException $e) {
            Log::error('VectorStoreManagement: getStatistics failed with VectorStoreException', [
                'store_key' => $storeKey,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'store_key' => $storeKey,
                'total_documents' => 0,
            ];

        } catch (\Throwable $e) {
            Log::error('VectorStoreManagement: getStatistics failed with unexpected exception', [
                'store_key' => $storeKey,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'store_key' => $storeKey,
                'total_documents' => 0,
            ];
        }
    }

    /**
     * Browse documents in a vector store with pagination
     */
    public function browseDocuments(string $storeKey, int $page = 1, int $perPage = 20, ?string $search = null): array
    {
        $store = $this->getStore($storeKey);
        if (! $store) {
            return ['data' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage];
        }

        $query = DB::table($store['table']);

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($store, $search) {
                $q->where($store['doc_id_column'], 'LIKE', "%{$search}%")
                    ->orWhere($store['content_column'], 'LIKE', "%{$search}%");
            });
        }

        $total = $query->count();

        $documents = $query
            ->select([
                'id',
                $store['doc_id_column'].' as doc_id',
                $store['content_column'].' as content',
                'chunk_index',
                'embedding_model',
                'token_count',
                'created_at',
                'updated_at',
            ])
            ->orderBy('created_at', 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->map(function ($doc) {
                // Truncate content for preview
                if (isset($doc->content) && strlen($doc->content) > 200) {
                    $doc->content_preview = substr($doc->content, 0, 200).'...';
                } else {
                    $doc->content_preview = $doc->content ?? '';
                }

                return $doc;
            });

        return [
            'data' => $documents,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage),
        ];
    }

    /**
     * Search documents by similarity using embedding
     */
    public function searchBySimilarity(string $storeKey, string $query, int $limit = 10): array
    {
        $correlationId = request()->header('X-Request-ID') ?? Str::uuid()->toString();
        $startTime = microtime(true);

        Log::withContext(['correlation_id' => $correlationId]);

        Log::info('VectorStoreManagement: searchBySimilarity initiated', [
            'store_key' => $storeKey,
            'query_length' => strlen($query),
            'limit' => $limit,
            'user_id' => auth()->id(),
        ]);

        try {
            $store = $this->getStore($storeKey);
            if (! $store) {
                Log::warning('VectorStoreManagement: Unknown store key for similarity search', ['store_key' => $storeKey]);

                return [];
            }

            // Generate embedding for query
            $model = config('openai.models.embeddings');
            $response = $this->openai->embeddings([$query], $model);
            $embedding = $response['data'][0]['embedding'] ?? null;

            if (! $embedding) {
                Log::warning('VectorStoreManagement: Failed to generate embedding for query');

                return [];
            }

            // Use the appropriate service for similarity search
            $results = match ($storeKey) {
                'laws' => $this->lawVectors->search($embedding, ['limit' => $limit]),
                'court_decisions' => $this->searchCourtDecisions($embedding, $limit),
                'cases' => $this->searchCases($embedding, $limit),
                'textract' => $this->searchTextract($embedding, $limit),
                default => [],
            };

            $duration = (microtime(true) - $startTime) * 1000;

            Log::info('VectorStoreManagement: searchBySimilarity completed', [
                'store_key' => $storeKey,
                'results_count' => count($results),
                'duration_ms' => round($duration, 2),
            ]);

            return $results;

        } catch (VectorStoreException $e) {
            Log::error('VectorStoreManagement: searchBySimilarity failed with VectorStoreException', [
                'store_key' => $storeKey,
                'query' => substr($query, 0, 100),
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            return [];

        } catch (\Throwable $e) {
            Log::error('VectorStoreManagement: searchBySimilarity failed with unexpected exception', [
                'store_key' => $storeKey,
                'query' => substr($query, 0, 100),
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            return [];
        }
    }

    /**
     * Search court decisions by embedding
     */
    protected function searchCourtDecisions(array $embedding, int $limit): array
    {
        $table = 'court_decision_documents';
        $driver = DB::connection()->getDriverName();

        if ($driver !== 'pgsql') {
            return [];
        }

        $embVec = '['.implode(',', $embedding).']';

        $results = DB::select("
            SELECT
                id,
                doc_id,
                content,
                1 - (embedding <=> ?::vector) as similarity
            FROM {$table}
            WHERE embedding IS NOT NULL
            ORDER BY embedding <=> ?::vector
            LIMIT ?
        ", [$embVec, $embVec, $limit]);

        return array_map(fn ($r) => (array) $r, $results);
    }

    /**
     * Search cases by embedding
     */
    protected function searchCases(array $embedding, int $limit): array
    {
        $table = 'cases_documents';
        $driver = DB::connection()->getDriverName();

        if ($driver !== 'pgsql') {
            return [];
        }

        $embVec = '['.implode(',', $embedding).']';

        $results = DB::select("
            SELECT
                id,
                doc_id,
                content,
                1 - (embedding <=> ?::vector) as similarity
            FROM {$table}
            WHERE embedding IS NOT NULL
            ORDER BY embedding <=> ?::vector
            LIMIT ?
        ", [$embVec, $embVec, $limit]);

        return array_map(fn ($r) => (array) $r, $results);
    }

    /**
     * Search textract documents by embedding
     */
    protected function searchTextract(array $embedding, int $limit): array
    {
        // Textract doesn't store embeddings in the same way
        // This is a placeholder for future implementation
        return [];
    }

    /**
     * Delete document by ID
     */
    public function deleteDocument(string $storeKey, string $documentId): bool
    {
        $store = $this->getStore($storeKey);
        if (! $store) {
            return false;
        }

        try {
            $deleted = DB::table($store['table'])
                ->where('id', $documentId)
                ->delete();

            Log::info('[VectorStoreManagement] Document deleted', [
                'store' => $storeKey,
                'document_id' => $documentId,
            ]);

            return $deleted > 0;
        } catch (\Throwable $e) {
            Log::error('[VectorStoreManagement] Failed to delete document', [
                'store' => $storeKey,
                'document_id' => $documentId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Delete all documents for a specific doc_id
     */
    public function deleteByDocId(string $storeKey, string $docId): int
    {
        $store = $this->getStore($storeKey);
        if (! $store) {
            return 0;
        }

        try {
            $deleted = DB::table($store['table'])
                ->where($store['doc_id_column'], $docId)
                ->delete();

            Log::info('[VectorStoreManagement] Documents deleted by doc_id', [
                'store' => $storeKey,
                'doc_id' => $docId,
                'count' => $deleted,
            ]);

            return $deleted;
        } catch (\Throwable $e) {
            Log::error('[VectorStoreManagement] Failed to delete by doc_id', [
                'store' => $storeKey,
                'doc_id' => $docId,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Get document details by ID
     */
    public function getDocument(string $storeKey, string $documentId): ?array
    {
        $store = $this->getStore($storeKey);
        if (! $store) {
            return null;
        }

        try {
            $document = DB::table($store['table'])
                ->where('id', $documentId)
                ->first();

            return $document ? (array) $document : null;
        } catch (\Throwable $e) {
            Log::error('[VectorStoreManagement] Failed to get document', [
                'store' => $storeKey,
                'document_id' => $documentId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Re-index a document (regenerate embedding)
     */
    public function reindexDocument(string $storeKey, string $documentId): bool
    {
        $correlationId = request()->header('X-Request-ID') ?? Str::uuid()->toString();
        $startTime = microtime(true);

        Log::withContext(['correlation_id' => $correlationId]);

        Log::info('VectorStoreManagement: reindexDocument initiated', [
            'store_key' => $storeKey,
            'document_id' => $documentId,
            'user_id' => auth()->id(),
        ]);

        try {
            $store = $this->getStore($storeKey);
            if (! $store) {
                Log::warning('VectorStoreManagement: Unknown store key for reindex', ['store_key' => $storeKey]);

                return false;
            }

            $document = $this->getDocument($storeKey, $documentId);
            if (! $document) {
                Log::warning('VectorStoreManagement: Document not found for reindex', [
                    'store_key' => $storeKey,
                    'document_id' => $documentId,
                ]);

                return false;
            }

            $content = $document[$store['content_column']] ?? null;
            if (! $content) {
                Log::warning('VectorStoreManagement: Document has no content for reindex', [
                    'store_key' => $storeKey,
                    'document_id' => $documentId,
                ]);

                return false;
            }

            // Generate new embedding
            $model = config('openai.models.embeddings');
            $response = $this->openai->embeddings([$content], $model);
            $embedding = $response['data'][0]['embedding'] ?? null;

            if (! $embedding) {
                Log::error('VectorStoreManagement: Failed to generate embedding for reindex');

                return false;
            }

            // Update embedding in database
            $driver = DB::connection()->getDriverName();
            $updateData = [
                'embedding_model' => $model,
                'embedding_dimensions' => count($embedding),
                'updated_at' => now(),
            ];

            if ($driver === 'pgsql') {
                $embVec = '['.implode(',', $embedding).']';
                DB::table($store['table'])
                    ->where('id', $documentId)
                    ->update(array_merge($updateData, [
                        'embedding' => DB::raw("'{$embVec}'::vector"),
                    ]));
            } else {
                DB::table($store['table'])
                    ->where('id', $documentId)
                    ->update(array_merge($updateData, [
                        'embedding' => json_encode($embedding),
                    ]));
            }

            $duration = (microtime(true) - $startTime) * 1000;

            Log::info('VectorStoreManagement: reindexDocument completed', [
                'store_key' => $storeKey,
                'document_id' => $documentId,
                'embedding_dimensions' => count($embedding),
                'duration_ms' => round($duration, 2),
            ]);

            return true;

        } catch (VectorStoreException $e) {
            Log::error('VectorStoreManagement: reindexDocument failed with VectorStoreException', [
                'store_key' => $storeKey,
                'document_id' => $documentId,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;

        } catch (\Throwable $e) {
            Log::error('VectorStoreManagement: reindexDocument failed with unexpected exception', [
                'store_key' => $storeKey,
                'document_id' => $documentId,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }
}
