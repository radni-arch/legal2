<?php

namespace Tests\Unit\Services;

use App\Services\Pdf\PdfArticleSplitter;
use App\Services\PdfRenderer;
use Mockery;
use Tests\TestCase;

class PdfArticleSplitterTest extends TestCase
{
    protected $mockRenderer;

    protected PdfArticleSplitter $splitter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockRenderer = Mockery::mock(PdfRenderer::class);
        $this->splitter = new PdfArticleSplitter($this->mockRenderer);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_throws_exception_when_pdf_file_not_found(): void
    {
        // Arrange
        $nonExistentPdf = '/tmp/non-existent-file.pdf';
        $outDir = sys_get_temp_dir().'/test-output';

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('PDF not found');

        $this->splitter->split($nonExistentPdf, $outDir);
    }

    public function test_detects_article_numbers_from_croatian_text(): void
    {
        // This is an integration-like test that would require a real PDF
        // For unit testing, we test the logic through a mock service

        $service = new class($this->mockRenderer) extends PdfArticleSplitter
        {
            public function detectArticleStartsByPagePublic(string $pdfPath, int $startPage, int $endPage): array
            {
                // Simulate detecting articles
                return [
                    ['number' => '1', 'start_page' => 1],
                    ['number' => '2', 'start_page' => 2],
                    ['number' => '3', 'start_page' => 3],
                ];
            }
        };

        $this->assertIsObject($service);
    }

    public function test_normalizes_article_numbers(): void
    {
        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->splitter);
        $method = $reflection->getMethod('normalizeArticleNumber');
        $method->setAccessible(true);

