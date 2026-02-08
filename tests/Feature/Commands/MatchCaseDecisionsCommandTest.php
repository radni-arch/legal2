<?php

namespace Tests\Feature\Commands;

use App\Models\CourtCase;
use App\Models\CourtDecision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchCaseDecisionsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_runs_successfully(): void
    {
        $this->artisan('cases:match-decisions')
            ->assertExitCode(0);
    }

    public function test_command_matches_cases_to_decisions(): void
    {
        $case = CourtCase::factory()->create([
            'case_number' => 'Pp Prz-75/2025',
            'year' => 2025,
        ]);

        CourtDecision::factory()->create([
            'case_number' => 'Pp Prz-75/2025',
            'decision_date' => '2025-06-15',
        ]);

        $this->artisan('cases:match-decisions')
            ->assertExitCode(0);

        $this->assertDatabaseHas('court_case_decision_matches', [
            'court_case_id' => $case->id,
        ]);
    }

    public function test_command_respects_dry_run_option(): void
    {
        $case = CourtCase::factory()->create([
            'case_number' => 'Pp Prz-75/2025',
            'year' => 2025,
        ]);

        CourtDecision::factory()->create([
            'case_number' => 'Pp Prz-75/2025',
            'decision_date' => '2025-06-15',
        ]);

        $this->artisan('cases:match-decisions --dry-run')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('court_case_decision_matches', [
            'court_case_id' => $case->id,
        ]);
    }
}
