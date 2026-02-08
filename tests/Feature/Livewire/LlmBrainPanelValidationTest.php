<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\LlmBrainPanel;
use App\Services\Graph\ReasoningChainService;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class LlmBrainPanelValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Clear rate limiter state between tests
        RateLimiter::clear('llm-brain:127.0.0.1');
    }

    /** @test */
    public function it_rejects_queries_exceeding_max_length()
    {
        $longQuery = str_repeat('a', 2001); // 2001 chars, max is 2000

        Livewire::test(LlmBrainPanel::class)
            ->set('naturalQuery', $longQuery)
            ->call('executeQuery')
            ->assertSet('error', 'Query must not exceed 2000 characters.');
    }

    /** @test */
    public function it_accepts_queries_at_max_length()
    {
        $mock = Mockery::mock(ReasoningChainService::class);
        $mock->shouldReceive('executeReasoningChain')
            ->andReturn(['success' => true, 'results' => [], 'cypher_query' => 'MATCH (n) RETURN n', 'explanation' => 'Test']);
        $this->app->instance(ReasoningChainService::class, $mock);

        $maxQuery = str_repeat('a', 2000); // Exactly 2000 chars

        Livewire::test(LlmBrainPanel::class)
            ->set('naturalQuery', $maxQuery)
            ->call('executeQuery')
            ->assertSet('error', null);
    }

    /** @test */
    public function it_strips_html_tags_from_queries()
    {
        $mock = Mockery::mock(ReasoningChainService::class);
        $mock->shouldReceive('executeReasoningChain')
            ->once()
            ->with(Mockery::on(fn($q) =>
                !str_contains($q, '<script>') &&
                !str_contains($q, '</script>') &&
                str_contains($q, 'Find') &&
                str_contains($q, 'decisions')
            ))
            ->andReturn(['success' => true, 'results' => [], 'cypher_query' => 'MATCH (n) RETURN n', 'explanation' => 'Test']);
        $this->app->instance(ReasoningChainService::class, $mock);

        Livewire::test(LlmBrainPanel::class)
            ->set('naturalQuery', 'Find <script>alert("xss")</script> decisions')
            ->call('executeQuery')
            ->assertSet('error', null);
    }

    /** @test */
    public function it_decodes_and_strips_html_entity_encoded_tags()
    {
        $mock = Mockery::mock(ReasoningChainService::class);
        $mock->shouldReceive('executeReasoningChain')
            ->once()
            ->with(Mockery::on(fn($q) =>
                !str_contains($q, '<script>') &&
                !str_contains($q, 'script') &&
                !str_contains($q, '&lt;') &&
                !str_contains($q, '&gt;') &&
                str_contains($q, 'Find') &&
                str_contains($q, 'decisions')
            ))
            ->andReturn(['success' => true, 'results' => [], 'cypher_query' => 'MATCH (n) RETURN n', 'explanation' => 'Test']);
        $this->app->instance(ReasoningChainService::class, $mock);

        Livewire::test(LlmBrainPanel::class)
            ->set('naturalQuery', 'Find &lt;script&gt;alert("xss")&lt;/script&gt; decisions')
            ->call('executeQuery')
            ->assertSet('error', null);
    }

    /** @test */
    public function it_removes_dangerous_cypher_injection_patterns()
    {
        $mock = Mockery::mock(ReasoningChainService::class);
        $mock->shouldReceive('executeReasoningChain')
            ->once()
            ->with(Mockery::on(fn($q) =>
                !str_contains(strtoupper($q), 'DELETE') &&
                !str_contains(strtoupper($q), 'DETACH') &&
                !str_contains(strtoupper($q), 'DROP') &&
                !str_contains(strtoupper($q), 'CREATE') &&
                str_contains($q, 'Find') &&
                str_contains($q, 'all nodes')
            ))
            ->andReturn(['success' => true, 'results' => [], 'cypher_query' => 'MATCH (n) RETURN n', 'explanation' => 'Test']);
        $this->app->instance(ReasoningChainService::class, $mock);

        Livewire::test(LlmBrainPanel::class)
            ->set('naturalQuery', 'Find all nodes MATCH (n) DELETE n')
            ->call('executeQuery')
            ->assertSet('error', null);
    }

    /** @test */
    public function it_enforces_rate_limiting()
    {
        $mock = Mockery::mock(ReasoningChainService::class);
        // Should only be called 10 times due to rate limiting
        $mock->shouldReceive('executeReasoningChain')
            ->times(10)
            ->andReturn(['success' => true, 'results' => [], 'cypher_query' => 'MATCH (n) RETURN n', 'explanation' => 'Test']);
        $this->app->instance(ReasoningChainService::class, $mock);

        $component = Livewire::test(LlmBrainPanel::class);

        // First 10 requests should succeed
        for ($i = 0; $i < 10; $i++) {
            $component->set('naturalQuery', "Query $i")
                ->call('executeQuery')
                ->assertSet('error', null);
        }

        // 11th request should be rate limited
        $component->set('naturalQuery', 'Query 11')
            ->call('executeQuery')
            ->assertSet('error', 'Too many requests. Please wait before trying again.');
    }

    /** @test */
    public function it_shows_generic_error_messages_to_users()
    {
        $mock = Mockery::mock(ReasoningChainService::class);
        $mock->shouldReceive('executeReasoningChain')
            ->once()
            ->andThrow(new \Exception('Sensitive internal error: database connection string exposed'));
        $this->app->instance(ReasoningChainService::class, $mock);

        Livewire::test(LlmBrainPanel::class)
            ->set('naturalQuery', 'Find decisions')
            ->call('executeQuery')
            ->assertSet('error', 'An error occurred while processing your query. Please try again.');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
