<?php

namespace App\Jobs;

use App\DTOs\MatchingResult;
use App\Services\CaseDecisionMatchingService;
use App\Traits\BroadcastsJobProgress;
use App\Traits\DetectsEnvironment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReconcileUnmatchedCasesJob implements ShouldQueue
{
    use BroadcastsJobProgress, DetectsEnvironment, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 600; // 10 minutes

    public function __construct(
        public int $limit = 100,
        protected ?int $userId = null
    ) {
        $this->userId = $this->userId ?? auth()->id();
    }

    public function getJobDisplayName(): string
    {
        return 'Reconciling Unmatched Cases';
    }

    public function handle(CaseDecisionMatchingService $service): void
    {
        $broadcastJobId = 'reconcile_cases_' . now()->timestamp;

        Log::info('ReconcileUnmatchedCasesJob - Starting', [
            'limit' => $this->limit,
            'attempt' => $this->attempts(),
        ]);

        try {
            if (isset($this->userId) && $this->userId) {
                $this->broadcastStarted($this->userId, $broadcastJobId, [
                    'limit' => $this->limit,
                ]);
            }

            $result = $service->matchUnmatchedCases($this->limit, 'scheduled_reconciliation');

            Log::info('ReconcileUnmatchedCasesJob - Completed', $result->toArray());

            if (isset($this->userId) && $this->userId) {
                $this->broadcastCompleted($this->userId, $broadcastJobId, $result->toArray());
            }

        } catch (\Exception $e) {
            Log::error('ReconcileUnmatchedCasesJob - Failed', [
                'limit' => $this->limit,
                'error' => $e->getMessage(),
            ]);

            if (isset($this->userId) && $this->userId) {
                $this->broadcastFailed($this->userId, $broadcastJobId, $e->getMessage(), 'Reconciliation');
            }

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ReconcileUnmatchedCasesJob - Failed permanently', [
            'limit' => $this->limit,
            'error' => $exception->getMessage(),
            'attempts' => $this->tries,
        ]);
    }

    /**
     * Dispatch job with environment detection.
     * Runs synchronously in dev/test, dispatches to queue in production.
     */
    public static function dispatchWithEnvDetection(int $limit = 100): array
    {
        $isDev = app()->environment('local', 'testing');

        if ($isDev) {
            // Run synchronously in dev/test environments
            Log::info('ReconcileUnmatchedCasesJob - Running synchronously (dev environment)', [
                'limit' => $limit,
            ]);

            try {
                $service = app(CaseDecisionMatchingService::class);
                $result = $service->matchUnmatchedCases($limit, 'scheduled_reconciliation');

                Log::info('ReconcileUnmatchedCasesJob - Sync completed', $result->toArray());

                return [
                    'mode' => 'sync',
                    'result' => $result->toArray(),
                ];

            } catch (\Exception $e) {
                Log::error('ReconcileUnmatchedCasesJob - Sync execution failed', [
                    'error' => $e->getMessage(),
                ]);

                return [
                    'mode' => 'sync',
                    'error' => $e->getMessage(),
                ];
            }
        } else {
            // Dispatch to queue in production
            Log::info('ReconcileUnmatchedCasesJob - Dispatching to queue (production environment)', [
                'limit' => $limit,
            ]);

            self::dispatch($limit);

            return [
                'mode' => 'async',
                'message' => 'Job dispatched to queue',
            ];
        }
    }

    /**
     * Get tags for job monitoring.
     */
    public function tags(): array
    {
        return [
            'case-matching',
            'reconciliation',
            "limit:{$this->limit}",
        ];
    }
}
