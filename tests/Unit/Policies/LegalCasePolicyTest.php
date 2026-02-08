<?php

namespace Tests\Unit\Policies;

use App\Models\LegalCase;
use App\Models\User;
use App\Policies\LegalCasePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalCasePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected LegalCasePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new LegalCasePolicy;
    }

    /** @test */
    public function admin_can_view_any_cases(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->assertTrue($this->policy->viewAny($admin));
    }

    /** @test */
    public function lawyer_can_view_any_cases(): void
    {
        $lawyer = User::factory()->create(['role' => 'lawyer']);

        $this->assertTrue($this->policy->viewAny($lawyer));
    }

    /** @test */
    public function non_lawyer_cannot_view_any_cases(): void
    {
        $user = User::factory()->create(['role' => 'client']);

        $this->assertFalse($this->policy->viewAny($user));
    }

    /** @test */
    public function owner_can_view_their_case(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);
        $case = LegalCase::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($this->policy->view($user, $case));
    }

    /** @test */
    public function assigned_user_can_view_case(): void
    {
        $owner = User::factory()->create(['role' => 'lawyer']);
        $assigned = User::factory()->create(['role' => 'lawyer']);
        $case = LegalCase::factory()->create(['user_id' => $owner->id]);
        $case->assignedUsers()->attach($assigned->id);

        $this->assertTrue($this->policy->view($assigned, $case));
    }

    /** @test */
    public function team_member_can_view_team_case(): void
    {
        $teamId = 1;
        $owner = User::factory()->create(['role' => 'lawyer', 'team_id' => $teamId]);
        $teammate = User::factory()->create(['role' => 'lawyer', 'team_id' => $teamId]);
        $case = LegalCase::factory()->create(['user_id' => $owner->id, 'team_id' => $teamId]);

        $this->assertTrue($this->policy->view($teammate, $case));
    }

    /** @test */
    public function non_team_member_cannot_view_case(): void
    {
        $owner = User::factory()->create(['role' => 'lawyer', 'team_id' => 1]);
        $other = User::factory()->create(['role' => 'lawyer', 'team_id' => 2]);
        $case = LegalCase::factory()->create(['user_id' => $owner->id, 'team_id' => 1]);

        $this->assertFalse($this->policy->view($other, $case));
    }

    /** @test */
    public function lawyer_can_create_case(): void
    {
        $lawyer = User::factory()->create(['role' => 'lawyer']);

        $this->assertTrue($this->policy->create($lawyer));
    }

    /** @test */
    public function owner_can_update_their_case(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);
        $case = LegalCase::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($this->policy->update($user, $case));
    }

    /** @test */
    public function assigned_user_can_update_case(): void
    {
        $owner = User::factory()->create(['role' => 'lawyer']);
        $assigned = User::factory()->create(['role' => 'lawyer']);
        $case = LegalCase::factory()->create(['user_id' => $owner->id]);
        $case->assignedUsers()->attach($assigned->id);

        $this->assertTrue($this->policy->update($assigned, $case));
    }

    /** @test */
    public function non_assigned_user_cannot_update_case(): void
    {
        $owner = User::factory()->create(['role' => 'lawyer']);
        $other = User::factory()->create(['role' => 'lawyer']);
        $case = LegalCase::factory()->create(['user_id' => $owner->id]);

        $this->assertFalse($this->policy->update($other, $case));
    }

    /** @test */
    public function owner_can_delete_their_case(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);
        $case = LegalCase::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($this->policy->delete($user, $case));
    }

    /** @test */
    public function non_owner_cannot_delete_case(): void
    {
        $owner = User::factory()->create(['role' => 'lawyer']);
        $other = User::factory()->create(['role' => 'lawyer']);
        $case = LegalCase::factory()->create(['user_id' => $owner->id]);

        $this->assertFalse($this->policy->delete($other, $case));
    }

    /** @test */
    public function only_admin_can_force_delete(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lawyer = User::factory()->create(['role' => 'lawyer']);
        $case = LegalCase::factory()->create(['user_id' => $lawyer->id]);

        $this->assertTrue($this->policy->forceDelete($admin, $case));
        $this->assertFalse($this->policy->forceDelete($lawyer, $case));
    }

    /** @test */
    public function owner_and_admin_can_restore_case(): void
    {
        $owner = User::factory()->create(['role' => 'lawyer']);
        $admin = User::factory()->create(['role' => 'admin']);
        $other = User::factory()->create(['role' => 'lawyer']);
        $case = LegalCase::factory()->create(['user_id' => $owner->id]);

        $this->assertTrue($this->policy->restore($owner, $case));
        $this->assertTrue($this->policy->restore($admin, $case));
        $this->assertFalse($this->policy->restore($other, $case));
    }
}
