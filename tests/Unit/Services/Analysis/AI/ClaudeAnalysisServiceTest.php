<?php

namespace Tests\Unit\Services\Analysis\AI;

use App\Services\Analysis\AI\ClaudeAnalysisService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ClaudeAnalysisServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.claude.api_key', 'test-api-key');
        Config::set('services.claude.model', 'claude-sonnet-4-5-20250929');
        Config::set('services.claude.max_tokens', 4096);
    }

    /** @test */
    public function it_can_be_instantiated(): void
    {
        $service = new ClaudeAnalysisService();

        $this->assertInstanceOf(ClaudeAnalysisService::class, $service);
    }

    /** @test */
    public function analyze_sends_correct_request_to_claude_api(): void
    {
        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => 'Analysis result text']
                ],
                'usage' => [
                    'input_tokens' => 100,
                    'output_tokens' => 50,
                ],
            ], 200),
        ]);

        $service = new ClaudeAnalysisService();

        $result = $service->analyze(
            'You are a legal analyst.',
            'Analyze this document text.'
        );

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.anthropic.com/v1/messages'
                && $request->header('x-api-key')[0] === 'test-api-key'
                && $request->header('anthropic-version')[0] === '2023-06-01'
                && $request['model'] === 'claude-sonnet-4-5-20250929'
                && $request['max_tokens'] === 4096
                && $request['system'] === 'You are a legal analyst.'
                && $request['messages'][0]['role'] === 'user'
                && $request['messages'][0]['content'] === 'Analyze this document text.';
        });
    }

    /** @test */
    public function analyze_returns_content_usage_cost_and_metadata(): void
    {
        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => 'Analysis result']
                ],
                'usage' => [
                    'input_tokens' => 1000,
                    'output_tokens' => 500,
                ],
            ], 200),
        ]);

        $service = new ClaudeAnalysisService();

        $result = $service->analyze('System prompt', 'User content');

        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('usage', $result);
        $this->assertArrayHasKey('cost', $result);
        $this->assertArrayHasKey('processing_time', $result);
        $this->assertArrayHasKey('model', $result);

        $this->assertEquals('Analysis result', $result['content']);
        $this->assertEquals(1000, $result['usage']['input_tokens']);
        $this->assertEquals(500, $result['usage']['output_tokens']);
        $this->assertEquals('claude-sonnet-4-5-20250929', $result['model']);
    }

    /** @test */
    public function analyze_calculates_cost_correctly_for_sonnet(): void
    {
        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => 'Response']
                ],
                'usage' => [
                    'input_tokens' => 1_000_000, // 1M tokens
                    'output_tokens' => 1_000_000, // 1M tokens
                ],
            ], 200),
        ]);

        $service = new ClaudeAnalysisService();
        $result = $service->analyze('System', 'User');

        // Sonnet pricing: $3/1M input, $15/1M output
        $expectedCost = 3.0 + 15.0; // $18.00 total

        $this->assertEquals($expectedCost, $result['cost']);
    }

    /** @test */
    public function analyze_throws_exception_on_api_error(): void
    {
        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response([
                'error' => ['message' => 'Rate limit exceeded'],
            ], 429),
        ]);

        Log::shouldReceive('error')->once();

        $service = new ClaudeAnalysisService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Claude API error: 429');

        $service->analyze('System', 'User');
    }

    /** @test */
    public function analyze_concatenates_multiple_text_content_blocks(): void
    {
        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => 'First part.'],
                    ['type' => 'text', 'text' => 'Second part.'],
                ],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
            ], 200),
        ]);

        $service = new ClaudeAnalysisService();
        $result = $service->analyze('System', 'User');

        $this->assertEquals("First part.\nSecond part.", $result['content']);
    }

    /** @test */
    public function analyzeJson_appends_json_instruction_to_system_prompt(): void
    {
        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => '{"key": "value"}']
                ],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
            ], 200),
        ]);

        $service = new ClaudeAnalysisService();
        $service->analyzeJson('Original system prompt', 'User content');

        Http::assertSent(function ($request) {
            return str_contains($request['system'], 'Original system prompt')
                && str_contains($request['system'], 'IMPORTANT: Respond ONLY with valid JSON');
        });
    }

    /** @test */
    public function analyzeJson_parses_valid_json_response(): void
    {
        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => '{"events": [{"date": "2024-01-15", "description": "Filing"}]}']
                ],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
            ], 200),
        ]);

        $service = new ClaudeAnalysisService();
        $result = $service->analyzeJson('System', 'User');

        $this->assertArrayHasKey('parsed', $result);
        $this->assertEquals([
            'events' => [['date' => '2024-01-15', 'description' => 'Filing']]
        ], $result['parsed']);
    }

    /** @test */
    public function analyzeJson_strips_markdown_fences_from_response(): void
    {
        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => "```json\n{\"key\": \"value\"}\n```"]
                ],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
            ], 200),
        ]);

        $service = new ClaudeAnalysisService();
        $result = $service->analyzeJson('System', 'User');

        $this->assertEquals(['key' => 'value'], $result['parsed']);
    }

    /** @test */
    public function analyzeJson_throws_exception_on_invalid_json(): void
    {
        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => 'This is not JSON at all']
                ],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
            ], 200),
        ]);

        $service = new ClaudeAnalysisService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to parse Claude JSON response');

        $service->analyzeJson('System', 'User');
    }

    /** @test */
    public function it_uses_configured_model(): void
    {
        Config::set('services.claude.model', 'claude-opus-4-20250514');

        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response([
                'content' => [['type' => 'text', 'text' => 'Response']],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
            ], 200),
        ]);

        $service = new ClaudeAnalysisService();
        $result = $service->analyze('System', 'User');

        Http::assertSent(function ($request) {
            return $request['model'] === 'claude-opus-4-20250514';
        });

        $this->assertEquals('claude-opus-4-20250514', $result['model']);
    }

    /** @test */
    public function it_uses_configured_max_tokens(): void
    {
        Config::set('services.claude.max_tokens', 8192);

        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response([
                'content' => [['type' => 'text', 'text' => 'Response']],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
            ], 200),
        ]);

        $service = new ClaudeAnalysisService();
        $service->analyze('System', 'User');

        Http::assertSent(function ($request) {
            return $request['max_tokens'] === 8192;
        });
    }
}
