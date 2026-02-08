<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Graph\CircuitBreaker;
use App\Services\Graph\CircuitBreakerOpenException;
use PHPUnit\Framework\TestCase;

class CircuitBreakerTest extends TestCase
{
    public function test_initial_state_is_closed(): void
    {
        $breaker = new CircuitBreaker('test-breaker', 3, 30);

        $this->assertEquals('closed', $breaker->getState());
    }

    public function test_executes_operation_when_closed(): void
    {
        $breaker = new CircuitBreaker('test-breaker', 3, 30);

        $result = $breaker->call(fn() => 'success');

        $this->assertEquals('success', $result);
    }

    public function test_records_failure_and_rethrows_exception(): void
    {
        $breaker = new CircuitBreaker('test-breaker', 3, 30);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Operation failed');

        $breaker->call(function () {
            throw new \RuntimeException('Operation failed');
        });
    }

    public function test_opens_circuit_after_threshold_failures(): void
    {
        $breaker = new CircuitBreaker('test-breaker', 3, 30);

        // Record 3 failures (threshold)
        for ($i = 0; $i < 3; $i++) {
            try {
                $breaker->call(function () {
                    throw new \RuntimeException('Failure');
                });
            } catch (\RuntimeException $e) {
                // Expected
            }
        }

        $this->assertEquals('open', $breaker->getState());
    }

    public function test_throws_circuit_breaker_exception_when_open(): void
    {
        $breaker = new CircuitBreaker('test-breaker', 2, 30);

        // Trigger open state
        for ($i = 0; $i < 2; $i++) {
            try {
                $breaker->call(function () {
                    throw new \RuntimeException('Failure');
                });
            } catch (\RuntimeException $e) {
                // Expected
            }
        }

        $this->expectException(CircuitBreakerOpenException::class);
        $this->expectExceptionMessage("Circuit breaker 'test-breaker' is open");

        $breaker->call(fn() => 'should not execute');
    }

    public function test_transitions_to_half_open_after_recovery_timeout(): void
    {
        $breaker = new CircuitBreaker('test-breaker', 2, 1); // 1 second timeout

        // Trigger open state
        for ($i = 0; $i < 2; $i++) {
            try {
                $breaker->call(function () {
                    throw new \RuntimeException('Failure');
                });
            } catch (\RuntimeException $e) {
                // Expected
            }
        }

        $this->assertEquals('open', $breaker->getState());

        // Wait for recovery timeout
        sleep(2);

        // Check state - should transition to half-open when checked
        $this->assertEquals('half-open', $breaker->getState());
    }

    public function test_closes_circuit_on_success_in_half_open_state(): void
    {
        $breaker = new CircuitBreaker('test-breaker', 2, 1);

        // Trigger open state
        for ($i = 0; $i < 2; $i++) {
            try {
                $breaker->call(function () {
                    throw new \RuntimeException('Failure');
                });
            } catch (\RuntimeException $e) {
                // Expected
            }
        }

        // Wait for recovery timeout
        sleep(2);

        // Successful call should close circuit
        $result = $breaker->call(fn() => 'recovered');

        $this->assertEquals('recovered', $result);
        $this->assertEquals('closed', $breaker->getState());
    }

    public function test_reopens_circuit_on_failure_in_half_open_state(): void
    {
        $breaker = new CircuitBreaker('test-breaker', 2, 1);

        // Trigger open state
        for ($i = 0; $i < 2; $i++) {
            try {
                $breaker->call(function () {
                    throw new \RuntimeException('Failure');
                });
            } catch (\RuntimeException $e) {
                // Expected
            }
        }

        // Wait for recovery timeout
        sleep(2);

        // Failed call should reopen circuit
        try {
            $breaker->call(function () {
                throw new \RuntimeException('Still failing');
            });
        } catch (\RuntimeException $e) {
            // Expected
        }

        $this->assertEquals('open', $breaker->getState());
    }

    public function test_resets_failure_count_on_success(): void
    {
        $breaker = new CircuitBreaker('test-breaker', 3, 30);

        // Record 2 failures (below threshold)
        for ($i = 0; $i < 2; $i++) {
            try {
                $breaker->call(function () {
                    throw new \RuntimeException('Failure');
                });
            } catch (\RuntimeException $e) {
                // Expected
            }
        }

        $this->assertEquals('closed', $breaker->getState());

        // Successful call should reset counter
        $breaker->call(fn() => 'success');

        // Should still be closed and counter reset
        // So we can fail 2 more times without opening
        for ($i = 0; $i < 2; $i++) {
            try {
                $breaker->call(function () {
                    throw new \RuntimeException('Failure');
                });
            } catch (\RuntimeException $e) {
                // Expected
            }
        }

        $this->assertEquals('closed', $breaker->getState());
    }

    public function test_constructor_sets_configuration(): void
    {
        $breaker = new CircuitBreaker('my-breaker', 10, 60);

        $this->assertEquals('my-breaker', $breaker->getName());
        $this->assertEquals(10, $breaker->getFailureThreshold());
        $this->assertEquals(60, $breaker->getRecoveryTimeout());
    }
}
