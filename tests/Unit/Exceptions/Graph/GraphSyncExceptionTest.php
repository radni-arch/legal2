<?php

namespace Tests\Unit\Exceptions\Graph;

use App\Exceptions\Graph\GraphSyncException;
use PHPUnit\Framework\TestCase;

class GraphSyncExceptionTest extends TestCase
{
    /** @test */
    public function it_can_be_instantiated_with_message(): void
    {
        $exception = new GraphSyncException('Test sync failure');

        $this->assertInstanceOf(GraphSyncException::class, $exception);
        $this->assertEquals('Test sync failure', $exception->getMessage());
    }

    /** @test */
    public function it_stores_node_type_context(): void
    {
        $exception = new GraphSyncException('Test message');
        $exception->setNodeType('Judge');

        $this->assertEquals('Judge', $exception->getNodeType());
    }

    /** @test */
    public function it_stores_node_id_context(): void
    {
        $exception = new GraphSyncException('Test message');
        $exception->setNodeId(123);

        $this->assertEquals(123, $exception->getNodeId());
    }

    /** @test */
    public function it_stores_operation_context(): void
    {
        $exception = new GraphSyncException('Test message');
        $exception->setOperation('sync');

        $this->assertEquals('sync', $exception->getOperation());
    }

    /** @test */
    public function it_chains_previous_exception(): void
    {
        $previous = new \Exception('Previous error');
        $exception = new GraphSyncException('Sync failed', 0, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    /** @test */
    public function it_has_factory_method_for_node(): void
    {
        $previous = new \Exception('Database error');
        $exception = GraphSyncException::forNode('Judge', 123, $previous);

        $this->assertInstanceOf(GraphSyncException::class, $exception);
        $this->assertEquals('Judge', $exception->getNodeType());
        $this->assertEquals(123, $exception->getNodeId());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertStringContainsString('Judge', $exception->getMessage());
        $this->assertStringContainsString('123', $exception->getMessage());
    }

    /** @test */
    public function it_has_factory_method_for_node_without_previous_exception(): void
    {
        $exception = GraphSyncException::forNode('Party', 456);

        $this->assertInstanceOf(GraphSyncException::class, $exception);
        $this->assertEquals('Party', $exception->getNodeType());
        $this->assertEquals(456, $exception->getNodeId());
        $this->assertNull($exception->getPrevious());
    }

    /** @test */
    public function it_has_factory_method_for_operation(): void
    {
        $previous = new \Exception('Connection timeout');
        $exception = GraphSyncException::forOperation('create', 'Decision', 789, $previous);

        $this->assertInstanceOf(GraphSyncException::class, $exception);
        $this->assertEquals('create', $exception->getOperation());
        $this->assertEquals('Decision', $exception->getNodeType());
        $this->assertEquals(789, $exception->getNodeId());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertStringContainsString('create', $exception->getMessage());
        $this->assertStringContainsString('Decision', $exception->getMessage());
        $this->assertStringContainsString('789', $exception->getMessage());
    }

    /** @test */
    public function it_has_factory_method_for_operation_without_previous_exception(): void
    {
        $exception = GraphSyncException::forOperation('update', 'Citation', 321);

        $this->assertInstanceOf(GraphSyncException::class, $exception);
        $this->assertEquals('update', $exception->getOperation());
        $this->assertEquals('Citation', $exception->getNodeType());
        $this->assertEquals(321, $exception->getNodeId());
        $this->assertNull($exception->getPrevious());
    }

    /** @test */
    public function it_provides_useful_message_format(): void
    {
        $exception = GraphSyncException::forNode('Judge', 123);

        $message = $exception->getMessage();
        $this->assertStringContainsString('sync', strtolower($message));
        $this->assertStringContainsString('Judge', $message);
        $this->assertStringContainsString('123', $message);
    }

    /** @test */
    public function it_can_get_all_context_as_array(): void
    {
        $exception = GraphSyncException::forOperation('delete', 'Party', 555);

        $context = $exception->getContext();

        $this->assertIsArray($context);
        $this->assertEquals('delete', $context['operation']);
        $this->assertEquals('Party', $context['node_type']);
        $this->assertEquals(555, $context['node_id']);
    }

    /** @test */
    public function it_handles_null_values_in_context(): void
    {
        $exception = new GraphSyncException('Test message');

        $this->assertNull($exception->getNodeType());
        $this->assertNull($exception->getNodeId());
        $this->assertNull($exception->getOperation());
    }
}
