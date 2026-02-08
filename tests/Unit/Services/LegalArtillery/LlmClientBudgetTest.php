<?php

namespace Tests\Unit\Services\LegalArtillery;

use App\Services\LegalArtillery\LlmClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LlmClientBudgetTest extends TestCase
{
    public function test_set_budget_returns_self(): void
    {
        $client = new LlmClient(apiKey: 'test', model: 'claude-sonnet-4-20250514', maxTokens: 1024);
        $result = $client->setBudget(10000);
        $this->assertSame($client, $result);
    }

    public function test_initial_tokens_used_is_zero(): void
    {
        $client = new LlmClient(apiKey: 'test', model: 'claude-sonnet-4-20250514', maxTokens: 1024);
        $this->assertEquals(0, $client->getTokensUsed());
    }

    public function test_budget_not_exceeded_initially(): void
    {
        $client = new LlmClient(apiKey: 'test', model: 'claude-sonnet-4-20250514', maxTokens: 1024);
        $client->setBudget(10000);
        $this->assertFalse($client->isBudgetExceeded());
    }

    public function test_remaining_budget_calculated_correctly(): void
    {
        $client = new LlmClient(apiKey: 'test', model: 'claude-sonnet-4-20250514', maxTokens: 1024);
        $client->setBudget(10000);
        $this->assertEquals(10000, $client->getRemainingBudget());
    }

    public function test_remaining_budget_null_without_budget(): void
    {
        $client = new LlmClient(apiKey: 'test', model: 'claude-sonnet-4-20250514', maxTokens: 1024);
        $this->assertNull($client->getRemainingBudget());
    }

    public function test_tracks_tokens_from_api_response(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'Response']],
                'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
            ], 200),
        ]);

        $client = new LlmClient(apiKey: 'test', model: 'claude-sonnet-4-20250514', maxTokens: 1024);
        $client->generate('System', 'User');

        $this->assertEquals(150, $client->getTokensUsed());
    }

    public function test_accumulates_tokens_across_calls(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'Response']],
                'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
            ], 200),
        ]);

        $client = new LlmClient(apiKey: 'test', model: 'claude-sonnet-4-20250514', maxTokens: 1024);
        $client->generate('System', 'User');
        $client->generate('System', 'User');

        $this->assertEquals(300, $client->getTokensUsed());
    }

    public function test_throws_when_budget_exceeded(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'Response']],
                'usage' => ['input_tokens' => 5000, 'output_tokens' => 5001],
            ], 200),
        ]);

        $client = new LlmClient(apiKey: 'test', model: 'claude-sonnet-4-20250514', maxTokens: 1024);
        $client->setBudget(10000);

        // First call uses 10001 tokens (exceeds budget)
        $client->generate('System', 'User');

        $this->assertTrue($client->isBudgetExceeded());

        // Second call should throw
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Token budget exceeded');
        $client->generate('System', 'Another');
    }

    public function test_reset_clears_token_count(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'Response']],
                'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
            ], 200),
        ]);

        $client = new LlmClient(apiKey: 'test', model: 'claude-sonnet-4-20250514', maxTokens: 1024);
        $client->generate('System', 'User');
        $this->assertEquals(150, $client->getTokensUsed());

        $client->resetTokenCount();
        $this->assertEquals(0, $client->getTokensUsed());
    }
}
