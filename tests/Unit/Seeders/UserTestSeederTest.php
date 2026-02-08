<?php

namespace Tests\Unit\Seeders;

use App\Models\User;
use Database\Seeders\UserTestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTestSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_admin_user(): void
    {
        $this->seed(UserTestSeeder::class);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@example.com',
            'name' => 'Test Admin',
        ]);
    }

    public function test_seeder_creates_correct_number_of_users(): void
    {
        $this->seed(UserTestSeeder::class);

        // Should create 1 admin + 4 regular users = 5 total (for testing environment)
        $this->assertEquals(5, User::count());
    }

    public function test_seeder_creates_users_with_valid_email(): void
    {
        $this->seed(UserTestSeeder::class);

        $users = User::all();

        foreach ($users as $user) {
            $this->assertNotEmpty($user->email);
            $this->assertStringContainsString('@', $user->email);
        }
    }

    public function test_seeder_is_idempotent(): void
    {
        // Run seeder first time
        $this->seed(UserTestSeeder::class);
        $firstCount = User::count();

        // Run seeder second time - should not create duplicates
        $this->seed(UserTestSeeder::class);
        $secondCount = User::count();

        // Count should remain the same
        $this->assertEquals($firstCount, $secondCount);
        $this->assertEquals(5, $secondCount); // Should still be 5 total
    }
}
