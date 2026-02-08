<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * ReferenceDataSeeder
 *
 * Seeds reference/lookup data that other entities depend on.
 * This includes keywords, taxonomies, and other static reference data.
 *
 * Usage:
 *   php artisan db:seed --class=ReferenceDataSeeder
 *
 * Or in tests:
 *   $this->seed(ReferenceDataSeeder::class);
 */
class ReferenceDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if ($this->command) {
            $this->command->info('Seeding reference data...');
        }

        // Seed Eoglasna keywords
        $this->call(EoglasnaKeywordSeeder::class);

        // Add other reference data seeders here as needed:
        // - Roles and permissions
        // - Categories
        // - Tags
        // - Other lookup tables

        if ($this->command) {
            $this->command->info('✓ Reference data seeding completed');
        }
    }
}
