<?php

namespace Tests\Feature;

use App\Models\DocumentGenerationRun;
use App\Services\LegalArtillery\EscalationLadderSuggester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalArtilleryEscalationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_escalation_flow_records_next_rung_from_suggestion(): void
    {
        config([
            'escalation-ladders' => [
                'ladders' => [
                    'default' => [
                        [
                            'profile' => 'predsjednik_suda',
                            'min_wait_days' => 0,
                        ],
                        [
                            'profile' => 'dorh_production',
                            'reason' => 'Escalate to DORH after denial.',
                        ],
                    ],
                ],
            ],
        ]);

        $suggester = new EscalationLadderSuggester();
        $suggestion = $suggester->suggestNextRung(
            failedProfileKey: 'predsjednik_suda',
            responseType: 'denied',
            elapsedDays: 3,
            ladderKey: 'default',
        );

        $this->assertSame('dorh_production', $suggestion['next_profile_key']);
        $this->assertSame('Escalate to DORH after denial.', $suggestion['reason']);

        $run = DocumentGenerationRun::factory()->create(['model_config' => []]);
        $run->updateEscalationState([
            'last_action' => 'suggested',
            'last_response' => 'denied',
            'next_rung' => $suggestion['next_profile_key'],
        ]);

        $run->refresh();
        $state = $run->getEscalationState();

        $this->assertSame('suggested', $state['last_action']);
        $this->assertSame('denied', $state['last_response']);
        $this->assertSame('dorh_production', $state['next_rung']);
    }

    public function test_escalation_flow_reports_missing_ladder_configuration(): void
    {
        config(['escalation-ladders' => []]);

        $suggester = new EscalationLadderSuggester();
        $suggestion = $suggester->suggestNextRung(
            failedProfileKey: 'predsjednik_suda',
            responseType: 'denied',
            elapsedDays: 1,
            ladderKey: 'missing',
        );

        $this->assertNull($suggestion['next_profile_key']);
        $this->assertSame('Escalation ladder not configured.', $suggestion['reason']);
    }

    public function test_escalation_flow_enforces_minimum_wait_days(): void
    {
        config([
            'escalation-ladders' => [
                'ladders' => [
                    'default' => [
                        [
                            'profile' => 'predsjednik_suda',
                            'min_wait_days' => 5,
                        ],
                        [
                            'profile' => 'dorh_production',
                            'reason' => 'Escalate to DORH after delay.',
                        ],
                    ],
                ],
            ],
        ]);

        $suggester = new EscalationLadderSuggester();
        $suggestion = $suggester->suggestNextRung(
            failedProfileKey: 'predsjednik_suda',
            responseType: 'denied',
            elapsedDays: 2,
            ladderKey: 'default',
        );

        $this->assertNull($suggestion['next_profile_key']);
        $this->assertSame('Minimum wait period has not elapsed.', $suggestion['reason']);
        $this->assertSame(3, $suggestion['wait_days_remaining']);
    }
}
