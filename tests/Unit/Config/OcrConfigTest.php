<?php

namespace Tests\Unit\Config;

use Tests\TestCase;

class OcrConfigTest extends TestCase
{
    /** @test */
    public function it_has_all_required_top_level_sections(): void
    {
        $config = config('ocr');

        $this->assertArrayHasKey('default_engine', $config);
        $this->assertArrayHasKey('routing', $config);
        $this->assertArrayHasKey('quality', $config);
        $this->assertArrayHasKey('ocrmypdf', $config);
        $this->assertArrayHasKey('tesseract', $config);
        $this->assertArrayHasKey('force_reocr_on_review', $config);
    }

    /** @test */
    public function it_has_ocrmypdf_configuration_keys(): void
    {
        $ocrmypdf = config('ocr.ocrmypdf');

        $expectedKeys = [
            'binary',
            'languages',
            'timeout',
            'max_pages',
            'deskew',
            'clean',
            'remove_background',
            'jobs',
            'pdf_renderer',
            'output_type',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $ocrmypdf, "Missing ocrmypdf key: {$key}");
        }
    }

    /** @test */
    public function it_has_quality_configuration_keys(): void
    {
        $quality = config('ocr.quality');

        $this->assertArrayHasKey('min_confidence', $quality);
        $this->assertArrayHasKey('min_coverage', $quality);
        $this->assertArrayHasKey('max_low_confidence_pages', $quality);
        $this->assertArrayHasKey('min_improvement_percent', $quality);
        $this->assertArrayHasKey('croatian_diacritics', $quality);
    }

    /** @test */
    public function it_has_correct_default_values(): void
    {
        $this->assertEquals('auto', config('ocr.default_engine'));
        $this->assertEquals('ocrmypdf', config('ocr.ocrmypdf.binary'));
        $this->assertEquals('hrv+eng', config('ocr.ocrmypdf.languages'));
        $this->assertEquals(600, config('ocr.ocrmypdf.timeout'));
        $this->assertTrue(config('ocr.ocrmypdf.deskew'));
        $this->assertFalse(config('ocr.ocrmypdf.clean'));
        $this->assertEquals(0.82, config('ocr.quality.min_confidence'));
        $this->assertEquals(0.75, config('ocr.quality.min_coverage'));
        $this->assertEquals(3, config('ocr.quality.max_low_confidence_pages'));
        $this->assertTrue(config('ocr.force_reocr_on_review'));
    }

    /** @test */
    public function it_has_tesseract_configuration_keys(): void
    {
        $tesseract = config('ocr.tesseract');

        $expectedKeys = [
            'binary',
            'convert_binary',
            'languages',
            'dpi',
            'psm',
            'oem',
            'page_timeout',
            'max_pages',
            'preserve_layout',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $tesseract, "Missing tesseract key: {$key}");
        }
    }

    /** @test */
    public function it_has_routing_configuration_keys(): void
    {
        $routing = config('ocr.routing');

        $this->assertArrayHasKey('croatian_text_threshold', $routing);
        $this->assertArrayHasKey('structured_content_threshold', $routing);
        $this->assertArrayHasKey('textract_confidence_threshold', $routing);
        $this->assertArrayHasKey('fallback_confidence_threshold', $routing);
    }

    /** @test */
    public function quality_values_are_correct_types(): void
    {
        $this->assertIsFloat(config('ocr.quality.min_confidence'));
        $this->assertIsFloat(config('ocr.quality.min_coverage'));
        $this->assertIsInt(config('ocr.quality.max_low_confidence_pages'));
        $this->assertIsFloat(config('ocr.quality.min_improvement_percent'));
        $this->assertIsArray(config('ocr.quality.croatian_diacritics'));
    }

    /** @test */
    public function ocrmypdf_values_are_correct_types(): void
    {
        $this->assertIsString(config('ocr.ocrmypdf.binary'));
        $this->assertIsString(config('ocr.ocrmypdf.languages'));
        $this->assertIsInt(config('ocr.ocrmypdf.timeout'));
        $this->assertIsInt(config('ocr.ocrmypdf.max_pages'));
        $this->assertIsBool(config('ocr.ocrmypdf.deskew'));
        $this->assertIsBool(config('ocr.ocrmypdf.clean'));
        $this->assertIsBool(config('ocr.ocrmypdf.remove_background'));
        $this->assertIsInt(config('ocr.ocrmypdf.jobs'));
        $this->assertIsString(config('ocr.ocrmypdf.pdf_renderer'));
        $this->assertIsString(config('ocr.ocrmypdf.output_type'));
    }
}
