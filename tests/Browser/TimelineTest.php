<?php

namespace Tests\Browser;

use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\AuthenticatesUser;
use Tests\Browser\Concerns\MocksExternalApis;
use Tests\DuskTestCase;

class TimelineTest extends DuskTestCase
{
    use AuthenticatesUser, MocksExternalApis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockAllExternalApis();

        // Ensure required tables exist
        if (! Schema::hasTable('users') || ! Schema::hasTable('legal_cases')) {
            $this->markTestSkipped('Database schema not initialized');
        }
    }

    public function test_can_access_timeline_page(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/timeline')
                ->waitForText('Timeline', 10)
                ->assertSee('Timeline')
                ->assertSee('Case');
        });
    }

    public function test_timeline_displays_case_events(): void
    {
        $user = User::factory()->create();
        $case = LegalCase::factory()->create([
            'case_number' => 'Pp-74/2025',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/timeline')
                ->waitForText('Timeline', 10)

                // Wait for timeline to load (TimelineJS)
                ->waitFor('@timeline-container', 15)

                // Verify case events shown
                ->assertSee('Pp-74/2025')
                ->assertPresent('@timeline-container');
        });
    }

    public function test_can_navigate_timeline_events(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/timeline')
                ->waitFor('@timeline-container', 15)

                // Click next event
                ->press('@timeline-next-button')
                ->pause(1000)

                // Verify event changed
                ->assertPresent('@timeline-event-details');
        });
    }

    public function test_can_filter_timeline_by_case(): void
    {
        $user = User::factory()->create();
        $case1 = LegalCase::factory()->create(['case_number' => 'Pp-111/2025']);
        $case2 = LegalCase::factory()->create(['case_number' => 'Pp-222/2025']);

        $this->browse(function (Browser $browser) use ($user, $case1) {
            $this->loginAs($browser, $user);

            $browser->visit('/timeline')
                ->waitForText('Timeline', 10)

                // Select specific case
                ->select('@case-filter', $case1->id)
                ->pause(1000)
                ->waitFor('@timeline-container', 15)

                // Verify only case1 events shown
                ->assertSee('Pp-111/2025')
                ->assertDontSee('Pp-222/2025');
        });
    }

    public function test_timeline_shows_event_details(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/timeline')
                ->waitFor('@timeline-container', 15)

                // Click event to expand details
                ->press('@timeline-event-0')
                ->pause(500)

                // Verify details shown
                ->assertPresent('@event-description')
                ->assertPresent('@event-legal-references');
        });
    }

    public function test_can_switch_timeline_variants(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            // Test main timeline
            $browser->visit('/timeline')
                ->waitFor('@timeline-container', 15)
                ->assertPresent('@timeline-container');

            // Test comparative timeline
            $browser->visit('/comparative-timeline')
                ->waitFor('@comparative-timeline-container', 15)
                ->assertPresent('@comparative-timeline-container');

            // Test GUP timeline
            $browser->visit('/comparative-timeline3')
                ->waitFor('@gup-timeline-container', 15)
                ->assertPresent('@gup-timeline-container');
        });
    }
}
