<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\AgentCollaborationViewer;
use App\Models\AgentCommunication;
use App\Models\OrchestrationLog;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class AgentCollaborationViewerTest extends TestCase
{
    use UsesTestDatabase;

    /** @test */
    public function it_renders_successfully(): void
    {
        // Create an orchestration log
        $log = OrchestrationLog::create([
            'agent_pipeline' => ['Agent1', 'Agent2', 'Agent3'],
            'task_description' => 'Test collaboration',
            'status' => 'running',
            'shared_context' => ['case_id' => 'case-123'],
            'execution_history' => [],
        ]);

        Livewire::test(AgentCollaborationViewer::class, ['orchestrationId' => $log->orchestration_id])
            ->assertStatus(200)
            ->assertSee('Test collaboration');
    }

    /** @test */
    public function it_displays_collaboration_status(): void
    {
        $log = OrchestrationLog::create([
            'agent_pipeline' => ['Agent1'],
            'task_description' => 'Status test',
            'status' => 'completed',
            'shared_context' => [],
            'execution_history' => [],
        ]);

        Livewire::test(AgentCollaborationViewer::class, ['orchestrationId' => $log->orchestration_id])
            ->assertSee('Completed') // Ucfirst in blade
            ->assertSee('Status test');
    }

    /** @test */
    public function it_shows_agent_execution_timeline(): void
    {
        $log = OrchestrationLog::create([
            'agent_pipeline' => ['Agent1', 'Agent2'],
            'task_description' => 'Timeline test',
            'status' => 'completed',
            'shared_context' => [],
            'execution_history' => [
                [
                    'agent' => 'Agent1',
                    'status' => 'completed',
                    'tokens_used' => 100,
                    'cost' => 0.002,
                    'started_at' => now()->subMinutes(5)->toISOString(),
                    'completed_at' => now()->subMinutes(4)->toISOString(),
                ],
                [
                    'agent' => 'Agent2',
                    'status' => 'completed',
                    'tokens_used' => 150,
                    'cost' => 0.003,
                    'started_at' => now()->subMinutes(4)->toISOString(),
                    'completed_at' => now()->subMinutes(3)->toISOString(),
                ],
            ],
        ]);

        Livewire::test(AgentCollaborationViewer::class, ['orchestrationId' => $log->orchestration_id])
            ->assertSee('Agent1')
            ->assertSee('Agent2')
            ->assertSee('Timeline');
    }

    /** @test */
    public function it_displays_shared_context(): void
    {
        $log = OrchestrationLog::create([
            'agent_pipeline' => ['Agent1'],
            'task_description' => 'Context test',
            'status' => 'running',
            'shared_context' => [
                'case_id' => 'case-456',
                'jurisdiction' => 'Osijek',
                'defendant' => 'John Doe',
            ],
            'execution_history' => [],
        ]);

        Livewire::test(AgentCollaborationViewer::class, ['orchestrationId' => $log->orchestration_id])
            ->assertSee('case-456')
            ->assertSee('Osijek')
            ->assertSee('John Doe')
            ->assertSee('Shared Context');
    }

    /** @test */
    public function it_shows_inter_agent_messages(): void
    {
        $log = OrchestrationLog::create([
            'agent_pipeline' => ['Agent1', 'Agent2'],
            'task_description' => 'Messages test',
            'status' => 'running',
            'shared_context' => [],
            'execution_history' => [],
        ]);

        // Create messages (using AgentCommunication structure)
        AgentCommunication::create([
            'sender_agent_type' => 'Agent1',
            'receiver_agent_type' => 'Agent2',
            'message_type' => 'request',
            'message_data' => ['query' => 'Find precedents'],
            'priority' => 10,
            'status' => 'pending',
        ]);

        AgentCommunication::create([
            'sender_agent_type' => 'Agent2',
            'receiver_agent_type' => 'Agent1',
            'message_type' => 'response',
            'message_data' => ['results' => ['precedent-1', 'precedent-2']],
            'priority' => 5,
            'status' => 'completed',
        ]);

        Livewire::test(AgentCollaborationViewer::class, ['orchestrationId' => $log->orchestration_id])
            ->assertSee('Agent1')
            ->assertSee('Agent2')
            ->assertSee('Messages');
    }

    /** @test */
    public function it_refreshes_data_with_polling(): void
    {
        $log = OrchestrationLog::create([
            'agent_pipeline' => ['Agent1'],
            'task_description' => 'Polling test',
            'status' => 'running',
            'shared_context' => [],
            'execution_history' => [],
        ]);

        $component = Livewire::test(AgentCollaborationViewer::class, ['orchestrationId' => $log->orchestration_id])
            ->assertSee('Running'); // Ucfirst in blade

        // Update the log
        $log->update(['status' => 'completed']);

        // Call refresh method
        $component->call('refreshData')
            ->assertSee('Completed'); // Ucfirst in blade
    }

    /** @test */
    public function it_handles_missing_orchestration_gracefully(): void
    {
        Livewire::test(AgentCollaborationViewer::class, ['orchestrationId' => 'non-existent'])
            ->assertSee('Orchestration not found')
            ->assertStatus(200);
    }

    /** @test */
    public function it_displays_execution_metrics(): void
    {
        $log = OrchestrationLog::create([
            'agent_pipeline' => ['Agent1', 'Agent2'],
            'task_description' => 'Metrics test',
            'status' => 'completed',
            'shared_context' => [],
            'execution_history' => [],
            'tokens_used' => 500,
            'cost_spent' => 0.01,
            'duration_ms' => 5000,
            'completed_agents' => 2,
            'failed_agents' => 0,
        ]);

        Livewire::test(AgentCollaborationViewer::class, ['orchestrationId' => $log->orchestration_id])
            ->assertSee('500') // tokens
            ->assertSee('0.0100') // cost (formatted with 4 decimals)
            ->assertSee('5,000') // duration (number_format adds comma)
            ->assertSee('Metrics');
    }
}
