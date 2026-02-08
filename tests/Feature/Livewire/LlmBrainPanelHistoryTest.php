<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\LlmBrainPanel;
use App\Services\Graph\ReasoningChainService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LlmBrainPanelHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear rate limiter to prevent interference with tests
        \Illuminate\Support\Facades\RateLimiter::clear('llm-brain:127.0.0.1');

        // Mock ReasoningChainService to return successful results
        $this->mock(ReasoningChainService::class, function ($mock) {
            $mock->shouldReceive('executeReasoningChain')
                ->andReturn([
                    'success' => true,
                    'results' => [
                        ['id' => 1, 'title' => 'Result 1'],
                        ['id' => 2, 'title' => 'Result 2'],
                    ],
                    'cypher_query' => 'MATCH (n) RETURN n',
                    'explanation' => 'Test explanation',
                ]);
        });
    }

    protected function tearDown(): void
    {
        // Clean up rate limiter after each test
        \Illuminate\Support\Facades\RateLimiter::clear('llm-brain:127.0.0.1');

        parent::tearDown();
    }

    /** @test */
    public function it_stores_query_history()
    {
        $component = Livewire::test(LlmBrainPanel::class);

        // Execute first query
        $component->set('naturalQuery', 'Find Supreme Court decisions')
            ->call('executeQuery');

        // Execute second query
        $component->set('naturalQuery', 'Find contradicting decisions')
            ->call('executeQuery');

        // Verify history has 2 items, most recent first
        $component->assertSet('queryHistory', function ($history) {
            return count($history) === 2
                && $history[0]['query'] === 'Find contradicting decisions'
                && $history[1]['query'] === 'Find Supreme Court decisions'
                && isset($history[0]['timestamp'])
                && isset($history[0]['result_count'])
                && $history[0]['result_count'] === 2
                && $history[1]['result_count'] === 2;
        });
    }

    /** @test */
    public function it_limits_history_to_10_items()
    {
        $component = Livewire::test(LlmBrainPanel::class);

        // Execute 12 queries
        for ($i = 1; $i <= 12; $i++) {
            // Clear rate limiter before each query to prevent blocking
            \Illuminate\Support\Facades\RateLimiter::clear('llm-brain:127.0.0.1');

            $component->set('naturalQuery', "Query number $i")
                ->call('executeQuery');
        }

        // Verify only 10 items kept, most recent first
        $component->assertSet('queryHistory', function ($history) {
            if (count($history) !== 10) {
                return false;
            }

            // Most recent should be "Query number 12"
            if ($history[0]['query'] !== 'Query number 12') {
                return false;
            }

            // Oldest should be "Query number 3" (12, 11, 10, 9, 8, 7, 6, 5, 4, 3)
            if ($history[9]['query'] !== 'Query number 3') {
                return false;
            }

            return true;
        });
    }

    /** @test */
    public function it_can_rerun_query_from_history()
    {
        $component = Livewire::test(LlmBrainPanel::class);

        // Execute first query
        $component->set('naturalQuery', 'Original query text')
            ->call('executeQuery');

        // Change the query input
        $component->set('naturalQuery', 'Different text');

        // Rerun from history (index 0 = most recent)
        $component->call('rerunFromHistory', 0);

        // Verify the query was restored and executed
        $component->assertSet('naturalQuery', 'Original query text');

        // Verify queryResult is populated (meaning executeQuery was called)
        $component->assertSet('queryResult', function ($result) {
            return is_array($result) && count($result) === 2;
        });

        // Verify generatedCypher is populated
        $component->assertSet('generatedCypher', 'MATCH (n) RETURN n');
    }
}
