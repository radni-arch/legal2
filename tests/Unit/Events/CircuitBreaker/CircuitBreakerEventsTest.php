<?php

namespace Tests\Unit\Events\CircuitBreaker;

use App\Events\CircuitBreaker\CircuitBreakerClosed;
use App\Events\CircuitBreaker\CircuitBreakerFailure;
use App\Events\CircuitBreaker\CircuitBreakerHalfOpened;
use App\Events\CircuitBreaker\CircuitBreakerOpened;
use App\Listeners\CircuitBreaker\LogCircuitBreakerState;
use App\Listeners\CircuitBreaker\NotifySlackOnCircuitBreaker;
use App\Listeners\CircuitBreaker\RecordCircuitBreakerMetrics;
use App\Services\CircuitBreaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Test circuit breaker events and listeners
 */
class CircuitBreakerEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache before each test
        Cache::flush();
    }

    /**
     * Test circuit breaker opened event is dispatched
     */
    public function test_circuit_breaker_opened_event_is_dispatched(): void
    {
        Event::fake([CircuitBreakerOpened::class]);

        $circuitBreaker = new CircuitBreaker('test-service', failureThreshold: 2);

        // Trigger failures to open the circuit
        try {
            $circuitBreaker->call(function () {
                throw new \Exception('Test failure 1');
            });
        } catch (\Exception $e) {
            // Expected
        }

        try {
            $circuitBreaker->call(function () {
                throw new \Exception('Test failure 2');
            });
        } catch (\Exception $e) {
            // Expected - this should open the circuit
        }

        Event::assertDispatched(CircuitBreakerOpened::class, function ($event) {
            return $event->serviceName === 'test-service'
                && $event->failureCount >= 2
                && $event->retryAfterSeconds === 60;
        });
    }

    /**
     * Test circuit breaker closed event is dispatched
     */
    public function test_circuit_breaker_closed_event_is_dispatched(): void
    {
        Event::fake([CircuitBreakerClosed::class, CircuitBreakerHalfOpened::class]);

        $circuitBreaker = new CircuitBreaker('test-service', failureThreshold: 2, successThreshold: 2);

        // First, open the circuit
        try {
            $circuitBreaker->call(function () {
                throw new \Exception('Test failure 1');
            });
        } catch (\Exception $e) {
        }

        try {
            $circuitBreaker->call(function () {
                throw new \Exception('Test failure 2');
            });
        } catch (\Exception $e) {
        }

        // Fast forward time to allow reset
        Cache::put('circuit_breaker:test-service:last_failure', time() - 70, 3600);

        // Make successful calls to close the circuit
        $circuitBreaker->call(function () {
            return 'success 1';
        });

        $circuitBreaker->call(function () {
            return 'success 2';
        });

        Event::assertDispatched(CircuitBreakerClosed::class, function ($event) {
            return $event->serviceName === 'test-service'
                && $event->successCount >= 2;
        });
    }

    /**
     * Test circuit breaker half opened event is dispatched
     */
    public function test_circuit_breaker_half_opened_event_is_dispatched(): void
    {
        Event::fake([CircuitBreakerHalfOpened::class]);

        $circuitBreaker = new CircuitBreaker('test-service', failureThreshold: 2);

        // Open the circuit
        try {
            $circuitBreaker->call(function () {
                throw new \Exception('Test failure 1');
            });
        } catch (\Exception $e) {
        }

        try {
            $circuitBreaker->call(function () {
                throw new \Exception('Test failure 2');
            });
        } catch (\Exception $e) {
        }

        // Fast forward time to allow reset attempt
        Cache::put('circuit_breaker:test-service:last_failure', time() - 70, 3600);

        // Next call should transition to half-open
        $circuitBreaker->call(function () {
            return 'testing';
        });

        Event::assertDispatched(CircuitBreakerHalfOpened::class, function ($event) {
            return $event->serviceName === 'test-service'
                && $event->requiredSuccesses === 2;
        });
    }

    /**
     * Test circuit breaker failure event is dispatched on each failure
     */
    public function test_circuit_breaker_failure_event_is_dispatched(): void
    {
        Event::fake([CircuitBreakerFailure::class]);

        $circuitBreaker = new CircuitBreaker('test-service');

        try {
            $circuitBreaker->call(function () {
                throw new \RuntimeException('Test error');
            });
        } catch (\Exception $e) {
            // Expected
        }

        Event::assertDispatched(CircuitBreakerFailure::class, function ($event) {
            return $event->serviceName === 'test-service'
                && $event->currentFailureCount === 1
                && $event->state === 'closed'
                && isset($event->errorDetails['message'])
                && $event->errorDetails['message'] === 'Test error';
        });
    }

    /**
     * Test log listener handles circuit breaker events
     */
    public function test_log_listener_handles_events(): void
    {
        Log::shouldReceive('channel')
            ->with('stack')
            ->andReturnSelf();

        Log::shouldReceive('warning')
            ->once()
            ->with('Circuit breaker OPENED', \Mockery::on(function ($context) {
                return $context['service'] === 'test-service'
                    && $context['event_type'] === 'circuit_breaker_opened';
            }));

        $event = new CircuitBreakerOpened(
            serviceName: 'test-service',
            failureCount: 5,
            openedAt: now(),
            lastError: ['message' => 'Test error'],
            retryAfterSeconds: 60
        );

        $listener = new LogCircuitBreakerState;
        $listener->handleOpened($event);
    }

    /**
     * Test Slack notification is sent when circuit opens
     */
    public function test_slack_notification_is_sent_on_circuit_open(): void
    {
        config(['services.slack.circuit_breaker_webhook' => 'https://hooks.slack.com/test']);

        Http::fake([
            'hooks.slack.com/*' => Http::response(['ok' => true], 200),
        ]);

        $event = new CircuitBreakerOpened(
            serviceName: 'test-service',
            failureCount: 5,
            openedAt: now(),
            lastError: ['message' => 'Connection timeout'],
            retryAfterSeconds: 60
        );

        $listener = new NotifySlackOnCircuitBreaker;
        $listener->handle($event);

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return str_contains($request->url(), 'hooks.slack.com')
                && isset($body['attachments'])
                && $body['attachments'][0]['title'] === 'Circuit Breaker OPENED: test-service';
        });
    }

    /**
     * Test Slack notification is skipped when webhook not configured
     */
    public function test_slack_notification_skipped_when_not_configured(): void
    {
        config(['services.slack.circuit_breaker_webhook' => null]);

        Http::fake();

        $event = new CircuitBreakerOpened(
            serviceName: 'test-service',
            failureCount: 5,
            openedAt: now(),
            lastError: ['message' => 'Test error'],
            retryAfterSeconds: 60
        );

        $listener = new NotifySlackOnCircuitBreaker;
        $listener->handle($event);

        Http::assertNothingSent();
    }

    /**
     * Test metrics listener records events to database
     */
    public function test_metrics_listener_records_events_to_database(): void
    {
        // Create the table for this test
        if (! DB::getSchemaBuilder()->hasTable('circuit_breaker_events')) {
            DB::getSchemaBuilder()->create('circuit_breaker_events', function ($table) {
                $table->id();
                $table->string('service');
                $table->string('state');
                $table->integer('failure_count')->default(0);
                $table->timestamp('opened_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at');
            });
        }

        $event = new CircuitBreakerOpened(
            serviceName: 'test-service',
            failureCount: 5,
            openedAt: now(),
            lastError: ['message' => 'Test error'],
            retryAfterSeconds: 60
        );

        $listener = new RecordCircuitBreakerMetrics;
        $listener->handleOpened($event);

        $this->assertDatabaseHas('circuit_breaker_events', [
            'service' => 'test-service',
            'state' => 'open',
            'failure_count' => 5,
        ]);
    }

    /**
     * Test circuit breaker events contain correct data
     */
    public function test_circuit_breaker_events_contain_correct_data(): void
    {
        $openedAt = now();
        $lastError = ['message' => 'Connection failed', 'class' => 'Exception'];

        $openedEvent = new CircuitBreakerOpened(
            serviceName: 'api-service',
            failureCount: 5,
            openedAt: $openedAt,
            lastError: $lastError,
            retryAfterSeconds: 120
        );

        $this->assertEquals('api-service', $openedEvent->serviceName);
        $this->assertEquals(5, $openedEvent->failureCount);
        $this->assertEquals($openedAt, $openedEvent->openedAt);
        $this->assertEquals($lastError, $openedEvent->lastError);
        $this->assertEquals(120, $openedEvent->retryAfterSeconds);

        $closedAt = now();
        $closedEvent = new CircuitBreakerClosed(
            serviceName: 'api-service',
            successCount: 2,
            closedAt: $closedAt
        );

        $this->assertEquals('api-service', $closedEvent->serviceName);
        $this->assertEquals(2, $closedEvent->successCount);
        $this->assertEquals($closedAt, $closedEvent->closedAt);

        $halfOpenedAt = now();
        $halfOpenedEvent = new CircuitBreakerHalfOpened(
            serviceName: 'api-service',
            halfOpenedAt: $halfOpenedAt,
            requiredSuccesses: 3
        );

        $this->assertEquals('api-service', $halfOpenedEvent->serviceName);
        $this->assertEquals($halfOpenedAt, $halfOpenedEvent->halfOpenedAt);
        $this->assertEquals(3, $halfOpenedEvent->requiredSuccesses);

        $occurredAt = now();
        $errorDetails = ['message' => 'Error', 'class' => 'RuntimeException'];
        $failureEvent = new CircuitBreakerFailure(
            serviceName: 'api-service',
            currentFailureCount: 3,
            failureThreshold: 5,
            state: 'closed',
            errorDetails: $errorDetails,
            occurredAt: $occurredAt
        );

        $this->assertEquals('api-service', $failureEvent->serviceName);
        $this->assertEquals(3, $failureEvent->currentFailureCount);
        $this->assertEquals(5, $failureEvent->failureThreshold);
        $this->assertEquals('closed', $failureEvent->state);
        $this->assertEquals($errorDetails, $failureEvent->errorDetails);
        $this->assertEquals($occurredAt, $failureEvent->occurredAt);
    }
}
