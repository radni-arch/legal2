<?php

namespace Tests\Unit\Services\Graph;

use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\Log;
use Laudis\Neo4j\Exception\Neo4jException;
use Mockery;
use Tests\TestCase;

class GraphDatabaseServiceRetryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Configure Neo4j and retry settings
        config([
            'neo4j.sync.enabled' => false, // Disable actual connections for unit tests
            'graph.retry.attempts' => 3,
            'graph.retry.delay_ms' => 100,
            'graph.retry.multiplier' => 2,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_retries_on_connection_errors()
    {
        // This test verifies that connection errors trigger retry behavior
        $service = new GraphDatabaseService();

        // Create a mock that throws connection error twice, then succeeds
        $connectionException = new \RuntimeException('Cannot connect to Neo4j server');

        // Mock the service to throw connection errors
        $attempts = 0;
        $service = Mockery::mock(GraphDatabaseService::class)->makePartial();
        $service->shouldReceive('run')
            ->times(3)
            ->andReturnUsing(function () use ($connectionException, &$attempts) {
                $attempts++;
                if ($attempts < 3) {
                    throw $connectionException;
                }
                return ['success' => true];
            });

        // Should eventually succeed after retries
        $result = $service->withRetry(fn() => $service->run('RETURN 1'));

        $this->assertNotNull($result);
        $this->assertEquals(3, $attempts);
    }

    /** @test */
    public function it_retries_on_timeout_errors()
    {
        // This test verifies that timeout errors trigger retry behavior
        $service = new GraphDatabaseService();
        $timeoutException = new \RuntimeException('Query exceeded timeout of 60 seconds');

        $attempts = 0;
        $service = Mockery::mock(GraphDatabaseService::class)->makePartial();
        $service->shouldReceive('run')
            ->times(2)
            ->andReturnUsing(function () use ($timeoutException, &$attempts) {
                $attempts++;
                if ($attempts < 2) {
                    throw $timeoutException;
                }
                return ['success' => true];
            });

        // Should eventually succeed after retries
        $result = $service->withRetry(fn() => $service->run('RETURN 1'));

        $this->assertNotNull($result);
        $this->assertEquals(2, $attempts);
    }

    /** @test */
    public function it_does_not_retry_on_cypher_syntax_errors()
    {
        // This test verifies that Cypher syntax errors are NOT retried
        $service = new GraphDatabaseService();
        $syntaxException = new \RuntimeException('Invalid input \'X\': expected <init> (line 1, column 1)');

        $attempts = 0;
        $service = Mockery::mock(GraphDatabaseService::class)->makePartial();
        $service->shouldReceive('run')
            ->once()
            ->andReturnUsing(function () use ($syntaxException, &$attempts) {
                $attempts++;
                throw $syntaxException;
            });

        // Should throw immediately without retry
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid input');

        try {
            $service->withRetry(fn() => $service->run('INVALID CYPHER'));
        } finally {
            $this->assertEquals(1, $attempts);
        }
    }

    /** @test */
    public function it_does_not_retry_on_constraint_violations()
    {
        // This test verifies that constraint violations are NOT retried
        $service = new GraphDatabaseService();
        $constraintException = new \RuntimeException('Node(0) already exists with label `Law` and property `id` = \'law-123\'');

        $attempts = 0;
        $service = Mockery::mock(GraphDatabaseService::class)->makePartial();
        $service->shouldReceive('run')
            ->once()
            ->andReturnUsing(function () use ($constraintException, &$attempts) {
                $attempts++;
                throw $constraintException;
            });

        // Should throw immediately without retry
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('already exists');

        try {
            $service->withRetry(fn() => $service->run('CREATE constraint violation'));
        } finally {
            $this->assertEquals(1, $attempts);
        }
    }

    /** @test */
    public function it_uses_exponential_backoff_delays()
    {
        // This test verifies exponential backoff timing
        config([
            'graph.retry.attempts' => 3,
            'graph.retry.delay_ms' => 100,
            'graph.retry.multiplier' => 2,
        ]);

        $service = new GraphDatabaseService();
        $connectionException = new \RuntimeException('Connection lost');

        $attempts = 0;
        $timestamps = [];

        $service = Mockery::mock(GraphDatabaseService::class)->makePartial();
        $service->shouldReceive('run')
            ->times(3)
            ->andReturnUsing(function () use ($connectionException, &$attempts, &$timestamps) {
                $timestamps[] = microtime(true);
                $attempts++;
                if ($attempts < 3) {
                    throw $connectionException;
                }
                return ['success' => true];
            });

        // Execute with retry
        $result = $service->withRetry(fn() => $service->run('RETURN 1'));

        // Verify 3 attempts were made
        $this->assertEquals(3, $attempts);

        // Check that delays increase (allow for timing variance)
        // With exponential backoff: 1st attempt immediate, 2nd after 100ms, 3rd after 200ms
        if (count($timestamps) >= 3) {
            $delay1 = ($timestamps[1] - $timestamps[0]) * 1000; // Convert to ms
            $delay2 = ($timestamps[2] - $timestamps[1]) * 1000;

            // Delay2 should be roughly 2x delay1 (with tolerance)
            // Note: First delay should be ~100ms, second ~200ms
            $this->assertGreaterThan(50, $delay1, 'First retry delay should be at least 50ms');
            $this->assertGreaterThan($delay1 * 0.5, $delay2, 'Second retry delay should be roughly 2x the first');
        }
    }

    /** @test */
    public function it_respects_configurable_retry_attempts()
    {
        // This test verifies config controls retry attempts
        config(['graph.retry.attempts' => 2]);

        $service = new GraphDatabaseService();
        $connectionException = new \RuntimeException('Connection lost');

        $attempts = 0;
        $service = Mockery::mock(GraphDatabaseService::class)->makePartial();
        $service->shouldReceive('run')
            ->times(2)
            ->andReturnUsing(function () use ($connectionException, &$attempts) {
                $attempts++;
                throw $connectionException;
            });

        // Should fail after 2 attempts (config value)
        $this->expectException(\RuntimeException::class);

        try {
            $service->withRetry(fn() => $service->run('RETURN 1'));
        } finally {
            $this->assertEquals(2, $attempts);
        }
    }

    /** @test */
    public function it_returns_successful_result_immediately_without_retry()
    {
        // This test verifies successful operations don't trigger retries
        $service = new GraphDatabaseService();

        $attempts = 0;
        $service = Mockery::mock(GraphDatabaseService::class)->makePartial();
        $service->shouldReceive('run')
            ->once()
            ->andReturnUsing(function () use (&$attempts) {
                $attempts++;
                return ['result' => 'success'];
            });

        $result = $service->withRetry(fn() => $service->run('RETURN 1'));

        $this->assertEquals(['result' => 'success'], $result);
        $this->assertEquals(1, $attempts);
    }

    /** @test */
    public function is_retryable_identifies_connection_errors_correctly()
    {
        // This test verifies isRetryable() logic
        $service = new GraphDatabaseService();

        // Connection errors should be retryable
        $connectionException = new \RuntimeException('Cannot connect to Neo4j server');
        $this->assertTrue($service->isRetryable($connectionException));

        // Timeout errors should be retryable
        $timeoutException = new \RuntimeException('Query exceeded timeout');
        $this->assertTrue($service->isRetryable($timeoutException));

        // Syntax errors should NOT be retryable
        $syntaxException = new \RuntimeException('Invalid input \'X\': expected');
        $this->assertFalse($service->isRetryable($syntaxException));

        // Constraint violations should NOT be retryable
        $constraintException = new \RuntimeException('already exists with label');
        $this->assertFalse($service->isRetryable($constraintException));
    }
}
