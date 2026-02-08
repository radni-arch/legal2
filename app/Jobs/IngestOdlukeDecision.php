<?php

namespace App\Jobs;

use App\Models\FailedIngestion;
use App\Services\Odluke\OdlukeIngestService;
use App\Traits\BroadcastsJobProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class IngestOdlukeDecision implements ShouldQueue
{
    use BroadcastsJobProgress, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 5;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public $backoff = [60, 120, 240, 480, 960]; // 1min, 2min, 4min, 8min, 16min

    /**
     * The maximum number of seconds the job can run.
     */
    public $timeout = 300; // 5 minutes

    /**
     * Delete the job if its models no longer exist.
     */
    public $deleteWhenMissingModels = false;

    protected ?int $userId = null;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $decisionId,
        public array $options = [],
        public ?string $failedIngestionId = null,
        ?int $userId = null,
    ) {
        $this->userId = $userId ?? auth()->id();
        $this->onQueue($options['queue'] ?? 'default');
    }

    public function getJobDisplayName(): string
    {
        return 'Ingesting Decision: ' . $this->decisionId;
    }

    /**
     * Execute the job.
     */
    public function handle(OdlukeIngestService $ingestService): void
    {
        $attempt = $this->attempts();

        Log::info('IngestOdlukeDecision job starting', [
            'decision_id' => $this->decisionId,
            'attempt' => $attempt,
            'max_tries' => $this->tries,
        ]);

        $broadcastJobId = 'ingest_odluke_' . $this->decisionId;

        try {
            if ($this->userId) {
                $this->broadcastStarted($this->userId, $broadcastJobId, [
                    'decision_id' => $this->decisionId,
                ]);
            }

            // Attempt ingestion
            $result = $ingestService->ingestByIds([$this->decisionId], $this->options);

            // Check if ingestion was successful
            if ($this->wasSuccessful($result)) {
                $this->handleSuccess($result);
            } else {
                $this->handleFailure($result);
            }
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * Check if ingestion was successful
     */
    protected function wasSuccessful(array $result): bool
    {
        // Success if at least one chunk was inserted
        $inserted = $result['inserted'] ?? 0;
        $errors = $result['errors'] ?? 0;
        $skipped = $result['skipped'] ?? 0;

        return $inserted > 0 && $errors === 0;
    }

    /**
     * Handle successful ingestion
     */
    protected function handleSuccess(array $result): void
    {
        Log::info('Decision ingested successfully', [
            'decision_id' => $this->decisionId,
            'inserted' => $result['inserted'] ?? 0,
            'graph_synced' => $result['graph_synced'] ?? 0,
        ]);

        if ($this->userId) {
            $this->broadcastCompleted($this->userId, 'ingest_odluke_' . $this->decisionId, [
                'inserted' => $result['inserted'] ?? 0,
                'graph_synced' => $result['graph_synced'] ?? 0,
            ]);
        }

        // Mark failed ingestion as succeeded if it exists
        if ($this->failedIngestionId) {
            $failedIngestion = FailedIngestion::find($this->failedIngestionId);
            if ($failedIngestion) {
                $failedIngestion->markSucceeded([
                    'result' => $result,
                    'completed_at' => now()->toIso8601String(),
                    'final_attempt' => $this->attempts(),
                ]);
            }
        } else {
            // Check for any existing failed ingestion records
            $existing = FailedIngestion::where('decision_id', $this->decisionId)
                ->where('source_type', 'odluke')
                ->whereIn('status', [FailedIngestion::STATUS_PENDING, FailedIngestion::STATUS_RETRYING])
                ->first();

            if ($existing) {
                $existing->markSucceeded([
                    'result' => $result,
                    'completed_at' => now()->toIso8601String(),
                    'final_attempt' => $this->attempts(),
                ]);
            }
        }
    }

    /**
     * Handle failed ingestion (skipped or errors)
     */
    protected function handleFailure(array $result): void
    {
        $skipped = $result['skipped'] ?? 0;
        $errors = $result['errors'] ?? 0;

        $failureReason = match (true) {
            $skipped > 0 => FailedIngestion::REASON_EMPTY_TEXT,
            $errors > 0 => FailedIngestion::REASON_EXTRACTION_ERROR,
            default => FailedIngestion::REASON_UNKNOWN,
        };

        $errorMessage = match ($failureReason) {
            FailedIngestion::REASON_EMPTY_TEXT => 'Empty text extracted from both HTML and PDF',
            FailedIngestion::REASON_EXTRACTION_ERROR => 'Text extraction failed',
            default => 'Unknown ingestion error',
        };

        $this->recordFailure($failureReason, $errorMessage, [
            'result' => $result,
            'attempt' => $this->attempts(),
        ]);

        // Release back to queue for retry if not max attempts
        if ($this->attempts() < $this->tries) {
            $delay = $this->backoff[$this->attempts() - 1] ?? 60;
            $this->release($delay);

            Log::warning('Decision ingestion failed, will retry', [
                'decision_id' => $this->decisionId,
                'attempt' => $this->attempts(),
                'next_retry_in' => $delay.'s',
                'reason' => $failureReason,
            ]);
        } else {
            Log::error('Decision ingestion failed permanently', [
                'decision_id' => $this->decisionId,
                'attempts' => $this->attempts(),
                'reason' => $failureReason,
            ]);

            $this->fail(new \Exception($errorMessage));
        }
    }

    /**
     * Handle exception during ingestion
     */
    protected function handleException(\Throwable $e): void
    {
        if ($this->userId) {
            $this->broadcastFailed($this->userId, 'ingest_odluke_' . $this->decisionId, $e->getMessage(), 'Ingestion');
        }

        $failureReason = match (true) {
            str_contains($e->getMessage(), 'HTTP') || str_contains($e->getMessage(), 'Connection') => FailedIngestion::REASON_NETWORK_ERROR,
            str_contains($e->getMessage(), 'graph') => FailedIngestion::REASON_GRAPH_SYNC_ERROR,
            str_contains($e->getMessage(), 'embed') => FailedIngestion::REASON_EMBEDDING_ERROR,
            default => FailedIngestion::REASON_UNKNOWN,
        };

        $this->recordFailure($failureReason, $e->getMessage(), [
            'exception' => get_class($e),
            'trace' => $e->getTraceAsString(),
            'attempt' => $this->attempts(),
        ]);

        // Release back to queue for retry if not max attempts
        if ($this->attempts() < $this->tries) {
            $delay = $this->backoff[$this->attempts() - 1] ?? 60;
            $this->release($delay);

            Log::warning('Decision ingestion exception, will retry', [
                'decision_id' => $this->decisionId,
                'attempt' => $this->attempts(),
                'next_retry_in' => $delay.'s',
                'error' => $e->getMessage(),
            ]);
        } else {
            Log::error('Decision ingestion failed permanently after exception', [
                'decision_id' => $this->decisionId,
                'attempts' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            $this->fail($e);
        }
    }

    /**
     * Record failure in database
     */
    protected function recordFailure(string $failureReason, string $errorMessage, array $errorDetails): void
    {
        if ($this->failedIngestionId) {
            $failedIngestion = FailedIngestion::find($this->failedIngestionId);
            if ($failedIngestion) {
                $failedIngestion->recordFailedAttempt($errorMessage, $errorDetails);
            }
        } else {
            FailedIngestion::recordFailure(
                decisionId: $this->decisionId,
                failureReason: $failureReason,
                errorMessage: $errorMessage,
                errorDetails: $errorDetails,
                ingestionOptions: $this->options,
                sourceType: 'odluke',
                maxAttempts: $this->tries
            );
        }
    }

    /**
     * Handle a job failure (called when job finally fails).
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('IngestOdlukeDecision job failed permanently', [
            'decision_id' => $this->decisionId,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
        ]);

        // Ensure failure is recorded
        $this->recordFailure(
            FailedIngestion::REASON_UNKNOWN,
            $exception->getMessage(),
            [
                'exception' => get_class($exception),
                'final_attempt' => $this->attempts(),
                'failed_at' => now()->toIso8601String(),
            ]
        );
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return ['odluke', 'ingestion', 'decision:'.$this->decisionId];
    }
}
