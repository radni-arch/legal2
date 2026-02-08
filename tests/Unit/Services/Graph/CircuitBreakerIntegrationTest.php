<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Graph\CircuitBreaker;
use App\Services\Graph\CircuitBreakerOpenException;
use Tests\TestCase;

class CircuitBreakerIntegrationTest extends TestCase
{
    /** @test */
    public function circuit_breaker_opens_after_threshold_failures(): void
    {
        $circuitBreaker = new CircuitBreaker(
            name: 'test-breaker-1',
            failureThreshold: 3,
            recoveryTimeout: 30
        );

        // Cause failures up to threshold
        for ($i = 0; $i < 3; $i++) {
            try {
                $circuitBreaker->call(function () {
                    throw new \RuntimeException('Simulated failure');
                });
            } catch (\RuntimeException $e) {
                // Expected
            }
        }

        // Circuit should now be open - next call should throw CircuitBreakerOpenException
        $this->expectException(CircuitBreakerOpenException::class);
        $circuitBreaker->call(function () {
            return 'success';
        });
    }

    /** @test */
    public function circuit_breaker_stays_closed_below_threshold(): void
    {
        $circuitBreaker = new CircuitBreaker(
            name: 'test-breaker-2',
            failureThreshold: 3,
            recoveryTimeout: 30
        );

        // Cause failures below threshold
        for ($i = 0; $i < 2; $i++) {
            try {
                $circuitBreaker->call(function () {
                    throw new \RuntimeException('Simulated failure');
                });
            } catch (\RuntimeException $e) {
                // Expected
            }
        }

        // Circuit should still be closed - this call should succeed
        $result = $circuitBreaker->call(function () {
            return 'success';
        });

        $this->assertEquals('success', $result);
    }

    /** @test */
    public function circuit_breaker_resets_on_success(): void
    {
        $circuitBreaker = new CircuitBreaker(
            name: 'test-breaker-3',
            failureThreshold: 3,
            recoveryTimeout: 30
        );

        // Cause some failures
        for ($i = 0; $i < 2; $i++) {
            try {
                $circuitBreaker->call(function () {
                    throw new \RuntimeException('Simulated failure');
                });
            } catch (\RuntimeException $e) {
                // Expected
            }
        }

        // Success should reset failure count
        $result = $circuitBreaker->call(function () {
            return 'success';
        });

        $this->assertEquals('success', $result);

        // Should be able to handle more failures now (starting from 0 again)
        for ($i = 0; $i < 2; $i++) {
            try {
                $circuitBreaker->call(function () {
                    throw new \RuntimeException('Simulated failure');
                });
            } catch (\RuntimeException $e) {
                // Expected
            }
        }

        // Still below threshold, should succeed
        $result = $circuitBreaker->call(function () {
            return 'success again';
        });

        $this->assertEquals('success again', $result);
    }

    /** @test */
    public function circuit_breaker_allows_recovery_after_timeout(): void
    {
        $circuitBreaker = new CircuitBreaker(
            name: 'test-breaker-4',
            failureThreshold: 1,
            recoveryTimeout: 1  // 1 second for fast test
        );

        // Cause failure to open circuit
        try {
            $circuitBreaker->call(function () {
                throw new \RuntimeException('Simulated failure');
            });
        } catch (\RuntimeException $e) {
            // Expected
        }

        // Circuit should be open
        try {
            $circuitBreaker->call(function () {
                return 'should fail';
            });
            $this->fail('Expected CircuitBreakerOpenException was not thrown');
        } catch (CircuitBreakerOpenException $e) {
            // Expected - circuit is open
        }

        // Wait for recovery timeout
        sleep(2);

        // Circuit should transition to half-open and allow a probe request
        // If probe succeeds, circuit closes
        $result = $circuitBreaker->call(function () {
            return 'recovery success';
        });

        $this->assertEquals('recovery success', $result);
        $this->assertEquals('closed', $circuitBreaker->getState());
    }

    /** @test */
    public function circuit_breaker_reopens_if_half_open_probe_fails(): void
    {
        $circuitBreaker = new CircuitBreaker(
            name: 'test-breaker-5',
            failureThreshold: 1,
            recoveryTimeout: 1
        );

        // Open the circuit
        try {
            $circuitBreaker->call(function () {
                throw new \RuntimeException('Initial failure');
            });
        } catch (\RuntimeException $e) {
            // Expected
        }

        // Wait for recovery timeout to enter half-open state
        sleep(2);

        // Probe fails - circuit should reopen
        try {
            $circuitBreaker->call(function () {
                throw new \RuntimeException('Probe failure');
            });
        } catch (\RuntimeException $e) {
            // Expected
        }

        // Circuit should be open again - immediate next call should fail
        $this->expectException(CircuitBreakerOpenException::class);
        $circuitBreaker->call(function () {
            return 'should not execute';
        });
    }

    /** @test */
    public function circuit_breaker_returns_operation_result(): void
    {
        $circuitBreaker = new CircuitBreaker(
            name: 'test-breaker-6',
            failureThreshold: 3,
            recoveryTimeout: 30
        );

        $result = $circuitBreaker->call(function () {
            return ['data' => 'test', 'count' => 42];
        });

        $this->assertEquals(['data' => 'test', 'count' => 42], $result);
    }

    /** @test */
    public function circuit_breaker_propagates_operation_exceptions(): void
    {
        $circuitBreaker = new CircuitBreaker(
            name: 'test-breaker-7',
            failureThreshold: 3,
            recoveryTimeout: 30
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Custom error message');

        $circuitBreaker->call(function () {
            throw new \InvalidArgumentException('Custom error message');
        });
    }
}
