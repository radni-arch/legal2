<?php

namespace Tests\Unit\Providers;

use App\Agents\Contracts\LegalArtilleryAgentContract;
use App\Agents\LegalArtilleryAgent;
use App\Agents\LegalArtilleryOrchestrator;
use App\Services\LegalArtillery\DocxRenderer;
use App\Services\LegalArtillery\LlmClient;
use Tests\TestCase;

/**
 * SOT-009: Verify Legal Artillery DI bindings are consolidated.
 *
 * These tests ensure that LegalArtilleryServiceProvider is the single
 * canonical source for all Legal Artillery container bindings, and that
 * AppServiceProvider contains zero Legal Artillery-specific registrations.
 */
class LegalArtilleryDiConsolidationTest extends TestCase
{
    // ---------------------------------------------------------------
    // Singleton resolution tests
    // ---------------------------------------------------------------

    public function test_legal_artillery_agent_resolves_as_singleton(): void
    {
        $instance1 = app(LegalArtilleryAgent::class);
        $instance2 = app(LegalArtilleryAgent::class);

        $this->assertInstanceOf(LegalArtilleryAgent::class, $instance1);
        $this->assertSame($instance1, $instance2, 'LegalArtilleryAgent must be a singleton');
    }

    public function test_legal_artillery_orchestrator_resolves_as_singleton(): void
    {
        $instance1 = app(LegalArtilleryOrchestrator::class);
        $instance2 = app(LegalArtilleryOrchestrator::class);

        $this->assertInstanceOf(LegalArtilleryOrchestrator::class, $instance1);
        $this->assertSame($instance1, $instance2, 'LegalArtilleryOrchestrator must be a singleton');
    }

    public function test_llm_client_resolves_as_singleton(): void
    {
        $instance1 = app(LlmClient::class);
        $instance2 = app(LlmClient::class);

        $this->assertInstanceOf(LlmClient::class, $instance1);
        $this->assertSame($instance1, $instance2, 'LlmClient must be a singleton');
    }

    // ---------------------------------------------------------------
    // Contract / interface binding tests
    // ---------------------------------------------------------------

    public function test_contract_resolves_to_legal_artillery_agent(): void
    {
        $resolved = app(LegalArtilleryAgentContract::class);

        $this->assertInstanceOf(
            LegalArtilleryAgent::class,
            $resolved,
            'LegalArtilleryAgentContract must resolve to LegalArtilleryAgent'
        );
    }

    // ---------------------------------------------------------------
    // Dependency injection tests
    // ---------------------------------------------------------------

    public function test_resolved_agent_has_orchestrator_injected(): void
    {
        $agent = app(LegalArtilleryAgent::class);

        $reflection = new \ReflectionClass($agent);
        $property = $reflection->getProperty('orchestrator');
        $property->setAccessible(true);
        $orchestrator = $property->getValue($agent);

        $this->assertInstanceOf(
            LegalArtilleryOrchestrator::class,
            $orchestrator,
            'LegalArtilleryAgent must have a non-null orchestrator'
        );
    }

    public function test_resolved_agent_has_renderer_injected(): void
    {
        $agent = app(LegalArtilleryAgent::class);

        $reflection = new \ReflectionClass($agent);
        $property = $reflection->getProperty('renderer');
        $property->setAccessible(true);
        $renderer = $property->getValue($agent);

        $this->assertInstanceOf(
            DocxRenderer::class,
            $renderer,
            'LegalArtilleryAgent must have a non-null DocxRenderer'
        );
    }

    // ---------------------------------------------------------------
    // Consolidation verification: AppServiceProvider has NO Legal
    // Artillery bindings (source-code level check)
    // ---------------------------------------------------------------

    public function test_app_service_provider_has_no_legal_artillery_bindings(): void
    {
        $source = file_get_contents(
            app_path('Providers/AppServiceProvider.php')
        );

        // These class references should NOT appear in AppServiceProvider
        $forbiddenPatterns = [
            'LlmClient::class' => 'LlmClient singleton',
            'LegalArtilleryOrchestrator::class' => 'LegalArtilleryOrchestrator singleton',
            'LegalArtilleryAgent::class' => 'LegalArtilleryAgent singleton',
        ];

        foreach ($forbiddenPatterns as $pattern => $label) {
            $this->assertStringNotContainsString(
                $pattern,
                $source,
                "AppServiceProvider must NOT contain {$label} binding (found '{$pattern}')"
            );
        }
    }
}
