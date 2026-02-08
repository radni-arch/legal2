<?php

namespace App\Contracts;

/**
 * Circuit Breaker Pattern Interface
 *
 * Defines the contract for circuit breaker implementations that prevent
 * cascading failures by monitoring external service calls and temporarily
 * blocking requests when a service is experiencing issues.
 *
 * The circuit breaker operates in three states:
 * - CLOSED: Normal operation, requests pass through
 * - OPEN: Service is failing, requests are blocked immediately
 * - HALF_OPEN: Testing if service has recovered
 */
interface CircuitBreakerInterface
{
    /**
     * Execute a callable with circuit breaker protection
     *
     * @param  callable  $callback  The operation to execute
     * @return mixed The result of the callback
     *
     * @throws \App\Services\CircuitBreakerException If circuit is open
     * @throws \Throwable If the callback throws an exception
     */
    public function call(callable $callback);

    /**
     * Get current circuit breaker status
     *
     * Returns comprehensive status information including state, failure count,
     * success count, thresholds, and last failure time.
     *
     * @return array{
     *     service: string,
     *     state: string,
     *     failure_count: int,
     *     success_count: int,
     *     failure_threshold: int,
     *     success_threshold: int,
     *     last_failure: string|null,
     *     can_retry: bool
     * }
     */
    public function getStatus(): array;

    /**
     * Manually reset the circuit breaker to closed state
     *
     * This clears all failure counts and transitions the circuit to CLOSED state,
     * allowing requests to pass through immediately.
     */
    public function reset(): void;
}
