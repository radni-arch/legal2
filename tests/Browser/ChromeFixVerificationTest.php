<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\MocksExternalApis;
use Tests\DuskTestCase;

class ChromeFixVerificationTest extends DuskTestCase
{
    use DatabaseMigrations, MocksExternalApis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockAllExternalApis();
    }

    /**
     * Verify Chrome no longer crashes in Docker
     *
     * @test
     */
    public function test_chrome_loads_page_without_crashing(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                ->assertPathIs('/login')
                ->assertPresent('body');

            // If we get here, Chrome didn't crash!
            $this->assertTrue(true, 'Chrome successfully loaded the page without crashing');
        });
    }

    /**
     * Verify Chrome can navigate between pages
     *
     * @test
     */
    public function test_chrome_can_navigate_between_pages(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                ->assertPathIs('/login');

            $browser->visit('/register')
                ->assertPathIs('/register');

            // Successfully navigated without crash
            $this->assertTrue(true, 'Chrome successfully navigated between pages');
        });
    }
}
