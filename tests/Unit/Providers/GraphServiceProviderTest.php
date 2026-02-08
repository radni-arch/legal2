<?php

namespace Tests\Unit\Providers;

use App\Providers\GraphServiceProvider;
use App\Services\Graph\GraphDataIntegrityService;
use App\Services\Graph\JudgeGraphSyncService;
use App\Services\Graph\LegalPrincipleExtractor;
use App\Services\Graph\PartyGraphSyncService;
use App\Services\Graph\PrecedentLinker;
use App\Services\GraphDatabaseService;
use Tests\TestCase;

class GraphServiceProviderTest extends TestCase
{
    /**
     * Test that JudgeGraphSyncService can be resolved from the container
     */
    public function test_judge_graph_sync_service_can_be_resolved(): void
    {
        $service = $this->app->make(JudgeGraphSyncService::class);

        $this->assertInstanceOf(JudgeGraphSyncService::class, $service);
    }

    /**
     * Test that JudgeGraphSyncService is properly instantiated with dependencies
     */
    public function test_judge_graph_sync_service_has_dependencies(): void
    {
        $service = $this->app->make(JudgeGraphSyncService::class);

        // Use reflection to verify the dependency is injected
        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('graph');
        $property->setAccessible(true);
        $graph = $property->getValue($service);

        $this->assertInstanceOf(GraphDatabaseService::class, $graph);
    }

    /**
     * Test that JudgeGraphSyncService is a singleton
     */
    public function test_judge_graph_sync_service_is_singleton(): void
    {
        $service1 = $this->app->make(JudgeGraphSyncService::class);
        $service2 = $this->app->make(JudgeGraphSyncService::class);

        $this->assertSame($service1, $service2);
    }

    /**
     * Test that PartyGraphSyncService can be resolved from the container
     */
    public function test_party_graph_sync_service_can_be_resolved(): void
    {
        $service = $this->app->make(PartyGraphSyncService::class);

        $this->assertInstanceOf(PartyGraphSyncService::class, $service);
    }

    /**
     * Test that PartyGraphSyncService is properly instantiated with dependencies
     */
    public function test_party_graph_sync_service_has_dependencies(): void
    {
        $service = $this->app->make(PartyGraphSyncService::class);

        // Use reflection to verify the dependency is injected
        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('graph');
        $property->setAccessible(true);
        $graph = $property->getValue($service);

        $this->assertInstanceOf(GraphDatabaseService::class, $graph);
    }

    /**
     * Test that PartyGraphSyncService is a singleton
     */
    public function test_party_graph_sync_service_is_singleton(): void
    {
        $service1 = $this->app->make(PartyGraphSyncService::class);
        $service2 = $this->app->make(PartyGraphSyncService::class);

        $this->assertSame($service1, $service2);
    }

    /**
     * Test that LegalPrincipleExtractor can be resolved from the container
     */
    public function test_legal_principle_extractor_can_be_resolved(): void
    {
        $service = $this->app->make(LegalPrincipleExtractor::class);

        $this->assertInstanceOf(LegalPrincipleExtractor::class, $service);
    }

    /**
     * Test that LegalPrincipleExtractor is a singleton
     */
    public function test_legal_principle_extractor_is_singleton(): void
    {
        $service1 = $this->app->make(LegalPrincipleExtractor::class);
        $service2 = $this->app->make(LegalPrincipleExtractor::class);

        $this->assertSame($service1, $service2);
    }

    /**
     * Test that PrecedentLinker can be resolved from the container
     */
    public function test_precedent_linker_can_be_resolved(): void
    {
        $service = $this->app->make(PrecedentLinker::class);

        $this->assertInstanceOf(PrecedentLinker::class, $service);
    }

    /**
     * Test that PrecedentLinker is properly instantiated with dependencies
     */
    public function test_precedent_linker_has_dependencies(): void
    {
        $service = $this->app->make(PrecedentLinker::class);

        // Use reflection to verify the dependency is injected
        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('graph');
        $property->setAccessible(true);
        $graph = $property->getValue($service);

        $this->assertInstanceOf(GraphDatabaseService::class, $graph);
    }

    /**
     * Test that PrecedentLinker is a singleton
     */
    public function test_precedent_linker_is_singleton(): void
    {
        $service1 = $this->app->make(PrecedentLinker::class);
        $service2 = $this->app->make(PrecedentLinker::class);

        $this->assertSame($service1, $service2);
    }

    /**
     * Test that GraphDataIntegrityService can be resolved from the container
     */
    public function test_graph_data_integrity_service_can_be_resolved(): void
    {
        $service = $this->app->make(GraphDataIntegrityService::class);

        $this->assertInstanceOf(GraphDataIntegrityService::class, $service);
    }

    /**
     * Test that GraphDataIntegrityService is properly instantiated with dependencies
     */
    public function test_graph_data_integrity_service_has_dependencies(): void
    {
        $service = $this->app->make(GraphDataIntegrityService::class);

        // Use reflection to verify the dependency is injected
        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('graph');
        $property->setAccessible(true);
        $graph = $property->getValue($service);

        $this->assertInstanceOf(GraphDatabaseService::class, $graph);
    }

    /**
     * Test that GraphDataIntegrityService is a singleton
     */
    public function test_graph_data_integrity_service_is_singleton(): void
    {
        $service1 = $this->app->make(GraphDataIntegrityService::class);
        $service2 = $this->app->make(GraphDataIntegrityService::class);

        $this->assertSame($service1, $service2);
    }

    /**
     * Test that GraphSyncEnhancedCommand is registered when running in console
     */
    public function test_graph_sync_enhanced_command_is_registered(): void
    {
        // Get all registered commands
        $registeredCommands = \Artisan::all();

        // Verify GraphSyncEnhancedCommand is registered
        $this->assertArrayHasKey('graph:sync-enhanced', $registeredCommands);
        $this->assertInstanceOf(\App\Console\Commands\GraphSyncEnhancedCommand::class, $registeredCommands['graph:sync-enhanced']);
    }
}
