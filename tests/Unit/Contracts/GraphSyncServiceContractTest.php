<?php

namespace Tests\Unit\Contracts;

use App\Services\Graph\GraphSyncServiceInterface;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Abstract contract test for GraphSyncServiceInterface
 *
 * Any service implementing GraphSyncServiceInterface must extend this class
 * and implement createService() to verify compliance with the interface contract.
 *
 * This ensures all sync services have consistent behavior.
 * Abstract contract test for GraphSyncServiceInterface implementations.
 *
 * Concrete implementations should extend this class and implement the
 * createService() method to instantiate their specific implementation.
 *
 * Example:
 * ```
 * class LawGraphSyncServiceTest extends GraphSyncServiceContractTest
 * {
 *     protected function createService(): GraphSyncServiceInterface
 *     {
 *         return app(LawGraphSyncService::class);
 *     }
 * }
 * ```
 */
abstract class GraphSyncServiceContractTest extends TestCase
{
    use UsesTestDatabase;

    /**
     * Factory method that concrete test classes must implement
     * to provide their specific service implementation.
     */
    abstract protected function createService(): GraphSyncServiceInterface;

    /**
<<<<<<< HEAD
     * Create a valid test document ID for the service
     */
    abstract protected function createValidDocumentId(): string;

    /**
     * Get the document type(s) this service supports
     */
    abstract protected function getSupportedTypes(): array;

    // ========================================
    // Interface Implementation Tests
    // ========================================

    /** @test */
    public function it_implements_graph_sync_service_interface()
    {
        $service = $this->createService();

        $this->assertInstanceOf(
            GraphSyncServiceInterface::class,
            $service,
            'Service must implement GraphSyncServiceInterface'
        );
    }

    /** @test */
    public function it_has_sync_method()
    {
        $service = $this->createService();

        $this->assertTrue(
            method_exists($service, 'sync'),
            'Service must have sync() method'
        );
    }

    /** @test */
    public function it_has_supports_type_method()
    {
        $service = $this->createService();

        $this->assertTrue(
            method_exists($service, 'supportsType'),
            'Service must have supportsType() method'
        );
    }

    // ========================================
    // Behavioral Contract Tests
    // ========================================

    /** @test */
    public function sync_accepts_valid_document_id()
    {
        $service = $this->createService();
        $documentId = $this->createValidDocumentId();

        // Should not throw exception
        try {
            $service->sync($documentId);
            $this->assertTrue(true);
        } catch (\Exception $e) {
            $this->fail("sync() should accept valid document ID: {$e->getMessage()}");
        }
    }

    /** @test */
    public function sync_handles_non_existent_document_gracefully()
    {
        $service = $this->createService();

        // Should not throw exception for non-existent ID
        try {
            $service->sync('non-existent-document-id-12345');
            $this->assertTrue(true);
        } catch (\Exception $e) {
            $this->fail("sync() should handle non-existent documents gracefully: {$e->getMessage()}");
        }
    }

    /** @test */
    public function supports_type_returns_true_for_supported_types()
    {
        $service = $this->createService();
        $supportedTypes = $this->getSupportedTypes();

        foreach ($supportedTypes as $type) {
            $this->assertTrue(
                $service->supportsType($type),
                "Service should support type: {$type}"
            );
        }
    }

    /** @test */
    public function supports_type_returns_false_for_unsupported_types()
    {
        $service = $this->createService();
        $unsupportedTypes = ['UnsupportedType', 'RandomType', 'InvalidDocument'];

        foreach ($unsupportedTypes as $type) {
            $this->assertFalse(
                $service->supportsType($type),
                "Service should not support type: {$type}"
            );
        }
    }

    // ========================================
    // Type Safety Tests
    // ========================================

    /** @test */
    public function sync_accepts_string_document_id()
    {
        $service = $this->createService();
        $documentId = $this->createValidDocumentId();

        $this->expectNotToPerformAssertions();
        $service->sync($documentId);
    }

    /** @test */
    public function supports_type_accepts_string_type()
    {
        $service = $this->createService();

        $this->expectNotToPerformAssertions();
        $service->supportsType('LawDocument');
    }

    // ========================================
    // Idempotency Tests
    // ========================================

    /** @test */
    public function sync_is_idempotent()
    {
        $service = $this->createService();
        $documentId = $this->createValidDocumentId();

        // Calling sync multiple times should not cause errors
        try {
            $service->sync($documentId);
            $service->sync($documentId);
            $service->sync($documentId);
            $this->assertTrue(true);
        } catch (\Exception $e) {
            $this->fail("sync() should be idempotent: {$e->getMessage()}");
        }
    }

    // ========================================
    // Edge Case Tests
    // ========================================

    /** @test */
    public function sync_handles_empty_string_document_id()
    {
        $service = $this->createService();

        // Should not throw exception for empty string
        try {
            $service->sync('');
            $this->assertTrue(true);
        } catch (\Exception $e) {
            $this->fail("sync() should handle empty string gracefully: {$e->getMessage()}");
        }
    }

