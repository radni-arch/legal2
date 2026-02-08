<?php

namespace Tests\Unit\Mcp;

use App\Mcp\OdlukeTools;
use App\Models\IngestedLaw;
use App\Models\Law;
use App\Services\Odluke\OdlukeClient;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Unit tests for OdlukeTools class
 */
class OdlukeToolsTest extends TestCase
{
    use UsesTestDatabase;

    protected OdlukeTools $tools;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new OdlukeTools;
    }

    /**
     * @test
     * Integration test - makes real HTTP call to odluke.sudovi.hr
     */
    public function search_returns_success_response_with_ids(): void
    {
        // Integration test: Make real HTTP call with simple query likely to return results
        try {
            $result = $this->tools->search('kazneno', null, 5, 1);
        } catch (\Exception $e) {
            $this->markTestSkipped('External service unavailable: '.$e->getMessage());
        }

        $this->assertIsArray($result);
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('isError', $result);
        $this->assertIsArray($result['content']);
        $this->assertArrayHasKey('text', $result['content'][0]);

        // If service returns results, verify structure
        if (! $result['isError']) {
            $data = json_decode($result['content'][0]['text'], true);
            $this->assertArrayHasKey('ids', $data);
            $this->assertIsArray($data['ids']);
        }
    }

    /**
     * @test
     * Integration test - tests error handling when no results found
     */
    public function search_returns_error_when_no_ids_found(): void
    {
        // Integration test: Use extremely specific query unlikely to return results
        try {
            $result = $this->tools->search('xyzabc123nonexistent999', null, 100, 1);
        } catch (\Exception $e) {
            $this->markTestSkipped('External service unavailable: '.$e->getMessage());
        }

        $this->assertIsArray($result);
        $this->assertArrayHasKey('isError', $result);
        $this->assertArrayHasKey('content', $result);

        // Result should indicate no IDs found (either error or empty result)
        if ($result['isError']) {
            $this->assertStringContainsString('Nema ID-eva', $result['content'][0]['text']);
        } else {
            // If not error, should be empty result
            $data = json_decode($result['content'][0]['text'], true);
            $this->assertEmpty($data['ids'] ?? []);
        }
    }

    /**
     * @test
     * Integration test - verifies limit parameter is respected
     */
    public function search_respects_limit_parameter(): void
    {
        // Integration test: Request with small limit and verify we don't exceed it
        try {
            $result = $this->tools->search('kazneno', null, 3, 1);
        } catch (\Exception $e) {
            $this->markTestSkipped('External service unavailable: '.$e->getMessage());
        }

        $this->assertIsArray($result);

        // If we got results, verify count doesn't exceed limit
        if (! $result['isError']) {
            $data = json_decode($result['content'][0]['text'], true);
            if (isset($data['ids']) && is_array($data['ids'])) {
                $this->assertLessThanOrEqual(3, count($data['ids']),
                    'Result count should not exceed requested limit of 3');
            }
        }
    }

    /** @test */
    public function meta_returns_error_when_no_ids_provided(): void
    {
        $result = $this->tools->meta(null, null);

        $this->assertTrue($result['isError']);
        $this->assertStringContainsString('barem jedan id', $result['content'][0]['text']);
    }

    /** @test */
    public function meta_handles_single_id_parameter(): void
    {
        $mockClient = $this->createMock(OdlukeClient::class);
        $mockClient->method('fetchDecisionMeta')
            ->willReturn([
                'id' => 'test-id',
                'title' => 'Test Decision',
                'date' => '2024-01-01',
            ]);
        $mockClient->method('buildBaseFileName')
            ->willReturn('test-decision');
        $mockClient->method('downloadPdfUrl')
            ->willReturn('https://example.com/pdf');
        $mockClient->method('downloadHtmlUrl')
            ->willReturn('https://example.com/html');

        $this->app->instance(OdlukeClient::class, $mockClient);

        $result = $this->tools->meta('test-id');

        $this->assertFalse($result['isError']);
        $this->assertStringContainsString('test-id', $result['content'][0]['text']);
        $this->assertStringContainsString('Test Decision', $result['content'][0]['text']);
    }

    /** @test */
    public function meta_handles_multiple_ids(): void
    {
        $mockClient = $this->createMock(OdlukeClient::class);
        $mockClient->method('fetchDecisionMeta')
            ->willReturn(['id' => 'test']);
        $mockClient->method('buildBaseFileName')
            ->willReturn('test');
        $mockClient->method('downloadPdfUrl')
            ->willReturn('https://example.com/pdf');
        $mockClient->method('downloadHtmlUrl')
            ->willReturn('https://example.com/html');

        $this->app->instance(OdlukeClient::class, $mockClient);

        $result = $this->tools->meta(null, ['id1', 'id2']);

        $this->assertFalse($result['isError']);
        $content = $result['content'][0]['text'];
        $decoded = json_decode($content, true);
        $this->assertCount(2, $decoded);
    }

    /** @test */
    public function download_validates_format_parameter(): void
    {
        $mockClient = $this->createMock(OdlukeClient::class);
        $mockClient->method('fetchDecisionMeta')->willReturn([]);
        $mockClient->method('buildBaseFileName')->willReturn('test');
        $mockClient->method('downloadPdf')->willReturn(['ok' => true, 'bytes' => 'pdf-content', 'content_type' => 'application/pdf']);
        $mockClient->method('downloadPdfUrl')->willReturn('https://example.com/pdf');
        $mockClient->method('downloadHtmlUrl')->willReturn('https://example.com/html');

        $this->app->instance(OdlukeClient::class, $mockClient);

        // Invalid format should default to 'pdf'
        $result = $this->tools->download('test-id', 'invalid-format', false);

        $this->assertFalse($result['isError']);
    }

    /** @test */
    public function download_handles_pdf_format(): void
    {
        $mockClient = $this->createMock(OdlukeClient::class);
        $mockClient->method('fetchDecisionMeta')->willReturn([]);
        $mockClient->method('buildBaseFileName')->willReturn('test');
        $mockClient->method('downloadPdf')
            ->willReturn([
                'ok' => true,
                'bytes' => 'pdf-content',
                'content_type' => 'application/pdf',
            ]);
        $mockClient->method('downloadPdfUrl')->willReturn('https://example.com/pdf');
        $mockClient->method('downloadHtmlUrl')->willReturn('https://example.com/html');

        $this->app->instance(OdlukeClient::class, $mockClient);

        $result = $this->tools->download('test-id', 'pdf', false);

        $this->assertFalse($result['isError']);
        $content = json_decode($result['content'][0]['text'], true);
        $this->assertArrayHasKey('pdf', $content);
        $this->assertEquals(11, $content['pdf']['bytes']); // strlen('pdf-content')
    }

    /** @test */
    public function download_handles_http_errors(): void
    {
        $mockClient = $this->createMock(OdlukeClient::class);
        $mockClient->method('fetchDecisionMeta')->willReturn([]);
        $mockClient->method('buildBaseFileName')->willReturn('test');
        $mockClient->method('downloadPdf')
            ->willReturn(['ok' => false, 'status' => 404]);
        $mockClient->method('downloadPdfUrl')->willReturn('https://example.com/pdf');
        $mockClient->method('downloadHtmlUrl')->willReturn('https://example.com/html');

        $this->app->instance(OdlukeClient::class, $mockClient);

        $result = $this->tools->download('test-id', 'pdf', false);

        $this->assertTrue($result['isError']);
        $content = json_decode($result['content'][0]['text'], true);
        $this->assertArrayHasKey('errors', $content);
        $this->assertStringContainsString('404', $content['errors']['pdf']);
    }

    /** @test */
    public function search_law_articles_returns_empty_result_when_no_laws_found(): void
    {
        $result = $this->tools->searchLawArticles('nonexistent query');

        $this->assertFalse($result['isError']);
        $this->assertStringContainsString('Nema zakona', $result['content'][0]['text']);
    }

    /** @test */
    public function search_law_articles_finds_laws_by_query(): void
    {
        // Create test data
        $ingestedLaw = IngestedLaw::create([
            'id' => 'test-law-id',
            'doc_id' => 'NN-123-20',
            'title' => 'Test Law About Labour',
            'law_number' => 'NN 123/20',
            'jurisdiction' => 'Croatia',
            'country' => 'HR',
            'language' => 'hr',
            'ingested_at' => now(),
        ]);

        Law::create([
            'id' => 'test-article-1',
            'doc_id' => 'NN-123-20',
            'ingested_law_id' => $ingestedLaw->id,
            'chunk_index' => 1,
            'content' => 'Article 1 content',
            'chapter' => 'Chapter 1',
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-3-small',
            'embedding_dimensions' => 1536,
            'content_hash' => hash('sha256', 'Article 1 content'),
            'embedding_vector' => '['.implode(',', array_fill(0, 1536, 0.1)).']',
        ]);

        $result = $this->tools->searchLawArticles('Labour');

        $this->assertFalse($result['isError']);
        $content = json_decode($result['content'][0]['text'], true);
        $this->assertGreaterThan(0, $content['count']);
        $this->assertArrayHasKey('results', $content);
        $this->assertEquals('Test Law About Labour', $content['results'][0]['title']);
    }

    /** @test */
    public function search_law_articles_filters_by_law_number(): void
    {
        IngestedLaw::create([
            'id' => 'law-1',
            'doc_id' => 'NN-123-20',
            'title' => 'Law 123',
            'law_number' => 'NN 123/20',
            'jurisdiction' => 'Croatia',
            'ingested_at' => now(),
        ]);

        IngestedLaw::create([
            'id' => 'law-2',
            'doc_id' => 'NN-456-20',
            'title' => 'Law 456',
            'law_number' => 'NN 456/20',
            'jurisdiction' => 'Croatia',
            'ingested_at' => now(),
        ]);

        $result = $this->tools->searchLawArticles(null, '123');

        $content = json_decode($result['content'][0]['text'], true);
        $this->assertEquals(1, $content['count']);
        $this->assertEquals('NN 123/20', $content['results'][0]['law_number']);
    }

    /** @test */
    public function search_law_articles_respects_limit(): void
    {
        // Create 15 laws
        for ($i = 1; $i <= 15; $i++) {
            IngestedLaw::create([
                'id' => "law-{$i}",
                'doc_id' => "NN-{$i}-20",
                'title' => "Law {$i}",
                'law_number' => "NN {$i}/20",
                'jurisdiction' => 'Croatia',
                'ingested_at' => now(),
            ]);
        }

        $result = $this->tools->searchLawArticles(null, null, null, 5);

        $content = json_decode($result['content'][0]['text'], true);
        $this->assertLessThanOrEqual(5, $content['count']);
    }

    /** @test */
    public function search_law_articles_eager_loads_articles(): void
    {
        $ingestedLaw = IngestedLaw::create([
            'id' => 'law-1',
            'doc_id' => 'NN-123-20',
            'title' => 'Test Law',
            'law_number' => 'NN 123/20',
            'jurisdiction' => 'Croatia',
            'ingested_at' => now(),
        ]);

        // Create 3 articles
        for ($i = 1; $i <= 3; $i++) {
            Law::create([
                'id' => "article-{$i}",
                'doc_id' => 'NN-123-20',
                'ingested_law_id' => $ingestedLaw->id,
                'chunk_index' => $i,
                'content' => "Article {$i} content",
                'embedding_provider' => 'openai',
                'embedding_model' => 'text-embedding-3-small',
                'embedding_dimensions' => 1536,
                'content_hash' => hash('sha256', "Article {$i} content"),
                'embedding_vector' => '['.implode(',', array_fill(0, 1536, 0.1)).']',
            ]);
        }

        // Enable query log to check for N+1
        \DB::enableQueryLog();

        $result = $this->tools->searchLawArticles('Test');

        $queries = \DB::getQueryLog();
        // Should be 2 queries: 1 for IngestedLaw, 1 for eager-loaded Laws
        $this->assertLessThanOrEqual(2, count($queries));

        $content = json_decode($result['content'][0]['text'], true);
        $this->assertEquals(3, $content['results'][0]['articles_count']);
    }

    /** @test */
    public function get_law_article_by_id_returns_error_when_id_empty(): void
    {
        $result = $this->tools->getLawArticleById('');

        $this->assertTrue($result['isError']);
        $this->assertStringContainsString('obavezan', $result['content'][0]['text']);
    }

    /** @test */
    public function get_law_article_by_id_returns_error_when_not_found(): void
    {
        $result = $this->tools->getLawArticleById('nonexistent-id');

        $this->assertTrue($result['isError']);
        $this->assertStringContainsString('nije pronađen', $result['content'][0]['text']);
    }

    /** @test */
    public function get_law_article_by_id_returns_article_with_parent_law(): void
    {
        $ingestedLaw = IngestedLaw::create([
            'id' => 'parent-law',
            'doc_id' => 'NN-123-20',
            'title' => 'Parent Law',
            'law_number' => 'NN 123/20',
            'jurisdiction' => 'Croatia',
            'ingested_at' => now(),
        ]);

        $articleId = (string) \Illuminate\Support\Str::ulid();
        $article = Law::create([
            'id' => $articleId,
            'doc_id' => 'NN-123-20',
            'ingested_law_id' => $ingestedLaw->id,
            'chunk_index' => 5,
            'content' => 'Test article content',
            'chapter' => 'Chapter 2',
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-3-small',
            'embedding_dimensions' => 1536,
            'content_hash' => hash('sha256', 'Test article content'),
            'embedding_vector' => '['.implode(',', array_fill(0, 1536, 0.1)).']',
        ]);

        $result = $this->tools->getLawArticleById($articleId);

        $this->assertFalse($result['isError']);
        $content = json_decode($result['content'][0]['text'], true);
        $this->assertEquals($articleId, $content['id']);
        $this->assertEquals('Test article content', $content['content']);
        $this->assertEquals(5, $content['chunk_index']);
        $this->assertArrayHasKey('parent_law', $content);
        $this->assertEquals('Parent Law', $content['parent_law']['title']);
    }
}
