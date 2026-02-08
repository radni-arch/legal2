<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * UserTestSeeder
 *
 * Seeds test users for testing and development.
 * Creates an admin user and a configurable number of regular users.
 *
 * Usage:
 *   php artisan db:seed --class=UserTestSeeder
 *
 * Or in tests:
 *   $this->seed(UserTestSeeder::class);
 */
class UserTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Fixed count for test seeder: 1 admin + 4 regular = 5 total
        // This is a TEST seeder, so we keep it small
        $regularUserCount = 4;

        // Create admin user (idempotent - won't duplicate if run twice)
        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Test Admin',
                'password' => bcrypt('password'),
            ]
        );

        // Count existing non-admin users
        $existingCount = User::where('email', '!=', 'admin@example.com')->count();
        $needed = max(0, $regularUserCount - $existingCount);

        // Create regular users only if needed
        if ($needed > 0) {
            User::factory()->count($needed)->create();
        }

        if ($this->command) {
            $totalCount = User::count();
            $this->command->info(sprintf(
                '✓ Users ready: %d total (1 admin + %d regular)',
                $totalCount,
                $totalCount - 1
            ));
        }
    }
}
