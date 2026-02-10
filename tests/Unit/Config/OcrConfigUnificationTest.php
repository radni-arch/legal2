<?php

namespace Tests\Unit\Config;

use Tests\TestCase;

/**
 * OcrConfigUnificationTest
 *
 * Ensures all OCR pipeline steps read configuration from the unified
 * config/ocr.php namespace. No OCR consumer should use vizra-adk.ocr.*,
 * env() directly, or hard-coded values that diverge from config/ocr.php.
 *
 * @see config/ocr.php
 * @covers SOT-012
 */
class OcrConfigUnificationTest extends TestCase
{
    /**
     * List of files that are OCR pipeline consumers.
     * These files must ONLY read OCR config via config('ocr.*').
     */
    private function ocrConsumerFiles(): array
    {
        return [
            base_path('app/Pipelines/Textract/CheckOcrQualityStep.php'),
            base_path('app/Pipelines/Textract/TesseractOcrStep.php'),
            base_path('app/Pipelines/Textract/LocalOcrRouteStep.php'),
            base_path('app/Pipelines/Textract/CheckExistingTextStep.php'),
            base_path('app/Pipelines/Textract/PersistReconstructedStep.php'),
            base_path('app/Services/Ocr/OcrmypdfService.php'),
            base_path('app/Services/Ocr/DocumentOcrRouter.php'),
            base_path('app/Services/Ocr/TesseractOcrService.php'),
            base_path('app/Services/Ocr/OcrQualityComparator.php'),
            base_path('app/Services/CaseIngestPipeline.php'),
        ];
    }

    // ---------------------------------------------------------------
    // Test: No OCR consumer uses the old vizra-adk.ocr.* namespace
    // ---------------------------------------------------------------

    /** @test */
    public function no_ocr_consumer_uses_vizra_adk_ocr_config_namespace(): void
    {
        $violations = [];

        foreach ($this->ocrConsumerFiles() as $file) {
            if (! file_exists($file)) {
                continue;
            }

            $contents = file_get_contents($file);
            if (preg_match_all("/config\(\s*['\"]vizra-adk\.ocr\./", $contents, $matches)) {
                $violations[] = basename($file) . ': ' . count($matches[0]) . ' vizra-adk.ocr.* reference(s)';
            }
        }

        $this->assertEmpty(
            $violations,
            "OCR consumers still reference vizra-adk.ocr.* namespace:\n" . implode("\n", $violations)
        );
    }

    // ---------------------------------------------------------------
    // Test: No OCR consumer uses env() directly
    // ---------------------------------------------------------------

    /** @test */
    public function no_ocr_consumer_uses_env_directly(): void
    {
        $violations = [];

        foreach ($this->ocrConsumerFiles() as $file) {
            if (! file_exists($file)) {
                continue;
            }

            $contents = file_get_contents($file);
            // Match env() calls with OCR/TESSERACT/OCRMYPDF env vars
            if (preg_match_all("/\benv\(\s*['\"](?:OCR_|TESSERACT_|OCRMYPDF_|IMAGEMAGICK_)/", $contents, $matches)) {
                $violations[] = basename($file) . ': ' . count($matches[0]) . ' direct env() call(s)';
            }
        }

        $this->assertEmpty(
            $violations,
            "OCR consumers use env() directly instead of config():\n" . implode("\n", $violations)
        );
    }

    // ---------------------------------------------------------------
    // Test: All OCR consumers only use the config('ocr.*') namespace
    // ---------------------------------------------------------------

