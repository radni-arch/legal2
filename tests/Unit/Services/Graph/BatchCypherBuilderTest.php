<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Graph\BatchCypherBuilder;
use Tests\TestCase;

class BatchCypherBuilderTest extends TestCase
{
    private BatchCypherBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new BatchCypherBuilder();
    }

    /** @test */
    public function it_builds_batch_upsert_query_for_nodes(): void
    {
        $nodes = [
            ['id' => 'node_1', 'name' => 'First', 'type' => 'test'],
            ['id' => 'node_2', 'name' => 'Second', 'type' => 'test'],
        ];

        $result = $this->builder->buildBatchUpsertNodes('TestLabel', $nodes, 'id');

        $this->assertArrayHasKey('query', $result);
        $this->assertArrayHasKey('parameters', $result);
        $this->assertStringContainsString('UNWIND', $result['query']);
        $this->assertStringContainsString('MERGE', $result['query']);
        $this->assertStringContainsString('TestLabel', $result['query']);
        $this->assertCount(2, $result['parameters']['nodes']);
    }

    /** @test */
    public function it_builds_batch_upsert_query_for_relationships(): void
    {
        $relationships = [
            ['fromId' => 'node_1', 'toId' => 'node_2', 'weight' => 0.8],
            ['fromId' => 'node_2', 'toId' => 'node_3', 'weight' => 0.6],
        ];

        $result = $this->builder->buildBatchUpsertRelationships(
            'FromLabel',
            'ToLabel',
            'RELATES_TO',
            $relationships
        );

        $this->assertArrayHasKey('query', $result);
        $this->assertArrayHasKey('parameters', $result);
        $this->assertStringContainsString('UNWIND', $result['query']);
        $this->assertStringContainsString('MERGE', $result['query']);
        $this->assertStringContainsString('RELATES_TO', $result['query']);
        $this->assertCount(2, $result['parameters']['relationships']);
    }

    /** @test */
    public function it_throws_exception_for_empty_nodes_array(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Nodes array cannot be empty');

        $this->builder->buildBatchUpsertNodes('TestLabel', []);
    }

    /** @test */
    public function it_throws_exception_for_empty_label(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Label cannot be empty');

        $this->builder->buildBatchUpsertNodes('', [['id' => '1']]);
    }

    /** @test */
    public function it_chunks_array_based_on_flush_threshold(): void
    {
        $builder = new BatchCypherBuilder(2);
        $items = [1, 2, 3, 4, 5];

        $chunks = $builder->chunk($items);

        $this->assertCount(3, $chunks);
        $this->assertEquals([1, 2], $chunks[0]);
        $this->assertEquals([3, 4], $chunks[1]);
        $this->assertEquals([5], $chunks[2]);
    }

    /** @test */
    public function it_uses_config_for_default_flush_threshold(): void
    {
        config(['graph.batch.flush_threshold' => 50]);

        $builder = new BatchCypherBuilder();

        $this->assertEquals(50, $builder->getFlushThreshold());
    }
}
