<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\AuthenticatesUser;
use Tests\Browser\Concerns\MocksExternalApis;
use Tests\DuskTestCase;

class ExampleTest extends DuskTestCase
{
    use AuthenticatesUser, MocksExternalApis;
    // Temporarily disabled DatabaseMigrations due to PostgreSQL type conflicts
    // use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockAllExternalApis();
    }

    public function test_can_visit_login_page(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                ->assertSee('Welcome Back')
                ->assertSee('Email Address')
                ->assertSee('Password')
                ->assertSee('Sign In');
        });
    }
}
