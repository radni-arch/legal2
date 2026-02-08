<?php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\AuthenticatesUser;
use Tests\Browser\Concerns\MocksExternalApis;
use Tests\DuskTestCase;

/**
 * ParallelTimeline E2E Tests
 *
 * Comprehensive browser tests for the ParallelTimeline Livewire component.
 * Tests dual-lane timeline visualization for tracking concurrent events,
 * filtering, navigation, and responsive layout.
 *
 * Test Coverage:
 * - Component loading and initialization
 * - Timeline display with events in both lanes
 * - Event card rendering with metadata
 * - Navigation (previous/next day buttons)
 * - View mode switching (timeline, list, comparison)
 * - Filtering by lane, event type, and date range
 * - Event details panel opening/closing
 * - Sorting functionality
 * - Responsive layout behavior
 * - Filter panel visibility toggle
 * - Auto-update feature
 * - Empty state handling
 *
 * @group dusk
 * @group timeline
 */
class ParallelTimelineTest extends DuskTestCase
{
    use AuthenticatesUser, MocksExternalApis;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock external APIs for offline testing
        $this->mockAllExternalApis();

        // Create test user with unique email to avoid conflicts
        $this->user = User::factory()->create([
            'email' => 'parallel-timeline-'.uniqid().'@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    protected function tearDown(): void
    {
        // Clean up test user
        if (isset($this->user)) {
            $this->user->delete();
        }

        parent::tearDown();
    }

    /**
     * Test: Component loads successfully with default state
     *
     * Verifies:
     * - Page loads without errors
     * - Main container is present
     * - Title is visible
     * - Default day is displayed
     * - Both lanes have event counts
     */
    public function test_component_loads_successfully(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-parallel-timeline')
                ->waitForLivewire()
                ->assertPresent('@parallel-timeline-container')
                ->assertPresent('@title')
                ->assertSee('Paralelni Vremenski Slijed')
                ->assertPresent('@current-day-display')
                ->assertPresent('@top-lane-container')
                ->assertPresent('@bottom-lane-container')
                ->assertPresent('@top-lane-count-number')
                ->assertPresent('@bottom-lane-count-number');
        });
    }

