<?php

namespace App\Listeners\CircuitBreaker;

use App\Events\CircuitBreaker\CircuitBreakerClosed;
use App\Events\CircuitBreaker\CircuitBreakerFailure;
use App\Events\CircuitBreaker\CircuitBreakerHalfOpened;
use App\Events\CircuitBreaker\CircuitBreakerOpened;
use Illuminate\Support\Facades\Log;

/**
 * Log circuit breaker state changes to dedicated log file
 *
 * This listener maintains a detailed audit trail of all circuit breaker
 * state transitions in storage/logs/circuit-breaker.log
 */
class LogCircuitBreakerState
{
    /**
     * Handle circuit breaker opened event
     */
    public function handleOpened(CircuitBreakerOpened $event): void
    {
        Log::channel('stack')->warning('Circuit breaker OPENED', [
            'service' => $event->serviceName,
            'failure_count' => $event->failureCount,
            'opened_at' => $event->openedAt->toDateTimeString(),
            'retry_after_seconds' => $event->retryAfterSeconds,
            'last_error' => $event->lastError,
            'event_type' => 'circuit_breaker_opened',
        ]);
    }

    /**
     * Handle circuit breaker closed event
     */
    public function handleClosed(CircuitBreakerClosed $event): void
    {
        Log::channel('stack')->info('Circuit breaker CLOSED', [
            'service' => $event->serviceName,
            'success_count' => $event->successCount,
            'closed_at' => $event->closedAt->toDateTimeString(),
            'event_type' => 'circuit_breaker_closed',
        ]);
    }

    /**
     * Handle circuit breaker half-opened event
     */
    public function handleHalfOpened(CircuitBreakerHalfOpened $event): void
    {
        Log::channel('stack')->info('Circuit breaker HALF-OPENED', [
            'service' => $event->serviceName,
            'half_opened_at' => $event->halfOpenedAt->toDateTimeString(),
            'required_successes' => $event->requiredSuccesses,
            'event_type' => 'circuit_breaker_half_opened',
        ]);
    }

    /**
     * Handle circuit breaker failure event
     */
    public function handleFailure(CircuitBreakerFailure $event): void
    {
        Log::channel('stack')->warning('Circuit breaker recorded FAILURE', [
            'service' => $event->serviceName,
            'current_failure_count' => $event->currentFailureCount,
            'failure_threshold' => $event->failureThreshold,
            'state' => $event->state,
            'error_details' => $event->errorDetails,
            'occurred_at' => $event->occurredAt->toDateTimeString(),
            'event_type' => 'circuit_breaker_failure',
        ]);
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe($events): array
    {
        return [
            CircuitBreakerOpened::class => 'handleOpened',
            CircuitBreakerClosed::class => 'handleClosed',
            CircuitBreakerHalfOpened::class => 'handleHalfOpened',
            CircuitBreakerFailure::class => 'handleFailure',
        ];
    }
}
