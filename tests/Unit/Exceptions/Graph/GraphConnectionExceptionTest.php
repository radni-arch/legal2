<?php

namespace Tests\Unit\Exceptions\Graph;

use App\Exceptions\Graph\GraphConnectionException;
use PHPUnit\Framework\TestCase;

class GraphConnectionExceptionTest extends TestCase
{
    /** @test */
    public function it_can_be_instantiated_with_message(): void
    {
        $exception = new GraphConnectionException('Connection failed');

        $this->assertInstanceOf(GraphConnectionException::class, $exception);
        $this->assertEquals('Connection failed', $exception->getMessage());
    }

    /** @test */
    public function it_stores_host_context(): void
    {
        $exception = new GraphConnectionException('Test message');
        $exception->setHost('neo4j://localhost:7687');

        $this->assertEquals('neo4j://localhost:7687', $exception->getHost());
    }

    /** @test */
    public function it_stores_connection_config_context(): void
    {
        $config = [
            'host' => 'neo4j://localhost:7687',
            'username' => 'neo4j',
            'database' => 'neo4j',
        ];

        $exception = new GraphConnectionException('Test message');
        $exception->setConnectionConfig($config);

        $this->assertEquals($config, $exception->getConnectionConfig());
    }

    /** @test */
    public function it_chains_previous_exception(): void
    {
        $previous = new \Exception('Network timeout');
        $exception = new GraphConnectionException('Connection failed', 0, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    /** @test */
    public function it_has_factory_method_for_host(): void
    {
        $previous = new \Exception('TCP connection refused');
        $exception = GraphConnectionException::forHost('neo4j://localhost:7687', $previous);

        $this->assertInstanceOf(GraphConnectionException::class, $exception);
        $this->assertEquals('neo4j://localhost:7687', $exception->getHost());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertStringContainsString('neo4j://localhost:7687', $exception->getMessage());
    }

    /** @test */
    public function it_has_factory_method_for_host_without_previous_exception(): void
    {
        $exception = GraphConnectionException::forHost('bolt://remote:7687');

        $this->assertInstanceOf(GraphConnectionException::class, $exception);
        $this->assertEquals('bolt://remote:7687', $exception->getHost());
        $this->assertNull($exception->getPrevious());
    }

    /** @test */
    public function it_has_factory_method_for_connection_config(): void
    {
        $config = [
            'host' => 'neo4j://localhost:7687',
            'username' => 'neo4j',
            'database' => 'neo4j',
        ];
        $previous = new \Exception('Authentication failed');
        $exception = GraphConnectionException::forConnection($config, $previous);

        $this->assertInstanceOf(GraphConnectionException::class, $exception);
        $this->assertEquals($config, $exception->getConnectionConfig());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertStringContainsString('neo4j://localhost:7687', $exception->getMessage());
    }

    /** @test */
    public function it_has_factory_method_for_connection_config_without_previous_exception(): void
    {
        $config = [
            'host' => 'bolt://remote:7687',
            'username' => 'user',
        ];
        $exception = GraphConnectionException::forConnection($config);

        $this->assertInstanceOf(GraphConnectionException::class, $exception);
        $this->assertEquals($config, $exception->getConnectionConfig());
        $this->assertNull($exception->getPrevious());
    }

    /** @test */
    public function it_provides_useful_message_format(): void
    {
        $exception = GraphConnectionException::forHost('neo4j://production:7687');

        $message = $exception->getMessage();
        $this->assertStringContainsString('connection', strtolower($message));
        $this->assertStringContainsString('neo4j://production:7687', $message);
    }

    /** @test */
    public function it_can_get_all_context_as_array(): void
    {
        $config = [
            'host' => 'neo4j://localhost:7687',
            'username' => 'neo4j',
        ];
        $exception = GraphConnectionException::forConnection($config);

        $context = $exception->getContext();

        $this->assertIsArray($context);
        $this->assertEquals($config, $context['connection_config']);
        $this->assertEquals('neo4j://localhost:7687', $context['host']);
    }

    /** @test */
    public function it_handles_null_values_in_context(): void
    {
        $exception = new GraphConnectionException('Test message');

        $this->assertNull($exception->getHost());
        $this->assertNull($exception->getConnectionConfig());
    }

    /** @test */
    public function it_extracts_host_from_connection_config(): void
    {
        $config = [
            'host' => 'bolt://database:7687',
            'username' => 'admin',
        ];
        $exception = GraphConnectionException::forConnection($config);

        // When connection config is set, host should be extracted
        $this->assertEquals('bolt://database:7687', $exception->getHost());
    }
}
