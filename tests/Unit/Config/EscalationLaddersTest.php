<?php

namespace Tests\Unit\Config;

use App\Agents\LegalArtilleryAgent;
use App\Agents\LegalArtilleryOrchestrator;
use App\Models\DocumentGenerationRun;
use App\Services\EscalationLadderSuggester;
use App\Services\LegalArtillery\DocxRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\TestCase;

class EscalationLaddersTest extends TestCase
{
    use RefreshDatabase;

    public function test_selects_next_rung_from_configured_hierarchy(): void
    {
        $suggester = new EscalationLadderSuggester();
        $hierarchy = config('escalation-ladders.hierarchy');

        $this->assertIsArray($hierarchy);
        $this->assertNotEmpty($hierarchy);
        $this->assertSame($hierarchy, $suggester->hierarchy());
        $this->assertSame($hierarchy[0], $suggester->suggestNext(null));
        $this->assertSame($hierarchy[1], $suggester->suggestNext($hierarchy[0]));
        $this->assertTrue($suggester->isTerminal($hierarchy[count($hierarchy) - 1]));
    }

    public function test_allows_custom_hierarchy_configuration(): void
    {
        $custom = ['alpha', 'beta'];
        $suggester = new EscalationLadderSuggester($custom);

        $this->assertSame($custom, $suggester->hierarchy());
        $this->assertSame('alpha', $suggester->suggestNext(null));
        $this->assertSame('beta', $suggester->suggestNext('alpha'));
        $this->assertTrue($suggester->isTerminal('beta'));
    }

    public function test_reads_hierarchy_from_configuration(): void
    {
        config(['escalation-ladders.hierarchy' => ['alpha', 'beta', 'gamma']]);

        $suggester = new EscalationLadderSuggester();

        $this->assertSame(['alpha', 'beta', 'gamma'], $suggester->hierarchy());
        $this->assertSame('alpha', $suggester->suggestNext(null));
        $this->assertSame('beta', $suggester->suggestNext('alpha'));
        $this->assertSame('gamma', $suggester->suggestNext('beta'));
        $this->assertTrue($suggester->isTerminal('gamma'));
    }

    public function test_run_metadata_logging_filters_and_timestamps_state_updates(): void
    {
        Carbon::setTestNow('2024-01-01T12:00:00+00:00');

        $run = DocumentGenerationRun::factory()->create();
        $agent = new LegalArtilleryAgent(
            Mockery::mock(LegalArtilleryOrchestrator::class),
            Mockery::mock(DocxRenderer::class),
        );

        $agent->updateEscalationState($run->id, [
            'last_action' => 'suggested',
            'next_rung' => 'ombudsman',
            'unexpected_key' => 'ignored',
        ]);

        $run->refresh();
        $state = $run->getEscalationState();

        $this->assertSame('suggested', $state['last_action']);
        $this->assertSame('ombudsman', $state['next_rung']);
        $this->assertSame('2024-01-01T12:00:00+00:00', $state['updated_at']);
        $this->assertArrayNotHasKey('unexpected_key', $state);

        Carbon::setTestNow();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
