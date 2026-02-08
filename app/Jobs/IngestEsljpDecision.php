<?php

namespace App\Jobs;

use App\Services\Esljp\EsljpIngestService;
use App\Traits\BroadcastsJobProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class IngestEsljpDecision implements ShouldQueue
{
    use BroadcastsJobProgress, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;

    public $backoff = [60, 120, 240, 480, 960];

    public $timeout = 300;

    public $deleteWhenMissingModels = false;

    protected ?int $userId = null;

    public function __construct(
        public string $decisionId,
        public array $options = [],
        ?int $userId = null,
    ) {
        $this->userId = $userId ?? auth()->id();
        $this->onQueue($options['queue'] ?? 'default');
    }

    public function getJobDisplayName(): string
    {
        return 'Ingesting ESLJP Decision: '.$this->decisionId;
    }

    public function handle(EsljpIngestService $ingestService): void
    {
        Log::info('IngestEsljpDecision job starting', [
            'decision_id' => $this->decisionId,
            'attempt' => $this->attempts(),
            'max_tries' => $this->tries,
        ]);

        $broadcastJobId = 'ingest_esljp_'.$this->decisionId;

        try {
            if ($this->userId) {
                $this->broadcastStarted($this->userId, $broadcastJobId, [
                    'decision_id' => $this->decisionId,
                ]);
            }

            $result = $ingestService->ingestByIds([$this->decisionId], $this->options);
            $inserted = (int) ($result['inserted'] ?? 0);
            $errors = (int) ($result['errors'] ?? 0);

            if ($inserted > 0 && $errors === 0) {
                if ($this->userId) {
                    $this->broadcastCompleted($this->userId, $broadcastJobId, [
                        'inserted' => $inserted,
                    ]);
                }

                Log::info('ESLJP decision ingested successfully', [
                    'decision_id' => $this->decisionId,
                    'inserted' => $inserted,
                ]);

                return;
            }

            $message = $errors > 0 ? 'ESLJP ingestion reported errors' : 'ESLJP ingestion produced no chunks';
            if ($this->userId) {
                $this->broadcastFailed($this->userId, $broadcastJobId, $message, 'Ingestion');
            }

            Log::warning('ESLJP decision ingestion incomplete', [
                'decision_id' => $this->decisionId,
                'inserted' => $inserted,
                'errors' => $errors,
            ]);
        } catch (\Throwable $e) {
            if ($this->userId) {
                $this->broadcastFailed($this->userId, $broadcastJobId, $e->getMessage(), 'Ingestion');
            }

            Log::error('ESLJP decision ingestion failed', [
                'decision_id' => $this->decisionId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
