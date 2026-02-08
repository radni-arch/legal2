<?php

namespace Tests\Integration\Graph;

use PHPUnit\Framework\Attributes\Test;

class ConstraintViolationTest extends GraphIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create test constraint for this test suite
        try {
            $this->graph->run(
                'CREATE CONSTRAINT test_unique_id IF NOT EXISTS
                 FOR (n:ConstraintTestNode) REQUIRE n.unique_id IS UNIQUE'
            );
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'not available')) {
                $this->markTestSkipped('Neo4j not available');
            }
            // Ignore if constraint already exists
        }
    }

    protected function tearDown(): void
    {
        // Clean up test nodes
        try {
            $this->graph->run('MATCH (n:ConstraintTestNode) DETACH DELETE n');
        } catch (\Throwable $e) {
            // Ignore cleanup errors
        }

        parent::tearDown();
    }

    #[Test]
    public function it_throws_exception_on_unique_constraint_violation(): void
    {
        $uniqueId = 'unique_test_' . uniqid();

        // Create first node - should succeed
        try {
            $this->graph->run(
                'CREATE (n:ConstraintTestNode {unique_id: $id, name: $name})',
                ['id' => $uniqueId, 'name' => 'First Node']
            );
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'not available')) {
                $this->markTestSkipped('Neo4j not available');
            }
            throw $e;
        }

        // Try to create second node with same unique_id - should fail
        $this->expectException(\Throwable::class);

        $this->graph->run(
            'CREATE (n:ConstraintTestNode {unique_id: $id, name: $name})',
            ['id' => $uniqueId, 'name' => 'Duplicate Node']
        );
    }

    #[Test]
    public function it_allows_merge_on_existing_unique_id(): void
    {
        $uniqueId = 'merge_test_' . uniqid();

        try {
            // Create initial node
            $this->graph->run(
                'CREATE (n:ConstraintTestNode {unique_id: $id, name: $name})',
                ['id' => $uniqueId, 'name' => 'Initial']
            );

            // MERGE should not throw - it finds existing node
            $this->graph->run(
                'MERGE (n:ConstraintTestNode {unique_id: $id}) SET n.name = $name RETURN n',
                ['id' => $uniqueId, 'name' => 'Updated']
            );

            // Verify update worked
            $result = $this->graph->run(
                'MATCH (n:ConstraintTestNode {unique_id: $id}) RETURN n.name as name',
                ['id' => $uniqueId]
            );

            $this->assertEquals('Updated', $result[0]['name'] ?? null);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'not available')) {
                $this->markTestSkipped('Neo4j not available');
            }
            throw $e;
        }
    }

    #[Test]
    public function it_handles_constraint_violation_exception_details(): void
    {
        $uniqueId = 'error_detail_test_' . uniqid();

        try {
            // Create first node
            $this->graph->run(
                'CREATE (n:ConstraintTestNode {unique_id: $id})',
                ['id' => $uniqueId]
            );
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'not available')) {
                $this->markTestSkipped('Neo4j not available');
            }
            throw $e;
        }

        // Try duplicate and capture exception
        try {
            $this->graph->run(
                'CREATE (n:ConstraintTestNode {unique_id: $id})',
                ['id' => $uniqueId]
            );
            $this->fail('Expected constraint violation exception');
        } catch (\Throwable $e) {
            // Verify exception contains useful information
            $message = $e->getMessage();
            $this->assertTrue(
                str_contains($message, 'constraint') ||
                str_contains($message, 'unique') ||
                str_contains($message, 'already exists') ||
                str_contains($message, 'ConstraintValidationFailed'),
                "Exception should mention constraint violation: {$message}"
            );
        }
    }

    #[Test]
    public function it_enforces_node_label_constraint(): void
    {
        // Test that constraints are label-specific
        $sharedId = 'shared_id_' . uniqid();

        try {
            // Create node with ConstraintTestNode label
            $this->graph->run(
                'CREATE (n:ConstraintTestNode {unique_id: $id})',
                ['id' => $sharedId]
            );

            // Same ID on different label should work (no constraint there)
            $this->graph->run(
                'CREATE (n:OtherTestNode {unique_id: $id})',
                ['id' => $sharedId]
            );

            // Verify both exist
            $result1 = $this->graph->run(
                'MATCH (n:ConstraintTestNode {unique_id: $id}) RETURN count(n) as c',
                ['id' => $sharedId]
            );
            $result2 = $this->graph->run(
                'MATCH (n:OtherTestNode {unique_id: $id}) RETURN count(n) as c',
                ['id' => $sharedId]
            );

            $this->assertEquals(1, $result1[0]['c'] ?? 0);
            $this->assertEquals(1, $result2[0]['c'] ?? 0);

            // Cleanup OtherTestNode
            $this->graph->run('MATCH (n:OtherTestNode) DELETE n');
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'not available')) {
                $this->markTestSkipped('Neo4j not available');
            }
            throw $e;
        }
    }

    #[Test]
    public function it_handles_multiple_unique_properties(): void
    {
        // Create a composite constraint for testing
        try {
            $this->graph->run(
                'CREATE CONSTRAINT test_composite IF NOT EXISTS
                 FOR (n:CompositeTestNode) REQUIRE (n.type, n.code) IS UNIQUE'
            );
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'not available')) {
                $this->markTestSkipped('Neo4j not available');
            }
            // May fail on older Neo4j versions, skip test
            if (str_contains($e->getMessage(), 'Invalid') || str_contains($e->getMessage(), 'not supported')) {
                $this->markTestSkipped('Composite constraints not supported');
            }
        }

        try {
            // Create first node with type+code combination
            $this->graph->run(
                'CREATE (n:CompositeTestNode {type: $type, code: $code})',
                ['type' => 'A', 'code' => '001']
            );

            // Same type, different code - should work
            $this->graph->run(
                'CREATE (n:CompositeTestNode {type: $type, code: $code})',
                ['type' => 'A', 'code' => '002']
            );

            // Different type, same code - should work
            $this->graph->run(
                'CREATE (n:CompositeTestNode {type: $type, code: $code})',
                ['type' => 'B', 'code' => '001']
            );

            $this->assertTrue(true, 'Different combinations should be allowed');

            // Same type AND code - should fail
            $this->expectException(\Throwable::class);
            $this->graph->run(
                'CREATE (n:CompositeTestNode {type: $type, code: $code})',
                ['type' => 'A', 'code' => '001']
            );
        } finally {
            // Cleanup
            try {
                $this->graph->run('MATCH (n:CompositeTestNode) DELETE n');
                $this->graph->run('DROP CONSTRAINT test_composite IF EXISTS');
            } catch (\Throwable $e) {
                // Ignore
            }
        }
    }
}
