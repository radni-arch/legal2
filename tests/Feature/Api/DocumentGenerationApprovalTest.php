<?php

namespace Tests\Feature\Api;

use App\Agents\Contracts\LegalArtilleryAgentContract;
use App\Models\DocumentGenerationRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Test Suite for Document Generation Approval & Dispatch API
 *
 * Tests API endpoints for approving and dispatching document generation runs:
 * - POST /api/documents/runs/{id}/approve - Approve a generation run
 * - POST /api/documents/runs/{id}/dispatch - Dispatch an approved run
 *
 * Follows strict TDD methodology.
 */
class DocumentGenerationApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Bypass API token middleware for testing (follows existing test pattern)
        $this->withoutMiddleware(\App\Http\Middleware\ApiTokenAuth::class);

        // Create test users
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
    }

    /**
     * Test 1: POST /api/documents/runs/{id}/approve - Successful approval
     *
     * @test
     */
    public function test_approve_run_succeeds(): void
    {
        $this->actingAs($this->user);

        $run = DocumentGenerationRun::factory()->create([
            'document_type' => 'predsjednik_suda',
            'status' => 'completed',
            'final_document' => 'Test content',
            'user_id' => $this->user->id,
        ]);

        $mock = Mockery::mock(LegalArtilleryAgentContract::class);
        $mock->shouldReceive('approveRun')
            ->once()
            ->with($run->id, $this->user->id, 'Looks good')
            ->andReturn($run->fresh());
        $this->app->instance(LegalArtilleryAgentContract::class, $mock);

        $response = $this->postJson("/api/documents/runs/{$run->id}/approve", [
            'notes' => 'Looks good',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'message']);
    }

    /**
     * Test 2: POST /api/documents/runs/{id}/approve - 404 for missing run
     *
     * @test
     */
    public function test_approve_run_404_for_missing_run(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/documents/runs/nonexistent/approve');

        $response->assertStatus(404);
    }

    /**
     * Test 3: POST /api/documents/runs/{id}/approve - 403 for other user's run
     *
     * @test
     */
    public function test_approve_run_403_for_other_users_run(): void
    {
        $this->actingAs($this->user);

        $run = DocumentGenerationRun::factory()->create([
            'document_type' => 'predsjednik_suda',
            'status' => 'completed',
            'user_id' => $this->otherUser->id,
        ]);

        $response = $this->postJson("/api/documents/runs/{$run->id}/approve");

        $response->assertStatus(403);
    }

    /**
     * Test 4: POST /api/documents/runs/{id}/approve - 401 when unauthenticated
     *
     * @test
     */
    public function test_approve_run_requires_authentication(): void
    {
        $run = DocumentGenerationRun::factory()->create([
            'status' => 'completed',
        ]);

        $response = $this->postJson("/api/documents/runs/{$run->id}/approve");

        $response->assertStatus(401);
    }

    /**
     * Test 5: POST /api/documents/runs/{id}/approve - 422 when agent throws
     *
     * @test
     */
    public function test_approve_run_returns_422_on_invalid_argument(): void
    {
        $this->actingAs($this->user);

        $run = DocumentGenerationRun::factory()->create([
            'document_type' => 'predsjednik_suda',
            'status' => 'running',
            'user_id' => $this->user->id,
        ]);

        $mock = Mockery::mock(LegalArtilleryAgentContract::class);
        $mock->shouldReceive('approveRun')
            ->once()
            ->andThrow(new \InvalidArgumentException('Cannot approve run with status: running'));
        $this->app->instance(LegalArtilleryAgentContract::class, $mock);

        $response = $this->postJson("/api/documents/runs/{$run->id}/approve");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Cannot approve run with status: running');
    }

    /**
     * Test 6: POST /api/documents/runs/{id}/dispatch - Dispatch fails without approval
     *
     * @test
     */
    public function test_dispatch_fails_without_approval(): void
    {
        $this->actingAs($this->user);

        $run = DocumentGenerationRun::factory()->create([
            'document_type' => 'predsjednik_suda',
            'status' => 'completed',
            'final_document' => 'Test content',
            'user_id' => $this->user->id,
        ]);

        $mock = Mockery::mock(LegalArtilleryAgentContract::class);
        $mock->shouldReceive('dispatchApproved')
            ->once()
            ->andThrow(new \InvalidArgumentException('Run is not ready for dispatch'));
        $this->app->instance(LegalArtilleryAgentContract::class, $mock);

        $response = $this->postJson("/api/documents/runs/{$run->id}/dispatch", [
            'send_email' => true,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Run is not ready for dispatch');
    }

    /**
     * Test 7: POST /api/documents/runs/{id}/dispatch - Successful dispatch
     *
     * @test
     */
    public function test_dispatch_succeeds_for_approved_run(): void
    {
        $this->actingAs($this->user);

        $run = DocumentGenerationRun::factory()->create([
            'document_type' => 'predsjednik_suda',
            'status' => 'completed',
            'final_document' => 'Test content',
            'user_id' => $this->user->id,
        ]);

        $mock = Mockery::mock(LegalArtilleryAgentContract::class);
        $mock->shouldReceive('dispatchApproved')
            ->once()
            ->with(
                $run->id,
                true,   // sendEmail
                false,  // asDraft
                'test@example.com',  // toEmail
                false,  // submitEkom
            )
            ->andReturn($run->fresh());
        $this->app->instance(LegalArtilleryAgentContract::class, $mock);

        $response = $this->postJson("/api/documents/runs/{$run->id}/dispatch", [
            'send_email' => true,
            'as_draft' => false,
            'to_email' => 'test@example.com',
            'submit_ekom' => false,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'message']);
    }

    /**
     * Test 8: POST /api/documents/runs/{id}/dispatch - 404 for missing run
     *
     * @test
     */
    public function test_dispatch_run_404_for_missing_run(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/documents/runs/nonexistent/dispatch');

        $response->assertStatus(404);
    }

    /**
     * Test 9: POST /api/documents/runs/{id}/dispatch - 403 for other user's run
     *
     * @test
     */
    public function test_dispatch_run_403_for_other_users_run(): void
    {
        $this->actingAs($this->user);

        $run = DocumentGenerationRun::factory()->create([
            'document_type' => 'predsjednik_suda',
            'status' => 'completed',
            'user_id' => $this->otherUser->id,
        ]);

        $response = $this->postJson("/api/documents/runs/{$run->id}/dispatch");

        $response->assertStatus(403);
    }

    /**
     * Test 10: POST /api/documents/runs/{id}/dispatch - 401 when unauthenticated
     *
     * @test
     */
    public function test_dispatch_run_requires_authentication(): void
    {
        $run = DocumentGenerationRun::factory()->create([
            'status' => 'completed',
        ]);

        $response = $this->postJson("/api/documents/runs/{$run->id}/dispatch");

        $response->assertStatus(401);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
