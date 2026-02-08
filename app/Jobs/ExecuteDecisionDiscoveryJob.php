<?php

namespace App\Jobs;

use App\Agents\DecisionDiscoveryAgent;
use App\Jobs\Concerns\HasQueuePriority;
use App\Traits\BroadcastsJobProgress;
use App\Traits\DetectsEnvironment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExecuteDecisionDiscoveryJob implements ShouldQueue
{
    use BroadcastsJobProgress, DetectsEnvironment, Dispatchable, HasQueuePriority, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     * Exponential backoff: 60s, 180s (1 min, 3 mins)
     */
    public int $backoff = 60;

    /**
     * Calculate the number of seconds to wait before retrying.
     * Uses exponential backoff strategy.
     */
    public function backoff(): array
    {
        return [60, 180]; // 1 minute, 3 minutes
    }

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 900; // 15 minutes

    protected ?int $userId = null;

    /**
     * Create a new job instance.
     *
     * @param  int|null  $maxDecisions  Maximum decisions to discover
     * @param  string|null  $topic  Optional specific topic to search
     * @param  int|null  $userId  The user who initiated this job
     */
    public function __construct(
        protected ?int $maxDecisions = null,
        protected ?string $topic = null,
        ?int $userId = null
    ) {
        $this->userId = $userId ?? auth()->id();
        // AI agent for court decision discovery - use dedicated agents queue
        $this->onAgentsQueue();
    }

    public function getJobDisplayName(): string
    {
        return 'Decision Discovery' . ($this->topic ? ': ' . mb_substr($this->topic, 0, 40) : '');
    }

    /**
     * Execute the job.
     */
    public function handle(DecisionDiscoveryAgent $agent): void
    {
        Log::info('ExecuteDecisionDiscoveryJob - Starting', [
            'max_decisions' => $this->maxDecisions,
            'topic' => $this->topic,
            'attempt' => $this->attempts(),
            'max_tries' => $this->tries,
        ]);

        $broadcastJobId = 'decision_discovery_' . ($this->topic ? md5($this->topic) : 'all');

        try {
            if ($this->userId) {
                $this->broadcastStarted($this->userId, $broadcastJobId, [
                    'topic' => $this->topic,
                    'max_decisions' => $this->maxDecisions,
                ]);
            }

            // Configure agent based on parameters
            // Always set maxDecisionsGlobal to ensure clean state (prevents leakage if agent is reused)
            $agent->setMaxDecisionsGlobal($this->maxDecisions);

            // Execute discovery with optional topic filter
            $stats = $agent->discover($this->topic);

            if ($this->topic !== null) {
                Log::info('ExecuteDecisionDiscoveryJob - Completed with topic filter', [
                    'topic' => $this->topic,
                    'stats' => $stats,
                ]);

                $stats = $agent->discoverSingleTopic($this->topic);
            } else {
                Log::info('ExecuteDecisionDiscoveryJob - Using auto-generated topics');
                $stats = $agent->discover();
            }

            Log::info('ExecuteDecisionDiscoveryJob - Completed successfully', [
                'stats' => $stats,
            ]);

            if ($this->userId) {
                $this->broadcastCompleted($this->userId, $broadcastJobId, $stats ?? []);
            }

        } catch (\Exception $e) {
            $attempt = $this->attempts();
            $willRetry = $attempt < $this->tries;

            Log::error('ExecuteDecisionDiscoveryJob - Failed', [
                'error' => $e->getMessage(),
                'max_decisions' => $this->maxDecisions,
                'topic' => $this->topic,
                'attempt' => $attempt,
                'max_tries' => $this->tries,
                'will_retry' => $willRetry,
                'next_retry_in_seconds' => $willRetry ? $this->backoff()[$attempt - 1] ?? 60 : null,
                'trace' => $e->getTraceAsString(),
            ]);

            if ($this->userId) {
                $this->broadcastFailed($this->userId, $broadcastJobId, $e->getMessage(), 'Decision Discovery');
            }

            // Rethrow to allow Laravel's queue system to handle the failure and retry
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     * Called when all retry attempts have been exhausted.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ExecuteDecisionDiscoveryJob - Job failed permanently after all retries', [
            'error' => $exception->getMessage(),
            'max_decisions' => $this->maxDecisions,
            'topic' => $this->topic,
            'total_attempts' => $this->tries,
            'exception_type' => get_class($exception),
        ]);
    }

    /**
     * Dispatch job with environment detection
     * Returns sync result in dev, dispatches to queue in production
     */
    public static function dispatchWithEnvDetection(?int $maxDecisions = null, ?string $topic = null): array
    {
        $isDev = app()->environment('local', 'testing');

        if ($isDev) {
            // Run synchronously in dev/test environments
            Log::info('ExecuteDecisionDiscoveryJob - Running synchronously (dev environment)');

            try {
                $agent = app(DecisionDiscoveryAgent::class);

                // Always set maxDecisionsGlobal to ensure clean state (prevents leakage if agent is reused)
                $agent->setMaxDecisionsGlobal($maxDecisions);

                $stats = $agent->discover($topic);

                // Execute discovery - single topic or multi-topic
                if ($topic !== null) {
                    $stats = $agent->discoverSingleTopic($topic);
                } else {
                    $stats = $agent->discover();
                }

                return [
                    'mode' => 'sync',
                    'stats' => $stats,
                ];

            } catch (\Exception $e) {
                Log::error('ExecuteDecisionDiscoveryJob - Sync execution failed', [
                    'error' => $e->getMessage(),
                ]);

                return [
                    'mode' => 'sync',
                    'error' => $e->getMessage(),
                ];
            }
        } else {
            // Dispatch to queue in production
            Log::info('ExecuteDecisionDiscoveryJob - Dispatching to queue (production environment)');

            self::dispatch($maxDecisions, $topic);

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
            'agent:decision-discovery',
            $this->topic ? "topic:{$this->topic}" : 'topic:all',
        ];
    }
}
