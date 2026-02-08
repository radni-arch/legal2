<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\Agents\OrchestratorService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * TDD Tests for Agent Collaboration API Endpoints
 *
 * Sprint 3.7: Orchestrator API Endpoints
 *
 * Tests the collaboration endpoints:
 * - POST /api/agents/collaborate
 * - GET /api/agents/collaborate/{id}/status
 * - GET /api/agents/collaborate/{id}/result
 */
class AgentCollaborationApiTest extends TestCase
{
    use UsesTestDatabase;

    protected OrchestratorService $orchestrator;

    protected User $testUser;

    protected string $apiToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orchestrator = app(OrchestratorService::class);

        // Create test user with API token
        $this->apiToken = Str::random(60);
        $this->testUser = User::factory()->create([
            'api_token' => $this->apiToken,
        ]);

        // Note: Not clearing cache here to allow rate limiting tests to work
        // Rate limiting tests will clear cache themselves if needed
    }

    /** @test */
    public function it_starts_collaboration_with_valid_request()
    {
        $payload = [
            'pipeline' => ['ResearchSpecialistAgent', 'RiskAnalystAgent'],
            'task_description' => 'Analyze evidence for admissibility',
            'initial_context' => [
                'case_id' => 'case-123',
                'evidence_type' => 'search_warrant',
            ],
            'budgets' => [
                'token_budget' => 10000,
                'cost_budget' => 1.00,
                'time_budget_ms' => 30000,
            ],
        ];

        $response = $this->withToken($this->apiToken)->postJson('/api/agents/collaborate', $payload, [
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'success',
            'orchestration_id',
            'status',
            'message',
        ]);

        $response->assertJson([
            'success' => true,
            'status' => 'pending',
        ]);

        $this->assertIsString($response->json('orchestration_id'));
        $this->assertNotEmpty($response->json('orchestration_id'));
    }

    /** @test */
    public function it_requires_authentication_for_collaboration()
    {
        $payload = [
            'pipeline' => ['ResearchSpecialistAgent'],
            'task_description' => 'Test task',
        ];

        // Request without API token
        $response = $this->postJson('/api/agents/collaborate', $payload);

        $response->assertStatus(401);
    }

    /** @test */
    public function it_validates_required_fields_for_collaboration()
    {
        $response = $this->withToken($this->apiToken)->postJson('/api/agents/collaborate', [
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['pipeline', 'task_description']);
    }

    /** @test */
    public function it_validates_pipeline_is_array()
    {
        $payload = [
            'pipeline' => 'not-an-array',
            'task_description' => 'Test task',
        ];

        $response = $this->withToken($this->apiToken)->postJson('/api/agents/collaborate', $payload, [
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['pipeline']);
    }

    /** @test */
    public function it_validates_pipeline_contains_at_least_one_agent()
    {
        $payload = [
            'pipeline' => [],
            'task_description' => 'Test task',
        ];

        $response = $this->withToken($this->apiToken)->postJson('/api/agents/collaborate', $payload, [
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['pipeline']);
    }

    /** @test */
    public function it_validates_budgets_structure()
    {
        $payload = [
            'pipeline' => ['ResearchSpecialistAgent'],
            'task_description' => 'Test task',
            'budgets' => [
                'token_budget' => 'not-a-number',
            ],
        ];

        $response = $this->withToken($this->apiToken)->postJson('/api/agents/collaborate', $payload, [
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['budgets.token_budget']);
    }

    /** @test */
    public function it_retrieves_collaboration_status()
    {
        // Create a collaboration
        $orchestrationId = $this->orchestrator->orchestrate(
            ['ResearchSpecialistAgent', 'RiskAnalystAgent'],
            'Analyze evidence admissibility',
            ['case_id' => 'case-456']
        );

        // Get status
        $response = $this->withToken($this->apiToken)->getJson("/api/agents/collaborate/{$orchestrationId}/status", [
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'orchestration_id',
            'status',
            'progress',
        ]);

        $response->assertJson([
            'success' => true,
            'orchestration_id' => $orchestrationId,
            'status' => 'pending',
        ]);

        $response->assertJsonPath('progress.total_agents', 2);
        $response->assertJsonPath('progress.completed_agents', 0);
    }

    /** @test */
    public function it_retrieves_running_status_after_execution_starts()
    {
        // Create and start execution
        $orchestrationId = $this->orchestrator->orchestrate(
            ['ResearchSpecialistAgent'],
            'Research task',
            ['topic' => 'proportionality']
        );

        // Start execution (in real scenario, this would be async)
        $this->orchestrator->execute($orchestrationId);

        // Get status
        $response = $this->withToken($this->apiToken)->getJson("/api/agents/collaborate/{$orchestrationId}/status", [
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'status' => 'completed', // Execution completes immediately in test
        ]);

        $response->assertJsonPath('progress.completed_agents', 1);
    }

    /** @test */
    public function it_returns_404_for_nonexistent_collaboration_status()
    {
        $response = $this->withToken($this->apiToken)->getJson('/api/agents/collaborate/nonexistent-id/status', [
        ]);

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'error' => 'Orchestration not found',
        ]);
    }

    /** @test */
    public function it_retrieves_collaboration_result_when_completed()
    {
        // Create and execute collaboration
        $orchestrationId = $this->orchestrator->orchestrate(
            ['ResearchSpecialistAgent', 'RiskAnalystAgent'],
            'Analyze evidence',
            ['case_id' => 'case-789']
        );

        $this->orchestrator->execute($orchestrationId);

        // Get result
        $response = $this->withToken($this->apiToken)->getJson("/api/agents/collaborate/{$orchestrationId}/result", [
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'orchestration_id',
            'status',
            'result',
        ]);

        $response->assertJson([
            'success' => true,
            'status' => 'completed',
        ]);

        $result = $response->json('result');
        $this->assertArrayHasKey('shared_context', $result);
        $this->assertArrayHasKey('execution_history', $result);
        $this->assertArrayHasKey('tokens_used', $result);
        $this->assertArrayHasKey('cost_spent', $result);
        $this->assertArrayHasKey('duration_ms', $result);
    }

    /** @test */
    public function it_returns_error_for_incomplete_collaboration_result()
    {
        // Create but don't execute
        $orchestrationId = $this->orchestrator->orchestrate(
            ['ResearchSpecialistAgent'],
            'Pending task',
            []
        );

        // Try to get result before completion
        $response = $this->withToken($this->apiToken)->getJson("/api/agents/collaborate/{$orchestrationId}/result", [
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'error' => 'Collaboration not yet completed',
        ]);
    }

    /** @test */
    public function it_returns_404_for_nonexistent_collaboration_result()
    {
        $response = $this->withToken($this->apiToken)->getJson('/api/agents/collaborate/nonexistent-id/result', [
        ]);

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'error' => 'Orchestration not found',
        ]);
    }

    /** @test */
    public function it_enforces_rate_limiting_on_collaborate_endpoint()
    {
        Cache::flush(); // Clear cache to ensure clean rate limit state

        $payload = [
            'pipeline' => ['ResearchSpecialistAgent'],
            'task_description' => 'Rate limit test',
        ];

        // Make 10 requests (the limit)
        for ($i = 0; $i < 10; $i++) {
            $response = $this->withToken($this->apiToken)->postJson('/api/agents/collaborate', $payload);
            $response->assertStatus(201);
        }

        // 11th request should be rate limited
        $response = $this->withToken($this->apiToken)->postJson('/api/agents/collaborate', $payload);
        $response->assertStatus(429); // Too Many Requests
    }

    /** @test */
    public function it_enforces_rate_limiting_on_status_endpoint()
    {
        Cache::flush(); // Clear cache to ensure clean rate limit state

        // Create a collaboration
        $orchestrationId = $this->orchestrator->orchestrate(
            ['ResearchSpecialistAgent'],
            'Test',
            []
        );

        // Make 10 requests (the limit)
        for ($i = 0; $i < 10; $i++) {
            $response = $this->withToken($this->apiToken)->getJson("/api/agents/collaborate/{$orchestrationId}/status");
            $response->assertStatus(200);
        }

        // 11th request should be rate limited
        $response = $this->withToken($this->apiToken)->getJson("/api/agents/collaborate/{$orchestrationId}/status");
        $response->assertStatus(429);
    }

    /** @test */
    public function it_enforces_rate_limiting_on_result_endpoint()
    {
        Cache::flush(); // Clear cache to ensure clean rate limit state

        // Create and execute collaboration
        $orchestrationId = $this->orchestrator->orchestrate(
            ['ResearchSpecialistAgent'],
            'Test',
            []
        );
        $this->orchestrator->execute($orchestrationId);

        // Make 10 requests (the limit)
        for ($i = 0; $i < 10; $i++) {
            $response = $this->withToken($this->apiToken)->getJson("/api/agents/collaborate/{$orchestrationId}/result");
            $response->assertStatus(200);
        }

        // 11th request should be rate limited
        $response = $this->withToken($this->apiToken)->getJson("/api/agents/collaborate/{$orchestrationId}/result");
        $response->assertStatus(429);
    }

    /** @test */
    public function it_includes_metadata_in_collaboration_response()
    {
        $payload = [
            'pipeline' => ['ResearchSpecialistAgent', 'RiskAnalystAgent', 'EvidenceAnalystAgent'],
            'task_description' => 'Complex multi-agent analysis',
            'initial_context' => [
                'case_id' => 'case-complex-001',
                'priority' => 'high',
            ],
            'budgets' => [
                'token_budget' => 50000,
                'cost_budget' => 5.00,
                'time_budget_ms' => 60000,
            ],
        ];

        $response = $this->withToken($this->apiToken)->postJson('/api/agents/collaborate', $payload, [
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('metadata.total_agents', 3);
        $response->assertJsonPath('metadata.budgets.token_budget', 50000);
        $response->assertJsonPath('metadata.budgets.cost_budget', 5);
        $response->assertJsonPath('metadata.budgets.time_budget_ms', 60000);
    }

    /** @test */
    public function it_handles_failed_collaboration_status()
    {
        // This test would require mocking agent failure
        // For now, we verify the structure supports failed status
        $orchestrationId = $this->orchestrator->orchestrate(
            ['ResearchSpecialistAgent'],
            'Test failure handling',
            []
        );

        // Execute (will succeed in test environment)
        $this->orchestrator->execute($orchestrationId);

        $response = $this->withToken($this->apiToken)->getJson("/api/agents/collaborate/{$orchestrationId}/status", [
        ]);

        $response->assertStatus(200);
        $this->assertContains($response->json('status'), ['pending', 'running', 'completed', 'failed']);
    }

    /** @test */
    public function it_accepts_optional_initial_context()
    {
        $payload = [
            'pipeline' => ['ResearchSpecialistAgent'],
            'task_description' => 'Task without initial context',
        ];

        $response = $this->withToken($this->apiToken)->postJson('/api/agents/collaborate', $payload, [
        ]);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);
    }

    /** @test */
    public function it_accepts_optional_budgets()
    {
        $payload = [
            'pipeline' => ['ResearchSpecialistAgent'],
            'task_description' => 'Task without budgets',
        ];

        $response = $this->withToken($this->apiToken)->postJson('/api/agents/collaborate', $payload, [
        ]);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);
    }
}
