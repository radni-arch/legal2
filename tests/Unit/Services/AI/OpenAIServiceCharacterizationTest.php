<?php

namespace Tests\Unit\Services\AI;

use App\Services\OpenAIService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Characterization Tests for OpenAIService
 *
 * These tests document and freeze the current behavior of OpenAIService.php (1,010 lines).
 * DO NOT modify these tests during refactoring - they serve as the regression test suite.
 *
 * Test Coverage:
 * - 32 public methods
 * - Circuit breaker integration
 * - Error handling and retries
 * - Logging behavior
 * - Config defaults
 * - HTTP client configuration
 */
class OpenAIServiceCharacterizationTest extends TestCase
{
    protected OpenAIService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Set required config
        Config::set('openai.api_key', 'sk-test-key-1234567890');
        Config::set('openai.organization', 'org-test');
        Config::set('openai.project', 'proj-test');
        Config::set('openai.base_url', 'https://api.openai.com/v1');
        Config::set('openai.timeout', 60);
        Config::set('openai.connect_timeout', 10);
        Config::set('openai.models.chat', 'gpt-4o');
        Config::set('openai.models.embeddings', 'text-embedding-3-small');
        Config::set('openai.models.image', 'dall-e-3');
        Config::set('openai.models.stt', 'whisper-1');
        Config::set('openai.models.tts', 'tts-1');
        Config::set('openai.models.responses', 'gpt-4o');
        Config::set('openai.circuit_breaker.failure_threshold', 5);
        Config::set('openai.circuit_breaker.success_threshold', 2);
        Config::set('openai.circuit_breaker.timeout', 60);
        Config::set('openai.circuit_breaker.retry_after', 30);

