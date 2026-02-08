<?php

namespace App\Services;

use App\Contracts\Ingest\IngestPipelineServiceInterface;
use App\Exceptions\IngestException;
use App\Models\AgentVectorMemory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Ingest Pipeline Service
 *
 * Handles text and file ingestion into vector stores with embedding generation.
 * Supports chunking, deduplication, and both pgvector and JSON storage.
 *
 * Hardened with comprehensive error handling and performance monitoring
 */
class IngestPipelineService implements IngestPipelineServiceInterface
{
    public function __construct(protected OpenAIService $openai, protected ?OcrService $ocr = null) {}

    /**
     * Ingest text into vector store
     *
     * @throws IngestException
     */
    public function ingestText(string $agent, string $namespace, string $text, array $options = []): array
    {
        $startTime = microtime(true);

        Log::info('Starting text ingestion', [
            'agent' => $agent,
            'namespace' => $namespace,
            'text_length' => strlen($text),
            'options' => $options,
        ]);

        try {
            $chunkChars = (int) ($options['chunk_chars'] ?? 2000);
            $overlap = (int) ($options['overlap'] ?? 200);
            $model = $options['model'] ?? config('openai.models.embeddings');

            // Chunk text
            $chunkStart = microtime(true);
            try {
                $chunks = $this->chunkText($text, $chunkChars, $overlap);
                $chunkDuration = microtime(true) - $chunkStart;

                Log::info('Text chunking completed', [
                    'agent' => $agent,
                    'namespace' => $namespace,
                    'chunk_count' => count($chunks),
                    'chunk_size' => $chunkChars,
                    'overlap' => $overlap,
                    'duration_ms' => round($chunkDuration * 1000, 2),
                ]);
            } catch (\Throwable $e) {
                $chunkDuration = microtime(true) - $chunkStart;

                Log::error('Text chunking failed', [
                    'agent' => $agent,
                    'namespace' => $namespace,
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'duration_ms' => round($chunkDuration * 1000, 2),
                    'trace' => $e->getTraceAsString(),
                ]);

                throw new IngestException(
                    "Failed to chunk text for agent {$agent}: {$e->getMessage()}",
                    IngestException::CHUNKING_FAILED,
                    $e
                );
            }

            if (empty($chunks)) {
                $totalDuration = microtime(true) - $startTime;

                Log::warning('No chunks generated from text', [
                    'agent' => $agent,
                    'namespace' => $namespace,
                    'text_length' => strlen($text),
                    'duration_ms' => round($totalDuration * 1000, 2),
                ]);

                return ['count' => 0, 'inserted' => 0];
            }

            // Map chunks to docs
            $docs = [];
            foreach ($chunks as $i => $content) {
                $docs[] = [
                    'content' => $content,
                    'metadata' => null,
                    'source' => $options['source'] ?? 'text',
                    'source_id' => $options['source_id'] ?? null,
                    'chunk_index' => $i,
                ];
            }

            $result = $this->ingestDocuments($agent, $namespace, $docs, ['model' => $model]);
            $totalDuration = microtime(true) - $startTime;

            Log::info('Text ingestion completed', [
                'agent' => $agent,
                'namespace' => $namespace,
                'chunks' => count($chunks),
                'inserted' => $result['inserted'],
                'total_duration_ms' => round($totalDuration * 1000, 2),
            ]);

            return $result;

        } catch (IngestException $e) {
            $totalDuration = microtime(true) - $startTime;

            Log::error('Text ingestion failed', [
                'agent' => $agent,
                'namespace' => $namespace,
                'error_code' => $e->getCode(),
                'error' => $e->getMessage(),
                'total_duration_ms' => round($totalDuration * 1000, 2),
            ]);

            throw $e;
        } catch (\Throwable $e) {
            $totalDuration = microtime(true) - $startTime;

            Log::error('Text ingestion failed with unexpected error', [
                'agent' => $agent,
                'namespace' => $namespace,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'total_duration_ms' => round($totalDuration * 1000, 2),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new IngestException(
                "Unexpected error in text ingestion for agent {$agent}: {$e->getMessage()}",
                IngestException::UNEXPECTED_ERROR,
                $e
            );
        }
    }

    /**
     * Ingest file into vector store
     *
     * @throws IngestException
     */
    public function ingestFile(string $agent, string $namespace, string $path, ?string $mime = null, array $options = []): array
    {
        $startTime = microtime(true);

        Log::info('Starting file ingestion', [
            'agent' => $agent,
            'namespace' => $namespace,
            'file_path' => $path,
            'mime_type' => $mime,
            'options' => $options,
        ]);

        try {
            // Determine MIME type
            $mime = $mime ?: (function_exists('mime_content_type') ? @mime_content_type($path) : null);

            // Extract content
            $extractStart = microtime(true);
            $content = '';

            try {
                if ($mime === 'application/pdf' || str_ends_with(strtolower($path), '.pdf')) {
                    if ($this->ocr) {
                        $content = $this->ocr->extractTextFromPdf($path) ?? '';
                        Log::debug('PDF text extracted via OCR', [
                            'file_path' => $path,
                            'content_length' => strlen($content),
                        ]);
                    }
                }

                if ($content === '') {
                    $content = @file_get_contents($path) ?: '';
                    Log::debug('File content read directly', [
                        'file_path' => $path,
                        'content_length' => strlen($content),
                    ]);
                }

                $extractDuration = microtime(true) - $extractStart;

                Log::info('File content extracted', [
                    'file_path' => $path,
                    'content_length' => strlen($content),
                    'duration_ms' => round($extractDuration * 1000, 2),
                ]);
            } catch (\Throwable $e) {
                $extractDuration = microtime(true) - $extractStart;

                Log::error('File content extraction failed', [
                    'file_path' => $path,
                    'mime_type' => $mime,
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'duration_ms' => round($extractDuration * 1000, 2),
                    'trace' => $e->getTraceAsString(),
                ]);

                throw new IngestException(
                    "Failed to extract content from file {$path}: {$e->getMessage()}",
                    IngestException::FILE_READ_FAILED,
                    $e
                );
            }

            if ($content === '') {
                $totalDuration = microtime(true) - $startTime;

                Log::warning('No content extracted from file', [
                    'file_path' => $path,
                    'mime_type' => $mime,
                    'duration_ms' => round($totalDuration * 1000, 2),
                ]);

                return ['count' => 0, 'inserted' => 0, 'skipped' => true];
            }

            $result = $this->digestAndStore($agent, $namespace, $content, [
                'source' => 'file',
                'source_id' => basename($path),
                'model' => $options['model'] ?? config('openai.models.embeddings'),
                'chunk_chars' => $options['chunk_chars'] ?? 2000,
                'overlap' => $options['overlap'] ?? 200,
            ]);

            $totalDuration = microtime(true) - $startTime;

            Log::info('File ingestion completed', [
                'agent' => $agent,
                'namespace' => $namespace,
                'file_path' => $path,
                'inserted' => $result['inserted'],
                'total_duration_ms' => round($totalDuration * 1000, 2),
            ]);

            return $result;

        } catch (IngestException $e) {
            $totalDuration = microtime(true) - $startTime;

            Log::error('File ingestion failed', [
                'file_path' => $path,
                'error_code' => $e->getCode(),
                'error' => $e->getMessage(),
                'total_duration_ms' => round($totalDuration * 1000, 2),
            ]);

            throw $e;
        } catch (\Throwable $e) {
            $totalDuration = microtime(true) - $startTime;

            Log::error('File ingestion failed with unexpected error', [
                'file_path' => $path,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'total_duration_ms' => round($totalDuration * 1000, 2),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new IngestException(
                "Unexpected error in file ingestion for {$path}: {$e->getMessage()}",
                IngestException::UNEXPECTED_ERROR,
                $e
            );
        }
    }

    /**
     * Digest and store content
     *
     * @throws IngestException
     */
    protected function digestAndStore(string $agent, string $namespace, string $text, array $opts): array
    {
        $startTime = microtime(true);

        $chunkChars = (int) ($opts['chunk_chars'] ?? 2000);
        $overlap = (int) ($opts['overlap'] ?? 200);
        $model = $opts['model'] ?? config('openai.models.embeddings');
        $source = $opts['source'] ?? null;
        $sourceId = $opts['source_id'] ?? null;

        try {
            $chunks = $this->chunkText($text, $chunkChars, $overlap);

            if (empty($chunks)) {
                Log::debug('No chunks generated in digestAndStore', [
                    'agent' => $agent,
                    'namespace' => $namespace,
                    'text_length' => strlen($text),
                ]);

                return ['count' => 0, 'inserted' => 0];
            }

            $docs = [];
            foreach ($chunks as $i => $content) {
                $docs[] = [
                    'content' => $content,
                    'metadata' => null,
                    'source' => $source,
                    'source_id' => $sourceId,
                    'chunk_index' => $i,
                ];
            }

            return $this->ingestDocuments($agent, $namespace, $docs, ['model' => $model]);

        } catch (\Throwable $e) {
            $duration = microtime(true) - $startTime;

            Log::error('Digest and store failed', [
                'agent' => $agent,
                'namespace' => $namespace,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'duration_ms' => round($duration * 1000, 2),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new IngestException(
                "Failed to digest and store content: {$e->getMessage()}",
                IngestException::PIPELINE_FAILED,
                $e
            );
        }
    }

    /**
     * Batch-ingest pre-chunked documents (content + optional metadata).
     * Each doc: [content, metadata, source, source_id, chunk_index]
     *
     * @throws IngestException
     */
    public function ingestDocuments(string $agent, string $namespace, array $docs, array $options = []): array
    {
        $startTime = microtime(true);

        Log::info('Starting document batch ingestion', [
            'agent' => $agent,
            'namespace' => $namespace,
            'document_count' => count($docs),
            'options' => $options,
        ]);

        try {
            // Filter empty docs
            $docs = array_values(array_filter($docs, fn ($d) => isset($d['content']) && trim((string) $d['content']) !== ''));

            if (empty($docs)) {
                Log::warning('No valid documents to ingest after filtering', [
                    'agent' => $agent,
                    'namespace' => $namespace,
                ]);

                return ['count' => 0, 'inserted' => 0];
            }

            $model = $options['model'] ?? config('openai.models.embeddings');

            // Generate embeddings
            $embeddingStart = microtime(true);
            try {
                $inputs = array_map(fn ($d) => (string) $d['content'], $docs);
                $emb = $this->openai->embeddings($inputs, $model);
                $data = $emb['data'] ?? [];
                $embeddingDuration = microtime(true) - $embeddingStart;

                Log::info('Embeddings generated', [
                    'agent' => $agent,
                    'namespace' => $namespace,
                    'input_count' => count($inputs),
                    'embedding_count' => count($data),
                    'model' => $model,
                    'duration_ms' => round($embeddingDuration * 1000, 2),
                ]);
            } catch (\Throwable $e) {
                $embeddingDuration = microtime(true) - $embeddingStart;

                Log::error('Embedding generation failed', [
                    'agent' => $agent,
                    'namespace' => $namespace,
                    'document_count' => count($docs),
                    'model' => $model,
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'duration_ms' => round($embeddingDuration * 1000, 2),
                    'trace' => $e->getTraceAsString(),
                ]);

                throw new IngestException(
                    "Failed to generate embeddings for agent {$agent}: {$e->getMessage()}",
                    IngestException::EMBEDDING_FAILED,
                    $e
                );
            }

            if (count($data) !== count($docs)) {
                Log::warning('Embedding count mismatch', [
                    'agent' => $agent,
                    'namespace' => $namespace,
                    'docs' => count($docs),
                    'embeddings' => count($data),
                ]);
            }

            $dims = isset($data[0]['embedding']) ? count($data[0]['embedding']) : null;
            $driver = DB::connection()->getDriverName();
            $table = (new AgentVectorMemory)->getTable();

            // Store documents
            $storeStart = microtime(true);
            $inserted = 0;

            try {
                DB::transaction(function () use ($docs, $data, $agent, $namespace, $dims, $model, $driver, $table, &$inserted) {
                    $now = now();
                    foreach ($docs as $i => $doc) {
                        $content = (string) $doc['content'];
                        $vec = $data[$i]['embedding'] ?? null;

                        if (! is_array($vec)) {
                            Log::debug('Skipping document with invalid embedding', [
                                'agent' => $agent,
                                'namespace' => $namespace,
                                'doc_index' => $i,
                            ]);

                            continue;
                        }

                        $hash = hash('sha256', $content);

                        // Check for duplicates
                        $exists = AgentVectorMemory::where('agent_name', $agent)
                            ->where('content_hash', $hash)
                            ->exists();

                        if ($exists) {
                            Log::debug('Skipping duplicate document', [
                                'agent' => $agent,
                                'namespace' => $namespace,
                                'content_hash' => $hash,
                            ]);

                            continue;
                        }

                        $payload = [
                            'id' => (string) Str::ulid(),
                            'agent_name' => $agent,
                            'namespace' => $namespace,
                            'content' => $content,
                            'metadata' => isset($doc['metadata']) ? json_encode($doc['metadata'], JSON_UNESCAPED_UNICODE) : null,
                            'source' => $doc['source'] ?? null,
                            'source_id' => $doc['source_id'] ?? null,
                            'chunk_index' => (int) ($doc['chunk_index'] ?? 0),
                            'embedding_provider' => 'openai',
                            'embedding_model' => $model,
                            'embedding_dimensions' => $dims ?? count($vec),
                            'embedding_norm' => $this->norm($vec),
                            'content_hash' => $hash,
                            'token_count' => $this->estimateTokens($content),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];

                        if ($driver === 'pgsql' && $this->hasPgvectorExtension()) {
                            $payload['embedding'] = DB::raw($this->toPgVectorCastLiteral($vec));
                        } else {
                            $payload['embedding'] = json_encode($vec);
                        }

                        DB::table($table)->insert($payload);
                        $inserted++;
                    }
                });

                $storeDuration = microtime(true) - $storeStart;

                Log::info('Documents stored in database', [
                    'agent' => $agent,
                    'namespace' => $namespace,
                    'inserted' => $inserted,
                    'skipped' => count($docs) - $inserted,
                    'duration_ms' => round($storeDuration * 1000, 2),
                ]);

            } catch (\Throwable $e) {
                $storeDuration = microtime(true) - $storeStart;

                Log::error('Document storage failed', [
                    'agent' => $agent,
                    'namespace' => $namespace,
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'duration_ms' => round($storeDuration * 1000, 2),
                    'trace' => $e->getTraceAsString(),
                ]);

                throw new IngestException(
                    "Failed to store documents for agent {$agent}: {$e->getMessage()}",
                    IngestException::STORAGE_FAILED,
                    $e
                );
            }

            $totalDuration = microtime(true) - $startTime;

            Log::info('Document batch ingestion completed', [
                'agent' => $agent,
                'namespace' => $namespace,
                'count' => count($docs),
                'inserted' => $inserted,
                'dimensions' => $dims,
                'model' => $model,
                'total_duration_ms' => round($totalDuration * 1000, 2),
            ]);

            return ['count' => count($docs), 'inserted' => $inserted, 'dimensions' => $dims, 'model' => $model];

        } catch (IngestException $e) {
            $totalDuration = microtime(true) - $startTime;

            Log::error('Document batch ingestion failed', [
                'agent' => $agent,
                'namespace' => $namespace,
                'error_code' => $e->getCode(),
                'error' => $e->getMessage(),
                'total_duration_ms' => round($totalDuration * 1000, 2),
            ]);

            throw $e;
        } catch (\Throwable $e) {
            $totalDuration = microtime(true) - $startTime;

            Log::error('Document batch ingestion failed with unexpected error', [
                'agent' => $agent,
                'namespace' => $namespace,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'total_duration_ms' => round($totalDuration * 1000, 2),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new IngestException(
                "Unexpected error in document batch ingestion for agent {$agent}: {$e->getMessage()}",
                IngestException::UNEXPECTED_ERROR,
                $e
            );
        }
    }

 public function chunkText(string $text, int $chunkChars = 2000, int $overlap = 200): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        // Ensure valid UTF-8 to avoid broken JSON/logging and mid-sequence slicing.
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }

        $chunks = [];
        $len = mb_strlen($text, 'UTF-8');
        $start = 0;

        $chunkChars = max(1, $chunkChars);
        $overlap = max(0, min($overlap, $chunkChars - 1));

        while ($start < $len) {
            $end = min($start + $chunkChars, $len);
            $slice = mb_substr($text, $start, $end - $start, 'UTF-8');
            $chunks[] = $this->smartTrim($slice);

            if ($end >= $len) {
                break;
            }

            $start = max(0, $end - $overlap);
        }

        return $chunks;
    }

    protected function smartTrim(string $s): string
    {
        // try to avoid cutting in the middle of words
        $s = preg_replace('/\s+/', ' ', $s);

        return trim($s ?? '');
    }

    protected function estimateTokens(string $s): int
    {
        // Rough heuristic: ~4 chars per token
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

    protected function dot(array $a, array $b): float
    {
        $n = min(count($a), count($b));
        $sum = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $sum += ($a[$i] * $b[$i]);
        }

        return $sum;
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
        // returns SQL snippet like '\'[0.1,0.2]\'::vector'
        return "'".$this->toPgVectorLiteral($vec)."'::vector";
    }

    public function search(string $agent, ?string $namespace, string $query, int $limit = 5): array
    {
        $embedRes = $this->openai->embeddings($query);
        $vec = $embedRes['data'][0]['embedding'] ?? null;
        if (! is_array($vec)) {
            return ['results' => [], 'count' => 0];
        }
        $qNorm = $this->norm($vec);
        if ($qNorm <= 0) {
            return ['results' => [], 'count' => 0];
        }

        $driver = DB::connection()->getDriverName();
        $table = (new AgentVectorMemory)->getTable();

        if ($driver === 'pgsql' && $this->hasPgvectorExtension()) {
            $vecLiteral = $this->toPgVectorCastLiteral($vec);
            $bindings = [$agent];
            $where = 'agent_name = ? AND embedding_norm > 0'; // Filter out zero-norm vectors
            if ($namespace) {
                $where .= ' AND namespace = ?';
                $bindings[] = $namespace;
            }

            $sql = "SELECT id, content, source, source_id, chunk_index, (1 - (embedding <=> {$vecLiteral})) AS score
                    FROM {$table}
                    WHERE {$where}
                    ORDER BY embedding <=> {$vecLiteral}
                    LIMIT ".(int) max(1, $limit);

            $rows = DB::select($sql, $bindings);
            $results = array_map(function ($r) {
                return [
                    'id' => $r->id,
                    'content' => $r->content,
                    'score' => (float) $r->score,
                    'source' => $r->source,
                    'source_id' => $r->source_id,
                    'chunk_index' => (int) $r->chunk_index,
                ];
            }, $rows);

            return ['results' => $results, 'count' => count($results)];
        }

        // Fallback: compute in PHP using JSON vectors
        $q = AgentVectorMemory::query()->where('agent_name', $agent);

        if ($namespace) {
            $q->where('namespace', $namespace);
        }

        $rows = $q->limit(500)->get(['id', 'content', 'embedding', 'embedding_vector', 'embedding_norm', 'source', 'source_id', 'chunk_index']);

        $scored = [];
        foreach ($rows as $r) {
            $v = $r->embedding_vector ?? null;
            $rNorm = (float) ($r->embedding_norm ?? 0);
            if (! is_array($v) || $rNorm <= 0) {
                continue;
            }
            $dot = $this->dot($vec, $v);
            $cos = $dot / ($qNorm * $rNorm);
            $scored[] = [
                'id' => $r->id,
                'content' => $r->content,
                'score' => $cos,
                'source' => $r->source,
                'source_id' => $r->source_id,
                'chunk_index' => $r->chunk_index,
            ];
        }
        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);
        $top = array_slice($scored, 0, max(1, $limit));

        return [
            'results' => $top,
            'count' => count($top),
        ];
    }

    /**
     * Check if pgvector extension is available in PostgreSQL
     */
    protected function hasPgvectorExtension(): bool
    {
        static $hasExtension = null;

        if ($hasExtension !== null) {
            return $hasExtension;
        }

        try {
            $result = DB::select("SELECT 1 FROM pg_extension WHERE extname = 'vector'");
            $hasExtension = ! empty($result);
        } catch (\Exception $e) {
            $hasExtension = false;
        }

        return $hasExtension;
    }
}
