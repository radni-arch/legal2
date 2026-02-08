<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\LlmBrainPanel;
use App\Services\Graph\ReasoningChainService;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class LlmBrainPanelRateLimitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('llm-brain:127.0.0.1');
    }

    /** @test */
    public function it_rate_limits_query_execution_to_10_per_minute()
    {
        $mock = Mockery::mock(ReasoningChainService::class);
        $mock->shouldReceive('executeReasoningChain')
            ->andReturn(['success' => true, 'results' => [], 'cypher_query' => 'MATCH (n) RETURN n', 'explanation' => 'Test']);
        $this->app->instance(ReasoningChainService::class, $mock);

        // Execute 10 queries (should all succeed)
        for ($i = 0; $i < 10; $i++) {
            Livewire::test(LlmBrainPanel::class)
                ->set('naturalQuery', "Query $i")
                ->call('executeQuery')
                ->assertSet('error', null);
        }

        // 11th query should be rate limited
        Livewire::test(LlmBrainPanel::class)
            ->set('naturalQuery', 'Query 11')
            ->call('executeQuery')
            ->assertSet('error', 'Too many requests. Please wait before trying again.');
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('llm-brain:127.0.0.1');
        Mockery::close();
        parent::tearDown();
    }
}
