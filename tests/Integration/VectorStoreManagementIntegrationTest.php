<?php

namespace Tests\Integration;

use App\Models\CaseDocument;
use App\Models\CourtDecisionDocument;
use App\Models\Law;
use App\Models\LegalCase;
use App\Models\TextractDocument;
use App\Services\VectorStoreManagementService;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Integration tests for VectorStoreManagementService
 *
 * These tests verify vector store management works end-to-end,
 * including store configuration, statistics, and operations.
 */
class VectorStoreManagementIntegrationTest extends TestCase
{
    use UsesTestDatabase;

    protected VectorStoreManagementService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Use real service for integration testing
        $this->service = app(VectorStoreManagementService::class);
    }

    /** @test */
    public function it_lists_all_available_vector_stores()
    {
        // Act: Get all vector stores
        $stores = $this->service->getStores();

        // Assert: All expected stores are available
        $this->assertIsArray($stores);
        $this->assertArrayHasKey('laws', $stores);
        $this->assertArrayHasKey('court_decisions', $stores);
        $this->assertArrayHasKey('cases', $stores);
        $this->assertArrayHasKey('textract', $stores);

        // Verify store structure
        foreach ($stores as $key => $store) {
            $this->assertArrayHasKey('name', $store);
            $this->assertArrayHasKey('description', $store);
            $this->assertArrayHasKey('table', $store);
            $this->assertArrayHasKey('model', $store);
            $this->assertArrayHasKey('doc_id_column', $store);
            $this->assertArrayHasKey('content_column', $store);
        }
    }

    /** @test */
    public function it_retrieves_specific_store_configuration()
    {
        // Act: Get laws store configuration
        $lawsStore = $this->service->getStore('laws');

        // Assert: Store configuration is correct
        $this->assertIsArray($lawsStore);
        $this->assertEquals('Laws', $lawsStore['name']);
        $this->assertEquals('laws', $lawsStore['table']);
        $this->assertEquals(Law::class, $lawsStore['model']);

        // Test court decisions store
        $decisionsStore = $this->service->getStore('court_decisions');
        $this->assertIsArray($decisionsStore);
        $this->assertEquals('Court Decisions', $decisionsStore['name']);
        $this->assertEquals('court_decision_documents', $decisionsStore['table']);
    }

    /** @test */
    public function it_returns_null_for_nonexistent_store()
    {
        // Act: Try to get nonexistent store
        $store = $this->service->getStore('nonexistent_store');

        // Assert: Returns null
        $this->assertNull($store);
    }

    /** @test */
    public function it_gets_statistics_for_laws_store()
    {
        // Arrange: Create some law records
        Law::factory()->count(5)->create([
            'title' => 'Test Law',
            'content' => 'Law content for testing statistics',
        ]);

        // Act: Get statistics
        $stats = $this->service->getStatistics('laws');

        // Assert: Statistics are returned
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('store_key', $stats);
        $this->assertEquals('laws', $stats['store_key']);
        $this->assertArrayHasKey('total_documents', $stats);
        $this->assertGreaterThanOrEqual(5, $stats['total_documents']);
    }

    /** @test */
    public function it_gets_statistics_for_court_decisions_store()
    {
        // Arrange: Create court decision documents
        CourtDecisionDocument::factory()->count(3)->create([
            'content' => 'Court decision content',
            'title' => 'Test Decision',
        ]);

        // Act: Get statistics
        $stats = $this->service->getStatistics('court_decisions');

        // Assert: Statistics are returned
        $this->assertIsArray($stats);
        $this->assertEquals('court_decisions', $stats['store_key']);
        $this->assertArrayHasKey('total_documents', $stats);
        $this->assertGreaterThanOrEqual(3, $stats['total_documents']);
    }

    /** @test */
    public function it_gets_statistics_for_cases_store()
    {
        // Arrange: Create case documents
        $case = LegalCase::factory()->create();

        CaseDocument::factory()->count(7)->create([
            'case_id' => $case->id,
            'content' => 'Case document content',
            'title' => 'Test Case Document',
        ]);

        // Act: Get statistics
        $stats = $this->service->getStatistics('cases');

        // Assert: Statistics are returned
        $this->assertIsArray($stats);
        $this->assertEquals('cases', $stats['store_key']);
        $this->assertArrayHasKey('total_documents', $stats);
        $this->assertGreaterThanOrEqual(7, $stats['total_documents']);
    }

    /** @test */
    public function it_gets_statistics_for_textract_store()
    {
        // Arrange: Create textract documents (chunks with embeddings)
        TextractDocument::factory()->count(2)->create([
            'content' => 'Extracted PDF text content',
        ]);

        // Act: Get statistics
        $stats = $this->service->getStatistics('textract');

        // Assert: Statistics are returned
        $this->assertIsArray($stats);
        $this->assertEquals('textract', $stats['store_key']);
        $this->assertArrayHasKey('total_documents', $stats);
        $this->assertGreaterThanOrEqual(2, $stats['total_documents']);
    }

    /** @test */
    public function it_handles_empty_stores_gracefully()
    {
        // Act: Get statistics for empty store
        // (No data created in setUp, so store should be empty or minimal)
        $stats = $this->service->getStatistics('laws');

        // Assert: Returns valid statistics even for empty store
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('store_key', $stats);
        $this->assertArrayHasKey('total_documents', $stats);
        $this->assertGreaterThanOrEqual(0, $stats['total_documents']);
    }

    /** @test */
    public function it_gets_all_stores_statistics_at_once()
    {
        // Arrange: Create data in multiple stores
        Law::factory()->create(['title' => 'Test Law']);
        CourtDecisionDocument::factory()->create(['title' => 'Test Decision']);

        $case = LegalCase::factory()->create();
        CaseDocument::factory()->create([
            'case_id' => $case->id,
            'title' => 'Test Case Doc',
        ]);

        // Act: Get statistics for each store
        $allStats = [];
        foreach ($this->service->getStores() as $key => $store) {
            $allStats[$key] = $this->service->getStatistics($key);
        }

        // Assert: Statistics for all stores
        $this->assertCount(4, $allStats);
        $this->assertArrayHasKey('laws', $allStats);
        $this->assertArrayHasKey('court_decisions', $allStats);
        $this->assertArrayHasKey('cases', $allStats);
        $this->assertArrayHasKey('textract', $allStats);

        foreach ($allStats as $key => $stats) {
            $this->assertIsArray($stats);
            $this->assertEquals($key, $stats['store_key']);
            $this->assertArrayHasKey('total_documents', $stats);
        }
    }

    /** @test */
    public function it_verifies_store_table_names_match_models()
    {
        // Act: Get all stores
        $stores = $this->service->getStores();

        // Assert: Table names in store config MUST match the model's actual table
        foreach ($stores as $key => $store) {
            $model = app($store['model']);

            if (method_exists($model, 'getTable')) {
                $expectedTable = $model->getTable();

                $this->assertEquals(
                    $expectedTable,
                    $store['table'],
                    "Store '{$key}' has table '{$store['table']}' but its model uses '{$expectedTable}'"
                );
            }
        }
    }

    /** @test */
    public function it_provides_correct_model_classes_for_stores()
    {
        // Act: Get all stores
        $stores = $this->service->getStores();

        // Assert: All model classes exist and are valid
        $this->assertEquals(Law::class, $stores['laws']['model']);
        $this->assertEquals(CourtDecisionDocument::class, $stores['court_decisions']['model']);
        $this->assertEquals(CaseDocument::class, $stores['cases']['model']);
        $this->assertEquals(TextractDocument::class, $stores['textract']['model']);

        // Verify classes can be instantiated
        foreach ($stores as $key => $store) {
            $this->assertTrue(class_exists($store['model']));
        }
    }
}
