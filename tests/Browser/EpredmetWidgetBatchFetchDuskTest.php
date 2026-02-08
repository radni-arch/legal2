<?php

namespace Tests\Browser;

use App\Models\Court;
use Illuminate\Support\Facades\Queue;
use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\AuthenticatesUser;
use Tests\Browser\Concerns\MocksExternalApis;
use Tests\DuskTestCase;

/**
 * EpredmetWidget Batch Fetch Browser Tests
 *
 * Tests the batch fetch functionality of the E-Court (EKOM) widget,
 * verifying the UI interactions for starting batch fetch operations,
 * validation error display, and tab navigation.
 */
class EpredmetWidgetBatchFetchDuskTest extends DuskTestCase
{
    use AuthenticatesUser;
    use MocksExternalApis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockOpenAIApis();
        $this->mockGraphQLApi();
        Queue::fake();
    }

    /**
     * Test that batch fetch tab loads and displays courts dropdown
     */
    public function test_batch_fetch_tab_loads_courts(): void
    {
        // Create level 1 (municipal) courts
        Court::factory()->count(3)->create(['level' => 1]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/dashboard')
                ->waitFor('@epredmet-widget', 10)
                ->click('@epredmet-widget-toggle')
                ->waitFor('[dusk="tab-batch-fetch"]', 10)
                ->click('[dusk="tab-batch-fetch"]')
                ->waitFor('[dusk="batch-fetch-panel"]', 10)
                ->assertPresent('[dusk="batch-court-select"]')
                ->assertPresent('[dusk="batch-year-select"]')
                ->assertPresent('[dusk="batch-register-select"]')
                ->assertPresent('[dusk="start-batch-fetch"]');
        });
    }

    /**
     * Test that batch fetch can be started successfully with valid court selection
     */
    public function test_can_start_batch_fetch(): void
    {
        $court = Court::factory()->create([
            'external_id' => 5107,
            'level' => 1,
            'name' => 'Opcinski sud u Osijeku',
        ]);

        $this->browse(function (Browser $browser) use ($court) {
            $this->loginAs($browser);

            $browser->visit('/dashboard')
                ->waitFor('@epredmet-widget', 10)
                ->click('@epredmet-widget-toggle')
                ->waitFor('[dusk="tab-batch-fetch"]', 10)
                ->click('[dusk="tab-batch-fetch"]')
                ->waitFor('[dusk="batch-fetch-panel"]', 10)
                ->waitFor('[dusk="batch-court-select"]', 10)
                ->select('[dusk="batch-court-select"]', $court->id)
                ->click('[dusk="start-batch-fetch"]')
                ->waitFor('[dusk="batch-fetch-dispatched"]', 10)
                ->assertSee('Job dispatched');
        });
    }

    /**
     * Test that validation error shows when trying to start fetch without selecting court
     */
    public function test_validation_shows_error_when_no_court_selected(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/dashboard')
                ->waitFor('@epredmet-widget', 10)
                ->click('@epredmet-widget-toggle')
                ->waitFor('[dusk="tab-batch-fetch"]', 10)
                ->click('[dusk="tab-batch-fetch"]')
                ->waitFor('[dusk="batch-fetch-panel"]', 10)
                ->click('[dusk="start-batch-fetch"]')
                ->waitFor('[dusk="batch-court-error"]', 10)
                ->assertPresent('[dusk="batch-court-error"]');
        });
    }

    /**
     * Test that user can navigate between all three tabs
     */
    public function test_can_switch_between_all_tabs(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/dashboard')
                ->waitFor('@epredmet-widget', 10)
                ->click('@epredmet-widget-toggle')
                // Start at lookup tab (default)
                ->waitFor('[dusk="tab-lookup"]', 10)
                ->waitFor('[dusk="fetch-form"]', 10)
                ->assertPresent('[dusk="fetch-form"]')
                // Navigate to sync status tab
                ->click('[dusk="tab-sync-status"]')
                ->waitFor('[dusk="sync-status-panel"]', 10)
                ->assertPresent('[dusk="sync-status-panel"]')
                // Navigate to batch fetch tab
                ->click('[dusk="tab-batch-fetch"]')
                ->waitFor('[dusk="batch-fetch-panel"]', 10)
                ->assertPresent('[dusk="batch-fetch-panel"]')
                // Back to lookup tab
                ->click('[dusk="tab-lookup"]')
                ->waitFor('[dusk="fetch-form"]', 10)
                ->assertPresent('[dusk="fetch-form"]');
        });
    }

    /**
     * Test that courts table is displayed with selectable courts
     */
    public function test_courts_table_displays_with_actions(): void
    {
        $court = Court::factory()->create([
            'external_id' => 5107,
            'level' => 1,
            'name' => 'Opcinski sud u Osijeku',
        ]);

        $this->browse(function (Browser $browser) use ($court) {
            $this->loginAs($browser);

            $browser->visit('/dashboard')
                ->waitFor('@epredmet-widget', 10)
                ->click('@epredmet-widget-toggle')
                ->click('[dusk="tab-batch-fetch"]')
                ->waitFor('[dusk="batch-fetch-panel"]', 10)
                ->waitFor('[dusk="courts-table"]', 10)
                ->assertPresent('[dusk="courts-table"]')
                ->assertPresent('[dusk="court-0"]')
                ->assertPresent('[dusk="select-court-'.$court->id.'"]');
        });
    }

    /**
     * Test that quick actions buttons are present
     */
    public function test_quick_actions_present(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/dashboard')
                ->waitFor('@epredmet-widget', 10)
                ->click('@epredmet-widget-toggle')
                ->click('[dusk="tab-batch-fetch"]')
                ->waitFor('[dusk="batch-fetch-panel"]', 10)
                ->assertPresent('[dusk="fetch-all-pending"]')
                ->assertPresent('[dusk="retry-failed"]');
        });
    }

    /**
     * Test that clicking select button in courts table populates dropdown
     */
    public function test_select_court_from_table(): void
    {
        $court = Court::factory()->create([
            'external_id' => 5107,
            'level' => 1,
            'name' => 'Opcinski sud u Osijeku',
        ]);

        $this->browse(function (Browser $browser) use ($court) {
            $this->loginAs($browser);

            $browser->visit('/dashboard')
                ->waitFor('@epredmet-widget', 10)
                ->click('@epredmet-widget-toggle')
                ->click('[dusk="tab-batch-fetch"]')
                ->waitFor('[dusk="batch-fetch-panel"]', 10)
                ->waitFor('[dusk="select-court-'.$court->id.'"]', 10)
                ->click('[dusk="select-court-'.$court->id.'"]')
                ->pause(500) // Wait for Livewire to update
                ->assertSelected('[dusk="batch-court-select"]', $court->id);
        });
    }
}
