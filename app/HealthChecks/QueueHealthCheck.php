<?php

namespace App\HealthChecks;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class QueueHealthCheck
{
    public function __invoke(): HealthCheckResult
    {
        try {
            $connection = config('queue.default', 'sync');
            $size = Queue::connection($connection)->size();

            $data = [
                'driver' => $connection,
                'queue_size' => $size,
            ];

            if ($size > 1000) {
                return HealthCheckResult::degraded(
                    "Queue backlog is high: {$size} jobs pending",
                    $data
                );
            }

            return HealthCheckResult::healthy($data, 'Queue is operational');
        } catch (\Exception $e) {
            Log::error('Queue health check failed', ['error' => $e->getMessage()]);

            return HealthCheckResult::unhealthy(
                'Queue connection failed: ' . $e->getMessage(),
                ['error' => $e->getMessage()]
            );
        }
    }
}
