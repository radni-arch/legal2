<?php

namespace Tests\Feature\Api;

use App\Agents\Contracts\LegalArtilleryAgentContract;
use App\Models\DocumentContext;
use App\Models\DocumentGenerationRun;
use App\Models\DocumentIteration;
use App\Models\Evidence;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Test Suite for DocumentGenerationController
 *
 * Tests API endpoints for the LegalArtilleryAgent:
 * - POST /api/documents/generate - Start document generation
 * - GET /api/documents/runs/{id} - Get specific run
 * - GET /api/documents/runs - List user's runs
 *
 * Follows strict TDD methodology
 */
class DocumentGenerationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Bypass API token middleware for testing
        $this->withoutMiddleware(\App\Http\Middleware\ApiTokenAuth::class);

        // Create test users
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
    }

    /**
     * Test 1: POST /api/documents/generate - Successful generation with standalone context
     *
     * @test
     */
    public function it_generates_document_with_standalone_context()
    {
        $this->actingAs($this->user);

        // Mock the agent to return a completed run
        $mockAgent = Mockery::mock(LegalArtilleryAgentContract::class);
        $expectedRun = DocumentGenerationRun::factory()->create([
            'user_id' => $this->user->id,
            'document_type' => 'suppression_motion',
            'status' => 'completed',
            'final_document' => 'PRIJEDLOG ZA ISKLJUČENJE DOKAZA...',
            'final_score' => 87.5,
            'total_iterations' => 4,
            'stopped_reason' => 'converged',
        ]);

        $mockAgent->shouldReceive('fire')
            ->once()
            ->with(
                'suppression_motion',
                $this->user->id,
                Mockery::subset([
                    'context' => 'Evidence obtained during illegal search',
                    'additional_context' => 'Search warrant was overly broad',
                ]),
                false,
                false,
                null,
                null,
                false
            )
            ->andReturn($expectedRun);

        $this->app->instance(LegalArtilleryAgentContract::class, $mockAgent);

        // Act: Send request
        $response = $this->postJson('/api/documents/generate', [
            'document_type' => 'suppression_motion',
            'context' => 'Evidence obtained during illegal search',
            'additional_context' => 'Search warrant was overly broad',
        ]);

        // Assert: Verify response
        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'id' => $expectedRun->id,
                    'document_type' => 'suppression_motion',
                    'status' => 'completed',
                    'final_score' => 87.5,
                    'total_iterations' => 4,
                    'stopped_reason' => 'converged',
                ],
            ]);
    }

    /**
     * Test 2: POST /api/documents/generate - Generation with case_id
     *
     * @test
     */
    public function it_generates_document_with_case_id()
    {
        $this->actingAs($this->user);

        // Create a case
        $case = LegalCase::factory()->create();

        $mockAgent = Mockery::mock(LegalArtilleryAgentContract::class);
        $expectedRun = DocumentGenerationRun::factory()->create([
            'user_id' => $this->user->id,
            'case_id' => $case->id,
            'document_type' => 'appeal_brief',
            'status' => 'completed',
        ]);

        $mockAgent->shouldReceive('fire')
            ->once()
            ->with(
                'appeal_brief',
                $this->user->id,
                Mockery::subset([
                    'case_id' => (string) $case->id,
                ]),
                false,
                false,
                null,
                null,
                false
            )
            ->andReturn($expectedRun);

        $this->app->instance(LegalArtilleryAgentContract::class, $mockAgent);

        $response = $this->postJson('/api/documents/generate', [
            'document_type' => 'appeal_brief',
            'case_id' => $case->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.case_id', (string) $case->id);
    }

    /**
     * Test 3: POST /api/documents/generate - Mixed mode with case_id + evidence_ids
     *
     * @test
     */
    public function it_generates_document_with_mixed_mode()
    {
        $this->actingAs($this->user);

        $case = LegalCase::factory()->create();
        $evidence1 = Evidence::factory()->create();
        $evidence2 = Evidence::factory()->create();

        $mockAgent = Mockery::mock(LegalArtilleryAgentContract::class);
        $expectedRun = DocumentGenerationRun::factory()->create([
            'user_id' => $this->user->id,
            'case_id' => $case->id,
            'document_type' => 'evidence_analysis',
            'status' => 'completed',
        ]);

        $mockAgent->shouldReceive('fire')
            ->once()
            ->with(
                'evidence_analysis',
                $this->user->id,
                Mockery::on(function ($input) use ($case, $evidence1, $evidence2) {
                    return $input['case_id'] === (string) $case->id
                        && $input['evidence_ids'] === [(string) $evidence1->id, (string) $evidence2->id];
                }),
                false,
                false,
                null,
                null,
                false
            )
            ->andReturn($expectedRun);

        $this->app->instance(LegalArtilleryAgentContract::class, $mockAgent);

        $response = $this->postJson('/api/documents/generate', [
            'document_type' => 'evidence_analysis',
            'case_id' => (string) $case->id,
            'evidence_ids' => [(string) $evidence1->id, (string) $evidence2->id],
        ]);

        $response->assertStatus(201);
    }

    /**
     * Test 4: POST /api/documents/generate - Validation: missing document_type
     *
     * @test
     */
    public function it_rejects_request_without_document_type()
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/documents/generate', [
            'context' => 'Some context',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['document_type']);
    }

    /**
     * Test 5: POST /api/documents/generate - Validation: invalid document_type
     *
     * @test
     */
    public function it_rejects_invalid_document_type()
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/documents/generate', [
            'document_type' => 'invalid_type',
            'context' => 'Some context',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['document_type']);
    }

    /**
     * Test 6: POST /api/documents/generate - Validation: invalid case_id
     *
     * @test
     */
    public function it_rejects_invalid_case_id()
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/documents/generate', [
            'document_type' => 'suppression_motion',
            'case_id' => 99999, // Non-existent case
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['case_id']);
    }

    /**
     * Test 7: POST /api/documents/generate - Validation: invalid evidence_ids
     *
     * @test
     */
    public function it_rejects_invalid_evidence_ids()
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/documents/generate', [
            'document_type' => 'evidence_analysis',
            'evidence_ids' => [99999], // Non-existent evidence
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['evidence_ids.0']);
    }

    /**
     * Test 8: POST /api/documents/generate - Authentication required
     *
     * @test
     */
    public function it_requires_authentication_to_generate()
    {
        // Don't authenticate
        $response = $this->postJson('/api/documents/generate', [
            'document_type' => 'suppression_motion',
            'context' => 'Some context',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test 9: POST /api/documents/generate - Response includes all expected fields
     *
     * @test
     */
    public function it_returns_complete_response_structure()
    {
        $this->actingAs($this->user);

        $mockAgent = Mockery::mock(LegalArtilleryAgentContract::class);
        $expectedRun = DocumentGenerationRun::factory()->create([
            'user_id' => $this->user->id,
            'document_type' => 'dismissal_motion',
            'status' => 'completed',
            'final_document' => 'ZAHTJEV ZA ODBACIVANJE...',
            'final_score' => 92.3,
            'total_iterations' => 3,
            'stopped_reason' => 'max_iterations',
        ]);

        $mockAgent->shouldReceive('fire')
            ->once()
            ->with(
                'dismissal_motion',
                $this->user->id,
                Mockery::subset([
                    'context' => 'Case context',
                ]),
                false,
                false,
                null,
                null,
                false
            )
            ->andReturn($expectedRun);

        $this->app->instance(LegalArtilleryAgentContract::class, $mockAgent);

        $response = $this->postJson('/api/documents/generate', [
            'document_type' => 'dismissal_motion',
            'context' => 'Case context',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'document_type',
                    'status',
                    'final_document',
                    'final_score',
                    'total_iterations',
                    'stopped_reason',
                    'created_at',
                    'updated_at',
                ],
            ]);
    }

    /**
     * Test 10: GET /api/documents/runs/{id} - Successful retrieval with relationships
     *
     * @test
     */
    public function it_retrieves_specific_run_with_relationships()
    {
        $this->actingAs($this->user);

        // Create run with iterations and context
        $run = DocumentGenerationRun::factory()->create([
            'user_id' => $this->user->id,
        ]);

        DocumentIteration::factory()->count(3)->create([
            'generation_run_id' => $run->id,
        ]);

        DocumentContext::factory()->create([
            'generation_run_id' => $run->id,
        ]);

        $response = $this->getJson("/api/documents/runs/{$run->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'document_type',
                    'status',
                    'iterations' => [
                        '*' => [
                            'id',
                            'iteration_number',
                            'phase',
                            'weighted_score',
                        ],
                    ],
                    'context' => [
                        'id',
                        'context_type',
                        'assembled_context',
                    ],
                ],
            ])
            ->assertJsonCount(3, 'data.iterations');
    }

    /**
     * Test 11: GET /api/documents/runs/{id} - 404 for non-existent run
     *
     * @test
     */
    public function it_returns_404_for_non_existent_run()
    {
        $this->actingAs($this->user);

        $response = $this->getJson('/api/documents/runs/01JCQR7X8ZMQH4P9WK5YBTN999');

        $response->assertStatus(404);
    }

    /**
     * Test 12: GET /api/documents/runs/{id} - Authorization check (users can only see their own)
     *
     * @test
     */
    public function it_prevents_users_from_viewing_other_users_runs()
    {
        $this->actingAs($this->user);

        // Create a run owned by another user
        $otherRun = DocumentGenerationRun::factory()->create([
            'user_id' => $this->otherUser->id,
        ]);

        $response = $this->getJson("/api/documents/runs/{$otherRun->id}");

        $response->assertStatus(403);
    }

    /**
     * Test 13: GET /api/documents/runs/{id} - Authentication required
     *
     * @test
     */
    public function it_requires_authentication_to_view_run()
    {
        $run = DocumentGenerationRun::factory()->create();

        $response = $this->getJson("/api/documents/runs/{$run->id}");

        $response->assertStatus(401);
    }

    /**
     * Test 14: GET /api/documents/runs - List user's runs with pagination
     *
     * @test
     */
    public function it_lists_users_runs_with_pagination()
    {
        $this->actingAs($this->user);

        // Create 20 runs for the user
        DocumentGenerationRun::factory()->count(20)->create([
            'user_id' => $this->user->id,
        ]);

        // Create runs for another user (should not be included)
        DocumentGenerationRun::factory()->count(5)->create([
            'user_id' => $this->otherUser->id,
        ]);

        $response = $this->getJson('/api/documents/runs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'document_type',
                        'status',
                    ],
                ],
                'links' => [
                    'first',
                    'last',
                    'prev',
                    'next',
                ],
                'meta' => [
                    'current_page',
                    'from',
                    'last_page',
                    'per_page',
                    'to',
                    'total',
                ],
            ])
            ->assertJsonPath('meta.total', 20)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonCount(15, 'data'); // First page should have 15 items
    }

    /**
     * Test 15: GET /api/documents/runs - Filter by status
     *
     * @test
     */
    public function it_filters_runs_by_status()
    {
        $this->actingAs($this->user);

        // Create runs with different statuses
        DocumentGenerationRun::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'status' => 'completed',
        ]);

        DocumentGenerationRun::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'status' => 'running',
        ]);

        DocumentGenerationRun::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'failed',
        ]);

        $response = $this->getJson('/api/documents/runs?status=completed');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 3);
    }

    /**
     * Test 16: GET /api/documents/runs - Filter by document_type
     *
     * @test
     */
    public function it_filters_runs_by_document_type()
    {
        $this->actingAs($this->user);

        DocumentGenerationRun::factory()->count(4)->create([
            'user_id' => $this->user->id,
            'document_type' => 'suppression_motion',
        ]);

        DocumentGenerationRun::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'document_type' => 'appeal_brief',
        ]);

        $response = $this->getJson('/api/documents/runs?document_type=suppression_motion');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 4);
    }

    /**
     * Test 17: GET /api/documents/runs - Sorted by newest first by default
     *
     * @test
     */
    public function it_sorts_runs_by_newest_first()
    {
        $this->actingAs($this->user);

        $oldest = DocumentGenerationRun::factory()->create([
            'user_id' => $this->user->id,
            'created_at' => now()->subDays(3),
        ]);

        $middle = DocumentGenerationRun::factory()->create([
            'user_id' => $this->user->id,
            'created_at' => now()->subDays(1),
        ]);

        $newest = DocumentGenerationRun::factory()->create([
            'user_id' => $this->user->id,
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/documents/runs');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.id', $newest->id)
            ->assertJsonPath('data.1.id', $middle->id)
            ->assertJsonPath('data.2.id', $oldest->id);
    }

    /**
     * Test 18: GET /api/documents/runs - Only returns authenticated user's runs
     *
     * @test
     */
    public function it_only_returns_authenticated_users_runs()
    {
        $this->actingAs($this->user);

        // User's runs
        DocumentGenerationRun::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        // Other user's runs (should not be visible)
        DocumentGenerationRun::factory()->count(5)->create([
            'user_id' => $this->otherUser->id,
        ]);

        $response = $this->getJson('/api/documents/runs');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 3);
    }

    /**
     * Test 19: GET /api/documents/runs - Authentication required
     *
     * @test
     */
    public function it_requires_authentication_to_list_runs()
    {
        $response = $this->getJson('/api/documents/runs');

        $response->assertStatus(401);
    }

    /**
     * Test 20: GET /api/documents/runs - Pagination page parameter works
     *
     * @test
     */
    public function it_supports_pagination_page_parameter()
    {
        $this->actingAs($this->user);

        DocumentGenerationRun::factory()->count(30)->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/documents/runs?page=2');

        $response->assertStatus(200)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonCount(15, 'data');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
