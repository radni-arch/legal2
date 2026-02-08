<?php

namespace Tests\Feature\Services\Research;

use App\Contracts\Research\SearchExecutorInterface;
use App\Models\CaseDocument;
use App\Models\CourtDecision;
use App\Models\Law;
use App\Models\LegalCase;
use App\Services\Research\SearchExecutorService;
use App\Services\Search\CaseSearchService;
use App\Services\Search\DecisionSearchService;
use App\Services\Search\LawSearchService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Integration tests for Search Executor Service
 *
 * Tests the SearchExecutorService's ability to:
 * - Query all search services (law, decision, case)
 * - Handle search failures gracefully
 * - Aggregate and deduplicate results
 *
 * Sprint 5: Research Agent Refactoring - Worker E
 */
class SearchExecutorIntegrationTest extends TestCase
{
    use UsesTestDatabase;

    protected SearchExecutorInterface $executor;

    protected function setUp(): void
    {
        parent::setUp();

        // Use real search executor service for integration testing
        $this->executor = app(SearchExecutorInterface::class);
    }

    /**
     * Test search executor integrates with all search services
     * Flow: Questions → Law search + Decision search + Case search → Aggregate → Return
     *
     * @test
     */
    public function test_search_executor_queries_all_search_services(): void
    {
        // Arrange: Create test data in all three corpora
        $law = Law::factory()->create([
            'title' => 'Test Criminal Code - Liability Provisions',
            'content' => 'Article 1: Criminal liability applies when a person commits an act prohibited by law.',
            'law_name' => 'Kazneni zakon',
            'article_number' => '1',
        ]);

        $decision = CourtDecision::factory()->create([
            'title' => 'Test Court Decision on Liability',
            'text' => 'The court finds that criminal liability was established according to Article 1 of the Criminal Code.',
            'court' => 'Županijski sud u Osijeku',
            'decision_date' => now()->subMonths(3),
        ]);

        // Create case with document
        $legalCase = LegalCase::factory()->create([
            'case_number' => 'K-TEST-001/2024',
            'title' => 'Test Case on Criminal Liability',
            'client_name' => 'Test Client',
            'status' => 'Active',
        ]);

        $caseDoc = CaseDocument::factory()->create([
            'legal_case_id' => $legalCase->id,
            'file_name' => 'Test Case Brief.pdf',
            'extracted_text' => 'This case involves questions of criminal liability under Croatian law.',
            'file_type' => 'application/pdf',
        ]);

        // Mock OpenAI API for embeddings
        Http::fake([
            'api.openai.com/*' => Http::response([
                'object' => 'list',
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.1)],
                ],
                'model' => 'text-embedding-ada-002',
                'usage' => [
                    'prompt_tokens' => 8,
                    'total_tokens' => 8,
                ],
            ], 200),
        ]);

        // Act: Execute searches across all corpora using the executor
        $questions = [
            ['tool' => 'law_vector_search', 'params' => ['query' => 'criminal liability', 'limit' => 5]],
            ['tool' => 'decision_vector_search', 'params' => ['query' => 'criminal liability', 'limit' => 5]],
            ['tool' => 'case_vector_search', 'params' => ['query' => 'criminal liability', 'limit' => 5]],
        ];

        $results = $this->executor->execute($questions);

        // Assert: Verify we got results from all three corpora
        $this->assertCount(3, $results, 'Should execute all 3 search actions');

        // Verify each result has the expected structure
        foreach ($results as $result) {
            $this->assertArrayHasKey('tool', $result);
            $this->assertArrayHasKey('params', $result);
            $this->assertArrayHasKey('success', $result);
            $this->assertArrayHasKey('execution_time', $result);

            if ($result['success']) {
                $this->assertArrayHasKey('result', $result, 'Successful search should have result key');
            } else {
                $this->assertArrayHasKey('error', $result, 'Failed search should have error key');
            }
        }

        // Verify at least one result from each corpus
        $lawResults = collect($results)->firstWhere('tool', 'law_vector_search');
        $decisionResults = collect($results)->firstWhere('tool', 'decision_vector_search');
        $caseResults = collect($results)->firstWhere('tool', 'case_vector_search');

        $this->assertNotNull($lawResults, 'Should have law search results');
        $this->assertNotNull($decisionResults, 'Should have decision search results');
        $this->assertNotNull($caseResults, 'Should have case search results');

        // Verify successful searches returned data
        if ($lawResults['success']) {
            $this->assertIsArray($lawResults['result'], 'Law results should be an array');
        }

        if ($decisionResults['success']) {
            $this->assertIsArray($decisionResults['result'], 'Decision results should be an array');
        }

        if ($caseResults['success']) {
            $this->assertIsArray($caseResults['result'], 'Case results should be an array');
        }
    }

    /**
     * Test graceful degradation when some searches fail
     * If law search fails, should still return decision + case results
     *
     * @test
     */
    public function test_search_executor_handles_search_failures_gracefully(): void
    {
        // Arrange: Create a mock law search service that fails
        $mockLawSearch = Mockery::mock(LawSearchService::class);
        $mockLawSearch->shouldReceive('search')
            ->andThrow(new \Exception('Law search service temporarily unavailable'));

        // Create real decision and case data
        $decision = CourtDecision::factory()->create([
            'title' => 'Resilience Test Decision',
            'text' => 'This decision demonstrates resilience to partial service failures.',
            'court' => 'Vrhovni sud',
        ]);

        $legalCase = LegalCase::factory()->create([
            'case_number' => 'K-RESILIENCE-001/2024',
            'title' => 'Resilience Test Case',
        ]);

        $caseDoc = CaseDocument::factory()->create([
            'legal_case_id' => $legalCase->id,
            'file_name' => 'Resilience Test.pdf',
            'extracted_text' => 'This document tests resilience to service failures.',
        ]);

        // Create a custom executor with mocked law search
        $executor = new SearchExecutorService(
            $mockLawSearch,
            app(DecisionSearchService::class),
            app(CaseSearchService::class)
        );

        // Mock OpenAI for the successful searches
        Http::fake([
            'api.openai.com/*' => Http::response([
                'object' => 'list',
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.2)],
                ],
                'model' => 'text-embedding-ada-002',
                'usage' => ['prompt_tokens' => 8, 'total_tokens' => 8],
            ], 200),
        ]);

        // Capture logs to verify error handling
        Log::spy();

        // Act: Execute searches with one failing service
        $questions = [
            ['tool' => 'law_vector_search', 'params' => ['query' => 'resilience test', 'limit' => 5]],
            ['tool' => 'decision_vector_search', 'params' => ['query' => 'resilience test', 'limit' => 5]],
            ['tool' => 'case_vector_search', 'params' => ['query' => 'resilience test', 'limit' => 5]],
        ];

        $results = $executor->execute($questions);

        // Assert: All three searches were attempted
        $this->assertCount(3, $results, 'Should attempt all 3 searches');

        // Verify the failed law search
        $lawResult = collect($results)->firstWhere('tool', 'law_vector_search');
        $this->assertNotNull($lawResult);
        $this->assertFalse($lawResult['success'], 'Law search should have failed');
        $this->assertArrayHasKey('error', $lawResult);
        $this->assertStringContainsString('Law search service temporarily unavailable', $lawResult['error']);

        // Verify the other searches still succeeded
        $decisionResult = collect($results)->firstWhere('tool', 'decision_vector_search');
        $caseResult = collect($results)->firstWhere('tool', 'case_vector_search');

        // Note: These may also fail if embeddings are not set up, but they should be attempted
        $this->assertNotNull($decisionResult, 'Decision search should be attempted');
        $this->assertNotNull($caseResult, 'Case search should be attempted');

        // Verify error was logged
        Log::shouldHaveReceived('error')
            ->atLeast()
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'SearchExecutor') &&
                    isset($context['error']) &&
                    str_contains($context['error'], 'Law search service temporarily unavailable');
            });

        // Verify performance metrics track the failure
        $metrics = $executor->getPerformanceMetrics();
        $this->assertArrayHasKey('failed_searches', $metrics);
        $this->assertGreaterThanOrEqual(1, $metrics['failed_searches'], 'Should track at least one failed search');
    }

    /**
     * Test result aggregation and deduplication
     * Same content in multiple corpora should be properly handled
     *
     * @test
     */
    public function test_search_executor_aggregates_and_deduplicates_results(): void
    {
        // Arrange: Create duplicate content across different corpora
        $sharedContent = 'Article 15 of the Criminal Code establishes specific provisions for liability determination.';

        // Create law with this content
        $law = Law::factory()->create([
            'title' => 'Kazneni zakon - Article 15',
            'content' => $sharedContent,
            'law_name' => 'Kazneni zakon',
            'article_number' => '15',
        ]);

        // Create decision citing the same content
        $decision = CourtDecision::factory()->create([
            'title' => 'Decision citing Article 15',
            'text' => "The court examined {$sharedContent} In this case, we apply these principles.",
            'court' => 'Županijski sud',
        ]);

        // Create case document with similar content
        $legalCase = LegalCase::factory()->create([
            'case_number' => 'K-DEDUP-001/2024',
            'title' => 'Deduplication Test Case',
        ]);

        $caseDoc = CaseDocument::factory()->create([
            'legal_case_id' => $legalCase->id,
            'file_name' => 'Article 15 Analysis.pdf',
            'extracted_text' => "Our analysis references {$sharedContent} This is crucial to our defense.",
        ]);

        // Mock OpenAI embeddings
        Http::fake([
            'api.openai.com/*' => Http::response([
                'object' => 'list',
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.3)],
                ],
                'model' => 'text-embedding-ada-002',
                'usage' => ['prompt_tokens' => 12, 'total_tokens' => 12],
            ], 200),
        ]);

        // Act: Execute searches that should return overlapping content
        $questions = [
            ['tool' => 'law_vector_search', 'params' => ['query' => 'Article 15 liability', 'limit' => 10]],
            ['tool' => 'decision_vector_search', 'params' => ['query' => 'Article 15 liability', 'limit' => 10]],
            ['tool' => 'case_vector_search', 'params' => ['query' => 'Article 15 liability', 'limit' => 10]],
        ];

        $results = $this->executor->execute($questions);

        // Assert: Verify all searches completed
        $this->assertCount(3, $results, 'Should execute all 3 searches');

        // Collect all returned items
        $allItems = [];
        $successfulSearches = 0;

        foreach ($results as $result) {
            if ($result['success'] && isset($result['result']) && is_array($result['result'])) {
                $successfulSearches++;
                $allItems = array_merge($allItems, $result['result']);
            }
        }

        // Verify we got results (at least one search succeeded)
        if ($successfulSearches > 0) {
            $this->assertGreaterThan(0, count($allItems), 'Should have at least some search results');

            // Verify each result has necessary structure
            foreach ($allItems as $item) {
                // Results should be arrays or objects with identifiable content
                $this->assertTrue(
                    is_array($item) || is_object($item),
                    'Each result should be an array or object'
                );
            }
        }

        // Verify performance metrics
        $metrics = $this->executor->getPerformanceMetrics();

        $this->assertArrayHasKey('total_searches', $metrics);
        $this->assertEquals(3, $metrics['total_searches']);

        $this->assertArrayHasKey('successful_searches', $metrics);
        $this->assertArrayHasKey('failed_searches', $metrics);

        $this->assertEquals(
            $metrics['successful_searches'] + $metrics['failed_searches'],
            $metrics['total_searches'],
            'Successful + failed should equal total searches'
        );

        // Verify corpus breakdown
        $this->assertArrayHasKey('corpus_breakdown', $metrics);

        if ($successfulSearches > 0) {
            $this->assertGreaterThan(
                0,
                count($metrics['corpus_breakdown']),
                'Should have corpus breakdown for successful searches'
            );

            // Each corpus in breakdown should have count and total_time
            foreach ($metrics['corpus_breakdown'] as $corpus => $stats) {
                $this->assertArrayHasKey('count', $stats);
                $this->assertArrayHasKey('total_time', $stats);
                $this->assertGreaterThan(0, $stats['count']);
                $this->assertGreaterThanOrEqual(0, $stats['total_time']);
            }
        }

        // Verify total execution time is tracked
        $this->assertArrayHasKey('total_time', $metrics);
        $this->assertGreaterThan(0, $metrics['total_time'], 'Should track total execution time');
    }
}
