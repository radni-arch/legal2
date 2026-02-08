<?php

namespace Tests\Unit\Seeders;

use App\Models\CaseDocument;
use App\Models\EoglasnaKeyword;
use App\Models\LegalCase;
use App\Models\User;
use Database\Seeders\TestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_seeder_creates_all_data(): void
    {
        $this->seed(TestDataSeeder::class);

        // Verify users were created
        $this->assertGreaterThanOrEqual(5, User::count());

        // Verify legal cases were created
        $this->assertGreaterThanOrEqual(3, LegalCase::count());

        // Verify case documents were created
        $this->assertGreaterThan(0, CaseDocument::count());

        // Verify reference data was created
        $this->assertGreaterThan(0, EoglasnaKeyword::count());
    }

    public function test_master_seeder_creates_admin_user(): void
    {
        $this->seed(TestDataSeeder::class);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@example.com',
        ]);
    }

    public function test_master_seeder_creates_test_cases(): void
    {
        $this->seed(TestDataSeeder::class);

        $this->assertDatabaseHas('cases', [
            'case_number' => 'TEST-CASE-0001',
        ]);

        $this->assertDatabaseHas('cases', [
            'case_number' => 'TEST-CASE-0002',
        ]);
    }

    public function test_master_seeder_is_idempotent(): void
    {
        // Run seeder first time
        $this->seed(TestDataSeeder::class);
        $firstUserCount = User::count();
        $firstCaseCount = LegalCase::count();
        $firstDocCount = CaseDocument::count();
        $firstKeywordCount = EoglasnaKeyword::count();

        // Run seeder second time
        $this->seed(TestDataSeeder::class);
        $secondUserCount = User::count();
        $secondCaseCount = LegalCase::count();
        $secondDocCount = CaseDocument::count();
        $secondKeywordCount = EoglasnaKeyword::count();

        // All counts should remain the same
        $this->assertEquals($firstUserCount, $secondUserCount);
        $this->assertEquals($firstCaseCount, $secondCaseCount);
        $this->assertEquals($firstDocCount, $secondDocCount);
        $this->assertEquals($firstKeywordCount, $secondKeywordCount);
    }
}
