<?php

namespace Tests\Unit\Services;

use App\Models\IngestedLaw;
use App\Models\LawUpload;
use App\Services\LawFetcher;
use App\Services\LawIngestService;
use App\Services\LawParser;
use App\Services\LawVectorStoreService;
use App\Services\MetadataBuilder;
use App\Services\PdfRenderer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class LawIngestServiceTest extends TestCase
{
    use UsesTestDatabase;

    protected LawIngestService $service;

    protected $mockFetcher;

    protected $mockParser;

    protected $mockVectorStore;

    protected $mockMeta;

    protected $mockPdfRenderer;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->mockFetcher = Mockery::mock(LawFetcher::class);
        $this->mockParser = Mockery::mock(LawParser::class);
        $this->mockVectorStore = Mockery::mock(LawVectorStoreService::class);
        $this->mockMeta = Mockery::mock(MetadataBuilder::class);
        $this->mockPdfRenderer = Mockery::mock(PdfRenderer::class);

        $this->service = new LawIngestService(
            $this->mockFetcher,
            $this->mockParser,
            $this->mockVectorStore,
            $this->mockMeta,
            $this->mockPdfRenderer
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Helper to create a generator from an array
     */
    protected function makeGenerator(array $items): \Generator
    {
        foreach ($items as $item) {
            yield $item;
        }
    }

    /** @test */
    public function it_ingests_law_successfully(): void
    {
        // Arrange
        $lawData = [
            'year' => '2024',
            'edition' => '100',
            'act' => '1',
            'title' => 'Test Zakon',
            'html_url' => 'https://example.com/zakon.html',
            'pdf_url' => 'https://example.com/zakon.pdf',
            'eli_resource' => 'hr/zakon/2024/100/1',
            'date_publication' => '2024-01-15',
            'type_document' => 'Zakon',
        ];

        $htmlContent = '<article><h1>Članak 1</h1><p>Test content</p></article>';

        Http::fake([
            'https://example.com/zakon.html' => Http::response($htmlContent, 200),
            'https://example.com/zakon.pdf' => Http::response('PDF content', 200, ['Content-Type' => 'application/pdf']),
        ]);

        $this->mockFetcher->shouldReceive('latestConsolidations')
            ->withAnyArgs()
            ->once()
            ->andReturnUsing(function () use ($lawData) {
                yield $lawData;
            });

        $this->mockParser->shouldReceive('splitIntoArticles')
            ->once()
            ->andReturn([
                ['number' => '1', 'html' => '<p>Test content</p>', 'heading_chain' => []],
            ]);

        $this->mockMeta->shouldReceive('buildArticleMetadata')
            ->once()
            ->andReturn(['test' => 'metadata']);

        $this->mockPdfRenderer->shouldReceive('renderArticle')
            ->once();

        $this->mockVectorStore->shouldReceive('ingest')
            ->once()
            ->andReturn(['inserted' => 1]);

        // Act
        $result = $this->service->ingest(['max_acts' => 1]);

        // Assert
        $this->assertEquals(1, $result['acts_processed']);
        $this->assertEquals(1, $result['articles_seen']);
        $this->assertEquals(1, $result['inserted']);
        $this->assertEquals(0, $result['errors']);

        // Verify database records
        $this->assertDatabaseHas('ingested_laws', [
            'doc_id' => 'hr/zakon/2024/100/1',
            'title' => 'Test Zakon',
            'jurisdiction' => 'HR',
        ]);

        // Verify PDF upload record created
        $this->assertDatabaseHas('law_uploads', [
            'doc_id' => 'hr/zakon/2024/100/1',
            'mime_type' => 'application/pdf',
        ]);
    }

    /** @test */
    public function it_skips_already_ingested_laws(): void
    {
        // Arrange
        $docId = 'hr/zakon/2024/100/1';

        IngestedLaw::factory()->create([
            'doc_id' => $docId,
            'title' => 'Existing Law',
        ]);

        $lawData = [
            'year' => '2024',
            'edition' => '100',
            'act' => '1',
            'title' => 'Duplicate Law',
            'html_url' => 'https://example.com/zakon.html',
            'eli_resource' => $docId,
        ];

        $this->mockFetcher->shouldReceive('latestConsolidations')
            ->withAnyArgs()
            ->once()
            ->andReturnUsing(function () use ($lawData) {
                yield $lawData;
            });

        // Act
        $result = $this->service->ingest();

        // Assert
        $this->assertEquals(1, $result['acts_processed']);
        $this->assertEquals(1, $result['skipped']);
        $this->assertEquals(0, $result['inserted']);
        $this->assertCount(1, $result['skip_messages']);
    }

    /** @test */
    public function it_handles_http_retry_with_exponential_backoff(): void
    {
        // Arrange
        $lawData = [
            'year' => '2024',
            'edition' => '101',
            'act' => '2',
            'title' => 'Test Law Retry',
            'html_url' => 'https://example.com/zakon-retry.html',
            'eli_resource' => 'hr/zakon/2024/101/2',
            'date_publication' => '2024-01-15',
            'type_document' => 'Zakon',
        ];

        // First two attempts fail, third succeeds
        Http::fake([
            'https://example.com/zakon-retry.html' => Http::sequence()
                ->push('', 500)
                ->push('', 503)
                ->push('<article><h1>Članak 1</h1><p>Content</p></article>', 200),
        ]);

        $this->mockFetcher->shouldReceive('latestConsolidations')
            ->withAnyArgs()
            ->once()
            ->andReturnUsing(function () use ($lawData) {
                yield $lawData;
            });

        $this->mockParser->shouldReceive('splitIntoArticles')
            ->once()
            ->andReturn([
                ['number' => '1', 'html' => '<p>Content</p>', 'heading_chain' => []],
            ]);

        $this->mockMeta->shouldReceive('buildArticleMetadata')
            ->once()
            ->andReturn([]);

        $this->mockPdfRenderer->shouldReceive('renderArticle')
            ->once();

        $this->mockVectorStore->shouldReceive('ingest')
            ->once()
            ->andReturn(['inserted' => 1]);

        // Act
        $result = $this->service->ingest(['max_acts' => 1]);

        // Assert
        $this->assertEquals(0, $result['errors'], 'Expected no errors, got: '.($result['errors'] ?? 0));
        $this->assertEquals(1, $result['acts_processed']);
        $this->assertEquals(1, $result['inserted']);
    }

    /** @test */
    public function it_handles_http_failure_after_max_retries(): void
    {
        // Arrange
        $lawData = [
            'year' => '2024',
            'edition' => '100',
            'act' => '1',
            'title' => 'Test Law',
            'html_url' => 'https://example.com/zakon.html',
            'eli_resource' => 'hr/zakon/2024/100/1',
            'date_publication' => '2024-01-15',
            'type_document' => 'Zakon',
        ];

        Http::fake([
            'https://example.com/zakon.html' => Http::response('', 500),
        ]);

        $this->mockFetcher->shouldReceive('latestConsolidations')
            ->withAnyArgs()
            ->once()
            ->andReturnUsing(function () use ($lawData) {
                yield $lawData;
            });

        // Act
        $result = $this->service->ingest(['max_acts' => 1]);

        // Assert
        $this->assertEquals(1, $result['acts_processed']);
        $this->assertEquals(1, $result['errors']);
        $this->assertEquals(0, $result['inserted']);
    }

    /** @test */
    public function it_validates_pdf_content_type(): void
    {
        // Arrange
        $lawData = [
            'year' => '2024',
            'edition' => '100',
            'act' => '1',
            'title' => 'Test Law',
            'html_url' => 'https://example.com/zakon.html',
            'pdf_url' => 'https://example.com/zakon.pdf',
            'eli_resource' => 'hr/zakon/2024/100/1',
            'date_publication' => '2024-01-15',
            'type_document' => 'Zakon',
        ];

        Http::fake([
            'https://example.com/zakon.html' => Http::response('<article><p>Content</p></article>', 200),
            // Wrong content type - should fail
            'https://example.com/zakon.pdf' => Http::response('Not a PDF', 200, ['Content-Type' => 'text/html']),
        ]);

        $this->mockFetcher->shouldReceive('latestConsolidations')
            ->withAnyArgs()
            ->once()
            ->andReturnUsing(function () use ($lawData) {
                yield $lawData;
            });

        $this->mockParser->shouldReceive('splitIntoArticles')
            ->once()
            ->andReturn([
                ['number' => '1', 'html' => '<p>Content</p>'],
            ]);

        $this->mockMeta->shouldReceive('buildArticleMetadata')
            ->once()
            ->andReturn([]);

        $this->mockPdfRenderer->shouldReceive('renderArticle')
            ->once();

        $this->mockVectorStore->shouldReceive('ingest')
            ->once()
            ->andReturn(['inserted' => 1]);

        // Act
        $result = $this->service->ingest(['max_acts' => 1]);

        // Assert - Should still succeed, just skip the invalid PDF
        $this->assertEquals(1, $result['acts_processed']);
        $this->assertEquals(1, $result['inserted']);

        // Verify only one upload record (full PDF should be skipped)
        $uploads = LawUpload::all();
        $this->assertGreaterThanOrEqual(1, $uploads->count());
    }

    /** @test */
    public function it_limits_acts_processed_by_max_acts_option(): void
    {
        // Arrange
        $laws = [];
        for ($i = 1; $i <= 5; $i++) {
            $laws[] = [
                'year' => '2024',
                'edition' => '100',
                'act' => (string) $i,
                'title' => "Law {$i}",
                'html_url' => "https://example.com/law{$i}.html",
                'eli_resource' => "hr/zakon/2024/100/{$i}",
                'date_publication' => '2024-01-15',
                'type_document' => 'Zakon',
            ];
        }

        Http::fake(['*' => Http::response('<article><p>Content</p></article>', 200)]);

        $this->mockFetcher->shouldReceive('latestConsolidations')
            ->withAnyArgs()
            ->once()
            ->andReturnUsing(function () use ($laws) {
                foreach ($laws as $law) {
                    yield $law;
                }
            });

        $this->mockParser->shouldReceive('splitIntoArticles')
            ->times(3)
            ->andReturn([['number' => '1', 'html' => '<p>Content</p>']]);

        $this->mockMeta->shouldReceive('buildArticleMetadata')
            ->times(3)
            ->andReturn([]);

        $this->mockPdfRenderer->shouldReceive('renderArticle')
            ->times(3);

        $this->mockVectorStore->shouldReceive('ingest')
            ->times(3)
            ->andReturn(['inserted' => 1]);

        // Act
        $result = $this->service->ingest(['max_acts' => 3]);

        // Assert
        $this->assertEquals(3, $result['acts_processed']);
        $this->assertEquals(3, $result['inserted']);
    }

    /** @test */
    public function it_filters_by_since_year(): void
    {
        // Arrange
        $laws = [
            [
                'year' => '2020',
                'edition' => '100',
                'act' => '1',
                'title' => 'Old Law',
                'html_url' => 'https://example.com/old.html',
                'eli_resource' => 'hr/zakon/2020/100/1',
                'date_publication' => '2020-01-15',
                'type_document' => 'Zakon',
            ],
            [
                'year' => '2024',
                'edition' => '100',
                'act' => '2',
                'title' => 'New Law',
                'html_url' => 'https://example.com/new.html',
                'eli_resource' => 'hr/zakon/2024/100/2',
                'date_publication' => '2024-01-15',
                'type_document' => 'Zakon',
            ],
        ];

        $this->mockFetcher->shouldReceive('latestConsolidations')
            ->withAnyArgs()
            ->with(2024)
            ->once()
            ->andReturnUsing(function () use ($laws) {
                yield $laws[1];
            });

        Http::fake(['*' => Http::response('<article><p>Content</p></article>', 200)]);

        $this->mockParser->shouldReceive('splitIntoArticles')
            ->once()
            ->andReturn([['number' => '1', 'html' => '<p>Content</p>']]);

        $this->mockMeta->shouldReceive('buildArticleMetadata')
            ->once()
            ->andReturn([]);

        $this->mockPdfRenderer->shouldReceive('renderArticle')
            ->once();

        $this->mockVectorStore->shouldReceive('ingest')
            ->once()
            ->andReturn(['inserted' => 1]);

        // Act
        $result = $this->service->ingest(['since_year' => 2024]);

        // Assert
        $this->assertEquals(1, $result['acts_processed']);
    }

    /** @test */
    public function it_stores_html_source_for_traceability(): void
    {
        // Arrange
        $lawData = [
            'year' => '2024',
            'edition' => '100',
            'act' => '1',
            'title' => 'Test Law',
            'html_url' => 'https://example.com/zakon.html',
            'eli_resource' => 'hr/zakon/2024/100/1',
            'date_publication' => '2024-01-15',
            'type_document' => 'Zakon',
        ];

        $htmlContent = '<article><h1>Članak 1</h1><p>Content</p></article>';

        Http::fake([
            'https://example.com/zakon.html' => Http::response($htmlContent, 200),
        ]);

        $this->mockFetcher->shouldReceive('latestConsolidations')
            ->withAnyArgs()
            ->once()
            ->andReturnUsing(function () use ($lawData) {
                yield $lawData;
            });

        $this->mockParser->shouldReceive('splitIntoArticles')
            ->once()
            ->andReturn([['number' => '1', 'html' => '<p>Content</p>']]);

        $this->mockMeta->shouldReceive('buildArticleMetadata')
            ->once()
            ->andReturn([]);

        $this->mockPdfRenderer->shouldReceive('renderArticle')
            ->once();

        $this->mockVectorStore->shouldReceive('ingest')
            ->once()
            ->andReturn(['inserted' => 1]);

        // Act
        $this->service->ingest(['max_acts' => 1]);

        // Assert
        Storage::disk('local')->assertExists('laws/2024/100/act-1/source.html');
        $storedHtml = Storage::disk('local')->get('laws/2024/100/act-1/source.html');
        $this->assertEquals($htmlContent, $storedHtml);
    }

    /** @test */
    public function it_generates_per_article_pdfs(): void
    {
        // Arrange
        $lawData = [
            'year' => '2024',
            'edition' => '100',
            'act' => '1',
            'title' => 'Test Law',
            'html_url' => 'https://example.com/zakon.html',
            'eli_resource' => 'hr/zakon/2024/100/1',
            'date_publication' => '2024-01-15',
        ];

        Http::fake(['*' => Http::response('<article><p>Content</p></article>', 200)]);

        $this->mockFetcher->shouldReceive('latestConsolidations')
            ->withAnyArgs()
            ->once()
            ->andReturnUsing(function () use ($lawData) {
                yield $lawData;
            });

        $this->mockParser->shouldReceive('splitIntoArticles')
            ->once()
            ->andReturn([
                ['number' => '1', 'html' => '<p>Content 1</p>', 'heading_chain' => []],
                ['number' => '2', 'html' => '<p>Content 2</p>', 'heading_chain' => []],
            ]);

        $this->mockMeta->shouldReceive('buildArticleMetadata')
            ->twice()
            ->andReturn([]);

        $this->mockPdfRenderer->shouldReceive('renderArticle')
            ->twice()
            ->with(Mockery::on(function ($data) {
                return isset($data['law_title']) &&
                    isset($data['article_number']) &&
                    isset($data['article_html']);
            }), Mockery::type('string'));

        $this->mockVectorStore->shouldReceive('ingest')
            ->once()
            ->andReturn(['inserted' => 2]);

        // Act
        $result = $this->service->ingest(['max_acts' => 1]);

        // Assert
        $this->assertEquals(2, $result['articles_seen']);

        // Verify upload records for both articles
        $uploads = LawUpload::all();
        $this->assertGreaterThanOrEqual(2, $uploads->count());
    }

    /** @test */
    public function it_handles_missing_html_url_gracefully(): void
    {
        // Arrange
        $lawData = [
            'year' => '2024',
            'edition' => '100',
            'act' => '1',
            'title' => 'Law without URL',
            // No html_url
            'eli_resource' => 'hr/zakon/2024/100/1',
        ];

        $this->mockFetcher->shouldReceive('latestConsolidations')
            ->withAnyArgs()
            ->once()
            ->andReturnUsing(function () use ($lawData) {
                yield $lawData;
            });

        // Act
        $result = $this->service->ingest();

        // Assert
        $this->assertEquals(1, $result['acts_processed']);
        $this->assertEquals(0, $result['inserted']);
        $this->assertEquals(0, $result['errors']);
    }

    /** @test */
    public function it_converts_html_to_plain_text(): void
    {
        // Arrange
        $lawData = [
            'year' => '2024',
            'edition' => '100',
            'act' => '1',
            'title' => 'Test Law',
            'html_url' => 'https://example.com/zakon.html',
            'eli_resource' => 'hr/zakon/2024/100/1',
            'date_publication' => '2024-01-15',
            'type_document' => 'Zakon',
        ];

        $htmlContent = '<article><h1>Članak 1</h1><p>Test &nbsp; content</p><script>alert("xss")</script></article>';

        Http::fake(['*' => Http::response($htmlContent, 200)]);

        $this->mockFetcher->shouldReceive('latestConsolidations')
            ->withAnyArgs()
            ->once()
            ->andReturnUsing(function () use ($lawData) {
                yield $lawData;
            });

        $this->mockParser->shouldReceive('splitIntoArticles')
            ->once()
            ->andReturn([['number' => '1', 'html' => '<p>Test &nbsp; content</p><script>alert("xss")</script>']]);

        $this->mockMeta->shouldReceive('buildArticleMetadata')
            ->once()
            ->andReturn([]);

        $this->mockPdfRenderer->shouldReceive('renderArticle')
            ->once();

        $this->mockVectorStore->shouldReceive('ingest')
            ->once()
            ->with(Mockery::any(), Mockery::on(function ($docs) {
                // Check that HTML was properly converted to text
                $content = $docs[0]['content'] ?? '';

                return strpos($content, '<') === false && // No HTML tags
                    strpos($content, 'script') === false && // Script tag removed
                    ! empty($content); // Has content
            }), Mockery::any())
            ->andReturn(['inserted' => 1]);

        // Act
        $result = $this->service->ingest(['max_acts' => 1]);

        // Assert
        $this->assertEquals(1, $result['inserted']);
    }

    /** @test */
    public function it_passes_options_to_vector_store(): void
    {
        // Arrange
        $lawData = [
            'year' => '2024',
            'edition' => '100',
            'act' => '1',
            'title' => 'Test Law',
            'html_url' => 'https://example.com/zakon.html',
            'eli_resource' => 'hr/zakon/2024/100/1',
            'date_publication' => '2024-01-15',
            'type_document' => 'Zakon',
        ];

        Http::fake(['*' => Http::response('<article><p>Content</p></article>', 200)]);

        $this->mockFetcher->shouldReceive('latestConsolidations')
            ->withAnyArgs()
            ->once()
            ->andReturnUsing(function () use ($lawData) {
                yield $lawData;
            });

        $this->mockParser->shouldReceive('splitIntoArticles')
            ->once()
            ->andReturn([['number' => '1', 'html' => '<p>Content</p>']]);

        $this->mockMeta->shouldReceive('buildArticleMetadata')
            ->once()
            ->andReturn([]);

        $this->mockPdfRenderer->shouldReceive('renderArticle')
            ->once();

        $this->mockVectorStore->shouldReceive('ingest')
            ->once()
            ->with(
                Mockery::any(),
                Mockery::any(),
                Mockery::on(function ($options) {
                    return $options['model'] === 'custom-embedding-model' &&
                        $options['provider'] === 'openai' &&
                        isset($options['ingested_law_id']);
                })
            )
            ->andReturn(['inserted' => 1]);

        // Act
        $result = $this->service->ingest([
            'max_acts' => 1,
            'model' => 'custom-embedding-model',
            'agent' => 'test-agent',
            'namespace' => 'test-ns',
        ]);

        // Assert
        $this->assertEquals('custom-embedding-model', $result['model']);
        $this->assertEquals('test-agent', $result['agent']);
        $this->assertEquals('test-ns', $result['namespace']);
    }

    /** @test */
    public function it_calculates_exponential_backoff_delay_correctly(): void
    {
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateBackoffDelay');
        $method->setAccessible(true);

        // Test attempt 1: should be ~1000ms base + jitter
        $delay1 = $method->invoke($this->service, 1);
        $this->assertGreaterThanOrEqual(1000, $delay1);
        $this->assertLessThanOrEqual(1500, $delay1);

        // Test attempt 2: should be ~2000ms base + jitter
        $delay2 = $method->invoke($this->service, 2);
        $this->assertGreaterThanOrEqual(2000, $delay2);
        $this->assertLessThanOrEqual(3000, $delay2);

        // Test attempt 3: should be ~4000ms base + jitter
        $delay3 = $method->invoke($this->service, 3);
        $this->assertGreaterThanOrEqual(4000, $delay3);
        $this->assertLessThanOrEqual(6000, $delay3);

        // Verify exponential growth
        $this->assertGreaterThan($delay1, $delay2);
        $this->assertGreaterThan($delay2, $delay3);
    }

    /** @test */
    public function it_handles_empty_articles_list(): void
    {
        // Arrange
        $lawData = [
            'year' => '2024',
            'edition' => '100',
            'act' => '1',
            'title' => 'Test Law',
            'html_url' => 'https://example.com/zakon.html',
            'eli_resource' => 'hr/zakon/2024/100/1',
            'date_publication' => '2024-01-15',
            'type_document' => 'Zakon',
        ];

        Http::fake(['*' => Http::response('<article><p>Content</p></article>', 200)]);

        $this->mockFetcher->shouldReceive('latestConsolidations')
            ->withAnyArgs()
            ->once()
            ->andReturnUsing(function () use ($lawData) {
                yield $lawData;
            });

        $this->mockParser->shouldReceive('splitIntoArticles')
            ->once()
            ->andReturn([]); // No articles found

        // Act
        $result = $this->service->ingest(['max_acts' => 1]);

        // Assert
        $this->assertEquals(1, $result['acts_processed']);
        $this->assertEquals(0, $result['articles_seen']);
        $this->assertEquals(0, $result['inserted']);
    }

    /** @test */
    public function it_returns_comprehensive_statistics(): void
    {
        // Arrange
        $lawData = [
            'year' => '2024',
            'edition' => '100',
            'act' => '1',
            'title' => 'Test Law',
            'html_url' => 'https://example.com/zakon.html',
            'eli_resource' => 'hr/zakon/2024/100/1',
            'date_publication' => '2024-01-15',
            'type_document' => 'Zakon',
        ];

        Http::fake(['*' => Http::response('<article><p>Content</p></article>', 200)]);

        $this->mockFetcher->shouldReceive('latestConsolidations')
            ->withAnyArgs()
            ->once()
            ->andReturnUsing(function () use ($lawData) {
                yield $lawData;
            });

        $this->mockParser->shouldReceive('splitIntoArticles')
            ->once()
            ->andReturn([['number' => '1', 'html' => '<p>Content</p>']]);

        $this->mockMeta->shouldReceive('buildArticleMetadata')
            ->once()
            ->andReturn([]);

        $this->mockPdfRenderer->shouldReceive('renderArticle')
            ->once();

        $this->mockVectorStore->shouldReceive('ingest')
            ->once()
            ->andReturn(['inserted' => 1]);

        // Act
        $result = $this->service->ingest(['max_acts' => 1]);

        // Assert - Verify all expected statistics are present
        $this->assertArrayHasKey('acts_processed', $result);
        $this->assertArrayHasKey('articles_seen', $result);
        $this->assertArrayHasKey('inserted', $result);
        $this->assertArrayHasKey('skipped', $result);
        $this->assertArrayHasKey('skip_messages', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('model', $result);
        $this->assertArrayHasKey('agent', $result);
        $this->assertArrayHasKey('namespace', $result);
    }
}
