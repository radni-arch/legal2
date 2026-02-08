<?php

namespace Tests\Feature\Authorization;

use App\Models\CaseDocument;
use App\Models\LegalCase;
use App\Models\User;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class DocumentAuthorizationTest extends TestCase
{
    use UsesTestDatabase;

    public function test_user_can_view_document_from_own_case(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);
        $case = LegalCase::factory()->create(['user_id' => $user->id]);
        $document = CaseDocument::factory()->create(['case_id' => $case->id]);

        $this->assertTrue($user->can('view', $document));
    }

    public function test_user_cannot_view_document_from_other_users_case(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);
        $otherCase = LegalCase::factory()->create();
        $document = CaseDocument::factory()->create(['case_id' => $otherCase->id]);

        $this->assertFalse($user->can('view', $document));
    }

    public function test_user_can_view_document_from_team_case(): void
    {
        $teamId = 1;
        $user = User::factory()->create(['role' => 'lawyer', 'team_id' => $teamId]);
        $case = LegalCase::factory()->create(['team_id' => $teamId]);
        $document = CaseDocument::factory()->create(['case_id' => $case->id]);

        $this->assertTrue($user->can('view', $document));
    }

    public function test_assigned_user_can_view_document_from_case(): void
    {
        $user = User::factory()->create(['role' => 'assistant']);
        $case = LegalCase::factory()->create();
        $case->assignedUsers()->attach($user);
        $document = CaseDocument::factory()->create(['case_id' => $case->id]);

        $this->assertTrue($user->can('view', $document));
    }

    public function test_admin_can_view_any_document(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $case = LegalCase::factory()->create();
        $document = CaseDocument::factory()->create(['case_id' => $case->id]);

        $this->assertTrue($admin->can('view', $document));
    }

    public function test_viewer_cannot_create_document(): void
    {
        $viewer = User::factory()->create(['role' => 'viewer']);

        $this->assertFalse($viewer->can('create', CaseDocument::class));
    }

    public function test_lawyer_can_create_document(): void
    {
        $lawyer = User::factory()->create(['role' => 'lawyer']);

        $this->assertTrue($lawyer->can('create', CaseDocument::class));
    }

    public function test_assistant_can_create_document(): void
    {
        $assistant = User::factory()->create(['role' => 'assistant']);

        $this->assertTrue($assistant->can('create', CaseDocument::class));
    }

    public function test_admin_can_create_document(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->assertTrue($admin->can('create', CaseDocument::class));
    }

    public function test_case_owner_can_update_document(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);
        $case = LegalCase::factory()->create(['user_id' => $user->id]);
        $document = CaseDocument::factory()->create(['case_id' => $case->id]);

        $this->assertTrue($user->can('update', $document));
    }

    public function test_team_member_can_update_document_from_team_case(): void
    {
        $teamId = 1;
        $user = User::factory()->create(['role' => 'lawyer', 'team_id' => $teamId]);
        $case = LegalCase::factory()->create(['team_id' => $teamId]);
        $document = CaseDocument::factory()->create(['case_id' => $case->id]);

        $this->assertTrue($user->can('update', $document));
    }

    public function test_assigned_user_can_update_document(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);
        $case = LegalCase::factory()->create();
        $case->assignedUsers()->attach($user);
        $document = CaseDocument::factory()->create(['case_id' => $case->id]);

        $this->assertTrue($user->can('update', $document));
    }

    public function test_viewer_cannot_update_document_even_if_assigned(): void
    {
        $viewer = User::factory()->create(['role' => 'viewer']);
        $case = LegalCase::factory()->create();
        $case->assignedUsers()->attach($viewer);
        $document = CaseDocument::factory()->create(['case_id' => $case->id]);

        $this->assertFalse($viewer->can('update', $document));
    }

    public function test_unrelated_user_cannot_update_document(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);
        $case = LegalCase::factory()->create();
        $document = CaseDocument::factory()->create(['case_id' => $case->id]);

        $this->assertFalse($user->can('update', $document));
    }

    public function test_admin_can_update_any_document(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $case = LegalCase::factory()->create();
        $document = CaseDocument::factory()->create(['case_id' => $case->id]);

        $this->assertTrue($admin->can('update', $document));
    }

    public function test_case_owner_can_delete_document(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);
        $case = LegalCase::factory()->create(['user_id' => $user->id]);
        $document = CaseDocument::factory()->create(['case_id' => $case->id]);

        $this->assertTrue($user->can('delete', $document));
    }

    public function test_team_member_can_delete_document_from_team_case(): void
    {
        $teamId = 1;
        $user = User::factory()->create(['role' => 'lawyer', 'team_id' => $teamId]);
        $case = LegalCase::factory()->create(['team_id' => $teamId]);
        $document = CaseDocument::factory()->create(['case_id' => $case->id]);

        $this->assertTrue($user->can('delete', $document));
    }

    public function test_assigned_user_can_delete_document(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);
        $case = LegalCase::factory()->create();
        $case->assignedUsers()->attach($user);
        $document = CaseDocument::factory()->create(['case_id' => $case->id]);

        $this->assertTrue($user->can('delete', $document));
    }

    public function test_admin_can_delete_any_document(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $case = LegalCase::factory()->create();
        $document = CaseDocument::factory()->create(['case_id' => $case->id]);

        $this->assertTrue($admin->can('delete', $document));
    }

    public function test_only_admin_can_force_delete_document(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'lawyer']);
        $case = LegalCase::factory()->create(['user_id' => $owner->id]);
        $document = CaseDocument::factory()->create(['case_id' => $case->id]);

        $this->assertTrue($admin->can('forceDelete', $document));
        $this->assertFalse($owner->can('forceDelete', $document));
    }

    public function test_all_authenticated_users_can_view_any_documents(): void
    {
        $user = User::factory()->create(['role' => 'viewer']);

        $this->assertTrue($user->can('viewAny', CaseDocument::class));
    }

    public function test_document_inherits_case_permissions(): void
    {
        $owner = User::factory()->create(['role' => 'lawyer']);
        $other = User::factory()->create(['role' => 'lawyer']);
        $case = LegalCase::factory()->create(['user_id' => $owner->id]);
        $document = CaseDocument::factory()->create(['case_id' => $case->id]);

        // Owner can access through case
        $this->assertTrue($owner->can('view', $document));
        $this->assertTrue($owner->can('update', $document));
        $this->assertTrue($owner->can('delete', $document));

        // Other user cannot access
        $this->assertFalse($other->can('view', $document));
        $this->assertFalse($other->can('update', $document));
        $this->assertFalse($other->can('delete', $document));
    }

    public function test_admin_can_view_document_without_case(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'lawyer']);
        $document = CaseDocument::factory()->create(['case_id' => null]);

        $this->assertTrue($admin->can('view', $document));
        $this->assertFalse($user->can('view', $document));
    }

    public function test_multiple_users_with_case_access_can_all_view_document(): void
    {
        $teamId = 1;
        $user1 = User::factory()->create(['role' => 'lawyer', 'team_id' => $teamId]);
        $user2 = User::factory()->create(['role' => 'lawyer', 'team_id' => $teamId]);
        $case = LegalCase::factory()->create(['team_id' => $teamId]);
        $document = CaseDocument::factory()->create(['case_id' => $case->id]);

        $this->assertTrue($user1->can('view', $document));
        $this->assertTrue($user2->can('view', $document));
    }
}
