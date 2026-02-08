<?php

namespace Tests\Unit;

use App\Services\Ocr\OcrQualityAnalyzer;
use Tests\TestCase;

/**
 * Unit tests for OCR quality analysis.
 */
class OcrConfidenceTest extends TestCase
{
    protected OcrQualityAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new OcrQualityAnalyzer;
    }

    /** @test */
    public function it_calculates_confidence_from_textract_blocks()
    {
        // Arrange: Create blocks with known confidence values
        $blocks = [
            ['BlockType' => 'WORD', 'Text' => 'Hello', 'Confidence' => 95.0, 'Page' => 1],
            ['BlockType' => 'WORD', 'Text' => 'World', 'Confidence' => 92.0, 'Page' => 1],
            ['BlockType' => 'WORD', 'Text' => 'Test', 'Confidence' => 88.0, 'Page' => 1],
            ['BlockType' => 'LINE', 'Text' => 'Ignored', 'Page' => 1], // Should be ignored
        ];

        // Act
        $result = $this->analyzer->analyzeFromBlocks($blocks);

        // Assert
        $this->assertArrayHasKey('confidence', $result);
        $this->assertArrayHasKey('coverage', $result);
        $this->assertArrayHasKey('total_words', $result);

        // Confidence = (95 + 92 + 88) / 3 / 100 = 0.9167
        $expectedConfidence = (95.0 + 92.0 + 88.0) / 3 / 100;
        $this->assertEquals(round($expectedConfidence, 4), $result['confidence']);

        // Coverage = 3/3 = 1.0 (all words have confidence)
        $this->assertEquals(1.0, $result['coverage']);

        // Total words
        $this->assertEquals(3, $result['total_words']);
    }

    /** @test */
    public function it_identifies_low_confidence_pages()
    {
        // Arrange: Page 1 has high confidence, Page 2 has low confidence
        $blocks = [
            // Page 1: High confidence (avg > 0.80)
            ['BlockType' => 'WORD', 'Text' => 'Good', 'Confidence' => 95.0, 'Page' => 1],
            ['BlockType' => 'WORD', 'Text' => 'Quality', 'Confidence' => 93.0, 'Page' => 1],

            // Page 2: Low confidence (avg < 0.80)
            ['BlockType' => 'WORD', 'Text' => 'Bad', 'Confidence' => 65.0, 'Page' => 2],
            ['BlockType' => 'WORD', 'Text' => 'Quality', 'Confidence' => 70.0, 'Page' => 2],
        ];

        // Act
        $result = $this->analyzer->analyzeFromBlocks($blocks);

        // Assert
        $this->assertEquals(2, $result['total_pages']);
        $this->assertEquals(1, $result['low_confidence_pages']);
        $this->assertContains(2, $result['low_confidence_page_numbers']);
        $this->assertNotContains(1, $result['low_confidence_page_numbers']);
    }

    /** @test */
    public function it_handles_blocks_without_confidence()
    {
        // Arrange: Some blocks missing confidence
        $blocks = [
            ['BlockType' => 'WORD', 'Text' => 'Word1', 'Confidence' => 95.0, 'Page' => 1],
            ['BlockType' => 'WORD', 'Text' => 'Word2', 'Page' => 1], // Missing confidence
            ['BlockType' => 'WORD', 'Text' => 'Word3', 'Confidence' => 90.0, 'Page' => 1],
        ];

        // Act
        $result = $this->analyzer->analyzeFromBlocks($blocks);

        // Assert
        $this->assertEquals(3, $result['total_words']);
        $this->assertEquals(2, $result['words_with_confidence']);

        // Coverage = 2/3 = 0.6667
        $this->assertEquals(0.6667, $result['coverage']);

        // Confidence = (95 + 90) / 2 / 100 = 0.925
        $this->assertEquals(0.925, $result['confidence']);
    }

    /** @test */
    public function it_estimates_quality_from_raw_text()
    {
        // Arrange: Good quality text
        $goodText = <<<'TEXT'
Članak 1. Opće odredbe

Ovim Zakonom uređuju se temeljna prava i slobode čovjeka i građanina.
Jamči se zaštita ljudskih prava i temeljnih sloboda sukladno Ustavu Republike Hrvatske.

Članak 2. Primjena Zakona

Odredbe ovog Zakona primjenjuju se na sve građane Republike Hrvatske bez
obzira na njihovu nacionalnu, vjersku ili političku pripadnost.
TEXT;

        // Act
        $result = $this->analyzer->estimateFromText($goodText);

        // Assert
        $this->assertArrayHasKey('confidence', $result);
        $this->assertArrayHasKey('coverage', $result);
        $this->assertTrue($result['estimated']);
        $this->assertEquals('text_heuristics', $result['method']);

        // Good text should have reasonable confidence
        $this->assertGreaterThan(0.6, $result['confidence']);
    }

    /** @test */
    public function it_detects_poor_quality_text()
    {
        // Arrange: Poor quality text (garbled, fragmented)
        $poorText = "a b c d\ne\nf\ng h\ni j\nk\nl m n o\np\nq r\ns";

        // Act
        $result = $this->analyzer->estimateFromText($poorText);

        // Assert: Poor quality text should have lower confidence
        $this->assertLessThan(0.7, $result['confidence']);
    }

    /** @test */
    public function it_returns_zero_metrics_for_empty_text()
    {
        // Act
        $result = $this->analyzer->estimateFromText('');

        // Assert
        $this->assertEquals(0.0, $result['confidence']);
        $this->assertEquals(0.0, $result['coverage']);
        $this->assertEquals(0, $result['total_words']);
    }

    /** @test */
    public function it_provides_page_statistics()
    {
        // Arrange: Multi-page document
        $blocks = [
            ['BlockType' => 'WORD', 'Text' => 'Page1Word1', 'Confidence' => 95.0, 'Page' => 1],
            ['BlockType' => 'WORD', 'Text' => 'Page1Word2', 'Confidence' => 93.0, 'Page' => 1],
            ['BlockType' => 'WORD', 'Text' => 'Page2Word1', 'Confidence' => 88.0, 'Page' => 2],
            ['BlockType' => 'WORD', 'Text' => 'Page2Word2', 'Confidence' => 85.0, 'Page' => 2],
            ['BlockType' => 'WORD', 'Text' => 'Page2Word3', 'Confidence' => 82.0, 'Page' => 2],
        ];

        // Act
        $result = $this->analyzer->analyzeFromBlocks($blocks);

        // Assert
        $this->assertArrayHasKey('page_stats', $result);

        $pageStats = $result['page_stats'];
        $this->assertArrayHasKey(1, $pageStats);
        $this->assertArrayHasKey(2, $pageStats);

        // Page 1: 2 words
        $this->assertEquals(2, $pageStats[1]['words']);
        $this->assertEquals(2, $pageStats[1]['words_with_confidence']);

        // Page 2: 3 words
        $this->assertEquals(3, $pageStats[2]['words']);
        $this->assertEquals(3, $pageStats[2]['words_with_confidence']);

        // Page 1 avg confidence: (95 + 93) / 2 / 100 = 0.94
        $this->assertEquals(0.94, $pageStats[1]['avg_confidence']);

        // Page 2 avg confidence: (88 + 85 + 82) / 3 / 100 = 0.85
        $this->assertEquals(0.85, $pageStats[2]['avg_confidence']);
    }

    /** @test */
    public function it_calculates_coverage_correctly()
    {
        // Arrange: 60% of words have confidence data
        $blocks = [
            ['BlockType' => 'WORD', 'Text' => 'Word1', 'Confidence' => 95.0, 'Page' => 1],
            ['BlockType' => 'WORD', 'Text' => 'Word2', 'Confidence' => 93.0, 'Page' => 1],
            ['BlockType' => 'WORD', 'Text' => 'Word3', 'Confidence' => 91.0, 'Page' => 1],
            ['BlockType' => 'WORD', 'Text' => 'Word4', 'Page' => 1], // Missing confidence
            ['BlockType' => 'WORD', 'Text' => 'Word5', 'Page' => 1], // Missing confidence
        ];

        // Act
        $result = $this->analyzer->analyzeFromBlocks($blocks);

        // Assert: Coverage = 3/5 = 0.6
        $this->assertEquals(0.6, $result['coverage']);
    }

    /** @test */
    public function it_flags_document_when_low_confidence_pages_exceed_threshold()
    {
        // Arrange: 5-page document with 4 pages having low confidence (exceeds default threshold of 3)
        $blocks = [
            // Page 1: High confidence (avg = 0.94) - GOOD
            ['BlockType' => 'WORD', 'Text' => 'Good1', 'Confidence' => 95.0, 'Page' => 1],
            ['BlockType' => 'WORD', 'Text' => 'Good2', 'Confidence' => 93.0, 'Page' => 1],

            // Page 2: Low confidence (avg = 0.675) - BAD
            ['BlockType' => 'WORD', 'Text' => 'Bad1', 'Confidence' => 65.0, 'Page' => 2],
            ['BlockType' => 'WORD', 'Text' => 'Bad2', 'Confidence' => 70.0, 'Page' => 2],

            // Page 3: Low confidence (avg = 0.72) - BAD
            ['BlockType' => 'WORD', 'Text' => 'Bad3', 'Confidence' => 72.0, 'Page' => 3],
            ['BlockType' => 'WORD', 'Text' => 'Bad4', 'Confidence' => 72.0, 'Page' => 3],

            // Page 4: Low confidence (avg = 0.755) - BAD
            ['BlockType' => 'WORD', 'Text' => 'Bad5', 'Confidence' => 75.0, 'Page' => 4],
            ['BlockType' => 'WORD', 'Text' => 'Bad6', 'Confidence' => 76.0, 'Page' => 4],

            // Page 5: Low confidence (avg = 0.68) - BAD
            ['BlockType' => 'WORD', 'Text' => 'Bad7', 'Confidence' => 68.0, 'Page' => 5],
            ['BlockType' => 'WORD', 'Text' => 'Bad8', 'Confidence' => 68.0, 'Page' => 5],
        ];

        // Act
        $result = $this->analyzer->analyzeFromBlocks($blocks);

        // Assert: 4 out of 5 pages have low confidence (below 0.80 threshold)
        $this->assertEquals(5, $result['total_pages']);
        $this->assertEquals(4, $result['low_confidence_pages']);
        $this->assertContains(2, $result['low_confidence_page_numbers']);
        $this->assertContains(3, $result['low_confidence_page_numbers']);
        $this->assertContains(4, $result['low_confidence_page_numbers']);
        $this->assertContains(5, $result['low_confidence_page_numbers']);
        $this->assertNotContains(1, $result['low_confidence_page_numbers']);

        // Assert: Overall confidence is also low
        $this->assertLessThan(0.80, $result['confidence']);

        // Assert: This would trigger needs_review in CaseIngestPipeline
        // (default max_low_confidence_pages is 3, we have 4)
        $this->assertGreaterThan(3, $result['low_confidence_pages'],
            'Document should exceed the default threshold of 3 low-confidence pages');
    }

    /** @test */
    public function it_handles_document_with_all_low_confidence_pages()
    {
        // Arrange: 3-page document where all pages have low confidence
        $blocks = [
            // Page 1: Low confidence
            ['BlockType' => 'WORD', 'Text' => 'Low1', 'Confidence' => 60.0, 'Page' => 1],
            ['BlockType' => 'WORD', 'Text' => 'Low2', 'Confidence' => 62.0, 'Page' => 1],

            // Page 2: Low confidence
            ['BlockType' => 'WORD', 'Text' => 'Low3', 'Confidence' => 58.0, 'Page' => 2],
            ['BlockType' => 'WORD', 'Text' => 'Low4', 'Confidence' => 64.0, 'Page' => 2],

            // Page 3: Low confidence
            ['BlockType' => 'WORD', 'Text' => 'Low5', 'Confidence' => 55.0, 'Page' => 3],
            ['BlockType' => 'WORD', 'Text' => 'Low6', 'Confidence' => 59.0, 'Page' => 3],
        ];

        // Act
        $result = $this->analyzer->analyzeFromBlocks($blocks);

        // Assert: All pages flagged as low confidence
        $this->assertEquals(3, $result['total_pages']);
        $this->assertEquals(3, $result['low_confidence_pages']);
        $this->assertEquals([1, 2, 3], $result['low_confidence_page_numbers']);

        // Assert: Overall confidence is very low
        $this->assertLessThan(0.65, $result['confidence']);
    }

    /** @test */
    public function it_correctly_identifies_mixed_quality_multipage_document()
    {
        // Arrange: 10-page document with varied quality (2 low, 8 good)
        $blocks = [];

        for ($page = 1; $page <= 10; $page++) {
            // Pages 3 and 7 have low confidence, others are good
            $isLowQuality = in_array($page, [3, 7]);
            $confidence = $isLowQuality ? 70.0 : 92.0;

            $blocks[] = ['BlockType' => 'WORD', 'Text' => "Page{$page}Word1", 'Confidence' => $confidence, 'Page' => $page];
            $blocks[] = ['BlockType' => 'WORD', 'Text' => "Page{$page}Word2", 'Confidence' => $confidence, 'Page' => $page];
            $blocks[] = ['BlockType' => 'WORD', 'Text' => "Page{$page}Word3", 'Confidence' => $confidence, 'Page' => $page];
        }

        // Act
        $result = $this->analyzer->analyzeFromBlocks($blocks);

        // Assert: Correctly identifies the 2 low confidence pages
        $this->assertEquals(10, $result['total_pages']);
        $this->assertEquals(2, $result['low_confidence_pages']);
        $this->assertEquals([3, 7], $result['low_confidence_page_numbers']);

        // Assert: Overall confidence should be decent (weighted towards good pages)
        $this->assertGreaterThan(0.80, $result['confidence']);

        // Assert: This should NOT trigger needs_review (only 2 low conf pages, threshold is 3)
        $this->assertLessThanOrEqual(3, $result['low_confidence_pages']);
    }
}
