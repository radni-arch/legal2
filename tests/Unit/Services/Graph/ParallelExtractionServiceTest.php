<?php

namespace Tests\Unit\Services\Graph;

use App\Models\TextractDocument;
use App\Services\Graph\ParallelExtractionService;
use Illuminate\Support\Facades\Concurrency;
use Tests\TestCase;

class ParallelExtractionServiceTest extends TestCase
{
    private ParallelExtractionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ParallelExtractionService();
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_runs_extractors_in_parallel(): void
    {
        // Mock document with content
        $document = new TextractDocument([
            'id' => 'test-doc-1',
            'content' => 'Test legal document content',
        ]);

        // Define extractors to run
        $extractorClasses = [
            'App\Services\Graph\Extractors\LegalTopicExtractor',
            'App\Services\Graph\Extractors\DateEventExtractor',
            'App\Services\Graph\Extractors\LegalConceptExtractor',
        ];

        // Spy on Concurrency to verify parallel execution
        Concurrency::spy();

        // Execute extraction
        $results = $this->service->extractInParallel($document, $extractorClasses);

        // Verify Concurrency::run was called for parallel execution
        Concurrency::shouldHaveReceived('run')
            ->once()
            ->with(\Mockery::type('array'));

        // Verify results structure
        $this->assertIsArray($results);
        $this->assertCount(3, $results);
    }

    /** @test */
    public function it_collects_results_from_all_extractors(): void
    {
        // Mock document
        $document = new TextractDocument([
            'id' => 'test-doc-2',
            'content' => 'Legal document with multiple entities and dates',
        ]);

        // Define multiple extractors
        $extractorClasses = [
            'App\Services\Graph\Extractors\LegalTopicExtractor',
            'App\Services\Graph\Extractors\DateEventExtractor',
            'App\Services\Graph\Extractors\LegalConceptExtractor',
            'App\Services\Graph\Extractors\LegalDefinitionExtractor',
        ];

        // Execute extraction
        $results = $this->service->extractInParallel($document, $extractorClasses);

        // Verify all extractors returned results
        $this->assertIsArray($results);
        $this->assertCount(4, $results);

        // Each result should have extractor class and extracted data
        foreach ($results as $result) {
            $this->assertArrayHasKey('extractor', $result);
            $this->assertArrayHasKey('data', $result);
            $this->assertContains($result['extractor'], $extractorClasses);
        }
    }

    /** @test */
    public function it_handles_extractor_failures_gracefully(): void
    {
        // Mock document
        $document = new TextractDocument([
            'id' => 'test-doc-3',
            'content' => 'Test content that may cause extractor failure',
        ]);

        // Mix valid extractors with one that will fail
        $extractorClasses = [
            'App\Services\Graph\Extractors\LegalTopicExtractor',
            'App\Services\Graph\Extractors\NonExistentExtractor', // This should fail
            'App\Services\Graph\Extractors\DateEventExtractor',
        ];

        // Execute extraction - should not throw exception
        $results = $this->service->extractInParallel($document, $extractorClasses);

        // Verify results exist even with failures
        $this->assertIsArray($results);

        // Results should include error information for failed extractor
        $failedResults = array_filter($results, function ($result) {
            return isset($result['error']) && $result['error'] === true;
        });

        $this->assertNotEmpty($failedResults);

        // Successful extractors should still have their results
        $successfulResults = array_filter($results, function ($result) {
            return !isset($result['error']) || $result['error'] === false;
        });

        $this->assertNotEmpty($successfulResults);
    }

    /** @test */
    public function it_respects_concurrency_limit(): void
    {
        // Mock document
        $document = new TextractDocument([
            'id' => 'test-doc-4',
            'content' => 'Test document for concurrency testing',
        ]);

        // Define many extractors to test limit
        $extractorClasses = [
            'App\Services\Graph\Extractors\LegalTopicExtractor',
            'App\Services\Graph\Extractors\DateEventExtractor',
            'App\Services\Graph\Extractors\LegalConceptExtractor',
            'App\Services\Graph\Extractors\LegalDefinitionExtractor',
            'App\Services\Graph\Extractors\LegalArgumentExtractor',
            'App\Services\Graph\Extractors\EvidenceExtractor',
        ];

        // Set concurrency limit to 3
        $this->service->setConcurrencyLimit(3);

        // Spy on Concurrency facade
        Concurrency::spy();

        // Execute extraction
        $results = $this->service->extractInParallel($document, $extractorClasses);

        // Verify concurrency limit is respected via chunking
        // With 6 extractors and limit of 3, run() should be called twice (2 chunks)
        Concurrency::shouldHaveReceived('run')
            ->times(2);

        // Verify all extractors still executed despite limit
        $this->assertIsArray($results);
        $this->assertCount(6, $results);

        // Verify service returns the set limit
        $this->assertEquals(3, $this->service->getConcurrencyLimit());
    }

    /** @test */
    public function it_returns_fluent_interface_from_set_concurrency_limit(): void
    {
        $result = $this->service->setConcurrencyLimit(5);

        $this->assertInstanceOf(ParallelExtractionService::class, $result);
        $this->assertSame($this->service, $result);
    }

    /** @test */
    public function it_uses_default_concurrency_limit_from_config(): void
    {
        // Use Laravel's proper config helper (isolated per test via RefreshApplication)
        $this->app['config']->set('graph.parallel.concurrency_limit', 10);

        $service = new ParallelExtractionService();

        $this->assertEquals(10, $service->getConcurrencyLimit());
    }

    /** @test */
    public function it_handles_empty_extractor_array(): void
    {
        $document = new TextractDocument([
            'id' => 'test-doc-5',
            'content' => 'Test content',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Extractor classes array cannot be empty');

        $this->service->extractInParallel($document, []);
    }
}
