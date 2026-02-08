<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\GraphViewer;
use App\Services\DecisionCitationService;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class CitationAnalysisComponentTest extends TestCase
{
    use UsesTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock OpenAI for offline testing
        Http::fake([
            'api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => '{}']]]], 200),
        ]);
    }

    public function test_citation_analysis_button_only_shows_for_court_decisions()
    {
        $decision = \App\Models\CourtDecisionDocument::factory()->create();

        Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'CourtDecisionDocument')
            ->set('selectedNodeId', $decision->id)
            ->assertSee('Citation Analysis'); // Button should be visible
    }

    public function test_citation_analysis_button_hidden_for_other_node_types()
    {
        Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'Law')
            ->set('selectedNodeId', 'some-law-id')
            ->assertDontSee('Citation Analysis'); // Button should be hidden
    }

    public function test_open_citation_analysis_sets_panel_visible()
    {
        $decision = \App\Models\CourtDecisionDocument::factory()->create();

        Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'CourtDecisionDocument')
            ->set('selectedNodeId', $decision->id)
            ->call('openCitationAnalysis')
            ->assertSet('showCitationAnalysis', true);
    }

    public function test_close_citation_analysis_hides_panel()
    {
        Livewire::test(GraphViewer::class)
            ->set('showCitationAnalysis', true)
            ->call('closeCitationAnalysis')
            ->assertSet('showCitationAnalysis', false)
            ->assertSet('citationAnalysisResults', null);
    }

    public function test_run_citation_analysis_calls_service()
    {
        $decision = \App\Models\CourtDecisionDocument::factory()->create();

        // Mock the service
        $this->mock(DecisionCitationService::class, function ($mock) {
            $mock->shouldReceive('analyzeCitations')
                ->once()
                ->andReturn([
                    'nodes' => [],
                    'edges' => [],
                    'root_decision' => 'test-id',
                ]);
        });

        Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'CourtDecisionDocument')
            ->set('selectedNodeId', $decision->id)
            ->set('citationOperation', 'graph')
            ->call('runCitationAnalysis')
            ->assertSet('loading', false)
            ->assertNotNull('citationAnalysisResults');
    }

    public function test_citation_analysis_operation_selector_changes()
    {
        Livewire::test(GraphViewer::class)
            ->set('citationOperation', 'graph')
            ->assertSet('citationOperation', 'graph')
            ->set('citationOperation', 'authority')
            ->assertSet('citationOperation', 'authority')
            ->set('citationOperation', 'patterns')
            ->assertSet('citationOperation', 'patterns')
            ->set('citationOperation', 'influence')
            ->assertSet('citationOperation', 'influence');
    }

    public function test_citation_analysis_handles_errors_gracefully()
    {
        $decision = \App\Models\CourtDecisionDocument::factory()->create();

        // Mock service to throw exception
        $this->mock(DecisionCitationService::class, function ($mock) {
            $mock->shouldReceive('analyzeCitations')
                ->andThrow(new \Exception('Test error'));
        });

        Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'CourtDecisionDocument')
            ->set('selectedNodeId', $decision->id)
            ->call('runCitationAnalysis')
            ->assertSet('loading', false)
            ->assertNotNull('error');
    }
}
