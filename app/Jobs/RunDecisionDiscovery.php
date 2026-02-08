<?php

namespace App\Jobs;

use App\Agents\DecisionDiscoveryAgent;
use App\Traits\BroadcastsJobProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queue job for running decision discovery with retry mechanism
 *
 * This job provides:
 * - Automatic retry on failure (3 attempts)
 * - Exponential backoff between retries
 * - Timeout protection (30 minutes)
 * - Error logging and tracking
 */
class RunDecisionDiscovery implements ShouldQueue
{
    use BroadcastsJobProgress, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 1800; // 30 minutes

    /**
     * The number of seconds to wait before retrying the job.
     */
    public array $backoff = [
        60,    // First retry after 1 minute
        300,   // Second retry after 5 minutes
        900,   // Third retry after 15 minutes
    ];

    protected ?int $userId = null;

    /**
     * Create a new job instance.
     *
     * @param  int  $topics  Number of topics to generate
     * @param  int  $perTopic  Decisions to evaluate per topic
     * @param  int  $ingest  Top decisions to ingest per topic
     * @param  float  $threshold  Relevance threshold (0-100)
     * @param  int|null  $userId  The user who initiated this job
     */
    public function __construct(
        protected int $topics = 5,
        protected int $perTopic = 50,
        protected int $ingest = 10,
        protected float $threshold = 70.0,
        ?int $userId = null
    ) {
        $this->userId = $userId ?? auth()->id();
    }

    public function getJobDisplayName(): string
    {
        return 'Decision Discovery Run';
    }

    /**
     * Execute the job.
     */
    public function handle(DecisionDiscoveryAgent $agent): void
    {
        $attempt = $this->attempts();

        Log::info('RunDecisionDiscovery - Starting', [
            'attempt' => $attempt,
            'max_attempts' => $this->tries,
            'config' => [
                'topics' => $this->topics,
                'per_topic' => $this->perTopic,
                'ingest' => $this->ingest,
                'threshold' => $this->threshold,
            ],
        ]);

        $broadcastJobId = 'run_discovery_' . $attempt . '_' . now()->timestamp;

        try {
            if ($this->userId) {
                $this->broadcastStarted($this->userId, $broadcastJobId, [
                    'topics' => $this->topics,
                    'per_topic' => $this->perTopic,
                ]);
            }

            // Configure agent
            $agent->setTopicsPerRun($this->topics)
                ->setDecisionsPerTopic($this->perTopic)
                ->setIngestPerTopic($this->ingest)
                ->setRelevanceThreshold($this->threshold);

            // Execute discovery
            $stats = $agent->discover();

            Log::info('RunDecisionDiscovery - Completed successfully', [
                'attempt' => $attempt,
                'stats' => $stats,
            ]);

            if ($this->userId) {
                $this->broadcastCompleted($this->userId, $broadcastJobId, $stats ?? []);
            }

        } catch (\Exception $e) {
            Log::error('RunDecisionDiscovery - Failed', [
                'attempt' => $attempt,
                'max_attempts' => $this->tries,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($this->userId) {
                $this->broadcastFailed($this->userId, $broadcastJobId, $e->getMessage(), 'Decision Discovery');
            }

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('RunDecisionDiscovery - Job failed permanently after all retries', [
            'total_attempts' => $this->tries,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Could send notification to admin here
        // Example: Notification::route('mail', config('mail.admin'))
        //             ->notify(new DecisionDiscoveryFailedNotification($exception));
    }

    /**
     * Get tags for job monitoring.
     */
    public function tags(): array
    {
        return [
            'agent:decision-discovery',
            'scheduled:daily',
            "topics:{$this->topics}",
        ];
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): \DateTime
    {
        // Allow retries for up to 2 hours
        return now()->addHours(2);
    }
}
