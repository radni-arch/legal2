<?php

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\MocksExternalApis;
use Tests\DuskTestCase;

class AuthenticationTest extends DuskTestCase
{
    use DatabaseMigrations, MocksExternalApis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockAllExternalApis();
    }

    public function test_user_can_login_and_access_dashboard(): void
    {
        $user = User::factory()->create([
            'email' => 'attorney-'.uniqid().'@example.com',
            'password' => bcrypt('secure-password'),
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->visit('/login')
                ->assertSee('Welcome Back')
                ->type('email', $user->email)
                ->type('password', 'secure-password')
                ->press('Sign In')
                ->waitForLocation('/dashboard', 20)
                ->assertPathIs('/dashboard')
                ->assertSee('Unified Dashboard')
                ->assertAuthenticated();
        });
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create([
            'email' => 'logout-'.uniqid().'@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            // Login first
            $browser->visit('/login')
                ->type('email', $user->email)
                ->type('password', 'password')
                ->press('Sign In')
                ->waitForLocation('/dashboard', 20)
                ->assertAuthenticated();

            // Then logout
            $browser->press('Logout')
                ->waitForLocation('/login', 20)
                ->assertPathIs('/login')
                ->assertGuest();
        });
    }

    public function test_invalid_credentials_show_error(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                ->waitFor('input[name="email"]', 15)
                ->type('email', 'nonexistent@example.com')
                ->type('password', 'wrong-password')
                ->press('Sign In')
                ->waitForText('credentials', 15)
                ->assertSee('credentials')
                ->assertPathIs('/login')
                ->assertGuest();
        });
    }

    public function test_unauthenticated_user_redirected_to_login(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/playground')
                ->waitForLocation('/login', 20)
                ->pause(1000)
                ->assertPathIs('/login')
                ->assertSee('Sign In');
        });
    }
}
