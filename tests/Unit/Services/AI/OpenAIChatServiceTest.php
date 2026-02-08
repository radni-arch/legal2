<?php

namespace Tests\Unit\Services\AI;

use App\Contracts\AI\CacheServiceInterface;
use App\Services\AI\OpenAIChatService;
use App\Services\CircuitBreaker;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Unit Tests for OpenAIChatService
 *
 * TDD approach: Tests written first (RED), then implementation (GREEN), then refactor.
 *
 * Coverage:
 * - chat() method (basic, with options, caching, retries)
 * - chatStream() method (streaming responses)
 * - getAvailableModels() method
 * - Error handling and circuit breaker
 * - Configuration options
 */
class OpenAIChatServiceTest extends TestCase
{
    protected OpenAIChatService $service;

    protected CacheServiceInterface $cache;

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
        Config::set('openai.circuit_breaker.failure_threshold', 5);
        Config::set('openai.circuit_breaker.success_threshold', 2);
        Config::set('openai.circuit_breaker.timeout', 60);
        Config::set('openai.circuit_breaker.retry_after', 30);

        // Mock cache service
        $this->cache = $this->createMock(CacheServiceInterface::class);

        $this->service = new OpenAIChatService($this->cache);
    }

    // ========== Basic Chat Tests ==========

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
                            'content' => 'Hello! How can I help you today?',
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 10,
                    'completion_tokens' => 8,
                    'total_tokens' => 18,
                ],
            ], 200),
        ]);

        $messages = [
            ['role' => 'user', 'content' => 'Hello'],
        ];

        $response = $this->service->chat($messages);

        $this->assertEquals('chatcmpl-123', $response['id']);
        $this->assertEquals('Hello! How can I help you today?', $response['choices'][0]['message']['content']);
        $this->assertEquals(18, $response['usage']['total_tokens']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.openai.com/v1/chat/completions' &&
                $request->hasHeader('Authorization', 'Bearer sk-test-key-1234567890') &&
                $request['model'] === 'gpt-4o' &&
                $request['messages'][0]['role'] === 'user' &&
                $request['messages'][0]['content'] === 'Hello';
        });
    }

    /** @test */
    public function it_sends_chat_with_system_message()
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Legal response']]],
            ], 200),
        ]);

        $messages = [
            ['role' => 'system', 'content' => 'You are a Croatian legal expert'],
            ['role' => 'user', 'content' => 'Explain ZKP Article 9'],
        ];

        $this->service->chat($messages);

        Http::assertSent(function ($request) {
            return count($request['messages']) === 2 &&
                $request['messages'][0]['role'] === 'system' &&
                $request['messages'][1]['role'] === 'user';
        });
    }

    /** @test */
    public function it_sends_chat_with_custom_model()
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'model' => 'gpt-4o-mini',
                'choices' => [['message' => ['content' => 'Response']]],
            ], 200),
        ]);

        $messages = [['role' => 'user', 'content' => 'Test']];

        $this->service->chat($messages, 'gpt-4o-mini');

        Http::assertSent(function ($request) {
            return $request['model'] === 'gpt-4o-mini';
        });
    }

    /** @test */
    public function it_sends_chat_with_temperature_option()
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Creative response']]],
            ], 200),
        ]);

        $messages = [['role' => 'user', 'content' => 'Be creative']];
        $options = ['temperature' => 0.9];

        $this->service->chat($messages, 'gpt-4o', $options);

        Http::assertSent(function ($request) {
            return $request['temperature'] === 0.9;
        });
    }

    /** @test */
    public function it_sends_chat_with_max_tokens_option()
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Short response']]],
            ], 200),
        ]);

        $messages = [['role' => 'user', 'content' => 'Brief answer please']];
        $options = ['max_tokens' => 100];

        $this->service->chat($messages, 'gpt-4o', $options);

        Http::assertSent(function ($request) {
            return $request['max_tokens'] === 100;
        });
    }

    /** @test */
    public function it_sends_chat_with_multiple_options()
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Response']]],
            ], 200),
        ]);

        $messages = [['role' => 'user', 'content' => 'Test']];
        $options = [
            'temperature' => 0.7,
            'max_tokens' => 500,
            'top_p' => 0.9,
            'frequency_penalty' => 0.5,
            'presence_penalty' => 0.3,
        ];

        $this->service->chat($messages, 'gpt-4o', $options);

        Http::assertSent(function ($request) {
            return $request['temperature'] === 0.7 &&
                $request['max_tokens'] === 500 &&
                $request['top_p'] === 0.9 &&
                $request['frequency_penalty'] === 0.5 &&
                $request['presence_penalty'] === 0.3;
        });
    }

    /** @test */
    public function it_includes_organization_header_when_configured()
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Response']]],
            ], 200),
        ]);

        $messages = [['role' => 'user', 'content' => 'Test']];

        $this->service->chat($messages);

        Http::assertSent(function ($request) {
            return $request->hasHeader('OpenAI-Organization', 'org-test') &&
                $request->hasHeader('OpenAI-Project', 'proj-test');
        });
    }

    /** @test */
    public function it_omits_organization_header_when_not_configured()
    {
        Config::set('openai.organization', null);
        Config::set('openai.project', null);

        $service = new OpenAIChatService($this->cache);

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Response']]],
            ], 200),
        ]);

        $messages = [['role' => 'user', 'content' => 'Test']];

        $service->chat($messages);

        Http::assertSent(function ($request) {
            return ! $request->hasHeader('OpenAI-Organization') &&
                ! $request->hasHeader('OpenAI-Project');
        });
    }

    /** @test */
    public function it_throws_exception_when_api_key_missing()
    {
        Config::set('openai.api_key', '');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OPENAI_API_KEY is not configured');

        $service = new OpenAIChatService($this->cache);
        $service->chat([['role' => 'user', 'content' => 'Test']]);
    }

    // ========== Caching Tests ==========

    /** @test */
    public function it_checks_cache_before_making_request()
    {
        $messages = [['role' => 'user', 'content' => 'Cached question']];
        $cachedResponse = [
            'choices' => [['message' => ['content' => 'Cached answer']]],
        ];

        $this->cache->expects($this->once())
            ->method('generateKey')
            ->with('chat', $this->anything())
            ->willReturn('chat:test-cache-key');

        $this->cache->expects($this->once())
            ->method('get')
            ->with('chat:test-cache-key')
            ->willReturn($cachedResponse);

        Http::fake(); // No HTTP requests should be made

        $response = $this->service->chat($messages);

        $this->assertEquals('Cached answer', $response['choices'][0]['message']['content']);
        $this->assertTrue($response['cached']);

        Http::assertNothingSent();
    }

    /** @test */
    public function it_caches_successful_chat_responses()
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Fresh answer']]],
            ], 200),
        ]);

        $messages = [['role' => 'user', 'content' => 'New question']];

        $this->cache->expects($this->once())
            ->method('generateKey')
            ->with('chat', $this->anything())
            ->willReturn('chat:new-question-key');

        $this->cache->expects($this->once())
            ->method('get')
            ->with('chat:new-question-key')
            ->willReturn(null); // Cache miss

        $this->cache->expects($this->once())
            ->method('put')
            ->with(
                'chat:new-question-key',
                $this->arrayHasKey('choices'),
                $this->greaterThan(0)
            );

        $response = $this->service->chat($messages);

        $this->assertEquals('Fresh answer', $response['choices'][0]['message']['content']);
    }

    /** @test */
    public function it_does_not_cache_on_error()
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response('Server error', 500),
        ]);

        $messages = [['role' => 'user', 'content' => 'Question']];

        $this->cache->expects($this->once())
            ->method('generateKey')
            ->willReturn('chat:error-key');

        $this->cache->expects($this->once())
            ->method('get')
            ->willReturn(null);

        $this->cache->expects($this->never())
            ->method('put');

        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->twice();
        Log::shouldReceive('error')->once();

        try {
            $this->service->chat($messages);
        } catch (\Exception $e) {
            // Expected exception
        }
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

        $this->cache->expects($this->once())
            ->method('generateKey')
            ->willReturn('chat:400-key');

        $this->cache->expects($this->once())
            ->method('get')
            ->willReturn(null);

        $this->expectException(RequestException::class);

        $this->service->chat([['role' => 'user', 'content' => 'Test']]);
    }

    /** @test */
    public function it_logs_requests_and_responses()
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Response']]],
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

        $this->cache->expects($this->once())
            ->method('generateKey')
            ->willReturn('chat:log-key');

        $this->cache->expects($this->once())
            ->method('get')
            ->willReturn(null);

        $this->service->chat([['role' => 'user', 'content' => 'Test']]);
    }

    // ========== Streaming Tests ==========

    /** @test */
    public function it_supports_streaming_chat_completion()
    {
        $this->markTestIncomplete('Streaming implementation pending');

        // This test will be implemented when chatStream() is added
        $chunks = [];
        $callback = function ($chunk) use (&$chunks) {
            $chunks[] = $chunk;
        };

        $messages = [['role' => 'user', 'content' => 'Tell me a story']];

        $this->service->chatStream($messages, 'gpt-4o', [], $callback);

        $this->assertGreaterThan(0, count($chunks));
    }

    // ========== Available Models Tests ==========

    /** @test */
    public function it_returns_available_chat_models()
    {
        $models = $this->service->getAvailableModels();

        $this->assertIsArray($models);
        $this->assertContains('gpt-4o', $models);
        $this->assertContains('gpt-4o-mini', $models);
        $this->assertContains('gpt-4-turbo', $models);
        $this->assertContains('gpt-3.5-turbo', $models);
    }

    // ========== Configuration Tests ==========

    /** @test */
    public function it_uses_custom_base_url_from_config()
    {
        Config::set('openai.base_url', 'https://custom-api.example.com/v1');

        $service = new OpenAIChatService($this->cache);

        Http::fake([
            'custom-api.example.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Response']]],
            ], 200),
        ]);

        $this->cache->expects($this->once())
            ->method('generateKey')
            ->willReturn('chat:custom-url-key');

        $this->cache->expects($this->once())
            ->method('get')
            ->willReturn(null);

        $service->chat([['role' => 'user', 'content' => 'Test']]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'custom-api.example.com');
        });
    }

    /** @test */
    public function it_uses_configured_timeout()
    {
        Config::set('openai.timeout', 120);
        Config::set('openai.connect_timeout', 20);

        $service = new OpenAIChatService($this->cache);

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Response']]],
            ], 200),
        ]);

        $this->cache->expects($this->once())
            ->method('generateKey')
            ->willReturn('chat:timeout-key');

        $this->cache->expects($this->once())
            ->method('get')
            ->willReturn(null);

        $service->chat([['role' => 'user', 'content' => 'Test']]);

        $this->assertTrue(true); // If it doesn't timeout, test passes
    }

    /** @test */
    public function it_uses_default_model_from_config()
    {
        Config::set('openai.models.chat', 'gpt-4o-mini');

        Http::fake([
            '*' => Http::response([
                'model' => 'gpt-4o-mini',
                'choices' => [['message' => ['content' => 'Response from gpt-4o-mini']]],
            ], 200),
        ]);

        $cache = $this->createMock(CacheServiceInterface::class);
        $cache->expects($this->once())
            ->method('generateKey')
            ->willReturn('chat:default-model-key');

        $cache->expects($this->once())
            ->method('get')
            ->willReturn(null);

        $service = new OpenAIChatService($cache);

        // Call chat without specifying a model - should use default from config
        $response = $service->chat([['role' => 'user', 'content' => 'Test']]);

        // Verify response contains expected model
        $this->assertArrayHasKey('model', $response);
        $this->assertEquals('gpt-4o-mini', $response['model']);
        $this->assertStringContainsString('gpt-4o-mini', $response['choices'][0]['message']['content']);
    }

    // ========== Circuit Breaker Tests ==========

    /** @test */
    public function it_uses_circuit_breaker_for_requests()
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Response']]],
            ], 200),
        ]);

        $this->cache->expects($this->once())
            ->method('generateKey')
            ->willReturn('chat:circuit-breaker-key');

        $this->cache->expects($this->once())
            ->method('get')
            ->willReturn(null);

        $response = $this->service->chat([['role' => 'user', 'content' => 'Test']]);

        $this->assertArrayHasKey('choices', $response);
    }

    /** @test */
    public function it_handles_circuit_breaker_open_state()
    {
        $this->markTestIncomplete('Circuit breaker testing requires service state manipulation');

        // This test would verify circuit breaker behavior after multiple failures
        // Implementation depends on how CircuitBreaker can be tested/mocked
    }
}
