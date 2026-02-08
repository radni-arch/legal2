<?php

namespace Tests\Unit\Agents;

use App\Agents\LegalArtilleryOrchestrator;
use App\DTOs\CaseContext;
use App\DTOs\DocumentProfile;
use App\Models\DocumentGenerationRun;
use App\Services\LegalArtillery\LlmClient;
use App\Services\LegalArtillery\ProfileContextBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;
use Vizra\VizraADK\System\AgentContext;

class LegalArtilleryOrchestratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_config_returns_expected_values(): void
    {
        $originalModel = Config::get('legal-artillery.generation.model');
        $originalMaxTokens = Config::get('legal-artillery.generation.max_tokens');
        $originalMaxIterations = Config::get('documents.max_iterations');
        $originalThreshold = Config::get('documents.convergence_threshold');
        $originalProfiles = Config::get('legal-artillery.profiles');

        Config::set('legal-artillery.generation.model', 'test-model');
        Config::set('legal-artillery.generation.max_tokens', 1234);
        Config::set('documents.max_iterations', 7);
        Config::set('documents.convergence_threshold', 2.5);
        Config::set('legal-artillery.profiles', [
            'test_profile' => [
                'name' => 'Test Profile',
                'recipient' => ['title' => 'Test'],
                'legal_basis' => [],
                'tone' => 'formal',
                'structure' => [],
                'docx_template' => 'legal-formal',
            ],
        ]);

        $orchestrator = new LegalArtilleryOrchestrator(
            Mockery::mock(LlmClient::class),
            Mockery::mock(ProfileContextBuilder::class),
        );

        $config = $orchestrator->getConfig();

        $this->assertSame('test-model', $config['model']);
        $this->assertSame(1234, $config['max_tokens']);
        $this->assertSame(7, $config['max_iterations']);
        $this->assertSame(2.5, $config['convergence_threshold']);
        $this->assertSame(['test_profile'], $config['supported_profiles']);

        Config::set('legal-artillery.generation.model', $originalModel);
        Config::set('legal-artillery.generation.max_tokens', $originalMaxTokens);
        Config::set('documents.max_iterations', $originalMaxIterations);
        Config::set('documents.convergence_threshold', $originalThreshold);
        Config::set('legal-artillery.profiles', $originalProfiles);
    }

    public function test_execute_requires_profile_key(): void
    {
        $orchestrator = new LegalArtilleryOrchestrator(
            Mockery::mock(LlmClient::class),
            Mockery::mock(ProfileContextBuilder::class),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('profile_key required');

        $orchestrator->execute(['user_id' => 10], Mockery::mock(AgentContext::class));
    }

    public function test_execute_returns_run_summary(): void
    {
        $llm = Mockery::mock(LlmClient::class);
        $contextBuilder = Mockery::mock(ProfileContextBuilder::class);

        $run = DocumentGenerationRun::factory()->completed()->create([
            'user_id' => 10,
            'document_type' => 'predsjednik_suda',
        ]);

        $orchestrator = Mockery::mock(LegalArtilleryOrchestrator::class, [$llm, $contextBuilder])
            ->makePartial();
        $orchestrator->shouldReceive('generate')
            ->once()
            ->andReturn($run);

        $result = $orchestrator->execute([
            'profile_key' => 'predsjednik_suda',
            'user_id' => 10,
            'max_iterations' => 1,
            'additional_context' => ['context' => 'Test context'],
        ], Mockery::mock(AgentContext::class));

        $this->assertSame($run->id, $result['run_id']);
        $this->assertSame('completed', $result['status']);
        $this->assertSame($run->final_document, $result['final_document']);
        $this->assertSame($run->final_score, $result['final_score']);
        $this->assertSame($run->total_iterations, $result['total_iterations']);
        $this->assertSame($run->stopped_reason, $result['stopped_reason']);
    }

    public function test_generate_throws_when_llm_is_not_configured(): void
    {
        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('isConfigured')->andReturnFalse();

        $contextBuilder = Mockery::mock(ProfileContextBuilder::class);
        $contextBuilder->shouldNotReceive('reset');

        $orchestrator = new LegalArtilleryOrchestrator($llm, $contextBuilder);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot start document generation');

        $orchestrator->generate(
            DocumentProfile::fromConfig('predsjednik_suda'),
            CaseContext::fromConfig(),
            1,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
