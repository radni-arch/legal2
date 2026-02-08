# OCR Pipeline Robustness Sprint Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Replace raw Tesseract+ImageMagick with ocrmypdf, harden the OCR routing logic, and make the entire Textract↔Tesseract pipeline resilient to failures.

**Architecture:** The current pipeline runs Textract first, then optionally routes to Tesseract if quality is low or Croatian content is detected. We replace `TesseractOcrService` internals with `ocrmypdf` (which wraps Tesseract + Ghostscript with deskew/denoise). We fix contradictory routing rules in `DocumentOcrRouter`, add retry/circuit-breaker patterns to the pipeline steps, and add integration tests for the full flow.

**Tech Stack:** Laravel Pipeline, ocrmypdf (Python CLI), Tesseract 5 (via ocrmypdf), AWS Textract, PHPUnit, config-driven thresholds.

---

## Sprint Overview

| # | Task | Estimate | Priority |
|---|------|----------|----------|
| 1 | Audit & document current routing logic flaws | 1h | P0 |
| 2 | Create `OcrmypdfService` replacing `TesseractOcrService` internals | 3h | P0 |
| 3 | Fix `DocumentOcrRouter` contradictory rules | 2h | P0 |
| 4 | Add retry wrapper for pipeline steps | 2h | P0 |
| 5 | Harden `TesseractOcrStep` to use new `OcrmypdfService` | 1.5h | P0 |
| 6 | Fix `OcrQualityComparator` scoring saturation | 1h | P1 |
| 7 | Add health-check / availability guard | 1h | P1 |
| 8 | Add pipeline timeout enforcement | 1.5h | P1 |
| 9 | Add re-OCR path when quality is below threshold | 2h | P1 |
| 10 | Integration tests for full OCR routing flow | 3h | P1 |
| 11 | Config & documentation cleanup | 1h | P2 |

**Total estimate:** ~19 hours (~2.5 days)

---

## Task 1: Audit & Document Current Routing Logic Flaws

**Files:**
- Read: `app/Services/Ocr/DocumentOcrRouter.php`
- Read: `app/Pipelines/Textract/TesseractOcrStep.php`
- Read: `app/Pipelines/Textract/CheckOcrQualityStep.php`
- Create: `docs/ocr-routing-audit.md`

**Purpose:** Before changing code, document exactly what's broken so we can write targeted tests.

**Step 1: Document the contradiction in DocumentOcrRouter::recommend()**

The current rules execute sequentially without early return:

```
Rule 1: avg_confidence < 0.70 → engine = tesseract
Rule 2: croatian + avg_confidence < 0.85 → engine = tesseract  
Rule 3: structured content ratio >= 0.30 → engine = textract  ← OVERRIDES Rule 1 & 2
Rule 4: avg_confidence >= 0.85 → engine = textract
```

Problem: A document with **low confidence (0.65)** AND **tables** hits Rule 1 (tesseract), then Rule 3 flips it back to textract. The low-confidence signal is lost.

**Step 2: Document the quality comparator saturation**

In `OcrQualityComparator::scoreDiacritics()`:
```php
return min(1.0, ($diacriticCount / $len) * 20);
```
Croatian text at ~5% diacritics: `0.05 * 20 = 1.0` → saturated. Both Textract and Tesseract outputs will score 1.0 for diacritics, making the comparison useless for Croatian text.

**Step 3: Document missing error paths**

- `TesseractOcrStep`: If Tesseract binary missing → falls back silently, no alert
- `CheckOcrQualityStep`: Sets `needsReview = true` but nothing acts on it
- `ProcessDrivePdf`: Catches `\Throwable` but no per-step retry
- No timeout on the Textract polling loop (`WaitAndFetchTextract`)

**Step 4: Write the audit doc**

```markdown
# OCR Routing Audit — [DATE]

## Critical Issues
1. Rule contradiction in DocumentOcrRouter (Rule 3 overrides Rule 1)
2. Diacritic scoring saturates at ~5% density, neutralizing Croatian comparison
3. needsReview flag is set but never consumed by any downstream step
4. No retry on transient failures (S3 upload, Textract API)
5. No health check before attempting Tesseract path

## Moderate Issues  
6. TesseractOcrService spawns 2 processes (convert + tesseract) per page
7. No deskew/denoise preprocessing
8. Temp file cleanup in finally block but no verification
9. No pipeline-level timeout (Textract polling can hang)

## Recommendations
- Replace ImageMagick+Tesseract with ocrmypdf (single process, handles deskew)
- Rewrite routing as weighted scoring instead of sequential if/else
- Add retry decorator to pipeline steps
- Add circuit breaker for external service calls
```

**Step 5: Commit**
```bash
git add docs/ocr-routing-audit.md
git commit -m "docs: audit OCR routing logic and document flaws"
```

---

## Task 2: Create `OcrmypdfService` Replacing Tesseract Internals

**Files:**
- Create: `app/Services/Ocr/OcrmypdfService.php`
- Create: `config/ocr.php` (if not exists, or modify existing)
- Test: `tests/Unit/Services/Ocr/OcrmypdfServiceTest.php`

**Context:** `ocrmypdf` is a Python CLI tool that wraps Tesseract + Ghostscript. It handles PDF→image conversion, deskew, denoising, and produces a searchable PDF in one call. This replaces the fragile ImageMagick→TIFF→Tesseract chain.

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services\Ocr;

use App\Services\Ocr\OcrmypdfService;
use Tests\TestCase;

