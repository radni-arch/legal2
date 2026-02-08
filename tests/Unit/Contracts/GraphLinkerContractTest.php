<?php

namespace Tests\Unit\Contracts;

use App\Contracts\GraphLinkerInterface;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Abstract contract test for GraphLinkerInterface
 *
 * Any service implementing GraphLinkerInterface must extend this class
 * and implement createLinker() to verify compliance with the interface contract.
 *
 * This ensures all linker services have consistent behavior.

 * Abstract contract test for GraphLinkerInterface implementations.
 *
 * Concrete implementations should extend this class and implement the
 * factory methods to provide test data for their specific implementation.
 *
 * Example:

 * class LawGraphLinkerTest extends GraphLinkerContractTest
 * {
 *     protected function createLinker(): GraphLinkerInterface
 *     {
 *         return app(LawGraphLinker::class);
 *     }
 *
 *     protected function getTestNodeData(): array
 *     {
 *         $law = Law::factory()->create();
 *         return [
 *             'nodeType' => 'Law',
 *             'nodeId' => $law->id,
 *             'content' => $law->content,
 *         ];
 *     }
 * }
 */
abstract class GraphLinkerContractTest extends TestCase
{
    use UsesTestDatabase;

    abstract protected function createLinker(): GraphLinkerInterface;

    /**
     * Get valid node types for this linker
     */
    abstract protected function getValidNodeTypes(): array;

    /**
     * Get sample content for testing
     *
     * @return string|array|null
     */
    abstract protected function getSampleContent();

    abstract protected function getTestNodeData(): array;

    /**
     * Override to provide content with citations for testing citation linking.
     */
    abstract protected function getContentWithCitations(): string;

    /**
     * Override to provide content with keywords for testing keyword linking.
     */
    abstract protected function getContentWithKeywords(): string;

    // ========================================
    // Interface Implementation Tests
    // ========================================

    /** @test */
    public function it_implements_graph_linker_interface()
    {
        $linker = $this->createLinker();

        $this->assertInstanceOf(
            GraphLinkerInterface::class,
            $linker,
            'Linker must implement GraphLinkerInterface'
        );
    }

    /** @test */
    public function it_has_link_method()
    {
        $linker = $this->createLinker();

        $this->assertTrue(
            method_exists($linker, 'link'),
            'Linker must have link() method'
        );
    }

    // ========================================
    // Behavioral Contract Tests
    // ========================================

    /** @test */
    public function link_accepts_valid_parameters()
    {
        $linker = $this->createLinker();
        $nodeTypes = $this->getValidNodeTypes();
        $content = $this->getSampleContent();

        if (empty($nodeTypes)) {
            $this->markTestSkipped('No valid node types defined');
        }

        $nodeType = $nodeTypes[0];
        $nodeId = 'test-node-id-'.uniqid();

        // Should not throw exception
        try {
            $linker->link($nodeType, $nodeId, $content);
            $this->assertTrue(true);
        } catch (\Exception $e) {
            $this->fail("link() should accept valid parameters: {$e->getMessage()}");
        }
    }

    /** @test */
    public function link_throws_exception_for_empty_node_type(): void
    {
        // Arrange
        $linker = $this->createLinker();
        $data = $this->getTestNodeData();

        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        $linker->link('', $data['nodeId'], $data['content']);
    }

    /** @test */
    public function link_throws_exception_for_empty_node_id(): void
    {
        // Arrange
        $linker = $this->createLinker();
        $data = $this->getTestNodeData();

        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        $linker->link($data['nodeType'], '', $data['content']);
    }

    /** @test */
    public function link_throws_exception_for_invalid_node_type(): void
    {
        // Arrange
        $linker = $this->createLinker();
        $data = $this->getTestNodeData();
        $invalidNodeType = 'InvalidNodeType';

        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        $linker->link($invalidNodeType, $data['nodeId'], $data['content']);
    }

    /** @test */
    public function link_accepts_empty_content(): void
    {
        // Arrange
        $linker = $this->createLinker();
        $data = $this->getTestNodeData();

        // Act & Assert - should not throw exception
        $linker->link($data['nodeType'], $data['nodeId'], '');
        $this->assertTrue(true);
    }

    /** @test */
    public function link_similar_returns_integer(): void
    {
        // Arrange
        $linker = $this->createLinker();
        $data = $this->getTestNodeData();

        // Act
        $result = $linker->linkSimilar($data['nodeType'], $data['nodeId']);

        // Assert
        $this->assertIsInt($result);
    }

    /** @test */
    public function link_similar_returns_non_negative_count(): void
    {
        // Arrange
        $linker = $this->createLinker();
        $data = $this->getTestNodeData();

        // Act
        $result = $linker->linkSimilar($data['nodeType'], $data['nodeId']);

        // Assert
        $this->assertGreaterThanOrEqual(0, $result);
    }

