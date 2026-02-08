<?php

namespace Tests\Unit\Events;

use App\Events\CaseDecisionMatched;
use App\Events\CaseDecisionMatchVerified;
use App\Models\CourtCaseDecisionMatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseDecisionEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_case_decision_matched_event_contains_match(): void
    {
        $match = CourtCaseDecisionMatch::factory()->create();
        $event = new CaseDecisionMatched($match);

        $this->assertEquals($match->id, $event->match->id);
    }

    public function test_case_decision_match_verified_event_contains_match_and_status(): void
    {
        $match = CourtCaseDecisionMatch::factory()->create();
        $event = new CaseDecisionMatchVerified($match, 'verified');

        $this->assertEquals($match->id, $event->match->id);
        $this->assertEquals('verified', $event->status);
    }
}
