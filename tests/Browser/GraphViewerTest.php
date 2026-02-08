<?php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\AuthenticatesUser;
use Tests\Browser\Concerns\MocksExternalApis;
use Tests\DuskTestCase;

class GraphViewerTest extends DuskTestCase
{
    use AuthenticatesUser, MocksExternalApis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockAllExternalApis();
    }

    public function test_can_access_graph_viewer(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/graph')
                ->waitForText('Graph', 10)
                ->assertSee('Graph')
                ->assertSee('Search')
                ->assertSee('Node Type');
        });
    }

    public function test_can_search_legal_nodes(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/graph')
                ->waitForText('Graph', 10)

                // Enter search term
                ->type('@search-input', 'ZKP')
                ->pause(500)

                // Click search
                ->press('Search')
                ->waitForText('results', 10)

                // Verify results shown
                ->assertSee('ZKP');
        });
    }

    public function test_can_filter_by_node_type(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/graph')
                ->waitForText('Graph', 10)

                // Select node type
                ->select('@node-type-filter', 'LawDocument')
                ->pause(500)

                // Search
                ->type('@search-input', 'Kazneni')
                ->press('Search')
                ->waitForText('Law', 10)

                // Verify only law nodes shown
                ->assertSee('Law')
                ->assertSee('Kazneni');
        });
    }

    public function test_can_load_node_graph_visualization(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/graph')
                ->waitForText('Graph', 10)

                // Search for specific node
                ->type('@search-input', 'Pp-74/2025')
                ->press('Search')
                ->waitForText('Pp-74/2025', 10)

                // Click node to load graph
                ->press('@load-graph-button')
                ->waitFor('@graph-canvas', 15)

                // Verify graph visualization shown
                ->assertPresent('@graph-canvas')
                ->assertSee('relationships');
        });
    }

    public function test_can_switch_view_modes(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/graph')
                ->waitForText('Graph', 10)

                // Switch to table view
                ->select('@view-mode', 'table')
                ->pause(500)
                ->assertPresent('@table-view')

                // Switch to JSON view
                ->select('@view-mode', 'json')
                ->pause(500)
                ->assertPresent('@json-view')

                // Switch back to graph view
                ->select('@view-mode', 'graph')
                ->pause(500)
                ->assertPresent('@graph-canvas');
        });
    }

    public function test_can_view_graph_metrics(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/graph')
                ->waitForText('Graph', 10)

                // Click metrics button
                ->press('@toggle-metrics-button')
                ->waitFor('@metrics-panel', 5)

                // Verify metrics shown
                ->assertSee('PageRank')
                ->assertSee('Citation Clusters')
                ->assertSee('Network Statistics');
        });
    }

    public function test_can_view_influential_decisions(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/graph')
                ->waitForText('Graph', 10)

                // Toggle metrics
                ->press('@toggle-metrics-button')
                ->waitFor('@metrics-panel', 5)

                // Click influential decisions
                ->press('@load-influential-button')
                ->waitForText('Influential', 10)

                // Verify list shown
                ->assertSee('score')
                ->assertPresent('@influential-decisions-list');
        });
    }

    public function test_can_view_citation_clusters(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/graph')
                ->waitForText('Graph', 10)

                // Toggle metrics
                ->press('@toggle-metrics-button')
                ->waitFor('@metrics-panel', 5)

                // Load clusters
                ->press('@load-clusters-button')
                ->waitForText('Cluster', 10)

                // Click cluster to view
                ->press('@view-cluster-1')
                ->waitFor('@cluster-details', 5)

                // Verify cluster details shown
                ->assertSee('decisions')
                ->assertPresent('@cluster-graph');
        });
    }
}
