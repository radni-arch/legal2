<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Graph\DocumentTypeDetector;
use Tests\TestCase;

class DocumentTypeDetectorTest extends TestCase
{
    private DocumentTypeDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new DocumentTypeDetector();
    }

    /** @test */
    public function it_detects_court_decision_document_type(): void
    {
        $content = 'REPUBLIKA HRVATSKA U IME REPUBLIKE HRVATSKE PRESUDA Općinski sud u Zagrebu';

        $result = $this->detector->detect($content);

        $this->assertEquals('court_decision', $result);
    }

    /** @test */
    public function it_detects_law_document_type(): void
    {
        $content = 'ZAKON O OBVEZNIM ODNOSIMA Članak 1. Ovim se Zakonom uređuju obvezni odnosi';

        $result = $this->detector->detect($content);

        $this->assertEquals('law', $result);
    }

    /** @test */
    public function it_returns_generic_for_unknown_document_type(): void
    {
        $content = 'This is some generic text without legal markers.';

        $result = $this->detector->detect($content);

        $this->assertEquals('generic', $result);
    }

    /** @test */
    public function it_returns_extractors_for_court_decision(): void
    {
        $extractors = $this->detector->getExtractorsForType('court_decision');

        $this->assertContains('LegalTopicExtractor', $extractors);
        $this->assertContains('LawyerExtractor', $extractors);
        $this->assertContains('VerdictExtractor', $extractors);
        $this->assertContains('EvidenceExtractor', $extractors);
        $this->assertCount(9, $extractors); // All 9 extractors
    }

    /** @test */
    public function it_returns_limited_extractors_for_law_document(): void
    {
        $extractors = $this->detector->getExtractorsForType('law');

        $this->assertContains('ArticleExtractor', $extractors);
        $this->assertContains('LegalDefinitionExtractor', $extractors);
        $this->assertContains('LegalConceptExtractor', $extractors);
        $this->assertContains('LegalTopicExtractor', $extractors);
        $this->assertNotContains('LawyerExtractor', $extractors);
        $this->assertNotContains('VerdictExtractor', $extractors);
    }

    /** @test */
    public function it_returns_minimal_extractors_for_generic(): void
    {
        $extractors = $this->detector->getExtractorsForType('generic');

        $this->assertContains('DateEventExtractor', $extractors);
        $this->assertContains('LegalConceptExtractor', $extractors);
        $this->assertContains('LegalTopicExtractor', $extractors);
        $this->assertCount(3, $extractors);
    }
}
