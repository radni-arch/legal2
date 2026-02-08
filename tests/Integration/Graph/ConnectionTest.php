<?php

namespace Tests\Integration\Graph;

use PHPUnit\Framework\Attributes\Test;

class ConnectionTest extends GraphIntegrationTestCase
{
    #[Test]
    public function it_connects_to_neo4j_container(): void
    {
        $result = $this->graph->run('RETURN 1 as value');
        $this->assertEquals(1, $result->first()->get('value'));
    }

    #[Test]
    public function it_can_create_and_query_nodes(): void
    {
        $this->graph->run(
            'CREATE (n:TestNode {id: $id, name: $name})',
            ['id' => 'test_1', 'name' => 'Test Node']
        );

        $this->assertNodeExists('TestNode', ['id' => 'test_1']);
    }

    #[Test]
    public function it_cleans_database_between_tests(): void
    {
        // Previous test's node should not exist
        $result = $this->graph->run('MATCH (n:TestNode) RETURN count(n) as count');
        $this->assertEquals(0, $result->first()->get('count'));
    }
}
