<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * TestDataSeeder
 *
 * Master test data seeder that calls all sub-seeders in the correct order.
 * Creates a complete test database with users, legal cases, and reference data.
 *
 * Usage:
 *   php artisan db:seed --class=TestDataSeeder
 *
 * Or in tests:
 *   $this->seed(TestDataSeeder::class);
 */
class TestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if ($this->command) {
            $this->command->info('Seeding test data...');
        }

        // Seed in order of dependencies:
        // 1. Reference data (no dependencies)
        // 2. Users (no dependencies)
        // 3. Legal cases (no strict dependencies)

        $this->call([
            ReferenceDataSeeder::class,
            UserTestSeeder::class,
            LegalCaseTestSeeder::class,
        ]);

        if ($this->command) {
            $this->command->info('✓ Test data seeding completed!');
        }
    }
}
