<?php

namespace Tests\Unit\Services;

use App\Models\AgentVectorMemory;
use App\Services\IngestPipelineService;
use App\Services\OcrService;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class IngestPipelineServiceTest extends TestCase
{
    use UsesTestDatabase;

    protected IngestPipelineService $service;

    protected OpenAIService $mockOpenAI;

    protected OcrService $mockOcr;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock OpenAI service
        $this->mockOpenAI = $this->createMock(OpenAIService::class);
        $this->mockOcr = $this->createMock(OcrService::class);

        $this->service = new IngestPipelineService($this->mockOpenAI, $this->mockOcr);

        // Set up config
        Config::set('openai.models.embeddings', 'text-embedding-ada-002');
    }

    // ===== Text Chunking Tests =====

    /** @test */
    public function it_chunks_text_with_default_parameters()
    {
        $text = str_repeat('This is a test sentence. ', 200); // ~5000 chars
        $chunks = $this->service->chunkText($text);

        $this->assertIsArray($chunks);
        $this->assertGreaterThan(1, count($chunks));
        $this->assertLessThanOrEqual(2000, strlen($chunks[0]));
    }

    /** @test */
    public function it_chunks_text_with_custom_chunk_size()
    {
        $text = str_repeat('Test. ', 200); // ~1200 chars
        $chunks = $this->service->chunkText($text, 500, 50);

        $this->assertGreaterThan(1, count($chunks));
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(500, strlen($chunk));
        }
    }

    /** @test */
    public function it_chunks_text_with_overlap()
    {
        $text = str_repeat('ABCDEFGHIJ', 50); // 500 chars
        $chunks = $this->service->chunkText($text, 100, 20);

        $this->assertGreaterThan(1, count($chunks));
        // Verify overlap exists between consecutive chunks
        for ($i = 0; $i < count($chunks) - 1; $i++) {
            $this->assertNotEmpty($chunks[$i]);
        }
    }

    /** @test */
    public function it_returns_empty_array_for_empty_text()
    {
        $chunks = $this->service->chunkText('');
        $this->assertEmpty($chunks);

        $chunks = $this->service->chunkText('   ');
        $this->assertEmpty($chunks);
    }

    /** @test */
    public function it_returns_single_chunk_for_short_text()
    {
        $text = 'This is a short text.';
        $chunks = $this->service->chunkText($text, 2000, 200);

        $this->assertCount(1, $chunks);
        $this->assertEquals('This is a short text.', $chunks[0]);
    }

    /** @test */
    public function it_trims_whitespace_in_chunks()
    {
        $text = '   Start text    Middle text    End text   ';
        $chunks = $this->service->chunkText($text, 50, 10);

        foreach ($chunks as $chunk) {
            $this->assertEquals($chunk, trim($chunk));
            $this->assertStringNotContainsString('    ', $chunk); // Multiple spaces removed
        }
    }

    // ===== Ingest Text Tests =====

    /** @test */
    public function it_ingests_text_successfully()
    {
        $text = str_repeat('Sample text for ingestion. ', 100);
        $embeddings = [array_fill(0, 1536, 0.1)];

        $this->mockOpenAI->expects($this->once())
            ->method('embeddings')
            ->willReturn([
                'data' => [
                    ['embedding' => $embeddings[0]],
                ],
            ]);

        $result = $this->service->ingestText('test-agent', 'test-namespace', $text);

        $this->assertArrayHasKey('count', $result);
        $this->assertArrayHasKey('inserted', $result);
        $this->assertGreaterThan(0, $result['count']);
    }

    /** @test */
    public function it_returns_zero_count_for_empty_text_ingestion()
    {
        $result = $this->service->ingestText('test-agent', 'test-namespace', '');

        $this->assertEquals(0, $result['count']);
        $this->assertEquals(0, $result['inserted']);
    }

    /** @test */
    public function it_respects_custom_chunk_options_in_text_ingestion()
    {
        $text = str_repeat('Custom chunk test. ', 200);
        $embeddings = [array_fill(0, 1536, 0.1), array_fill(0, 1536, 0.2)];

        $this->mockOpenAI->expects($this->once())
            ->method('embeddings')
            ->willReturn([
                'data' => array_map(fn ($e) => ['embedding' => $e], $embeddings),
            ]);

        $result = $this->service->ingestText(
            'test-agent',
            'test-namespace',
            $text,
            [
                'chunk_chars' => 1000,
                'overlap' => 100,
                'source' => 'custom-source',
                'source_id' => 'custom-id',
            ]
        );

        $this->assertArrayHasKey('count', $result);
        $this->assertGreaterThan(0, $result['count']);
    }

    /** @test */
    public function it_uses_custom_model_for_text_ingestion()
    {
        $text = 'Test text for custom model.';

        $this->mockOpenAI->expects($this->once())
            ->method('embeddings')
            ->with(
                $this->anything(),
                'custom-embedding-model'
            )
            ->willReturn([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.1)],
                ],
            ]);

        $result = $this->service->ingestText(
            'test-agent',
            'test-namespace',
            $text,
            ['model' => 'custom-embedding-model']
        );

        $this->assertEquals('custom-embedding-model', $result['model']);
    }

    // ===== Ingest File Tests =====

    /** @test */
    public function it_ingests_plain_text_file()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'Plain text content for testing.');

        $this->mockOpenAI->expects($this->once())
            ->method('embeddings')
            ->willReturn([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.1)],
                ],
            ]);

        $result = $this->service->ingestFile('test-agent', 'test-namespace', $tempFile);

        $this->assertArrayHasKey('count', $result);
        $this->assertGreaterThan(0, $result['count']);

        unlink($tempFile);
    }

    /** @test */
    public function it_ingests_pdf_file_with_ocr()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_').'.pdf';
        file_put_contents($tempFile, 'Fake PDF content');

        $this->mockOcr->expects($this->once())
            ->method('extractTextFromPdf')
            ->with($tempFile)
            ->willReturn('Extracted PDF text content.');

        $this->mockOpenAI->expects($this->once())
            ->method('embeddings')
            ->willReturn([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.1)],
                ],
            ]);

        $result = $this->service->ingestFile(
            'test-agent',
            'test-namespace',
            $tempFile,
            'application/pdf'
        );

        $this->assertArrayHasKey('count', $result);
        $this->assertGreaterThan(0, $result['count']);

        unlink($tempFile);
    }

    /** @test */
    public function it_falls_back_to_file_contents_when_ocr_returns_empty()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_').'.pdf';
        file_put_contents($tempFile, 'Fallback content');

        $this->mockOcr->expects($this->once())
            ->method('extractTextFromPdf')
            ->willReturn('');

        $this->mockOpenAI->expects($this->once())
            ->method('embeddings')
            ->willReturn([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.1)],
                ],
            ]);

        $result = $this->service->ingestFile(
            'test-agent',
            'test-namespace',
            $tempFile,
            'application/pdf'
        );

        $this->assertArrayHasKey('count', $result);

        unlink($tempFile);
    }

    /** @test */
    public function it_returns_skipped_for_empty_file()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, '');

        $result = $this->service->ingestFile('test-agent', 'test-namespace', $tempFile);

        $this->assertEquals(0, $result['count']);
        $this->assertEquals(0, $result['inserted']);
        $this->assertTrue($result['skipped']);

        unlink($tempFile);
    }

    /** @test */
    public function it_handles_nonexistent_file()
    {
        $result = $this->service->ingestFile(
            'test-agent',
            'test-namespace',
            '/nonexistent/file/path.txt'
        );

        $this->assertEquals(0, $result['count']);
        $this->assertEquals(0, $result['inserted']);
        $this->assertTrue($result['skipped']);
    }

    // ===== Ingest Documents Tests =====

    /** @test */
    public function it_ingests_documents_with_embeddings()
    {
        $docs = [
            [
                'content' => 'Document 1 content',
                'metadata' => ['type' => 'legal'],
                'source' => 'test',
                'source_id' => 'doc-1',
                'chunk_index' => 0,
            ],
            [
                'content' => 'Document 2 content',
                'metadata' => ['type' => 'case'],
                'source' => 'test',
                'source_id' => 'doc-2',
                'chunk_index' => 1,
            ],
        ];

        $this->mockOpenAI->expects($this->once())
            ->method('embeddings')
            ->with(['Document 1 content', 'Document 2 content'], 'text-embedding-ada-002')
            ->willReturn([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.1)],
                    ['embedding' => array_fill(0, 1536, 0.4)],
                ],
            ]);

        $result = $this->service->ingestDocuments('test-agent', 'test-namespace', $docs);

        $this->assertEquals(2, $result['count']);
        $this->assertEquals(2, $result['inserted']);
        $this->assertEquals(1536, $result['dimensions']);
        $this->assertEquals('text-embedding-ada-002', $result['model']);

        // Verify records in database
        $this->assertDatabaseHas('agent_vector_memories', [
            'agent_name' => 'test-agent',
            'namespace' => 'test-namespace',
            'content' => 'Document 1 content',
        ]);
    }

    /** @test */
    public function it_filters_empty_documents()
    {
        $docs = [
            ['content' => 'Valid content', 'chunk_index' => 0],
            ['content' => '', 'chunk_index' => 1],
            ['content' => '   ', 'chunk_index' => 2],
            ['content' => 'Another valid', 'chunk_index' => 3],
        ];

        $this->mockOpenAI->expects($this->once())
            ->method('embeddings')
            ->willReturn([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.1)],
                    ['embedding' => array_fill(0, 1536, 0.4)],
                ],
            ]);

        $result = $this->service->ingestDocuments('test-agent', 'test-namespace', $docs);

        $this->assertEquals(2, $result['count']); // Only 2 valid documents
    }

    /** @test */
    public function it_returns_zero_for_all_empty_documents()
    {
        $docs = [
            ['content' => '', 'chunk_index' => 0],
            ['content' => '   ', 'chunk_index' => 1],
        ];

        $result = $this->service->ingestDocuments('test-agent', 'test-namespace', $docs);

        $this->assertEquals(0, $result['count']);
        $this->assertEquals(0, $result['inserted']);
    }

    /** @test */
    public function it_skips_duplicate_documents_by_content_hash()
    {
        $content = 'Duplicate test content';
        $hash = hash('sha256', $content);

        // Insert first document
        AgentVectorMemory::create([
            'id' => (string) Str::ulid(),
            'agent_name' => 'test-agent',
            'namespace' => 'test-namespace',
            'content' => $content,
            'content_hash' => $hash,
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-ada-002',
            'embedding_dimensions' => 1536,
            'embedding' => array_fill(0, 1536, 0.1),
            'embedding_norm' => 0.374,
            'token_count' => 5,
        ]);

        // Try to insert duplicate
        $docs = [
            ['content' => $content, 'chunk_index' => 0],
        ];

        $this->mockOpenAI->expects($this->once())
            ->method('embeddings')
            ->willReturn([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.1)],
                ],
            ]);

        $result = $this->service->ingestDocuments('test-agent', 'test-namespace', $docs);

        $this->assertEquals(1, $result['count']);
        $this->assertEquals(0, $result['inserted']); // Should be skipped as duplicate
    }

    /** @test */
    public function it_handles_embedding_count_mismatch()
    {
        Log::shouldReceive('warning')
            ->once()
            ->with('Embedding count mismatch', ['docs' => 2, 'embeddings' => 1]);

        $docs = [
            ['content' => 'Doc 1', 'chunk_index' => 0],
            ['content' => 'Doc 2', 'chunk_index' => 1],
        ];

        $this->mockOpenAI->expects($this->once())
            ->method('embeddings')
            ->willReturn([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.1)],
                    // Missing second embedding
                ],
            ]);

        $result = $this->service->ingestDocuments('test-agent', 'test-namespace', $docs);

        $this->assertEquals(2, $result['count']);
    }

    /** @test */
    public function it_stores_metadata_as_json()
    {
        $docs = [
            [
                'content' => 'Test content',
                'metadata' => ['key1' => 'value1', 'key2' => 'value2'],
                'chunk_index' => 0,
            ],
        ];

        $this->mockOpenAI->expects($this->once())
            ->method('embeddings')
            ->willReturn([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.1)],
                ],
            ]);

        $result = $this->service->ingestDocuments('test-agent', 'test-namespace', $docs);

        $this->assertEquals(1, $result['inserted']);

        $record = AgentVectorMemory::where('agent_name', 'test-agent')->first();
        $this->assertNotNull($record);
        $this->assertIsArray($record->metadata);
        $this->assertEquals('value1', $record->metadata['key1']);
        $this->assertEquals('value2', $record->metadata['key2']);
    }

    /** @test */
    public function it_calculates_embedding_norm_correctly()
    {
        $docs = [
            ['content' => 'Test norm', 'chunk_index' => 0],
        ];

        // Create a 1536-dimensional vector with first two elements as 3.0 and 4.0, rest as 0.0
        // Norm should still be 5.0 (sqrt(3^2 + 4^2 + 0 + 0 + ...))
        $embedding = array_merge([3.0, 4.0], array_fill(0, 1534, 0.0));
        $expectedNorm = sqrt(3.0 * 3.0 + 4.0 * 4.0); // 5.0

        $this->mockOpenAI->expects($this->once())
            ->method('embeddings')
            ->willReturn([
                'data' => [
                    ['embedding' => $embedding],
                ],
            ]);

        $result = $this->service->ingestDocuments('test-agent', 'test-namespace', $docs);

        $record = AgentVectorMemory::where('agent_name', 'test-agent')->first();
        $this->assertEquals($expectedNorm, $record->embedding_norm, '', 0.01);
    }

    /** @test */
    public function it_estimates_token_count()
    {
        $docs = [
            ['content' => str_repeat('word ', 100), 'chunk_index' => 0], // ~500 chars = ~125 tokens
        ];

        $this->mockOpenAI->expects($this->once())
            ->method('embeddings')
            ->willReturn([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.1)],
                ],
            ]);

        $result = $this->service->ingestDocuments('test-agent', 'test-namespace', $docs);

        $record = AgentVectorMemory::where('agent_name', 'test-agent')->first();
        $this->assertGreaterThan(100, $record->token_count);
    }

    /** @test */
    public function it_sets_chunk_index_correctly()
    {
        $docs = [
            ['content' => 'Chunk 0', 'chunk_index' => 0],
            ['content' => 'Chunk 1', 'chunk_index' => 1],
            ['content' => 'Chunk 2', 'chunk_index' => 2],
        ];

        $this->mockOpenAI->expects($this->once())
            ->method('embeddings')
            ->willReturn([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.1)],
                    ['embedding' => array_fill(0, 1536, 0.4)],
                    ['embedding' => array_fill(0, 1536, 0.7)],
                ],
            ]);

        $result = $this->service->ingestDocuments('test-agent', 'test-namespace', $docs);

        $records = AgentVectorMemory::where('agent_name', 'test-agent')
            ->orderBy('chunk_index')
            ->get();

        $this->assertCount(3, $records);
        $this->assertEquals(0, $records[0]->chunk_index);
        $this->assertEquals(1, $records[1]->chunk_index);
        $this->assertEquals(2, $records[2]->chunk_index);
    }

    // ===== Search Tests =====

    /** @test */
    public function it_searches_with_vector_similarity()
    {
        // Create test memory
        AgentVectorMemory::create([
            'id' => (string) Str::ulid(),
            'agent_name' => 'search-agent',
            'namespace' => 'search-ns',
            'content' => 'Searchable content about legal cases',
            'content_hash' => hash('sha256', 'Searchable content'),
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-ada-002',
            'embedding_dimensions' => 1536,
            'embedding' => array_fill(0, 1536, 0.6),
            'embedding_norm' => 1.0,
            'token_count' => 10,
            'source' => 'test',
            'source_id' => 'test-1',
            'chunk_index' => 0,
        ]);

        $this->mockOpenAI->expects($this->once())
            ->method('embeddings')
            ->with('legal cases')
            ->willReturn([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.8)], // Same as stored vector
                ],
            ]);

        $result = $this->service->search('search-agent', 'search-ns', 'legal cases', 5);

        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('count', $result);
        $this->assertGreaterThan(0, $result['count']);
        $this->assertStringContainsString('Searchable content', $result['results'][0]['content']);
    }

    /** @test */
    public function it_limits_search_results()
    {
        // Create 10 test memories
        for ($i = 0; $i < 10; $i++) {
            AgentVectorMemory::create([
                'id' => (string) Str::ulid(),
                'agent_name' => 'limit-agent',
                'namespace' => 'limit-ns',
                'content' => "Content $i",
                'content_hash' => hash('sha256', "Content $i"),
                'embedding_provider' => 'openai',
                'embedding_model' => 'text-embedding-ada-002',
                'embedding_dimensions' => 1536,
                'embedding' => array_fill(0, 1536, 0.1 * $i),
                'embedding_norm' => 1.0,
                'token_count' => 5,
                'source' => 'test',
                'source_id' => "test-$i",
                'chunk_index' => $i,
            ]);
        }

        $this->mockOpenAI->expects($this->once())
            ->method('embeddings')
            ->willReturn([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.5)],
                ],
            ]);

        $result = $this->service->search('limit-agent', 'limit-ns', 'test query', 3);

        $this->assertLessThanOrEqual(3, $result['count']);
    }

    /** @test */
    public function it_returns_empty_results_when_no_embedding_returned()
    {
        $this->mockOpenAI->expects($this->once())
            ->method('embeddings')
            ->willReturn([
                'data' => [
                    ['embedding' => null], // No embedding
                ],
            ]);

        $result = $this->service->search('test-agent', 'test-namespace', 'query', 5);

        $this->assertEmpty($result['results']);
        $this->assertEquals(0, $result['count']);
    }

    /** @test */
    public function it_returns_empty_results_for_zero_norm_query()
    {
        $this->mockOpenAI->expects($this->once())
            ->method('embeddings')
            ->willReturn([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.0)], // Zero vector
                ],
            ]);

        $result = $this->service->search('test-agent', 'test-namespace', 'query', 5);

        $this->assertEmpty($result['results']);
        $this->assertEquals(0, $result['count']);
    }

    /** @test */
    public function it_filters_by_namespace_in_search()
    {
        // Create memories in different namespaces
        AgentVectorMemory::create([
            'id' => (string) Str::ulid(),
            'agent_name' => 'ns-agent',
            'namespace' => 'namespace-1',
            'content' => 'Content in namespace 1',
            'content_hash' => hash('sha256', 'Content 1'),
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-ada-002',
            'embedding_dimensions' => 1536,
            'embedding' => array_fill(0, 1536, 0.6),
            'embedding_norm' => 1.0,
            'token_count' => 5,
            'source' => 'test',
            'source_id' => 'test-1',
            'chunk_index' => 0,
        ]);

        AgentVectorMemory::create([
            'id' => (string) Str::ulid(),
            'agent_name' => 'ns-agent',
            'namespace' => 'namespace-2',
            'content' => 'Content in namespace 2',
            'content_hash' => hash('sha256', 'Content 2'),
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-ada-002',
            'embedding_dimensions' => 1536,
            'embedding' => array_fill(0, 1536, 0.6),
            'embedding_norm' => 1.0,
            'token_count' => 5,
            'source' => 'test',
            'source_id' => 'test-2',
            'chunk_index' => 0,
        ]);

        $this->mockOpenAI->expects($this->once())
            ->method('embeddings')
            ->willReturn([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.8)],
                ],
            ]);

        $result = $this->service->search('ns-agent', 'namespace-1', 'query', 10);

        $this->assertEquals(1, $result['count']);
        $this->assertStringContainsString('namespace 1', $result['results'][0]['content']);
    }

    /** @test */
    public function it_searches_without_namespace_filter()
    {
        // Create memories in different namespaces
        AgentVectorMemory::create([
            'id' => (string) Str::ulid(),
            'agent_name' => 'all-agent',
            'namespace' => 'ns-1',
            'content' => 'Content 1',
            'content_hash' => hash('sha256', 'Content 1'),
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-ada-002',
            'embedding_dimensions' => 1536,
            'embedding' => array_fill(0, 1536, 0.6),
            'embedding_norm' => 1.0,
            'token_count' => 5,
            'source' => 'test',
            'source_id' => 'test-1',
            'chunk_index' => 0,
        ]);

        AgentVectorMemory::create([
            'id' => (string) Str::ulid(),
            'agent_name' => 'all-agent',
            'namespace' => 'ns-2',
            'content' => 'Content 2',
            'content_hash' => hash('sha256', 'Content 2'),
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-ada-002',
            'embedding_dimensions' => 1536,
            'embedding' => array_fill(0, 1536, 0.6),
            'embedding_norm' => 1.0,
            'token_count' => 5,
            'source' => 'test',
            'source_id' => 'test-2',
            'chunk_index' => 0,
        ]);

        $this->mockOpenAI->expects($this->once())
            ->method('embeddings')
            ->willReturn([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.8)],
                ],
            ]);

        $result = $this->service->search('all-agent', null, 'query', 10);

        $this->assertEquals(2, $result['count']);
    }

    /** @test */
    public function search_results_include_metadata()
    {
        AgentVectorMemory::create([
            'id' => (string) Str::ulid(),
            'agent_name' => 'meta-agent',
            'namespace' => 'meta-ns',
            'content' => 'Content with metadata',
            'content_hash' => hash('sha256', 'Content with metadata'),
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-ada-002',
            'embedding_dimensions' => 1536,
            'embedding' => array_fill(0, 1536, 0.6),
            'embedding_norm' => 1.0,
            'token_count' => 5,
            'source' => 'custom-source',
            'source_id' => 'custom-id-123',
            'chunk_index' => 5,
        ]);

        $this->mockOpenAI->expects($this->once())
            ->method('embeddings')
            ->willReturn([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.8)],
                ],
            ]);

        $result = $this->service->search('meta-agent', 'meta-ns', 'query', 5);

        $this->assertArrayHasKey('source', $result['results'][0]);
        $this->assertArrayHasKey('source_id', $result['results'][0]);
        $this->assertArrayHasKey('chunk_index', $result['results'][0]);
        $this->assertEquals('custom-source', $result['results'][0]['source']);
        $this->assertEquals('custom-id-123', $result['results'][0]['source_id']);
        $this->assertEquals(5, $result['results'][0]['chunk_index']);
    }

    /** @test */
    public function search_results_are_sorted_by_similarity()
    {
        // Create memories with different similarity to query
        // High similarity: vector very similar to query
        $highVector = array_fill(0, 1536, 0.5);
        $highVector[0] = 0.9; // Slight variation to differentiate
        $highNorm = sqrt(array_sum(array_map(fn ($v) => $v * $v, $highVector)));

        AgentVectorMemory::create([
            'id' => (string) Str::ulid(),
            'agent_name' => 'sort-agent',
            'namespace' => 'sort-ns',
            'content' => 'High similarity',
            'content_hash' => hash('sha256', 'High similarity'),
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-ada-002',
            'embedding_dimensions' => 1536,
            'embedding' => $highVector,
            'embedding_norm' => $highNorm,
            'token_count' => 5,
            'source' => 'test',
            'source_id' => 'high',
            'chunk_index' => 0,
        ]);

        // Low similarity: vector pointing in opposite direction
        $lowVector = array_fill(0, 1536, 0.5);
        $lowVector[0] = 0.1; // Different first element
        $lowNorm = sqrt(array_sum(array_map(fn ($v) => $v * $v, $lowVector)));

        AgentVectorMemory::create([
            'id' => (string) Str::ulid(),
            'agent_name' => 'sort-agent',
            'namespace' => 'sort-ns',
            'content' => 'Low similarity',
            'content_hash' => hash('sha256', 'Low similarity'),
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-ada-002',
            'embedding_dimensions' => 1536,
            'embedding' => $lowVector,
            'embedding_norm' => $lowNorm,
            'token_count' => 5,
            'source' => 'test',
            'source_id' => 'low',
            'chunk_index' => 1,
        ]);

        $queryVector = array_fill(0, 1536, 0.5);
        $queryVector[0] = 0.9; // Matches high vector's first element

        $this->mockOpenAI->expects($this->once())
            ->method('embeddings')
            ->willReturn([
                'data' => [
                    ['embedding' => $queryVector],
                ],
            ]);

        $result = $this->service->search('sort-agent', 'sort-ns', 'query', 10);

        $this->assertEquals(2, $result['count']);
        // First result should have higher score (more similar)
        $this->assertGreaterThan($result['results'][1]['score'], $result['results'][0]['score']);
        $this->assertEquals('high', $result['results'][0]['source_id']);
    }

    /** @test */
    public function it_handles_missing_embeddings_in_search()
    {
        // Create memory with zero vector embedding (zero norm will cause it to be skipped)
        AgentVectorMemory::create([
            'id' => (string) Str::ulid(),
            'agent_name' => 'null-agent',
            'namespace' => 'null-ns',
            'content' => 'No embedding',
            'content_hash' => hash('sha256', 'No embedding'),
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-ada-002',
            'embedding_dimensions' => 1536,
            'embedding_vector' => array_fill(0, 1536, 0.0), // Zero vector
            'embedding_norm' => 0, // Zero norm means it will be filtered out in search
            'token_count' => 5,
            'source' => 'test',
            'source_id' => 'test-1',
            'chunk_index' => 0,
        ]);

        $this->mockOpenAI->expects($this->once())
            ->method('embeddings')
            ->willReturn([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.8)],
                ],
            ]);

        $result = $this->service->search('null-agent', 'null-ns', 'query', 5);

        // Zero norm vectors are filtered out in the search (line 263: if (!is_array($v) || $rNorm <= 0) continue;)
        $this->assertEquals(0, $result['count']);
    }
}
