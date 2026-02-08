<?php

namespace Tests\Unit\Providers;

use App\Agents\Contracts\LegalArtilleryAgentContract;
use App\Agents\LegalArtilleryAgent;
use App\Agents\LegalArtilleryOrchestrator;
use App\Services\LegalArtillery\DigitalSigner;
use App\Services\LegalArtillery\DocxRenderer;
use App\Services\LegalArtillery\GmailDispatcher;
use App\Services\LegalArtillery\LlmClient;
use App\Services\LegalArtillery\ResponseHandler;
use Tests\TestCase;

class LegalArtilleryServiceProviderTest extends TestCase
{
    public function test_llm_client_is_registered_as_singleton(): void
    {
        $instance1 = app(LlmClient::class);
        $instance2 = app(LlmClient::class);

        $this->assertInstanceOf(LlmClient::class, $instance1);
        $this->assertSame($instance1, $instance2, 'LlmClient should be a singleton');
    }

    public function test_orchestrator_is_registered_as_singleton(): void
    {
        $instance1 = app(LegalArtilleryOrchestrator::class);
        $instance2 = app(LegalArtilleryOrchestrator::class);

        $this->assertInstanceOf(LegalArtilleryOrchestrator::class, $instance1);
        $this->assertSame($instance1, $instance2, 'LegalArtilleryOrchestrator should be a singleton');
    }

    public function test_docx_renderer_is_bound(): void
    {
        $renderer = app(DocxRenderer::class);

        $this->assertInstanceOf(DocxRenderer::class, $renderer);
    }

    public function test_gmail_dispatcher_is_bound(): void
    {
        $dispatcher = app(GmailDispatcher::class);

        $this->assertInstanceOf(GmailDispatcher::class, $dispatcher);
    }

    public function test_legal_artillery_agent_is_bound(): void
    {
        $agent = app(LegalArtilleryAgent::class);

        $this->assertInstanceOf(LegalArtilleryAgent::class, $agent);
    }

    public function test_contract_resolves_to_agent(): void
    {
        $agent = app(LegalArtilleryAgentContract::class);

        $this->assertInstanceOf(LegalArtilleryAgent::class, $agent);
    }

    public function test_response_handler_is_bound(): void
    {
        $handler = app(ResponseHandler::class);

        $this->assertInstanceOf(ResponseHandler::class, $handler);
    }

    public function test_digital_signer_is_bound(): void
    {
        $signer = app(DigitalSigner::class);

        $this->assertInstanceOf(DigitalSigner::class, $signer);
    }

    public function test_orchestrator_receives_llm_client(): void
    {
        $orchestrator = app(LegalArtilleryOrchestrator::class);

        $reflection = new \ReflectionClass($orchestrator);
        $property = $reflection->getProperty('llm');
        $property->setAccessible(true);
        $llm = $property->getValue($orchestrator);

        $this->assertInstanceOf(LlmClient::class, $llm);
    }
}
