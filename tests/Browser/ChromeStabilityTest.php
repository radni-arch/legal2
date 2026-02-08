<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\MocksExternalApis;
use Tests\DuskTestCase;

class ChromeStabilityTest extends DuskTestCase
{
    use DatabaseMigrations, MocksExternalApis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockAllExternalApis();
    }

    public function test_chrome_does_not_crash(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                ->assertPathIs('/login')
                ->screenshot('chrome-working');
        });
    }
}
