<?php

namespace Tests\Unit\Policies;

use App\Models\AgentRun;
use App\Models\User;
use App\Policies\AgentRunPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentRunPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected AgentRunPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new AgentRunPolicy;
    }

    /** @test */
    public function lawyers_can_view_any_agent_runs(): void
    {
        $lawyer = User::factory()->create(['role' => 'lawyer']);

        $this->assertTrue($this->policy->viewAny($lawyer));
    }

    /** @test */
    public function non_lawyers_cannot_view_any_agent_runs(): void
    {
        $user = User::factory()->create(['role' => 'client']);

        $this->assertFalse($this->policy->viewAny($user));
    }

    /** @test */
    public function lawyers_can_view_individual_agent_runs(): void
    {
        $lawyer = User::factory()->create(['role' => 'lawyer']);
        $agentRun = AgentRun::factory()->create();

        $this->assertTrue($this->policy->view($lawyer, $agentRun));
    }

    /** @test */
    public function lawyers_can_create_agent_runs(): void
    {
        $lawyer = User::factory()->create(['role' => 'lawyer']);

        $this->assertTrue($this->policy->create($lawyer));
    }

    /** @test */
    public function only_admin_can_update_agent_runs(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lawyer = User::factory()->create(['role' => 'lawyer']);
        $agentRun = AgentRun::factory()->create();

        $this->assertTrue($this->policy->update($admin, $agentRun));
        $this->assertFalse($this->policy->update($lawyer, $agentRun));
    }

    /** @test */
    public function only_admin_can_delete_agent_runs(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lawyer = User::factory()->create(['role' => 'lawyer']);
        $agentRun = AgentRun::factory()->create();

        $this->assertTrue($this->policy->delete($admin, $agentRun));
        $this->assertFalse($this->policy->delete($lawyer, $agentRun));
    }

    /** @test */
    public function lawyers_can_cancel_running_agents(): void
    {
        $lawyer = User::factory()->create(['role' => 'lawyer']);
        $runningAgent = AgentRun::factory()->create(['status' => 'running']);
        $completedAgent = AgentRun::factory()->create(['status' => 'completed']);

        $this->assertTrue($this->policy->cancel($lawyer, $runningAgent));
        $this->assertFalse($this->policy->cancel($lawyer, $completedAgent));
    }

    /** @test */
    public function lawyers_can_cancel_paused_agents(): void
    {
        $lawyer = User::factory()->create(['role' => 'lawyer']);
        $pausedAgent = AgentRun::factory()->create(['status' => 'paused']);

        $this->assertTrue($this->policy->cancel($lawyer, $pausedAgent));
    }

    /** @test */
    public function lawyers_can_resume_paused_agents_with_checkpoint(): void
    {
        $lawyer = User::factory()->create(['role' => 'lawyer']);
        $pausedAgent = AgentRun::factory()->create([
            'status' => 'paused',
            'can_resume' => true,
            'checkpoint_state' => ['iteration' => 3, 'data' => 'test'],
        ]);

        $this->assertTrue($this->policy->resume($lawyer, $pausedAgent));
    }

    /** @test */
    public function cannot_resume_agent_without_checkpoint(): void
    {
        $lawyer = User::factory()->create(['role' => 'lawyer']);
        $agent = AgentRun::factory()->create([
            'status' => 'paused',
            'can_resume' => false,
            'checkpoint_state' => null,
        ]);

        $this->assertFalse($this->policy->resume($lawyer, $agent));
    }

    /** @test */
    public function cannot_resume_completed_agent(): void
    {
        $lawyer = User::factory()->create(['role' => 'lawyer']);
        $completedAgent = AgentRun::factory()->create([
            'status' => 'completed',
            'can_resume' => true,
            'checkpoint_state' => ['data' => 'test'],
        ]);

        $this->assertFalse($this->policy->resume($lawyer, $completedAgent));
    }
}
