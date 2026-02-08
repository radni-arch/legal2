<?php

namespace Tests\Feature\Authorization;

use App\Models\AgentRun;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Integration tests for controller-level authorization
 *
 * Tests that authorization policies are properly enforced at the controller layer
 */
class ControllerAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    // ========================================================================
    // AgentController Authorization Tests
    // ========================================================================

    /** @test */
    public function lawyers_can_start_agent_research(): void
    {
        $lawyer = User::factory()->create(['role' => 'lawyer']);

        $response = $this->actingAs($lawyer)->postJson('/api/agent/research/start', [
            'objective' => 'Research Croatian criminal procedure regarding evidence admissibility',
            'topics' => ['evidence', 'criminal procedure'],
        ]);

        $response->assertSuccessful();
    }

    /** @test */
    public function non_lawyers_cannot_start_agent_research(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $response = $this->actingAs($client)->postJson('/api/agent/research/start', [
            'objective' => 'Research Croatian criminal procedure',
        ]);

        $response->assertForbidden();
    }

    /** @test */
    public function lawyers_can_view_agent_runs(): void
    {
        $lawyer = User::factory()->create(['role' => 'lawyer']);
        $run = AgentRun::factory()->create();

        $response = $this->actingAs($lawyer)->getJson("/api/agent/research/{$run->id}");

        $response->assertSuccessful();
    }

    /** @test */
    public function non_lawyers_cannot_view_agent_runs(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $run = AgentRun::factory()->create();

        $response = $this->actingAs($client)->getJson("/api/agent/research/{$run->id}");

        $response->assertForbidden();
    }

    /** @test */
    public function lawyers_can_list_agent_runs(): void
    {
        $lawyer = User::factory()->create(['role' => 'lawyer']);
        AgentRun::factory()->count(3)->create();

        $response = $this->actingAs($lawyer)->getJson('/api/agent/research');

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'success',
            'data' => [
                'runs',
                'count',
            ],
        ]);
    }

    /** @test */
    public function only_admin_can_delete_agent_runs(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lawyer = User::factory()->create(['role' => 'lawyer']);
        $run = AgentRun::factory()->create();

        // Admin can delete
        $response = $this->actingAs($admin)->deleteJson("/api/agent/research/{$run->id}");
        $response->assertSuccessful();

        // Lawyer cannot delete
        $run2 = AgentRun::factory()->create();
        $response = $this->actingAs($lawyer)->deleteJson("/api/agent/research/{$run2->id}");
        $response->assertForbidden();
    }

    // ========================================================================
    // EvidenceController Authorization Tests
    // ========================================================================

    /** @test */
    public function owner_can_analyze_evidence_for_their_case(): void
    {
        $lawyer = User::factory()->create(['role' => 'lawyer']);
        $case = LegalCase::factory()->create(['user_id' => $lawyer->id]);

        $response = $this->actingAs($lawyer)->postJson("/api/evidence/analyze/{$case->id}", [
            'evidence' => [
                [
                    'id' => 'evidence-1',
                    'type' => 'physical',
                    'description' => 'Found weapon',
                ],
            ],
        ]);

        $response->assertSuccessful();
    }

    /** @test */
    public function non_owner_cannot_analyze_evidence_for_case(): void
    {
        $owner = User::factory()->create(['role' => 'lawyer']);
        $other = User::factory()->create(['role' => 'lawyer']);
        $case = LegalCase::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($other)->postJson("/api/evidence/analyze/{$case->id}", [
            'evidence' => [
                [
                    'id' => 'evidence-1',
                    'type' => 'physical',
                    'description' => 'Found weapon',
                ],
            ],
        ]);

        $response->assertForbidden();
    }

    /** @test */
    public function owner_can_generate_suppression_motion_for_their_case(): void
    {
        $lawyer = User::factory()->create(['role' => 'lawyer']);
        $case = LegalCase::factory()->create(['user_id' => $lawyer->id]);

        $response = $this->actingAs($lawyer)->postJson("/api/evidence/suppress-motion/{$case->id}", [
            'evidence_ids' => ['evidence-1', 'evidence-2'],
        ]);

        $response->assertSuccessful();
    }

    /** @test */
    public function non_owner_cannot_generate_suppression_motion(): void
    {
        $owner = User::factory()->create(['role' => 'lawyer']);
        $other = User::factory()->create(['role' => 'lawyer']);
        $case = LegalCase::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($other)->postJson("/api/evidence/suppress-motion/{$case->id}", [
            'evidence_ids' => ['evidence-1'],
        ]);

        $response->assertForbidden();
    }

    /** @test */
    public function team_member_can_analyze_evidence_for_team_case(): void
    {
        $teamId = 1;
        $owner = User::factory()->create(['role' => 'lawyer', 'team_id' => $teamId]);
        $teammate = User::factory()->create(['role' => 'lawyer', 'team_id' => $teamId]);
        $case = LegalCase::factory()->create(['user_id' => $owner->id, 'team_id' => $teamId]);

        $response = $this->actingAs($teammate)->postJson("/api/evidence/analyze/{$case->id}", [
            'evidence' => [
                [
                    'id' => 'evidence-1',
                    'type' => 'witness',
                    'description' => 'Testimony',
                ],
            ],
        ]);

        $response->assertSuccessful();
    }

    /** @test */
    public function assigned_user_can_analyze_evidence(): void
    {
        $owner = User::factory()->create(['role' => 'lawyer']);
        $assigned = User::factory()->create(['role' => 'lawyer']);
        $case = LegalCase::factory()->create(['user_id' => $owner->id]);
        $case->assignedUsers()->attach($assigned->id);

        $response = $this->actingAs($assigned)->postJson("/api/evidence/analyze/{$case->id}", [
            'evidence' => [
                [
                    'id' => 'evidence-1',
                    'type' => 'document',
                    'description' => 'Contract',
                ],
            ],
        ]);

        $response->assertSuccessful();
    }

    /** @test */
    public function admin_bypasses_all_authorization_checks(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lawyer = User::factory()->create(['role' => 'lawyer']);
        $case = LegalCase::factory()->create(['user_id' => $lawyer->id]);
        $run = AgentRun::factory()->create();

        // Admin can analyze evidence for any case
        $response = $this->actingAs($admin)->postJson("/api/evidence/analyze/{$case->id}", [
            'evidence' => [
                [
                    'id' => 'evidence-1',
                    'type' => 'physical',
                    'description' => 'Evidence',
                ],
            ],
        ]);
        $response->assertSuccessful();

        // Admin can delete agent runs
        $response = $this->actingAs($admin)->deleteJson("/api/agent/research/{$run->id}");
        $response->assertSuccessful();
    }

    // ========================================================================
    // Unauthenticated Access Tests
    // ========================================================================

    /** @test */
    public function unauthenticated_users_cannot_access_protected_endpoints(): void
    {
        $case = LegalCase::factory()->create();
        $run = AgentRun::factory()->create();

        // Cannot start research
        $response = $this->postJson('/api/agent/research/start', [
            'objective' => 'Test',
        ]);
        $response->assertUnauthorized();

        // Cannot view runs
        $response = $this->getJson("/api/agent/research/{$run->id}");
        $response->assertUnauthorized();

        // Cannot analyze evidence
        $response = $this->postJson("/api/evidence/analyze/{$case->id}", [
            'evidence' => [],
        ]);
        $response->assertUnauthorized();
    }
}
