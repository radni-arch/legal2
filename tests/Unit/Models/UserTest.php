<?php

namespace Tests\Unit\Models;

use App\Models\User;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class UserTest extends TestCase
{
    use UsesTestDatabase;

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
    }

    /** @test */
    public function it_hides_sensitive_attributes()
    {
        $user = User::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => bcrypt('secret'),
            'api_token' => 'test-token-12345',
        ]);

        $array = $user->toArray();

        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('remember_token', $array);
        $this->assertArrayNotHasKey('api_token', $array);
    }

    /** @test */
    public function it_casts_email_verified_at_as_datetime()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => '2024-01-15 10:00:00',
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $user->email_verified_at);
    }

    /** @test */
    public function it_generates_api_token()
    {
        $user = User::create([
            'name' => 'API User',
            'email' => 'api@example.com',
            'password' => bcrypt('password'),
        ]);

        $token = $user->generateApiToken();

        $this->assertNotNull($token);
        $this->assertEquals(80, strlen($token)); // bin2hex(40 bytes) = 80 chars
        $this->assertEquals($token, $user->api_token);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'api_token' => $token,
        ]);
    }

    /** @test */
    public function it_revokes_api_token()
    {
        $user = User::create([
            'name' => 'Token User',
            'email' => 'token@example.com',
            'password' => bcrypt('password'),
        ]);

        $user->generateApiToken();
        $this->assertNotNull($user->api_token);

        $user->revokeApiToken();
        $this->assertNull($user->fresh()->api_token);
    }

    /** @test */
    public function it_hashes_password()
    {
        $user = User::create([
            'name' => 'Hash Test',
            'email' => 'hash@example.com',
            'password' => 'plain-password',
        ]);

        $this->assertNotEquals('plain-password', $user->password);
        $this->assertTrue(password_verify('plain-password', $user->password));
    }

    /** @test */
    public function it_has_research_sessions_relationship()
    {
        $user = User::factory()->create();

        // Test that the relationship exists and is the correct type
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\HasMany::class,
            $user->researchSessions()
        );
    }
}
