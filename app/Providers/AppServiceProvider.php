<?php

namespace App\Providers;

use App\Events\CaseUploaded;
use App\Events\CourtDecisionIngested;
use App\Events\LawIngested;
use App\Http\Livewire\EoglasnaMonitoring;
use App\Http\Livewire\EpredmetWidget;
use App\Http\Livewire\GraphViewer;
use App\Http\Livewire\GupTimeline;
use App\Http\Livewire\IngestedLawsManager;
use App\Http\Livewire\LegalPlayground;
use App\Http\Livewire\OpenAILogViewer;
use App\Http\Livewire\OpenAIResponsesViewer;
use App\Http\Livewire\OpenAIVectorManager;
use App\Http\Livewire\TextractManager;
use App\Http\Livewire\TranscriptPreviewer;
use App\Http\Livewire\UnifiedSearch;
use App\Listeners\SyncCaseToGraph;
use App\Listeners\SyncDecisionToGraph;
use App\Listeners\SyncLawToGraph;
use App\Livewire\AgentPerformanceDashboard;
use App\Livewire\CaseAnalysisDashboard;
use App\Livewire\CaseFileCompleteness;
use App\Livewire\CaseOverview;
use App\Livewire\Graph\AlertPanelController;
use App\Livewire\Graph\ForceGraphController;
use App\Livewire\LegalArtillery\Dashboard;
use App\Livewire\LegalArtillery\GenerationMonitor;
use App\Livewire\LegalArtillery\NewGeneration;
use App\Livewire\LegalArtillery\RunDetails;
use App\Services\AdvancedKeywordExtractor;
use App\Services\Odluke\OdlukeClient;
use App\Services\OpenAIService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use App\Services\LegalArtillery\PiiRedactor;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OpenAIService::class, function () {
            return new OpenAIService;
        });

        // Register LegalArtillery LlmClient singleton
        $this->app->singleton(\App\Services\LegalArtillery\LlmClient::class, function () {
            return \App\Services\LegalArtillery\LlmClient::fromConfig();
        });

        // Register LegalArtilleryOrchestrator (unified Worker/Critic with profile context)
        $this->app->singleton(\App\Agents\LegalArtilleryOrchestrator::class, function ($app) {
            return new \App\Agents\LegalArtilleryOrchestrator(
                $app->make(\App\Services\LegalArtillery\LlmClient::class),
                new \App\Services\LegalArtillery\ProfileContextBuilder(),
                $app->make(PiiRedactor::class),
            );
        });

        // Register LegalArtilleryAgent (main facade for document generation)
        $this->app->singleton(\App\Agents\LegalArtilleryAgent::class, function ($app) {
            return new \App\Agents\LegalArtilleryAgent(
                $app->make(\App\Agents\LegalArtilleryOrchestrator::class),
                new \App\Services\LegalArtillery\DocxRenderer(),
                config('legal-artillery.gmail.enabled')
                    ? new \App\Services\LegalArtillery\GmailDispatcher($app->make(PiiRedactor::class))
                    : null,
            );
        });

        $this->app->singleton(OdlukeClient::class, function () {
            return OdlukeClient::fromConfig();
        });

        $this->app->singleton(\App\Services\Esljp\EsljpClient::class, function () {
            return \App\Services\Esljp\EsljpClient::fromConfig();
        });

        $this->app->singleton(\App\Services\Usud\UsudClient::class, function () {
            return \App\Services\Usud\UsudClient::fromConfig();
        });

        // Register AdvancedKeywordExtractor with dependency injection
        $this->app->singleton(AdvancedKeywordExtractor::class, function ($app) {
            return new AdvancedKeywordExtractor($app->make(OpenAIService::class));
        });

        // Register Graph services (extracted from GraphRagService)
        $this->app->singleton(\App\Services\Graph\GraphKeywordLinker::class);
        $this->app->singleton(\App\Services\Graph\GraphCitationLinker::class);
        $this->app->singleton(\App\Services\Graph\GraphSimilarityLinker::class);
        $this->app->singleton(\App\Services\Graph\LawGraphSyncService::class);
        $this->app->singleton(\App\Services\Graph\DecisionGraphSyncService::class);
        $this->app->singleton(\App\Services\Graph\CaseGraphSyncService::class);
        $this->app->singleton(\App\Services\Graph\TextractGraphSyncService::class);

        // Register Search services (Tasks 2.4, 2.6, 2.9)
        // $this->app->singleton(\App\Services\SearchEmbeddingService::class);
        $this->app->singleton(\App\Services\Search\LawSearchService::class);
        $this->app->singleton(\App\Services\Search\CaseSearchService::class);
        $this->app->singleton(\App\Services\Search\SearchResultAggregator::class);
        $this->app->singleton(\App\Services\Search\SearchResultDeduplicator::class);
        $this->app->singleton(\App\Services\Search\SearchOrchestrator::class);

        // Register AI services (WORKER D: OpenAICacheService)
        $this->app->singleton(\App\Services\AI\OpenAICacheService::class);

        // Register Agents services (Question Generator)
        $this->app->bind(
            \App\Contracts\Agents\QuestionGeneratorInterface::class,
            \App\Services\Agents\QuestionGeneratorService::class
        );
        $this->app->singleton(
            \App\Contracts\Research\AnswerEvaluatorInterface::class,
            \App\Services\Research\AnswerEvaluatorService::class
        );
        $this->app->singleton(
            \App\Contracts\Research\QualityAssessorInterface::class,
            \App\Services\Research\QualityAssessorService::class
        );

        // Register Research Module Services (Task 8: ResearchOrchestrator Vizra Migration)
        $this->app->singleton(\App\Services\Research\ResearchPlannerService::class);
        $this->app->singleton(\App\Services\Research\ResearchEvaluatorService::class);
        $this->app->singleton(\App\Services\Research\InsightExtractorService::class);
        $this->app->singleton(\App\Services\Research\ResearchCheckpointService::class);

        // Register ResearchOrchestrator with all 6 dependencies
        $this->app->singleton(
            \App\Services\ResearchOrchestrator::class,
            function ($app) {
                return new \App\Services\ResearchOrchestrator(
                    $app->make(\App\Services\Research\SearchExecutorService::class),
                    $app->make(\App\Services\Agents\IterationControllerService::class),
                    $app->make(\App\Services\Research\ResearchPlannerService::class),
                    $app->make(\App\Services\Research\ResearchEvaluatorService::class),
                    $app->make(\App\Services\Research\InsightExtractorService::class),
                    $app->make(\App\Services\Research\ResearchCheckpointService::class)
                );
            }
        );

        // Register ResearchService - wrapper for ResearchOrchestrator
        // Provides backward compatibility with AgentRun persistence
        $this->app->singleton(
            \App\Services\ResearchService::class,
            function ($app) {
                return new \App\Services\ResearchService(
                    $app->make(\App\Services\ResearchOrchestrator::class)
                );
            }
        );

        // Register Evidence Analysis Module Services
        // Base services (only depend on OpenAIService)
        $this->app->singleton(\App\Modules\Evidence\Services\EvidenceAdmissibilityChecker::class);
        $this->app->singleton(\App\Modules\Evidence\Services\ConstitutionalViolationDetector::class);
        $this->app->singleton(\App\Modules\Evidence\Services\AlternativeInterpretationAnalyzer::class);
        $this->app->singleton(\App\Modules\Evidence\Services\ContextAnalyzer::class);
        $this->app->singleton(\App\Modules\Evidence\Services\RecontextualizationService::class);

        // Suppression motion generator (depends on admissibility checker and constitutional detector)
        $this->app->singleton(\App\Modules\Evidence\Services\SuppressionMotionGenerator::class);

        // Evidence Analysis Module (depends on all Evidence services)
        $this->app->singleton(\App\Modules\Evidence\EvidenceAnalysisModule::class);

        // Register interface bindings for utility and graph services
        $this->app->bind(
            \App\Contracts\CircuitBreakerInterface::class,
            \App\Services\CircuitBreaker::class
        );
        $this->app->bind(
            \App\Contracts\GraphDatabaseServiceInterface::class,
            \App\Services\GraphDatabaseService::class
        );
        $this->app->bind(
            \App\Contracts\GraphRelationshipUpdaterInterface::class,
            \App\Services\GraphRelationshipUpdater::class
        );
        $this->app->bind(
            \App\Contracts\RagOrchestratorInterface::class,
            \App\Services\RagOrchestrator::class
        );
        $this->app->bind(
            \App\Contracts\FactExtractionServiceInterface::class,
            \App\Services\FactExtractionService::class
        );
        $this->app->bind(
            \App\Contracts\DecisionCitationServiceInterface::class,
            \App\Services\DecisionCitationService::class
        );

        // Graph linker and sync service interfaces (already registered as singletons above)
        $this->app->bind(
            \App\Contracts\GraphLinkerInterface::class,
            \App\Services\Graph\GraphKeywordLinker::class
        );
        $this->app->bind(
            \App\Contracts\GraphSyncServiceInterface::class,
            \App\Services\Graph\CaseGraphSyncService::class
        );

        // Register Pipeline & Agent Interface Bindings (Worker C: Day 3)
        $this->app->bind(
            \App\Contracts\Services\AgentToolboxInterface::class,
            \App\Services\AgentToolbox::class
        );
        $this->app->bind(
            \App\Contracts\Services\AgentEvaluationServiceInterface::class,
            \App\Services\AgentEvaluationService::class
        );
        $this->app->bind(
            \App\Contracts\Services\AgentRunDispatcherInterface::class,
            \App\Services\AgentRunDispatcher::class
        );
        $this->app->bind(
            \App\Contracts\Services\AutonomousResearchAgentInterface::class,
            \App\Agents\AutonomousResearchAgent::class
        );
        $this->app->bind(
            \App\Contracts\Services\McpToOpenAIBridgeInterface::class,
            \App\Services\McpToOpenAIBridge::class
        );
        $this->app->bind(
            \App\Contracts\Services\Mcp\InternalMcpClientInterface::class,
            \App\Services\Mcp\InternalMcpClient::class
        );
        $this->app->bind(
            \App\Contracts\Services\Textract\TableExtractorServiceInterface::class,
            \App\Services\Textract\TableExtractorService::class
        );
        $this->app->bind(
            \App\Contracts\Services\Pdf\PdfMergerInterface::class,
            \App\Services\PdfMerger::class
        );
        $this->app->bind(
            \App\Contracts\Services\Pdf\PdfArticleSplitterInterface::class,
            \App\Services\Pdf\PdfArticleSplitter2::class
        );
        $this->app->bind(
            \App\Contracts\Services\UploadServiceInterface::class,
            \App\Services\UploadService::class
        );

        // Register interface bindings for vector stores
        $this->app->bind(
            \App\Contracts\VectorStore\LawVectorStoreInterface::class,
            \App\Services\LawVectorStoreService::class
        );
        $this->app->bind(
            \App\Contracts\VectorStore\CourtDecisionVectorStoreInterface::class,
            \App\Services\CourtDecisionVectorStoreService::class
        );
        $this->app->bind(
            \App\Contracts\VectorStore\CaseVectorStoreInterface::class,
            \App\Services\CaseVectorStoreService::class
        );
        $this->app->bind(
            \App\Contracts\VectorStore\TextractVectorStoreInterface::class,
            \App\Services\TextractVectorStoreService::class
        );

        // Register interface bindings for external services
        $this->app->bind(
            \App\Contracts\External\EkomServiceInterface::class,
            \App\Services\EkomService::class
        );
        $this->app->bind(
            \App\Contracts\External\EoglasnaServiceInterface::class,
            \App\Services\EoglasnaService::class
        );

        // Register interface bindings for ingest services
        $this->app->bind(
            \App\Contracts\Ingest\OdlukeIngestServiceInterface::class,
            \App\Services\Odluke\OdlukeIngestService::class
        );
        $this->app->bind(
            \App\Contracts\Ingest\ZakonHrIngestServiceInterface::class,
            \App\Services\ZakonHrIngestService::class
        );
        $this->app->bind(
            \App\Contracts\Ingest\IngestPipelineServiceInterface::class,
            \App\Services\IngestPipelineService::class
        );
        $this->app->bind(
            \App\Contracts\Ingest\CaseIngestPipelineInterface::class,
            \App\Services\CaseIngestPipeline::class
        );

        // OCR: provide OcrmypdfService to TesseractOcrStep
        $this->app->when(\App\Pipelines\Textract\TesseractOcrStep::class)
            ->needs(\App\Services\Ocr\OcrmypdfService::class)
            ->give(fn () => new \App\Services\Ocr\OcrmypdfService());

        // Register Search Service Interfaces (Worker B: Search Service Interfaces)
        $this->app->bind(
            \App\Contracts\Services\QueryRewriterInterface::class,
            \App\Services\QueryRewriter::class
        );
        $this->app->bind(
            \App\Contracts\Services\TaggingServiceInterface::class,
            \App\Services\TaggingService::class
        );
        $this->app->bind(
            \App\Contracts\Services\KeywordExtractorInterface::class,
            \App\Services\AdvancedKeywordExtractor::class
        );
        $this->app->bind(
            \App\Contracts\Search\UnifiedSearchServiceInterface::class,
            \App\Services\UnifiedSearchService::class
        );
        $this->app->bind(
            \App\Contracts\Search\SearchOrchestratorInterface::class,
            \App\Services\Search\SearchOrchestrator::class
        );
        $this->app->bind(
            \App\Contracts\Search\SearchResultAggregatorInterface::class,
            \App\Services\Search\SearchResultAggregator::class
        );
    }

    public function boot(): void
    {
        // Track application boot time for uptime monitoring (skip in testing)
        if (! app()->environment('testing', 'dusk.local')) {
            \Illuminate\Support\Facades\Cache::rememberForever('app_boot_time', function () {
                return now()->timestamp;
            });
        }

        // Validate critical environment variables
        $this->validateCriticalEnvironmentVariables();

        // Validate Neo4j configuration
        if (config('neo4j.sync.enabled') && ! config('neo4j.password')) {
            throw new \RuntimeException('NEO4J_PASSWORD must be set when Neo4j is enabled');
        }

        // Database query performance logging
        $this->configureDatabaseQueryLogging();

        // Register policies
        Gate::policy(\App\Models\LegalCase::class, \App\Policies\CasePolicy::class);
        Gate::policy(\App\Models\CaseDocument::class, \App\Policies\DocumentPolicy::class);
        Gate::policy(\App\Models\CourtDecision::class, \App\Policies\DecisionPolicy::class);
        Gate::policy(\App\Models\AgentRun::class, \App\Policies\AgentRunPolicy::class);

        // Configure rate limiting
        $this->configureRateLimiting();
        // Register model observers for cache invalidation
        \App\Models\IngestedLaw::observe(\App\Observers\IngestedLawObserver::class);
        \App\Models\CourtDecision::observe(\App\Observers\CourtDecisionObserver::class);
        \App\Models\TextractDocument::observe(\App\Observers\TextractDocumentObserver::class);
        // Workaround for Livewire bug: SupportMultipleRootElementDetection crashes
        // when DOMDocument::loadHTML() fails to produce a <body> element, causing
        // "Attempt to read property 'childNodes' on null". This sets a temporary
        // error handler around mount finishers to suppress the specific null access.
        // See: https://github.com/livewire/livewire/issues/XXXX
        $this->patchLivewireDomDocumentNullBody();

        // Register Livewire components
        Livewire::component('openai-vector-manager', OpenAIVectorManager::class);
        Livewire::component('openai-log-viewer', OpenAILogViewer::class);
        Livewire::component('openai-responses-viewer', OpenAIResponsesViewer::class);
        Livewire::component('gup-timeline', GupTimeline::class);
        Livewire::component('ingested-laws-manager', IngestedLawsManager::class);
        Livewire::component('transcript-previewer', TranscriptPreviewer::class);
        Livewire::component('textract-manager', TextractManager::class);
        Livewire::component('epredmet-widget', EpredmetWidget::class);
        Livewire::component('eoglasna-monitoring', EoglasnaMonitoring::class);
        Livewire::component('unified-search', UnifiedSearch::class);
        Livewire::component('graph-viewer', GraphViewer::class);
        Livewire::component('legal-playground', LegalPlayground::class);

        Livewire::component('case-overview', CaseOverview::class);
        Livewire::component('case-analysis-dashboard', CaseAnalysisDashboard::class);
        Livewire::component('case-file-completeness', CaseFileCompleteness::class);
        Livewire::component('agent-performance-dashboard', AgentPerformanceDashboard::class);
        Livewire::component('graph.force-graph-controller', ForceGraphController::class);
        Livewire::component('graph.alert-panel-controller', AlertPanelController::class);
        Livewire::component('legal-artillery.dashboard', Dashboard::class);
        Livewire::component('legal-artillery.generation-monitor', GenerationMonitor::class);
        Livewire::component('legal-artillery.new-generation', NewGeneration::class);
        Livewire::component('legal-artillery.run-details', RunDetails::class);

        // Register event listeners for Neo4j graph auto-sync
        Event::listen(LawIngested::class, SyncLawToGraph::class);
        Event::listen(CaseUploaded::class, SyncCaseToGraph::class);
        Event::listen(CourtDecisionIngested::class, SyncDecisionToGraph::class);

        // Note: Authorization policies are registered in AuthServiceProvider
    }

    /**
     * Patch Livewire's SupportMultipleRootElementDetection null body crash.
     *
     * Livewire's debug-only root element check uses DOMDocument::loadHTML() to
     * parse rendered component HTML, then accesses $body->childNodes without
     * null-checking $body. When DOMDocument fails to produce a <body> element
     * (e.g. due to complex HTML with large wire:snapshot attributes), this
     * crashes with "Attempt to read property 'childNodes' on null".
     *
     * Fix: temporarily set an error handler around mount finishers to suppress
     * this specific null property access from the vendor code.
     */
    protected function patchLivewireDomDocumentNullBody(): void
    {
        if (! config('app.debug')) {
            return;
        }

        \Livewire\before('mount', function () {
            return function ($html) {
                set_error_handler(function (int $severity, string $message, string $file) {
                    if (
                        str_contains($message, 'childNodes')
                        && str_contains($file, 'SupportMultipleRootElementDetection')
                    ) {
                        return true;
                    }

                    return false;
                });
            };
        });

        \Livewire\after('mount', function () {
            return function ($html) {
                restore_error_handler();
            };
        });
    }

    /**
     * Configure rate limiting for the application.
     */
    protected function configureRateLimiting(): void
    {
        // OpenAI Proxy - Most restrictive (expensive)
        RateLimiter::for('openai', function (Request $request) {
            return Limit::perMinute(30)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'error' => 'Too many OpenAI requests. Please try again later.',
                        'retry_after' => 60,
                    ], 429);
                });
        });

        // Agent execution - Moderate
        RateLimiter::for('agents', function (Request $request) {
            return Limit::perMinute(10)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'error' => 'Too many agent requests. Please try again later.',
                        'retry_after' => 60,
                    ], 429);
                });
        });

        // Search - Liberal
        RateLimiter::for('search', function (Request $request) {
            return Limit::perMinute(60)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'error' => 'Too many search requests. Please try again later.',
                        'retry_after' => 60,
                    ], 429);
                });
        });

        // API general
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'error' => 'Too many API requests. Please try again later.',
                        'retry_after' => 60,
                    ], 429);
                });
        });

        // Document generation API - dedicated per-user throttle
        RateLimiter::for('documents', function (Request $request) {
            return Limit::perMinute(20)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'error' => 'Too many document generation requests. Please slow down and try again.',
                        'retry_after' => 60,
                    ], 429);
                });
        });

        // Token budget limiter (per user per day)
        RateLimiter::for('openai-tokens', function (Request $request) {
            $user = $request->user();
            $tokenBudget = $user?->token_budget_daily ?? 50000;

            return Limit::perDay($tokenBudget)
                ->by('tokens:'.($user?->id ?? $request->ip()))
                ->response(function () use ($tokenBudget) {
                    return response()->json([
                        'error' => 'Daily token budget exceeded',
                        'budget' => $tokenBudget,
                        'retry_after' => now()->endOfDay()->diffInSeconds(),
                    ], 429);
                });
        });
    }

    /**
     * Configure database query performance logging.
     */
    protected function configureDatabaseQueryLogging(): void
    {
        if (! app()->environment('production', 'staging')) {
            return;
        }

        $slowQueryThreshold = (int) env('DB_SLOW_QUERY_THRESHOLD_MS', 1000);
        $logAllQueries = (bool) env('DB_LOG_ALL_QUERIES', false);

        \Illuminate\Support\Facades\DB::listen(function ($query) use ($slowQueryThreshold, $logAllQueries) {
            $sql = $query->sql;
            $bindings = $query->bindings;
            $time = $query->time;

            // Log slow queries
            if ($time >= $slowQueryThreshold) {
                \Log::warning('Slow database query detected', [
                    'sql' => $sql,
                    'bindings' => $bindings,
                    'time_ms' => $time,
                    'connection' => $query->connectionName,
                    'threshold_ms' => $slowQueryThreshold,
                ]);

                // Track metric for monitoring
                if (class_exists('\Prometheus\Counter')) {
                    app('prometheus')->getOrRegisterCounter(
                        'database',
                        'slow_queries_total',
                        'Total number of slow database queries',
                        ['connection']
                    )->inc([$query->connectionName]);
                }
            }

            // Optionally log all queries for debugging
            if ($logAllQueries) {
                \Log::debug('Database query executed', [
                    'sql' => $sql,
                    'bindings' => $bindings,
                    'time_ms' => $time,
                    'connection' => $query->connectionName,
                ]);
            }

            // Track query performance percentiles
            if ($time > 0) {
                if (class_exists('\Prometheus\Histogram')) {
                    app('prometheus')->getOrRegisterHistogram(
                        'database',
                        'query_duration_milliseconds',
                        'Database query duration in milliseconds',
                        ['connection'],
                        [10, 50, 100, 250, 500, 1000, 2500, 5000, 10000]
                    )->observe($time, [$query->connectionName]);
                }
            }
        });
    }

    /**
     * Validate critical environment variables on bootstrap.
     *
     * @throws \RuntimeException
     */
    protected function validateCriticalEnvironmentVariables(): void
    {
        // DISABLED: This validation causes false positives when config is cached.
        // When config:cache runs, env() values are baked into the cache.
        // If the cache was created before .env had values, env() returns empty
        // even though .env now has the correct values.
        //
        // The app will fail with clear error messages at the point of use
        // (e.g., when calling OpenAI API) which is more reliable.
        return;
    }
}
