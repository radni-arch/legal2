<?php

namespace Tests\Browser\Concerns;

use App\Models\User;
use Laravel\Dusk\Browser;

trait AuthenticatesUser
{
    protected function loginAs(Browser $browser, ?User $user = null): void
    {
        if ($user === null) {
            // Create a test user with a unique email to avoid duplicate constraints
            $user = User::factory()->create([
                'email' => 'test-'.uniqid().'@example.com',
                'password' => bcrypt('password'),
            ]);
        }

        $browser->visit('/login')
            ->waitFor('input[name="email"]', 10)
            ->type('email', $user->email)
            ->type('password', 'password')
            ->press('Sign In')
            ->waitForLocation('/dashboard', 10)
            ->assertAuthenticated();
    }

    protected function logout(Browser $browser): void
    {
        $browser->press('Logout')
            ->waitForLocation('/login')
            ->assertPathIs('/login');
    }
}
