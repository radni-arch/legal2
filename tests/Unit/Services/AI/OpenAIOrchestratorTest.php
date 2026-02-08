<?php

namespace Tests\Unit\Services\AI;

use App\Contracts\AI\AnalysisServiceInterface;
use App\Contracts\AI\ChatServiceInterface;
use App\Contracts\AI\EmbeddingServiceInterface;
use App\Services\AI\OpenAIOrchestrator;
use Mockery;
use Tests\TestCase;

/**
 * OpenAIOrchestrator Test Suite
 *
 * Tests the orchestrator's delegation to specialized services.
 * Uses mocks to verify proper method calls and parameter passing.
 */
class OpenAIOrchestratorTest extends TestCase
{
    protected ChatServiceInterface $chatMock;

    protected EmbeddingServiceInterface $embeddingMock;

    protected AnalysisServiceInterface $analysisMock;

    protected OpenAIOrchestrator $orchestrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->chatMock = Mockery::mock(ChatServiceInterface::class);
        $this->embeddingMock = Mockery::mock(EmbeddingServiceInterface::class);
        $this->analysisMock = Mockery::mock(AnalysisServiceInterface::class);

        $this->orchestrator = new OpenAIOrchestrator(
            $this->chatMock,
            $this->embeddingMock,
            $this->analysisMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ========================================
    // Chat Service Tests
    // ========================================

    public function test_chat_delegates_to_chat_service(): void
    {
        $messages = [
            ['role' => 'user', 'content' => 'Hello'],
        ];
        $model = 'gpt-4o';
        $options = ['temperature' => 0.7];
        $expectedResponse = [
            'choices' => [
                ['message' => ['content' => 'Hi there!']],
            ],
        ];

        $this->chatMock
            ->shouldReceive('chat')
            ->once()
            ->with($messages, $model, $options)
            ->andReturn($expectedResponse);

        $result = $this->orchestrator->chat($messages, $model, $options);

        $this->assertEquals($expectedResponse, $result);
    }

    public function test_chat_uses_default_model(): void
    {
        $messages = [['role' => 'user', 'content' => 'Test']];
        $expectedResponse = ['choices' => []];

        $this->chatMock
            ->shouldReceive('chat')
            ->once()
            ->with($messages, 'gpt-4o', [])
            ->andReturn($expectedResponse);

        $result = $this->orchestrator->chat($messages);

        $this->assertEquals($expectedResponse, $result);
    }

    public function test_chat_stream_delegates_to_chat_service(): void
    {
        $messages = [['role' => 'user', 'content' => 'Stream test']];
        $model = 'gpt-4o';
        $options = [];
        $callback = function ($chunk) {
            // Test callback
        };

        $this->chatMock
            ->shouldReceive('chatStream')
            ->once()
            ->with($messages, $model, $options, Mockery::type('callable'))
            ->andReturnNull();

        $this->orchestrator->chatStream($messages, $model, $options, $callback);

        $this->assertTrue(true); // Assert delegation occurred
    }

    public function test_chat_stream_uses_default_callback_when_null(): void
    {
        $messages = [['role' => 'user', 'content' => 'Test']];

        $this->chatMock
            ->shouldReceive('chatStream')
            ->once()
            ->with($messages, 'gpt-4o', [], Mockery::type('callable'))
            ->andReturnNull();

        $this->orchestrator->chatStream($messages);

        $this->assertTrue(true);
    }

    public function test_get_available_models_delegates_to_chat_service(): void
    {
        $expectedModels = ['gpt-4o', 'gpt-4o-mini', 'gpt-3.5-turbo'];

        $this->chatMock
            ->shouldReceive('getAvailableModels')
            ->once()
            ->andReturn($expectedModels);

        $result = $this->orchestrator->getAvailableModels();

        $this->assertEquals($expectedModels, $result);
    }

    // ========================================
    // Embedding Service Tests
    // ========================================

    public function test_embed_delegates_to_embedding_service(): void
    {
        $text = 'Test text for embedding';
        $model = 'text-embedding-3-small';
        $expectedEmbedding = ['embedding' => [0.1, 0.2, 0.3]];

        $this->embeddingMock
            ->shouldReceive('embed')
            ->once()
            ->with($text, $model)
            ->andReturn($expectedEmbedding);

        $result = $this->orchestrator->embed($text, $model);

        $this->assertEquals($expectedEmbedding, $result);
    }

    public function test_embed_uses_default_model(): void
    {
        $text = 'Test';
        $expectedEmbedding = ['embedding' => []];

        $this->embeddingMock
            ->shouldReceive('embed')
            ->once()
            ->with($text, 'text-embedding-3-small')
            ->andReturn($expectedEmbedding);

        $result = $this->orchestrator->embed($text);

        $this->assertEquals($expectedEmbedding, $result);
    }

    public function test_batch_embed_delegates_to_embedding_service(): void
    {
        $texts = ['Text 1', 'Text 2', 'Text 3'];
        $model = 'text-embedding-3-small';
        $expectedEmbeddings = [
            ['embedding' => [0.1, 0.2]],
            ['embedding' => [0.3, 0.4]],
            ['embedding' => [0.5, 0.6]],
        ];

        $this->embeddingMock
            ->shouldReceive('batchEmbed')
            ->once()
            ->with($texts, $model)
            ->andReturn($expectedEmbeddings);

        $result = $this->orchestrator->batchEmbed($texts, $model);

        $this->assertEquals($expectedEmbeddings, $result);
    }

    public function test_get_embedding_dimensions_delegates_to_embedding_service(): void
    {
        $model = 'text-embedding-3-small';
        $expectedDimensions = 1536;

        $this->embeddingMock
            ->shouldReceive('getEmbeddingDimensions')
            ->once()
            ->with($model)
            ->andReturn($expectedDimensions);

        $result = $this->orchestrator->getEmbeddingDimensions($model);

        $this->assertEquals($expectedDimensions, $result);
    }

    public function test_embeddings_with_string_input_delegates_to_embed(): void
    {
        $text = 'Single text';
        $model = 'text-embedding-3-small';
        $expectedResult = ['embedding' => [0.1, 0.2]];

        $this->embeddingMock
            ->shouldReceive('embed')
            ->once()
            ->with($text, $model)
            ->andReturn($expectedResult);

        $result = $this->orchestrator->embeddings($text, $model);

        $this->assertEquals($expectedResult, $result);
    }

    public function test_embeddings_with_array_input_delegates_to_batch_embed(): void
    {
        $texts = ['Text 1', 'Text 2'];
        $model = 'text-embedding-3-small';
        $expectedResult = [
            ['embedding' => [0.1]],
            ['embedding' => [0.2]],
        ];

        $this->embeddingMock
            ->shouldReceive('batchEmbed')
            ->once()
            ->with($texts, $model)
            ->andReturn($expectedResult);

        $result = $this->orchestrator->embeddings($texts, $model);

        $this->assertEquals($expectedResult, $result);
    }

    public function test_embeddings_uses_default_model_when_null(): void
    {
        $text = 'Test';
        $expectedResult = ['embedding' => []];

        $this->embeddingMock
            ->shouldReceive('embed')
            ->once()
            ->with($text, 'text-embedding-3-small')
            ->andReturn($expectedResult);

        $result = $this->orchestrator->embeddings($text, null);

        $this->assertEquals($expectedResult, $result);
    }

    // ========================================
    // Analysis Service Tests
    // ========================================

    public function test_analyze_legal_text_delegates_to_analysis_service(): void
    {
        $text = 'Legal document text';
        $options = ['detailed' => true];
        $expectedAnalysis = [
            'summary' => 'Legal summary',
            'entities' => ['Court', 'Law'],
        ];

        $this->analysisMock
            ->shouldReceive('analyzeLegalText')
            ->once()
            ->with($text, $options)
            ->andReturn($expectedAnalysis);

        $result = $this->orchestrator->analyzeLegalText($text, $options);

        $this->assertEquals($expectedAnalysis, $result);
    }

    public function test_summarize_delegates_to_analysis_service(): void
    {
        $text = 'Long text to summarize';
        $maxLength = 200;
        $expectedSummary = 'Short summary';

        $this->analysisMock
            ->shouldReceive('summarize')
            ->once()
            ->with($text, $maxLength)
            ->andReturn($expectedSummary);

        $result = $this->orchestrator->summarize($text, $maxLength);

        $this->assertEquals($expectedSummary, $result);
    }

    public function test_summarize_uses_default_max_length(): void
    {
        $text = 'Text';
        $expectedSummary = 'Summary';

        $this->analysisMock
            ->shouldReceive('summarize')
            ->once()
            ->with($text, 500)
            ->andReturn($expectedSummary);

        $result = $this->orchestrator->summarize($text);

        $this->assertEquals($expectedSummary, $result);
    }

    public function test_extract_structured_data_delegates_to_analysis_service(): void
    {
        $text = 'Document with structured data';
        $schema = [
            'case_number' => 'string',
            'date' => 'date',
            'parties' => 'array',
        ];
        $expectedData = [
            'case_number' => 'ABC-123/2024',
            'date' => '2024-11-05',
            'parties' => ['Plaintiff', 'Defendant'],
        ];

        $this->analysisMock
            ->shouldReceive('extractStructuredData')
            ->once()
            ->with($text, $schema)
            ->andReturn($expectedData);

        $result = $this->orchestrator->extractStructuredData($text, $schema);

        $this->assertEquals($expectedData, $result);
    }

    // ========================================
    // Integration Tests
    // ========================================

    public function test_orchestrator_can_be_resolved_from_container(): void
    {
        $orchestrator = $this->app->make(OpenAIOrchestrator::class);

        $this->assertInstanceOf(OpenAIOrchestrator::class, $orchestrator);
    }

    public function test_orchestrator_services_are_singletons(): void
    {
        $orchestrator1 = $this->app->make(OpenAIOrchestrator::class);
        $orchestrator2 = $this->app->make(OpenAIOrchestrator::class);

        $this->assertSame($orchestrator1, $orchestrator2);
    }
}
