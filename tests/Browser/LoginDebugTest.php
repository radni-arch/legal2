<?php

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\MocksExternalApis;
use Tests\DuskTestCase;
use Tests\UsesTestDatabase;

class LoginDebugTest extends DuskTestCase
{
    use MocksExternalApis, UsesTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockAllExternalApis();
    }

    /**
     * Debug login flow
     *
     * @test
     */
    public function test_debug_login(): void
    {
        $email = 'debugtest-'.uniqid().'@example.com';
        $user = User::factory()->create([
            'email' => $email,
            'password' => Hash::make('password123'),
        ]);

        $this->browse(function (Browser $browser) use ($email) {
            $browser->visit('/login')
                ->pause(2000)  // Wait for page to fully load
                ->screenshot('before-login')
                ->type('#email', $email)  // Use ID instead of dusk attribute
                ->type('#password', 'password123')
                ->click('[dusk="login-btn"]')
                ->pause(2000)
                ->screenshot('after-login')
                ->pause(1000);

            dump('Current URL: '.$browser->driver->getCurrentURL());
            dump('Page Title: '.$browser->driver->getTitle());
        });
    }
}