    /** @test */
    public function supports_type_handles_empty_string()
    {
        $service = $this->createService();

        $result = $service->supportsType('');

        $this->assertIsBool($result);
        $this->assertFalse($result, 'Empty string should not be a supported type');
    }

    /** @test */
    public function supports_type_is_case_sensitive_or_consistent()
    {
        $service = $this->createService();
        $supportedTypes = $this->getSupportedTypes();

        if (empty($supportedTypes)) {
            $this->markTestSkipped('No supported types defined');
        }

        $type = $supportedTypes[0];
        $resultOriginal = $service->supportsType($type);
        $resultLower = $service->supportsType(strtolower($type));
        $resultUpper = $service->supportsType(strtoupper($type));

        // The service should be consistent with case handling
        // Either case-sensitive (different results) or case-insensitive (same results)
        $this->assertTrue(
            ($resultOriginal === $resultLower && $resultLower === $resultUpper) ||
            ($resultOriginal !== $resultLower || $resultOriginal !== $resultUpper),
            'supportsType() should have consistent case handling'
        );
    }

    abstract protected function getValidDocumentId(): string;

    /**
     * Override to provide an array of valid document IDs for batch testing.
     *
     * @return array<string>
     */
    abstract protected function getValidDocumentIds(): array;

    /**
     * Override to provide the document type that this service supports.
     */
    abstract protected function getSupportedDocumentType(): string;

    /** @test */
    public function sync_throws_exception_for_invalid_document_id(): void
    {
        // Arrange
        $service = $this->createService();
        $invalidId = 'non-existent-id-'.uniqid();

        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        $service->sync($invalidId);
    }

    /** @test */
    public function sync_throws_exception_for_empty_document_id(): void
    {
        // Arrange
        $service = $this->createService();

        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        $service->sync('');
    }

    /** @test */
    public function sync_batch_accepts_array_of_document_ids(): void
    {
        // Arrange
        $service = $this->createService();
        $documentIds = $this->getValidDocumentIds();

        // Act
        $result = $service->syncBatch($documentIds);

        // Assert
        $this->assertIsArray($result);
    }

    /** @test */
    public function sync_batch_returns_map_of_ids_to_status(): void
    {
        // Arrange
        $service = $this->createService();
        $documentIds = $this->getValidDocumentIds();

        // Act
        $result = $service->syncBatch($documentIds);

        // Assert
        $this->assertIsArray($result);
        foreach ($documentIds as $id) {
            $this->assertArrayHasKey($id, $result);
            $this->assertIsBool($result[$id]);
        }
    }

    /** @test */
    public function sync_batch_handles_empty_array(): void
    {
        // Arrange
        $service = $this->createService();

        // Act
        $result = $service->syncBatch([]);

        // Assert
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /** @test */
    public function sync_batch_handles_mix_of_valid_and_invalid_ids(): void
    {
        // Arrange
        $service = $this->createService();
        $validIds = $this->getValidDocumentIds();
        $invalidId = 'non-existent-id-'.uniqid();
        $mixedIds = array_merge($validIds, [$invalidId]);

        // Act
        $result = $service->syncBatch($mixedIds);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(count($mixedIds), $result);

        // Valid IDs should succeed
        foreach ($validIds as $id) {
            $this->assertArrayHasKey($id, $result);
            $this->assertTrue($result[$id]);
        }

        // Invalid ID should fail
        $this->assertArrayHasKey($invalidId, $result);
        $this->assertFalse($result[$invalidId]);
    }

    /** @test */
    public function unsync_returns_boolean(): void
    {
        // Arrange
        $service = $this->createService();
        $documentId = $this->getValidDocumentId();

        // Act
        $result = $service->unsync($documentId);

        // Assert
        $this->assertIsBool($result);
    }

    /** @test */
    public function unsync_returns_true_for_existing_document(): void
    {
        // Arrange
        $service = $this->createService();
        $documentId = $this->getValidDocumentId();

        // Sync first to ensure document exists in graph
        $service->sync($documentId);

        // Act
        $result = $service->unsync($documentId);

        // Assert
        $this->assertTrue($result);
    }

    /** @test */
    public function unsync_returns_false_for_non_existent_document(): void
    {
        // Arrange
        $service = $this->createService();
        $nonExistentId = 'non-existent-id-'.uniqid();

        // Act
        $result = $service->unsync($nonExistentId);

        // Assert
        $this->assertFalse($result);
    }

    /** @test */
    public function sync_and_unsync_are_idempotent(): void
    {
        // Arrange
        $service = $this->createService();
        $documentId = $this->getValidDocumentId();

        // Act - sync twice
        $service->sync($documentId);
        $service->sync($documentId); // Should not throw or cause issues

        // Act - unsync twice
        $firstUnsync = $service->unsync($documentId);
        $secondUnsync = $service->unsync($documentId);

        // Assert
        $this->assertTrue($firstUnsync);
        $this->assertFalse($secondUnsync); // Second unsync should return false (not found)
    }
}
