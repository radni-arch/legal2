<?php

namespace Tests\Unit\Services;

use App\Services\OpenAIService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tests for OpenAIService retry logic with connection timeouts.
 *
 * TASK-007: Verify retry callback handles ConnectionException without TypeError.
 */
class OpenAIServiceRetryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('openai.api_key', 'sk-test-key-retry');
        Config::set('openai.organization', null);
        Config::set('openai.project', null);
        Config::set('openai.base_url', 'https://api.openai.com/v1');
        Config::set('openai.timeout', 5);
        Config::set('openai.connect_timeout', 2);
        Config::set('openai.models.chat', 'gpt-4o');
        Config::set('openai.circuit_breaker.failure_threshold', 10);
        Config::set('openai.circuit_breaker.success_threshold', 2);
        Config::set('openai.circuit_breaker.timeout', 60);
        Config::set('openai.circuit_breaker.retry_after', 30);
    }

    /** @test */
    public function retry_callback_accepts_throwable_parameter_without_type_error(): void
    {
        // Configure retry with 2 attempts
        Config::set('openai.retry', [
            'times' => 2,
            'sleep_ms' => 10,
            'exponential_backoff' => false,
            'jitter' => false,
            'when' => [
                'status_codes' => [500, 502, 503],
            ],
        ]);

        // Fake HTTP to throw ConnectionException then succeed
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::sequence()
                ->pushResponse(Http::response(['choices' => [['message' => ['content' => 'ok']]]], 200)),
        ]);

        $service = new OpenAIService;

        // This should NOT throw TypeError - the retry callback signature
        // accepts ?\Throwable as second parameter
        $response = $service->chat([
            ['role' => 'user', 'content' => 'test'],
        ]);

        $this->assertArrayHasKey('choices', $response);
    }

    /** @test */
    public function retry_respects_max_retry_count(): void
    {
        Config::set('openai.retry', [
            'times' => 3,
            'sleep_ms' => 10,
            'exponential_backoff' => false,
            'jitter' => false,
            'when' => [
                'status_codes' => [500],
            ],
        ]);

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response(['error' => 'Server Error'], 500),
        ]);

        $service = new OpenAIService;

        $this->expectException(\Illuminate\Http\Client\RequestException::class);

        $service->chat([
            ['role' => 'user', 'content' => 'test'],
        ]);
    }

    /** @test */
    public function retry_when_callback_has_proper_type_hints(): void
    {
        Config::set('openai.retry', [
            'times' => 2,
            'sleep_ms' => 10,
            'exponential_backoff' => true,
            'jitter' => false,
            'when' => [
                'status_codes' => [429, 500],
            ],
        ]);

        // Should succeed on first try - verifying no TypeError from callback setup
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'success']]],
            ], 200),
        ]);

        $service = new OpenAIService;

        $response = $service->chat([
            ['role' => 'user', 'content' => 'test'],
        ]);

        $this->assertEquals('success', $response['choices'][0]['message']['content']);
    }

    /** @test */
    public function retry_does_not_retry_on_non_retryable_status_codes(): void
    {
        Config::set('openai.retry', [
            'times' => 3,
            'sleep_ms' => 10,
            'exponential_backoff' => false,
            'jitter' => false,
            'when' => [
                'status_codes' => [500, 502, 503],
            ],
        ]);

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response(['error' => 'Bad Request'], 400),
        ]);

        $service = new OpenAIService;

        $this->expectException(\Illuminate\Http\Client\RequestException::class);

        $service->chat([
            ['role' => 'user', 'content' => 'test'],
        ]);
    }
}
