<?php

namespace Tests\Integration;

use App\Models\CaseDocument;
use App\Models\CourtDecisionDocument;
use App\Models\Law;
use App\Services\CaseVectorStoreService;
use App\Services\CourtDecisionVectorStoreService;
use App\Services\LawVectorStoreService;
use App\Services\UnifiedSearchService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Integration Test: Search Pipeline End-to-End
 *
 * Tests the complete search workflow:
 * 1. Query embedding generation
 * 2. Vector similarity search across multiple corpora
 * 3. Result aggregation and ranking
 * 4. Filtering and deduplication
 * 5. Pagination
 */
class SearchPipelineFlowTest extends TestCase
{
    use UsesTestDatabase;

    protected UnifiedSearchService $searchService;

    protected LawVectorStoreService $lawVectorStore;

    protected CourtDecisionVectorStoreService $decisionVectorStore;

    protected CaseVectorStoreService $caseVectorStore;

    protected function setUp(): void
    {
        parent::setUp();

        // Fake OpenAI API responses for offline testing
        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response([
                'object' => 'list',
                'data' => [
                    [
                        'object' => 'embedding',
                        'embedding' => $this->generateMockEmbeddingVector(),
                        'index' => 0,
                    ],
                ],
                'model' => 'text-embedding-3-small',
                'usage' => [
                    'prompt_tokens' => 8,
                    'total_tokens' => 8,
                ],
            ], 200),
        ]);

        $this->searchService = app(UnifiedSearchService::class);
        $this->lawVectorStore = app(LawVectorStoreService::class);
        $this->decisionVectorStore = app(CourtDecisionVectorStoreService::class);
        $this->caseVectorStore = app(CaseVectorStoreService::class);
    }

    /**
     * Test 1: Complete search pipeline from query to results
     *
     * @test
     *
     * @group integration
     * @group search-pipeline
     */
    public function test_complete_search_pipeline_from_query_to_results()
    {
        // Arrange: Create test data with embeddings
        $law = Law::factory()->create([
            'title' => 'Zakon o kaznenom postupku - Pretraga',
            'content' => 'Pretraga doma i drugih prostorija može se izvršiti samo na temelju naredbe suda.',
            'law_number' => 'ZKP-240',
        ]);

        $decision = CourtDecisionDocument::factory()->create([
            'title' => 'Presuda - Nezakonita pretraga',
            'content' => 'Sud je utvrdio da je pretraga izvršena bez valjane naredbe.',
            'court' => 'Županijski sud u Osijeku',
        ]);

        $caseDoc = CaseDocument::factory()->create([
            'title' => 'Slučaj - Pretraga stana',
            'content' => 'Optuženi tvrdi da je pretraga bila nezakonita jer nije bilo naredbe suda.',
            'category' => 'evidence',
        ]);

        // Generate embeddings for test data
        $this->generateEmbeddings($law, $decision, $caseDoc);

        // Act: Perform search across all corpora
        $results = $this->searchService->search('pretraga doma', [
            'corpora' => ['laws', 'decisions', 'cases'],
            'limit' => 10,
            'threshold' => 0.5,
        ]);

        // Assert: Verify search results structure
        $this->assertIsArray($results);
        $this->assertArrayHasKey('success', $results);
        $this->assertArrayHasKey('data', $results);
        $this->assertArrayHasKey('metadata', $results);
        $this->assertTrue($results['success']);

        // Verify results contain data from all corpora
        $this->assertNotEmpty($results['data']);

        // Verify result structure
        $firstResult = $results['data'][0];
        $this->assertArrayHasKey('id', $firstResult);
        $this->assertArrayHasKey('type', $firstResult);
        $this->assertArrayHasKey('score', $firstResult);
        $this->assertArrayHasKey('snippet', $firstResult);

        // Verify metadata
        $this->assertArrayHasKey('total_results', $results['metadata']);
        $this->assertArrayHasKey('query', $results['metadata']);
        $this->assertArrayHasKey('corpora_searched', $results['metadata']);

        // Verify timing information (inside metadata.performance)
        $this->assertArrayHasKey('performance', $results['metadata']);
        $this->assertArrayHasKey('total_time', $results['metadata']['performance']);
    }

    /**
     * Test 2: Search with corpus filtering
     *
     * @test
     *
     * @group integration
     * @group search-pipeline
     */
    public function test_search_with_corpus_filtering()
    {
        // Create test data
        $law = Law::factory()->create([
            'title' => 'Zakon o drogama',
            'content' => 'Posjedovanje droga je kažnjivo.',
        ]);

        $decision = CourtDecisionDocument::factory()->create([
            'title' => 'Presuda - Droge',
            'content' => 'Optuženi je osuđen za posjedovanje droga.',
        ]);

        $this->generateEmbeddings($law, $decision);

        // Search only laws
        $lawResults = $this->searchService->search('droge', [
            'corpora' => ['laws'],
            'limit' => 5,
        ]);

        // Verify only laws in results
        foreach ($lawResults['data'] as $result) {
            $this->assertEquals('law', $result['type']);
        }

        // Search only decisions
        $decisionResults = $this->searchService->search('droge', [
            'corpora' => ['decisions'],
            'limit' => 5,
        ]);

        // Verify only decisions in results
        foreach ($decisionResults['data'] as $result) {
            $this->assertEquals('decision', $result['type']);
        }
    }

    /**
     * Test 3: Search with result ranking and scoring
     *
     * @test
     *
     * @group integration
     * @group search-pipeline
     */
    public function test_search_result_ranking_and_scoring()
    {
        // Create documents with varying relevance
        $highRelevance = Law::factory()->create([
            'title' => 'Zakon o pretresima',
            'content' => 'Detaljna procedura za pretragu doma i drugih prostorija.',
        ]);

        $mediumRelevance = Law::factory()->create([
            'title' => 'Kazneni zakon',
            'content' => 'Općenite odredbe o kaznenom postupku.',
        ]);

        $lowRelevance = Law::factory()->create([
            'title' => 'Zakon o policiji',
            'content' => 'Ovlaštenja policije u različitim situacijama.',
        ]);

        $this->generateEmbeddings($highRelevance, $mediumRelevance, $lowRelevance);

        // Perform search
        $results = $this->searchService->search('pretraga doma', [
            'corpora' => ['laws'],
            'limit' => 10,
            'threshold' => 0.3,
        ]);

        // Verify results are sorted by score (descending)
        $scores = array_column($results['data'], 'score');
        $sortedScores = $scores;
        rsort($sortedScores);

        $this->assertEquals($sortedScores, $scores, 'Results should be sorted by score descending');

        // Verify all scores are above threshold
        foreach ($scores as $score) {
            $this->assertGreaterThanOrEqual(0.3, $score);
        }
    }

    /**
     * Test 4: Search with pagination
     *
     * @test
     *
     * @group integration
     * @group search-pipeline
     */
    public function test_search_with_pagination()
    {
        // Create multiple documents
        for ($i = 1; $i <= 15; $i++) {
            Law::factory()->create([
                'title' => "Zakon broj $i",
                'content' => 'Sadržaj zakona o postupku '.$i,
            ]);
        }

        // Refresh embeddings for all laws
        Law::all()->each(function ($law) {
            $this->generateEmbeddings($law);
        });

        // Test page 1
        $page1 = $this->searchService->search('postupak', [
            'corpora' => ['laws'],
            'page' => 1,
            'per_page' => 5,
        ]);

        $this->assertCount(5, $page1['data']);

        // Test page 2
        $page2 = $this->searchService->search('postupak', [
            'corpora' => ['laws'],
            'page' => 2,
            'per_page' => 5,
        ]);

        $this->assertCount(5, $page2['data']);

        // Verify different results on different pages
        $page1Ids = array_column($page1['data'], 'id');
        $page2Ids = array_column($page2['data'], 'id');

        $this->assertEmpty(array_intersect($page1Ids, $page2Ids), 'Pages should have different results');
    }

    /**
     * Test 5: Search with threshold filtering
     *
     * @test
     *
     * @group integration
     * @group search-pipeline
     */
    public function test_search_with_threshold_filtering()
    {
        $law = Law::factory()->create([
            'title' => 'Test Law',
            'content' => 'Some legal content about procedures',
        ]);

        $this->generateEmbeddings($law);

        // High threshold - fewer results
        $highThreshold = $this->searchService->search('procedures', [
            'corpora' => ['laws'],
            'threshold' => 0.9,
        ]);

        // Low threshold - more results
        $lowThreshold = $this->searchService->search('procedures', [
            'corpora' => ['laws'],
            'threshold' => 0.3,
        ]);

        // Verify high threshold returns fewer or equal results
        $this->assertLessThanOrEqual(
            count($lowThreshold['data']),
            count($highThreshold['data'])
        );

        // Verify all results meet threshold
        foreach ($highThreshold['data'] as $result) {
            $this->assertGreaterThanOrEqual(0.9, $result['score']);
        }
    }

    /**
     * Test 6: Search handles empty query gracefully
     *
     * @test
     *
     * @group integration
     * @group search-pipeline
     */
    public function test_search_handles_empty_query()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->searchService->search('', [
            'corpora' => ['laws'],
        ]);
    }

    /**
     * Test 7: Search handles invalid corpus
     *
     * @test
     *
     * @group integration
     * @group search-pipeline
     */
    public function test_search_handles_invalid_corpus()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->searchService->search('test', [
            'corpora' => ['invalid_corpus'],
        ]);
    }

    /**
     * Test 8: Multi-corpus search aggregates results correctly
     *
     * @test
     *
     * @group integration
     * @group search-pipeline
     */
    public function test_multi_corpus_search_aggregates_correctly()
    {
        // Create one document per corpus
        $law = Law::factory()->create([
            'title' => 'Law about searches',
            'content' => 'Legal requirements for home searches',
        ]);

        $decision = CourtDecisionDocument::factory()->create([
            'title' => 'Decision about search',
            'content' => 'Court ruled on search validity',
        ]);

        $caseDoc = CaseDocument::factory()->create([
            'title' => 'Case involving search',
            'content' => 'Evidence from home search',
        ]);

        $this->generateEmbeddings($law, $decision, $caseDoc);

        // Search all corpora
        $results = $this->searchService->search('home search', [
            'corpora' => ['laws', 'decisions', 'cases'],
            'limit' => 10,
        ]);

        // Get unique types from results
        $typesInResults = array_unique(array_column($results['data'], 'type'));

        // Verify we got results from multiple corpora
        $this->assertGreaterThan(1, count($typesInResults));
    }

    /**
     * Helper: Generate embeddings for test data
     */
    protected function generateEmbeddings(...$models): void
    {
        foreach ($models as $model) {
            if ($model instanceof Law) {
                // Generate embedding vector (mock or real)
                $embedding = $this->mockEmbedding();
                DB::table('laws')
                    ->where('id', $model->id)
                    ->update(['embedding' => DB::raw("'[$embedding]'::vector")]);
            } elseif ($model instanceof CourtDecisionDocument) {
                $embedding = $this->mockEmbedding();
                DB::table('court_decision_documents')
                    ->where('id', $model->id)
                    ->update(['embedding' => DB::raw("'[$embedding]'::vector")]);
            } elseif ($model instanceof CaseDocument) {
                $embedding = $this->mockEmbedding();
                DB::table('cases_documents')
                    ->where('id', $model->id)
                    ->update(['embedding_vector' => DB::raw("'[$embedding]'::vector")]);
            }
        }
    }

    /**
     * Helper: Generate mock embedding vector
     * Uses consistent values so documents match with search queries
     */
    protected function mockEmbedding(): string
    {
        // Generate a 1536-dimensional vector with consistent values for testing
        // Using 0.5 as a base value with slight variations to avoid all zeros
        $vector = [];
        for ($i = 0; $i < 1536; $i++) {
            $vector[] = 0.5 + (($i % 10) * 0.01);  // Values range from 0.5 to 0.59
        }

        return implode(',', $vector);
    }

    /**
     * Helper: Generate mock embedding vector as array (for Http::fake)
     * Uses consistent values so search queries match with documents
     */
    protected function generateMockEmbeddingVector(): array
    {
        // Generate a 1536-dimensional vector with consistent values for testing
        // Using same pattern as mockEmbedding() for high similarity
        $vector = [];
        for ($i = 0; $i < 1536; $i++) {
            $vector[] = 0.5 + (($i % 10) * 0.01);  // Values range from 0.5 to 0.59
        }

        return $vector;
    }
}
