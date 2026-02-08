<?php

namespace Tests\Unit\Services\Ocr;

use App\Services\Ocr\TesseractOcrService;
use Tests\TestCase;

class TesseractOcrServiceTest extends TestCase
{
    private TesseractOcrService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TesseractOcrService();
    }

    /** @test */
    public function it_builds_tesseract_command_with_croatian_language(): void
    {
        $command = $this->service->buildCommand('/tmp/test.tif', '/tmp/output');

        $this->assertStringContainsString('hrv+eng', implode(' ', $command));
    }

    /** @test */
    public function it_preserves_page_structure_in_output(): void
    {
        $pages = [
            1 => "First page text\nwith multiple lines",
            2 => "Second page content\nmore text here",
        ];

        $result = $this->service->combinePages($pages, true);

        $this->assertStringContainsString('First page text', $result);
        $this->assertStringContainsString('Second page content', $result);
        $this->assertStringContainsString("\n\n--- Page 2 ---\n\n", $result);
    }

    /** @test */
    public function it_combines_pages_without_separators_when_disabled(): void
    {
        $pages = [
            1 => "Page one text",
            2 => "Page two text",
        ];

        $result = $this->service->combinePages($pages, false);

        $this->assertStringNotContainsString('--- Page', $result);
        $this->assertStringContainsString("Page one text\n\nPage two text", $result);
    }

    /** @test */
    public function it_returns_empty_for_nonexistent_file(): void
    {
        $result = $this->service->extractText('/nonexistent/file.pdf');

        $this->assertArrayHasKey('text', $result);
        $this->assertEquals('', $result['text']);
        $this->assertEquals('error', $result['status']);
    }

    /** @test */
    public function it_returns_page_metadata_structure(): void
    {
        $pages = [
            1 => "Test content for page one with adequate text",
            2 => "Second page with different content here",
        ];

        $metadata = $this->service->buildPageMetadata($pages);

        $this->assertCount(2, $metadata);
        $this->assertEquals(1, $metadata[0]['page']);
        $this->assertArrayHasKey('char_count', $metadata[0]);
        $this->assertArrayHasKey('word_count', $metadata[0]);
    }

    /** @test */
    public function it_configures_psm_and_oem_from_config(): void
    {
        config(['ocr.tesseract.psm' => 6, 'ocr.tesseract.oem' => 3]);
        $service = new TesseractOcrService();
        $command = $service->buildCommand('/tmp/test.tif', '/tmp/output');

        $this->assertContains('--psm', $command);
        $this->assertContains('6', $command);
        $this->assertContains('--oem', $command);
        $this->assertContains('3', $command);
    }
}
