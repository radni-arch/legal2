<?php

namespace Tests\Unit\Jobs;

use App\Jobs\MatchCaseToDecisionJob;
use App\Models\CourtCase;
use App\Models\CourtCaseDecisionMatch;
use App\Models\CourtDecision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchCaseToDecisionJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_creates_match_when_decision_exists(): void
    {
        $case = CourtCase::factory()->create([
            'case_number' => 'Pp Prz-75/2025',
            'year' => 2025,
        ]);

        CourtDecision::factory()->create([
            'case_number' => 'Pp Prz-75/2025',
            'decision_date' => '2025-06-15',
        ]);

        $job = new MatchCaseToDecisionJob($case);
        $job->handle(app(\App\Services\CaseDecisionMatchingService::class));

        $this->assertDatabaseHas('court_case_decision_matches', [
            'court_case_id' => $case->id,
        ]);
    }

    public function test_job_handles_no_match_gracefully(): void
    {
        $case = CourtCase::factory()->create([
            'case_number' => 'Pp Prz-999/2025',
        ]);

        $job = new MatchCaseToDecisionJob($case);
        $job->handle(app(\App\Services\CaseDecisionMatchingService::class));

        $this->assertDatabaseMissing('court_case_decision_matches', [
            'court_case_id' => $case->id,
        ]);
    }
}