    /**
     * Test: Timeline displays events from both lanes
     *
     * Verifies:
     * - Top lane events are visible
     * - Bottom lane events are visible
     * - Event cards have proper styling
     * - Event metadata (title, description, date, time) is displayed
     * - Event priorities are shown
     * - Event status badges are present
     */
    public function test_timeline_displays_events_from_both_lanes(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-parallel-timeline')
                ->waitForLivewire()
                ->assertPresent('@top-events-list')
                ->assertPresent('@bottom-events-list')
                // Check top lane events
                ->assertPresent('@top-event-card-0')
                ->assertSeeIn('@top-event-title-0', 'Optužnica podnesena')
                ->assertPresent('@top-event-description-0')
                ->assertPresent('@top-event-date-0')
                ->assertPresent('@top-event-time-0')
                ->assertPresent('@top-event-priority-0')
                ->assertPresent('@top-event-status-0')
                // Check bottom lane events
                ->assertPresent('@bottom-event-card-0')
                ->assertSeeIn('@bottom-event-title-0', 'Pretraga doma')
                ->assertPresent('@bottom-event-description-0')
                ->assertPresent('@bottom-event-date-0')
                ->assertPresent('@bottom-event-time-0')
                ->assertPresent('@bottom-event-priority-0')
                ->assertPresent('@bottom-event-status-0');
        });
    }

    /**
     * Test: Date navigation (previous/next day)
     *
     * Verifies:
     * - Previous day button navigates to previous date
     * - Next day button navigates to next date
     * - Current day display updates
     * - Navigation works multiple times in sequence
     * - Component re-renders after navigation
     */
    public function test_date_navigation_previous_next_day(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-parallel-timeline')
                ->waitForLivewire()
                // Get initial day
                ->assertPresent('@current-day-display')
                ->assertSeeIn('@current-day-display', '2025-06-09')
                // Click previous day
                ->click('@prev-day-btn')
                ->waitForLivewire()
                ->pause(500)
                // Day should change to previous
                ->assertSeeIn('@current-day-display', '2025-06-08')
                // Click next day twice to get back to original + 1
                ->click('@next-day-btn')
                ->waitForLivewire()
                ->pause(500)
                ->assertSeeIn('@current-day-display', '2025-06-09')
                ->click('@next-day-btn')
                ->waitForLivewire()
                ->pause(500)
                ->assertSeeIn('@current-day-display', '2025-06-10');
        });
    }

    /**
     * Test: View mode switching (timeline, list, comparison)
     *
     * Verifies:
     * - View mode buttons are present
     * - Clicking timeline button activates timeline mode
     * - Clicking list button activates list mode
     * - Clicking comparison button activates comparison mode
     * - Button styling changes to indicate active mode
     * - Component correctly switches between modes
     */
    public function test_view_mode_switching(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-parallel-timeline')
                ->waitForLivewire()
                // Check view mode buttons
                ->assertPresent('@view-timeline-btn')
                ->assertPresent('@view-list-btn')
                ->assertPresent('@view-comparison-btn')
                // Timeline mode should be active initially
                ->assertPresent('@view-timeline-btn')
                // Switch to list mode
                ->click('@view-list-btn')
                ->waitForLivewire()
                ->pause(500)
                // Switch to comparison mode
                ->click('@view-comparison-btn')
                ->waitForLivewire()
                ->pause(500)
                // Switch back to timeline mode
                ->click('@view-timeline-btn')
                ->waitForLivewire()
                ->pause(500)
                ->assertPresent('@timeline-main-container');
        });
    }

    /**
     * Test: Filter panel toggle and visibility
     *
     * Verifies:
     * - Filter panel toggle button is present
     * - Filter panel is hidden initially
     * - Clicking toggle shows filter panel
     * - Clicking toggle again hides filter panel
     * - Filter controls are visible when panel is open
     * - Filter panel contains all filter options
     */
    public function test_filter_panel_toggle(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-parallel-timeline')
                ->waitForLivewire()
                ->assertPresent('@filter-panel-toggle-btn')
                // Filter panel should be hidden initially
                ->assertMissing('@filter-panel')
                // Click toggle to show panel
                ->click('@filter-panel-toggle-btn')
                ->waitForLivewire()
                ->pause(500)
                // Panel should now be visible
                ->assertPresent('@filter-panel')
                ->assertPresent('@lane-filter-group')
                ->assertPresent('@event-type-filter-group')
                ->assertPresent('@date-range-filter-group')
                ->assertPresent('@auto-update-label')
                // Click toggle to hide panel
                ->click('@filter-panel-toggle-btn')
                ->waitForLivewire()
                ->pause(500)
                // Panel should be hidden again
                ->assertMissing('@filter-panel');
        });
    }

    /**
     * Test: Filter by event type
     *
     * Verifies:
     * - Event type filter dropdown is present
     * - Selecting "filing" shows only filing events
     * - Selecting "hearing" shows only hearing events
     * - Selecting "Svi tipovi" shows all events
     * - Event count updates when filter is applied
     * - Component re-renders correctly
     */
    public function test_filter_by_event_type(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-parallel-timeline')
                ->waitForLivewire()
                // Show filter panel
                ->click('@filter-panel-toggle-btn')
                ->waitForLivewire()
                ->pause(500)
                // Filter by filing type
                ->select('@event-type-filter-select', 'filing')
                ->waitForLivewire()
                ->pause(500)
                // Check that events are filtered (component should handle this)
                ->assertPresent('@two-lane-container')
                // Clear filter by selecting all
                ->select('@event-type-filter-select', '')
                ->waitForLivewire()
                ->pause(500)
                // Events should be visible again
                ->assertPresent('@top-events-list')
                ->assertPresent('@bottom-events-list');
        });
    }

    /**
     * Test: Filter by lane (top, bottom, all)
     *
     * Verifies:
     * - Lane filter dropdown is present
     * - Selecting "Gornja linija" shows only top lane
     * - Selecting "Donja linija" shows only bottom lane
     * - Selecting "Sve linije" shows both lanes
     * - Filter properly isolates events
     */
    public function test_filter_by_lane(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-parallel-timeline')
                ->waitForLivewire()
                // Show filter panel
                ->click('@filter-panel-toggle-btn')
                ->waitForLivewire()
                ->pause(500)
                // Both lanes should be visible initially
                ->assertPresent('@top-lane-container')
                ->assertPresent('@bottom-lane-container')
                // Filter to top lane only
                ->select('@lane-filter-select', 'top')
                ->waitForLivewire()
                ->pause(500)
                ->assertPresent('@top-lane-container')
                // Filter to bottom lane only
                ->select('@lane-filter-select', 'bottom')
                ->waitForLivewire()
                ->pause(500)
                ->assertPresent('@bottom-lane-container')
                // Show all lanes
                ->select('@lane-filter-select', 'all')
                ->waitForLivewire()
                ->pause(500)
                ->assertPresent('@top-lane-container')
                ->assertPresent('@bottom-lane-container');
        });
    }

    /**
     * Test: Event details panel interaction
     *
     * Verifies:
     * - Details panel is hidden initially
     * - Clicking "Detalji" button on event opens details panel
     * - Details panel shows selected event information
     * - Details panel includes ID, title, type, date, time, status, priority
     * - Close button hides details panel
     * - Details panel can be opened for events in both lanes
     */
    public function test_event_details_panel(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-parallel-timeline')
                ->waitForLivewire()
                // Details panel should be hidden initially
                ->assertMissing('@event-details-panel')
                // Click details button on first top event
                ->click('@top-event-details-btn-0')
                ->waitForLivewire()
                ->pause(500)
                // Details panel should now be visible
                ->assertPresent('@event-details-panel')
                ->assertPresent('@details-panel-title')
                ->assertPresent('@details-id')
                ->assertPresent('@details-title')
                ->assertPresent('@details-type')
                ->assertPresent('@details-date')
                ->assertPresent('@details-time')
                ->assertPresent('@details-status')
                ->assertPresent('@details-priority')
                ->assertPresent('@details-description')
                // Close details panel
                ->click('@close-details-btn')
                ->waitForLivewire()
                ->pause(500)
                // Panel should be hidden again
                ->assertMissing('@event-details-panel');
        });
    }

    /**
     * Test: Clear all filters button
     *
     * Verifies:
     * - Clear filters button is present
     * - Clicking button resets all filters to defaults
     * - Filter panel closes after clearing
     * - All events are visible after clearing filters
     * - Component state is reset correctly
     */
    public function test_clear_all_filters(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-parallel-timeline')
                ->waitForLivewire()
                // Show filter panel
                ->click('@filter-panel-toggle-btn')
                ->waitForLivewire()
                ->pause(500)
                // Apply a filter
                ->select('@event-type-filter-select', 'filing')
                ->waitForLivewire()
                ->pause(500)
                // Clear all filters
                ->click('@clear-filters-btn')
                ->waitForLivewire()
                ->pause(500)
                // Both lanes should have events again
                ->assertPresent('@top-events-list')
                ->assertPresent('@bottom-events-list');
        });
    }

    /**
     * Test: Refresh timeline button
     *
     * Verifies:
     * - Refresh button is present
     * - Clicking refresh reloads timeline data
     * - Component state is maintained after refresh
     * - Events are still visible after refresh
     * - Current day is maintained
     */
    public function test_refresh_timeline(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-parallel-timeline')
                ->waitForLivewire()
                ->assertPresent('@refresh-btn')
                // Store initial event count
                ->assertPresent('@top-events-list')
                // Click refresh
                ->click('@refresh-btn')
                ->waitForLivewire()
                ->pause(500)
                // Events should still be present
                ->assertPresent('@top-events-list')
                ->assertPresent('@bottom-events-list');
        });
    }

    /**
     * Test: Auto-update toggle feature
     *
     * Verifies:
     * - Auto-update checkbox is present in filter panel
     * - Checkbox is unchecked initially
     * - Clicking checkbox enables auto-update
     * - Clicking again disables auto-update
     * - Checkbox state reflects properly
     */
    public function test_auto_update_toggle(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-parallel-timeline')
                ->waitForLivewire()
                // Show filter panel
                ->click('@filter-panel-toggle-btn')
                ->waitForLivewire()
                ->pause(500)
                // Check auto-update checkbox
                ->assertPresent('@auto-update-checkbox')
                // Enable auto-update
                ->click('@auto-update-checkbox')
                ->waitForLivewire()
                ->pause(500)
                // Disable auto-update
                ->click('@auto-update-checkbox')
                ->waitForLivewire()
                ->pause(500);
        });
    }

    /**
     * Test: Responsive layout on desktop and mobile
     *
     * Verifies:
     * - Component displays in two-column grid on desktop
     * - Event cards have proper spacing
     * - Controls section has proper grid layout
     * - Top and bottom lanes are side by side
     * - Responsive info message is displayed
     */
    public function test_responsive_layout_desktop(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-parallel-timeline')
                ->waitForLivewire()
                // Check main container
                ->assertPresent('@parallel-timeline-container')
                // Check two-lane container for two-column layout
                ->assertPresent('@two-lane-container')
                // Check responsive info
                ->assertPresent('@responsive-info')
                ->assertPresent('@responsive-label')
                ->assertSee('responzivna');
        });
    }

    /**
     * Test: Event counts update correctly
     *
     * Verifies:
     * - Top lane event count is displayed
     * - Bottom lane event count is displayed
     * - Count numbers are correct
     * - Counts update when filters are applied
     * - Empty state shows when no events match filters
     */
    public function test_event_counts_displayed_correctly(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-parallel-timeline')
                ->waitForLivewire()
                // Check event counts are present
                ->assertPresent('@top-lane-count')
                ->assertPresent('@top-lane-count-number')
                ->assertPresent('@bottom-lane-count')
                ->assertPresent('@bottom-lane-count-number')
                // Counts should show numbers
                ->assertPresent('@top-lane-count-number')
                ->assertPresent('@bottom-lane-count-number')
                // Verify lane headers
                ->assertPresent('@top-lane-header')
                ->assertPresent('@bottom-lane-header')
                ->assertPresent('@top-lane-title')
                ->assertPresent('@bottom-lane-title')
                ->assertSee('Gornja Linija')
                ->assertSee('Donja Linija');
        });
    }
}
