<?php

namespace Tests\Unit\Models;

use App\Models\Court;
use App\Models\CourtCase;
use App\Models\CourtCaseDecisionMatch;
use App\Models\CourtDecision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourtCaseDecisionMatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_match_can_be_created_with_required_attributes(): void
    {
        $match = CourtCaseDecisionMatch::create([
            'court_case_id' => 1,
            'court_decision_id' => '01HQXYZ123456789ABCDEFGH',
            'matched_at' => now(),
            'match_type' => 'auto',
            'match_confidence' => 100,
            'match_source' => 'scheduled_job',
            'verification_status' => 'pending',
            'match_criteria' => ['case_number' => 'Pp Prz-75', 'year' => 2025],
        ]);

        $this->assertNotNull($match->id);
        $this->assertEquals(100, $match->match_confidence);
        $this->assertIsArray($match->match_criteria);
    }

    public function test_court_case_has_decision_matches_relationship(): void
    {
        $court = Court::create(['external_id' => 1001, 'name' => 'Test Court', 'code' => 'TC01']);

        $case = CourtCase::create([
            'court_id' => $court->id,
            'case_number' => 'Pp Prz-75/2025',
            'register' => 'Pp Prz',
            'number' => 75,
            'year' => 2025,
        ]);

        $match = CourtCaseDecisionMatch::create([
            'court_case_id' => $case->id,
            'court_decision_id' => '01HQXYZ123456789ABCDEFGH',
            'matched_at' => now(),
            'match_type' => 'auto',
            'match_confidence' => 100,
            'match_source' => 'test',
            'verification_status' => 'verified',
            'match_criteria' => ['case_number' => 'Pp Prz-75', 'year' => 2025],
        ]);

        $this->assertTrue($case->decisionMatches->contains($match));
    }

    public function test_court_case_unmatched_scope_excludes_matched_cases(): void
    {
        $court = Court::create(['external_id' => 1002, 'name' => 'Test Court', 'code' => 'TC02']);

        $matchedCase = CourtCase::create([
            'court_id' => $court->id,
            'case_number' => 'Pp Prz-76/2025',
            'register' => 'Pp Prz',
            'number' => 76,
            'year' => 2025,
        ]);

        $unmatchedCase = CourtCase::create([
            'court_id' => $court->id,
            'case_number' => 'Pp Prz-77/2025',
            'register' => 'Pp Prz',
            'number' => 77,
            'year' => 2025,
        ]);

        CourtCaseDecisionMatch::create([
            'court_case_id' => $matchedCase->id,
            'court_decision_id' => '01HQXYZ123456789ABCDEFGH',
            'matched_at' => now(),
            'match_type' => 'auto',
            'match_confidence' => 100,
            'match_source' => 'test',
            'verification_status' => 'verified',
            'match_criteria' => [],
        ]);

        $unmatched = CourtCase::unmatched()->get();

        $this->assertFalse($unmatched->contains($matchedCase));
        $this->assertTrue($unmatched->contains($unmatchedCase));
    }

    public function test_court_case_pending_review_scope(): void
    {
        $court = Court::create(['external_id' => 1003, 'name' => 'Test Court', 'code' => 'TC03']);

        $pendingCase = CourtCase::create([
            'court_id' => $court->id,
            'case_number' => 'Pp Prz-78/2025',
            'register' => 'Pp Prz',
            'number' => 78,
            'year' => 2025,
        ]);

        $verifiedCase = CourtCase::create([
            'court_id' => $court->id,
            'case_number' => 'Pp Prz-79/2025',
            'register' => 'Pp Prz',
            'number' => 79,
            'year' => 2025,
        ]);

        CourtCaseDecisionMatch::create([
            'court_case_id' => $pendingCase->id,
            'court_decision_id' => '01HQXYZ123456789ABCDEFGH',
            'matched_at' => now(),
            'match_type' => 'auto',
            'match_confidence' => 60,
            'match_source' => 'test',
            'verification_status' => 'pending',
            'match_criteria' => [],
        ]);

        CourtCaseDecisionMatch::create([
            'court_case_id' => $verifiedCase->id,
            'court_decision_id' => '01HQXYZ987654321ABCDEFGH',
            'matched_at' => now(),
            'match_type' => 'auto',
            'match_confidence' => 100,
            'match_source' => 'test',
            'verification_status' => 'verified',
            'match_criteria' => [],
        ]);

        $pending = CourtCase::pendingReview()->get();

        $this->assertTrue($pending->contains($pendingCase));
        $this->assertFalse($pending->contains($verifiedCase));
    }

    public function test_court_decision_has_case_matches_relationship(): void
    {
        $decision = CourtDecision::factory()->create();
        $court = Court::create(['external_id' => 1004, 'name' => 'Test Court', 'code' => 'TC04']);

        $case = CourtCase::create([
            'court_id' => $court->id,
            'case_number' => 'Pp Prz-80/2025',
            'register' => 'Pp Prz',
            'number' => 80,
            'year' => 2025,
        ]);

        $match = CourtCaseDecisionMatch::create([
            'court_case_id' => $case->id,
            'court_decision_id' => $decision->id,
            'matched_at' => now(),
            'match_type' => 'auto',
            'match_confidence' => 100,
            'match_source' => 'test',
            'verification_status' => 'verified',
            'match_criteria' => [],
        ]);

        $this->assertTrue($decision->caseMatches->contains($match));
    }
}