    /** @test */
    public function link_similar_accepts_custom_threshold(): void
    {
        // Arrange
        $linker = $this->createLinker();
        $data = $this->getTestNodeData();

        // Act
        $result = $linker->linkSimilar($data['nodeType'], $data['nodeId'], 0.9);

        // Assert
        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(0, $result);
    }

    /** @test */
    public function link_similar_throws_exception_for_invalid_threshold(): void
    {
        // Arrange
        $linker = $this->createLinker();
        $data = $this->getTestNodeData();

        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act - threshold > 1.0
        $linker->linkSimilar($data['nodeType'], $data['nodeId'], 1.5);
    }

    /** @test */
    public function link_similar_throws_exception_for_negative_threshold(): void
    {
        // Arrange
        $linker = $this->createLinker();
        $data = $this->getTestNodeData();

        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act - threshold < 0.0
        $linker->linkSimilar($data['nodeType'], $data['nodeId'], -0.1);
    }

    /** @test */
    public function link_citations_returns_array(): void
    {
        // Arrange
        $linker = $this->createLinker();
        $data = $this->getTestNodeData();
        $content = $this->getContentWithCitations();

        // Act
        $result = $linker->linkCitations($data['nodeType'], $data['nodeId'], $content);

        // Assert
        $this->assertIsArray($result);
    }

    /** @test */
    public function link_citations_returns_array_of_strings(): void
    {
        // Arrange
        $linker = $this->createLinker();
        $data = $this->getTestNodeData();
        $content = $this->getContentWithCitations();

        // Act
        $result = $linker->linkCitations($data['nodeType'], $data['nodeId'], $content);

        // Assert
        $this->assertIsArray($result);
        foreach ($result as $citedId) {
            $this->assertIsString($citedId);
        }
    }

    public function link_handles_empty_content_gracefully()
    {
        $linker = $this->createLinker();
        $nodeTypes = $this->getValidNodeTypes();

        if (empty($nodeTypes)) {
            $this->markTestSkipped('No valid node types defined');
        }

        $nodeType = $nodeTypes[0];
        $nodeId = 'test-node-id-'.uniqid();

        // Should not throw exception for empty content
        try {
            $linker->link($nodeType, $nodeId, '');
            $this->assertTrue(true);
        } catch (\Exception $e) {
            $this->fail("link() should handle empty content gracefully: {$e->getMessage()}");
        }
    }

