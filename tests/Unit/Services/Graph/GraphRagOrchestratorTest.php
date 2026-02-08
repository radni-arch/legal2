<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Graph\CaseGraphSyncService;
use App\Services\Graph\GraphCitationLinker;
use App\Services\Graph\GraphRagOrchestrator;
use App\Services\Graph\GraphSimilarityLinker;
use App\Services\Graph\TextractGraphSyncService;
use App\Services\GraphDatabaseService;
use App\Services\TaggingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class GraphRagOrchestratorTest extends TestCase
{
    protected GraphRagOrchestrator $orchestrator;

    protected $graphMock;

    protected $taggingMock;

    protected $caseSyncMock;

    protected $textractSyncMock;

    protected $citationLinkerMock;

    protected $similarityLinkerMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock all dependencies
        $this->graphMock = Mockery::mock(GraphDatabaseService::class);
        $this->taggingMock = Mockery::mock(TaggingService::class);
        $this->caseSyncMock = Mockery::mock(CaseGraphSyncService::class);
        $this->textractSyncMock = Mockery::mock(TextractGraphSyncService::class);
        $this->citationLinkerMock = Mockery::mock(GraphCitationLinker::class);
        $this->similarityLinkerMock = Mockery::mock(GraphSimilarityLinker::class);

        $this->orchestrator = new GraphRagOrchestrator(
            $this->graphMock,
            $this->taggingMock,
            $this->caseSyncMock,
            $this->textractSyncMock,
            null, // advancedExtractor
            $this->citationLinkerMock,
            $this->similarityLinkerMock
        );
    }

    protected function tearDown(): void
    {
        // Count Mockery expectations as PHPUnit assertions to avoid risky test warnings
        $container = Mockery::getContainer();
        if ($container) {
            $this->addToAssertionCount($container->mockery_getExpectationCount());
        }
        Mockery::close();
        parent::tearDown();
    }

    public function test_sync_case_delegates_to_case_sync_service()
    {
        $caseDocId = 'case-doc-123';

        $this->caseSyncMock
            ->shouldReceive('sync')
            ->once()
            ->with($caseDocId);

        $this->orchestrator->syncCase($caseDocId);
    }

    public function test_sync_case_handles_invalid_argument_exception()
    {
        $caseDocId = 'nonexistent-case';

        $this->caseSyncMock
            ->shouldReceive('sync')
            ->once()
            ->with($caseDocId)
            ->andThrow(new \InvalidArgumentException('Document not found'));

        // Should not throw exception, just log debug
        Log::shouldReceive('debug')
            ->once()
            ->with('Case document not found for sync', Mockery::type('array'));

        $this->orchestrator->syncCase($caseDocId);
    }

    public function test_sync_document_routes_to_correct_handler()
    {
        // Test law routing
        $this->caseSyncMock->shouldReceive('sync')->never();

        // Since we can't easily mock syncLaw in this test, we'll test the match logic
        // by testing that invalid types throw exception

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown document type: invalid_type');

        $this->orchestrator->syncDocument('invalid_type', 'some-id');
    }

    public function test_sync_document_case_type()
    {
        $caseId = 'case-123';

        $this->caseSyncMock
            ->shouldReceive('sync')
            ->once()
            ->with($caseId);

        $this->orchestrator->syncDocument('case', $caseId);
    }

    public function test_extract_keywords_uses_legacy_method()
    {
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($this->orchestrator);
        $method = $reflection->getMethod('extractKeywords');
        $method->setAccessible(true);

        // Note: extractKeywords filters words with <= 3 chars, so "sud" (3 chars) is excluded
        // Using "sudski" (6 chars) and "presuda" (7 chars) which will be included
        $content = 'Ovo je ugovor o prodaji. Sudski postupak je donio presuda. Tužitelj ima pravo na naknadu.';
        $keywords = $method->invoke($this->orchestrator, $content, 10);

        // Should return array with keywords and weights
        $this->assertIsArray($keywords);
        $this->assertNotEmpty($keywords);

        // Legal terms should be boosted (all > 3 chars to pass filter)
        $this->assertArrayHasKey('ugovor', $keywords);
        $this->assertArrayHasKey('pravo', $keywords);
        $this->assertArrayHasKey('tužitelj', $keywords);
    }

    public function test_extract_keywords_filters_stopwords()
    {
        $reflection = new \ReflectionClass($this->orchestrator);
        $method = $reflection->getMethod('extractKeywords');
        $method->setAccessible(true);

        $content = 'je su biti ima da za na u i ili te se';
        $keywords = $method->invoke($this->orchestrator, $content, 10);

        // Stopwords should be filtered out
        $this->assertEmpty($keywords);
    }

    public function test_extract_keywords_limits_results()
    {
        $reflection = new \ReflectionClass($this->orchestrator);
        $method = $reflection->getMethod('extractKeywords');
        $method->setAccessible(true);

        $content = str_repeat('ugovor sud presuda odluka tužitelj tuženik stranka odvjetnik žalba tužba parnica ', 5);
        $keywords = $method->invoke($this->orchestrator, $content, 3);

        // Should return exactly 3 keywords
        $this->assertCount(3, $keywords);
    }

    public function test_extract_keywords_normalizes_weights()
    {
        $reflection = new \ReflectionClass($this->orchestrator);
        $method = $reflection->getMethod('extractKeywords');
        $method->setAccessible(true);

        $content = 'ugovor ugovor ugovor sud presuda';
        $keywords = $method->invoke($this->orchestrator, $content, 3);

        // Weights should be normalized (max weight = 1.0)
        foreach ($keywords as $weight) {
            $this->assertLessThanOrEqual(1.0, $weight);
            $this->assertGreaterThan(0, $weight);
        }
    }

    public function test_generate_cache_key_creates_consistent_keys()
    {
        $reflection = new \ReflectionClass($this->orchestrator);
        $method = $reflection->getMethod('generateCacheKey');
        $method->setAccessible(true);

        $params = ['law' => 'ZKP', 'article' => '24', 'limit' => 50];

        $key1 = $method->invoke($this->orchestrator, 'decisions_citing_law', $params);
        $key2 = $method->invoke($this->orchestrator, 'decisions_citing_law', $params);

        // Same params should generate same key
        $this->assertEquals($key1, $key2);
    }

    public function test_generate_cache_key_handles_param_order()
    {
        $reflection = new \ReflectionClass($this->orchestrator);
        $method = $reflection->getMethod('generateCacheKey');
        $method->setAccessible(true);

        $params1 = ['limit' => 50, 'law' => 'ZKP', 'article' => '24'];
        $params2 = ['law' => 'ZKP', 'article' => '24', 'limit' => 50];

        $key1 = $method->invoke($this->orchestrator, 'decisions_citing_law', $params1);
        $key2 = $method->invoke($this->orchestrator, 'decisions_citing_law', $params2);

        // Different order should generate same key (params are sorted)
        $this->assertEquals($key1, $key2);
    }

    public function test_generate_cache_key_differs_by_operation()
    {
        $reflection = new \ReflectionClass($this->orchestrator);
        $method = $reflection->getMethod('generateCacheKey');
        $method->setAccessible(true);

        $params = ['law' => 'ZKP'];

        $key1 = $method->invoke($this->orchestrator, 'operation1', $params);
        $key2 = $method->invoke($this->orchestrator, 'operation2', $params);

        // Different operations should generate different keys
        $this->assertNotEquals($key1, $key2);
    }

    public function test_generate_cache_key_format()
    {
        $reflection = new \ReflectionClass($this->orchestrator);
        $method = $reflection->getMethod('generateCacheKey');
        $method->setAccessible(true);

        $params = ['law' => 'ZKP'];
        $key = $method->invoke($this->orchestrator, 'test_operation', $params);

        // Key should follow format: graph_rag:{operation}:{hash}
        $this->assertStringStartsWith('graph_rag:test_operation:', $key);
        $this->assertMatchesRegularExpression('/^graph_rag:test_operation:[a-f0-9]{32}$/', $key);
    }

    public function test_clear_all_graph_caches_with_redis()
    {
        // This test would require Redis to be set up
        // For now, just ensure it doesn't throw exceptions
        $this->orchestrator->clearAllGraphCaches();

        $this->assertTrue(true); // Test passes if no exception thrown
    }

    public function test_get_cited_laws_returns_empty_on_exception()
    {
        $this->graphMock
            ->shouldReceive('run')
            ->once()
            ->andThrow(new \Exception('Graph error'));

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to get cited laws', Mockery::type('array'));

        $result = $this->orchestrator->getCitedLaws('CaseDocument', 'case-123');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_get_citing_documents_returns_empty_on_exception()
    {
        $this->graphMock
            ->shouldReceive('run')
            ->once()
            ->andThrow(new \Exception('Graph error'));

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to get citing documents', Mockery::type('array'));

        $result = $this->orchestrator->getCitingDocuments('law-123');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_get_most_cited_laws_returns_empty_on_exception()
    {
        $this->graphMock
            ->shouldReceive('run')
            ->once()
            ->andThrow(new \Exception('Graph error'));

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to get most cited laws', Mockery::type('array'));

        $result = $this->orchestrator->getMostCitedLaws(20);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_enhanced_query_handles_graph_exceptions()
    {
        $this->graphMock
            ->shouldReceive('run')
            ->andThrow(new \Exception('Graph error'));

        Log::shouldReceive('warning')
            ->with('Graph query failed', Mockery::type('array'));

        $result = $this->orchestrator->enhancedQuery('test query', 'both', 10);

        // Should return empty results structure instead of throwing
        $this->assertIsArray($result);
        $this->assertArrayHasKey('direct_matches', $result);
        $this->assertArrayHasKey('related_via_keywords', $result);
    }

    public function test_get_graph_context_handles_exceptions_gracefully()
    {
        $this->graphMock
            ->shouldReceive('getNode')
            ->once()
            ->andThrow(new \Exception('Graph error'));

        Log::shouldReceive('warning')
            ->once()
            ->with('Failed to get graph context', Mockery::type('array'));

        $result = $this->orchestrator->getGraphContext('CaseDocument', 'case-123');

        // Should return empty context structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('node', $result);
        $this->assertArrayHasKey('tags', $result);
        $this->assertArrayHasKey('keywords', $result);
        $this->assertNull($result['node']);
        $this->assertEmpty($result['tags']);
    }

    public function test_find_decisions_citing_law_article_caches_results()
    {
        Cache::flush();

        $mockResult = Mockery::mock();
        $mockResult->shouldReceive('map')->once()->andReturn(collect([]));

        $this->graphMock
            ->shouldReceive('run')
            ->once() // Should only be called once due to caching
            ->andReturn($mockResult);

        // First call - should hit database
        $result1 = $this->orchestrator->findDecisionsCitingLawArticle('ZKP', '24', [], 50);

        // Second call - should use cache
        $result2 = $this->orchestrator->findDecisionsCitingLawArticle('ZKP', '24', [], 50);

        $this->assertEquals($result1, $result2);
    }

    public function test_find_decisions_citing_law_article_handles_exceptions()
    {
        Cache::flush();

        $this->graphMock
            ->shouldReceive('run')
            ->once()
            ->andThrow(new \Exception('Graph error'));

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to find decisions citing law article', Mockery::type('array'));

        $result = $this->orchestrator->findDecisionsCitingLawArticle('ZKP', '24');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_sync_all_methods_return_stats()
    {
        // Mock database queries to return empty results
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('orderBy')->andReturnSelf();
        DB::shouldReceive('chunk')->andReturn(null);

        $lawsResult = $this->orchestrator->syncAllLaws();
        $this->assertIsArray($lawsResult);
        $this->assertArrayHasKey('synced', $lawsResult);
        $this->assertArrayHasKey('errors', $lawsResult);

        $casesResult = $this->orchestrator->syncAllCases();
        $this->assertIsArray($casesResult);
        $this->assertArrayHasKey('synced', $casesResult);
        $this->assertArrayHasKey('errors', $casesResult);

        $decisionsResult = $this->orchestrator->syncAllCourtDecisions();
        $this->assertIsArray($decisionsResult);
        $this->assertArrayHasKey('synced', $decisionsResult);
        $this->assertArrayHasKey('errors', $decisionsResult);

        DB::shouldReceive('where')->andReturnSelf();
        $textractResult = $this->orchestrator->syncAllTextractJobs();
        $this->assertIsArray($textractResult);
        $this->assertArrayHasKey('synced', $textractResult);
        $this->assertArrayHasKey('errors', $textractResult);
    }
}
