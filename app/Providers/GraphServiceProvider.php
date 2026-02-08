<?php

namespace App\Providers;

use App\Services\Graph\CaseGraphSyncService;
use App\Services\Graph\GraphCitationLinker;
use App\Services\Graph\GraphDataIntegrityService;
use App\Services\Graph\GraphKeywordLinker;
use App\Services\Graph\GraphRagOrchestrator;
use App\Services\Graph\GraphSimilarityLinker;
use App\Services\Graph\JudgeGraphSyncService;
use App\Services\Graph\LegalPrincipleExtractor;
use App\Services\Graph\LegalPrincipleGraphSyncService;
use App\Services\Graph\PartyGraphSyncService;
use App\Services\Graph\PrecedentDetector;
use App\Services\Graph\PrecedentLinker;
use App\Services\Graph\TextractGraphSyncService;
use App\Services\GraphDatabaseService;
use App\Services\TaggingService;
use Illuminate\Support\ServiceProvider;

class GraphServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(GraphDatabaseService::class, function ($app) {
            return new GraphDatabaseService;
        });

        $this->app->singleton(TaggingService::class, function ($app) {
            return new TaggingService(
                $app->make(GraphDatabaseService::class)
            );
        });

        $this->app->singleton(GraphCitationLinker::class, function ($app) {
            return new GraphCitationLinker(
                $app->make(GraphDatabaseService::class)
            );
        });

        $this->app->singleton(GraphSimilarityLinker::class, function ($app) {
            return new GraphSimilarityLinker(
                $app->make(GraphDatabaseService::class)
            );
        });

        $this->app->singleton(GraphKeywordLinker::class, function ($app) {
            return new GraphKeywordLinker(
                $app->make(GraphDatabaseService::class),
                $app->bound(\App\Services\AdvancedKeywordExtractor::class)
                    ? $app->make(\App\Services\AdvancedKeywordExtractor::class)
                    : null
            );
        });

        $this->app->singleton(CaseGraphSyncService::class, function ($app) {
            return new CaseGraphSyncService(
                $app->make(GraphDatabaseService::class),
                $app->make(GraphKeywordLinker::class),
                $app->make(GraphCitationLinker::class),
                $app->make(GraphSimilarityLinker::class),
                $app->make(TaggingService::class)
            );
        });

        $this->app->singleton(TextractGraphSyncService::class, function ($app) {
            return new TextractGraphSyncService(
                $app->make(GraphDatabaseService::class),
                $app->make(GraphKeywordLinker::class),
                $app->make(GraphCitationLinker::class),
                $app->make(GraphSimilarityLinker::class),
                $app->make(TaggingService::class)
            );
        });

        // Register Judge Graph Sync Service
        $this->app->singleton(JudgeGraphSyncService::class, function ($app) {
            return new JudgeGraphSyncService(
                $app->make(GraphDatabaseService::class)
            );
        });

        // Register Party Graph Sync Service
        $this->app->singleton(PartyGraphSyncService::class, function ($app) {
            return new PartyGraphSyncService(
                $app->make(GraphDatabaseService::class)
            );
        });

        // Register Legal Principle Extractor (no dependencies)
        $this->app->singleton(LegalPrincipleExtractor::class, function ($app) {
            return new LegalPrincipleExtractor();
        });

        // Register Legal Principle Graph Sync Service
        $this->app->singleton(LegalPrincipleGraphSyncService::class, function ($app) {
            return new LegalPrincipleGraphSyncService(
                $app->make(GraphDatabaseService::class),
                $app->make(LegalPrincipleExtractor::class)
            );
        });

        // Register Precedent Linker
        $this->app->singleton(PrecedentLinker::class, function ($app) {
            return new PrecedentLinker(
                $app->make(GraphDatabaseService::class)
            );
        });

        // Register Precedent Detector (no dependencies)
        $this->app->singleton(PrecedentDetector::class, function ($app) {
            return new PrecedentDetector();
        });

        // Register Graph Data Integrity Service
        $this->app->singleton(GraphDataIntegrityService::class, function ($app) {
            return new GraphDataIntegrityService(
                $app->make(GraphDatabaseService::class)
            );
        });

        // Register GraphRagOrchestrator as the primary service
        $this->app->singleton(GraphRagOrchestrator::class, function ($app) {
            return new GraphRagOrchestrator(
                $app->make(GraphDatabaseService::class),
                $app->make(TaggingService::class),
                $app->make(CaseGraphSyncService::class),
                $app->make(TextractGraphSyncService::class),
                $app->bound(\App\Services\AdvancedKeywordExtractor::class)
                    ? $app->make(\App\Services\AdvancedKeywordExtractor::class)
                    : null,
                $app->make(GraphCitationLinker::class),
                $app->make(GraphSimilarityLinker::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Console\Commands\GraphInitCommand::class,
                \App\Console\Commands\GraphSyncCommand::class,
                \App\Console\Commands\GraphSyncEnhancedCommand::class,
                \App\Console\Commands\GraphQueryCommand::class,
                \App\Console\Commands\GraphStatsCommand::class,
            ]);
        }
    }
}
