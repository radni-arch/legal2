<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\AuthenticatesUser;
use Tests\Browser\Concerns\MocksExternalApis;
use Tests\DuskTestCase;

/**
 * Dusk integration tests for D3 chart rendering in the Epredmet analytics panel.
 *
 * Verifies that D3 charts render on the analytics tab, re-render on filter
 * changes, and survive tab switching without losing state.
 */
class EpredmetChartsTest extends DuskTestCase
{
    use AuthenticatesUser;
    use MocksExternalApis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockOpenAIApis();
    }

    /**
     * Test charts render on analytics tab.
     */
    public function test_analytics_charts_render(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/dashboard')
                ->waitFor('@epredmet-widget', 10)
                ->click('@epredmet-widget-toggle')
                ->waitFor('@tab-analytics')
                ->click('@tab-analytics')
                ->waitFor('@analytics-panel')
                ->click('@refresh-analytics')
                ->waitFor('#chart-yearly-trend svg', 15)
                ->assertPresent('#chart-yearly-trend svg')
                ->assertPresent('#chart-same-day-donut svg')
                ->assertPresent('#chart-court-bar svg');
        });
    }

    /**
     * Test charts re-render on year filter change.
     */
    public function test_charts_rerender_on_filter_change(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/dashboard')
                ->waitFor('@epredmet-widget', 10)
                ->click('@epredmet-widget-toggle')
                ->waitFor('@tab-analytics')
                ->click('@tab-analytics')
                ->waitFor('@analytics-panel')
                ->click('@refresh-analytics')
                ->waitFor('#chart-yearly-trend svg', 15)
                ->select('@analytics-year', '2024')
                ->waitFor('#chart-yearly-trend svg', 15)
                ->assertPresent('#chart-yearly-trend svg');
        });
    }

    /**
     * Test charts survive tab switching.
     */
    public function test_charts_survive_tab_switch(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/dashboard')
                ->waitFor('@epredmet-widget', 10)
                ->click('@epredmet-widget-toggle')
                ->waitFor('@tab-analytics')
                ->click('@tab-analytics')
                ->waitFor('@analytics-panel')
                ->click('@refresh-analytics')
                ->waitFor('#chart-yearly-trend svg', 15)
                ->click('@tab-lookup')
                ->pause(500)
                ->click('@tab-analytics')
                ->waitFor('#chart-yearly-trend svg', 10)
                ->assertPresent('#chart-yearly-trend svg');
        });
    }
}