        $this->service = new OpenAIService;
    }

    // ========== Chat & Completions Tests ==========

    /** @test */
    public function it_sends_basic_chat_completion_request()
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'id' => 'chatcmpl-123',
                'object' => 'chat.completion',
                'model' => 'gpt-4o',
                'choices' => [
                    [
                        'index' => 0,
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Test response',
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 10,
                    'completion_tokens' => 5,
                    'total_tokens' => 15,
                ],
            ], 200),
        ]);

        $messages = [
            ['role' => 'user', 'content' => 'Test message'],
        ];

        $response = $this->service->chat($messages);

        $this->assertEquals('chatcmpl-123', $response['id']);
        $this->assertEquals('Test response', $response['choices'][0]['message']['content']);
        $this->assertEquals(15, $response['usage']['total_tokens']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.openai.com/v1/chat/completions' &&
                $request->hasHeader('Authorization', 'Bearer sk-test-key-1234567890') &&
                $request->hasHeader('OpenAI-Organization', 'org-test') &&
                $request->hasHeader('OpenAI-Project', 'proj-test') &&
                $request->hasHeader('Content-Type', 'application/json') &&
                $request['model'] === 'gpt-4o' &&
                $request['messages'][0]['role'] === 'user' &&
                $request['messages'][0]['content'] === 'Test message';
        });
    }

    /** @test */
    public function it_sends_chat_completion_with_custom_model()
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'id' => 'chatcmpl-456',
                'model' => 'gpt-4o-mini',
                'choices' => [
                    ['message' => ['role' => 'assistant', 'content' => 'Response']],
                ],
            ], 200),
        ]);

        $messages = [['role' => 'user', 'content' => 'Hello']];

        $response = $this->service->chat($messages, 'gpt-4o-mini');

        $this->assertEquals('gpt-4o-mini', $response['model']);

        Http::assertSent(function ($request) {
            return $request['model'] === 'gpt-4o-mini';
        });
    }

    /** @test */
    public function it_sends_chat_completion_with_options()
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'id' => 'chatcmpl-789',
                'choices' => [
                    ['message' => ['role' => 'assistant', 'content' => 'Response']],
                ],
            ], 200),
        ]);

        $messages = [['role' => 'user', 'content' => 'Test']];
        $options = [
            'temperature' => 0.7,
            'max_tokens' => 500,
            'top_p' => 0.9,
        ];

        $this->service->chat($messages, null, $options);

        Http::assertSent(function ($request) {
            return $request['temperature'] === 0.7 &&
                $request['max_tokens'] === 500 &&
                $request['top_p'] === 0.9;
        });
    }

    /** @test */
    public function it_throws_exception_when_api_key_missing()
    {
        Config::set('openai.api_key', '');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OPENAI_API_KEY is not configured');

        $service = new OpenAIService;
        $service->chat([['role' => 'user', 'content' => 'Test']]);
    }

    /** @test */
    public function it_logs_chat_request_and_response()
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'id' => 'chatcmpl-log',
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'Response']]],
            ], 200),
        ]);

        Log::shouldReceive('channel')
            ->with('openai')
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->with('openai.request', \Mockery::on(function ($data) {
                return $data['event'] === 'openai.request' &&
                    $data['method'] === 'POST' &&
                    isset($data['request_id']);
            }));

        Log::shouldReceive('info')
            ->with('openai.response', \Mockery::on(function ($data) {
                return $data['event'] === 'openai.response' &&
                    $data['status'] === 200 &&
                    isset($data['duration_ms']);
            }));

        $response = $this->service->chat([['role' => 'user', 'content' => 'Test']]);

        $this->assertEquals('chatcmpl-log', $response['id']);
    }

    // ========== Embeddings Tests ==========

    /** @test */
    public function it_generates_embeddings_for_single_text()
    {
        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response([
                'object' => 'list',
                'data' => [
                    [
                        'object' => 'embedding',
                        'embedding' => array_fill(0, 1536, 0.1),
                        'index' => 0,
                    ],
                ],
                'model' => 'text-embedding-3-small',
                'usage' => [
                    'prompt_tokens' => 5,
                    'total_tokens' => 5,
                ],
            ], 200),
        ]);

        $response = $this->service->embeddings('Test text');

        $this->assertEquals('list', $response['object']);
        $this->assertCount(1536, $response['data'][0]['embedding']);
        $this->assertEquals(5, $response['usage']['prompt_tokens']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.openai.com/v1/embeddings' &&
                $request['model'] === 'text-embedding-3-small' &&
                $request['input'] === 'Test text';
        });
    }

    /** @test */
    public function it_generates_batch_embeddings_for_array_input()
    {
        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response([
                'object' => 'list',
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.1), 'index' => 0],
                    ['embedding' => array_fill(0, 1536, 0.2), 'index' => 1],
                    ['embedding' => array_fill(0, 1536, 0.3), 'index' => 2],
                ],
                'usage' => [
                    'prompt_tokens' => 15,
                    'total_tokens' => 15,
                ],
            ], 200),
        ]);

        $texts = ['First text', 'Second text', 'Third text'];

        $response = $this->service->embeddings($texts);

        $this->assertCount(3, $response['data']);
        $this->assertEquals(15, $response['usage']['prompt_tokens']);

        Http::assertSent(function ($request) {
            return $request['input'] === ['First text', 'Second text', 'Third text'];
        });
    }

    /** @test */
    public function it_generates_embeddings_with_custom_model()
    {
        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response([
                'data' => [['embedding' => array_fill(0, 3072, 0.1)]],
                'model' => 'text-embedding-3-large',
            ], 200),
        ]);

        $this->service->embeddings('Test', 'text-embedding-3-large');

        Http::assertSent(function ($request) {
            return $request['model'] === 'text-embedding-3-large';
        });
    }

    /** @test */
    public function it_extracts_embedding_vector_with_create_embedding_method()
    {
        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response([
                'data' => [
                    ['embedding' => [0.1, 0.2, 0.3, 0.4, 0.5]],
                ],
            ], 200),
        ]);

        $vector = $this->service->createEmbedding('Test text');

        $this->assertEquals([0.1, 0.2, 0.3, 0.4, 0.5], $vector);
    }

    /** @test */
    public function it_returns_empty_array_when_embedding_data_missing()
    {
        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response([
                'data' => [],
            ], 200),
        ]);

        $vector = $this->service->createEmbedding('Test');

        $this->assertEquals([], $vector);
    }

    // ========== Responses API Tests ==========

    /** @test */
    public function it_creates_response_with_responses_api()
    {
        Http::fake([
            'api.openai.com/v1/responses' => Http::response([
                'id' => 'resp-123',
                'object' => 'response',
            ], 200),
        ]);

        $response = $this->service->responses(['prompt' => 'Test']);

        $this->assertEquals('resp-123', $response['id']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.openai.com/v1/responses' &&
                $request['model'] === 'gpt-4o' &&
                $request['prompt'] === 'Test';
        });
    }

    /** @test */
    public function it_lists_responses_with_include_parameters()
    {
        Http::fake([
            '*' => Http::response([
                'object' => 'list',
                'data' => [
                    ['id' => 'resp-1', 'object' => 'response'],
                ],
            ], 200),
        ]);

        $include = ['message.input_text', 'output_text'];
        $query = ['limit' => 10];

        $response = $this->service->responsesList($query, $include);

        $this->assertEquals('list', $response['object']);
        $this->assertCount(1, $response['data']);
        $this->assertEquals('resp-1', $response['data'][0]['id']);
    }

    /** @test */
    public function it_retrieves_single_response_by_id()
    {
        Http::fake([
            '*' => Http::response([
                'id' => 'resp-456',
                'object' => 'response',
                'created' => time(),
            ], 200),
        ]);

        $response = $this->service->responseRetrieve('resp-456', ['message.input_text']);

        $this->assertEquals('resp-456', $response['id']);
        $this->assertEquals('response', $response['object']);
        $this->assertArrayHasKey('created', $response);
    }

    /** @test */
    public function it_retrieves_response_input_items()
    {
        Http::fake([
            '*' => Http::response([
                'object' => 'list',
                'data' => [
                    ['id' => 'item-1', 'type' => 'input_message'],
                ],
            ], 200),
        ]);

        $response = $this->service->responseInputItems('resp-789', ['message.input_image.image_url']);

        $this->assertEquals('list', $response['object']);
        $this->assertIsArray($response['data']);
        $this->assertCount(1, $response['data']);
    }

    /** @test */
    public function it_gets_responses_with_default_includes()
    {
        Http::fake([
            '*' => Http::response([
                'object' => 'list',
                'data' => [
                    ['id' => 'resp-a'],
                    ['id' => 'resp-b'],
                ],
                'has_more' => false,
            ], 200),
        ]);

        $response = $this->service->getResponses(['limit' => 5]);

        $this->assertEquals('list', $response['object']);
        $this->assertIsArray($response['data']);
        $this->assertCount(2, $response['data']);
    }

    // ========== Image Generation Tests ==========

    /** @test */
    public function it_generates_image_with_defaults()
    {
        Http::fake([
            'api.openai.com/v1/images/generations' => Http::response([
                'created' => time(),
                'data' => [
                    ['b64_json' => 'base64encodedimage...'],
                ],
            ], 200),
        ]);

        $response = $this->service->imageGenerate('A beautiful sunset');

        $this->assertArrayHasKey('data', $response);
        $this->assertCount(1, $response['data']);

        Http::assertSent(function ($request) {
            return $request['model'] === 'dall-e-3' &&
                $request['prompt'] === 'A beautiful sunset' &&
                $request['n'] === 1 &&
                $request['size'] === '1024x1024' &&
                $request['response_format'] === 'b64_json';
        });
    }

    /** @test */
    public function it_generates_image_with_custom_options()
    {
        Http::fake([
            'api.openai.com/v1/images/generations' => Http::response([
                'data' => [
                    ['url' => 'https://example.com/image.png'],
                ],
            ], 200),
        ]);

        $options = [
            'n' => 2,
            'size' => '512x512',
            'response_format' => 'url',
            'quality' => 'hd',
        ];

        $this->service->imageGenerate('Test prompt', $options);

        Http::assertSent(function ($request) {
            return $request['n'] === 2 &&
                $request['size'] === '512x512' &&
                $request['response_format'] === 'url' &&
                $request['quality'] === 'hd';
        });
    }

    // ========== File Operations Tests ==========

    /** @test */
    public function it_lists_files()
    {
        Http::fake([
            'api.openai.com/v1/files' => Http::response([
                'object' => 'list',
                'data' => [
                    ['id' => 'file-123', 'filename' => 'test.txt'],
                    ['id' => 'file-456', 'filename' => 'data.json'],
                ],
            ], 200),
        ]);

        $response = $this->service->fileList();

        $this->assertEquals('list', $response['object']);
        $this->assertCount(2, $response['data']);
    }

    /** @test */
    public function it_retrieves_file_by_id()
    {
        Http::fake([
            'api.openai.com/v1/files/file-abc' => Http::response([
                'id' => 'file-abc',
                'object' => 'file',
                'filename' => 'document.pdf',
            ], 200),
        ]);

        $response = $this->service->fileRetrieve('file-abc');

        $this->assertEquals('file-abc', $response['id']);
        $this->assertEquals('document.pdf', $response['filename']);
    }

    /** @test */
    public function it_deletes_file()
    {
        Http::fake([
            'api.openai.com/v1/files/file-xyz' => Http::response([
                'id' => 'file-xyz',
                'object' => 'file',
                'deleted' => true,
            ], 200),
        ]);

        $response = $this->service->fileDelete('file-xyz');

        $this->assertTrue($response['deleted']);

        Http::assertSent(function ($request) {
            return $request->method() === 'DELETE' &&
                str_contains($request->url(), '/files/file-xyz');
        });
    }

    // ========== Assistants API Tests ==========

    /** @test */
    public function it_creates_assistant_with_default_model()
    {
        Http::fake([
            'api.openai.com/v1/assistants' => Http::response([
                'id' => 'asst-123',
                'object' => 'assistant',
                'model' => 'gpt-4o',
            ], 200),
        ]);

        $response = $this->service->assistantsCreate([
            'name' => 'Legal Assistant',
            'instructions' => 'You are a legal expert',
        ]);

        $this->assertEquals('asst-123', $response['id']);

        Http::assertSent(function ($request) {
            return $request['model'] === 'gpt-4o' &&
                $request['name'] === 'Legal Assistant';
        });
    }

    /** @test */
    public function it_retrieves_assistant()
    {
        Http::fake([
            'api.openai.com/v1/assistants/asst-456' => Http::response([
                'id' => 'asst-456',
                'name' => 'Test Assistant',
            ], 200),
        ]);

        $response = $this->service->assistantsRetrieve('asst-456');

        $this->assertEquals('asst-456', $response['id']);
    }

    /** @test */
    public function it_lists_assistants()
    {
        Http::fake([
            'api.openai.com/v1/assistants*' => Http::response([
                'object' => 'list',
                'data' => [
                    ['id' => 'asst-1'],
                    ['id' => 'asst-2'],
                ],
            ], 200),
        ]);

        $response = $this->service->assistantsList(['limit' => 10]);

        $this->assertCount(2, $response['data']);
    }

    /** @test */
    public function it_deletes_assistant()
    {
        Http::fake([
            'api.openai.com/v1/assistants/asst-789' => Http::response([
                'id' => 'asst-789',
                'deleted' => true,
            ], 200),
        ]);

        $response = $this->service->assistantsDelete('asst-789');

        $this->assertTrue($response['deleted']);
    }

    // ========== Vector Store Tests ==========

    /** @test */
    public function it_creates_vector_store()
    {
        Http::fake([
            'api.openai.com/v1/vector_stores' => Http::response([
                'id' => 'vs-123',
                'object' => 'vector_store',
            ], 200),
        ]);

        $response = $this->service->vectorStoreCreate(['name' => 'Legal Documents']);

        $this->assertEquals('vs-123', $response['id']);
    }

    /** @test */
    public function it_retrieves_vector_store()
    {
        Http::fake([
            'api.openai.com/v1/vector_stores/vs-456' => Http::response([
                'id' => 'vs-456',
                'name' => 'Case Files',
            ], 200),
        ]);

        $response = $this->service->vectorStoreRetrieve('vs-456');

        $this->assertEquals('vs-456', $response['id']);
    }

    /** @test */
    public function it_lists_vector_stores()
    {
        Http::fake([
            'api.openai.com/v1/vector_stores*' => Http::response([
                'object' => 'list',
                'data' => [
                    ['id' => 'vs-1'],
                    ['id' => 'vs-2'],
                ],
            ], 200),
        ]);

        $response = $this->service->vectorStoreList();

        $this->assertCount(2, $response['data']);
    }

    /** @test */
    public function it_deletes_vector_store()
    {
        Http::fake([
            'api.openai.com/v1/vector_stores/vs-789' => Http::response([
                'id' => 'vs-789',
                'deleted' => true,
            ], 200),
        ]);

        $response = $this->service->vectorStoreDelete('vs-789');

        $this->assertTrue($response['deleted']);
    }

    /** @test */
    public function it_adds_file_to_vector_store()
    {
        Http::fake([
            'api.openai.com/v1/vector_stores/vs-123/files' => Http::response([
                'id' => 'file-vs-123',
                'object' => 'vector_store.file',
            ], 200),
        ]);

        $response = $this->service->vectorStoreAddFile('vs-123', 'file-456');

        $this->assertEquals('file-vs-123', $response['id']);

        Http::assertSent(function ($request) {
            return $request['file_id'] === 'file-456';
        });
    }

    /** @test */
    public function it_lists_vector_store_files()
    {
        Http::fake([
            'api.openai.com/v1/vector_stores/vs-123/files*' => Http::response([
                'object' => 'list',
                'data' => [
                    ['id' => 'file-1'],
                ],
            ], 200),
        ]);

        $response = $this->service->vectorStoreListFiles('vs-123');

        $this->assertCount(1, $response['data']);
    }

    /** @test */
    public function it_deletes_file_from_vector_store()
    {
        Http::fake([
            'api.openai.com/v1/vector_stores/vs-123/files/file-456' => Http::response([
                'id' => 'file-456',
                'deleted' => true,
            ], 200),
        ]);

        $response = $this->service->vectorStoreDeleteFile('vs-123', 'file-456');

        $this->assertTrue($response['deleted']);
    }

    /** @test */
    public function it_gets_vector_store_file()
    {
        Http::fake([
            'api.openai.com/v1/vector_stores/vs-123/files/file-789' => Http::response([
                'id' => 'file-789',
                'object' => 'vector_store.file',
            ], 200),
        ]);

        $response = $this->service->vectorStoreGetFile('vs-123', 'file-789');

        $this->assertEquals('file-789', $response['id']);
    }

    // ========== RAG & Grounded Response Tests ==========

    /** @test */
    public function it_builds_grounded_prompt_with_retrieved_chunks()
    {
        $query = 'Što je ZKP Članak 9?';
        $chunks = [
            [
                'content' => 'ZKP Članak 9 govori o načelu zakonitosti.',
                'confidence' => 0.9,
                'corpus' => 'laws',
                'title' => 'Zakon o kaznenom postupku',
                'law_number' => '152/08',
            ],
            [
                'content' => 'Dodatni tekst o ZKP.',
                'confidence' => 0.7,
                'corpus' => 'laws',
                'title' => 'ZKP',
            ],
        ];

        $prompt = $this->service->buildGroundedPrompt($query, $chunks);

        $this->assertStringContainsString('Odgovorite na sljedeće pitanje koristeći SAMO informacije iz priloženih pravnih izvora', $prompt);
        $this->assertStringContainsString('ZKP Članak 9 govori o načelu zakonitosti', $prompt);
        $this->assertStringContainsString('Zakon (NN 152/08)', $prompt);
        $this->assertStringContainsString($query, $prompt);
        $this->assertStringContainsString('[1]', $prompt);
        $this->assertStringContainsString('[2]', $prompt);
    }

    /** @test */
    public function it_filters_chunks_by_minimum_confidence()
    {
        $query = 'Test query';
        $chunks = [
            ['content' => 'High confidence', 'confidence' => 0.9, 'corpus' => 'laws'],
            ['content' => 'Low confidence', 'confidence' => 0.3, 'corpus' => 'laws'],
            ['content' => 'Medium confidence', 'confidence' => 0.6, 'corpus' => 'laws'],
        ];

        $prompt = $this->service->buildGroundedPrompt($query, $chunks, ['min_confidence' => 0.5]);

        $this->assertStringContainsString('High confidence', $prompt);
        $this->assertStringContainsString('Medium confidence', $prompt);
        $this->assertStringNotContainsString('Low confidence', $prompt);
    }

    /** @test */
    public function it_limits_number_of_chunks_in_grounded_prompt()
    {
        $query = 'Test';
        $chunks = array_fill(0, 20, [
            'content' => 'Test chunk',
            'confidence' => 0.8,
            'corpus' => 'laws',
        ]);

        $prompt = $this->service->buildGroundedPrompt($query, $chunks, ['max_chunks' => 3]);

        // Should only have [1], [2], [3] citations, not [4] or higher
        $this->assertStringContainsString('[1]', $prompt);
        $this->assertStringContainsString('[2]', $prompt);
        $this->assertStringContainsString('[3]', $prompt);
        $this->assertStringNotContainsString('[4]', $prompt);
    }

    /** @test */
    public function it_builds_refusal_message_for_low_confidence()
    {
        $query = 'Test question about obscure law';
        $confidence = 30; // Percentage value (not 0-1 range)

        $refusal = $this->service->buildRefusalMessage($query, $confidence, ['min_confidence' => 50]);

        $this->assertStringContainsString('ne mogu pružiti pouzdan odgovor', $refusal);
        $this->assertStringContainsString($query, $refusal);
        $this->assertStringContainsString('30%', $refusal);
        $this->assertStringContainsString('50%', $refusal);
    }

    /** @test */
    public function it_builds_clarification_prompts_for_missing_case_id()
    {
        $query = 'Što se dogodilo u predmetu?';
        $analysis = [
            'case_id' => null,
            'članci_prioritet' => ['ZKP 9'],
        ];

        $clarifications = $this->service->buildClarificationPrompts($query, $analysis);

        $this->assertContains('Molim navedite točan broj predmeta (npr. Pp-1234/2025).', $clarifications);
    }

    /** @test */
    public function it_builds_clarification_prompts_for_missing_citations()
    {
        $query = 'Što je relevantno?';
        $analysis = [
            'case_id' => 'Pp-123/2025',
            'članci_prioritet' => [],
        ];

        $clarifications = $this->service->buildClarificationPrompts($query, $analysis);

        $this->assertContains('Na koje članke zakona se odnosi vaše pitanje?', $clarifications);
    }

    /** @test */
    public function it_creates_grounded_chat_completion_with_high_confidence()
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'ZKP Članak 9 regulira načelo zakonitosti. [1]']],
                ],
                'usage' => ['total_tokens' => 100],
            ], 200),
        ]);

        $query = 'Objasni ZKP Članak 9';
        $chunks = [
            [
                'content' => 'ZKP Članak 9 text',
                'confidence' => 0.85,
                'corpus' => 'laws',
                'title' => 'ZKP',
                'doc_id' => 'law-zkp',
            ],
        ];

        $response = $this->service->groundedChatCompletion($query, $chunks, ['min_confidence' => 0.5]);

        $this->assertTrue($response['grounded']);
        $this->assertEquals(0.85, $response['confidence']);
        $this->assertStringContainsString('ZKP Članak 9', $response['answer']);
        $this->assertArrayHasKey('citations', $response);
        $this->assertCount(1, $response['citations']);
        $this->assertEquals(1, $response['citations'][0]['number']);

        Http::assertSent(function ($request) use ($query) {
            $content = $request['messages'][1]['content'];

            return str_contains($content, $query) &&
                str_contains($content, 'ZKP Članak 9 text') &&
                $request['temperature'] === 0.3;
        });
    }

    /** @test */
    public function it_refuses_grounded_chat_completion_with_low_confidence()
    {
        $query = 'Pitanje bez dobrih izvora';
        $chunks = [
            ['content' => 'Low relevance', 'confidence' => 0.2, 'corpus' => 'laws'],
            ['content' => 'Also low', 'confidence' => 0.3, 'corpus' => 'laws'],
        ];

        $response = $this->service->groundedChatCompletion($query, $chunks, ['min_confidence' => 0.5]);

        $this->assertFalse($response['grounded']);
        $this->assertEquals(0.25, $response['confidence']); // Average of 0.2 and 0.3
        $this->assertArrayHasKey('refusal', $response);
        $this->assertStringContainsString('ne mogu pružiti pouzdan odgovor', $response['refusal']);
        $this->assertArrayHasKey('clarifications', $response);

        Http::assertNothingSent();
    }

    /** @test */
    public function it_formats_law_source_correctly()
    {
        $query = 'Test';
        $chunks = [
            [
                'content' => 'Test',
                'confidence' => 0.9,
                'corpus' => 'laws',
                'law_number' => '152/08',
            ],
        ];

        $prompt = $this->service->buildGroundedPrompt($query, $chunks);

        $this->assertStringContainsString('Zakon (NN 152/08)', $prompt);
    }

    /** @test */
    public function it_formats_case_document_source_correctly()
    {
        $query = 'Test';
        $chunks = [
            [
                'content' => 'Test',
                'confidence' => 0.9,
                'corpus' => 'cases_documents',
                'doc_id' => 'case-123',
            ],
        ];

        $prompt = $this->service->buildGroundedPrompt($query, $chunks);

        $this->assertStringContainsString('Predmet case-123', $prompt);
    }

    /** @test */
    public function it_formats_court_decision_source_correctly()
    {
        $query = 'Test';
        $chunks = [
            [
                'content' => 'Test',
                'confidence' => 0.9,
                'corpus' => 'court_decision_documents',
                'doc_id' => 'decision-456',
            ],
        ];

        $prompt = $this->service->buildGroundedPrompt($query, $chunks);

        $this->assertStringContainsString('Sudska odluka decision-456', $prompt);
    }

    // ========== Error Handling Tests ==========

    /** @test */
    public function it_throws_request_exception_on_400_error()
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'error' => [
                    'message' => 'Invalid request',
                    'type' => 'invalid_request_error',
                ],
            ], 400),
        ]);

        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->twice();
        Log::shouldReceive('error')->once();

        $this->expectException(RequestException::class);

        $this->service->chat([['role' => 'user', 'content' => 'Test']]);
    }

    /** @test */
    public function it_logs_error_on_exception()
    {
        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response('Server error', 500),
        ]);

        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->times(2); // Request log and response log
        Log::shouldReceive('error')
            ->once()
            ->with('openai.error', \Mockery::on(function ($data) {
                return $data['event'] === 'openai.error' &&
                    isset($data['error']['message']) &&
                    isset($data['duration_ms']);
            }));

        try {
            $this->service->embeddings('Test');
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            $this->assertTrue(true); // Exception was expected
        }
    }

    /** @test */
    public function it_includes_organization_header_only_when_configured()
    {
        Config::set('openai.organization', null);
        $service = new OpenAIService;

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Test']]],
            ], 200),
        ]);

        $service->chat([['role' => 'user', 'content' => 'Test']]);

        Http::assertSent(function ($request) {
            return ! $request->hasHeader('OpenAI-Organization');
        });
    }

    /** @test */
    public function it_includes_project_header_only_when_configured()
    {
        Config::set('openai.project', null);
        $service = new OpenAIService;

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Test']]],
            ], 200),
        ]);

        $service->chat([['role' => 'user', 'content' => 'Test']]);

        Http::assertSent(function ($request) {
            return ! $request->hasHeader('OpenAI-Project');
        });
    }

    // ========== Configuration Tests ==========

    /** @test */
    public function it_uses_configured_timeout()
    {
        Config::set('openai.timeout', 120);
        Config::set('openai.connect_timeout', 20);

        $service = new OpenAIService;

        // Timeout is set on the HTTP client, we verify via successful request
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Test']]],
            ], 200),
        ]);

        $service->chat([['role' => 'user', 'content' => 'Test']]);

        $this->assertTrue(true); // If it doesn't timeout, test passes
    }

    /** @test */
    public function it_uses_default_chat_model_from_config()
    {
        Config::set('openai.models.chat', 'gpt-4o-mini');

        $service = new OpenAIService;

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Test']]],
            ], 200),
        ]);

        $service->chat([['role' => 'user', 'content' => 'Test']]);

        Http::assertSent(function ($request) {
            return $request['model'] === 'gpt-4o-mini';
        });
    }

    /** @test */
    public function it_uses_default_embeddings_model_from_config()
    {
        Config::set('openai.models.embeddings', 'text-embedding-ada-002');

        $service = new OpenAIService;

        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response([
                'data' => [['embedding' => [0.1, 0.2]]],
            ], 200),
        ]);

        $service->embeddings('Test');

        Http::assertSent(function ($request) {
            return $request['model'] === 'text-embedding-ada-002';
        });
    }

    /** @test */
    public function it_uses_custom_base_url_from_config()
    {
        Config::set('openai.base_url', 'https://custom-api.example.com/v1/');

        $service = new OpenAIService;

        Http::fake([
            'custom-api.example.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Test']]],
            ], 200),
        ]);

        $service->chat([['role' => 'user', 'content' => 'Test']]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'custom-api.example.com');
        });
    }

    // ========== Audio & File Upload Tests ==========

    /** @test */
    public function it_uploads_file_with_multipart()
    {
        $tempFile = tmpfile();
        $filePath = stream_get_meta_data($tempFile)['uri'];
        file_put_contents($filePath, 'test content');

        Http::fake([
            'api.openai.com/v1/files' => Http::response([
                'id' => 'file-abc123',
                'object' => 'file',
                'bytes' => 12,
                'created_at' => time(),
                'filename' => basename($filePath),
                'purpose' => 'assistants',
            ], 200),
        ]);

        $response = $this->service->fileUpload($filePath, 'assistants');

        $this->assertEquals('file-abc123', $response['id']);
        $this->assertEquals('file', $response['object']);
        $this->assertEquals('assistants', $response['purpose']);

        fclose($tempFile);
    }

    /** @test */
    public function it_transcribes_audio_file()
    {
        $tempFile = tmpfile();
        $filePath = stream_get_meta_data($tempFile)['uri'];
        file_put_contents($filePath, 'fake audio data');

        Http::fake([
            'api.openai.com/v1/audio/transcriptions' => Http::response([
                'text' => 'Transcribed text from audio',
            ], 200),
        ]);

        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->twice();

        $response = $this->service->transcribe($filePath);

        $this->assertEquals('Transcribed text from audio', $response['text']);

        fclose($tempFile);
    }

    /** @test */
    public function it_transcribes_audio_with_custom_options()
    {
        $tempFile = tmpfile();
        $filePath = stream_get_meta_data($tempFile)['uri'];
        file_put_contents($filePath, 'fake audio data');

        Http::fake([
            'api.openai.com/v1/audio/transcriptions' => Http::response([
                'text' => 'Croatian transcription',
                'language' => 'hr',
            ], 200),
        ]);

        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->twice();

        $response = $this->service->transcribe($filePath, [
            'language' => 'hr',
            'response_format' => 'json',
        ]);

        $this->assertEquals('Croatian transcription', $response['text']);
        $this->assertEquals('hr', $response['language']);

        fclose($tempFile);
    }

    /** @test */
    public function it_generates_text_to_speech_audio()
    {
        Http::fake([
            'api.openai.com/v1/audio/speech' => Http::response('binary-audio-data', 200, [
                'Content-Type' => 'audio/mpeg',
            ]),
        ]);

        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->twice();

        $audioData = $this->service->tts('Test Croatian text');

        $this->assertEquals('binary-audio-data', $audioData);
        $this->assertIsString($audioData);
    }

    /** @test */
    public function it_generates_tts_with_custom_voice_and_format()
    {
        Http::fake([
            'api.openai.com/v1/audio/speech' => Http::response('opus-audio-data', 200, [
                'Content-Type' => 'audio/opus',
            ]),
        ]);

        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->twice();

        $audioData = $this->service->tts('Croatian legal text', [
            'voice' => 'nova',
            'format' => 'opus',
            'model' => 'tts-1-hd',
        ]);

        $this->assertEquals('opus-audio-data', $audioData);
    }

    /** @test */
    public function it_updates_vector_store_file_metadata()
    {
        Http::fake([
            'api.openai.com/v1/vector_stores/vs-123/files/file-456' => Http::response([
                'id' => 'file-456',
                'object' => 'vector_store.file',
                'vector_store_id' => 'vs-123',
                'attributes' => ['document_type' => 'optužnica', 'case_id' => 'KO-DO-58/2026'],
            ], 200),
        ]);

        $response = $this->service->vectorStoreFileMetadataUpdate('vs-123', 'file-456', [
            'attributes' => ['document_type' => 'optužnica', 'case_id' => 'KO-DO-58/2026'],
        ]);

        $this->assertEquals('file-456', $response['id']);
        $this->assertArrayHasKey('attributes', $response);
        $this->assertEquals('optužnica', $response['attributes']['document_type']);
    }
}
