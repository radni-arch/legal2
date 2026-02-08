<?php

namespace Tests\Unit\Seeders;

use App\Models\EoglasnaKeyword;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferenceDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_eoglasna_keywords(): void
    {
        $this->seed(ReferenceDataSeeder::class);

        // Should create Eoglasna keywords
        $this->assertGreaterThan(0, EoglasnaKeyword::count());
    }

    public function test_seeder_creates_expected_keywords(): void
    {
        $this->seed(ReferenceDataSeeder::class);

        // Check for some expected keywords
        $this->assertDatabaseHas('eoglasna_keywords', [
            'query' => 'Andrija Glavaš',
            'scope' => 'notice',
        ]);
    }

    public function test_seeder_is_idempotent(): void
    {
        // Run seeder first time
        $this->seed(ReferenceDataSeeder::class);
        $firstCount = EoglasnaKeyword::count();

        // Run seeder second time
        $this->seed(ReferenceDataSeeder::class);
        $secondCount = EoglasnaKeyword::count();

        // Count should remain the same (idempotent)
        $this->assertEquals($firstCount, $secondCount);
    }
}
