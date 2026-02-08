<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class ProfileControllerTest extends TestCase
{
    use UsesTestDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_shows_user_profile_when_authenticated(): void
    {
        $response = $this->actingAs($this->user)->get('/profile');

        $response->assertStatus(200);
        $response->assertViewIs('profile.show');
        $response->assertViewHas('user', $this->user);
    }

    /** @test */
    public function it_redirects_to_login_when_not_authenticated(): void
    {
        $response = $this->get('/profile');

        $response->assertRedirect('/login');
    }

    /** @test */
    public function it_generates_api_token_for_user(): void
    {
        $this->assertNull($this->user->api_token);

        $response = $this->actingAs($this->user)->post('/profile/generate-token');

        $response->assertRedirect();
        $response->assertSessionHas('success', 'API token generated successfully!');
        $response->assertSessionHas('token');

        $this->user->refresh();
        $this->assertNotNull($this->user->api_token);
        $this->assertIsString($this->user->api_token);
        $this->assertEquals(80, strlen($this->user->api_token)); // 40 bytes = 80 hex chars
    }

    /** @test */
    public function it_stores_generated_token_in_database(): void
    {
        $response = $this->actingAs($this->user)->post('/profile/generate-token');

        $response->assertRedirect();

        $this->user->refresh();
        $token = $this->user->api_token;

        $this->assertNotNull($token);
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'api_token' => $token,
        ]);
    }

    /** @test */
    public function it_returns_generated_token_in_session(): void
    {
        $response = $this->actingAs($this->user)->post('/profile/generate-token');

        $response->assertSessionHas('token');

        $sessionToken = session('token');
        $this->assertNotNull($sessionToken);
        $this->assertEquals(80, strlen($sessionToken));

        $this->user->refresh();
        $this->assertEquals($this->user->api_token, $sessionToken);
    }

    /** @test */
    public function it_regenerates_token_when_called_multiple_times(): void
    {
        // Generate first token
        $response1 = $this->actingAs($this->user)->post('/profile/generate-token');
        $this->user->refresh();
        $firstToken = $this->user->api_token;

        // Generate second token
        $response2 = $this->actingAs($this->user)->post('/profile/generate-token');
        $this->user->refresh();
        $secondToken = $this->user->api_token;

        $this->assertNotEquals($firstToken, $secondToken);
        $this->assertNotNull($secondToken);
    }

    /** @test */
    public function it_revokes_api_token_for_user(): void
    {
        // First generate a token
        $this->user->generateApiToken();
        $this->assertNotNull($this->user->api_token);

        // Then revoke it
        $response = $this->actingAs($this->user)->post('/profile/revoke-token');

        $response->assertRedirect();
        $response->assertSessionHas('success', 'API token revoked successfully!');

        $this->user->refresh();
        $this->assertNull($this->user->api_token);
    }

    /** @test */
    public function it_removes_token_from_database_when_revoked(): void
    {
        // Generate token
        $this->user->generateApiToken();
        $token = $this->user->api_token;

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'api_token' => $token,
        ]);

        // Revoke token
        $response = $this->actingAs($this->user)->post('/profile/revoke-token');

        $response->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'api_token' => null,
        ]);
    }

    /** @test */
    public function it_revokes_token_even_when_no_token_exists(): void
    {
        // Ensure no token exists
        $this->assertNull($this->user->api_token);

        // Attempt to revoke
        $response = $this->actingAs($this->user)->post('/profile/revoke-token');

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->user->refresh();
        $this->assertNull($this->user->api_token);
    }

    /** @test */
    public function it_requires_authentication_for_token_generation(): void
    {
        $response = $this->post('/profile/generate-token');

        $response->assertRedirect('/login');
    }

    /** @test */
    public function it_requires_authentication_for_token_revocation(): void
    {
        $response = $this->post('/profile/revoke-token');

        $response->assertRedirect('/login');
    }

    /** @test */
    public function it_redirects_back_after_generating_token(): void
    {
        $response = $this->actingAs($this->user)
            ->from('/profile')
            ->post('/profile/generate-token');

        $response->assertRedirect('/profile');
    }

    /** @test */
    public function it_redirects_back_after_revoking_token(): void
    {
        $this->user->generateApiToken();

        $response = $this->actingAs($this->user)
            ->from('/profile')
            ->post('/profile/revoke-token');

        $response->assertRedirect('/profile');
    }

    /** @test */
    public function it_generates_unique_tokens_for_different_users(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $this->actingAs($user1)->post('/profile/generate-token');
        $this->actingAs($user2)->post('/profile/generate-token');

        $user1->refresh();
        $user2->refresh();

        $this->assertNotEquals($user1->api_token, $user2->api_token);
        $this->assertNotNull($user1->api_token);
        $this->assertNotNull($user2->api_token);
    }

    /** @test */
    public function it_passes_current_user_to_profile_view(): void
    {
        $response = $this->actingAs($this->user)->get('/profile');

        $response->assertViewHas('user', function ($viewUser) {
            return $viewUser->id === $this->user->id &&
                $viewUser->email === $this->user->email;
        });
    }

    /** @test */
    public function it_shows_success_message_after_token_generation(): void
    {
        $response = $this->actingAs($this->user)->post('/profile/generate-token');

        $response->assertSessionHas('success', 'API token generated successfully!');
    }

    /** @test */
    public function it_shows_success_message_after_token_revocation(): void
    {
        $this->user->generateApiToken();

        $response = $this->actingAs($this->user)->post('/profile/revoke-token');

        $response->assertSessionHas('success', 'API token revoked successfully!');
    }

    /** @test */
    public function it_maintains_user_session_after_generating_token(): void
    {
        $response = $this->actingAs($this->user)->post('/profile/generate-token');

        $response->assertRedirect();

        // Verify user is still authenticated
        $this->assertAuthenticatedAs($this->user);
    }

    /** @test */
    public function it_maintains_user_session_after_revoking_token(): void
    {
        $this->user->generateApiToken();

        $response = $this->actingAs($this->user)->post('/profile/revoke-token');

        $response->assertRedirect();

        // Verify user is still authenticated
        $this->assertAuthenticatedAs($this->user);
    }

    /** @test */
    public function it_generates_cryptographically_secure_tokens(): void
    {
        $response = $this->actingAs($this->user)->post('/profile/generate-token');

        $this->user->refresh();
        $token = $this->user->api_token;

        // Verify token is hexadecimal (only 0-9, a-f characters)
        $this->assertMatchesRegularExpression('/^[0-9a-f]{80}$/', $token);
    }

    /** @test */
    public function it_can_generate_revoke_and_regenerate_token(): void
    {
        // Generate
        $this->actingAs($this->user)->post('/profile/generate-token');
        $this->user->refresh();
        $token1 = $this->user->api_token;
        $this->assertNotNull($token1);

        // Revoke
        $this->actingAs($this->user)->post('/profile/revoke-token');
        $this->user->refresh();
        $this->assertNull($this->user->api_token);

        // Regenerate
        $this->actingAs($this->user)->post('/profile/generate-token');
        $this->user->refresh();
        $token2 = $this->user->api_token;
        $this->assertNotNull($token2);

        // Verify tokens are different
        $this->assertNotEquals($token1, $token2);
    }

    /** @test */
    public function it_only_affects_current_users_token(): void
    {
        $otherUser = User::factory()->create();
        $otherUser->generateApiToken();
        $otherToken = $otherUser->api_token;

        // Generate token for current user
        $this->actingAs($this->user)->post('/profile/generate-token');

        // Verify other user's token is unchanged
        $otherUser->refresh();
        $this->assertEquals($otherToken, $otherUser->api_token);
    }

    /** @test */
    public function it_profile_route_is_named(): void
    {
        $response = $this->actingAs($this->user)->get(route('profile.show'));

        $response->assertStatus(200);
    }

    /** @test */
    public function it_generate_token_route_is_named(): void
    {
        $response = $this->actingAs($this->user)->post(route('profile.generate-token'));

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    /** @test */
    public function it_revoke_token_route_is_named(): void
    {
        $this->user->generateApiToken();

        $response = $this->actingAs($this->user)->post(route('profile.revoke-token'));

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    /** @test */
    public function it_displays_profile_for_user_with_existing_token(): void
    {
        $this->user->generateApiToken();

        $response = $this->actingAs($this->user)->get('/profile');

        $response->assertStatus(200);
        $response->assertViewIs('profile.show');
        $response->assertViewHas('user', function ($viewUser) {
            return $viewUser->api_token !== null;
        });
    }

    /** @test */
    public function it_displays_profile_for_user_without_token(): void
    {
        $this->assertNull($this->user->api_token);

        $response = $this->actingAs($this->user)->get('/profile');

        $response->assertStatus(200);
        $response->assertViewIs('profile.show');
        $response->assertViewHas('user', function ($viewUser) {
            return $viewUser->api_token === null;
        });
    }
}