class OcrmypdfServiceTest extends TestCase
{
    private OcrmypdfService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OcrmypdfService();
    }

    /** @test */
    public function it_builds_correct_command_with_defaults(): void
    {
        $cmd = $this->service->buildCommand('/tmp/input.pdf', '/tmp/output.pdf');

        $this->assertContains('ocrmypdf', $cmd);
        $this->assertContains('--language', $cmd);
        $this->assertContains('hrv+eng', $cmd);
        $this->assertContains('--deskew', $cmd);
        $this->assertContains('/tmp/input.pdf', $cmd);
        $this->assertContains('/tmp/output.pdf', $cmd);
    }

    /** @test */
    public function it_builds_command_with_sidecar_text_extraction(): void
    {
        $cmd = $this->service->buildCommand(
            '/tmp/input.pdf',
            '/tmp/output.pdf',
            ['sidecar' => '/tmp/output.txt']
        );

        $this->assertContains('--sidecar', $cmd);
        $this->assertContains('/tmp/output.txt', $cmd);
    }

    /** @test */
    public function it_builds_command_with_force_ocr(): void
    {
        $cmd = $this->service->buildCommand(
            '/tmp/input.pdf',
            '/tmp/output.pdf',
            ['force_ocr' => true]
        );

        $this->assertContains('--force-ocr', $cmd);
    }

    /** @test */
    public function it_builds_command_with_skip_text(): void
    {
        $cmd = $this->service->buildCommand(
            '/tmp/input.pdf',
            '/tmp/output.pdf',
            ['skip_text' => true]
        );

        $this->assertContains('--skip-text', $cmd);
    }

    /** @test */
    public function it_reports_availability_correctly(): void
    {
        // This test depends on ocrmypdf being installed in CI/dev
        $available = $this->service->isAvailable();
        $this->assertIsBool($available);
    }

    /** @test */
    public function it_returns_error_result_for_missing_file(): void
    {
        $result = $this->service->process('/nonexistent/file.pdf');

        $this->assertEquals('error', $result['status']);
        $this->assertNotEmpty($result['error']);
        $this->assertEquals('ocrmypdf', $result['engine']);
    }

    /** @test */
    public function it_includes_clean_and_denoise_options(): void
    {
        $cmd = $this->service->buildCommand(
            '/tmp/input.pdf',
            '/tmp/output.pdf',
            ['clean' => true]
        );

        $this->assertContains('--clean', $cmd);
    }

    /** @test */
    public function it_respects_max_pages_config(): void
    {
        config(['ocr.ocrmypdf.max_pages' => 50]);
        $service = new OcrmypdfService();
        $cmd = $service->buildCommand('/tmp/input.pdf', '/tmp/output.pdf');

        $this->assertContains('--pages', $cmd);
        $this->assertContains('1-50', $cmd);
    }
}
```

**Step 2: Run test to verify it fails**
```bash
php artisan test --filter=OcrmypdfServiceTest
```
Expected: FAIL — class not found.

**Step 3: Write the implementation**

```php
<?php

namespace App\Services\Ocr;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * OcrmypdfService
 *
 * Wraps ocrmypdf CLI for high-quality OCR with deskew, denoise, and
 * native searchable PDF output. Replaces the ImageMagick+Tesseract chain.
 *
 * Install: pip install ocrmypdf
 * Requires: tesseract-ocr, tesseract-ocr-hrv, ghostscript
 */
class OcrmypdfService
{
    private string $binary;
    private string $languages;
    private int $timeout;
    private int $maxPages;
    private bool $deskew;
    private bool $clean;
    private bool $removeBackground;
    private int $jobsParallel;
    private string $pdfRenderer;
    private string $outputType;

    public function __construct()
    {
        $this->binary = (string) config('ocr.ocrmypdf.binary', 'ocrmypdf');
        $this->languages = (string) config('ocr.ocrmypdf.languages', 'hrv+eng');
        $this->timeout = (int) config('ocr.ocrmypdf.timeout', 600);
        $this->maxPages = (int) config('ocr.ocrmypdf.max_pages', 0);
        $this->deskew = (bool) config('ocr.ocrmypdf.deskew', true);
        $this->clean = (bool) config('ocr.ocrmypdf.clean', false);
        $this->removeBackground = (bool) config('ocr.ocrmypdf.remove_background', false);
        $this->jobsParallel = (int) config('ocr.ocrmypdf.jobs', 2);
        $this->pdfRenderer = (string) config('ocr.ocrmypdf.pdf_renderer', 'sandwich');
        $this->outputType = (string) config('ocr.ocrmypdf.output_type', 'pdf');
    }