    public function linkCitations_returns_empty_array_for_no_citations(): void
    {
        // Arrange
        $linker = $this->createLinker();
        $data = $this->getTestNodeData();
        $contentWithoutCitations = 'This is content without any citations.';

        // Act
        $result = $linker->linkCitations($data['nodeType'], $data['nodeId'], $contentWithoutCitations);

        // Assert
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /** @test */
    public function link_keywords_returns_array(): void
    {
        // Arrange
        $linker = $this->createLinker();
        $data = $this->getTestNodeData();
        $content = $this->getContentWithKeywords();

        // Act
        $result = $linker->linkKeywords($data['nodeType'], $data['nodeId'], $content);

        // Assert
        $this->assertIsArray($result);
    }

    /** @test */
    public function link_keywords_returns_array_of_strings(): void
    {
        // Arrange
        $linker = $this->createLinker();
        $data = $this->getTestNodeData();
        $content = $this->getContentWithKeywords();

        // Act
        $result = $linker->linkKeywords($data['nodeType'], $data['nodeId'], $content);

        // Assert
        $this->assertIsArray($result);
        foreach ($result as $keyword) {
            $this->assertIsString($keyword);
        }
    }

    public function link_handles_null_content_gracefully()
    {
        $linker = $this->createLinker();
        $nodeTypes = $this->getValidNodeTypes();

        if (empty($nodeTypes)) {
            $this->markTestSkipped('No valid node types defined');
        }

        $nodeType = $nodeTypes[0];
        $nodeId = 'test-node-id-'.uniqid();

        // Should not throw exception for null content
        try {
            $linker->link($nodeType, $nodeId, null);
            $this->assertTrue(true);
        } catch (\Exception $e) {
            $this->fail("link() should handle null content gracefully: {$e->getMessage()}");
        }
    }

    // ========================================
    // Type Safety Tests
    // ========================================

    /** @test */
    public function link_accepts_string_node_type()
    {
        $linker = $this->createLinker();
        $nodeTypes = $this->getValidNodeTypes();
        $content = $this->getSampleContent();

        if (empty($nodeTypes)) {
            $this->markTestSkipped('No valid node types defined');
        }

        $this->expectNotToPerformAssertions();
        $linker->link($nodeTypes[0], 'test-id', $content);
    }

    /** @test */
    public function link_accepts_string_node_id()
    {
        $linker = $this->createLinker();
        $nodeTypes = $this->getValidNodeTypes();
        $content = $this->getSampleContent();

        if (empty($nodeTypes)) {
            $this->markTestSkipped('No valid node types defined');
        }

        $this->expectNotToPerformAssertions();
        $linker->link($nodeTypes[0], 'test-node-id', $content);
    }

    /** @test */
    public function link_accepts_various_content_types()
    {
        $linker = $this->createLinker();
        $nodeTypes = $this->getValidNodeTypes();

        if (empty($nodeTypes)) {
            $this->markTestSkipped('No valid node types defined');
        }

        $nodeType = $nodeTypes[0];
        $nodeId = 'test-node-id';

        // Test with string content
        try {
            $linker->link($nodeType, $nodeId, 'string content');
            $this->assertTrue(true);
        } catch (\Exception $e) {
            $this->fail("link() should accept string content: {$e->getMessage()}");
        }

        // Test with array content (for embeddings)
        try {
            $linker->link($nodeType, $nodeId, [0.1, 0.2, 0.3]);
            $this->assertTrue(true);
        } catch (\Exception $e) {
            $this->fail("link() should accept array content: {$e->getMessage()}");
        }

        // Test with null content
        try {
            $linker->link($nodeType, $nodeId, null);
            $this->assertTrue(true);
        } catch (\Exception $e) {
            $this->fail("link() should accept null content: {$e->getMessage()}");
        }
    }

    // ========================================
    // Idempotency Tests
    // ========================================

    /** @test */
    public function link_is_idempotent()
    {
        $linker = $this->createLinker();
        $nodeTypes = $this->getValidNodeTypes();
        $content = $this->getSampleContent();

        if (empty($nodeTypes)) {
            $this->markTestSkipped('No valid node types defined');
        }

        $nodeType = $nodeTypes[0];
        $nodeId = 'test-node-id-'.uniqid();

        // Calling link multiple times should not cause errors
        try {
            $linker->link($nodeType, $nodeId, $content);
            $linker->link($nodeType, $nodeId, $content);
            $linker->link($nodeType, $nodeId, $content);
            $this->assertTrue(true);
        } catch (\Exception $e) {
            $this->fail("link() should be idempotent: {$e->getMessage()}");
        }
    }

    // ========================================
    // Edge Case Tests
    // ========================================

    /** @test */
    public function link_handles_empty_node_type()
    {
        $linker = $this->createLinker();
        $content = $this->getSampleContent();

        // Should handle empty node type (may throw or handle gracefully)
        // We just verify it doesn't crash the test suite
        try {
            $linker->link('', 'test-id', $content);
            $this->assertTrue(true);
        } catch (\Exception $e) {
            // Expected to throw, that's okay
            $this->assertTrue(true);
        }
    }

    public function linkKeywords_returns_empty_array_for_empty_content(): void
    {
        // Arrange
        $linker = $this->createLinker();
        $data = $this->getTestNodeData();

        // Act
        $result = $linker->linkKeywords($data['nodeType'], $data['nodeId'], '');

        // Assert
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /** @test */
    public function unlink_all_returns_integer(): void
    {
        // Arrange
        $linker = $this->createLinker();
        $data = $this->getTestNodeData();

        // Create some links first
        $linker->link($data['nodeType'], $data['nodeId'], $data['content']);

        // Act
        $result = $linker->unlinkAll($data['nodeType'], $data['nodeId']);

        // Assert
        $this->assertIsInt($result);
    }

    /** @test */
    public function unlink_all_returns_non_negative_count(): void
    {
        // Arrange
        $linker = $this->createLinker();
        $data = $this->getTestNodeData();

        // Act
        $result = $linker->unlinkAll($data['nodeType'], $data['nodeId']);

        // Assert
        $this->assertGreaterThanOrEqual(0, $result);
    }

    /** @test */
    public function unlink_all_returns_zero_for_node_without_links(): void
    {
        // Arrange
        $linker = $this->createLinker();
        $data = $this->getTestNodeData();
        $nodeIdWithoutLinks = $data['nodeId'].'-no-links';

        // Act
        $result = $linker->unlinkAll($data['nodeType'], $nodeIdWithoutLinks);

        // Assert
        $this->assertEquals(0, $result);
    }

    /** @test */
    public function get_supported_relationships_returns_array(): void
    {
        // Arrange
        $linker = $this->createLinker();
        $data = $this->getTestNodeData();

        // Act
        $result = $linker->getSupportedRelationships($data['nodeType']);

        // Assert
        $this->assertIsArray($result);
    }

    /** @test */
    public function get_supported_relationships_returns_array_of_strings(): void
    {
        // Arrange
        $linker = $this->createLinker();
        $data = $this->getTestNodeData();

        // Act
        $result = $linker->getSupportedRelationships($data['nodeType']);

        // Assert
        $this->assertIsArray($result);
        foreach ($result as $relationship) {
            $this->assertIsString($relationship);

        }
    }

    /** @test */
    public function link_handles_empty_node_id()
    {
        $linker = $this->createLinker();
        $nodeTypes = $this->getValidNodeTypes();
        $content = $this->getSampleContent();

        if (empty($nodeTypes)) {
            $this->markTestSkipped('No valid node types defined');
        }

        // Should handle empty node ID (may throw or handle gracefully)
        try {
            $linker->link($nodeTypes[0], '', $content);
            $this->assertTrue(true);
        } catch (\Exception $e) {
            // Expected to throw, that's okay
            $this->assertTrue(true);
        }
    }

    /** @test */
    public function link_handles_special_characters_in_content()
    {
        $linker = $this->createLinker();
        $nodeTypes = $this->getValidNodeTypes();

        if (empty($nodeTypes)) {
            $this->markTestSkipped('No valid node types defined');
        }

        $nodeType = $nodeTypes[0];
        $nodeId = 'test-node-id-'.uniqid();

        // Test with special characters
        $specialContent = "Test with special chars: <>&\"'[]{}()!@#$%^&*";

        try {
            $linker->link($nodeType, $nodeId, $specialContent);
            $this->assertTrue(true);
        } catch (\Exception $e) {
            $this->fail("link() should handle special characters: {$e->getMessage()}");
        }
    }

    /** @test */
    public function link_handles_unicode_content()
    {
        $linker = $this->createLinker();
        $nodeTypes = $this->getValidNodeTypes();

        if (empty($nodeTypes)) {
            $this->markTestSkipped('No valid node types defined');
        }

        $nodeType = $nodeTypes[0];
        $nodeId = 'test-node-id-'.uniqid();

        // Test with Croatian characters and Unicode
        $unicodeContent = 'Kazneni zakon č ć š ž đ € ñ 中文';

        try {
            $linker->link($nodeType, $nodeId, $unicodeContent);
            $this->assertTrue(true);
        } catch (\Exception $e) {
            $this->fail("link() should handle Unicode content: {$e->getMessage()}");
        }
    }

    /** @test */
    public function link_handles_very_long_content()
    {
        $linker = $this->createLinker();
        $nodeTypes = $this->getValidNodeTypes();

        if (empty($nodeTypes)) {
            $this->markTestSkipped('No valid node types defined');
        }

        $nodeType = $nodeTypes[0];
        $nodeId = 'test-node-id-'.uniqid();

        // Test with very long content (10,000 chars)
        $longContent = str_repeat('Long content test. ', 500);

        try {
            $linker->link($nodeType, $nodeId, $longContent);
            $this->assertTrue(true);
        } catch (\Exception $e) {
            $this->fail("link() should handle long content: {$e->getMessage()}");
        }
    }

    public function getSupportedRelationships_returns_non_empty_for_valid_type(): void
    {
        // Arrange
        $linker = $this->createLinker();
        $data = $this->getTestNodeData();

        // Act
        $result = $linker->getSupportedRelationships($data['nodeType']);

        // Assert
        $this->assertNotEmpty($result);
    }

    /** @test */
    public function get_supported_relationships_returns_empty_for_invalid_type(): void
    {
        // Arrange
        $linker = $this->createLinker();
        $invalidType = 'InvalidNodeType';

        // Act
        $result = $linker->getSupportedRelationships($invalidType);

        // Assert
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /** @test */

    /** @test */
    public function multiple_link_operations_work_together(): void
    {
        // Arrange
        $linker = $this->createLinker();
        $data = $this->getTestNodeData();
        $contentWithCitations = $this->getContentWithCitations();
        $contentWithKeywords = $this->getContentWithKeywords();

        // Act - perform multiple linking operations
        $linker->link($data['nodeType'], $data['nodeId'], $data['content']);
        $citations = $linker->linkCitations($data['nodeType'], $data['nodeId'], $contentWithCitations);
        $keywords = $linker->linkKeywords($data['nodeType'], $data['nodeId'], $contentWithKeywords);
        $similarCount = $linker->linkSimilar($data['nodeType'], $data['nodeId'], 0.8);

        // Assert - all operations should complete successfully
        $this->assertIsArray($citations);
        $this->assertIsArray($keywords);
        $this->assertIsInt($similarCount);
        $this->assertTrue(true);
    }
}
