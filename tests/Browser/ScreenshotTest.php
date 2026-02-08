<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ScreenshotTest extends DuskTestCase
{
    /**
     * Capture screenshots of key pages to verify UI
     *
     * @test
     */
    public function test_capture_login_page(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                ->screenshot('login-page')
                ->assertPathIs('/login');
        });
    }

    /**
     * Capture register page
     *
     * @test
     */
    public function test_capture_register_page(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/register')
                ->screenshot('register-page')
                ->assertPathIs('/register');
        });
    }

    /**
     * Capture dashboard page (requires login)
     *
     * @test
     */
    public function test_capture_dashboard_page(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/dashboard')
                ->screenshot('dashboard-page');
        });
    }
}
