<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\LlmBrainPanel;
use App\Services\Graph\ReasoningChainService;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class LlmBrainPanelEdgeCasesTest extends TestCase
{
    /** @test */
    public function it_handles_unicode_queries()
    {
        $mock = Mockery::mock(ReasoningChainService::class);
        $mock->shouldReceive('executeReasoningChain')
            ->with(Mockery::on(fn($q) => str_contains($q, 'članu'))) // Croatian chars preserved
            ->andReturn(['success' => true, 'results' => [], 'cypher_query' => 'MATCH (n) RETURN n', 'explanation' => 'Test']);
        $this->app->instance(ReasoningChainService::class, $mock);

        Livewire::test(LlmBrainPanel::class)
            ->set('naturalQuery', 'Pronađi odluke o članu 5.')
            ->call('executeQuery')
            ->assertSet('error', null);
    }

    /** @test */
    public function it_handles_empty_results()
    {
        $mock = Mockery::mock(ReasoningChainService::class);
        $mock->shouldReceive('executeReasoningChain')
            ->andReturn(['success' => true, 'results' => [], 'cypher_query' => 'MATCH (n) RETURN n', 'explanation' => 'No results']);
        $this->app->instance(ReasoningChainService::class, $mock);

        Livewire::test(LlmBrainPanel::class)
            ->set('naturalQuery', 'Find nonexistent data')
            ->call('executeQuery')
            ->assertSet('queryResult', []);
    }

    /** @test */
    public function it_handles_very_large_result_sets()
    {
        $largeResults = array_fill(0, 1000, ['id' => 'test', 'name' => 'Test Decision']);

        $mock = Mockery::mock(ReasoningChainService::class);
        $mock->shouldReceive('executeReasoningChain')
            ->andReturn(['success' => true, 'results' => $largeResults, 'cypher_query' => 'MATCH (n) RETURN n', 'explanation' => 'Test']);
        $this->app->instance(ReasoningChainService::class, $mock);

        Livewire::test(LlmBrainPanel::class)
            ->set('naturalQuery', 'Find all decisions')
            ->call('executeQuery')
            ->assertSet('error', null);
    }

    /** @test */
    public function it_handles_special_characters_in_queries()
    {
        $mock = Mockery::mock(ReasoningChainService::class);
        $mock->shouldReceive('executeReasoningChain')
            ->andReturn(['success' => true, 'results' => [], 'cypher_query' => 'MATCH (n) RETURN n', 'explanation' => 'Test']);
        $this->app->instance(ReasoningChainService::class, $mock);

        // HTML chars stripped, & and quotes should be OK
        Livewire::test(LlmBrainPanel::class)
            ->set('naturalQuery', 'Find "quoted" & special chars')
            ->call('executeQuery')
            ->assertSet('error', null);
    }

    /** @test */
    public function it_handles_whitespace_only_queries()
    {
        Livewire::test(LlmBrainPanel::class)
            ->set('naturalQuery', '   ')
            ->call('executeQuery')
            ->assertSee('Please enter a query');
    }

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\RateLimiter::clear('llm-brain:' . request()->ip());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
