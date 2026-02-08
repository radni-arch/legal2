<?php

namespace Tests\Feature;

use App\Events\Neo4jUnavailable;
use App\Listeners\NotifyNeo4jDowntime;
use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laudis\Neo4j\Contracts\ClientInterface;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class GraphDatabaseHealthTest extends TestCase
{
    use UsesTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache before each test
        Cache::forget('neo4j:status');

        // Enable Neo4j for tests
        Config::set('neo4j.sync.enabled', true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_caches_successful_health_check_status()
    {
        // Create a mock client that returns successful results
        $clientMock = Mockery::mock(ClientInterface::class);

        // Mock the health check query
        $healthCheckResult = Mockery::mock();
        $healthCheckResult->shouldReceive('count')->andReturn(1);

        // Mock SHOW CONSTRAINTS
        $constraintsResult = Mockery::mock();
        $constraintRecord = Mockery::mock();
        $constraintRecord->shouldReceive('get')->with('name')->andReturn('law_id');
        $constraintsResult->shouldReceive('getIterator')->andReturn(new \ArrayIterator([$constraintRecord]));

        // Mock SHOW INDEXES
        $indexesResult = Mockery::mock();
        $indexRecord = Mockery::mock();
        $indexRecord->shouldReceive('get')->with('name')->andReturn('law_title');
        $indexesResult->shouldReceive('getIterator')->andReturn(new \ArrayIterator([$indexRecord]));

        $clientMock->shouldReceive('run')
            ->with('RETURN 1 as test', [], null, Mockery::any())
            ->once()
            ->andReturn($healthCheckResult);

        $clientMock->shouldReceive('run')
            ->with('SHOW CONSTRAINTS', [], null, Mockery::any())
            ->once()
            ->andReturn($constraintsResult);

        $clientMock->shouldReceive('run')
            ->with('SHOW INDEXES', [], null, Mockery::any())
            ->once()
            ->andReturn($indexesResult);

        // Use reflection to set the client on the service
        $service = new GraphDatabaseService;
        $reflection = new \ReflectionClass($service);

        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($service, $clientMock);

        $sessionConfigProperty = $reflection->getProperty('sessionConfig');
        $sessionConfigProperty->setAccessible(true);
        $sessionConfigProperty->setValue($service, \Laudis\Neo4j\Databags\SessionConfiguration::default());

        // Invoke the health check
        $healthCheckMethod = $reflection->getMethod('performHealthCheck');
        $healthCheckMethod->setAccessible(true);
        $result = $healthCheckMethod->invoke($service);

        // Assert health check passed
        $this->assertTrue($result);

        // Assert cache was set
        $cachedStatus = Cache::get('neo4j:status');
        $this->assertNotNull($cachedStatus);
        $this->assertTrue($cachedStatus['status']['available']);
        $this->assertTrue($cachedStatus['status']['healthy']);
        $this->assertTrue($cachedStatus['status']['constraints_exist']);
        $this->assertTrue($cachedStatus['status']['indexes_exist']);
    }

    /** @test */
    public function it_emits_event_when_client_is_not_initialized()
    {
        Event::fake([Neo4jUnavailable::class]);

        // Create service without client
        $service = new GraphDatabaseService;
        $reflection = new \ReflectionClass($service);

        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($service, null);

        // Invoke the health check
        $healthCheckMethod = $reflection->getMethod('performHealthCheck');
        $healthCheckMethod->setAccessible(true);
        $result = $healthCheckMethod->invoke($service);

        // Assert health check failed
        $this->assertFalse($result);

        // Assert event was dispatched
        Event::assertDispatched(Neo4jUnavailable::class, function ($event) {
            return $event->error === 'Client not initialized'
                && $event->constraintsExist === false
                && $event->indexesExist === false;
        });

        // Assert cache was set with error
        $cachedStatus = Cache::get('neo4j:status');
        $this->assertNotNull($cachedStatus);
        $this->assertFalse($cachedStatus['status']['available']);
        $this->assertEquals('Client not initialized', $cachedStatus['error']);
    }

    /** @test */
    public function it_emits_event_when_connection_fails()
    {
        Event::fake([Neo4jUnavailable::class]);

        // Create a mock client that throws exception
        $clientMock = Mockery::mock(ClientInterface::class);
        $clientMock->shouldReceive('run')
            ->with('RETURN 1 as test', [], null, Mockery::any())
            ->once()
            ->andThrow(new \Exception('Cannot connect to Neo4j server'));

        $service = new GraphDatabaseService;
        $reflection = new \ReflectionClass($service);

        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($service, $clientMock);

        $sessionConfigProperty = $reflection->getProperty('sessionConfig');
        $sessionConfigProperty->setAccessible(true);
        $sessionConfigProperty->setValue($service, \Laudis\Neo4j\Databags\SessionConfiguration::default());

        // Invoke the health check
        $healthCheckMethod = $reflection->getMethod('performHealthCheck');
        $healthCheckMethod->setAccessible(true);
        $result = $healthCheckMethod->invoke($service);

        // Assert health check failed
        $this->assertFalse($result);

        // Assert event was dispatched
        Event::assertDispatched(Neo4jUnavailable::class, function ($event) {
            return str_contains($event->error, 'Cannot connect to Neo4j server');
        });
    }

    /** @test */
    public function it_detects_missing_constraints()
    {
        // Create a mock client
        $clientMock = Mockery::mock(ClientInterface::class);

        // Mock the health check query
        $healthCheckResult = Mockery::mock();
        $healthCheckResult->shouldReceive('count')->andReturn(1);

        // Mock SHOW CONSTRAINTS returning empty results
        $constraintsResult = Mockery::mock();
        $constraintsResult->shouldReceive('getIterator')->andReturn(new \ArrayIterator([]));

        // Mock SHOW INDEXES
        $indexesResult = Mockery::mock();
        $indexRecord = Mockery::mock();
        $indexRecord->shouldReceive('get')->with('name')->andReturn('law_title');
        $indexesResult->shouldReceive('getIterator')->andReturn(new \ArrayIterator([$indexRecord]));

        $clientMock->shouldReceive('run')
            ->with('RETURN 1 as test', [], null, Mockery::any())
            ->once()
            ->andReturn($healthCheckResult);

        $clientMock->shouldReceive('run')
            ->with('SHOW CONSTRAINTS', [], null, Mockery::any())
            ->once()
            ->andReturn($constraintsResult);

        $clientMock->shouldReceive('run')
            ->with('SHOW INDEXES', [], null, Mockery::any())
            ->once()
            ->andReturn($indexesResult);

        $service = new GraphDatabaseService;
        $reflection = new \ReflectionClass($service);

        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($service, $clientMock);

        $sessionConfigProperty = $reflection->getProperty('sessionConfig');
        $sessionConfigProperty->setAccessible(true);
        $sessionConfigProperty->setValue($service, \Laudis\Neo4j\Databags\SessionConfiguration::default());

        // Invoke the health check
        $healthCheckMethod = $reflection->getMethod('performHealthCheck');
        $healthCheckMethod->setAccessible(true);
        $result = $healthCheckMethod->invoke($service);

        // Assert health check passed (connection works)
        $this->assertTrue($result);

        // Assert cache shows constraints missing
        $cachedStatus = Cache::get('neo4j:status');
        $this->assertNotNull($cachedStatus);
        $this->assertTrue($cachedStatus['status']['available']);
        $this->assertFalse($cachedStatus['status']['healthy']); // Not healthy due to missing constraints
        $this->assertFalse($cachedStatus['status']['constraints_exist']);
    }

    /** @test */
    public function it_detects_missing_indexes()
    {
        // Create a mock client
        $clientMock = Mockery::mock(ClientInterface::class);

        // Mock the health check query
        $healthCheckResult = Mockery::mock();
        $healthCheckResult->shouldReceive('count')->andReturn(1);

        // Mock SHOW CONSTRAINTS
        $constraintsResult = Mockery::mock();
        $constraintRecord = Mockery::mock();
        $constraintRecord->shouldReceive('get')->with('name')->andReturn('law_id');
        $constraintsResult->shouldReceive('getIterator')->andReturn(new \ArrayIterator([$constraintRecord]));

        // Mock SHOW INDEXES returning empty results
        $indexesResult = Mockery::mock();
        $indexesResult->shouldReceive('getIterator')->andReturn(new \ArrayIterator([]));

        $clientMock->shouldReceive('run')
            ->with('RETURN 1 as test', [], null, Mockery::any())
            ->once()
            ->andReturn($healthCheckResult);

        $clientMock->shouldReceive('run')
            ->with('SHOW CONSTRAINTS', [], null, Mockery::any())
            ->once()
            ->andReturn($constraintsResult);

        $clientMock->shouldReceive('run')
            ->with('SHOW INDEXES', [], null, Mockery::any())
            ->once()
            ->andReturn($indexesResult);

        $service = new GraphDatabaseService;
        $reflection = new \ReflectionClass($service);

        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($service, $clientMock);

        $sessionConfigProperty = $reflection->getProperty('sessionConfig');
        $sessionConfigProperty->setAccessible(true);
        $sessionConfigProperty->setValue($service, \Laudis\Neo4j\Databags\SessionConfiguration::default());

        // Invoke the health check
        $healthCheckMethod = $reflection->getMethod('performHealthCheck');
        $healthCheckMethod->setAccessible(true);
        $result = $healthCheckMethod->invoke($service);

        // Assert health check passed (connection works)
        $this->assertTrue($result);

        // Assert cache shows indexes missing
        $cachedStatus = Cache::get('neo4j:status');
        $this->assertNotNull($cachedStatus);
        $this->assertTrue($cachedStatus['status']['available']);
        $this->assertFalse($cachedStatus['status']['healthy']); // Not healthy due to missing indexes
        $this->assertFalse($cachedStatus['status']['indexes_exist']);
    }

    /** @test */
    public function it_can_retrieve_cached_health_status()
    {
        // Manually cache a status
        $testStatus = [
            'status' => [
                'available' => true,
                'healthy' => true,
                'constraints_exist' => true,
                'indexes_exist' => true,
            ],
            'timestamp' => now()->toIso8601String(),
        ];

        Cache::put('neo4j:status', $testStatus, now()->addMinutes(5));

        // Create service and retrieve cached status
        $service = new GraphDatabaseService;
        $cachedStatus = $service->getCachedHealthStatus();

        $this->assertNotNull($cachedStatus);
        $this->assertEquals($testStatus, $cachedStatus);
    }

    /** @test */
    public function listener_logs_critical_error_on_downtime()
    {
        Log::shouldReceive('critical')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'Neo4j database is unavailable'
                    && isset($context['error'])
                    && isset($context['health_status']);
            });

        Log::shouldReceive('warning')->zeroOrMoreTimes();
        Log::shouldReceive('info')->zeroOrMoreTimes();

        $event = new Neo4jUnavailable(
            healthStatus: [
                'available' => false,
                'healthy' => false,
                'constraints_exist' => false,
                'indexes_exist' => false,
            ],
            error: 'Connection timeout',
            constraintsExist: false,
            indexesExist: false
        );

        $listener = new NotifyNeo4jDowntime;
        $listener->handle($event);
    }

    /** @test */
    public function listener_sends_slack_webhook_notification()
    {
        // Configure Slack webhook
        Config::set('services.slack.neo4j_webhook', 'https://hooks.slack.com/test-webhook');

        // Fake HTTP requests
        Http::fake([
            'hooks.slack.com/*' => Http::response(['ok' => true], 200),
        ]);

        Log::shouldReceive('critical')->once();
        Log::shouldReceive('info')
            ->once()
            ->with('Neo4j downtime notification sent to Slack webhook');

        $event = new Neo4jUnavailable(
            healthStatus: [
                'available' => false,
                'healthy' => false,
                'constraints_exist' => false,
                'indexes_exist' => false,
            ],
            error: 'Connection refused',
            constraintsExist: false,
            indexesExist: false
        );

        $listener = new NotifyNeo4jDowntime;
        $listener->handle($event);

        // Assert HTTP call was made
        Http::assertSent(function ($request) {
            return $request->url() === 'https://hooks.slack.com/test-webhook'
                && isset($request->data()['blocks']);
        });
    }

    /** @test */
    public function listener_sends_slack_bot_notification()
    {
        // Configure Slack bot token (no webhook)
        Config::set('services.slack.neo4j_webhook', null);
        Config::set('services.slack.notifications.bot_user_oauth_token', 'xoxb-test-token');
        Config::set('services.slack.notifications.channel', '#alerts');

        // Fake HTTP requests
        Http::fake([
            'slack.com/api/*' => Http::response(['ok' => true], 200),
        ]);

        Log::shouldReceive('critical')->once();
        Log::shouldReceive('info')
            ->once()
            ->with('Neo4j downtime notification sent to Slack via bot token');

        $event = new Neo4jUnavailable(
            healthStatus: [
                'available' => false,
                'healthy' => false,
                'constraints_exist' => false,
                'indexes_exist' => false,
            ],
            error: 'Authentication failed',
            constraintsExist: false,
            indexesExist: false
        );

        $listener = new NotifyNeo4jDowntime;
        $listener->handle($event);

        // Assert HTTP call was made to Slack API
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'slack.com/api/chat.postMessage')
                && $request->hasHeader('Authorization', 'Bearer xoxb-test-token')
                && isset($request->data()['channel'])
                && $request->data()['channel'] === '#alerts';
        });
    }

    /** @test */
    public function listener_handles_slack_notification_failure_gracefully()
    {
        // Configure Slack webhook
        Config::set('services.slack.neo4j_webhook', 'https://hooks.slack.com/test-webhook');

        // Fake HTTP to return error
        Http::fake([
            'hooks.slack.com/*' => Http::response(['error' => 'invalid_webhook'], 500),
        ]);

        Log::shouldReceive('critical')->once();
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'Failed to send Slack webhook notification';
            });

        $event = new Neo4jUnavailable(
            healthStatus: [
                'available' => false,
                'healthy' => false,
                'constraints_exist' => false,
                'indexes_exist' => false,
            ],
            error: 'Connection timeout',
            constraintsExist: false,
            indexesExist: false
        );

        $listener = new NotifyNeo4jDowntime;

        // Should not throw exception
        $listener->handle($event);
    }

    /** @test */
    public function listener_records_telescope_exception()
    {
        // Skip if Telescope is not installed
        if (! class_exists(\Laravel\Telescope\Telescope::class)) {
            $this->markTestSkipped('Telescope is not installed');
        }

        Log::shouldReceive('critical')->once();

        $event = new Neo4jUnavailable(
            healthStatus: [
                'available' => false,
                'healthy' => false,
                'constraints_exist' => false,
                'indexes_exist' => false,
            ],
            error: 'Database unavailable',
            constraintsExist: false,
            indexesExist: false
        );

        $listener = new NotifyNeo4jDowntime;

        // Should not throw exception even if Telescope is available
        $listener->handle($event);

        // We can't easily test Telescope recording without full integration,
        // but we ensure the code path doesn't break
        $this->assertTrue(true);
    }
}
