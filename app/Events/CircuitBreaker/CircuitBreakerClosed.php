<?php

namespace App\Events\CircuitBreaker;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a circuit breaker transitions to CLOSED state
 *
 * This event is triggered when the circuit breaker successfully recovers after
 * testing in half-open state, allowing normal traffic to resume.
 */
class CircuitBreakerClosed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance
     *
     * @param  string  $serviceName  The name of the service being monitored
     * @param  int  $successCount  Number of successes that triggered the closing
     * @param  \Illuminate\Support\Carbon  $closedAt  Timestamp when circuit closed
     */
    public function __construct(
        public string $serviceName,
        public int $successCount,
        public \Illuminate\Support\Carbon $closedAt
    ) {}
}
