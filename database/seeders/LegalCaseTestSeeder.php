<?php

namespace Database\Seeders;

use App\Models\CaseDocument;
use App\Models\LegalCase;
use Illuminate\Database\Seeder;

/**
 * LegalCaseTestSeeder
 *
 * Seeds test legal cases and their documents.
 * Creates a fixed number of cases with associated documents.
 *
 * Usage:
 *   php artisan db:seed --class=LegalCaseTestSeeder
 *
 * Or in tests:
 *   $this->seed(LegalCaseTestSeeder::class);
 */
class LegalCaseTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Fixed count for test seeder: 3 cases
        $caseCount = 3;
        $documentsPerCase = 2;

        // Create test cases with unique case numbers for idempotency
        for ($i = 1; $i <= $caseCount; $i++) {
            $caseNumber = sprintf('TEST-CASE-%04d', $i);

            // Use firstOrCreate for idempotency
            $case = LegalCase::firstOrCreate(
                ['case_number' => $caseNumber],
                [
                    'title' => "Test Legal Case {$i}",
                    'description' => "This is a test legal case number {$i} for testing purposes.",
                    'client_name' => "Test Client {$i}",
                    'opponent_name' => "Test Opponent {$i}",
                    'jurisdiction' => 'HR',
                    'court' => 'Vrhovni sud Republike Hrvatske',
                    'status' => 'active',
                    'filing_date' => now()->subDays($i * 10),
                    'tags' => ['test', 'seeded'],
                ]
            );

            // Create documents for this case if they don't exist
            $existingDocs = CaseDocument::where('case_id', $case->id)->count();
            $neededDocs = max(0, $documentsPerCase - $existingDocs);

            if ($neededDocs > 0) {
                for ($j = 1; $j <= $neededDocs; $j++) {
                    CaseDocument::factory()->create([
                        'case_id' => $case->id,
                        'title' => "Test Document {$j} for Case {$i}",
                        'category' => 'evidence',
                    ]);
                }
            }
        }

        if ($this->command) {
            $this->command->info(sprintf(
                '✓ Legal cases ready: %d cases with %d documents each',
                LegalCase::count(),
                $documentsPerCase
            ));
        }
    }
}
