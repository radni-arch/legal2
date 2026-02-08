<?php

namespace Tests\Feature\Authorization;

use App\Models\LegalCase;
use App\Models\User;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class CaseAuthorizationTest extends TestCase
{
    use UsesTestDatabase;

    public function test_user_can_view_own_case(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);
        $case = LegalCase::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($user->can('view', $case));
    }

    public function test_user_cannot_view_other_users_case(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);
        $otherCase = LegalCase::factory()->create();

        $this->assertFalse($user->can('view', $otherCase));
    }

    public function test_user_can_view_team_case(): void
    {
        $teamId = 1;
        $user = User::factory()->create(['role' => 'lawyer', 'team_id' => $teamId]);
        $case = LegalCase::factory()->create(['team_id' => $teamId]);

        $this->assertTrue($user->can('view', $case));
    }

    public function test_assigned_user_can_view_case(): void
    {
        $user = User::factory()->create(['role' => 'assistant']);
        $case = LegalCase::factory()->create();
        $case->assignedUsers()->attach($user);

        $this->assertTrue($user->can('view', $case));
    }

    public function test_admin_can_view_any_case(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $case = LegalCase::factory()->create();

        $this->assertTrue($admin->can('view', $case));
    }

    public function test_viewer_cannot_create_case(): void
    {
        $viewer = User::factory()->create(['role' => 'viewer']);

        $this->assertFalse($viewer->can('create', LegalCase::class));
    }

    public function test_only_owner_can_delete_case(): void
    {
        $owner = User::factory()->create(['role' => 'lawyer']);
        $assignedUser = User::factory()->create(['role' => 'lawyer']);

        $case = LegalCase::factory()->create(['user_id' => $owner->id]);
        $case->assignedUsers()->attach($assignedUser);

        $this->assertTrue($owner->can('delete', $case));
        $this->assertFalse($assignedUser->can('delete', $case));
    }

    public function test_lawyer_can_create_case(): void
    {
        $lawyer = User::factory()->create(['role' => 'lawyer']);

        $this->assertTrue($lawyer->can('create', LegalCase::class));
    }

    public function test_assistant_can_create_case(): void
    {
        $assistant = User::factory()->create(['role' => 'assistant']);

        $this->assertTrue($assistant->can('create', LegalCase::class));
    }

    public function test_admin_can_create_case(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->assertTrue($admin->can('create', LegalCase::class));
    }

    public function test_owner_can_update_own_case(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);
        $case = LegalCase::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($user->can('update', $case));
    }

    public function test_team_member_can_update_team_case(): void
    {
        $teamId = 1;
        $user = User::factory()->create(['role' => 'lawyer', 'team_id' => $teamId]);
        $case = LegalCase::factory()->create(['team_id' => $teamId]);

        $this->assertTrue($user->can('update', $case));
    }

    public function test_assigned_user_can_update_case(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);
        $case = LegalCase::factory()->create();
        $case->assignedUsers()->attach($user);

        $this->assertTrue($user->can('update', $case));
    }

    public function test_viewer_cannot_update_case(): void
    {
        $viewer = User::factory()->create(['role' => 'viewer']);
        $case = LegalCase::factory()->create();
        $case->assignedUsers()->attach($viewer);

        $this->assertFalse($viewer->can('update', $case));
    }

    public function test_unrelated_user_cannot_update_case(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);
        $case = LegalCase::factory()->create();

        $this->assertFalse($user->can('update', $case));
    }

    public function test_admin_can_update_any_case(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $case = LegalCase::factory()->create();

        $this->assertTrue($admin->can('update', $case));
    }

    public function test_admin_can_delete_any_case(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $case = LegalCase::factory()->create();

        $this->assertTrue($admin->can('delete', $case));
    }

    public function test_team_member_cannot_delete_team_case(): void
    {
        $teamId = 1;
        $user = User::factory()->create(['role' => 'lawyer', 'team_id' => $teamId]);
        $case = LegalCase::factory()->create(['team_id' => $teamId]);

        $this->assertFalse($user->can('delete', $case));
    }

    public function test_assigned_user_cannot_delete_case(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);
        $case = LegalCase::factory()->create();
        $case->assignedUsers()->attach($user);

        $this->assertFalse($user->can('delete', $case));
    }

    public function test_only_admin_can_force_delete(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'lawyer']);
        $case = LegalCase::factory()->create(['user_id' => $owner->id]);

        $this->assertTrue($admin->can('forceDelete', $case));
        $this->assertFalse($owner->can('forceDelete', $case));
    }

    public function test_all_authenticated_users_can_view_any(): void
    {
        $user = User::factory()->create(['role' => 'viewer']);

        $this->assertTrue($user->can('viewAny', LegalCase::class));
    }

    public function test_multiple_team_members_can_view_same_case(): void
    {
        $teamId = 1;
        $user1 = User::factory()->create(['role' => 'lawyer', 'team_id' => $teamId]);
        $user2 = User::factory()->create(['role' => 'lawyer', 'team_id' => $teamId]);
        $case = LegalCase::factory()->create(['team_id' => $teamId]);

        $this->assertTrue($user1->can('view', $case));
        $this->assertTrue($user2->can('view', $case));
    }

    public function test_different_team_members_cannot_view_case(): void
    {
        $user = User::factory()->create(['role' => 'lawyer', 'team_id' => 1]);
        $case = LegalCase::factory()->create(['team_id' => 2]);

        $this->assertFalse($user->can('view', $case));
    }

    public function test_owner_and_assigned_users_both_can_update(): void
    {
        $owner = User::factory()->create(['role' => 'lawyer']);
        $assigned = User::factory()->create(['role' => 'assistant']);
        $case = LegalCase::factory()->create(['user_id' => $owner->id]);
        $case->assignedUsers()->attach($assigned);

        $this->assertTrue($owner->can('update', $case));
        $this->assertTrue($assigned->can('update', $case));
    }

    public function test_viewer_role_has_read_only_access(): void
    {
        $viewer = User::factory()->create(['role' => 'viewer']);
        $case = LegalCase::factory()->create(['user_id' => $viewer->id]);

        $this->assertTrue($viewer->can('view', $case));
        $this->assertFalse($viewer->can('create', LegalCase::class));
        $this->assertFalse($viewer->can('update', $case));
        $this->assertTrue($viewer->can('delete', $case)); // Owner can delete
    }

    public function test_admin_has_full_access_to_all_cases(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $case = LegalCase::factory()->create();

        $this->assertTrue($admin->can('viewAny', LegalCase::class));
        $this->assertTrue($admin->can('view', $case));
        $this->assertTrue($admin->can('create', LegalCase::class));
        $this->assertTrue($admin->can('update', $case));
        $this->assertTrue($admin->can('delete', $case));
        $this->assertTrue($admin->can('forceDelete', $case));
    }

    public function test_case_without_team_can_only_be_viewed_by_owner_assigned_and_admin(): void
    {
        $owner = User::factory()->create(['role' => 'lawyer']);
        $assigned = User::factory()->create(['role' => 'lawyer']);
        $admin = User::factory()->create(['role' => 'admin']);
        $other = User::factory()->create(['role' => 'lawyer']);

        $case = LegalCase::factory()->create(['user_id' => $owner->id, 'team_id' => null]);
        $case->assignedUsers()->attach($assigned);

        $this->assertTrue($owner->can('view', $case));
        $this->assertTrue($assigned->can('view', $case));
        $this->assertTrue($admin->can('view', $case));
        $this->assertFalse($other->can('view', $case));
    }
}
