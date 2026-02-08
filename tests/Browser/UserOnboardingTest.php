<?php

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Sprint 12 Worker A: User Authentication & Onboarding Workflows
 *
 * Comprehensive E2E browser tests for user authentication flows:
 * - User Registration
 * - User Login
 * - Password Reset
 * - Email Verification
 * - Profile Management
 * - API Token Management
 */
class UserOnboardingTest extends DuskTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure required tables exist
        if (! Schema::hasTable('users')) {
            $this->markTestSkipped('Database schema not initialized');
        }
    }

    /**
     * Test 1: Complete User Registration Flow
     *
     * Verifies that a new user can:
     * - Access the registration page
     * - Fill out registration form with valid data
     * - Submit registration
     * - Be redirected to dashboard
     * - Be authenticated after registration
     *
     * @test
     */
    public function test_complete_user_registration_flow(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/register')
                ->assertSee('Create Account')
                ->assertSee('Full Name')
                ->assertSee('Email Address')
                ->assertSee('Password')

                    // Fill out registration form (using label text for clarity)
                ->type('Full Name', 'Test User')
                ->type('Email Address', 'testuser@example.com')
                ->type('Password', 'SecurePass123!')
                ->type('password_confirmation', 'SecurePass123!')

                    // Submit form
                ->press('Create Account')

                    // Assert successful registration
                ->waitForLocation('/dashboard', 10)
                ->assertPathIs('/dashboard')
                ->assertAuthenticated();

            // Verify user was created in database
            $this->assertDatabaseHas('users', [
                'email' => 'testuser@example.com',
                'name' => 'Test User',
            ]);
        });
    }

    /**
     * Test 2: User Login Flow
     *
     * Verifies that an existing user can:
     * - Access the login page
     * - Enter credentials
     * - Successfully log in
     * - Access authenticated areas
     *
     * @test
     */
    public function test_user_login_flow(): void
    {
        // Create a test user
        $user = User::factory()->create([
            'email' => 'logintest@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->visit('/login')
                ->assertSee('Welcome Back')
                ->assertSee('Email Address')
                ->assertSee('Password')

                    // Enter credentials (using label text for clarity)
                ->type('Email Address', 'logintest@example.com')
                ->type('Password', 'password123')

                    // Submit login form
                ->press('Sign In')

                    // Assert successful login
                ->waitForLocation('/dashboard', 10)
                ->assertPathIs('/dashboard')
                ->assertAuthenticatedAs($user);
        });
    }

    /**
     * Test 3: Password Reset Flow
     *
     * Verifies that a user can:
     * - Request password reset
     * - Receive reset link (simulated)
     * - Reset password successfully
     * - Login with new password
     *
     * @test
     */
    public function test_password_reset_flow(): void
    {
        $user = User::factory()->create([
            'email' => 'resettest@example.com',
            'password' => Hash::make('oldpassword123'),
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            // Step 1: Request password reset
            $browser->visit('/password/reset')
                ->assertSee('Reset Password')
                ->assertSee('Email')
                ->type('Email Address', 'resettest@example.com')
                ->press('Send Password Reset Link')
                ->waitForText('password reset link', 10)
                ->assertSee('password reset link');

            // Step 2: Simulate clicking reset link with token
            // In real scenario, this would be from email
            $token = \Illuminate\Support\Facades\Password::createToken($user);

            $browser->visit('/password/reset/'.$token.'?email='.urlencode($user->email))
                ->assertSee('Reset Password')
                ->assertSee('Email')
                ->assertSee('Password')
                ->assertSee('Confirm Password')

                    // Fill out reset form (using label text for clarity)
                ->type('Email Address', $user->email)
                ->type('Password', 'newpassword123')
                ->type('password_confirmation', 'newpassword123')
                ->press('Reset Password')

                    // Assert successful reset
                ->waitForLocation('/dashboard', 10)
                ->assertPathIs('/dashboard')
                ->assertAuthenticatedAs($user);

            // Verify password was changed
            $user->refresh();
            $this->assertTrue(Hash::check('newpassword123', $user->password));
        });
    }

    /**
     * Test 4: Email Verification Flow
     *
     * Verifies that a user can:
     * - Register without verified email
     * - See verification notice
     * - Click verification link
     * - Have email verified
     * - Access all features
     *
     * @test
     */
    public function test_email_verification_flow(): void
    {
        $user = User::factory()->create([
            'email' => 'unverified@example.com',
            'email_verified_at' => null, // Not verified
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            // Login as unverified user
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->assertSee('Verify Your Email');

            // Simulate email verification URL
            $verificationUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(60),
                ['id' => $user->id, 'hash' => sha1($user->email)]
            );

            // Visit verification URL
            $browser->visit($verificationUrl)
                ->waitForLocation('/dashboard', 10)
                ->assertPathIs('/dashboard')
                ->assertDontSee('Verify Your Email'); // Verification notice should be gone

            // Verify email_verified_at is set
            $user->refresh();
            $this->assertNotNull($user->email_verified_at);
        });
    }

    /**
     * Test 5: User Can Update Profile
     *
     * Verifies that a user can:
     * - Access profile page
     * - Update name and email
     * - See success message
     * - Changes are persisted
     *
     * @test
     */
    public function test_user_can_update_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/profile')
                ->assertSee('Profile')
                ->assertSee('Name')
                ->assertSee('Email')

                    // Clear and type new values (using label text for clarity)
                ->clear('Name')
                ->type('Name', 'Updated Name')
                ->clear('Email Address')
                ->type('Email Address', 'updated@example.com')
                ->press('Save Profile')

                    // Assert success
                ->waitForText('Profile updated successfully', 10)
                ->assertSee('Profile updated successfully');

            // Verify database changes
            $this->assertDatabaseHas('users', [
                'id' => $user->id,
                'name' => 'Updated Name',
                'email' => 'updated@example.com',
            ]);
        });
    }

    /**
     * Test 6: User Can Generate API Token
     *
     * Verifies that a user can:
     * - Access API token management page
     * - Generate a new API token
     * - See the generated token
     * - Token is saved in database
     *
     * @test
     */
    public function test_user_can_generate_api_token(): void
    {
        $user = User::factory()->create([
            'api_token' => null, // No token initially
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/profile/api-tokens')
                ->assertSee('API Tokens')
                ->assertSee('Generate New Token')

                    // Click generate button
                ->press('Generate New Token')

                    // Wait for token to be generated
                ->waitForText('Token generated successfully', 10)
                ->assertSee('Token generated successfully')
                ->assertSee('Your API Token')

                    // Assert token is displayed (partially masked)
                ->assertPresent('[data-test="api-token-display"]');

            // Verify token was saved to database
            $user->refresh();
            $this->assertNotNull($user->api_token);
            $this->assertIsString($user->api_token);
            $this->assertTrue(strlen($user->api_token) >= 40); // Tokens should be at least 40 chars
        });
    }

    /**
     * Test 7: User Can Revoke API Token
     *
     * Verifies that a user can:
     * - View existing API token
     * - Revoke the token
     * - See confirmation
     * - Token is removed from database
     *
     * @test
     */
    public function test_user_can_revoke_api_token(): void
    {
        $user = User::factory()->create([
            'api_token' => Str::random(60), // User already has a token
        ]);

        $originalToken = $user->api_token;

        $this->browse(function (Browser $browser) use ($user, $originalToken) {
            $browser->loginAs($user)
                ->visit('/profile/api-tokens')
                ->assertSee('API Tokens')
                ->assertSee('Active Token')

                    // Click revoke button
                ->press('Revoke Token')

                    // Confirm revocation (if modal exists)
                ->whenAvailable('.modal', function ($modal) {
                    $modal->press('Confirm');
                }, 2) // 2 second timeout for modal

                    // Wait for success message
                ->waitForText('Token revoked successfully', 10)
                ->assertSee('Token revoked successfully')
                ->assertDontSee('Active Token');

            // Verify token was removed from database
            $user->refresh();
            $this->assertNull($user->api_token);
            $this->assertNotEquals($originalToken, $user->api_token);
        });
    }

    /**
     * Additional helper test: Verify logout works
     *
     * @test
     */
    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->assertAuthenticatedAs($user)

                    // Click logout (usually in nav or menu)
                ->click('[data-test="logout-link"]')
                ->waitForLocation('/login', 10)
                ->assertGuest()
                ->assertPathIs('/login');
        });
    }

    /**
     * Additional helper test: Verify registration validation
     *
     * @test
     */
    public function test_registration_validates_required_fields(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/register')
                ->press('Create Account')

                    // Assert validation errors
                ->waitForText('name', 5)
                ->assertSee('email')
                ->assertSee('password');
        });
    }

    /**
     * Additional helper test: Verify login validation
     *
     * @test
     */
    public function test_login_rejects_invalid_credentials(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                ->type('Email Address', 'nonexistent@example.com')
                ->type('Password', 'wrongpassword')
                ->press('Sign In')
                ->waitForText('credentials', 10)
                ->assertSee('credentials')
                ->assertGuest();
        });
    }
}
