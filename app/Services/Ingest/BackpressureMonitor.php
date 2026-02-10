<?php

namespace App\Services\Ingest;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Monitors queue backpressure for ingestion pipelines.
 *
 * Checks pending job counts per queue and determines whether
 * to throttle new job dispatches based on configurable thresholds.
 */
class BackpressureMonitor
{
    /**
     * Get the number of pending jobs for a given queue.
     */
    public function getPendingCount(string $queue): int
    {
        return (int) DB::table('jobs')
            ->where('queue', $queue)
            ->whereNull('reserved_at')
            ->count();
    }

    /**
     * Check if a queue should be throttled based on backpressure thresholds.
     */
    public function shouldThrottle(string $queue): bool
    {
        $maxPending = (int) config('ingest-queues.backpressure.max_pending_per_queue', 500);
        $pending = $this->getPendingCount($queue);

        if ($pending >= $maxPending) {
            Log::warning('Ingest queue backpressure triggered', [
                'queue' => $queue,
                'pending' => $pending,
                'threshold' => $maxPending,
            ]);

            return true;
        }

        return false;
    }

    /**
     * Get health status of all configured ingest queues.
     *
     * @return array<string, array{queue: string, pending: int, threshold: int, throttled: bool}>
     */
    public function getQueueHealth(): array
    {
        $maxPending = (int) config('ingest-queues.backpressure.max_pending_per_queue', 500);
        $queues = config('ingest-queues.queues', []);
        $health = [];

        // Collect unique queue names
        $uniqueQueues = array_unique(array_values($queues));
        $uniqueQueues[] = config('ingest-queues.default_queue', 'ingest');
        $uniqueQueues = array_unique($uniqueQueues);

        foreach ($uniqueQueues as $queueName) {
            $pending = $this->getPendingCount($queueName);
            $health[$queueName] = [
                'queue' => $queueName,
                'pending' => $pending,
                'threshold' => $maxPending,
                'throttled' => $pending >= $maxPending,
            ];
        }

        return $health;
    }

    /**
     * Get the recommended delay (in seconds) for a throttled queue.
     */
    public function getThrottleDelay(): int
    {
        return (int) config('ingest-queues.backpressure.throttle_delay_seconds', 60);
    }

    /**
     * Resolve the queue name for a given source type.
     */
    public function resolveQueueName(string $sourceType): string
    {
        return config(
            "ingest-queues.queues.{$sourceType}",
            config('ingest-queues.default_queue', 'ingest')
        );
    }

    /**
     * Get the retry policy for a given source type.
     *
     * @return array{max_tries: int, backoff: array, retry_until_minutes: int, max_exceptions: int}
     */
    public function getRetryPolicy(string $sourceType): array
    {
        $default = [
            'max_tries' => 3,
            'backoff' => [60, 300, 900],
            'retry_until_minutes' => 30,
            'max_exceptions' => 2,
        ];

        return config("ingest-queues.retry_policies.{$sourceType}", $default);
    }
}
