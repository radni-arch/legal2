<?php

namespace Tests\Unit\Agents;

use App\Agents\LegalArtilleryOrchestrator;
use App\Services\LegalArtillery\LlmClient;
use App\Services\LegalArtillery\ProfileContextBuilder;
use Mockery;
use Tests\TestCase;
use Vizra\VizraADK\Agents\BaseLlmAgent;

class VizraIntegrationTest extends TestCase
{
    private LegalArtilleryOrchestrator $orchestrator;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock dependencies so we can resolve orchestrator from container
        $this->app->instance(LlmClient::class, Mockery::mock(LlmClient::class));
        $this->app->instance(ProfileContextBuilder::class, Mockery::mock(ProfileContextBuilder::class));

        $this->orchestrator = app(LegalArtilleryOrchestrator::class);
    }

    public function test_orchestrator_extends_base_llm_agent(): void
    {
        $this->assertInstanceOf(BaseLlmAgent::class, $this->orchestrator);
    }

    public function test_orchestrator_has_required_vizra_methods(): void
    {
        // getName() from BaseAgent, getConfig() custom, execute() from BaseLlmAgent
        $this->assertTrue(method_exists($this->orchestrator, 'run'));
        $this->assertTrue(method_exists($this->orchestrator, 'getConfig'));
        $this->assertTrue(method_exists($this->orchestrator, 'getName'));
        $this->assertTrue(method_exists($this->orchestrator, 'execute'));
    }

    public function test_orchestrator_provides_config(): void
    {
        $config = $this->orchestrator->getConfig();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('model', $config);
        $this->assertArrayHasKey('max_tokens', $config);
        $this->assertArrayHasKey('max_iterations', $config);
        $this->assertArrayHasKey('convergence_threshold', $config);
        $this->assertArrayHasKey('supported_profiles', $config);
    }

    public function test_orchestrator_config_returns_correct_model(): void
    {
        $config = $this->orchestrator->getConfig();

        $this->assertEquals(
            config('legal-artillery.generation.model', 'claude-sonnet-4-20250514'),
            $config['model']
        );
    }

    public function test_orchestrator_config_returns_correct_max_tokens(): void
    {
        $config = $this->orchestrator->getConfig();

        $this->assertEquals(
            config('legal-artillery.generation.max_tokens', 8192),
            $config['max_tokens']
        );
    }

    public function test_orchestrator_returns_correct_name(): void
    {
        $this->assertEquals('legal_artillery', $this->orchestrator->getName());
    }

    public function test_orchestrator_returns_description(): void
    {
        $description = $this->orchestrator->getDescription();

        $this->assertNotEmpty($description);
        $this->assertIsString($description);
    }

    public function test_orchestrator_config_lists_supported_profiles(): void
    {
        $config = $this->orchestrator->getConfig();

        $this->assertIsArray($config['supported_profiles']);
        // Profiles are loaded from config, should include known profiles
        $profiles = config('legal-artillery.profiles', []);
        $this->assertEquals(array_keys($profiles), $config['supported_profiles']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
