<?php

namespace Tests\Unit\Services\Search;

use App\Models\CourtDecision;
use App\Models\CourtDecisionDocument;
use App\Services\Search\DecisionSearchService;
use App\Services\Search\SearchEmbeddingService;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Test suite for DecisionSearchService
 *
 * This service is responsible for searching court decision documents.
 * It's extracted from UnifiedSearchService as part of Phase 2 refactoring.
 */
class DecisionSearchServiceTest extends TestCase
{
    use UsesTestDatabase;

    protected SearchEmbeddingService $embeddingServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock SearchEmbeddingService
        $this->embeddingServiceMock = Mockery::mock(SearchEmbeddingService::class);

        // Default: return a valid embedding
        $this->embeddingServiceMock->shouldReceive('embedQuery')
            ->andReturn(array_fill(0, 1536, 0.5));
    }

    protected function tearDown(): void
    {
        // Clear the table existence cache to ensure test isolation
        DecisionSearchService::clearTableExistsCache();
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test 1: It searches decisions by vector similarity
     *
     * @test
     */
    public function it_searches_decisions_by_vector_similarity()
    {
        // Arrange: Create a court decision with document
        $decision = CourtDecision::factory()->create([
            'title' => 'Test Decision',
            'court' => 'Županijski sud u Osijeku',
        ]);

        CourtDecisionDocument::factory()->create([
            'decision_id' => $decision->id,
            'content' => 'This is a test decision about criminal procedure',
            'embedding' => array_fill(0, 1536, 0.5),
        ]);

        // Act: Search for decisions
        $service = new DecisionSearchService($this->embeddingServiceMock);
        $results = $service->search('criminal procedure', [
            'threshold' => 0.0,
            'limit' => 10,
        ]);

        // Assert: Should return results
        $this->assertIsArray($results);
        $this->assertNotEmpty($results);
        $this->assertEquals('decision', $results[0]['type']);
        $this->assertArrayHasKey('score', $results[0]);
        $this->assertArrayHasKey('title', $results[0]);
    }

    /**
     * Test 2: It filters by court name
     *
     * @test
     */
    public function it_filters_by_court()
    {
        // Arrange: Create decisions from different courts
        $osijek = CourtDecision::factory()->create([
            'court' => 'Županijski sud u Osijeku',
        ]);

        $zagreb = CourtDecision::factory()->create([
            'court' => 'Vrhovni sud Republike Hrvatske',
        ]);

        CourtDecisionDocument::factory()->create([
            'decision_id' => $osijek->id,
            'content' => 'Test content',
            'embedding' => array_fill(0, 1536, 0.5),
        ]);

        CourtDecisionDocument::factory()->create([
            'decision_id' => $zagreb->id,
            'content' => 'Test content',
            'embedding' => array_fill(0, 1536, 0.5),
        ]);

        // Act: Filter by Osijek court
        $service = new DecisionSearchService($this->embeddingServiceMock);
        $results = $service->search('test', [
            'threshold' => 0.0,
            'filters' => ['court' => 'Osijek'],
        ]);

        // Assert: Should only return Osijek decisions
        $this->assertNotEmpty($results);
        foreach ($results as $result) {
            $this->assertStringContainsString('Osijek', $result['metadata']['court']);
        }
    }

    /**
     * Test 3: It filters by jurisdiction
     *
     * @test
     */
    public function it_filters_by_jurisdiction()
    {
        // Arrange: Create decisions with different jurisdictions
        $criminal = CourtDecision::factory()->create([
            'jurisdiction' => 'criminal',
        ]);

        $civil = CourtDecision::factory()->create([
            'jurisdiction' => 'civil',
        ]);

        CourtDecisionDocument::factory()->create([
            'decision_id' => $criminal->id,
            'content' => 'Test content',
            'embedding' => array_fill(0, 1536, 0.5),
        ]);

        CourtDecisionDocument::factory()->create([
            'decision_id' => $civil->id,
            'content' => 'Test content',
            'embedding' => array_fill(0, 1536, 0.5),
        ]);

        // Act: Filter by criminal jurisdiction
        $service = new DecisionSearchService($this->embeddingServiceMock);
        $results = $service->search('test', [
            'threshold' => 0.0,
            'filters' => ['jurisdiction' => 'criminal'],
        ]);

        // Assert: Should only return criminal decisions
        $this->assertNotEmpty($results);
        foreach ($results as $result) {
            $this->assertEquals('criminal', $result['metadata']['jurisdiction']);
        }
    }

    /**
     * Test 4: It filters by decision type
     *
     * @test
     */
    public function it_filters_by_decision_type()
    {
        // Arrange: Create decisions of different types
        $judgment = CourtDecision::factory()->create([
            'decision_type' => 'judgment',
        ]);

        $order = CourtDecision::factory()->create([
            'decision_type' => 'order',
        ]);

        CourtDecisionDocument::factory()->create([
            'decision_id' => $judgment->id,
            'content' => 'Test content',
            'embedding' => array_fill(0, 1536, 0.5),
        ]);

        CourtDecisionDocument::factory()->create([
            'decision_id' => $order->id,
            'content' => 'Test content',
            'embedding' => array_fill(0, 1536, 0.5),
        ]);

        // Act: Filter by judgment type
        $service = new DecisionSearchService($this->embeddingServiceMock);
        $results = $service->search('test', [
            'threshold' => 0.0,
            'filters' => ['decision_type' => 'judgment'],
        ]);

        // Assert: Should only return judgments
        $this->assertNotEmpty($results);
        foreach ($results as $result) {
            $this->assertEquals('judgment', $result['metadata']['decision_type']);
        }
    }

    /**
     * Test 5: It filters by date range
     *
     * @test
     */
    public function it_filters_by_date_range()
    {
        // Arrange: Create decisions with different dates
        $old = CourtDecision::factory()->create([
            'decision_date' => '2020-01-01',
        ]);

        $new = CourtDecision::factory()->create([
            'decision_date' => '2023-01-01',
        ]);

        CourtDecisionDocument::factory()->create([
            'decision_id' => $old->id,
            'content' => 'Test content',
            'embedding' => array_fill(0, 1536, 0.5),
        ]);

        CourtDecisionDocument::factory()->create([
            'decision_id' => $new->id,
            'content' => 'Test content',
            'embedding' => array_fill(0, 1536, 0.5),
        ]);

        // Act: Filter by date range
        $service = new DecisionSearchService($this->embeddingServiceMock);
        $results = $service->search('test', [
            'threshold' => 0.0,
            'filters' => [
                'date_from' => '2022-01-01',
                'date_to' => '2024-01-01',
            ],
        ]);

        // Assert: Should only return new decision
        $this->assertNotEmpty($results);
        foreach ($results as $result) {
            $date = $result['metadata']['decision_date'];
            $this->assertGreaterThanOrEqual('2022-01-01', $date);
            $this->assertLessThanOrEqual('2024-01-01', $date);
        }
    }

    /**
     * Test 6: It respects similarity threshold
     *
     * @test
     */
    public function it_respects_similarity_threshold()
    {
        // Arrange: Create decision with known embedding
        $decision = CourtDecision::factory()->create();

        CourtDecisionDocument::factory()->create([
            'decision_id' => $decision->id,
            'content' => 'Test content',
            'embedding' => array_fill(0, 1536, 0.5),
        ]);

        // Act: Search with high threshold
        $service = new DecisionSearchService($this->embeddingServiceMock);
        $results = $service->search('test', [
            'threshold' => 0.95,
        ]);

        // Assert: Results should meet threshold
        foreach ($results as $result) {
            $this->assertGreaterThanOrEqual(0.95, $result['score']);
        }
    }

    /**
     * Test 7: It respects result limit
     *
     * @test
     */
    public function it_respects_result_limit()
    {
        // Arrange: Create 10 decisions
        $decision = CourtDecision::factory()->create();

        for ($i = 0; $i < 10; $i++) {
            CourtDecisionDocument::factory()->create([
                'decision_id' => $decision->id,
                'content' => "Test content {$i}",
                'embedding' => array_fill(0, 1536, 0.5 + ($i * 0.01)),
            ]);
        }

        // Act: Search with limit of 5
        $service = new DecisionSearchService($this->embeddingServiceMock);
        $results = $service->search('test', [
            'threshold' => 0.0,
            'limit' => 5,
        ]);

        // Assert: Should return at most 5 results
        $this->assertLessThanOrEqual(5, count($results));
    }

    /**
     * Test 8: It includes all required metadata
     *
     * @test
     */
    public function it_includes_required_metadata()
    {
        // Arrange: Create decision with full metadata
        $decision = CourtDecision::factory()->create([
            'case_number' => 'K-123/2023',
            'title' => 'Test Decision',
            'court' => 'Županijski sud u Osijeku',
            'jurisdiction' => 'criminal',
            'decision_date' => '2023-01-15',
            'decision_type' => 'judgment',
            'ecli' => 'ECLI:HR:ŽSOJ:2023:K.123.2023',
        ]);

        CourtDecisionDocument::factory()->create([
            'decision_id' => $decision->id,
            'content' => 'Test content',
            'embedding' => array_fill(0, 1536, 0.5),
        ]);

        // Act: Search
        $service = new DecisionSearchService($this->embeddingServiceMock);
        $results = $service->search('test', ['threshold' => 0.0]);

        // Assert: Should include all metadata
        $this->assertNotEmpty($results);
        $metadata = $results[0]['metadata'];

        $this->assertArrayHasKey('decision_id', $metadata);
        $this->assertArrayHasKey('case_number', $metadata);
        $this->assertArrayHasKey('court', $metadata);
        $this->assertArrayHasKey('jurisdiction', $metadata);
        $this->assertArrayHasKey('decision_date', $metadata);
        $this->assertArrayHasKey('decision_type', $metadata);
        $this->assertArrayHasKey('ecli', $metadata);
        $this->assertArrayHasKey('content_hash', $metadata);
        $this->assertArrayHasKey('chunk_index', $metadata);
    }

    /**
     * Test 9: It generates snippets from content
     *
     * @test
     */
    public function it_generates_snippets()
    {
        // Arrange: Create decision with long content
        $decision = CourtDecision::factory()->create();
        $longContent = str_repeat('This is a very long decision text. ', 100);

        CourtDecisionDocument::factory()->create([
            'decision_id' => $decision->id,
            'content' => $longContent,
            'embedding' => array_fill(0, 1536, 0.5),
        ]);

        // Act: Search
        $service = new DecisionSearchService($this->embeddingServiceMock);
        $results = $service->search('test', ['threshold' => 0.0]);

        // Assert: Should have snippet
        $this->assertNotEmpty($results);
        $this->assertArrayHasKey('snippet', $results[0]);
        $this->assertLessThanOrEqual(203, mb_strlen($results[0]['snippet']));
    }

    /**
     * Test 10: It returns empty array for no matches
     *
     * @test
     */
    public function it_returns_empty_array_for_no_matches()
    {
        // Arrange: No decisions in database

        // Act: Search
        $service = new DecisionSearchService($this->embeddingServiceMock);
        $results = $service->search('nonexistent', ['threshold' => 0.9]);

        // Assert: Should return empty array
        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    /**
     * Test 11: It can be instantiated via container
     *
     * @test
     */
    public function it_can_be_instantiated_via_container()
    {
        // Act: Resolve from container
        $service = app(DecisionSearchService::class);

        // Assert: Should be instance of DecisionSearchService
        $this->assertInstanceOf(DecisionSearchService::class, $service);
    }

    /**
     * Test 12: It uses provided embedding model
     *
     * @test
     */
    public function it_uses_provided_embedding_model()
    {
        // Arrange: Mock embedding service with model expectation
        $embeddingService = Mockery::mock(SearchEmbeddingService::class);
        $embeddingService->shouldReceive('embedQuery')
            ->once()
            ->with('test query', 'text-embedding-3-large')
            ->andReturn(array_fill(0, 1536, 0.5));

        // Act: Search with custom model
        $service = new DecisionSearchService($embeddingService);
        $results = $service->search('test query', [
            'model' => 'text-embedding-3-large',
            'threshold' => 0.0,
        ]);

        // Assert: Should use the model (verified by Mockery expectation)
        $this->assertIsArray($results);
    }
}