    /**
     * Process a PDF file with ocrmypdf.
     *
     * @param string $inputPath Absolute path to input PDF
     * @param array  $options   Override options (force_ocr, skip_text, sidecar, clean)
     * @return array{
     *   status: string,
     *   output_pdf: string,
     *   text: string,
     *   text_file: ?string,
     *   page_count: int,
     *   engine: string,
     *   error: ?string,
     *   exit_code: int
     * }
     */
    public function process(string $inputPath, array $options = []): array
    {
        if (! is_file($inputPath)) {
            return [
                'status' => 'error',
                'output_pdf' => '',
                'text' => '',
                'text_file' => null,
                'page_count' => 0,
                'engine' => 'ocrmypdf',
                'error' => "Input file not found: {$inputPath}",
                'exit_code' => -1,
            ];
        }

        $tmpDir = sys_get_temp_dir() . '/ocrmypdf_' . uniqid();
        @mkdir($tmpDir, 0755, true);

        $outputPdf = $options['output_pdf'] ?? $tmpDir . '/output.pdf';
        $sidecarPath = $options['sidecar'] ?? $tmpDir . '/output.txt';

        // Always request sidecar for text extraction
        $options['sidecar'] = $sidecarPath;

        try {
            $command = $this->buildCommand($inputPath, $outputPdf, $options);

            Log::info('OcrmypdfService: executing', [
                'input' => $inputPath,
                'output' => $outputPdf,
                'command' => implode(' ', $command),
            ]);

            $process = new Process($command);
            $process->setTimeout($this->timeout);
            $process->run();

            $exitCode = $process->getExitCode();
            $text = '';
            $pageCount = 0;

            // ocrmypdf exit codes:
            // 0 = success
            // 1 = bad args
            // 2 = input file error  
            // 3 = output file error
            // 4 = already has text (when using --skip-text)
            // 5 = missing dependency
            // 6 = invalid config
            // 10 = encryption issue
            // 15 = other error

            if ($exitCode === 0 || $exitCode === 4) {
                // Read sidecar text
                if (is_file($sidecarPath)) {
                    $text = trim(file_get_contents($sidecarPath));
                }

                // Estimate page count from sidecar form-feeds or pdfinfo
                $pageCount = $this->estimatePageCount($outputPdf, $text);

                $status = $exitCode === 0 ? 'success' : 'skipped_existing_text';

                Log::info('OcrmypdfService: completed', [
                    'input' => $inputPath,
                    'status' => $status,
                    'text_length' => mb_strlen($text),
                    'page_count' => $pageCount,
                ]);

                return [
                    'status' => $status,
                    'output_pdf' => $outputPdf,
                    'text' => $text,
                    'text_file' => is_file($sidecarPath) ? $sidecarPath : null,
                    'page_count' => $pageCount,
                    'engine' => 'ocrmypdf',
                    'error' => null,
                    'exit_code' => $exitCode,
                ];
            }

            $errorOutput = $process->getErrorOutput() ?: $process->getOutput();

            Log::warning('OcrmypdfService: failed', [
                'input' => $inputPath,
                'exit_code' => $exitCode,
                'error' => $errorOutput,
            ]);

            return [
                'status' => 'error',
                'output_pdf' => '',
                'text' => '',
                'text_file' => null,
                'page_count' => 0,
                'engine' => 'ocrmypdf',
                'error' => "ocrmypdf exited with code {$exitCode}: " . mb_substr($errorOutput, 0, 500),
                'exit_code' => $exitCode,
            ];
        } catch (\Throwable $e) {
            Log::error('OcrmypdfService: exception', [
                'input' => $inputPath,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'error',
                'output_pdf' => '',
                'text' => '',
                'text_file' => null,
                'page_count' => 0,
                'engine' => 'ocrmypdf',
                'error' => $e->getMessage(),
                'exit_code' => -1,
            ];
        }
    }

    /**
     * Build the ocrmypdf command array.
     *
     * @return string[]
     */
    public function buildCommand(string $inputPath, string $outputPath, array $options = []): array
    {
        $cmd = [$this->binary];

        // Language
        $cmd[] = '--language';
        $cmd[] = $options['languages'] ?? $this->languages;

        // Deskew (straighten scanned pages)
        if ($options['deskew'] ?? $this->deskew) {
            $cmd[] = '--deskew';
        }

        // Clean (denoise before OCR)
        if ($options['clean'] ?? $this->clean) {
            $cmd[] = '--clean';
        }

        // Remove background
        if ($options['remove_background'] ?? $this->removeBackground) {
            $cmd[] = '--remove-background';
        }

        // Force OCR (re-OCR even if text layer exists)
        if ($options['force_ocr'] ?? false) {
            $cmd[] = '--force-ocr';
        }

        // Skip pages that already have text
        if ($options['skip_text'] ?? false) {
            $cmd[] = '--skip-text';
        }

        // Sidecar text output
        if (isset($options['sidecar'])) {
            $cmd[] = '--sidecar';
            $cmd[] = $options['sidecar'];
        }

        // Parallel jobs
        $jobs = (int) ($options['jobs'] ?? $this->jobsParallel);
        if ($jobs > 0) {
            $cmd[] = '--jobs';
            $cmd[] = (string) $jobs;
        }

        // PDF renderer
        $cmd[] = '--pdf-renderer';
        $cmd[] = $options['pdf_renderer'] ?? $this->pdfRenderer;

        // Output type
        $cmd[] = '--output-type';
        $cmd[] = $options['output_type'] ?? $this->outputType;

        // Page limit
        $maxPages = (int) ($options['max_pages'] ?? $this->maxPages);
        if ($maxPages > 0) {
            $cmd[] = '--pages';
            $cmd[] = "1-{$maxPages}";
        }

        // Rotate pages to correct orientation
        $cmd[] = '--rotate-pages';

        // Don't fail on soft errors (keep going on bad pages)
        $cmd[] = '--continue-on-soft-render-error';

        // Input and output
        $cmd[] = $inputPath;
        $cmd[] = $outputPath;

        return $cmd;
    }

    /**
     * Check if ocrmypdf is available.
     */
    public function isAvailable(): bool
    {
        try {
            $process = new Process([$this->binary, '--version']);
            $process->setTimeout(10);
            $process->run();
            return $process->isSuccessful();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Check if Croatian language data is available.
     */
    public function hasCroatianLanguage(): bool
    {
        try {
            $process = new Process(['tesseract', '--list-langs']);
            $process->setTimeout(10);
            $process->run();
            return $process->isSuccessful() && str_contains($process->getOutput(), 'hrv');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Get ocrmypdf version info.
     */
    public function getVersion(): ?string
    {
        try {
            $process = new Process([$this->binary, '--version']);
            $process->setTimeout(10);
            $process->run();
            return $process->isSuccessful() ? trim($process->getOutput()) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Estimate page count from output PDF or sidecar text.
     */
    private function estimatePageCount(string $pdfPath, string $text): int
    {
        // Try pdfinfo first
        try {
            $process = new Process(['pdfinfo', $pdfPath]);
            $process->setTimeout(10);
            $process->run();
            if ($process->isSuccessful() && preg_match('/Pages:\s+(\d+)/', $process->getOutput(), $m)) {
                return (int) $m[1];
            }
        } catch (\Throwable) {
            // fallback
        }

        // Fallback: count form-feed characters in sidecar
        if ($text !== '') {
            return max(1, substr_count($text, "\f") + 1);
        }

        return 0;
    }
}
```

**Step 4: Run tests**
```bash
php artisan test --filter=OcrmypdfServiceTest
```
Expected: PASS (at least the command-building and error-handling tests).

**Step 5: Add config entries**

In `config/ocr.php` (create or extend):
```php
'ocrmypdf' => [
    'binary'            => env('OCRMYPDF_BINARY', 'ocrmypdf'),
    'languages'         => env('OCRMYPDF_LANGUAGES', 'hrv+eng'),
    'timeout'           => env('OCRMYPDF_TIMEOUT', 600),
    'max_pages'         => env('OCRMYPDF_MAX_PAGES', 0),
    'deskew'            => env('OCRMYPDF_DESKEW', true),
    'clean'             => env('OCRMYPDF_CLEAN', false),
    'remove_background' => env('OCRMYPDF_REMOVE_BG', false),
    'jobs'              => env('OCRMYPDF_JOBS', 2),
    'pdf_renderer'      => env('OCRMYPDF_RENDERER', 'sandwich'),
    'output_type'       => env('OCRMYPDF_OUTPUT_TYPE', 'pdf'),
],
```

**Step 6: Commit**
```bash
git add app/Services/Ocr/OcrmypdfService.php config/ocr.php tests/Unit/Services/Ocr/OcrmypdfServiceTest.php
git commit -m "feat: add OcrmypdfService wrapping ocrmypdf CLI"
```

---

## Task 3: Fix `DocumentOcrRouter` Contradictory Rules

**Files:**
- Modify: `app/Services/Ocr/DocumentOcrRouter.php`
- Test: `tests/Unit/Services/Ocr/DocumentOcrRouterTest.php`

**Problem:** Sequential if-statements allow later rules to silently override earlier, more critical rules. A document with confidence 0.60 AND tables gets routed to Textract (Rule 3 overrides Rule 1).

**Step 1: Write failing tests that expose the contradiction**

```php
/** @test */
public function low_confidence_with_tables_still_routes_to_tesseract(): void
{
    $router = new DocumentOcrRouter();

    // Simulate: low confidence Textract output with some tables
    $text = str_repeat('presuda rješenje zahtjev sud ', 50);
    
    // Build blocks: mostly WORD blocks with low confidence, plus some TABLE blocks
    $blocks = [];
    for ($i = 0; $i < 100; $i++) {
        $blocks[] = ['BlockType' => 'WORD', 'Confidence' => 55.0, 'Page' => 1];
    }
    $blocks[] = ['BlockType' => 'TABLE', 'Page' => 1];
    $blocks[] = ['BlockType' => 'TABLE', 'Page' => 2];

    $result = $router->recommend($text, $blocks);

    // With 55% confidence, tables should NOT override the low-confidence signal
    $this->assertEquals('tesseract', $result['engine'],
        'Low confidence (0.55) should route to tesseract even with tables present');
}

/** @test */
public function high_confidence_with_tables_keeps_textract(): void
{
    $router = new DocumentOcrRouter();
    $text = 'Some English form content with tables';
    
    $blocks = [];
    for ($i = 0; $i < 100; $i++) {
        $blocks[] = ['BlockType' => 'WORD', 'Confidence' => 95.0, 'Page' => 1];
    }
    $blocks[] = ['BlockType' => 'TABLE', 'Page' => 1];

    $result = $router->recommend($text, $blocks);
    $this->assertEquals('textract', $result['engine']);
}
```

**Step 2: Run to verify failure**
```bash
php artisan test --filter=DocumentOcrRouterTest::low_confidence_with_tables_still_routes_to_tesseract
```
Expected: FAIL — currently returns 'textract' due to Rule 3 override.

**Step 3: Rewrite `recommend()` as weighted scoring**

Replace the sequential if/else chain with a scoring system:

```php
public function recommend(string $text, array $blocks = []): array
{
    $forcedEngine = config('ocr.default_engine', 'auto');
    if ($forcedEngine !== 'auto') {
        return [
            'engine' => $forcedEngine,
            'reasons' => ['forced_by_config'],
            'confidence' => 1.0,
            'analysis' => [],
        ];
    }

    $textAnalysis = $this->analyzeDocument($text);
    $blockAnalysis = !empty($blocks) ? $this->analyzeTextractBlocks($blocks) : [
        'has_structured_content' => false,
        'table_count' => 0,
        'form_count' => 0,
        'avg_confidence' => 0.0,
    ];

    // Weighted scoring: positive = favors Tesseract, negative = favors Textract
    $score = 0.0;
    $reasons = [];
    $weights = config('ocr.routing.weights', []);

    $fallbackThreshold = (float) ($weights['fallback_confidence_threshold'] ?? 0.70);
    $textractMinConf = (float) ($weights['textract_confidence_threshold'] ?? 0.85);
    $croatianThreshold = (float) ($weights['croatian_text_threshold'] ?? 0.6);

    // Signal 1: Textract confidence (strongest signal)
    $avgConf = $blockAnalysis['avg_confidence'];
    if ($avgConf > 0 && $avgConf < $fallbackThreshold) {
        $score += 3.0;  // Strong push toward tesseract
        $reasons[] = sprintf('very_low_textract_confidence:%.2f', $avgConf);
    } elseif ($avgConf > 0 && $avgConf < $textractMinConf) {
        $score += 1.0;  // Moderate push toward tesseract
        $reasons[] = sprintf('moderate_textract_confidence:%.2f', $avgConf);
    } elseif ($avgConf >= $textractMinConf) {
        $score -= 2.0;  // Push toward textract
        $reasons[] = sprintf('high_textract_confidence:%.2f', $avgConf);
    }

    // Signal 2: Croatian content
    if ($textAnalysis['has_croatian_content'] && $textAnalysis['croatian_score'] >= $croatianThreshold) {
        $score += 1.5;
        $reasons[] = sprintf('croatian_content:%.2f', $textAnalysis['croatian_score']);
    }

    // Signal 3: Structured content (tables/forms favor Textract, but NOT enough to override very low confidence)
    if ($blockAnalysis['has_structured_content']) {
        $score -= 1.0;  // Moderate push toward textract
        $reasons[] = sprintf('structured_content:tables=%d,forms=%d',
            $blockAnalysis['table_count'], $blockAnalysis['form_count']);
    }

    // Decision
    $engine = $score > 0 ? 'tesseract' : 'textract';

    if (empty($reasons)) {
        $reasons[] = 'default_engine';
    }

    Log::info('DocumentOcrRouter: recommendation', [
        'engine' => $engine,
        'score' => $score,
        'reasons' => $reasons,
        'croatian_score' => $textAnalysis['croatian_score'],
        'avg_confidence' => $blockAnalysis['avg_confidence'],
    ]);

    return [
        'engine' => $engine,
        'reasons' => $reasons,
        'confidence' => $this->calculateDecisionConfidence($textAnalysis, $blockAnalysis),
        'analysis' => array_merge($textAnalysis, $blockAnalysis),
        'routing_score' => round($score, 2),
    ];
}
```

**Key change:** Very low confidence (`< 0.70`) gives `+3.0`, structured content only gives `-1.0`. So low confidence with tables: `3.0 - 1.0 = 2.0` → still routes to tesseract. High confidence with tables: `-2.0 - 1.0 = -3.0` → textract.

**Step 4: Run tests**
```bash
php artisan test --filter=DocumentOcrRouterTest
```
Expected: PASS

**Step 5: Commit**
```bash
git add app/Services/Ocr/DocumentOcrRouter.php tests/Unit/Services/Ocr/DocumentOcrRouterTest.php
git commit -m "fix: rewrite OCR routing as weighted scoring to prevent rule contradiction"
```

---

## Task 4: Add Retry Wrapper for Pipeline Steps

**Files:**
- Create: `app/Pipelines/Concerns/RetryableStep.php`
- Modify: `app/Pipelines/Textract/UploadInputToS3Step.php`
- Modify: `app/Pipelines/Textract/StartAnalysisStep.php`
- Modify: `app/Pipelines/Textract/WaitAndFetchStep.php`
- Modify: `app/Pipelines/Textract/UploadOutputStep.php`
- Test: `tests/Unit/Pipelines/Concerns/RetryableStepTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Pipelines\Concerns;

use App\Pipelines\Concerns\RetryableStep;
use Tests\TestCase;

class RetryableStepTest extends TestCase
{
    /** @test */
    public function it_retries_on_transient_failure_and_succeeds(): void
    {
        $attempts = 0;
        $step = new class {
            use RetryableStep;
            public int $maxRetries = 3;
            public int $retryDelayMs = 0; // no delay in tests
        };

        $result = $step->withRetry(function () use (&$attempts) {
            $attempts++;
            if ($attempts < 3) {
                throw new \RuntimeException('Transient S3 error');
            }
            return 'success';
        }, 'test_operation');

        $this->assertEquals('success', $result);
        $this->assertEquals(3, $attempts);
    }

    /** @test */
    public function it_throws_after_max_retries_exceeded(): void
    {
        $step = new class {
            use RetryableStep;
            public int $maxRetries = 2;
            public int $retryDelayMs = 0;
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Persistent failure');

        $step->withRetry(function () {
            throw new \RuntimeException('Persistent failure');
        }, 'test_operation');
    }
}
```

**Step 2: Implement the trait**

```php
<?php

namespace App\Pipelines\Concerns;

use Illuminate\Support\Facades\Log;

trait RetryableStep
{
    public int $maxRetries = 3;
    public int $retryDelayMs = 1000;

    /**
     * Execute a callable with retry logic and exponential backoff.
     *
     * @throws \Throwable After exhausting all retries
     */
    protected function withRetry(callable $operation, string $operationName): mixed
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                return $operation();
            } catch (\Throwable $e) {
                $lastException = $e;

                if ($attempt >= $this->maxRetries) {
                    Log::error("RetryableStep: {$operationName} failed after {$attempt} attempts", [
                        'error' => $e->getMessage(),
                        'step' => static::class,
                    ]);
                    break;
                }

                $delayMs = $this->retryDelayMs * (2 ** ($attempt - 1)); // Exponential backoff
                Log::warning("RetryableStep: {$operationName} attempt {$attempt} failed, retrying in {$delayMs}ms", [
                    'error' => $e->getMessage(),
                    'step' => static::class,
                ]);

                if ($delayMs > 0) {
                    usleep($delayMs * 1000);
                }
            }
        }

        throw $lastException;
    }
}
```

**Step 3: Apply to S3 upload steps**

In `UploadInputToS3Step.php`:
```php
use App\Pipelines\Concerns\RetryableStep;

class UploadInputToS3Step
{
    use RetryableStep;

    public function handle(array $payload, Closure $next): mixed
    {
        $payload['s3Key'] = $this->withRetry(
            fn () => UploadInputToS3::run($payload['localPath'], $payload['driveFileId']),
            's3_upload_input'
        );
        $payload['job']->update(['s3_key' => $payload['s3Key'], 'status' => 'started']);
        return $next($payload);
    }
}
```

Apply similarly to `StartAnalysisStep`, `WaitAndFetchStep`, `UploadOutputStep`.

**Step 4: Run tests**
```bash
php artisan test --filter=RetryableStepTest
```

**Step 5: Commit**
```bash
git add app/Pipelines/Concerns/RetryableStep.php app/Pipelines/Textract/*.php tests/
git commit -m "feat: add RetryableStep trait with exponential backoff to pipeline steps"
```

---

## Task 5: Harden `TesseractOcrStep` to Use `OcrmypdfService`

**Files:**
- Modify: `app/Pipelines/Textract/TesseractOcrStep.php`
- Modify: `app/Services/Ocr/OcrmypdfService.php` (if needed)
- Test: `tests/Unit/Pipelines/Textract/TesseractOcrStepTest.php`

**Step 1: Write failing test**

```php
/** @test */
public function it_uses_ocrmypdf_when_routing_recommends_tesseract(): void
{
    $router = Mockery::mock(DocumentOcrRouter::class);
    $router->shouldReceive('recommend')->andReturn([
        'engine' => 'tesseract',
        'reasons' => ['croatian_content:0.8'],
        'confidence' => 0.8,
        'analysis' => [],
    ]);

    $ocrmypdf = Mockery::mock(OcrmypdfService::class);
    $ocrmypdf->shouldReceive('process')
        ->once()
        ->andReturn([
            'status' => 'success',
            'text' => 'Presuda broj Kž-123/2024',
            'output_pdf' => '/tmp/output.pdf',
            'page_count' => 3,
            'engine' => 'ocrmypdf',
            'error' => null,
            'exit_code' => 0,
        ]);

    // The old TesseractOcrService should NOT be called
    $tesseract = Mockery::mock(TesseractOcrService::class);
    $tesseract->shouldNotReceive('extractText');

    $comparator = Mockery::mock(OcrQualityComparator::class);
    $comparator->shouldReceive('compare')->andReturn([
        'winner' => 'tesseract',
        'improvement_percent' => 15.0,
        'textract_score' => 0.6,
        'tesseract_score' => 0.75,
        'reasons' => ['better_diacritics'],
    ]);

    $step = new TesseractOcrStep($router, $tesseract, $comparator, $ocrmypdf);
    // ... test pipeline payload
}
```

**Step 2: Update `TesseractOcrStep` constructor to accept `OcrmypdfService`**

```php
public function __construct(
    private DocumentOcrRouter $router,
    private TesseractOcrService $tesseract,  // kept as fallback
    private OcrQualityComparator $comparator,
    private ?OcrmypdfService $ocrmypdf = null,  // NEW — primary Tesseract path
) {}
```

In `handle()`, when `$routing['engine'] === 'tesseract'`:

```php
// Prefer ocrmypdf if available
if ($this->ocrmypdf?->isAvailable() && $localPath !== null) {
    $ocrResult = $this->ocrmypdf->process($localPath, [
        'force_ocr' => true,
        'deskew' => true,
    ]);

    if ($ocrResult['status'] === 'success' && !empty($ocrResult['text'])) {
        $comparison = $this->comparator->compare($textractText, $ocrResult['text']);
        $payload['ocr_comparison'] = $comparison;
        $payload['ocr_engine_used'] = $comparison['winner'] === 'tesseract' ? 'ocrmypdf' : 'textract';
        $payload['finalText'] = $comparison['winner'] === 'tesseract' ? $ocrResult['text'] : $textractText;
        
        // If ocrmypdf produced a better PDF, use it for reconstruction
        if ($comparison['winner'] === 'tesseract' && is_file($ocrResult['output_pdf'])) {
            $payload['ocrmypdf_output_pdf'] = $ocrResult['output_pdf'];
        }
        
        return $next($payload);
    }
    
    Log::warning('OcrmypdfService failed, falling back to raw Tesseract', [...]);
}

// Fallback to raw TesseractOcrService (legacy path)
$tesseractResult = $this->tesseract->extractText($localPath);
// ... existing logic
```

**Step 3: Register in service provider**

```php
$this->app->when(TesseractOcrStep::class)
    ->needs(OcrmypdfService::class)
    ->give(fn () => new OcrmypdfService());
```

**Step 4: Run tests**
```bash
php artisan test --filter=TesseractOcrStepTest
```

**Step 5: Commit**
```bash
git add app/Pipelines/Textract/TesseractOcrStep.php app/Providers/
git commit -m "feat: integrate OcrmypdfService as primary engine in TesseractOcrStep"
```

---

## Task 6: Fix `OcrQualityComparator` Scoring Saturation

**Files:**
- Modify: `app/Services/Ocr/OcrQualityComparator.php`
- Test: `tests/Unit/Services/Ocr/OcrQualityComparatorTest.php`

**Problem:** `scoreDiacritics()` uses `($count / $len) * 20`. Croatian text at 5% diacritics: `0.05 * 20 = 1.0`. Both engines produce similar diacritic density, so this metric can't differentiate them.

**Step 1: Write failing test**

```php
/** @test */
public function diacritics_score_differentiates_good_vs_bad_diacritics(): void
{
    $comparator = new OcrQualityComparator();

    // Textract output: diacritics mangled (č→c, š→s, ž→z)
    $badDiacritics = 'Opcinski sud u Zagrebu donosi presudu u predmetu Kz-123/2024';
    // Tesseract output: proper Croatian diacritics
    $goodDiacritics = 'Općinski sud u Zagrebu donosi presudu u predmetu Kž-123/2024';

    $badScore = $comparator->scoreDiacritics($badDiacritics);
    $goodScore = $comparator->scoreDiacritics($goodDiacritics);

    $this->assertGreaterThan($badScore, $goodScore,
        'Text with proper Croatian diacritics must score higher');
    
    // Neither should saturate at 1.0
    $this->assertLessThan(1.0, $goodScore, 'Good diacritics should not saturate at 1.0');
}
```

**Step 2: Fix the multiplier**

Replace the magic `* 20` with a calibrated sigmoid-like curve:

```php
public function scoreDiacritics(string $text): float
{
    $len = mb_strlen($text, 'UTF-8');
    if ($len === 0) {
        return 0.0;
    }

    $diacriticCount = 0;
    foreach (self::CROATIAN_DIACRITICS as $char) {
        $diacriticCount += mb_substr_count($text, $char);
    }

    $density = $diacriticCount / $len;

    // Croatian text typically has 3-8% diacritics.
    // Score should differentiate within this range, not saturate.
    // Using a logistic curve centered at 5% (expected density):
    //   0% → 0.0, 3% → 0.4, 5% → 0.6, 8% → 0.8, 12%+ → ~0.95
    $expectedDensity = 0.05;
    $steepness = 40.0;

    return 1.0 / (1.0 + exp(-$steepness * ($density - $expectedDensity * 0.5)));
}
```

**Step 3: Run tests**
```bash
php artisan test --filter=OcrQualityComparatorTest
```

**Step 4: Commit**
```bash
git add app/Services/Ocr/OcrQualityComparator.php tests/
git commit -m "fix: replace saturating diacritic score with logistic curve"
```

---

## Task 7: Add Health Check / Availability Guard

**Files:**
- Create: `app/Services/Ocr/OcrAvailabilityGuard.php`
- Modify: `app/Pipelines/Textract/TesseractOcrStep.php`
- Test: `tests/Unit/Services/Ocr/OcrAvailabilityGuardTest.php`

**Purpose:** Before routing to Tesseract/ocrmypdf, verify the binary exists and Croatian language pack is installed. Cache the result for 5 minutes to avoid repeated process spawns.

**Step 1: Write test**

```php
/** @test */
public function guard_returns_unavailable_when_binary_missing(): void
{
    config(['ocr.ocrmypdf.binary' => '/nonexistent/ocrmypdf']);
    $guard = new OcrAvailabilityGuard();

    $status = $guard->check();
    $this->assertFalse($status['ocrmypdf_available']);
}
```

**Step 2: Implement**

```php
<?php

namespace App\Services\Ocr;

use Illuminate\Support\Facades\Cache;

class OcrAvailabilityGuard
{
    private const CACHE_KEY = 'ocr_availability_status';
    private const CACHE_TTL = 300; // 5 minutes

    public function check(bool $fresh = false): array
    {
        if (!$fresh) {
            $cached = Cache::get(self::CACHE_KEY);
            if ($cached !== null) {
                return $cached;
            }
        }

        $ocrmypdf = app(OcrmypdfService::class);
        $tesseract = app(TesseractOcrService::class);

        $status = [
            'ocrmypdf_available' => $ocrmypdf->isAvailable(),
            'ocrmypdf_version' => $ocrmypdf->getVersion(),
            'tesseract_available' => $tesseract->isAvailable(),
            'croatian_language' => $ocrmypdf->hasCroatianLanguage(),
            'checked_at' => now()->toIso8601String(),
        ];

        Cache::put(self::CACHE_KEY, $status, self::CACHE_TTL);

        return $status;
    }

    /**
     * Can we use the local OCR path (ocrmypdf or tesseract)?
     */
    public function canUseLocalOcr(): bool
    {
        $status = $this->check();
        return ($status['ocrmypdf_available'] || $status['tesseract_available'])
            && $status['croatian_language'];
    }
}
```

**Step 3: Wire into TesseractOcrStep**

At the top of `handle()`:
```php
$guard = app(OcrAvailabilityGuard::class);
if (!$guard->canUseLocalOcr()) {
    Log::info('TesseractOcrStep: local OCR unavailable, keeping Textract', [...]);
    $payload['ocr_engine_used'] = 'textract';
    $payload['finalText'] = $textractText;
    $payload['ocr_skipped_reason'] = 'local_ocr_unavailable';
    return $next($payload);
}
```

**Step 4: Commit**
```bash
git add app/Services/Ocr/OcrAvailabilityGuard.php app/Pipelines/Textract/TesseractOcrStep.php tests/
git commit -m "feat: add OCR availability guard with caching"
```

---

## Task 8: Add Pipeline Timeout Enforcement

**Files:**
- Modify: `app/Pipelines/Textract/WaitAndFetchStep.php`
- Modify: `app/Services/TextractService.php` (the `waitAndFetchDocumentAnalysis` method)
- Modify: `app/Actions/Textract/ProcessDrivePdf.php`
- Test: `tests/Unit/Pipelines/Textract/WaitAndFetchStepTest.php`

**Purpose:** The Textract polling loop in `WaitAndFetchTextract` can hang indefinitely. Add configurable timeout.

**Step 1: Add timeout to WaitAndFetchStep**

```php
use App\Pipelines\Concerns\RetryableStep;

class WaitAndFetchStep
{
    use RetryableStep;

    public function handle(array $payload, Closure $next): mixed
    {
        $maxWaitSeconds = (int) config('textract.max_wait_seconds', 600);
        $startTime = time();

        $payload['blocks'] = $this->withRetry(function () use ($payload, $maxWaitSeconds, $startTime) {
            $elapsed = time() - $startTime;
            if ($elapsed >= $maxWaitSeconds) {
                throw new \RuntimeException(
                    "Textract analysis timed out after {$elapsed}s (limit: {$maxWaitSeconds}s)"
                );
            }
            return WaitAndFetchTextract::run($payload['jobId']);
        }, 'textract_wait_and_fetch');

        return $next($payload);
    }
}
```

**Step 2: Add overall pipeline timeout in ProcessDrivePdf**

```php
// In handle(), wrap the pipeline with a max execution time guard
$maxPipelineSeconds = (int) config('textract.max_pipeline_seconds', 1800);
$pipelineStart = time();

// Add to payload so steps can check elapsed time
$payload['pipeline_start'] = $pipelineStart;
$payload['pipeline_timeout'] = $maxPipelineSeconds;
```

**Step 3: Commit**
```bash
git add app/Pipelines/Textract/WaitAndFetchStep.php app/Actions/Textract/ProcessDrivePdf.php
git commit -m "feat: add timeout enforcement to Textract polling and pipeline"
```

---

## Task 9: Add Re-OCR Path When Quality Below Threshold

**Files:**
- Modify: `app/Pipelines/Textract/CheckOcrQualityStep.php`
- Modify: `app/Pipelines/Textract/TesseractOcrStep.php`
- Test: `tests/Unit/Pipelines/Textract/CheckOcrQualityStepTest.php`

**Purpose:** Currently `CheckOcrQualityStep` sets `needsReview = true` but nothing acts on it. Make `TesseractOcrStep` check this flag and force a Tesseract/ocrmypdf pass regardless of routing score.

**Step 1: In `TesseractOcrStep::handle()`, add quality-gated override**

```php
// After routing recommendation, check if quality step flagged this document
$needsReview = $payload['needsReview'] ?? false;
$qualityMetrics = $payload['qualityMetrics'] ?? [];

if ($needsReview && $routing['engine'] !== 'tesseract') {
    // Quality is bad enough to warrant re-OCR even if router said textract
    $forceReOcr = (bool) config('ocr.force_reocr_on_review', true);
    if ($forceReOcr && $localPath !== null) {
        Log::info('TesseractOcrStep: quality-gated re-OCR override', [
            'driveFileId' => $driveFileId,
            'reviewReasons' => $payload['reviewReasons'] ?? [],
            'original_recommendation' => $routing['engine'],
        ]);
        $routing['engine'] = 'tesseract';
        $routing['reasons'][] = 'quality_gated_override';
        $payload['ocr_routing'] = $routing;
    }
}
```

**Step 2: Commit**
```bash
git add app/Pipelines/Textract/TesseractOcrStep.php app/Pipelines/Textract/CheckOcrQualityStep.php tests/
git commit -m "feat: quality-gated re-OCR when CheckOcrQualityStep flags document"
```

---

## Task 10: Integration Tests for Full OCR Routing Flow

**Files:**
- Create: `tests/Integration/OcrRoutingIntegrationTest.php`
- Create: `tests/fixtures/ocr/sample-croatian-blocks.json`
- Create: `tests/fixtures/ocr/sample-english-blocks.json`

**Purpose:** End-to-end test of the routing decision → OCR execution → quality comparison → final text selection flow, using fixture data.

**Step 1: Create fixture files**

Generate minimal Textract-style block JSON for Croatian legal text (low confidence) and English form text (high confidence with tables).

**Step 2: Write integration tests**

```php
/** @test */
public function croatian_legal_document_with_low_confidence_routes_through_ocrmypdf(): void
{
    // Load fixture
    $blocks = json_decode(file_get_contents(
        base_path('tests/fixtures/ocr/sample-croatian-blocks.json')
    ), true);

    // Mock only external services, test real routing + comparison logic
    $router = app(DocumentOcrRouter::class);
    $comparator = app(OcrQualityComparator::class);
    $qualityAnalyzer = app(OcrQualityAnalyzer::class);

    // Step 1: Quality check
    $quality = $qualityAnalyzer->analyzeFromBlocks($blocks);
    $this->assertLessThan(0.80, $quality['confidence']);

    // Step 2: Routing
    $textractText = collect($blocks)
        ->where('BlockType', 'LINE')
        ->pluck('Text')
        ->implode("\n");
    $routing = $router->recommend($textractText, $blocks);
    $this->assertEquals('tesseract', $routing['engine']);

    // Step 3: Quality comparison (simulated Tesseract output)
    $tesseractText = str_replace(
        ['c', 's', 'z'],
        ['č', 'š', 'ž'],
        $textractText
    ); // Simulate better diacritics
    $comparison = $comparator->compare($textractText, $tesseractText);
    $this->assertEquals('tesseract', $comparison['winner']);
}
```

**Step 3: Commit**
```bash
git add tests/Integration/ tests/fixtures/
git commit -m "test: add integration tests for OCR routing flow"
```

---

## Task 11: Config & Documentation Cleanup

**Files:**
- Modify: `config/ocr.php`
- Create: `docs/ocr-pipeline.md`
- Modify: `.env.example`

**Step 1: Consolidate all OCR config into `config/ocr.php`**

Ensure all thresholds, binary paths, and feature flags are in one place:

```php
return [
    'default_engine' => env('OCR_DEFAULT_ENGINE', 'auto'),

    'routing' => [
        'weights' => [
            'croatian_text_threshold' => 0.6,
            'structured_content_threshold' => 0.3,
            'textract_confidence_threshold' => 0.85,
            'fallback_confidence_threshold' => 0.70,
        ],
    ],

    'quality' => [
        'min_confidence' => 0.82,
        'min_coverage' => 0.75,
        'max_low_confidence_pages' => 3,
        'min_improvement_percent' => 5.0,
    ],

    'ocrmypdf' => [
        'binary' => env('OCRMYPDF_BINARY', 'ocrmypdf'),
        'languages' => env('OCRMYPDF_LANGUAGES', 'hrv+eng'),
        'timeout' => 600,
        'deskew' => true,
        'clean' => false,
        'jobs' => 2,
        'max_pages' => 0,
    ],

    'tesseract' => [
        'binary' => env('TESSERACT_BINARY', '/usr/bin/tesseract'),
        'convert_binary' => env('IMAGEMAGICK_CONVERT', '/usr/bin/convert'),
        'languages' => env('TESSERACT_LANGUAGES', 'hrv+eng'),
        'dpi' => 300,
        'psm' => 3,
        'oem' => 1,
        'page_timeout' => 120,
        'max_pages' => 0,
        'preserve_layout' => true,
    ],

    'force_reocr_on_review' => env('OCR_FORCE_REOCR_ON_REVIEW', true),
];
```

**Step 2: Add .env.example entries**

```env
# OCR Configuration
OCR_DEFAULT_ENGINE=auto
OCRMYPDF_BINARY=ocrmypdf
OCRMYPDF_LANGUAGES=hrv+eng
TESSERACT_BINARY=/usr/bin/tesseract
OCR_FORCE_REOCR_ON_REVIEW=true
```

**Step 3: Write architecture doc**

Document the full flow:
```
PDF → Download → S3 Upload → Textract Analysis → Save Results
  → Collect Lines (OcrDocument) → Check Quality → OCR Routing Decision
  → [If Tesseract] ocrmypdf with deskew → Quality Comparison → Pick Winner
  → Metadata Extraction → PDF Reconstruction → S3 Upload → Persist
```

**Step 4: Commit**
```bash
git add config/ocr.php docs/ocr-pipeline.md .env.example
git commit -m "docs: consolidate OCR config and document pipeline architecture"
```

---

## Dependency Checklist

Before starting, ensure these are installed on dev/staging:

```bash
# Install ocrmypdf and Croatian language pack
pip install ocrmypdf
sudo apt-get install tesseract-ocr tesseract-ocr-hrv ghostscript

# Verify
ocrmypdf --version
tesseract --list-langs | grep hrv
```

## Risk Assessment

| Risk | Mitigation |
|------|------------|
| ocrmypdf not installed on production | Availability guard (Task 7) falls back to raw Tesseract, then Textract |
| Routing score weights need tuning | All weights are config-driven, can adjust without code changes |
| ocrmypdf slower than raw Tesseract | Configurable timeout (Task 8), parallel jobs flag |
| Existing tests break from routing change | Task 3 tests cover edge cases, old behavior was buggy anyway |
| S3 retries cause duplicate uploads | S3 PUT is idempotent for same key |
