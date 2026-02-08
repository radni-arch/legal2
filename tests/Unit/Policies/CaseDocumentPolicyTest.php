<?php

namespace Tests\Unit\Policies;

use App\Models\CaseDocument;
use App\Models\LegalCase;
use App\Models\User;
use App\Policies\CaseDocumentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class CaseDocumentPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected CaseDocumentPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new CaseDocumentPolicy;
    }

    /** @test */
    public function lawyer_can_view_any_documents(): void
    {
        $lawyer = User::factory()->create(['role' => 'lawyer']);

        $this->assertTrue($this->policy->viewAny($lawyer));
    }

    /** @test */
    public function non_lawyer_cannot_view_any_documents(): void
    {
        $user = User::factory()->create(['role' => 'client']);

        $this->assertFalse($this->policy->viewAny($user));
    }

    /** @test */
    public function can_view_document_if_can_view_case(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);
        $case = LegalCase::factory()->create(['user_id' => $user->id]);
        $document = CaseDocument::factory()->create(['case_id' => $case->id]);

        // Register policies for this test
        Gate::policy(LegalCase::class, \App\Policies\LegalCasePolicy::class);

        $this->assertTrue($this->policy->view($user, $document));
    }

    /** @test */
    public function cannot_view_document_if_cannot_view_case(): void
    {
        $owner = User::factory()->create(['role' => 'lawyer']);
        $other = User::factory()->create(['role' => 'lawyer']);
        $case = LegalCase::factory()->create(['user_id' => $owner->id]);
        $document = CaseDocument::factory()->create(['case_id' => $case->id]);

        // Register policies for this test
        Gate::policy(LegalCase::class, \App\Policies\LegalCasePolicy::class);

        $this->assertFalse($this->policy->view($other, $document));
    }

    /** @test */
    public function lawyer_can_create_document(): void
    {
        $lawyer = User::factory()->create(['role' => 'lawyer']);

        $this->assertTrue($this->policy->create($lawyer));
    }

    /** @test */
    public function can_update_document_if_can_update_case(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);
        $case = LegalCase::factory()->create(['user_id' => $user->id]);
        $document = CaseDocument::factory()->create(['case_id' => $case->id]);

        // Register policies for this test
        Gate::policy(LegalCase::class, \App\Policies\LegalCasePolicy::class);

        $this->assertTrue($this->policy->update($user, $document));
    }

    /** @test */
    public function cannot_update_document_if_cannot_update_case(): void
    {
        $owner = User::factory()->create(['role' => 'lawyer']);
        $other = User::factory()->create(['role' => 'lawyer']);
        $case = LegalCase::factory()->create(['user_id' => $owner->id]);
        $document = CaseDocument::factory()->create(['case_id' => $case->id]);

        // Register policies for this test
        Gate::policy(LegalCase::class, \App\Policies\LegalCasePolicy::class);

        $this->assertFalse($this->policy->update($other, $document));
    }

    /** @test */
    public function assigned_user_can_update_document(): void
    {
        $owner = User::factory()->create(['role' => 'lawyer']);
        $assigned = User::factory()->create(['role' => 'lawyer']);
        $case = LegalCase::factory()->create(['user_id' => $owner->id]);
        $case->assignedUsers()->attach($assigned->id);
        $document = CaseDocument::factory()->create(['case_id' => $case->id]);

        // Register policies for this test
        Gate::policy(LegalCase::class, \App\Policies\LegalCasePolicy::class);

        $this->assertTrue($this->policy->update($assigned, $document));
    }

    /** @test */
    public function can_delete_document_if_can_delete_case(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);
        $case = LegalCase::factory()->create(['user_id' => $user->id]);
        $document = CaseDocument::factory()->create(['case_id' => $case->id]);

        // Register policies for this test
        Gate::policy(LegalCase::class, \App\Policies\LegalCasePolicy::class);

        $this->assertTrue($this->policy->delete($user, $document));
    }

    /** @test */
    public function cannot_delete_document_if_cannot_delete_case(): void
    {
        $owner = User::factory()->create(['role' => 'lawyer']);
        $other = User::factory()->create(['role' => 'lawyer']);
        $case = LegalCase::factory()->create(['user_id' => $owner->id]);
        $document = CaseDocument::factory()->create(['case_id' => $case->id]);

        // Register policies for this test
        Gate::policy(LegalCase::class, \App\Policies\LegalCasePolicy::class);

        $this->assertFalse($this->policy->delete($other, $document));
    }

    /** @test */
    public function admin_can_perform_all_actions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lawyer = User::factory()->create(['role' => 'lawyer']);
        $case = LegalCase::factory()->create(['user_id' => $lawyer->id]);
        $document = CaseDocument::factory()->create(['case_id' => $case->id]);

        // Register policies for this test
        Gate::policy(LegalCase::class, \App\Policies\LegalCasePolicy::class);

        $this->assertTrue($this->policy->view($admin, $document));
        $this->assertTrue($this->policy->update($admin, $document));
        $this->assertTrue($this->policy->delete($admin, $document));
    }

    /** @test */
    public function team_member_can_view_team_document(): void
    {
        $teamId = 1;
        $owner = User::factory()->create(['role' => 'lawyer', 'team_id' => $teamId]);
        $teammate = User::factory()->create(['role' => 'lawyer', 'team_id' => $teamId]);
        $case = LegalCase::factory()->create(['user_id' => $owner->id, 'team_id' => $teamId]);
        $document = CaseDocument::factory()->create(['case_id' => $case->id]);

        // Register policies for this test
        Gate::policy(LegalCase::class, \App\Policies\LegalCasePolicy::class);

        $this->assertTrue($this->policy->view($teammate, $document));
    }
}
