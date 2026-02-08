<?php

namespace Tests\Feature\Authorization;

use App\Models\CourtDecision;
use App\Models\User;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class DecisionAuthorizationTest extends TestCase
{
    use UsesTestDatabase;

    public function test_all_users_can_view_court_decisions(): void
    {
        $user = User::factory()->create(['role' => 'viewer']);
        $decision = CourtDecision::factory()->create();

        $this->assertTrue($user->can('view', $decision));
    }

    public function test_lawyers_can_view_court_decisions(): void
    {
        $lawyer = User::factory()->create(['role' => 'lawyer']);
        $decision = CourtDecision::factory()->create();

        $this->assertTrue($lawyer->can('view', $decision));
    }

    public function test_assistants_can_view_court_decisions(): void
    {
        $assistant = User::factory()->create(['role' => 'assistant']);
        $decision = CourtDecision::factory()->create();

        $this->assertTrue($assistant->can('view', $decision));
    }

    public function test_admins_can_view_court_decisions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $decision = CourtDecision::factory()->create();

        $this->assertTrue($admin->can('view', $decision));
    }

    public function test_all_users_can_view_any_court_decisions(): void
    {
        $user = User::factory()->create(['role' => 'viewer']);

        $this->assertTrue($user->can('viewAny', CourtDecision::class));
    }

    public function test_only_admin_can_create_court_decision(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lawyer = User::factory()->create(['role' => 'lawyer']);
        $assistant = User::factory()->create(['role' => 'assistant']);
        $viewer = User::factory()->create(['role' => 'viewer']);

        $this->assertTrue($admin->can('create', CourtDecision::class));
        $this->assertFalse($lawyer->can('create', CourtDecision::class));
        $this->assertFalse($assistant->can('create', CourtDecision::class));
        $this->assertFalse($viewer->can('create', CourtDecision::class));
    }

    public function test_only_admin_can_update_court_decision(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lawyer = User::factory()->create(['role' => 'lawyer']);
        $decision = CourtDecision::factory()->create();

        $this->assertTrue($admin->can('update', $decision));
        $this->assertFalse($lawyer->can('update', $decision));
    }

    public function test_only_admin_can_delete_court_decision(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lawyer = User::factory()->create(['role' => 'lawyer']);
        $decision = CourtDecision::factory()->create();

        $this->assertTrue($admin->can('delete', $decision));
        $this->assertFalse($lawyer->can('delete', $decision));
    }

    public function test_only_admin_can_restore_court_decision(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lawyer = User::factory()->create(['role' => 'lawyer']);
        $decision = CourtDecision::factory()->create();

        $this->assertTrue($admin->can('restore', $decision));
        $this->assertFalse($lawyer->can('restore', $decision));
    }

    public function test_only_admin_can_force_delete_court_decision(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lawyer = User::factory()->create(['role' => 'lawyer']);
        $decision = CourtDecision::factory()->create();

        $this->assertTrue($admin->can('forceDelete', $decision));
        $this->assertFalse($lawyer->can('forceDelete', $decision));
    }

    public function test_court_decisions_are_public_for_viewing(): void
    {
        $viewer = User::factory()->create(['role' => 'viewer']);
        $lawyer = User::factory()->create(['role' => 'lawyer']);
        $assistant = User::factory()->create(['role' => 'assistant']);
        $admin = User::factory()->create(['role' => 'admin']);
        $decision = CourtDecision::factory()->create();

        // All roles can view
        $this->assertTrue($viewer->can('view', $decision));
        $this->assertTrue($lawyer->can('view', $decision));
        $this->assertTrue($assistant->can('view', $decision));
        $this->assertTrue($admin->can('view', $decision));
    }

    public function test_court_decisions_are_read_only_for_non_admins(): void
    {
        $lawyer = User::factory()->create(['role' => 'lawyer']);
        $decision = CourtDecision::factory()->create();

        // Can view
        $this->assertTrue($lawyer->can('view', $decision));

        // Cannot modify
        $this->assertFalse($lawyer->can('create', CourtDecision::class));
        $this->assertFalse($lawyer->can('update', $decision));
        $this->assertFalse($lawyer->can('delete', $decision));
    }

    public function test_admin_has_full_control_over_court_decisions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $decision = CourtDecision::factory()->create();

        $this->assertTrue($admin->can('viewAny', CourtDecision::class));
        $this->assertTrue($admin->can('view', $decision));
        $this->assertTrue($admin->can('create', CourtDecision::class));
        $this->assertTrue($admin->can('update', $decision));
        $this->assertTrue($admin->can('delete', $decision));
        $this->assertTrue($admin->can('forceDelete', $decision));
    }

    public function test_viewer_has_read_only_access_to_court_decisions(): void
    {
        $viewer = User::factory()->create(['role' => 'viewer']);
        $decision = CourtDecision::factory()->create();

        $this->assertTrue($viewer->can('viewAny', CourtDecision::class));
        $this->assertTrue($viewer->can('view', $decision));
        $this->assertFalse($viewer->can('create', CourtDecision::class));
        $this->assertFalse($viewer->can('update', $decision));
        $this->assertFalse($viewer->can('delete', $decision));
        $this->assertFalse($viewer->can('forceDelete', $decision));
    }

    public function test_assistant_has_read_only_access_to_court_decisions(): void
    {
        $assistant = User::factory()->create(['role' => 'assistant']);
        $decision = CourtDecision::factory()->create();

        $this->assertTrue($assistant->can('viewAny', CourtDecision::class));
        $this->assertTrue($assistant->can('view', $decision));
        $this->assertFalse($assistant->can('create', CourtDecision::class));
        $this->assertFalse($assistant->can('update', $decision));
        $this->assertFalse($assistant->can('delete', $decision));
        $this->assertFalse($assistant->can('forceDelete', $decision));
    }
}
