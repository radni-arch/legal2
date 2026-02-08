<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ReconcileUnmatchedCasesJob;
use App\Models\CourtCase;
use App\Models\CourtDecision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconcileUnmatchedCasesJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_processes_unmatched_cases(): void
    {
        $case = CourtCase::factory()->create([
            'case_number' => 'Pp Prz-75/2025',
            'year' => 2025,
        ]);

        CourtDecision::factory()->create([
            'case_number' => 'Pp Prz-75/2025',
            'decision_date' => '2025-06-15',
        ]);

        $job = new ReconcileUnmatchedCasesJob(100);
        $job->handle(app(\App\Services\CaseDecisionMatchingService::class));

        $this->assertDatabaseHas('court_case_decision_matches', [
            'court_case_id' => $case->id,
            'match_source' => 'scheduled_reconciliation',
        ]);
    }
}
