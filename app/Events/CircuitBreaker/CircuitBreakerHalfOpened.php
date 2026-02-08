<?php

namespace App\Events\CircuitBreaker;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a circuit breaker transitions to HALF_OPEN state
 *
 * This event is triggered when the circuit breaker enters testing mode after
 * the timeout period, allowing limited traffic to test if the service has recovered.
 */
class CircuitBreakerHalfOpened
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance
     *
     * @param  string  $serviceName  The name of the service being monitored
     * @param  \Illuminate\Support\Carbon  $halfOpenedAt  Timestamp when circuit entered half-open state
     * @param  int  $requiredSuccesses  Number of successes required to close the circuit
     */
    public function __construct(
        public string $serviceName,
        public \Illuminate\Support\Carbon $halfOpenedAt,
        public int $requiredSuccesses
    ) {}
}
