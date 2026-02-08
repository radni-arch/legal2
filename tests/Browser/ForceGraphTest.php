<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ForceGraphTest extends DuskTestCase
{
    /** @test */
    public function it_renders_force_graph_component(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/graph/explore')
                ->waitFor('[x-data*="ForceGraph"]', 5)
                ->assertPresent('svg')
                ->assertPresent('.graph-group');
        });
    }

    /** @test */
    public function it_displays_zoom_controls(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/graph/explore')
                ->waitFor('[x-data*="ForceGraph"]', 5)
                ->assertPresent('button[title="Zoom In"]')
                ->assertPresent('button[title="Zoom Out"]')
                ->assertPresent('button[title="Fit to View"]');
        });
    }

    /** @test */
    public function it_shows_node_type_filters(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/graph/explore')
                ->waitFor('[x-data*="ForceGraph"]', 5)
                ->assertSee('Node Types')
                ->assertPresent('input[type="checkbox"]');
        });
    }

    /** @test */
    public function it_has_search_input(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/graph/explore')
                ->waitFor('[x-data*="ForceGraph"]', 5)
                ->assertPresent('input[placeholder="Search nodes..."]');
        });
    }

    /** @test */
    public function it_shows_instructions(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/graph/explore')
                ->waitFor('[x-data*="ForceGraph"]', 5)
                ->assertSee('Click')
                ->assertSee('Double-click')
                ->assertSee('Drag')
                ->assertSee('Scroll');
        });
    }
}
