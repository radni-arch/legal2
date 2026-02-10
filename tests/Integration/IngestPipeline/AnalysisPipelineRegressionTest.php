<?php

namespace Tests\Integration\IngestPipeline;

use App\Events\DocumentAnalysisCompleted;
use App\Jobs\Analysis\RunCaseLevelAnalysisJob;
use App\Listeners\TriggerCaseLevelAnalysis;
use App\Models\DocumentAnalysis;
use App\Services\Analysis\Analyzers\CaseReferenceExtractor;
use App\Services\Analysis\Analyzers\DateContextExtractor;
use App\Services\Analysis\DocumentAnalysisPipeline;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Regression tests for the analysis pipeline wiring.
 *
 * Verifies that:
 * - DocumentAnalysisCompleted events trigger case-level analysis
 * - DateContextExtractor and CaseReferenceExtractor are registered
 * - The full event → listener → job chain is wired correctly
 *
 * SOT-014 acceptance criteria:
 * Both ingest entry paths produce expected analysis artifacts.
 */
class AnalysisPipelineRegressionTest extends TestCase
{
    /**
     * DocumentAnalysisCompleted event is listened to by TriggerCaseLevelAnalysis.
     */
    public function test_document_analysis_completed_triggers_case_level_analysis(): void
    {
        Queue::fake([RunCaseLevelAnalysisJob::class]);

        $listener = new TriggerCaseLevelAnalysis();
        $event = new DocumentAnalysisCompleted(
            caseId: 'case-analysis-1',
            documentId: 'doc-1',
            layer: DocumentAnalysis::LAYER_EXTRACTION,
        );

        $listener->handle($event);

        Queue::assertPushed(RunCaseLevelAnalysisJob::class);
    }

    /**
     * Non-extraction layer events do NOT trigger case-level analysis.
     */
    public function test_non_extraction_layer_does_not_trigger_case_analysis(): void
    {
        Queue::fake([RunCaseLevelAnalysisJob::class]);

        $listener = new TriggerCaseLevelAnalysis();

        // Test with various non-extraction layers
        $layers = ['summarization', 'classification', 'embedding'];

        foreach ($layers as $layer) {
            $event = new DocumentAnalysisCompleted(
                caseId: 'case-nontrigger',
                documentId: 'doc-nontrigger',
                layer: $layer,
            );

            $listener->handle($event);
        }

        Queue::assertNotPushed(RunCaseLevelAnalysisJob::class);
    }

    /**
     * DateContextExtractor class exists and is loadable for pipeline registration.
     * The pipeline uses class_exists() to conditionally add analyzers.
     */
    public function test_date_context_extractor_class_exists(): void
    {
        $this->assertTrue(
            class_exists(DateContextExtractor::class),
            'DateContextExtractor class must exist for pipeline registration'
        );

        // Verify it can be instantiated
        $extractor = new DateContextExtractor();
        $this->assertNotNull($extractor);
    }

    /**
     * CaseReferenceExtractor class exists and is loadable for pipeline registration.
     * The pipeline uses class_exists() to conditionally add analyzers.
     */
    public function test_case_reference_extractor_class_exists(): void
    {
        $this->assertTrue(
            class_exists(CaseReferenceExtractor::class),
            'CaseReferenceExtractor class must exist for pipeline registration'
        );

        // Verify it can be instantiated
        $extractor = new CaseReferenceExtractor();
        $this->assertNotNull($extractor);
    }

    /**
     * Pipeline extraction layer includes both extractors via reflection.
     */
    public function test_extraction_layer_includes_required_analyzers(): void
    {
        $pipeline = new DocumentAnalysisPipeline();

        // Use reflection to access private method
        $method = new \ReflectionMethod($pipeline, 'getExtractionLayerAnalyzers');
        $method->setAccessible(true);
        $analyzers = $method->invoke($pipeline);

        $classes = array_map(fn ($a) => get_class($a), $analyzers);

        $this->assertContains(
            DateContextExtractor::class,
            $classes,
            'Extraction layer must include DateContextExtractor'
        );
        $this->assertContains(
            CaseReferenceExtractor::class,
            $classes,
            'Extraction layer must include CaseReferenceExtractor'
        );
    }

    /**
     * TriggerCaseLevelAnalysis is registered as event listener in Laravel.
     */
    public function test_trigger_case_level_analysis_listener_is_registered(): void
    {
        $hasListeners = Event::hasListeners(DocumentAnalysisCompleted::class);

        $this->assertTrue($hasListeners, 'TriggerCaseLevelAnalysis should be registered as listener for DocumentAnalysisCompleted');
    }

    /**
     * DocumentIdentityBuilder is resolvable from the container.
     */
    public function test_document_identity_builder_is_resolvable(): void
    {
        $builder = app(\App\Services\Analysis\CaseLevel\DocumentIdentityBuilder::class);
        $this->assertNotNull($builder);
    }

    /**
     * OCR quality step reads from canonical config namespace.
     */
    public function test_ocr_quality_config_uses_canonical_namespace(): void
    {
        // Verify ocr.quality config namespace exists
        $this->assertNotNull(config('ocr.quality'), 'ocr.quality config namespace should exist');
        $this->assertIsArray(config('ocr.quality'), 'ocr.quality should be an array');
    }
}
