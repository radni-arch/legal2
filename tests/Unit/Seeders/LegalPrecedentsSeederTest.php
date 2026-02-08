<?php

namespace Tests\Unit\Seeders;

use App\Models\LegalPrecedent;
use Database\Seeders\LegalPrecedentsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPrecedentsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_reseed_with_changed_case_number_updates_existing_echr_entry(): void
    {
        // Simulate the OLD seeder having created a row with the old spelling
        LegalPrecedent::create([
            'case_number' => 'Doroz v. Poland',  // old spelling (no diacritical)
            'court' => 'ECHR',
            'court_full' => 'European Court of Human Rights',
            'decision_date' => '2020-10-29',
            'echr_app_number' => '71205/11',
            'legal_issue' => 'Search proportionality',
            'key_holding' => 'A search not justified...',
            'strength' => 'strong',
            'articles_interpreted' => ['ECHR Art.8'],
            'argument_types' => ['unlawful_search'],
            'tags' => ['echr_application', 'home_search'],
        ]);

        $this->assertSame(1, LegalPrecedent::where('echr_app_number', '71205/11')->count());

        // Run the seeder again (which now has the corrected spelling)
        $this->seed(LegalPrecedentsSeeder::class);

        // Should NOT create a duplicate — should update the existing row
        $matchingRows = LegalPrecedent::where('echr_app_number', '71205/11')->get();
        $this->assertCount(
            1,
            $matchingRows,
            "Expected 1 row for echr_app_number 71205/11 but found {$matchingRows->count()}. "
            . 'Reseed with changed case_number created a duplicate.'
        );

        // The case_number should be updated to the new spelling
        $this->assertSame('Dorož v. Poland', $matchingRows->first()->case_number);
    }

    public function test_reseed_is_idempotent_for_all_entries(): void
    {
        // Run the seeder twice
        $this->seed(LegalPrecedentsSeeder::class);
        $countAfterFirst = LegalPrecedent::count();

        $this->seed(LegalPrecedentsSeeder::class);
        $countAfterSecond = LegalPrecedent::count();

        $this->assertSame(
            $countAfterFirst,
            $countAfterSecond,
            "Seeder is not idempotent: {$countAfterFirst} rows after first run, {$countAfterSecond} after second."
        );
    }
}
