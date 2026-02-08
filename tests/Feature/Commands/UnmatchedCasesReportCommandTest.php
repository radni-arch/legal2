<?php

namespace Tests\Feature\Commands;

use App\Models\CourtCase;
use App\Models\CourtCaseDecisionMatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnmatchedCasesReportCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_runs_successfully(): void
    {
        $this->artisan('cases:unmatched-report')
            ->assertExitCode(0);
    }

    public function test_command_shows_unmatched_cases(): void
    {
        $unmatched = CourtCase::factory()->create([
            'case_number' => 'Pp Prz-99/2025',
        ]);

        $matched = CourtCase::factory()->create([
            'case_number' => 'Pp Prz-100/2025',
        ]);

        CourtCaseDecisionMatch::factory()->create([
            'court_case_id' => $matched->id,
        ]);

        $this->artisan('cases:unmatched-report')
            ->expectsOutputToContain('Pp Prz-99/2025')
            ->assertExitCode(0);
    }

    public function test_command_exports_to_json(): void
    {
        CourtCase::factory()->create();

        $outputPath = storage_path('app/test-unmatched.json');

        $this->artisan("cases:unmatched-report --format=json --output={$outputPath}")
            ->assertExitCode(0);

        $this->assertFileExists($outputPath);

        unlink($outputPath);
    }
}
