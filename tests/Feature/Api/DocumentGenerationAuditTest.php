<?php

namespace Tests\Feature\Api;

use App\Models\DocumentGenerationRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentGenerationAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Bypass API token middleware for testing (follows existing test pattern)
        $this->withoutMiddleware(\App\Http\Middleware\ApiTokenAuth::class);
    }

    public function test_audit_returns_expected_structure(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $run = DocumentGenerationRun::create([
            'document_type' => 'predsjednik_suda',
            'status' => 'completed',
            'final_document' => 'Test document content',
            'final_score' => 8.5,
            'total_iterations' => 3,
            'stopped_reason' => 'converged',
            'user_id' => $user->id,
        ]);

        $response = $this->getJson("/api/documents/runs/{$run->id}/audit");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'run_id',
                    'document_type',
                    'status',
                    'created_at',
                    'user',
                    'approval',
                    'generation',
                    'iterations',
                    'dispatch',
                    'document_hash',
                    'exported_at',
                ],
            ]);
    }

    public function test_audit_includes_document_hash(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $content = 'Test document for hashing';
        $run = DocumentGenerationRun::create([
            'document_type' => 'predsjednik_suda',
            'status' => 'completed',
            'final_document' => $content,
            'user_id' => $user->id,
        ]);

        $response = $this->getJson("/api/documents/runs/{$run->id}/audit");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals(hash('sha256', $content), $data['document_hash']);
    }

    public function test_audit_403_for_other_users_run(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user);

        $run = DocumentGenerationRun::create([
            'document_type' => 'predsjednik_suda',
            'status' => 'completed',
            'user_id' => $other->id,
        ]);

        $response = $this->getJson("/api/documents/runs/{$run->id}/audit");
        $response->assertStatus(403);
    }

    public function test_audit_404_for_missing_run(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->getJson('/api/documents/runs/nonexistent/audit');
        $response->assertStatus(404);
    }
}
