<?php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\AuthenticatesUser;
use Tests\Browser\Concerns\MocksExternalApis;
use Tests\DuskTestCase;

class PrecedentialBadgeTest extends DuskTestCase
{
    use AuthenticatesUser, MocksExternalApis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockAllExternalApis();
    }

    public function test_binding_badge_displays_correct_tooltip(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/test/precedential-badge/binding')
                ->waitFor('@precedential-badge-binding', 5)
                ->assertSee('Binding')

                // Hover over badge to show tooltip
                ->mouseover('@precedential-badge-binding')
                ->waitFor('@tooltip-binding', 2)
                ->assertSee('This precedent must be followed by lower courts within the same jurisdiction.')

                // Check ARIA attributes
                ->assertAttribute('@precedential-badge-binding', 'role', 'status')
                ->assertAttribute('@precedential-badge-binding', 'aria-label', 'Binding precedent')

                // Move mouse away and verify tooltip disappears
                ->mouseover('body')
                ->pause(300)
                ->assertMissing('@tooltip-binding');
        });
    }

    public function test_persuasive_badge_displays_correct_tooltip(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/test/precedential-badge/persuasive')
                ->waitFor('@precedential-badge-persuasive', 5)
                ->assertSee('Persuasive')

                // Hover over badge to show tooltip
                ->mouseover('@precedential-badge-persuasive')
                ->waitFor('@tooltip-persuasive', 2)
                ->assertSee('This precedent may be considered but is not legally binding.')

                // Check ARIA attributes
                ->assertAttribute('@precedential-badge-persuasive', 'role', 'status')
                ->assertAttribute('@precedential-badge-persuasive', 'aria-label', 'Persuasive precedent');
        });
    }

    public function test_informational_badge_displays_correct_tooltip(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/test/precedential-badge/informational')
                ->waitFor('@precedential-badge-informational', 5)
                ->assertSee('Informational')

                // Hover over badge to show tooltip
                ->mouseover('@precedential-badge-informational')
                ->waitFor('@tooltip-informational', 2)
                ->assertSee('This decision provides context but does not establish legal precedent.')

                // Check ARIA attributes
                ->assertAttribute('@precedential-badge-informational', 'role', 'status')
                ->assertAttribute('@precedential-badge-informational', 'aria-label', 'Informational precedent');
        });
    }

    public function test_tooltip_is_keyboard_accessible(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/test/precedential-badge/binding')
                ->waitFor('@precedential-badge-binding', 5)

                // Tab to the badge element
                ->keys('body', ['{tab}', '{tab}'])

                // Verify tooltip appears on focus
                ->waitFor('@tooltip-binding', 2)
                ->assertSee('This precedent must be followed by lower courts within the same jurisdiction.');
        });
    }
}
