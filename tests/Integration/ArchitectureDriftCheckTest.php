<?php

namespace Tests\Integration;

use Tests\TestCase;

/**
 * Architecture drift checks (SOT-017).
 *
 * Static checks ensuring docs/diagrams stay aligned with route
 * and job topology. These tests fail when code changes break
 * documented architectural invariants.
 *
 * Run in CI to catch drift early.
 */
class ArchitectureDriftCheckTest extends TestCase
{
    // ─── Route Topology ──────────────────────────────────────────

    /**
     * All documented upload routes exist.
     */
    public function test_upload_routes_exist(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes());

        $expected = [
            ['POST', 'uploads'],
            ['POST', 'uploads/start'],
            ['POST', 'uploads/{uploadId}/chunk/{index}'],
            ['POST', 'uploads/{uploadId}/complete'],
            ['DELETE', 'uploads/{uploadId}'],
        ];

        foreach ($expected as [$method, $uri]) {
            $match = $routes->first(function ($route) use ($method, $uri) {
                return in_array($method, $route->methods()) && $route->uri() === $uri;
            });

            $this->assertNotNull($match, "Expected route {$method} {$uri} not found");
        }
    }

    /**
     * Upload routes point to UploadController.
     */
    public function test_upload_routes_use_correct_controller(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes());

        $uploadRoutes = $routes->filter(function ($route) {
            return str_starts_with($route->uri(), 'uploads');
        });

        foreach ($uploadRoutes as $route) {
            $controller = $route->getActionName();
            $this->assertStringContainsString(
                'UploadController',
                $controller,
                "Upload route {$route->uri()} should use UploadController"
            );
        }
    }

    /**
     * /uploader view route exists.
     */
    public function test_uploader_view_route_exists(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes());

        $uploaderRoute = $routes->first(function ($route) {
            return $route->uri() === 'uploader';
        });

        $this->assertNotNull($uploaderRoute, '/uploader view route should exist');
    }

    /**
     * Health check route exists.
     */
    public function test_health_route_exists(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes());

        $healthRoute = $routes->first(function ($route) {
            return $route->uri() === 'api/health';
        });

        $this->assertNotNull($healthRoute, 'API health route should exist');
    }

    // ─── Job Topology ────────────────────────────────────────────

    /**
     * Critical ingest jobs exist.
     */
    public function test_critical_ingest_jobs_exist(): void
    {
        $requiredJobs = [
            \App\Jobs\Ingest\ProcessIngestRunJob::class,
            \App\Jobs\ProcessTextractJob::class,
            \App\Jobs\Analysis\RunCaseLevelAnalysisJob::class,
        ];

        foreach ($requiredJobs as $jobClass) {
            $this->assertTrue(
                class_exists($jobClass),
                "Required job class {$jobClass} must exist"
            );
        }
    }

    /**
     * ProcessIngestRunJob dispatches to the 'ingest' queue.
     */
    public function test_ingest_job_uses_correct_queue(): void
    {
        $job = new \App\Jobs\Ingest\ProcessIngestRunJob('test-id');
        $this->assertEquals('ingest', $job->queue);
    }

    /**
     * External source ingest jobs exist.
     */
    public function test_external_source_ingest_jobs_exist(): void
    {
        $externalJobs = [
            \App\Jobs\IngestOdlukeDecision::class,
            \App\Jobs\IngestUsudDecision::class,
            \App\Jobs\IngestEsljpDecision::class,
        ];

        foreach ($externalJobs as $jobClass) {
            $this->assertTrue(
                class_exists($jobClass),
                "External ingest job {$jobClass} must exist"
            );
        }
    }

    // ─── Event / Listener Wiring ─────────────────────────────────

    /**
     * Critical events exist.
     */
    public function test_critical_events_exist(): void
    {
        $requiredEvents = [
            \App\Events\CaseDocumentIngested::class,
            \App\Events\DocumentAnalysisCompleted::class,
        ];

        foreach ($requiredEvents as $eventClass) {
            $this->assertTrue(
                class_exists($eventClass),
                "Required event class {$eventClass} must exist"
            );
        }
    }

    /**
     * Critical listeners exist.
     */
    public function test_critical_listeners_exist(): void
    {
        $requiredListeners = [
            \App\Listeners\TriggerCaseLevelAnalysis::class,
        ];

        foreach ($requiredListeners as $listenerClass) {
            $this->assertTrue(
                class_exists($listenerClass),
                "Required listener class {$listenerClass} must exist"
            );
        }
    }

    // ─── Service Topology ────────────────────────────────────────

    /**
     * Core services are resolvable from the container.
     */
    public function test_core_services_are_resolvable(): void
    {
        $services = [
            \App\Services\Ingest\IngestOrchestrator::class,
            \App\Services\Analysis\DocumentAnalysisPipeline::class,
            \App\Services\Analysis\CaseLevel\DocumentIdentityBuilder::class,
            \App\Services\UploadService::class,
        ];

        foreach ($services as $serviceClass) {
            $instance = app($serviceClass);
            $this->assertNotNull($instance, "Service {$serviceClass} should be resolvable from container");
        }
    }

    /**
     * Escalation suggester interface is bound.
     */
    public function test_escalation_suggester_interface_bound(): void
    {
        $interface = \App\Contracts\LegalArtillery\EscalationSuggesterInterface::class;
        $this->assertTrue(
            app()->bound($interface),
            'EscalationSuggesterInterface should be bound in container'
        );

        $instance = app($interface);
        $this->assertInstanceOf(
            \App\Services\LegalArtillery\EscalationLadderSuggester::class,
            $instance
        );
    }

    /**
     * IngestOrchestrator is injected into UploadService.
     */
    public function test_upload_service_has_ingest_orchestrator(): void
    {
        $uploadService = app(\App\Services\UploadService::class);

        $reflection = new \ReflectionClass($uploadService);
        $prop = $reflection->getProperty('ingestOrchestrator');
        $prop->setAccessible(true);

        $this->assertInstanceOf(
            \App\Services\Ingest\IngestOrchestrator::class,
            $prop->getValue($uploadService)
        );
    }

    // ─── Config Topology ─────────────────────────────────────────

    /**
     * OCR config namespace has required keys.
     */
    public function test_ocr_config_has_required_keys(): void
    {
        $this->assertNotNull(config('ocr.quality'), 'ocr.quality config must exist');
        $this->assertNotNull(config('ocr.quality.min_confidence'), 'ocr.quality.min_confidence must exist');
    }

    /**
     * Informator feature flag config exists.
     */
    public function test_informator_feature_flag_config_exists(): void
    {
        // Config key should exist (even if defaulting to false)
        $this->assertNotNull(
            config('services.informator'),
            'services.informator config namespace must exist'
        );
        $this->assertArrayHasKey(
            'enrichment_enabled',
            config('services.informator'),
            'services.informator.enrichment_enabled must be configured'
        );
    }

    // ─── Model Topology ──────────────────────────────────────────

    /**
     * IngestRun model has required relationships.
     */
    public function test_ingest_run_model_has_required_relationships(): void
    {
        $model = new \App\Models\IngestRun();

        $this->assertTrue(
            method_exists($model, 'user'),
            'IngestRun must have user() relationship'
        );
        $this->assertTrue(
            method_exists($model, 'stepLogs'),
            'IngestRun must have stepLogs() relationship'
        );
    }

    /**
     * IngestRun has observability methods.
     */
    public function test_ingest_run_has_observability_methods(): void
    {
        $model = new \App\Models\IngestRun();

        $methods = ['totalDurationMs', 'failedSteps', 'isStale', 'pipelineProgress'];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists($model, $method),
                "IngestRun must have {$method}() observability method"
            );
        }
    }

    /**
     * IngestStepLog model exists with required fields.
     */
    public function test_ingest_step_log_model_exists(): void
    {
        $this->assertTrue(
            class_exists(\App\Models\IngestStepLog::class),
            'IngestStepLog model must exist'
        );

        $model = new \App\Models\IngestStepLog();
        $this->assertTrue(
            method_exists($model, 'ingestRun'),
            'IngestStepLog must have ingestRun() relationship'
        );
    }

    // ─── No Legacy References ────────────────────────────────────

    /**
     * Deprecated legacy services are removed.
     */
    public function test_legacy_services_removed(): void
    {
        $deprecated = [
            'App\Services\LegalArtillery\RecursiveDocumentWriter',
            'App\Services\LegalArtillery\IterativeRefiner',
            'App\Services\LegalArtillery\LegacyDevastatingArgumentBuilder',
            'App\Services\EscalationLadderSuggester',
            'App\Services\EscalationHierarchySuggester',
        ];

        foreach ($deprecated as $className) {
            $this->assertFalse(
                class_exists($className),
                "Deprecated class {$className} should be removed"
            );
        }
    }

    /**
     * No duplicate DI bindings for Legal Artillery services in AppServiceProvider.
     */
    public function test_no_duplicate_di_bindings(): void
    {
        $appProvider = file_get_contents(app_path('Providers/AppServiceProvider.php'));

        // Check that LegalArtilleryOrchestrator is NOT bound in AppServiceProvider
        // (it should only be in LegalArtilleryServiceProvider)
        $this->assertStringNotContainsString(
            'LegalArtilleryOrchestrator::class',
            $appProvider,
            'LegalArtilleryOrchestrator should not be bound in AppServiceProvider'
        );
    }
}
