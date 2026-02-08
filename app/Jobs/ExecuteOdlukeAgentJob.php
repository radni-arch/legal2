<?php

namespace App\Jobs;

use App\Agents\OdlukeAgent;
use App\Traits\BroadcastsJobProgress;
use App\Traits\DetectsEnvironment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ExecuteOdlukeAgentJob implements ShouldQueue
{
    use BroadcastsJobProgress, DetectsEnvironment, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 300; // 5 minutes

    protected ?int $userId = null;

    /**
     * Create a new job instance.
     *
     * @param  string  $query  User query
     * @param  array  $context  Additional context
     * @param  string|null  $cacheKey  Optional cache key for storing result
     * @param  int|null  $userId  The user who initiated this job
     */
    public function __construct(
        protected string $query,
        protected array $context = [],
        protected ?string $cacheKey = null,
        ?int $userId = null
    ) {
        $this->userId = $userId ?? auth()->id();
    }

    public function getJobDisplayName(): string
    {
        return 'Odluke Agent: ' . mb_substr($this->query, 0, 40);
    }

    /**
     * Execute the job.
     */
    public function handle(OdlukeAgent $agent): void
    {
        $this->logEnvironment('ExecuteOdlukeAgentJob');

        $startTime = microtime(true);
        Log::info('Starting OdlukeAgent job', [
            'query' => substr($this->query, 0, 100),
            'environment' => $this->isProduction() ? 'production' : 'dev',
        ]);

        $broadcastJobId = 'odluke_' . substr(md5($this->query), 0, 12);

        try {
            if ($this->userId) {
                $this->broadcastStarted($this->userId, $broadcastJobId, [
                    'query' => mb_substr($this->query, 0, 100),
                ]);
            }

            // Execute MCP tool chain
            $result = $agent->execute($this->query, $this->context);

            $duration = round(microtime(true) - $startTime, 2);

            // Cache result if cache key provided
            if ($this->cacheKey) {
                Cache::put($this->cacheKey, [
                    'status' => 'completed',
                    'result' => $result,
                    'duration' => $duration,
                    'completed_at' => now()->toIso8601String(),
                ], 3600); // 1 hour TTL
            }

            Log::info('OdlukeAgent completed successfully', [
                'duration' => $duration,
                'cache_key' => $this->cacheKey,
            ]);

            if ($this->userId) {
                $this->broadcastCompleted($this->userId, $broadcastJobId, [
                    'duration' => $duration,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('OdlukeAgent execution failed', [
                'query' => $this->query,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Cache error if cache key provided
            if ($this->cacheKey) {
                Cache::put($this->cacheKey, [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                    'completed_at' => now()->toIso8601String(),
                ], 3600);
            }

            if ($this->userId) {
                $this->broadcastFailed($this->userId, $broadcastJobId, $e->getMessage(), 'Odluke Agent');
            }

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ExecuteOdlukeAgentJob failed permanently', [
            'query' => substr($this->query, 0, 100),
            'error' => $exception->getMessage(),
        ]);

        // Update cache with failure
        if ($this->cacheKey) {
            Cache::put($this->cacheKey, [
                'status' => 'failed',
                'error' => $exception->getMessage(),
                'completed_at' => now()->toIso8601String(),
            ], 3600);
        }
    }

    /**
     * Get tags for job monitoring.
     */
    public function tags(): array
    {
        return [
            'agent:odluke',
            'query:'.substr(md5($this->query), 0, 8),
        ];
    }

    /**
     * Dispatch job with environment detection.
     */
    public static function dispatchWithEnvDetection(string $query, array $context = [], ?string $cacheKey = null): mixed
    {
        $job = new self($query, $context, $cacheKey);

        $isProduction = app()->environment('production');
        $isLocalhost = in_array(request()->ip(), ['127.0.0.1', '::1', 'localhost']);

        if ($isProduction && ! $isLocalhost) {
            // Production: Dispatch to queue
            Log::info('Dispatching ExecuteOdlukeAgentJob to queue (production)');

            // Set cache to "running" state
            if ($cacheKey) {
                Cache::put($cacheKey, [
                    'status' => 'running',
                    'started_at' => now()->toIso8601String(),
                ], 3600);
            }

            return self::dispatch($query, $context, $cacheKey);
        } else {
            // Localhost/dev: Run synchronously
            Log::info('Running ExecuteOdlukeAgentJob synchronously (dev/localhost)');
            $agent = app(OdlukeAgent::class);
            $job->handle($agent);

            // Return result from cache if available
            return $cacheKey ? Cache::get($cacheKey) : ['mode' => 'sync', 'completed' => true];
        }
    }
}
