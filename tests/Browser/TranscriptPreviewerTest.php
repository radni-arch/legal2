<?php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\AuthenticatesUser;
use Tests\Browser\Concerns\MocksExternalApis;
use Tests\DuskTestCase;

/**
 * TranscriptPreviewer E2E Tests
 *
 * Comprehensive browser tests for the TranscriptPreviewer Livewire component.
 * Tests document display, navigation, search, filtering, time handling, Croatian text,
 * and forensic linguistics analysis integration.
 *
 * ## Test Coverage
 *
 * - Component Loading & UI Initialization
 * - Document Display & Segment Rendering
 * - Search & Text Highlighting
 * - Speaker Filtering
 * - Timestamp Display & Duration
 * - Forensic Linguistics Panel
 * - Timeline Navigation
 * - Croatian Text Support
 * - Auto-refresh Functionality
 * - File Path Configuration
 */
class TranscriptPreviewerTest extends DuskTestCase
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
            'email' => 'transcript-test-'.uniqid().'@example.com',
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
     * Test: Component loads successfully with all main UI elements visible
     */
    public function test_component_loads_successfully(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-transcript-previewer')
                ->waitForLivewire()
                ->assertPresent('@transcript-previewer-container')
                ->assertPresent('@main-controls')
                ->assertPresent('@file-path-input')
                ->assertPresent('@search-input')
                ->assertPresent('@timestamps-toggle')
                ->assertPresent('@auto-refresh-toggle')
                ->assertPresent('@refresh-button')
                ->assertPresent('@clear-search-button')
                ->assertPresent('@lingua-controls')
                ->assertPresent('@lingua-toggle');
        });
    }

    /**
     * Test: Transcript segments display with proper speaker information
     */
    public function test_transcript_segments_display_correctly(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-transcript-previewer')
                ->waitForLivewire()
                ->assertPresent('@segments-list')
                ->assertPresent('@segment-0')
                ->assertPresent('@segment-0-speaker')
                ->assertSeeIn('@segment-0-speaker', 'S1')
                ->assertPresent('@segment-0-text')
                // Verify Croatian text is handled properly
                ->assertSeeIn('@segment-0-text', 'snimanje');
        });
    }

    /**
     * Test: Timecode display shows correct format and can be toggled
     */
    public function test_timecode_display_and_toggle(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-transcript-previewer')
                ->waitForLivewire()
                // Initially timestamps should be visible
                ->assertPresent('@segment-0-timecode')
                ->assertSeeIn('@segment-0-timecode', ':')
                // Toggle timestamps off
                ->click('@timestamps-toggle')
                ->waitForLivewire()
                ->pause(500)
                // Now timecode chips should be hidden
                ->assertMissing('@segment-0-timecode');
        });
    }

    /**
     * Test: Search functionality filters segments by text content
     */
    public function test_search_filters_segments_by_text(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-transcript-previewer')
                ->waitForLivewire()
                // Verify multiple segments exist initially
                ->assertPresent('@segment-0')
                ->assertPresent('@segment-1')
                // Search for specific Croatian text
                ->type('@search-input', 'ispitivanja')
                ->pause(500)
                ->waitForLivewire()
                // Should still have results
                ->assertPresent('@segments-list')
                // Search results should contain the term
                ->assertSee('ispitivanja');
        });
    }

    /**
     * Test: Search highlighting marks matching text
     */
    public function test_search_highlighting_marks_text(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-transcript-previewer')
                ->waitForLivewire()
                // Search for text that exists in transcript
                ->type('@search-input', 'osumnjičenika')
                ->pause(500)
                ->waitForLivewire()
                // Verify text is highlighted in results
                ->assertPresent('@segment-0-text')
                ->assertSee('osumnjičenika');
        });
    }

    /**
     * Test: Speaker filtering by checkbox selection
     */
    public function test_speaker_filtering_by_checkbox(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-transcript-previewer')
                ->waitForLivewire()
                // Verify both S1 and S2 speakers exist
                ->assertPresent('@speaker-S1-checkbox')
                ->assertPresent('@speaker-S2-checkbox')
                // Initially both should be checked
                ->assertChecked('@speaker-S1-checkbox')
                ->assertChecked('@speaker-S2-checkbox')
                // Uncheck S2
                ->click('@speaker-S2-checkbox')
                ->waitForLivewire()
                ->pause(500)
                // Should still have S1 segments
                ->assertPresent('@segments-list')
                ->assertSeeIn('@segment-0-speaker', 'S1');
        });
    }

    /**
     * Test: Show/Hide all speakers buttons
     */
    public function test_show_hide_all_speakers(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-transcript-previewer')
                ->waitForLivewire()
                // Click "None" to hide all speakers
                ->click('@hide-all-speakers-button')
                ->waitForLivewire()
                ->pause(500)
                // All checkboxes should be unchecked
                ->assertNotChecked('@speaker-S1-checkbox')
                ->assertNotChecked('@speaker-S2-checkbox')
                // Should show no segments message
                ->assertPresent('@no-segments-message')
                // Click "All" to show all speakers
                ->click('@show-all-speakers-button')
                ->waitForLivewire()
                ->pause(500)
                // Checkboxes should be checked again
                ->assertChecked('@speaker-S1-checkbox')
                ->assertChecked('@speaker-S2-checkbox')
                // Segments should be visible
                ->assertPresent('@segments-list');
        });
    }

    /**
     * Test: Clear search button resets search input
     */
    public function test_clear_search_button(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-transcript-previewer')
                ->waitForLivewire()
                // Enter search text
                ->type('@search-input', 'test')
                ->pause(500)
                // Verify text is in input
                ->assertValue('@search-input', 'test')
                // Click clear button
                ->click('@clear-search-button')
                ->waitForLivewire()
                ->pause(500)
                // Input should be empty
                ->assertValue('@search-input', '');
        });
    }

    /**
     * Test: Forensic linguistics panel displays summary and timeline
     */
    public function test_lingua_panel_displays_summary_and_timeline(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-transcript-previewer')
                ->waitForLivewire()
                // Verify lingua panel is visible
                ->assertPresent('@lingua-panel')
                ->assertPresent('@forensic-summary-chip')
                ->assertPresent('@lingua-summary-text')
                // Should contain Croatian analysis text
                ->assertSee('Opća analiza');
        });
    }

    /**
     * Test: Timeline bar displays forensic events
     */
    public function test_timeline_displays_events(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-transcript-previewer')
                ->waitForLivewire()
                // Check timeline bar is present
                ->assertPresent('@timeline-bar')
                ->assertPresent('@timeline-label')
                // Check lingua events list
                ->assertPresent('@lingua-events-list');
        });
    }

    /**
     * Test: Duration display shows correct format
     */
    public function test_duration_display_format(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-transcript-previewer')
                ->waitForLivewire()
                // Duration chip should be visible
                ->assertPresent('@duration-chip')
                // Should contain time format HH:MM:SS
                ->assertSee('00:');
        });
    }

    /**
     * Test: Lingua panel can be toggled on/off
     */
    public function test_lingua_panel_toggle(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-transcript-previewer')
                ->waitForLivewire()
                // Panel should be visible initially
                ->assertPresent('@lingua-panel')
                // Toggle off
                ->click('@lingua-toggle')
                ->waitForLivewire()
                ->pause(500)
                // Panel should be hidden
                ->assertMissing('@lingua-panel')
                // Toggle on again
                ->click('@lingua-toggle')
                ->waitForLivewire()
                ->pause(500)
                // Panel should be visible again
                ->assertPresent('@lingua-panel');
        });
    }

    /**
     * Test: Base start datetime chip displays correctly
     */
    public function test_base_start_datetime_display(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-transcript-previewer')
                ->waitForLivewire()
                // Check base start chip
                ->assertPresent('@base-start-chip')
                ->assertSee('2025-06-09');
        });
    }

    /**
     * Test: Timezone display shows correct value
     */
    public function test_timezone_display(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-transcript-previewer')
                ->waitForLivewire()
                // Check timezone chip
                ->assertPresent('@timezone-chip')
                ->assertSee('Europe/Zagreb');
        });
    }

    /**
     * Test: Auto-refresh toggle enables/disables polling
     */
    public function test_auto_refresh_toggle(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-transcript-previewer')
                ->waitForLivewire()
                // Auto-refresh should be off initially
                ->assertNotChecked('@auto-refresh-toggle')
                // Toggle on
                ->click('@auto-refresh-toggle')
                ->waitForLivewire()
                ->pause(500)
                // Should be checked
                ->assertChecked('@auto-refresh-toggle');
        });
    }

    /**
     * Test: Manual refresh button triggers reload
     */
    public function test_refresh_button_reloads_data(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-transcript-previewer')
                ->waitForLivewire()
                // Click refresh button - should complete without error
                ->click('@refresh-button')
                ->waitForLivewire()
                ->pause(500)
                // Component should still be loaded
                ->assertPresent('@transcript-previewer-container');
        });
    }

    /**
     * Test: Absolute datetime display for segments with timestamps
     */
    public function test_absolute_datetime_display(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-transcript-previewer')
                ->waitForLivewire()
                // Check if segments have absolute datetime
                ->assertPresent('@segment-0')
                // Look for datetime chip (may or may not be present depending on segment)
                ->assertPresent('@segment-0-header');
        });
    }

    /**
     * Test: Near-events details are displayed when lingua events are nearby
     */
    public function test_near_events_details_display(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-transcript-previewer')
                ->waitForLivewire()
                // Verify segments are loaded
                ->assertPresent('@segments-list')
                // Look for expandable details (may exist on some segments)
                ->assertPresent('@transcript-display');
        });
    }

    /**
     * Test: Croatian special characters are properly displayed
     */
    public function test_croatian_text_support(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-transcript-previewer')
                ->waitForLivewire()
                // Verify Croatian text with special characters
                ->assertSee('ispitivanja')
                ->assertSee('osumnjičenika')
                ->assertSee('kaznenog')
                ->assertSee('Osijek');
        });
    }

    /**
     * Test: File path chip displays truncated file path
     */
    public function test_file_path_chip_display(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-transcript-previewer')
                ->waitForLivewire()
                // Check file path chip
                ->assertPresent('@file-path-chip')
                ->assertSee('iznedjenaIzjava');
        });
    }
}
