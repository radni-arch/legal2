<?php

namespace Tests\Unit\Agents;

use App\Agents\LegalArtilleryAgent;
use App\DTOs\CaseContext;
use App\DTOs\DocumentProfile;
use App\Models\DocumentGenerationRun;
use App\Models\User;
use App\Services\LegalArtillery\LlmClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class LegalArtilleryAgentUnifiedTest extends TestCase
{
    use RefreshDatabase;

    public function test_unified_agent_generates_with_profile_and_iterations(): void
    {
        $user = User::factory()->create();

        // Mock LLM to avoid real API calls
        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->andReturn(
            json_encode(['sections' => [['key' => 'test', 'title' => 'Test', 'guidance' => 'Test']]]),
            'Generated document content with legal arguments...',
            'Final polished content',
            json_encode([
                'scores' => ['legal_rigor' => 85, 'persuasiveness' => 80, 'clarity' => 90, 'evidence_integration' => 75, 'formatting' => 85],
                'feedback' => ['strengths' => ['Good structure'], 'weaknesses' => [], 'specific_improvements' => []]
            ])
        );
        $this->app->instance(LlmClient::class, $llm);

        $agent = app(LegalArtilleryAgent::class);

        $result = $agent->fire(
            profileKey: 'predsjednik_suda',
            userId: $user->id,
            sendEmail: false,
            maxIterations: 2
        );

        // Should return DocumentGenerationRun with iterations
        $this->assertInstanceOf(DocumentGenerationRun::class, $result);
        $this->assertEquals('completed', $result->status);
        $this->assertNotNull($result->final_document);
        $this->assertGreaterThan(0, $result->total_iterations);
    }

    public function test_unified_agent_stores_profile_context(): void
    {
        $user = User::factory()->create();

        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->andReturn(
            json_encode(['sections' => [['key' => 't', 'title' => 'T', 'guidance' => 'G']]]),
            'Content', 'Polished',
            json_encode(['scores' => ['legal_rigor' => 85], 'feedback' => ['strengths' => [], 'weaknesses' => [], 'specific_improvements' => []]])
        );
        $this->app->instance(LlmClient::class, $llm);

        $agent = app(LegalArtilleryAgent::class);
        $result = $agent->fire('predsjednik_suda', $user->id, sendEmail: false);

        // Context should include profile data
        $context = $result->context;
        $this->assertNotNull($context);
        $assembledContext = json_decode($context->assembled_context, true);
        $this->assertArrayHasKey('profile_key', $assembledContext);
        $this->assertEquals('predsjednik_suda', $assembledContext['profile_key']);
    }

    public function test_unified_agent_uses_legal_provisions(): void
    {
        $user = User::factory()->create();

        // Seed provisions first
        $this->seed(\Database\Seeders\LegalProvisionsSeeder::class);

        $llm = Mockery::mock(LlmClient::class);
        // Capture all prompts to verify provisions are included
        $allPrompts = '';
        $llm->shouldReceive('generate')
            ->andReturnUsing(function ($system, $prompt) use (&$allPrompts) {
                $allPrompts .= $system . $prompt;
                return 'Generated content';
            });
        $this->app->instance(LlmClient::class, $llm);

        $agent = app(LegalArtilleryAgent::class);

        try {
            $agent->fire('predsjednik_suda', $user->id, sendEmail: false, maxIterations: 1);
        } catch (\Exception $e) {
            // Expected to fail on JSON parse, but we captured the prompts
        }

        // Provisions use "cl." format from LegalProvision::shortCitation()
        $this->assertStringContainsString('cl.150', $allPrompts);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
