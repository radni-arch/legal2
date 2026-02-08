<?php

namespace Tests\Unit\Services;

use App\Services\CircuitBreaker;
use App\Services\CircuitBreakerException;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Test suite for CircuitBreaker service
 *
 * Tests circuit breaker pattern implementation including:
 * - Normal operation (closed state)
 * - Failure threshold triggering (open state)
 * - Recovery testing (half-open state)
 * - Success threshold closing circuit
 */
class CircuitBreakerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    /**
     * Test 1: Successful calls pass through in closed state
     */
    public function test_successful_calls_pass_through_in_closed_state(): void
    {
        $breaker = new CircuitBreaker('test_service');

        $result = $breaker->call(function () {
            return 'success';
        });

        $this->assertEquals('success', $result);

        $status = $breaker->getStatus();
        $this->assertEquals('closed', $status['state']);
        $this->assertEquals(0, $status['failure_count']);
    }

    /**
     * Test 2: Circuit opens after reaching failure threshold
     */
    public function test_circuit_opens_after_failure_threshold(): void
    {
        $breaker = new CircuitBreaker('test_service', failureThreshold: 3);

        // Trigger 3 failures to reach threshold
        for ($i = 0; $i < 3; $i++) {
            try {
                $breaker->call(function () {
                    throw new \RuntimeException('Test failure');
                });
            } catch (\RuntimeException $e) {
                // Expected
            }
        }

        // Circuit should now be open
        $this->expectException(CircuitBreakerException::class);
        $this->expectExceptionMessage('Circuit breaker is OPEN');

        $breaker->call(function () {
            return 'should not execute';
        });
    }

    /**
     * Test 3: Circuit breaker blocks calls when open
     */
    public function test_circuit_breaker_blocks_calls_when_open(): void
    {
        $breaker = new CircuitBreaker('test_service', failureThreshold: 2);

        // Trigger failures to open circuit
        for ($i = 0; $i < 2; $i++) {
            try {
                $breaker->call(function () {
                    throw new \Exception('Failure');
                });
            } catch (\Exception $e) {
                // Expected
            }
        }

        // Verify circuit is open and blocks subsequent calls
        $callbackExecuted = false;

        try {
            $breaker->call(function () use (&$callbackExecuted) {
                $callbackExecuted = true;

                return 'executed';
            });
        } catch (CircuitBreakerException $e) {
            // Expected
        }

        $this->assertFalse($callbackExecuted, 'Callback should not execute when circuit is open');
    }

    /**
     * Test 4: Circuit transitions to half-open after timeout
     */
    public function test_circuit_transitions_to_half_open_after_timeout(): void
    {
        $breaker = new CircuitBreaker('test_service', failureThreshold: 2, timeout: 1);

        // Open the circuit
        for ($i = 0; $i < 2; $i++) {
            try {
                $breaker->call(function () {
                    throw new \Exception('Failure');
                });
            } catch (\Exception $e) {
                // Expected
            }
        }

        // Wait for timeout
        sleep(2);

        // Next call should transition to half-open
        $result = $breaker->call(function () {
            return 'half-open success';
        });

        $this->assertEquals('half-open success', $result);
    }

    /**
     * Test 5: Circuit closes after success threshold in half-open
     */
    public function test_circuit_closes_after_success_threshold_in_half_open(): void
    {
        $breaker = new CircuitBreaker('test_service', failureThreshold: 2, successThreshold: 2, timeout: 1);

        // Open the circuit
        for ($i = 0; $i < 2; $i++) {
            try {
                $breaker->call(function () {
                    throw new \Exception('Failure');
                });
            } catch (\Exception $e) {
                // Expected
            }
        }

        // Wait for timeout to allow half-open
        sleep(2);

        // Make successful calls to close circuit
        $breaker->call(function () {
            return 'success 1';
        });

        $breaker->call(function () {
            return 'success 2';
        });

        // Circuit should now be closed
        $status = $breaker->getStatus();
        $this->assertEquals('closed', $status['state']);
    }

    /**
     * Test 6: Failure in half-open immediately opens circuit
     */
    public function test_failure_in_half_open_immediately_opens_circuit(): void
    {
        $breaker = new CircuitBreaker('test_service', failureThreshold: 2, timeout: 1);

        // Open the circuit
        for ($i = 0; $i < 2; $i++) {
            try {
                $breaker->call(function () {
                    throw new \Exception('Failure');
                });
            } catch (\Exception $e) {
                // Expected
            }
        }

        // Wait for timeout
        sleep(2);

        // Fail in half-open state
        try {
            $breaker->call(function () {
                throw new \Exception('Half-open failure');
            });
        } catch (\Exception $e) {
            // Expected
        }

        // Circuit should be open again
        $this->expectException(CircuitBreakerException::class);

        $breaker->call(function () {
            return 'should not execute';
        });
    }

    /**
     * Test 7: Get status returns accurate information
     */
    public function test_get_status_returns_accurate_information(): void
    {
        $breaker = new CircuitBreaker('test_service', failureThreshold: 5, successThreshold: 2);

        // Initial status
        $status = $breaker->getStatus();
        $this->assertEquals('test_service', $status['service']);
        $this->assertEquals('closed', $status['state']);
        $this->assertEquals(0, $status['failure_count']);
        $this->assertEquals(5, $status['failure_threshold']);
        $this->assertEquals(2, $status['success_threshold']);
        $this->assertTrue($status['can_retry']);

        // After some failures
        for ($i = 0; $i < 3; $i++) {
            try {
                $breaker->call(function () {
                    throw new \Exception('Failure');
                });
            } catch (\Exception $e) {
                // Expected
            }
        }

        $status = $breaker->getStatus();
        $this->assertEquals(3, $status['failure_count']);
        $this->assertEquals('closed', $status['state']);
    }

    /**
     * Test 8: Manual reset closes circuit
     */
    public function test_manual_reset_closes_circuit(): void
    {
        $breaker = new CircuitBreaker('test_service', failureThreshold: 2);

        // Open the circuit
        for ($i = 0; $i < 2; $i++) {
            try {
                $breaker->call(function () {
                    throw new \Exception('Failure');
                });
            } catch (\Exception $e) {
                // Expected
            }
        }

        // Verify circuit is open
        try {
            $breaker->call(function () {
                return 'test';
            });
            $this->fail('Should have thrown CircuitBreakerException');
        } catch (CircuitBreakerException $e) {
            // Expected
        }

        // Reset circuit
        $breaker->reset();

        // Should work now
        $result = $breaker->call(function () {
            return 'success after reset';
        });

        $this->assertEquals('success after reset', $result);

        $status = $breaker->getStatus();
        $this->assertEquals('closed', $status['state']);
        $this->assertEquals(0, $status['failure_count']);
    }

    /**
     * Test 9: Success in closed state resets failure count
     */
    public function test_success_in_closed_state_resets_failure_count(): void
    {
        $breaker = new CircuitBreaker('test_service', failureThreshold: 5);

        // Generate some failures
        for ($i = 0; $i < 3; $i++) {
            try {
                $breaker->call(function () {
                    throw new \Exception('Failure');
                });
            } catch (\Exception $e) {
                // Expected
            }
        }

        $status = $breaker->getStatus();
        $this->assertEquals(3, $status['failure_count']);

        // Successful call should reset counter
        $breaker->call(function () {
            return 'success';
        });

        $status = $breaker->getStatus();
        $this->assertEquals(0, $status['failure_count']);
    }

    /**
     * Test 10: Different services have independent circuits
     */
    public function test_different_services_have_independent_circuits(): void
    {
        $breaker1 = new CircuitBreaker('service_1', failureThreshold: 2);
        $breaker2 = new CircuitBreaker('service_2', failureThreshold: 2);

        // Open circuit for service_1
        for ($i = 0; $i < 2; $i++) {
            try {
                $breaker1->call(function () {
                    throw new \Exception('Failure');
                });
            } catch (\Exception $e) {
                // Expected
            }
        }

        // service_1 should be open
        try {
            $breaker1->call(function () {
                return 'test';
            });
            $this->fail('Should have thrown CircuitBreakerException for service_1');
        } catch (CircuitBreakerException $e) {
            // Expected
        }

        // service_2 should still be closed
        $result = $breaker2->call(function () {
            return 'service_2 success';
        });

        $this->assertEquals('service_2 success', $result);
    }

    /**
     * Test 11: Circuit breaker handles different exception types
     */
    public function test_circuit_breaker_handles_different_exception_types(): void
    {
        $breaker = new CircuitBreaker('test_service', failureThreshold: 3);

        $exceptions = [
            new \RuntimeException('Runtime error'),
            new \InvalidArgumentException('Invalid argument'),
            new \LogicException('Logic error'),
        ];

        foreach ($exceptions as $exception) {
            try {
                $breaker->call(function () use ($exception) {
                    throw $exception;
                });
                $this->fail('Should have thrown exception');
            } catch (\Exception $e) {
                $this->assertInstanceOf(get_class($exception), $e);
            }
        }

        // Circuit should be open after 3 failures
        $this->expectException(CircuitBreakerException::class);

        $breaker->call(function () {
            return 'should not execute';
        });
    }

    /**
     * Test 12: Circuit breaker preserves callback return values
     */
    public function test_circuit_breaker_preserves_callback_return_values(): void
    {
        $breaker = new CircuitBreaker('test_service');

        $stringResult = $breaker->call(function () {
            return 'string value';
        });
        $this->assertEquals('string value', $stringResult);

        $arrayResult = $breaker->call(function () {
            return ['key' => 'value'];
        });
        $this->assertEquals(['key' => 'value'], $arrayResult);

        $intResult = $breaker->call(function () {
            return 42;
        });
        $this->assertEquals(42, $intResult);

        $objectResult = $breaker->call(function () {
            return (object) ['property' => 'value'];
        });
        $this->assertEquals('value', $objectResult->property);
    }
}
