<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\GraphDashboard;
use Livewire\Livewire;
use Tests\TestCase;

class GraphDashboardTest extends TestCase
{
    /**
     * Test 1: Component renders correctly with initial state
     *
     * @test
     */
    public function test_component_renders_correctly()
    {
        Livewire::test(GraphDashboard::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.graph-dashboard')
            ->assertSet('activePanel', 'explorer')
            ->assertSet('panels', [
                'explorer' => 'Explorer',
                'llm_brain' => 'LLM Brain',
                'analytics' => 'Analytics',
                'temporal' => 'Temporal',
                'admin' => 'Admin',
            ]);
    }

    /**
     * Test 2: Panel switching works
     *
     * @test
     */
    public function test_panel_switching_works()
    {
        Livewire::test(GraphDashboard::class)
            ->assertSet('activePanel', 'explorer')
            ->call('switchPanel', 'llm_brain')
            ->assertSet('activePanel', 'llm_brain')
            ->call('switchPanel', 'analytics')
            ->assertSet('activePanel', 'analytics')
            ->call('switchPanel', 'temporal')
            ->assertSet('activePanel', 'temporal')
            ->call('switchPanel', 'admin')
            ->assertSet('activePanel', 'admin')
            ->call('switchPanel', 'explorer')
            ->assertSet('activePanel', 'explorer');
    }

    /**
     * Test 3: Invalid panel name defaults to explorer
     *
     * @test
     */
    public function test_invalid_panel_defaults_to_explorer()
    {
        Livewire::test(GraphDashboard::class)
            ->call('switchPanel', 'invalid_panel')
            ->assertSet('activePanel', 'explorer');
    }

    /**
     * Test 4: Component exposes panel configuration
     *
     * @test
     */
    public function test_component_exposes_panel_configuration()
    {
        $component = Livewire::test(GraphDashboard::class);

        $panels = $component->get('panels');

        $this->assertIsArray($panels);
        $this->assertArrayHasKey('explorer', $panels);
        $this->assertArrayHasKey('llm_brain', $panels);
        $this->assertArrayHasKey('analytics', $panels);
        $this->assertArrayHasKey('temporal', $panels);
        $this->assertArrayHasKey('admin', $panels);
    }

    /**
     * Test 5: Explorer panel is active by default
     *
     * @test
     */
    public function test_explorer_panel_is_active_by_default()
    {
        Livewire::test(GraphDashboard::class)
            ->assertSet('activePanel', 'explorer');
    }
}
