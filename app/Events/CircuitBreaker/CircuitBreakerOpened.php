<?php

namespace App\Events\CircuitBreaker;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a circuit breaker transitions to OPEN state
 *
 * This event is triggered when the circuit breaker opens due to too many failures,
 * blocking subsequent requests to prevent cascading failures.
 */
class CircuitBreakerOpened
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance
     *
     * @param  string  $serviceName  The name of the service being monitored
     * @param  int  $failureCount  Number of failures that triggered the opening
     * @param  \Illuminate\Support\Carbon  $openedAt  Timestamp when circuit opened
     * @param  array  $lastError  Details of the last error that occurred
     * @param  int  $retryAfterSeconds  Seconds to wait before attempting retry
     */
    public function __construct(
        public string $serviceName,
        public int $failureCount,
        public \Illuminate\Support\Carbon $openedAt,
        public array $lastError,
        public int $retryAfterSeconds
    ) {}
}