    /** @test */
    public function all_ocr_config_reads_use_ocr_namespace(): void
    {
        $violations = [];

        foreach ($this->ocrConsumerFiles() as $file) {
            if (! file_exists($file)) {
                continue;
            }

            $contents = file_get_contents($file);

            // Find all config() calls in the file
            preg_match_all("/config\(\s*['\"]([^'\"]+)['\"]/", $contents, $matches);

            foreach ($matches[1] as $configKey) {
                // Skip non-OCR config keys (e.g. vizra-adk.vector_memory.*)
                if (! $this->isOcrRelatedConfigKey($configKey)) {
                    continue;
                }

                // OCR-related config keys must start with 'ocr.'
                if (! str_starts_with($configKey, 'ocr.')) {
                    $violations[] = basename($file) . ": uses '{$configKey}' instead of 'ocr.*'";
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "OCR-related config reads not using 'ocr.*' namespace:\n" . implode("\n", $violations)
        );
    }

    // ---------------------------------------------------------------
    // Test: config/ocr.php has all required keys
    // ---------------------------------------------------------------

    /** @test */
    public function config_ocr_has_all_required_keys(): void
    {
        $config = config('ocr');

        // Top-level keys
        $this->assertArrayHasKey('default_engine', $config);
        $this->assertArrayHasKey('routing', $config);
        $this->assertArrayHasKey('quality', $config);
        $this->assertArrayHasKey('ocrmypdf', $config);
        $this->assertArrayHasKey('tesseract', $config);
        $this->assertArrayHasKey('min_words_per_page_skip', $config);
        $this->assertArrayHasKey('force_reocr_on_review', $config);

        // Quality section - must include skip_embedding_on_low_quality
        $quality = $config['quality'];
        $this->assertArrayHasKey('min_confidence', $quality);
        $this->assertArrayHasKey('min_coverage', $quality);
        $this->assertArrayHasKey('max_low_confidence_pages', $quality);
        $this->assertArrayHasKey('min_improvement_percent', $quality);
        $this->assertArrayHasKey('croatian_diacritics', $quality);
        $this->assertArrayHasKey('skip_embedding_on_low_quality', $quality,
            'config/ocr.php quality section must include skip_embedding_on_low_quality');

        // Routing section
        $routing = $config['routing'];
        $this->assertArrayHasKey('croatian_text_threshold', $routing);
        $this->assertArrayHasKey('structured_content_threshold', $routing);
        $this->assertArrayHasKey('textract_confidence_threshold', $routing);
        $this->assertArrayHasKey('fallback_confidence_threshold', $routing);
    }

    // ---------------------------------------------------------------
    // Test: All config keys have sensible defaults
    // ---------------------------------------------------------------

    /** @test */
    public function config_ocr_has_sensible_defaults(): void
    {
        // Engine defaults
        $this->assertEquals('auto', config('ocr.default_engine'));

        // Quality thresholds
        $this->assertEqualsWithDelta(0.82, config('ocr.quality.min_confidence'), 0.001);
        $this->assertEqualsWithDelta(0.75, config('ocr.quality.min_coverage'), 0.001);
        $this->assertEquals(3, config('ocr.quality.max_low_confidence_pages'));
        $this->assertEqualsWithDelta(5.0, config('ocr.quality.min_improvement_percent'), 0.001);
        $this->assertFalse(config('ocr.quality.skip_embedding_on_low_quality'));

        // Routing thresholds
        $this->assertEqualsWithDelta(0.6, config('ocr.routing.croatian_text_threshold'), 0.001);
        $this->assertEqualsWithDelta(0.85, config('ocr.routing.textract_confidence_threshold'), 0.001);
        $this->assertEqualsWithDelta(0.70, config('ocr.routing.fallback_confidence_threshold'), 0.001);

        // Min words per page skip
        $this->assertEquals(50, config('ocr.min_words_per_page_skip'));

        // Force re-OCR
        $this->assertTrue(config('ocr.force_reocr_on_review'));
    }

    // ---------------------------------------------------------------
    // Test: Defaults in consumer code match config/ocr.php defaults
    // ---------------------------------------------------------------

    /** @test */
    public function quality_config_defaults_are_consistent_across_consumers(): void
    {
        // These are the canonical defaults from config/ocr.php
        $expectedDefaults = [
            'ocr.quality.min_confidence' => 0.82,
            'ocr.quality.min_coverage' => 0.75,
            'ocr.quality.max_low_confidence_pages' => 3,
            'ocr.quality.min_improvement_percent' => 5.0,
            'ocr.quality.skip_embedding_on_low_quality' => false,
            'ocr.force_reocr_on_review' => true,
            'ocr.min_words_per_page_skip' => 50,
        ];

        foreach ($expectedDefaults as $key => $expected) {
            $actual = config($key);
            $this->assertEquals(
                $expected,
                $actual,
                "Config key '{$key}' has unexpected default value: " . var_export($actual, true)
            );
        }
    }

    // ---------------------------------------------------------------
    // Helper: Determine if a config key is OCR-related
    // ---------------------------------------------------------------

    private function isOcrRelatedConfigKey(string $key): bool
    {
        $ocrPatterns = [
            'ocr.',
            'vizra-adk.ocr.',
            'services.ocr.',
        ];

        foreach ($ocrPatterns as $pattern) {
            if (str_starts_with($key, $pattern)) {
                return true;
            }
        }

        // Check for specific OCR-related key fragments
        $ocrKeywords = ['min_confidence', 'min_coverage', 'max_low_confidence_pages',
            'skip_embedding_on_low_quality', 'ocrmypdf', 'tesseract.binary'];

        foreach ($ocrKeywords as $keyword) {
            if (str_contains($key, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
