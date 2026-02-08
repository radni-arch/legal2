<?php

namespace App\Jobs;

use App\Jobs\SyncTextractToGraph;
use App\Models\EmbeddingBatch;
use App\Models\TextractJob;
use App\Services\CourtDecisionVectorStoreService;
use App\Services\OpenAIService;
use App\Traits\BroadcastsJobProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateEmbeddingsJob implements ShouldQueue
{
    use BroadcastsJobProgress, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public array $backoff = [120, 600];

    public int $timeout = 900;

    // Accept both integer (auto-increment IDs) and string (UUIDs) to match tests and DB
    public int|string $sourceId;

    public string $sourceType; // 'textract_job', 'batch'

    public ?string $embeddingBatchId;

    protected ?int $userId = null;

    /**
     * Create a new job instance.
     */
    public function __construct(int|string $sourceId, string $sourceType = 'textract_job', ?string $embeddingBatchId = null, ?int $userId = null)
    {
        $this->sourceId = $sourceId;
        $this->sourceType = $sourceType;
        $this->embeddingBatchId = $embeddingBatchId;
        $this->userId = $userId ?? auth()->id();

        $this->onQueue('embeddings');
    }

    public function getJobDisplayName(): string
    {
        return 'Generating Embeddings';
    }

    /**
     * Execute the job.
     */
    public function handle(OpenAIService $openai, CourtDecisionVectorStoreService $vectorStore): void
    {
        $startTime = microtime(true);

        $broadcastJobId = $this->sourceType . '_' . $this->sourceId;

        try {
            if ($this->userId) {
                $this->broadcastStarted($this->userId, $broadcastJobId, [
                    'source_type' => $this->sourceType,
                    'source_id' => $this->sourceId,
                ]);
            }

            if ($this->sourceType === 'textract_job') {
                $this->processTextractJob($openai, $vectorStore);
            } elseif ($this->sourceType === 'batch') {
                $this->processBatch($openai, $vectorStore);
            }

            $duration = microtime(true) - $startTime;

            Log::info('GenerateEmbeddingsJob - Completed', [
                'source_id' => $this->sourceId,
                'source_type' => $this->sourceType,
                'duration_seconds' => round($duration, 2),
            ]);

            if ($this->userId) {
                $this->broadcastCompleted($this->userId, $broadcastJobId, [
                    'duration_seconds' => round($duration, 2),
                ]);
            }

        } catch (\Exception $e) {
            Log::error('GenerateEmbeddingsJob - Failed', [
                'source_id' => $this->sourceId,
                'source_type' => $this->sourceType,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            // Mark embedding as failed on last attempt
            if ($this->sourceType === 'textract_job') {
                $failedJob = TextractJob::find($this->sourceId);
                if ($failedJob && $this->attempts() >= $this->tries) {
                    $failedJob->update([
                        'embedding_status' => 'failed',
                        'error' => 'Embedding generation failed: '.$e->getMessage(),
                    ]);
                }
            }
            if ($this->userId) {
                $this->broadcastFailed($this->userId, $broadcastJobId, $e->getMessage(), 'Embedding Generation');
            }

            if ($this->attempts() < $this->tries) {
                throw $e;
            }
        }
    }

    /**
     * Process single Textract job
     */
    protected function processTextractJob(OpenAIService $openai, CourtDecisionVectorStoreService $vectorStore): void
    {
        $job = TextractJob::find($this->sourceId);

        if (! $job) {
            Log::warning('GenerateEmbeddingsJob - Job not found', [
                'job_id' => $this->sourceId,
                'batch_id' => $this->embeddingBatchId,
            ]);

            // If this is part of a batch, throw exception so parent can handle failure
            if ($this->embeddingBatchId) {
                throw new \RuntimeException("Textract job not found: {$this->sourceId}");
            }

            return;
        }

        // Mark embedding as processing
        $job->update(['embedding_status' => 'processing']);

        // Get the extracted text from the effective_content attribute
        // This handles both extracted_content and manual_content (if manually edited)
        $text = $job->effective_content;

        if (empty($text)) {
            Log::warning('GenerateEmbeddingsJob - No text to embed', [
                'job_id' => $this->sourceId,
                'extracted_content_length' => strlen($job->extracted_content ?? ''),
                'manual_content_length' => strlen($job->manual_content ?? ''),
                'status' => $job->status,
            ]);

            return;
        }

        // Chunk text if needed (for very large documents)
        $chunkSize = config('distributed-processing.embeddings.chunk_size', 1500);
        $textLength = mb_strlen($text);

        if ($textLength > $chunkSize * 2) {
            // For large documents, use first chunk for embedding
            // In production, you may want to chunk and process all parts
            $text = mb_substr($text, 0, $chunkSize);
            Log::info('GenerateEmbeddingsJob - Text truncated for embedding', [
                'job_id' => $this->sourceId,
                'original_length' => $textLength,
                'truncated_length' => mb_strlen($text),
            ]);
        }

        // Generate embedding
        $embedding = $openai->embeddings($text, config('distributed-processing.embeddings.model', 'text-embedding-3-small'));

        if (isset($embedding['data'][0]['embedding'])) {
            $vector = $embedding['data'][0]['embedding'];
            $tokens = $embedding['usage']['total_tokens'] ?? 0;

            // Store in vector database
            $vectorStore->upsert([
                [
                    'id' => 'textract_'.$job->id,
                    'vector' => $vector,
                    'metadata' => [
                        'source' => 'textract',
                        'job_id' => $job->id,
                        'drive_file_id' => $job->drive_file_id,
                        'drive_file_name' => $job->drive_file_name,
                        'case_id' => $job->case_id,
                        'text_length' => strlen($text),
                        'manually_edited' => $job->manually_edited,
                        'created_at' => now()->toIso8601String(),
                    ],
                ],
            ]);

            // Update job embedding status
            $job->markEmbeddingSynced();

            // Dispatch graph sync if Neo4j is enabled
            if (config('neo4j.sync.enabled', false)) {
                SyncTextractToGraph::dispatch($job->id)
                    ->delay(now()->addSeconds(5));

                Log::info('GenerateEmbeddingsJob - Dispatched graph sync', [
                    'job_id' => $this->sourceId,
                ]);
            }

            // Update embedding batch if exists
            if ($this->embeddingBatchId) {
                $batch = EmbeddingBatch::find($this->embeddingBatchId);
                if ($batch) {
                    $batch->incrementProcessed($tokens, false);
                }
            }

            Log::info('GenerateEmbeddingsJob - Embedding created', [
                'job_id' => $this->sourceId,
                'vector_dimension' => count($vector),
                'tokens_used' => $tokens,
                'text_length' => strlen($text),
            ]);
        }
    }

    /**
     * Process batch of items
     */
    protected function processBatch(OpenAIService $openai, CourtDecisionVectorStoreService $vectorStore): void
    {
        $batch = EmbeddingBatch::find($this->sourceId);

        if (! $batch) {
            Log::warning('GenerateEmbeddingsJob - Batch not found', [
                'batch_id' => $this->sourceId,
            ]);

            return;
        }

        $batch->markProcessing();

        $itemIds = $batch->item_ids ?? [];

        foreach ($itemIds as $itemId) {
            // If the ID isn't a valid integer (textract_jobs.id is bigint), treat as missing
            if (! is_int($itemId) && ! (is_string($itemId) && ctype_digit($itemId))) {
                Log::warning('GenerateEmbeddingsJob - Batch item has invalid ID type', [
                    'batch_id' => $this->sourceId,
                    'item_id' => $itemId,
                ]);
                try {
                    $batch->incrementProcessed(0, true);
                } catch (\Throwable $t) {
                    Log::warning('GenerateEmbeddingsJob - Could not update batch counters', [
                        'batch_id' => $this->sourceId,
                        'item_id' => $itemId,
                        'error' => $t->getMessage(),
                    ]);
                }

                continue;
            }

            // If the referenced Textract job doesn't exist, mark as failed and continue
            $textract = TextractJob::find($itemId);
            if (! $textract) {
                Log::warning('GenerateEmbeddingsJob - Batch item not found', [
                    'batch_id' => $this->sourceId,
                    'item_id' => $itemId,
                ]);

                // Safely update batch counters without throwing
                try {
                    $batch->incrementProcessed(0, true);
                } catch (\Throwable $t) {
                    Log::warning('GenerateEmbeddingsJob - Could not update batch counters', [
                        'batch_id' => $this->sourceId,
                        'item_id' => $itemId,
                        'error' => $t->getMessage(),
                    ]);
                }

                continue;
            }

            try {
                // Dispatch individual embedding job
                GenerateEmbeddingsJob::dispatch($itemId, 'textract_job', $batch->id)
                    ->onQueue('embeddings');

            } catch (\Exception $e) {
                Log::error('GenerateEmbeddingsJob - Batch item failed', [
                    'batch_id' => $this->sourceId,
                    'item_id' => $itemId,
                    'error' => $e->getMessage(),
                ]);

                try {
                    // Update counters on failure
                    $batch->incrementProcessed(0, true);
                } catch (\Throwable $batchUpdateException) {
                    // If we can't update batch due to transaction state, log and continue
                    Log::warning('GenerateEmbeddingsJob - Could not update batch counters', [
                        'batch_id' => $this->sourceId,
                        'item_id' => $itemId,
                        'error' => $batchUpdateException->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return ['embeddings', 'source:'.$this->sourceType, 'id:'.$this->sourceId];
    }

    /**
     * Handle a job failure after all retries are exhausted.
     */
    public function failed(\Throwable $e): void
    {
        Log::error('GenerateEmbeddingsJob - Permanently failed', [
            'source_id' => $this->sourceId,
            'source_type' => $this->sourceType,
            'attempts' => $this->tries,
            'error' => $e->getMessage(),
        ]);

        if ($this->sourceType === 'textract_job') {
            $job = TextractJob::find($this->sourceId);
            if ($job) {
                $job->update([
                    'embedding_status' => 'failed',
                    'error' => sprintf(
                        'Embedding generation permanently failed after %d attempts: %s',
                        $this->tries,
                        $e->getMessage()
                    ),
                ]);
            }
        } elseif ($this->sourceType === 'batch') {
            $batch = EmbeddingBatch::find($this->sourceId);
            if ($batch) {
                $batch->markFailed($e->getMessage());
            }
        }

        if ($this->userId) {
            $this->broadcastFailed(
                $this->userId,
                $this->sourceType . '_' . $this->sourceId,
                'Permanently failed after ' . $this->tries . ' attempts: ' . $e->getMessage(),
                'Embedding Generation'
            );
        }
    }
}
