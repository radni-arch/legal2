<?php

namespace Tests\Unit\Services\Research;

use App\Services\Research\SearchExecutorService;
use App\Services\Search\CaseSearchService;
use App\Services\Search\DecisionSearchService;
use App\Services\Search\LawSearchService;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for SearchExecutorService
 *
 * Comprehensive test coverage for search execution functionality.
 */
class SearchExecutorServiceTest extends TestCase
{
    protected SearchExecutorService $service;

    protected LawSearchService $lawSearchMock;

    protected DecisionSearchService $decisionSearchMock;

    protected CaseSearchService $caseSearchMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks
        $this->lawSearchMock = Mockery::mock(LawSearchService::class);
        $this->decisionSearchMock = Mockery::mock(DecisionSearchService::class);
        $this->caseSearchMock = Mockery::mock(CaseSearchService::class);

        // Create service with mocks
        $this->service = new SearchExecutorService(
            $this->lawSearchMock,
            $this->decisionSearchMock,
            $this->caseSearchMock
        );

        // Suppress logs in tests
        Log::shouldReceive('withContext')->andReturn(null);
        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('debug')->andReturn(null);
        Log::shouldReceive('error')->andReturn(null);
        Log::shouldReceive('warning')->andReturn(null);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function test_execute_basic(): void
    {
        $this->lawSearchMock
            ->shouldReceive('search')
            ->once()
            ->with('proportionality test', Mockery::type('array'))
            ->andReturn([
                ['id' => 'law-1', 'score' => 0.9, 'content' => 'Law content'],
            ]);

        $actions = [
            ['tool' => 'law_vector_search', 'params' => ['query' => 'proportionality test']],
        ];

        $results = $this->service->execute($actions);

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]['success']);
        $this->assertEquals('law_vector_search', $results[0]['tool']);
        $this->assertCount(1, $results[0]['result']);
    }

    /** @test */
    public function test_execute_searches_all_corpora(): void
    {
        $this->lawSearchMock
            ->shouldReceive('search')
            ->once()
            ->andReturn([['id' => 'law-1', 'score' => 0.9]]);

        $this->decisionSearchMock
            ->shouldReceive('search')
            ->once()
            ->andReturn([['id' => 'decision-1', 'score' => 0.85]]);

        $this->caseSearchMock
            ->shouldReceive('search')
            ->once()
            ->andReturn([['id' => 'case-1', 'score' => 0.8]]);

        $actions = [
            ['tool' => 'law_vector_search', 'params' => ['query' => 'test']],
            ['tool' => 'decision_vector_search', 'params' => ['query' => 'test']],
            ['tool' => 'case_vector_search', 'params' => ['query' => 'test']],
        ];

        $results = $this->service->execute($actions);

        $this->assertCount(3, $results);
        $this->assertTrue($results[0]['success']);
        $this->assertTrue($results[1]['success']);
        $this->assertTrue($results[2]['success']);
    }

    /** @test */
    public function test_execute_aggregates_results(): void
    {
        $this->lawSearchMock
            ->shouldReceive('search')
            ->once()
            ->andReturn([
                ['id' => 'law-1', 'score' => 0.9, 'content' => 'Content 1'],
                ['id' => 'law-2', 'score' => 0.8, 'content' => 'Content 2'],
            ]);

        $actions = [
            ['tool' => 'law_vector_search', 'params' => ['query' => 'test']],
        ];

        $results = $this->service->execute($actions);

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]['success']);
        $this->assertCount(2, $results[0]['result']);
    }

    /** @test */
    public function test_search_laws_corpus(): void
    {
        $this->lawSearchMock
            ->shouldReceive('search')
            ->once()
            ->with('proportionality', Mockery::type('array'))
            ->andReturn([
                ['id' => 'law-1', 'score' => 0.9],
            ]);

        $results = $this->service->searchCorpus('laws', 'proportionality');

        $this->assertIsArray($results);
        $this->assertCount(1, $results);
    }

    /** @test */
    public function test_search_decisions_corpus(): void
    {
        $this->decisionSearchMock
            ->shouldReceive('search')
            ->once()
            ->with('home search', Mockery::type('array'))
            ->andReturn([
                ['id' => 'decision-1', 'score' => 0.85],
            ]);

        $results = $this->service->searchCorpus('decisions', 'home search');

        $this->assertIsArray($results);
        $this->assertCount(1, $results);
    }

    /** @test */
    public function test_search_cases_corpus(): void
    {
        $this->caseSearchMock
            ->shouldReceive('search')
            ->once()
            ->with('evidence', Mockery::type('array'))
            ->andReturn([
                ['id' => 'case-1', 'score' => 0.8],
            ]);

        $results = $this->service->searchCorpus('cases', 'evidence');

        $this->assertIsArray($results);
        $this->assertCount(1, $results);
    }

    /** @test */
    public function test_search_handles_empty_results(): void
    {
        $this->lawSearchMock
            ->shouldReceive('search')
            ->once()
            ->andReturn([]);

        $results = $this->service->searchCorpus('laws', 'nonexistent query');

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    /** @test */
    public function test_search_handles_errors(): void
    {
        $this->lawSearchMock
            ->shouldReceive('search')
            ->once()
            ->andThrow(new \Exception('Database connection failed'));

        $results = $this->service->searchCorpus('laws', 'test');

        $this->assertIsArray($results);
        $this->assertArrayHasKey('error', $results);
        $this->assertStringContainsString('Database connection failed', $results['error']);
    }

    /** @test */
    public function test_execute_handles_errors(): void
    {
        $this->lawSearchMock
            ->shouldReceive('search')
            ->once()
            ->andThrow(new \Exception('Search failed'));

        $actions = [
            ['tool' => 'law_vector_search', 'params' => ['query' => 'test']],
        ];

        $results = $this->service->execute($actions);

        $this->assertCount(1, $results);
        $this->assertFalse($results[0]['success']);
        $this->assertArrayHasKey('error', $results[0]);
    }

    /** @test */
    public function test_execute_action_law_vector_search(): void
    {
        $this->lawSearchMock
            ->shouldReceive('search')
            ->once()
            ->with('test query', ['query' => 'test query', 'limit' => 5])
            ->andReturn([['id' => 'law-1', 'score' => 0.9]]);

        $result = $this->service->executeAction('law_vector_search', [
            'query' => 'test query',
            'limit' => 5,
        ]);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    /** @test */
    public function test_execute_action_decision_vector_search(): void
    {
        $this->decisionSearchMock
            ->shouldReceive('search')
            ->once()
            ->with('test query', ['query' => 'test query'])
            ->andReturn([['id' => 'decision-1', 'score' => 0.85]]);

        $result = $this->service->executeAction('decision_vector_search', [
            'query' => 'test query',
        ]);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    /** @test */
    public function test_execute_action_case_vector_search(): void
    {
        $this->caseSearchMock
            ->shouldReceive('search')
            ->once()
            ->with('test query', ['query' => 'test query'])
            ->andReturn([['id' => 'case-1', 'score' => 0.8]]);

        $result = $this->service->executeAction('case_vector_search', [
            'query' => 'test query',
        ]);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    /** @test */
    public function test_execute_action_unknown_tool(): void
    {
        $result = $this->service->executeAction('unknown_tool', ['query' => 'test']);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Unknown search tool: unknown_tool', $result['error']);
    }

    /** @test */
    public function test_execute_missing_tool_name(): void
    {
        $actions = [
            ['params' => ['query' => 'test']], // Missing 'tool'
        ];

        $results = $this->service->execute($actions);

        $this->assertCount(1, $results);
        $this->assertFalse($results[0]['success']);
        $this->assertArrayHasKey('error', $results[0]);
    }

    /** @test */
    public function test_execute_missing_query_parameter(): void
    {
        $this->lawSearchMock
            ->shouldReceive('search')
            ->once()
            ->with('', ['limit' => 5]) // Empty query
            ->andReturn([]);

        $actions = [
            ['tool' => 'law_vector_search', 'params' => ['limit' => 5]], // Missing 'query'
        ];

        $results = $this->service->execute($actions);

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]['success']);
    }

    /** @test */
    public function test_execute_multiple_actions(): void
    {
        $this->lawSearchMock
            ->shouldReceive('search')
            ->times(2)
            ->andReturn([['id' => 'law-1', 'score' => 0.9]]);

        $this->decisionSearchMock
            ->shouldReceive('search')
            ->once()
            ->andReturn([['id' => 'decision-1', 'score' => 0.85]]);

        $actions = [
            ['tool' => 'law_vector_search', 'params' => ['query' => 'test 1']],
            ['tool' => 'law_vector_search', 'params' => ['query' => 'test 2']],
            ['tool' => 'decision_vector_search', 'params' => ['query' => 'test 3']],
        ];

        $results = $this->service->execute($actions);

        $this->assertCount(3, $results);
    }

    /** @test */
    public function test_performance_tracking(): void
    {
        $this->lawSearchMock
            ->shouldReceive('search')
            ->once()
            ->andReturn([['id' => 'law-1', 'score' => 0.9]]);

        $actions = [
            ['tool' => 'law_vector_search', 'params' => ['query' => 'test']],
        ];

        $this->service->execute($actions);

        $metrics = $this->service->getPerformanceMetrics();

        $this->assertArrayHasKey('total_searches', $metrics);
        $this->assertArrayHasKey('successful_searches', $metrics);
        $this->assertArrayHasKey('failed_searches', $metrics);
        $this->assertArrayHasKey('total_time', $metrics);
        $this->assertEquals(1, $metrics['total_searches']);
        $this->assertEquals(1, $metrics['successful_searches']);
        $this->assertEquals(0, $metrics['failed_searches']);
    }

    /** @test */
    public function test_performance_tracking_with_failures(): void
    {
        $this->lawSearchMock
            ->shouldReceive('search')
            ->once()
            ->andReturn([['id' => 'law-1', 'score' => 0.9]]);

        $this->decisionSearchMock
            ->shouldReceive('search')
            ->once()
            ->andThrow(new \Exception('Search failed'));

        $actions = [
            ['tool' => 'law_vector_search', 'params' => ['query' => 'test 1']],
            ['tool' => 'decision_vector_search', 'params' => ['query' => 'test 2']],
        ];

        $this->service->execute($actions);

        $metrics = $this->service->getPerformanceMetrics();

        $this->assertEquals(2, $metrics['total_searches']);
        $this->assertEquals(1, $metrics['successful_searches']);
        $this->assertEquals(1, $metrics['failed_searches']);
    }

    /** @test */
    public function test_performance_metrics_corpus_breakdown(): void
    {
        $this->lawSearchMock
            ->shouldReceive('search')
            ->times(2)
            ->andReturn([['id' => 'law-1', 'score' => 0.9]]);

        $this->decisionSearchMock
            ->shouldReceive('search')
            ->once()
            ->andReturn([['id' => 'decision-1', 'score' => 0.85]]);

        $actions = [
            ['tool' => 'law_vector_search', 'params' => ['query' => 'test 1']],
            ['tool' => 'law_vector_search', 'params' => ['query' => 'test 2']],
            ['tool' => 'decision_vector_search', 'params' => ['query' => 'test 3']],
        ];

        $this->service->execute($actions);

        $metrics = $this->service->getPerformanceMetrics();

        $this->assertArrayHasKey('corpus_breakdown', $metrics);
        $this->assertArrayHasKey('laws', $metrics['corpus_breakdown']);
        $this->assertArrayHasKey('decisions', $metrics['corpus_breakdown']);
        $this->assertEquals(2, $metrics['corpus_breakdown']['laws']['count']);
        $this->assertEquals(1, $metrics['corpus_breakdown']['decisions']['count']);
    }

    /** @test */
    public function test_reset_performance_metrics(): void
    {
        $this->lawSearchMock
            ->shouldReceive('search')
            ->once()
            ->andReturn([['id' => 'law-1', 'score' => 0.9]]);

        $actions = [
            ['tool' => 'law_vector_search', 'params' => ['query' => 'test']],
        ];

        $this->service->execute($actions);

        $this->assertNotEmpty($this->service->getPerformanceMetrics());

        $this->service->resetPerformanceMetrics();

        $this->assertEmpty($this->service->getPerformanceMetrics());
    }

    /** @test */
    public function test_search_corpus_with_options(): void
    {
        $this->lawSearchMock
            ->shouldReceive('search')
            ->once()
            ->with('test', [
                'limit' => 20,
                'threshold' => 0.8,
                'filters' => ['jurisdiction' => 'Croatia'],
            ])
            ->andReturn([['id' => 'law-1', 'score' => 0.9]]);

        $results = $this->service->searchCorpus('laws', 'test', [
            'limit' => 20,
            'threshold' => 0.8,
            'filters' => ['jurisdiction' => 'Croatia'],
        ]);

        $this->assertIsArray($results);
        $this->assertCount(1, $results);
    }

    /** @test */
    public function test_search_corpus_invalid_corpus(): void
    {
        $results = $this->service->searchCorpus('invalid_corpus', 'test');

        $this->assertIsArray($results);
        $this->assertArrayHasKey('error', $results);
    }

    /** @test */
    public function test_execute_with_retry_option(): void
    {
        // First call fails, second succeeds
        $this->lawSearchMock
            ->shouldReceive('search')
            ->once()
            ->andThrow(new \Exception('Temporary failure'))
            ->ordered();

        $this->lawSearchMock
            ->shouldReceive('search')
            ->once()
            ->andReturn([['id' => 'law-1', 'score' => 0.9]])
            ->ordered();

        $actions = [
            ['tool' => 'law_vector_search', 'params' => ['query' => 'test']],
        ];

        $results = $this->service->execute($actions, ['retries' => 1]);

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]['success']);
    }

    /** @test */
    public function test_execute_with_retry_exhausted(): void
    {
        // All attempts fail
        $this->lawSearchMock
            ->shouldReceive('search')
            ->times(3) // 1 initial + 2 retries
            ->andThrow(new \Exception('Persistent failure'));

        $actions = [
            ['tool' => 'law_vector_search', 'params' => ['query' => 'test']],
        ];

        $results = $this->service->execute($actions, ['retries' => 2]);

        $this->assertCount(1, $results);
        $this->assertFalse($results[0]['success']);
        $this->assertStringContainsString('Failed after', $results[0]['error']);
    }

    /** @test */
    public function test_execute_tracks_execution_time(): void
    {
        $this->lawSearchMock
            ->shouldReceive('search')
            ->once()
            ->andReturn([['id' => 'law-1', 'score' => 0.9]]);

        $actions = [
            ['tool' => 'law_vector_search', 'params' => ['query' => 'test']],
        ];

        $results = $this->service->execute($actions);

        $this->assertArrayHasKey('execution_time', $results[0]);
        $this->assertIsFloat($results[0]['execution_time']);
        $this->assertGreaterThanOrEqual(0, $results[0]['execution_time']);
    }

    /** @test */
    public function test_law_keyword_search(): void
    {
        $this->lawSearchMock
            ->shouldReceive('search')
            ->once()
            ->with('test', Mockery::on(function ($arg) {
                return isset($arg['search_type']) && $arg['search_type'] === 'keyword';
            }))
            ->andReturn([['id' => 'law-1', 'score' => 0.9]]);

        $result = $this->service->executeAction('law_keyword_search', [
            'query' => 'test',
        ]);

        $this->assertIsArray($result);
    }

    /** @test */
    public function test_law_hybrid_search(): void
    {
        $this->lawSearchMock
            ->shouldReceive('search')
            ->once()
            ->with('test', Mockery::on(function ($arg) {
                return isset($arg['search_type']) && $arg['search_type'] === 'hybrid';
            }))
            ->andReturn([['id' => 'law-1', 'score' => 0.9]]);

        $result = $this->service->executeAction('law_hybrid_search', [
            'query' => 'test',
        ]);

        $this->assertIsArray($result);
    }

    /** @test */
    public function test_decision_keyword_search(): void
    {
        $this->decisionSearchMock
            ->shouldReceive('search')
            ->once()
            ->with('test', Mockery::on(function ($arg) {
                return isset($arg['search_type']) && $arg['search_type'] === 'keyword';
            }))
            ->andReturn([['id' => 'decision-1', 'score' => 0.85]]);

        $result = $this->service->executeAction('decision_keyword_search', [
            'query' => 'test',
        ]);

        $this->assertIsArray($result);
    }

    /** @test */
    public function test_case_document_search(): void
    {
        $this->caseSearchMock
            ->shouldReceive('search')
            ->once()
            ->with('test', ['query' => 'test'])
            ->andReturn([['id' => 'case-1', 'score' => 0.8]]);

        $result = $this->service->executeAction('case_document_search', [
            'query' => 'test',
        ]);

        $this->assertIsArray($result);
    }

    /** @test */
    public function test_corpus_alias_law_singular(): void
    {
        $this->lawSearchMock
            ->shouldReceive('search')
            ->once()
            ->andReturn([['id' => 'law-1', 'score' => 0.9]]);

        $results = $this->service->searchCorpus('law', 'test'); // Singular form

        $this->assertIsArray($results);
    }

    /** @test */
    public function test_corpus_alias_decision_singular(): void
    {
        $this->decisionSearchMock
            ->shouldReceive('search')
            ->once()
            ->andReturn([['id' => 'decision-1', 'score' => 0.85]]);

        $results = $this->service->searchCorpus('decision', 'test'); // Singular form

        $this->assertIsArray($results);
    }

    /** @test */
    public function test_corpus_alias_case_singular(): void
    {
        $this->caseSearchMock
            ->shouldReceive('search')
            ->once()
            ->andReturn([['id' => 'case-1', 'score' => 0.8]]);

        $results = $this->service->searchCorpus('case', 'test'); // Singular form

        $this->assertIsArray($results);
    }
}
