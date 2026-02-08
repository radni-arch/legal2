<?php

namespace Tests\Feature\Authorization;

use App\Models\AgentRun;
use App\Models\User;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class AgentAuthorizationTest extends TestCase
{
    use UsesTestDatabase;

    public function test_user_can_view_own_agent_run(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);
        $agentRun = AgentRun::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($user->can('view', $agentRun));
    }

    public function test_user_cannot_view_other_users_agent_run(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);
        $otherRun = AgentRun::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->assertFalse($user->can('view', $otherRun));
    }

    public function test_admin_can_view_any_agent_run(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $agentRun = AgentRun::factory()->create();

        $this->assertTrue($admin->can('view', $agentRun));
    }

    public function test_viewer_cannot_create_agent_run(): void
    {
        $viewer = User::factory()->create(['role' => 'viewer']);

        $this->assertFalse($viewer->can('create', AgentRun::class));
    }

    public function test_lawyer_can_create_agent_run(): void
    {
        $lawyer = User::factory()->create(['role' => 'lawyer']);

        $this->assertTrue($lawyer->can('create', AgentRun::class));
    }

    public function test_assistant_can_create_agent_run(): void
    {
        $assistant = User::factory()->create(['role' => 'assistant']);

        $this->assertTrue($assistant->can('create', AgentRun::class));
    }

    public function test_admin_can_create_agent_run(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->assertTrue($admin->can('create', AgentRun::class));
    }

    public function test_owner_can_update_own_agent_run(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);
        $agentRun = AgentRun::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($user->can('update', $agentRun));
    }

    public function test_user_cannot_update_other_users_agent_run(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);
        $otherRun = AgentRun::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->assertFalse($user->can('update', $otherRun));
    }

    public function test_admin_can_update_any_agent_run(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $agentRun = AgentRun::factory()->create();

        $this->assertTrue($admin->can('update', $agentRun));
    }

    public function test_owner_can_delete_own_agent_run(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);
        $agentRun = AgentRun::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($user->can('delete', $agentRun));
    }

    public function test_user_cannot_delete_other_users_agent_run(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);
        $otherRun = AgentRun::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->assertFalse($user->can('delete', $otherRun));
    }

    public function test_admin_can_delete_any_agent_run(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $agentRun = AgentRun::factory()->create();

        $this->assertTrue($admin->can('delete', $agentRun));
    }

    public function test_only_admin_can_force_delete_agent_run(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'lawyer']);
        $agentRun = AgentRun::factory()->create(['user_id' => $owner->id]);

        $this->assertTrue($admin->can('forceDelete', $agentRun));
        $this->assertFalse($owner->can('forceDelete', $agentRun));
    }

    public function test_all_authenticated_users_can_view_any_agent_runs(): void
    {
        $user = User::factory()->create(['role' => 'viewer']);

        $this->assertTrue($user->can('viewAny', AgentRun::class));
    }

    public function test_legacy_agent_run_without_owner_only_viewable_by_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'lawyer']);
        $legacyRun = AgentRun::factory()->create(['user_id' => null]);

        $this->assertTrue($admin->can('view', $legacyRun));
        $this->assertFalse($user->can('view', $legacyRun));
    }

    public function test_admin_has_full_access_to_all_agent_runs(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $agentRun = AgentRun::factory()->create();

        $this->assertTrue($admin->can('viewAny', AgentRun::class));
        $this->assertTrue($admin->can('view', $agentRun));
        $this->assertTrue($admin->can('create', AgentRun::class));
        $this->assertTrue($admin->can('update', $agentRun));
        $this->assertTrue($admin->can('delete', $agentRun));
        $this->assertTrue($admin->can('forceDelete', $agentRun));
    }

    public function test_viewer_has_limited_access_to_agent_runs(): void
    {
        $viewer = User::factory()->create(['role' => 'viewer']);
        $ownRun = AgentRun::factory()->create(['user_id' => $viewer->id]);
        $otherRun = AgentRun::factory()->create();

        // Can view their own
        $this->assertTrue($viewer->can('view', $ownRun));

        // Cannot view others
        $this->assertFalse($viewer->can('view', $otherRun));

        // Cannot create
        $this->assertFalse($viewer->can('create', AgentRun::class));

        // Can update their own (pause/resume)
        $this->assertTrue($viewer->can('update', $ownRun));

        // Can delete their own
        $this->assertTrue($viewer->can('delete', $ownRun));

        // Cannot force delete
        $this->assertFalse($viewer->can('forceDelete', $ownRun));
    }
}
