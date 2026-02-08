<?php

namespace App\Events\CircuitBreaker;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a failure is recorded by the circuit breaker
 *
 * This event is triggered on each failure, regardless of circuit state,
 * allowing for detailed monitoring and metrics collection.
 */
class CircuitBreakerFailure
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance
     *
     * @param  string  $serviceName  The name of the service being monitored
     * @param  int  $currentFailureCount  Current count of failures
     * @param  int  $failureThreshold  Threshold at which circuit will open
     * @param  string  $state  Current state of the circuit (closed, open, half_open)
     * @param  array  $errorDetails  Details about the error that occurred
     * @param  \Illuminate\Support\Carbon  $occurredAt  Timestamp when failure occurred
     */
    public function __construct(
        public string $serviceName,
        public int $currentFailureCount,
        public int $failureThreshold,
        public string $state,
        public array $errorDetails,
        public \Illuminate\Support\Carbon $occurredAt
    ) {}
}
