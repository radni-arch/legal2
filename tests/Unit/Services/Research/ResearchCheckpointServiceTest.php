<?php

namespace Tests\Unit\Services\Research;

use App\Models\AgentRun;
use App\Services\Research\ResearchCheckpointService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ResearchCheckpointServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_saves_checkpoint(): void
    {
        $service = new ResearchCheckpointService();

        $run = AgentRun::factory()->create([
            'status' => 'running',
            'current_iteration' => 2,
        ]);

        $state = [
            'iterations' => [['number' => 1], ['number' => 2]],
            'insights' => [['title' => 'Test']],
        ];

        $service->saveCheckpoint($run, $state);

        $run->refresh();
        $this->assertNotNull($run->checkpoint_state);
        $this->assertEquals(2, $run->checkpoint_state['checkpoint_iteration']);
    }

    #[Test]
    public function it_restores_from_checkpoint(): void
    {
        $service = new ResearchCheckpointService();

        $run = AgentRun::factory()->create([
            'status' => 'paused',
            'checkpoint_state' => [
                'checkpoint_iteration' => 2,
                'iterations' => [['number' => 1], ['number' => 2]],
            ],
        ]);

        $state = $service->restoreCheckpoint($run);

        $this->assertEquals(2, $state['checkpoint_iteration']);
        $this->assertCount(2, $state['iterations']);
    }

    #[Test]
    public function it_detects_resumable_runs(): void
    {
        $service = new ResearchCheckpointService();

        $resumable = AgentRun::factory()->create([
            'status' => 'paused',
            'checkpoint_state' => ['checkpoint_iteration' => 1],
        ]);

        $notResumable = AgentRun::factory()->create([
            'status' => 'completed',
            'checkpoint_state' => null,
        ]);

        $this->assertTrue($service->canResume($resumable));
        $this->assertFalse($service->canResume($notResumable));
    }
}
