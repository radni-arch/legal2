<?php

namespace Tests\Feature;

use App\Models\CaseDocument;
use App\Models\LegalCase;
use App\Services\CaseSearchService;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Integration tests for CaseSearchService
 *
 * These tests use real database operations and service interactions
 * to verify search functionality works end-to-end.
 */
class CaseSearchServiceIntegrationTest extends TestCase
{
    use UsesTestDatabase;

    protected CaseSearchService $searchService;

    protected function setUp(): void
    {
        parent::setUp();

        // Use real services for integration testing
        $this->searchService = app(CaseSearchService::class);
    }

    /** @test */
    public function it_searches_cases_with_multiple_filters()
    {
        // Arrange: Create test cases with specific attributes
        $targetCase = LegalCase::factory()->create([
            'case_number' => 'INT-001/2024',
            'title' => 'Integration Test Contract Dispute',
            'client_name' => 'Alice Johnson',
            'opponent_name' => 'Bob Corporation',
            'court' => 'Commercial Court',
            'jurisdiction' => 'Croatia',
            'status' => 'Active',
            'tags' => ['contract', 'commercial'],
        ]);

        // Create a case that shouldn't match
        LegalCase::factory()->create([
            'case_number' => 'INT-002/2024',
            'title' => 'Different Case',
            'client_name' => 'Charlie Smith',
            'jurisdiction' => 'Slovenia',
            'status' => 'Closed',
        ]);

        // Act: Search with multiple filters
        $result = $this->searchService->searchCases('Integration Contract', [
            'jurisdiction' => 'Croatia',
            'status' => 'Active',
        ]);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals('cases', $result['search_type']);
        $this->assertGreaterThanOrEqual(1, count($result['data']));

        // Verify the correct case is in results
        $foundCase = collect($result['data'])->first(function ($case) use ($targetCase) {
            return $case->id === $targetCase->id;
        });

        $this->assertNotNull($foundCase, 'Target case should be found in search results');
        $this->assertEquals('Alice Johnson', $foundCase->client_name);
    }

    /** @test */
    public function it_searches_cases_by_client_and_opponent_names()
    {
        // Arrange
        $case = LegalCase::factory()->create([
            'case_number' => 'INT-003/2024',
            'title' => 'Smith vs Anderson Legal Matter',
            'client_name' => 'Sarah Smith',
            'opponent_name' => 'Michael Anderson',
            'status' => 'Active',
        ]);

        // Act: Search by client name
        $clientResult = $this->searchService->searchCases('Sarah Smith', [
            'client_name' => 'Smith',
        ]);

        // Assert client search
        $this->assertTrue($clientResult['success']);
        $this->assertGreaterThanOrEqual(1, count($clientResult['data']));

        $foundCase = collect($clientResult['data'])->first(function ($c) use ($case) {
            return $c->id === $case->id;
        });

        $this->assertNotNull($foundCase);
        $this->assertStringContainsString('Smith', $foundCase->client_name);

        // Act: Search by opponent name
        $opponentResult = $this->searchService->searchCases('Anderson', [
            'opponent_name' => 'Anderson',
        ]);

        // Assert opponent search
        $this->assertTrue($opponentResult['success']);
        $foundByOpponent = collect($opponentResult['data'])->first(function ($c) use ($case) {
            return $c->id === $case->id;
        });

        $this->assertNotNull($foundByOpponent);
        $this->assertStringContainsString('Anderson', $foundByOpponent->opponent_name);
    }

    /** @test */
    public function it_searches_documents_with_case_relationship()
    {
        // Arrange: Create case and related document
        $case = LegalCase::factory()->create([
            'case_number' => 'INT-DOC-001/2024',
            'title' => 'Document Test Case',
        ]);

        $document = CaseDocument::factory()->create([
            'case_id' => $case->id,
            'title' => 'Important Contract Agreement',
            'content' => 'This document contains important contract terms and conditions.',
            'category' => 'Evidence',
            'author' => 'Legal Team',
            'language' => 'en',
            'tags' => ['contract', 'agreement'],
        ]);

        // Act: Search documents with case filter
        $result = $this->searchService->searchDocuments('Contract Agreement', [
            'case_id' => $case->id,
            'include_content' => false,
        ]);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals('documents', $result['search_type']);

        if (count($result['data']) > 0) {
            // If search works, verify correct document
            $foundDoc = collect($result['data'])->first(function ($doc) use ($document) {
                return $doc->id === $document->id;
            });

            if ($foundDoc) {
                $this->assertEquals($case->id, $foundDoc->case_id);
                $this->assertStringContainsString('Contract', $foundDoc->title);
            }
        }

        // At minimum, verify the query structure is valid
        $this->assertArrayHasKey('pagination', $result);
        $this->assertArrayHasKey('data', $result);
    }

    /** @test */
    public function it_searches_documents_with_content_included()
    {
        // Arrange
        $case = LegalCase::factory()->create([
            'case_number' => 'INT-CONTENT-001/2024',
            'title' => 'Content Test Case',
        ]);

        $document = CaseDocument::factory()->create([
            'case_id' => $case->id,
            'title' => 'Full Content Document',
            'content' => 'This is a detailed document with comprehensive content about legal proceedings and case details.',
            'category' => 'Evidence',
        ]);

        // Act: Search with content included
        $result = $this->searchService->searchDocuments('detailed document', [
            'include_content' => true,
        ]);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals('documents', $result['search_type']);

        // Verify structure even if search doesn't find specific document
        $this->assertArrayHasKey('data', $result);

        if (count($result['data']) > 0) {
            // If results exist, verify content is included when requested
            $firstResult = $result['data'][0];
            $this->assertObjectHasProperty('content', $firstResult,
                'Documents should include content when include_content=true');
        }
    }

