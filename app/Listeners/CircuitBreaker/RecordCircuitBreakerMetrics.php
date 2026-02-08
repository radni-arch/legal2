<?php

namespace App\Listeners\CircuitBreaker;

use App\Events\CircuitBreaker\CircuitBreakerClosed;
use App\Events\CircuitBreaker\CircuitBreakerFailure;
use App\Events\CircuitBreaker\CircuitBreakerHalfOpened;
use App\Events\CircuitBreaker\CircuitBreakerOpened;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Record circuit breaker events to database for metrics and monitoring
 *
 * This listener maintains a historical record of all circuit breaker events
 * in the database for analysis, dashboards, and alerting.
 */
class RecordCircuitBreakerMetrics
{
    /**
     * Handle circuit breaker opened event
     */
    public function handleOpened(CircuitBreakerOpened $event): void
    {
        $this->recordEvent(
            service: $event->serviceName,
            state: 'open',
            failureCount: $event->failureCount,
            openedAt: $event->openedAt,
            metadata: [
                'last_error' => $event->lastError,
                'retry_after_seconds' => $event->retryAfterSeconds,
            ]
        );
    }

    /**
     * Handle circuit breaker closed event
     */
    public function handleClosed(CircuitBreakerClosed $event): void
    {
        $this->recordEvent(
            service: $event->serviceName,
            state: 'closed',
            failureCount: 0,
            openedAt: null,
            metadata: [
                'success_count' => $event->successCount,
                'closed_at' => $event->closedAt->toDateTimeString(),
            ]
        );
    }

    /**
     * Handle circuit breaker half-opened event
     */
    public function handleHalfOpened(CircuitBreakerHalfOpened $event): void
    {
        $this->recordEvent(
            service: $event->serviceName,
            state: 'half_open',
            failureCount: 0,
            openedAt: null,
            metadata: [
                'half_opened_at' => $event->halfOpenedAt->toDateTimeString(),
                'required_successes' => $event->requiredSuccesses,
            ]
        );
    }

    /**
     * Handle circuit breaker failure event
     */
    public function handleFailure(CircuitBreakerFailure $event): void
    {
        $this->recordEvent(
            service: $event->serviceName,
            state: $event->state,
            failureCount: $event->currentFailureCount,
            openedAt: null,
            metadata: [
                'failure_threshold' => $event->failureThreshold,
                'error_details' => $event->errorDetails,
                'occurred_at' => $event->occurredAt->toDateTimeString(),
                'event_type' => 'failure',
            ]
        );
    }

    /**
     * Record event to database
     */
    private function recordEvent(
        string $service,
        string $state,
        int $failureCount,
        ?\Illuminate\Support\Carbon $openedAt,
        array $metadata
    ): void {
        try {
            DB::table('circuit_breaker_events')->insert([
                'service' => $service,
                'state' => $state,
                'failure_count' => $failureCount,
                'opened_at' => $openedAt?->toDateTimeString(),
                'metadata' => json_encode($metadata),
                'created_at' => now(),
            ]);

            Log::debug('Circuit breaker event recorded to database', [
                'service' => $service,
                'state' => $state,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to record circuit breaker event to database', [
                'service' => $service,
                'state' => $state,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
            ]);
            // Don't throw - database failure shouldn't block the application
        }
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
