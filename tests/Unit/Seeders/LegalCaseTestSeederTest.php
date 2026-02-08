<?php

namespace Tests\Unit\Seeders;

use App\Models\CaseDocument;
use App\Models\LegalCase;
use Database\Seeders\LegalCaseTestSeeder;
use Database\Seeders\UserTestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalCaseTestSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed users first (legal cases need users)
        $this->seed(UserTestSeeder::class);
    }

    public function test_seeder_creates_legal_cases(): void
    {
        $this->seed(LegalCaseTestSeeder::class);

        // Should create at least 3 legal cases for testing
        $this->assertGreaterThanOrEqual(3, LegalCase::count());
    }

    public function test_seeder_creates_case_documents(): void
    {
        $this->seed(LegalCaseTestSeeder::class);

        // Should create case documents for the cases
        $this->assertGreaterThan(0, CaseDocument::count());
    }

    public function test_legal_cases_have_valid_attributes(): void
    {
        $this->seed(LegalCaseTestSeeder::class);

        $cases = LegalCase::all();

        foreach ($cases as $case) {
            $this->assertNotEmpty($case->case_number);
            $this->assertNotEmpty($case->title);
            $this->assertNotEmpty($case->client_name);
        }
    }

    public function test_seeder_is_idempotent(): void
    {
        // Run seeder first time
        $this->seed(LegalCaseTestSeeder::class);
        $firstCaseCount = LegalCase::count();
        $firstDocCount = CaseDocument::count();

        // Run seeder second time - should not create duplicates if case numbers are unique
        $this->seed(LegalCaseTestSeeder::class);
        $secondCaseCount = LegalCase::count();
        $secondDocCount = CaseDocument::count();

        // Counts should remain the same
        $this->assertEquals($firstCaseCount, $secondCaseCount);
        $this->assertEquals($firstDocCount, $secondDocCount);
    }
}