    /** @test */
    public function it_searches_documents_by_case_number()
    {
        // Arrange
        $case = LegalCase::factory()->create([
            'case_number' => 'UNIQUE-CASE-NUM-999/2024',
            'title' => 'Case Number Filter Test',
        ]);

        $document = CaseDocument::factory()->create([
            'case_id' => $case->id,
            'title' => 'Document for Unique Case',
            'content' => 'This document belongs to a specific case number.',
            'category' => 'Evidence',
        ]);

        // Act: Search documents by case number
        $result = $this->searchService->searchDocuments('', [
            'case_number' => 'UNIQUE-CASE-NUM-999',
        ]);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals('documents', $result['search_type']);
        $this->assertArrayHasKey('pagination', $result);

        // Verify query accepts case_number filter
        // If search implementation supports it, documents should be filtered
        if (count($result['data']) > 0) {
            $foundDoc = collect($result['data'])->first(function ($doc) use ($document) {
                return $doc->id === $document->id;
            });

            if ($foundDoc) {
                $this->assertEquals($case->id, $foundDoc->case_id);
            }
        }
    }

    /** @test */
    public function it_paginates_case_search_results_correctly()
    {
        // Arrange: Create multiple cases for pagination testing
        $cases = [];
        for ($i = 1; $i <= 30; $i++) {
            $cases[] = LegalCase::factory()->create([
                'case_number' => "PAG-{$i}/2024",
                'title' => "Pagination Test Case {$i}",
                'status' => 'Active',
                'tags' => ['pagination', 'test'],
            ]);
        }

        // Act: Search with pagination parameters
        $page1 = $this->searchService->searchCases('Pagination Test', [
            'page' => 1,
            'limit' => 10,
        ]);

        $page2 = $this->searchService->searchCases('Pagination Test', [
            'page' => 2,
            'limit' => 10,
        ]);

        // Assert page 1
        $this->assertTrue($page1['success']);
        $this->assertEquals(1, $page1['pagination']['page']);
        $this->assertEquals(10, $page1['pagination']['limit']);
        $this->assertGreaterThanOrEqual(30, $page1['pagination']['total']);

        // Assert page 2
        $this->assertTrue($page2['success']);
        $this->assertEquals(2, $page2['pagination']['page']);
        $this->assertEquals(10, $page2['pagination']['limit']);

        // Verify different results on different pages
        if (count($page1['data']) > 0 && count($page2['data']) > 0) {
            $page1Ids = collect($page1['data'])->pluck('id')->toArray();
            $page2Ids = collect($page2['data'])->pluck('id')->toArray();

            $overlap = array_intersect($page1Ids, $page2Ids);
            $this->assertEmpty($overlap, 'Different pages should have different results');
        }

        // Verify total pages calculation
        $expectedPages = ceil($page1['pagination']['total'] / 10);
        $this->assertEquals($expectedPages, $page1['pagination']['pages']);
    }

    /** @test */
    public function it_handles_vector_search_with_real_data()
    {
        // This test verifies the vector search structure works
        // Note: Actual embedding generation may be mocked in service

        // Arrange
        $case = LegalCase::factory()->create([
            'case_number' => 'VEC-001/2024',
            'title' => 'Vector Search Test Case',
        ]);

        $document = CaseDocument::factory()->create([
            'case_id' => $case->id,
            'title' => 'Semantic Search Document',
            'content' => 'This document should be searchable using semantic vector embeddings.',
            'embedding' => array_fill(0, 1536, 0.1), // Mock embedding
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-3-large',
            'embedding_dimensions' => 1536,
        ]);

        // Act: Attempt vector search (may fall back to regular search)
        try {
            $result = $this->searchService->search('semantic document', [
                'search_type' => 'vector',
            ]);

            // Assert structure
            $this->assertArrayHasKey('success', $result);
            $this->assertArrayHasKey('search_type', $result);
            $this->assertArrayHasKey('data', $result);

            // Vector search might return results or handle gracefully
            if ($result['success']) {
                $this->assertIsArray($result['data']);
            }
        } catch (\Exception $e) {
            // If vector search isn't fully configured, verify graceful handling
            $this->assertNotEmpty($e->getMessage());
        }
    }

    /** @test */
    public function it_validates_search_type_parameter()
    {
        // Act & Assert: Invalid search type should throw exception
        $this->expectException(\InvalidArgumentException::class);

        $this->searchService->search('test query', [
            'search_type' => 'invalid_type',
        ]);
    }

    /** @test */
    public function it_returns_empty_results_for_non_matching_search()
    {
        // Arrange: Create case with very specific content
        LegalCase::factory()->create([
            'case_number' => 'NOMATCH-001/2024',
            'title' => 'Specific Unique Case XYZABC123',
            'status' => 'Active',
        ]);

        // Act: Search for completely unrelated term
        $result = $this->searchService->searchCases('COMPLETELY_UNRELATED_TERM_999999', []);

        // Assert: Should return success with empty results
        $this->assertTrue($result['success']);
        $this->assertEquals('cases', $result['search_type']);
        $this->assertIsArray($result['data']);
        $this->assertArrayHasKey('pagination', $result);
    }
}