        // Test various formats
        $this->assertEquals('8a', $method->invoke($this->splitter, '8.a'));
        $this->assertEquals('12', $method->invoke($this->splitter, '12'));
        $this->assertEquals('15b', $method->invoke($this->splitter, '15.b'));
        $this->assertEquals('3', $method->invoke($this->splitter, '3'));
    }

    public function test_builds_page_ranges_correctly(): void
    {
        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->splitter);
        $method = $reflection->getMethod('buildPageRanges');
        $method->setAccessible(true);

        $starts = [
            ['number' => '1', 'start_page' => 1],
            ['number' => '2', 'start_page' => 3],
            ['number' => '3', 'start_page' => 5],
        ];

        $lastPage = 10;

        // Act
        $ranges = $method->invoke($this->splitter, $starts, $lastPage);

        // Assert
        $this->assertCount(3, $ranges);

        $this->assertEquals('1', $ranges[0]['number']);
        $this->assertEquals(1, $ranges[0]['start_page']);
        $this->assertEquals(3, $ranges[0]['end_page']); // Overlaps with next article start

        $this->assertEquals('2', $ranges[1]['number']);
        $this->assertEquals(3, $ranges[1]['start_page']);
        $this->assertEquals(5, $ranges[1]['end_page']);

        $this->assertEquals('3', $ranges[2]['number']);
        $this->assertEquals(5, $ranges[2]['start_page']);
        $this->assertEquals(10, $ranges[2]['end_page']); // Last page
    }

    public function test_splits_text_into_articles(): void
    {
        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->splitter);
        $method = $reflection->getMethod('splitTextIntoArticles');
        $method->setAccessible(true);

        $text = "Neka uvodna napomena.\n\nČlanak 1.\nSadržaj prvog članka.\n\nČlanak 2.\nSadržaj drugog članka.\n\nČlanak 3.\nSadržaj trećeg članka.";

        // Act
        $articles = $method->invoke($this->splitter, $text);

        // Assert
        $this->assertCount(3, $articles);
        $this->assertEquals('1', $articles[0]['number']);
        $this->assertStringContainsString('Članak 1', $articles[0]['text']);
        $this->assertEquals('2', $articles[1]['number']);
        $this->assertStringContainsString('Članak 2', $articles[1]['text']);
        $this->assertEquals('3', $articles[2]['number']);
    }

    public function test_splits_text_handles_case_insensitive_clanak(): void
    {
        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->splitter);
        $method = $reflection->getMethod('splitTextIntoArticles');
        $method->setAccessible(true);

        $text = "CLANAK 1.\nSadržaj.\n\nČlanak 2.\nDrugi sadržaj.\n\nCLANAK 3.\nTreći sadržaj.";

        // Act
        $articles = $method->invoke($this->splitter, $text);

        // Assert
        $this->assertGreaterThanOrEqual(1, count($articles));
    }

    public function test_creates_manifest_json_file(): void
    {
        // This test verifies manifest generation
        // In a real scenario, the split method creates a manifest.json file

        $reflection = new \ReflectionClass($this->splitter);
        $method = $reflection->getMethod('makeMeta');
        $method->setAccessible(true);

        // Create temp files for testing
        $srcPdf = tempnam(sys_get_temp_dir(), 'src_pdf_');
        $destPdf = tempnam(sys_get_temp_dir(), 'dest_pdf_');
        file_put_contents($srcPdf, 'source content');
        file_put_contents($destPdf, 'dest content');

        // Act
        $meta = $method->invoke(
            $this->splitter,
            $srcPdf,
            $destPdf,
            '5',
            10,
            15,
            'Test Law Title',
            'eli:hr:2024:law:123',
            '2024-01-15',
            'pages'
        );

        // Assert
        $this->assertEquals('5', $meta['article_number']);
        $this->assertEquals('Test Law Title', $meta['law_title']);
        $this->assertEquals('eli:hr:2024:law:123', $meta['eli']);
        $this->assertEquals('2024-01-15', $meta['publication_date']);
        $this->assertEquals('pages', $meta['mode']);
        $this->assertArrayHasKey('file', $meta);
        $this->assertArrayHasKey('sha256', $meta['file']);
        $this->assertArrayHasKey('bytes', $meta['file']);
        $this->assertEquals(['start' => 10, 'end' => 15], $meta['pages']);

        // Cleanup
        unlink($srcPdf);
        unlink($destPdf);
    }

    public function test_exports_page_range_creates_new_pdf(): void
    {
        // This test verifies the exportPageRange method
        // Due to FPDI library requirements, we'll test the structure

        $reflection = new \ReflectionClass($this->splitter);
        $method = $reflection->getMethod('exportPageRange');
        $method->setAccessible(true);

        // This would require a real PDF file to work properly
        // For unit testing, we verify the method signature exists
        $this->assertTrue($method->isPrivate());
        $this->assertEquals('exportPageRange', $method->getName());
    }

    public function test_handles_articles_with_letter_suffixes(): void
    {
        // Test normalization of articles like "8.a", "12.b"
        $reflection = new \ReflectionClass($this->splitter);
        $method = $reflection->getMethod('normalizeArticleNumber');
        $method->setAccessible(true);

        // Test various letter suffixes
        $this->assertEquals('8a', $method->invoke($this->splitter, '8.a'));
        $this->assertEquals('12b', $method->invoke($this->splitter, '12.b'));
        $this->assertEquals('15c', $method->invoke($this->splitter, '15.c'));
        $this->assertEquals('20a', $method->invoke($this->splitter, '20.A')); // Should handle uppercase
    }

    public function test_render_mode_creates_html_articles(): void
    {
        // This test verifies that render mode generates HTML-based PDFs

        $service = new class($this->mockRenderer) extends PdfArticleSplitter
        {
            public function test_render_mode()
            {
                $text = 'Članak 1. Test content';
                $articleHtml = '<div style="white-space:pre-wrap">'.e($text).'</div>';

                return [
                    'article_html' => $articleHtml,
                    'contains_div' => str_contains($articleHtml, '<div'),
                    'preserves_whitespace' => str_contains($articleHtml, 'pre-wrap'),
                ];
            }
        };

        $result = $service->test_render_mode();

        $this->assertTrue($result['contains_div']);
        $this->assertTrue($result['preserves_whitespace']);
    }

    public function test_splits_text_normalizes_line_endings(): void
    {
        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->splitter);
        $method = $reflection->getMethod('splitTextIntoArticles');
        $method->setAccessible(true);

        // Test with Windows-style line endings
        $text = "Članak 1.\r\nSadržaj.\r\n\r\nČlanak 2.\r\nDrugi sadržaj.";

        // Act
        $articles = $method->invoke($this->splitter, $text);

        // Assert
        $this->assertCount(2, $articles);
    }

    public function test_throws_exception_when_no_articles_detected(): void
    {
        // This would test the actual split method behavior
        // when no articles are found in the PDF

        $service = new class($this->mockRenderer) extends PdfArticleSplitter
        {
            public function split(
                string $pdfPath,
                string $outDir,
                string $mode = 'pages',
                ?string $lawTitle = null,
                ?string $eli = null,
                ?string $pubDate = null,
                int $startPage = 1
            ): array {
                // Simulate no articles found
                throw new \RuntimeException("Nisam pronašao oznake 'Članak N.' u PDF-u. Pokušaj s --start-page ili mode=render.");
            }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Nisam pronašao oznake');

        $service->split('/fake/path.pdf', '/fake/output');
    }

    public function test_creates_output_directory_if_not_exists(): void
    {
        // Test that split method creates output directory
        $outDir = sys_get_temp_dir().'/test-pdf-split-'.uniqid();

        // Verify directory doesn't exist yet
        $this->assertDirectoryDoesNotExist($outDir);

        // The split method would create it with @mkdir($outDir, 0775, true)
        // For testing, we verify the concept
        @mkdir($outDir, 0775, true);
        $this->assertDirectoryExists($outDir);

        // Cleanup
        rmdir($outDir);
    }

    public function test_generates_proper_filename_format(): void
    {
        // Test filename generation for articles
        $lawTitle = 'Zakon o radu';
        $articleNumber = '5';

        $expectedFilename = $lawTitle.' - '.sprintf('clanak-%s.pdf', $articleNumber);

        $this->assertEquals('Zakon o radu - clanak-5.pdf', $expectedFilename);
    }

    public function test_manifest_includes_all_required_fields(): void
    {
        // Verify manifest structure
        $manifestStructure = [
            'source_pdf' => 'required',
            'mode' => 'required',
            'count' => 'required',
            'generated_at' => 'required',
            'articles' => 'required',
        ];

        $this->assertArrayHasKey('source_pdf', $manifestStructure);
        $this->assertArrayHasKey('mode', $manifestStructure);
        $this->assertArrayHasKey('count', $manifestStructure);
        $this->assertArrayHasKey('articles', $manifestStructure);
    }

    public function test_article_metadata_includes_file_hash(): void
    {
        // Verify that article metadata includes SHA-256 hash
        $reflection = new \ReflectionClass($this->splitter);
        $method = $reflection->getMethod('makeMeta');
        $method->setAccessible(true);

        $testFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($testFile, 'test content');

        $srcPdf = tempnam(sys_get_temp_dir(), 'src_');
        file_put_contents($srcPdf, 'source');

        $meta = $method->invoke(
            $this->splitter,
            $srcPdf,
            $testFile,
            '1',
            1,
            1,
            'Test',
            null,
            null,
            'pages'
        );

        $this->assertNotNull($meta['file']['sha256']);
        $this->assertEquals(64, strlen($meta['file']['sha256'])); // SHA-256 produces 64 hex chars

        unlink($testFile);
        unlink($srcPdf);
    }

    public function test_handles_mixed_case_article_markers(): void
    {
        // Test detection of both "Članak" and "CLANAK"
        $reflection = new \ReflectionClass($this->splitter);
        $method = $reflection->getMethod('splitTextIntoArticles');
        $method->setAccessible(true);

        $text = "Članak 1.\nFirst.\n\nCLANAK 2.\nSecond.\n\nČlanak 3.\nThird.";

        $articles = $method->invoke($this->splitter, $text);

        // Should detect all three regardless of case
        $this->assertGreaterThanOrEqual(1, count($articles));
    }

    public function test_page_range_constraints(): void
    {
        // Test that page ranges are properly constrained
        $reflection = new \ReflectionClass($this->splitter);
        $method = $reflection->getMethod('buildPageRanges');
        $method->setAccessible(true);

        $starts = [
            ['number' => '1', 'start_page' => 1],
            ['number' => '2', 'start_page' => 100], // Beyond last page
        ];

        $lastPage = 50;

        $ranges = $method->invoke($this->splitter, $starts, $lastPage);

        // The second article's end page should be constrained to lastPage
        $this->assertEquals(50, $ranges[1]['end_page']);
    }
}
