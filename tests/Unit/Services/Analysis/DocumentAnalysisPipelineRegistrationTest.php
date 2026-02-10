<?php

namespace Tests\Unit\Services\Analysis;

use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Services\Analysis\Analyzers\CaseReferenceExtractor;
use App\Services\Analysis\Analyzers\DateContextExtractor;
use App\Services\Analysis\Analyzers\DateExtractor;
use App\Services\Analysis\Analyzers\KeywordAnalyzer;
use App\Services\Analysis\Contracts\DocumentAnalyzerInterface;
use App\Services\Analysis\DocumentAnalysisPipeline;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Tests that DateContextExtractor and CaseReferenceExtractor are properly
 * registered in DocumentAnalysisPipeline::getExtractionLayerAnalyzers().
 *
 * SOT-002: Add DateContextExtractor + CaseReferenceExtractor to analysis pipeline.
 */
class DocumentAnalysisPipelineRegistrationTest extends TestCase
{
    use DatabaseTransactions;

    private DocumentAnalysisPipeline $pipeline;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pipeline = new DocumentAnalysisPipeline();
    }

    /**
     * Helper: invoke the private getExtractionLayerAnalyzers() via reflection.
     *
     * @return DocumentAnalyzerInterface[]
     */
    private function getExtractionAnalyzers(): array
    {
        $method = new ReflectionMethod(DocumentAnalysisPipeline::class, 'getExtractionLayerAnalyzers');
        $method->setAccessible(true);

        return $method->invoke($this->pipeline);
    }

    /**
     * Helper: get the class names of all registered extraction analyzers.
     *
     * @return string[]
     */
    private function getExtractionAnalyzerClasses(): array
    {
        return array_map(
            fn(DocumentAnalyzerInterface $a) => get_class($a),
            $this->getExtractionAnalyzers()
        );
    }

    // ---------------------------------------------------------------
    // Registration Tests
    // ---------------------------------------------------------------

    /** @test */
    public function extraction_layer_includes_date_context_extractor(): void
    {
        $classes = $this->getExtractionAnalyzerClasses();

        $this->assertContains(
            DateContextExtractor::class,
            $classes,
            'DateContextExtractor must be registered in the extraction layer'
        );
    }

    /** @test */
    public function extraction_layer_includes_case_reference_extractor(): void
    {
        $classes = $this->getExtractionAnalyzerClasses();

        $this->assertContains(
            CaseReferenceExtractor::class,
            $classes,
            'CaseReferenceExtractor must be registered in the extraction layer'
        );
    }

    /** @test */
    public function existing_analyzers_still_present_after_registration(): void
    {
        $classes = $this->getExtractionAnalyzerClasses();

        // KeywordAnalyzer is always registered (no class_exists guard)
        $this->assertContains(
            KeywordAnalyzer::class,
            $classes,
            'KeywordAnalyzer must still be registered (no regression)'
        );
    }

    /** @test */
    public function date_context_extractor_ordered_after_date_extractor(): void
    {
        $classes = $this->getExtractionAnalyzerClasses();

        // DateExtractor extracts simple dates; DateContextExtractor adds rich context.
        // DateContextExtractor should come AFTER DateExtractor in the array.
        if (in_array(DateExtractor::class, $classes, true)) {
            $dateExtractorIdx = array_search(DateExtractor::class, $classes, true);
            $dateContextIdx = array_search(DateContextExtractor::class, $classes, true);

            $this->assertGreaterThan(
                $dateExtractorIdx,
                $dateContextIdx,
                'DateContextExtractor must be registered after DateExtractor'
            );
        } else {
            // DateExtractor may not exist yet; just verify DateContextExtractor is present
            $this->assertContains(DateContextExtractor::class, $classes);
        }
    }

    // ---------------------------------------------------------------
    // Integration Tests: verify pipeline produces expected output types
    // ---------------------------------------------------------------

    /** @test */
    public function running_extraction_on_croatian_text_produces_dates_with_context(): void
    {
        $croatianText = <<<'TEXT'
        REPUBLIKA HRVATSKA
        ŽUPANIJSKI SUD U ZAGREBU

        Dana 15. siječnja 2024. godine provedena je pretraga stana osumnjičenika
        na adresi Ilica 42, Zagreb. Pretraga je započela u 14:30 sati temeljem
        naredbe Županijskog suda od 12.01.2024. godine.

        KLASA: UP/I-034-02/24-01/123
        URBROJ: 511-01-02-03-24-1
        TEXT;

        $document = CaseDocument::factory()->create([
            'content' => $croatianText,
        ]);

        $results = $this->pipeline->runLayer($document, DocumentAnalysis::LAYER_EXTRACTION);

        // Find the dates_with_context analysis in results
        $datesWithContext = collect($results)->first(function (DocumentAnalysis $analysis) {
            return $analysis->analysis_type === 'dates_with_context';
        });

        $this->assertNotNull(
            $datesWithContext,
            'Pipeline must produce a dates_with_context analysis for Croatian text with dates'
        );
        $this->assertEquals(
            DocumentAnalysis::STATUS_COMPLETED,
            $datesWithContext->status
        );
        $this->assertNotEmpty($datesWithContext->results);
        $this->assertArrayHasKey('dates', $datesWithContext->results);
        $this->assertGreaterThan(0, $datesWithContext->results['date_count']);
    }

    /** @test */
    public function running_extraction_on_croatian_text_produces_case_references(): void
    {
        $croatianText = <<<'TEXT'
        REPUBLIKA HRVATSKA
        ŽUPANIJSKI SUD U ZAGREBU

        Predmet: K-123/2024-2

        KLASA: UP/I-034-02/24-01/123
        URBROJ: 511-01-02-03-24-1

        Broj: 511-07-11-K-51/2025

        Na temelju članka 239. stavka 1. Zakona o kaznenom postupku
        dana 15. siječnja 2024. donesena je presuda u predmetu K-123/2024.
        TEXT;

        $document = CaseDocument::factory()->create([
            'content' => $croatianText,
        ]);

        $results = $this->pipeline->runLayer($document, DocumentAnalysis::LAYER_EXTRACTION);

        // Find the case_references analysis in results
        $caseReferences = collect($results)->first(function (DocumentAnalysis $analysis) {
            return $analysis->analysis_type === 'case_references';
        });

        $this->assertNotNull(
            $caseReferences,
            'Pipeline must produce a case_references analysis for Croatian legal text with references'
        );
        $this->assertEquals(
            DocumentAnalysis::STATUS_COMPLETED,
            $caseReferences->status
        );
        $this->assertNotEmpty($caseReferences->results);
        // Should have at least klasa or case_numbers
        $hasReferences = !empty($caseReferences->results['klasa'])
            || !empty($caseReferences->results['case_numbers'])
            || !empty($caseReferences->results['urbroj'])
            || !empty($caseReferences->results['broj']);
        $this->assertTrue($hasReferences, 'case_references results must contain at least one reference type');
    }
}
