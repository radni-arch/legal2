<?php

namespace Tests\Unit\Policies;

use App\Models\CourtDecision;
use App\Models\Law;
use App\Models\User;
use App\Policies\CourtDecisionPolicy;
use App\Policies\LawPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for CourtDecisionPolicy and LawPolicy
 * These resources are public data but with admin-only modifications
 */
class PublicDataPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected CourtDecisionPolicy $decisionPolicy;

    protected LawPolicy $lawPolicy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->decisionPolicy = new CourtDecisionPolicy;
        $this->lawPolicy = new LawPolicy;
    }

    // ========================================================================
    // CourtDecisionPolicy Tests (10 tests)
    // ========================================================================

    /** @test */
    public function lawyers_can_view_any_court_decisions(): void
    {
        $lawyer = User::factory()->create(['role' => 'lawyer']);

        $this->assertTrue($this->decisionPolicy->viewAny($lawyer));
    }

    /** @test */
    public function non_lawyers_cannot_view_any_court_decisions(): void
    {
        $user = User::factory()->create(['role' => 'client']);

        $this->assertFalse($this->decisionPolicy->viewAny($user));
    }

    /** @test */
    public function anyone_can_view_individual_court_decisions(): void
    {
        $lawyer = User::factory()->create(['role' => 'lawyer']);
        $client = User::factory()->create(['role' => 'client']);
        $decision = CourtDecision::factory()->create();

        $this->assertTrue($this->decisionPolicy->view($lawyer, $decision));
        $this->assertTrue($this->decisionPolicy->view($client, $decision));
    }

    /** @test */
    public function only_admin_can_create_court_decisions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lawyer = User::factory()->create(['role' => 'lawyer']);

        $this->assertTrue($this->decisionPolicy->create($admin));
        $this->assertFalse($this->decisionPolicy->create($lawyer));
    }

    /** @test */
    public function only_admin_can_update_court_decisions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lawyer = User::factory()->create(['role' => 'lawyer']);
        $decision = CourtDecision::factory()->create();

        $this->assertTrue($this->decisionPolicy->update($admin, $decision));
        $this->assertFalse($this->decisionPolicy->update($lawyer, $decision));
    }

    /** @test */
    public function only_admin_can_delete_court_decisions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lawyer = User::factory()->create(['role' => 'lawyer']);
        $decision = CourtDecision::factory()->create();

        $this->assertTrue($this->decisionPolicy->delete($admin, $decision));
        $this->assertFalse($this->decisionPolicy->delete($lawyer, $decision));
    }

    // ========================================================================
    // LawPolicy Tests (10 tests)
    // ========================================================================

    /** @test */
    public function lawyers_can_view_any_laws(): void
    {
        $lawyer = User::factory()->create(['role' => 'lawyer']);

        $this->assertTrue($this->lawPolicy->viewAny($lawyer));
    }

    /** @test */
    public function non_lawyers_cannot_view_any_laws(): void
    {
        $user = User::factory()->create(['role' => 'client']);

        $this->assertFalse($this->lawPolicy->viewAny($user));
    }

    /** @test */
    public function anyone_can_view_individual_laws(): void
    {
        $lawyer = User::factory()->create(['role' => 'lawyer']);
        $client = User::factory()->create(['role' => 'client']);
        $law = Law::factory()->create();

        $this->assertTrue($this->lawPolicy->view($lawyer, $law));
        $this->assertTrue($this->lawPolicy->view($client, $law));
    }

    /** @test */
    public function only_admin_can_create_laws(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lawyer = User::factory()->create(['role' => 'lawyer']);

        $this->assertTrue($this->lawPolicy->create($admin));
        $this->assertFalse($this->lawPolicy->create($lawyer));
    }

    /** @test */
    public function only_admin_can_update_laws(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lawyer = User::factory()->create(['role' => 'lawyer']);
        $law = Law::factory()->create();

        $this->assertTrue($this->lawPolicy->update($admin, $law));
        $this->assertFalse($this->lawPolicy->update($lawyer, $law));
    }

    /** @test */
    public function only_admin_can_delete_laws(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lawyer = User::factory()->create(['role' => 'lawyer']);
        $law = Law::factory()->create();

        $this->assertTrue($this->lawPolicy->delete($admin, $law));
        $this->assertFalse($this->lawPolicy->delete($lawyer, $law));
    }

    // ========================================================================
    // Admin Bypass Tests (4 tests)
    // ========================================================================

    /** @test */
    public function admin_bypasses_all_decision_checks(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $decision = CourtDecision::factory()->create();

        // Admin should pass all checks via before() method
        $this->assertTrue($this->decisionPolicy->viewAny($admin));
        $this->assertTrue($this->decisionPolicy->view($admin, $decision));
        $this->assertTrue($this->decisionPolicy->create($admin));
        $this->assertTrue($this->decisionPolicy->update($admin, $decision));
        $this->assertTrue($this->decisionPolicy->delete($admin, $decision));
    }

    /** @test */
    public function admin_bypasses_all_law_checks(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $law = Law::factory()->create();

        // Admin should pass all checks via before() method
        $this->assertTrue($this->lawPolicy->viewAny($admin));
        $this->assertTrue($this->lawPolicy->view($admin, $law));
        $this->assertTrue($this->lawPolicy->create($admin));
        $this->assertTrue($this->lawPolicy->update($admin, $law));
        $this->assertTrue($this->lawPolicy->delete($admin, $law));
    }

    /** @test */
    public function lawyer_role_includes_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // isLawyer() returns true for both lawyer and admin
        $this->assertTrue($this->decisionPolicy->viewAny($admin));
        $this->assertTrue($this->lawPolicy->viewAny($admin));
    }

    /** @test */
    public function client_role_cannot_access_professional_resources(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        // Clients cannot view lists (professional access)
        $this->assertFalse($this->decisionPolicy->viewAny($client));
        $this->assertFalse($this->lawPolicy->viewAny($client));

        // But can view individual public resources
        $decision = CourtDecision::factory()->create();
        $law = Law::factory()->create();
        $this->assertTrue($this->decisionPolicy->view($client, $decision));
        $this->assertTrue($this->lawPolicy->view($client, $law));
    }
}
