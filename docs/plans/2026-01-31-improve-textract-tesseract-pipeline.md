# Improved Textract + Tesseract OCR Pipeline Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build an intelligent dual-OCR pipeline that uses Tesseract as primary OCR for Croatian legal documents (superior diacritic handling) and AWS Textract for structured document analysis (tables, forms, signatures), with automatic document type detection to route documents to the optimal engine, preserving original document formatting.

**Architecture:** A new `DocumentOcrRouter` service detects document characteristics (language, structure, scan quality) and routes to the optimal OCR engine. Tesseract runs locally with Croatian language packs (`hrv`) at high DPI for text-heavy legal documents. Textract handles structured documents needing table/form extraction. A new `TesseractOcrStep` pipeline step slots into the existing `ProcessDrivePdf` pipeline alongside quality-based fallback logic. Original text layout is preserved through page-aware extraction with positional metadata.

**Tech Stack:** PHP 8.2+, Laravel Pipeline, Tesseract OCR (via `thiagoalessio/tesseract_ocr` PHP wrapper), ImageMagick, AWS Textract SDK, existing `OcrQualityAnalyzer`

---

## Task 1: Add Tesseract PHP Package and Configuration

**Files:**
- Modify: `composer.json`
- Modify: `config/textract.php`
- Create: `config/ocr.php`

**Step 1: Add Tesseract PHP wrapper package**

```bash
composer require thiagoalessio/tesseract_ocr
```

**Step 2: Create OCR configuration file**

Create `config/ocr.php`:

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OCR Engine Selection
    |--------------------------------------------------------------------------
    |
    | Controls which OCR engine is preferred for different document types.
    | 'auto' = smart routing based on document analysis
    | 'tesseract' = always use Tesseract
    | 'textract' = always use AWS Textract
    |
    */
    'default_engine' => env('OCR_DEFAULT_ENGINE', 'auto'),

    /*
    |--------------------------------------------------------------------------
    | Tesseract Configuration
    |--------------------------------------------------------------------------
    */
    'tesseract' => [
        // Path to tesseract binary
        'binary' => env('TESSERACT_BINARY', '/usr/bin/tesseract'),

        // Languages for OCR (Croatian primary, English fallback)
        'languages' => env('TESSERACT_LANGUAGES', 'hrv+eng'),

        // DPI for PDF-to-image conversion
        'dpi' => (int) env('TESSERACT_DPI', 300),

        // Page segmentation mode (PSM)
        // 1 = Automatic with OSD, 3 = Fully automatic, 6 = Uniform block of text
        'psm' => (int) env('TESSERACT_PSM', 3),

        // OCR Engine Mode (OEM)
        // 1 = LSTM only, 3 = Default (LSTM + legacy)
        'oem' => (int) env('TESSERACT_OEM', 1),

        // Timeout per page in seconds
        'page_timeout' => (int) env('TESSERACT_PAGE_TIMEOUT', 120),

        // Maximum pages to process (0 = unlimited)
        'max_pages' => (int) env('TESSERACT_MAX_PAGES', 0),

        // Preserve original layout formatting
        'preserve_layout' => env('TESSERACT_PRESERVE_LAYOUT', true),

        // ImageMagick convert binary path
        'convert_binary' => env('IMAGEMAGICK_CONVERT', '/usr/bin/convert'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Document Type Detection
    |--------------------------------------------------------------------------
    |
    | Thresholds for automatic document routing.
    |
    */
    'routing' => [
        // Confidence threshold to prefer Tesseract for Croatian text
        'croatian_text_threshold' => (float) env('OCR_CROATIAN_THRESHOLD', 0.6),

        // If document has tables/forms, prefer Textract above this ratio
        'structured_content_threshold' => (float) env('OCR_STRUCTURED_THRESHOLD', 0.3),

        // Minimum Textract confidence to skip Tesseract re-OCR
        'textract_confidence_threshold' => (float) env('OCR_TEXTRACT_MIN_CONFIDENCE', 0.85),

        // If Textract confidence is below this, always try Tesseract
        'fallback_confidence_threshold' => (float) env('OCR_FALLBACK_THRESHOLD', 0.70),
    ],

    /*
    |--------------------------------------------------------------------------
    | Quality Comparison
    |--------------------------------------------------------------------------
    |
    | When both engines run, these settings control which result wins.
    |
    */
    'quality' => [
        // Minimum improvement (%) for Tesseract to override Textract result
        'min_improvement_percent' => (float) env('OCR_MIN_IMPROVEMENT', 5.0),

        // Croatian diacritic characters to check for quality
        'croatian_diacritics' => ['č', 'ć', 'ž', 'š', 'đ', 'Č', 'Ć', 'Ž', 'Š', 'Đ'],
    ],
];
```

**Step 3: Commit**

```bash
git add composer.json composer.lock config/ocr.php
git commit -m "feat: add Tesseract OCR package and configuration

- Add thiagoalessio/tesseract_ocr PHP wrapper
- Create config/ocr.php with engine routing, Tesseract settings
- Support auto/tesseract/textract engine selection
- Configure Croatian + English language support"
```

---

## Task 2: Create DocumentOcrRouter Service (with Tests)

**Files:**
- Create: `tests/Unit/Services/Ocr/DocumentOcrRouterTest.php`
- Create: `app/Services/Ocr/DocumentOcrRouter.php`

**Step 1: Write failing tests for DocumentOcrRouter**

Create `tests/Unit/Services/Ocr/DocumentOcrRouterTest.php`:

```php
<?php

namespace Tests\Unit\Services\Ocr;

use App\Services\Ocr\DocumentOcrRouter;
use Tests\TestCase;

class DocumentOcrRouterTest extends TestCase
{
    private DocumentOcrRouter $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new DocumentOcrRouter();
    }

    /** @test */
    public function it_detects_croatian_text_content(): void
    {
        $text = 'Republika Hrvatska, Općinski sud u Zagrebu donosi sljedeću presudu u predmetu broj Povrv-1234/2024';
        $analysis = $this->router->analyzeDocument($text);

        $this->assertGreaterThan(0.5, $analysis['croatian_score']);
        $this->assertTrue($analysis['has_croatian_content']);
    }

    /** @test */
    public function it_detects_non_croatian_text(): void
    {
        $text = 'This is an English legal document about contract law and liability terms.';
        $analysis = $this->router->analyzeDocument($text);

        $this->assertLessThan(0.3, $analysis['croatian_score']);
        $this->assertFalse($analysis['has_croatian_content']);
    }

    /** @test */
    public function it_detects_structured_content_with_tables(): void
    {
        $blocks = [
            ['BlockType' => 'TABLE', 'Confidence' => 95],
            ['BlockType' => 'CELL', 'Confidence' => 90],
            ['BlockType' => 'CELL', 'Confidence' => 88],
            ['BlockType' => 'WORD', 'Confidence' => 92],
            ['BlockType' => 'WORD', 'Confidence' => 91],
            ['BlockType' => 'LINE', 'Confidence' => 90],
        ];
        $analysis = $this->router->analyzeTextractBlocks($blocks);

        $this->assertTrue($analysis['has_structured_content']);
        $this->assertGreaterThan(0, $analysis['table_count']);
    }

    /** @test */
    public function it_routes_croatian_legal_text_to_tesseract(): void
    {
        $text = 'Presuda Općinskog suda u Zagrebu. Tužitelj podnosi žalbu protiv rješenja o odbijanju tužbenog zahtjeva.';
        $blocks = [
            ['BlockType' => 'WORD', 'Confidence' => 75],
            ['BlockType' => 'WORD', 'Confidence' => 70],
        ];

        $recommendation = $this->router->recommend($text, $blocks);

        $this->assertEquals('tesseract', $recommendation['engine']);
        $this->assertNotEmpty($recommendation['reasons']);
    }

    /** @test */
    public function it_routes_structured_documents_to_textract(): void
    {
        $text = 'Invoice table data with numbers';
        $blocks = [
            ['BlockType' => 'TABLE', 'Confidence' => 95],
            ['BlockType' => 'TABLE', 'Confidence' => 93],
            ['BlockType' => 'CELL', 'Confidence' => 94],
            ['BlockType' => 'CELL', 'Confidence' => 92],
            ['BlockType' => 'WORD', 'Confidence' => 96],
            ['BlockType' => 'WORD', 'Confidence' => 95],
        ];

        $recommendation = $this->router->recommend($text, $blocks);

        $this->assertEquals('textract', $recommendation['engine']);
    }

    /** @test */
    public function it_respects_forced_engine_config(): void
    {
        config(['ocr.default_engine' => 'tesseract']);
        $recommendation = $this->router->recommend('any text', []);

        $this->assertEquals('tesseract', $recommendation['engine']);
        $this->assertContains('forced_by_config', $recommendation['reasons']);
    }

    /** @test */
    public function it_detects_diacritic_density(): void
    {
        $textWithDiacritics = 'čćžšđ ČĆŽŠĐs rješenje općinski županijski';
        $textWithout = 'resenje opcinski zupanijski';

        $scorWith = $this->router->calculateDiacriticDensity($textWithDiacritics);
        $scoreWithout = $this->router->calculateDiacriticDensity($textWithout);

        $this->assertGreaterThan($scoreWithout, $scorWith);
    }

    /** @test */
    public function it_recommends_tesseract_for_low_confidence_croatian(): void
    {
        $text = 'Presuda u predmetu broj P-1234/2024. Sud je donio odluku o odbijanju žalbe.';
        $blocks = [
            ['BlockType' => 'WORD', 'Confidence' => 60],
            ['BlockType' => 'WORD', 'Confidence' => 55],
            ['BlockType' => 'WORD', 'Confidence' => 65],
        ];

        $recommendation = $this->router->recommend($text, $blocks);

        $this->assertEquals('tesseract', $recommendation['engine']);
    }

    /** @test */
    public function it_keeps_textract_for_high_confidence_results(): void
    {
        $text = 'Simple legal text with adequate quality.';
        $blocks = [
            ['BlockType' => 'WORD', 'Confidence' => 98],
            ['BlockType' => 'WORD', 'Confidence' => 97],
            ['BlockType' => 'WORD', 'Confidence' => 99],
        ];

        $recommendation = $this->router->recommend($text, $blocks);

        $this->assertEquals('textract', $recommendation['engine']);
    }
}
```

**Step 2: Run tests to verify they fail**

```bash
./scripts/run-focused-tests.sh DocumentOcrRouterTest
```

Expected: FAIL (class not found)

**Step 3: Implement DocumentOcrRouter**

Create `app/Services/Ocr/DocumentOcrRouter.php`:

```php
<?php

namespace App\Services\Ocr;

use Illuminate\Support\Facades\Log;

/**
 * DocumentOcrRouter
 *
 * Analyzes document characteristics and recommends the optimal OCR engine.
 * Routes Croatian legal text to Tesseract (better diacritic handling)
 * and structured documents (tables, forms) to AWS Textract.
 */
class DocumentOcrRouter
{
    /** Croatian-specific words and patterns for detection */
    private const CROATIAN_INDICATORS = [
        // Legal terms
        'presuda', 'rješenje', 'zahtjev', 'žalba', 'optužnica', 'ugovor',
        'punomoć', 'izjava', 'potvrda', 'zakon', 'pravilnik', 'tužitelj',
        'tuženik', 'odvjetnik', 'sud', 'sudac', 'rasprava', 'spis',
        'predmet', 'članak', 'stavak', 'točka', 'alineja', 'odluka',
        // Common Croatian words
        'republika', 'hrvatska', 'općinski', 'županijski', 'vrhovni',
        'trgovački', 'upravni', 'ustavni', 'kazneni', 'građanski',
        'narodne', 'novine', 'objavljen', 'stupiti', 'snagu',
    ];

    /** Croatian diacritic characters */
    private const CROATIAN_DIACRITICS = ['č', 'ć', 'ž', 'š', 'đ', 'Č', 'Ć', 'Ž', 'Š', 'Đ'];

    /**
     * Recommend which OCR engine to use.
     *
     * @param string $text Extracted text (from initial Textract pass or pdftotext)
     * @param array $blocks Textract response blocks (if available)
     * @return array{engine: string, reasons: array, confidence: float, analysis: array}
     */
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
        $blockAnalysis = ! empty($blocks) ? $this->analyzeTextractBlocks($blocks) : [
            'has_structured_content' => false,
            'table_count' => 0,
            'form_count' => 0,
            'avg_confidence' => 0.0,
        ];

        $reasons = [];
        $engine = 'textract'; // Default

        $routingConfig = config('ocr.routing', []);
        $croatianThreshold = (float) ($routingConfig['croatian_text_threshold'] ?? 0.6);
        $structuredThreshold = (float) ($routingConfig['structured_content_threshold'] ?? 0.3);
        $textractMinConfidence = (float) ($routingConfig['textract_confidence_threshold'] ?? 0.85);
        $fallbackThreshold = (float) ($routingConfig['fallback_confidence_threshold'] ?? 0.70);

        // Rule 1: If Textract confidence is very low, prefer Tesseract
        if ($blockAnalysis['avg_confidence'] > 0 && $blockAnalysis['avg_confidence'] < $fallbackThreshold) {
            $engine = 'tesseract';
            $reasons[] = sprintf('low_textract_confidence:%.2f', $blockAnalysis['avg_confidence']);
        }

        // Rule 2: Croatian content detected - prefer Tesseract for diacritics
        if ($textAnalysis['has_croatian_content'] && $textAnalysis['croatian_score'] >= $croatianThreshold) {
            // But only if Textract confidence isn't already excellent
            if ($blockAnalysis['avg_confidence'] < $textractMinConfidence) {
                $engine = 'tesseract';
                $reasons[] = sprintf('croatian_content:%.2f', $textAnalysis['croatian_score']);
            }
        }

        // Rule 3: Structured content (tables/forms) - prefer Textract
        if ($blockAnalysis['has_structured_content']) {
            $totalBlocks = max(1, count($blocks));
            $structuredRatio = ($blockAnalysis['table_count'] + $blockAnalysis['form_count']) / $totalBlocks;
            if ($structuredRatio >= $structuredThreshold) {
                $engine = 'textract';
                $reasons[] = sprintf('structured_content:%.2f', $structuredRatio);
            }
        }

        // Rule 4: High-confidence Textract results - keep Textract
        if ($blockAnalysis['avg_confidence'] >= $textractMinConfidence && empty($reasons)) {
            $engine = 'textract';
            $reasons[] = sprintf('high_textract_confidence:%.2f', $blockAnalysis['avg_confidence']);
        }

        if (empty($reasons)) {
            $reasons[] = 'default_engine';
        }

        Log::info('DocumentOcrRouter: recommendation', [
            'engine' => $engine,
            'reasons' => $reasons,
            'croatian_score' => $textAnalysis['croatian_score'],
            'avg_confidence' => $blockAnalysis['avg_confidence'],
        ]);

        return [
            'engine' => $engine,
            'reasons' => $reasons,
            'confidence' => $this->calculateDecisionConfidence($textAnalysis, $blockAnalysis),
            'analysis' => array_merge($textAnalysis, $blockAnalysis),
        ];
    }

    /**
     * Analyze document text for language and content characteristics.
     */
    public function analyzeDocument(string $text): array
    {
        $normalizedText = mb_strtolower(trim($text), 'UTF-8');
        $wordCount = count(preg_split('/\s+/u', $normalizedText, -1, PREG_SPLIT_NO_EMPTY));

        if ($wordCount === 0) {
            return [
                'croatian_score' => 0.0,
                'has_croatian_content' => false,
                'diacritic_density' => 0.0,
                'word_count' => 0,
                'indicator_matches' => 0,
            ];
        }

        // Count Croatian indicator word matches
        $indicatorMatches = 0;
        foreach (self::CROATIAN_INDICATORS as $indicator) {
            $indicatorMatches += mb_substr_count($normalizedText, $indicator);
        }

        // Calculate diacritic density
        $diacriticDensity = $this->calculateDiacriticDensity($text);

        // Croatian score: combination of indicator density and diacritic presence
        $indicatorDensity = min(1.0, $indicatorMatches / max(1, $wordCount) * 10);
        $croatianScore = ($indicatorDensity * 0.6) + ($diacriticDensity * 0.4);
        $croatianScore = min(1.0, $croatianScore);

        $threshold = (float) config('ocr.routing.croatian_text_threshold', 0.6);

        return [
            'croatian_score' => round($croatianScore, 4),
            'has_croatian_content' => $croatianScore >= $threshold,
            'diacritic_density' => round($diacriticDensity, 4),
            'word_count' => $wordCount,
            'indicator_matches' => $indicatorMatches,
        ];
    }

    /**
     * Analyze Textract blocks for structured content and confidence.
     */
    public function analyzeTextractBlocks(array $blocks): array
    {
        $tableCount = 0;
        $formCount = 0;
        $totalConfidence = 0.0;
        $wordCount = 0;

        foreach ($blocks as $block) {
            $type = $block['BlockType'] ?? '';
            $confidence = (float) ($block['Confidence'] ?? 0);

            match ($type) {
                'TABLE' => $tableCount++,
                'KEY_VALUE_SET' => $formCount++,
                'WORD' => (function () use (&$wordCount, &$totalConfidence, $confidence) {
                    $wordCount++;
                    $totalConfidence += $confidence / 100.0;
                })(),
                default => null,
            };
        }

        $avgConfidence = $wordCount > 0 ? $totalConfidence / $wordCount : 0.0;

        return [
            'has_structured_content' => ($tableCount + $formCount) > 0,
            'table_count' => $tableCount,
            'form_count' => $formCount,
            'avg_confidence' => round($avgConfidence, 4),
            'word_count' => $wordCount,
        ];
    }

    /**
     * Calculate density of Croatian diacritical characters in text.
     */
    public function calculateDiacriticDensity(string $text): float
    {
        $len = mb_strlen($text, 'UTF-8');
        if ($len === 0) {
            return 0.0;
        }

        $diacriticCount = 0;
        foreach (self::CROATIAN_DIACRITICS as $char) {
            $diacriticCount += mb_substr_count($text, $char);
        }

        // Normalize: expect roughly 5-10% diacritics in Croatian text
        return min(1.0, ($diacriticCount / $len) * 15);
    }

    /**
     * Calculate overall decision confidence.
     */
    private function calculateDecisionConfidence(array $textAnalysis, array $blockAnalysis): float
    {
        $signals = 0;
        $totalWeight = 0;

        if ($textAnalysis['croatian_score'] > 0) {
            $signals += $textAnalysis['croatian_score'] * 0.4;
            $totalWeight += 0.4;
        }

        if ($blockAnalysis['avg_confidence'] > 0) {
            $signals += $blockAnalysis['avg_confidence'] * 0.4;
            $totalWeight += 0.4;
        }

        if ($blockAnalysis['has_structured_content']) {
            $signals += 0.2;
            $totalWeight += 0.2;
        }

        return $totalWeight > 0 ? round($signals / $totalWeight, 4) : 0.5;
    }
}
```

**Step 4: Run tests to verify they pass**

```bash
./scripts/run-focused-tests.sh DocumentOcrRouterTest
```

Expected: All 9 tests PASS

**Step 5: Commit**

```bash
git add tests/Unit/Services/Ocr/DocumentOcrRouterTest.php app/Services/Ocr/DocumentOcrRouter.php
git commit -m "feat: add DocumentOcrRouter for intelligent OCR engine selection

- Detect Croatian legal content via keyword indicators and diacritic density
- Analyze Textract blocks for structured content (tables, forms)
- Route Croatian text to Tesseract, structured docs to Textract
- Support forced engine selection via config
- 9 unit tests covering all routing scenarios"
```

---

## Task 3: Create TesseractOcrService (with Tests)

**Files:**
- Create: `tests/Unit/Services/Ocr/TesseractOcrServiceTest.php`
- Create: `app/Services/Ocr/TesseractOcrService.php`

**Step 1: Write failing tests**

Create `tests/Unit/Services/Ocr/TesseractOcrServiceTest.php`:

```php
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
```

**Step 2: Run tests to verify they fail**

```bash
./scripts/run-focused-tests.sh TesseractOcrServiceTest
```

Expected: FAIL

**Step 3: Implement TesseractOcrService**

Create `app/Services/Ocr/TesseractOcrService.php`:

```php
<?php

namespace App\Services\Ocr;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * TesseractOcrService
 *
 * High-quality OCR extraction using Tesseract with Croatian language support.
 * Converts PDF pages to high-DPI images and processes them individually,
 * preserving original page structure and formatting.
 */
class TesseractOcrService
{
    private string $tesseractBinary;
    private string $convertBinary;
    private string $languages;
    private int $dpi;
    private int $psm;
    private int $oem;
    private int $pageTimeout;
    private int $maxPages;
    private bool $preserveLayout;

    public function __construct()
    {
        $this->tesseractBinary = (string) config('ocr.tesseract.binary', '/usr/bin/tesseract');
        $this->convertBinary = (string) config('ocr.tesseract.convert_binary', '/usr/bin/convert');
        $this->languages = (string) config('ocr.tesseract.languages', 'hrv+eng');
        $this->dpi = (int) config('ocr.tesseract.dpi', 300);
        $this->psm = (int) config('ocr.tesseract.psm', 3);
        $this->oem = (int) config('ocr.tesseract.oem', 1);
        $this->pageTimeout = (int) config('ocr.tesseract.page_timeout', 120);
        $this->maxPages = (int) config('ocr.tesseract.max_pages', 0);
        $this->preserveLayout = (bool) config('ocr.tesseract.preserve_layout', true);
    }

    /**
     * Extract text from a PDF file using Tesseract OCR.
     *
     * @param string $pdfPath Absolute path to PDF file
     * @return array{text: string, status: string, pages: array, page_count: int, engine: string}
     */
    public function extractText(string $pdfPath): array
    {
        if (! is_file($pdfPath)) {
            Log::warning('TesseractOcrService: PDF not found', ['path' => $pdfPath]);
            return [
                'text' => '',
                'status' => 'error',
                'pages' => [],
                'page_count' => 0,
                'engine' => 'tesseract',
                'error' => 'File not found',
            ];
        }

        $tmpDir = sys_get_temp_dir() . '/tesseract_' . uniqid();
        @mkdir($tmpDir, 0755, true);

        try {
            // Step 1: Convert PDF pages to TIFF images
            $imageFiles = $this->convertPdfToImages($pdfPath, $tmpDir);

            if (empty($imageFiles)) {
                Log::warning('TesseractOcrService: No images generated from PDF', ['path' => $pdfPath]);
                return [
                    'text' => '',
                    'status' => 'error',
                    'pages' => [],
                    'page_count' => 0,
                    'engine' => 'tesseract',
                    'error' => 'PDF to image conversion failed',
                ];
            }

            // Step 2: OCR each page
            $pages = [];
            $errors = [];
            foreach ($imageFiles as $pageNum => $imagePath) {
                if ($this->maxPages > 0 && $pageNum > $this->maxPages) {
                    break;
                }

                $pageText = $this->ocrSinglePage($imagePath, $pageNum);
                if ($pageText !== null) {
                    $pages[$pageNum] = $pageText;
                } else {
                    $errors[] = $pageNum;
                }
            }

            // Step 3: Combine pages preserving structure
            $fullText = $this->combinePages($pages, $this->preserveLayout);
            $pageMetadata = $this->buildPageMetadata($pages);

            $status = empty($errors) ? 'success' : (empty($pages) ? 'error' : 'partial');

            Log::info('TesseractOcrService: extraction complete', [
                'path' => $pdfPath,
                'pages_processed' => count($pages),
                'pages_failed' => count($errors),
                'text_length' => mb_strlen($fullText),
            ]);

            return [
                'text' => $fullText,
                'status' => $status,
                'pages' => $pageMetadata,
                'page_count' => count($pages),
                'engine' => 'tesseract',
                'failed_pages' => $errors,
            ];
        } finally {
            // Cleanup temp files
            $this->cleanupTempDir($tmpDir);
        }
    }

    /**
     * Convert PDF to per-page TIFF images using ImageMagick.
     *
     * @return array<int, string> Map of page number => image path
     */
    private function convertPdfToImages(string $pdfPath, string $tmpDir): array
    {
        $outputPattern = $tmpDir . '/page';

        $process = new Process([
            $this->convertBinary,
            '-density', (string) $this->dpi,
            '-depth', '8',
            '-strip',
            '-background', 'white',
            '-alpha', 'off',
            $pdfPath,
            $outputPattern . '.tif',
        ]);
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            Log::warning('TesseractOcrService: ImageMagick conversion failed', [
                'exit_code' => $process->getExitCode(),
                'error' => $process->getErrorOutput(),
            ]);
            return [];
        }

        // Collect generated images (multi-page PDFs produce page-0.tif, page-1.tif, etc.)
        $images = [];
        $multiPage = glob($tmpDir . '/page-*.tif');
        $singlePage = glob($tmpDir . '/page.tif');

        if (! empty($multiPage)) {
            sort($multiPage, SORT_NATURAL);
            foreach ($multiPage as $index => $path) {
                $images[$index + 1] = $path;
            }
        } elseif (! empty($singlePage)) {
            $images[1] = $singlePage[0];
        }

        return $images;
    }

    /**
     * Run Tesseract OCR on a single page image.
     */
    private function ocrSinglePage(string $imagePath, int $pageNum): ?string
    {
        $outputBase = $imagePath . '_ocr';

        $command = $this->buildCommand($imagePath, $outputBase);
        $process = new Process($command);
        $process->setTimeout($this->pageTimeout);
        $process->run();

        if (! $process->isSuccessful()) {
            Log::warning('TesseractOcrService: OCR failed for page', [
                'page' => $pageNum,
                'exit_code' => $process->getExitCode(),
                'error' => $process->getErrorOutput(),
            ]);
            return null;
        }

        $outputFile = $outputBase . '.txt';
        if (! is_file($outputFile)) {
            return null;
        }

        $text = file_get_contents($outputFile);
        @unlink($outputFile);

        return trim($text);
    }

    /**
     * Build the Tesseract command array.
     *
     * @return string[]
     */
    public function buildCommand(string $inputPath, string $outputBase): array
    {
        return [
            $this->tesseractBinary,
            $inputPath,
            $outputBase,
            '-l', $this->languages,
            '--psm', (string) $this->psm,
            '--oem', (string) $this->oem,
        ];
    }

    /**
     * Combine page texts, optionally preserving page structure.
     */
    public function combinePages(array $pages, bool $withSeparators = true): string
    {
        if (empty($pages)) {
            return '';
        }

        ksort($pages);

        if (! $withSeparators) {
            return implode("\n\n", $pages);
        }

        $parts = [];
        $isFirst = true;
        foreach ($pages as $pageNum => $text) {
            if (! $isFirst) {
                $parts[] = "\n\n--- Page {$pageNum} ---\n\n";
            }
            $parts[] = $text;
            $isFirst = false;
        }

        return implode('', $parts);
    }

    /**
     * Build per-page metadata for extracted text.
     *
     * @return array<int, array{page: int, char_count: int, word_count: int}>
     */
    public function buildPageMetadata(array $pages): array
    {
        $metadata = [];
        foreach ($pages as $pageNum => $text) {
            $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);
            $metadata[] = [
                'page' => $pageNum,
                'char_count' => mb_strlen($text, 'UTF-8'),
                'word_count' => count($words),
            ];
        }
        return $metadata;
    }

    /**
     * Check if Tesseract is available on the system.
     */
    public function isAvailable(): bool
    {
        $process = new Process([$this->tesseractBinary, '--version']);
        $process->setTimeout(10);
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * Check if Croatian language pack is installed.
     */
    public function hasCroatianLanguage(): bool
    {
        $process = new Process([$this->tesseractBinary, '--list-langs']);
        $process->setTimeout(10);
        $process->run();

        if (! $process->isSuccessful()) {
            return false;
        }

        return str_contains($process->getOutput(), 'hrv');
    }

    /**
     * Clean up temporary directory.
     */
    private function cleanupTempDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = glob($dir . '/*');
        foreach ((array) $files as $file) {
            @unlink($file);
        }
        @rmdir($dir);
    }
}
```

**Step 4: Run tests**

```bash
./scripts/run-focused-tests.sh TesseractOcrServiceTest
```

Expected: All 6 tests PASS

**Step 5: Commit**

```bash
git add tests/Unit/Services/Ocr/TesseractOcrServiceTest.php app/Services/Ocr/TesseractOcrService.php
git commit -m "feat: add TesseractOcrService for Croatian legal document OCR

- High-quality Tesseract OCR with Croatian (hrv) + English language support
- PDF-to-TIFF conversion via ImageMagick at configurable DPI
- Per-page processing with timeout and error handling
- Original page structure preservation with page separators
- Page metadata tracking (char count, word count per page)
- Availability and language pack detection methods
- 6 unit tests covering command building, page combining, metadata"
```

---

## Task 4: Create OcrQualityComparator Service (with Tests)

**Files:**
- Create: `tests/Unit/Services/Ocr/OcrQualityComparatorTest.php`
- Create: `app/Services/Ocr/OcrQualityComparator.php`

**Step 1: Write failing tests**

Create `tests/Unit/Services/Ocr/OcrQualityComparatorTest.php`:

```php
<?php

namespace Tests\Unit\Services\Ocr;

use App\Services\Ocr\OcrQualityComparator;
use Tests\TestCase;

class OcrQualityComparatorTest extends TestCase
{
    private OcrQualityComparator $comparator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->comparator = new OcrQualityComparator();
    }

    /** @test */
    public function it_prefers_text_with_more_croatian_diacritics(): void
    {
        $textractText = 'Opcinski sud u Zagrebu donosi rjesenje u predmetu';
        $tesseractText = 'Općinski sud u Zagrebu donosi rješenje u predmetu';

        $result = $this->comparator->compare($textractText, $tesseractText);

        $this->assertEquals('tesseract', $result['winner']);
        $this->assertGreaterThan(0, $result['improvement_percent']);
    }

    /** @test */
    public function it_prefers_longer_valid_text_when_quality_similar(): void
    {
        $short = 'Short text.';
        $long = 'Longer text with more content that represents better OCR extraction results from the document.';

        $result = $this->comparator->compare($short, $long);

        $this->assertEquals('tesseract', $result['winner']);
    }

    /** @test */
    public function it_keeps_textract_when_quality_difference_minimal(): void
    {
        $textractText = 'Općinski sud u Zagrebu donosi rješenje.';
        $tesseractText = 'Općinski sud u Zagrebu donosi rješenje.';

        $result = $this->comparator->compare($textractText, $tesseractText);

        $this->assertEquals('textract', $result['winner']);
        $this->assertLessThanOrEqual(0, $result['improvement_percent']);
    }

    /** @test */
    public function it_detects_garbled_tesseract_output(): void
    {
        $textractText = 'Clean readable text from legal document.';
        $tesseractText = '|||/// ~~~ @@@ ### %%% &&& *** +++';

        $result = $this->comparator->compare($textractText, $tesseractText);

        $this->assertEquals('textract', $result['winner']);
    }

    /** @test */
    public function it_returns_comparison_metrics(): void
    {
        $result = $this->comparator->compare('text one', 'text two');

        $this->assertArrayHasKey('winner', $result);
        $this->assertArrayHasKey('improvement_percent', $result);
        $this->assertArrayHasKey('textract_score', $result);
        $this->assertArrayHasKey('tesseract_score', $result);
        $this->assertArrayHasKey('reasons', $result);
    }

    /** @test */
    public function it_scores_croatian_diacritic_preservation(): void
    {
        $without = 'cezsd CEZSD';  // Missing diacritics
        $with = 'čćžšđ ČĆŽŠĐ';  // Proper diacritics

        $scoreWithout = $this->comparator->scoreDiacritics($without);
        $scoreWith = $this->comparator->scoreDiacritics($with);

        $this->assertGreaterThan($scoreWithout, $scoreWith);
    }
}
```

**Step 2: Run tests to verify they fail**

```bash
./scripts/run-focused-tests.sh OcrQualityComparatorTest
```

**Step 3: Implement OcrQualityComparator**

Create `app/Services/Ocr/OcrQualityComparator.php`:

```php
<?php

namespace App\Services\Ocr;

/**
 * OcrQualityComparator
 *
 * Compares OCR output from Textract and Tesseract to determine which
 * produced higher quality text, especially for Croatian legal documents.
 * Evaluates diacritic preservation, text completeness, and readability.
 */
class OcrQualityComparator
{
    private const CROATIAN_DIACRITICS = ['č', 'ć', 'ž', 'š', 'đ', 'Č', 'Ć', 'Ž', 'Š', 'Đ'];

    /**
     * Compare Textract output vs Tesseract output and pick the winner.
     *
     * @return array{winner: string, improvement_percent: float, textract_score: float, tesseract_score: float, reasons: array}
     */
    public function compare(string $textractText, string $tesseractText): array
    {
        $textractScore = $this->scoreText($textractText);
        $tesseractScore = $this->scoreText($tesseractText);

        $reasons = [];
        $minImprovement = (float) config('ocr.quality.min_improvement_percent', 5.0);

        // Diacritic comparison
        $diacTextract = $this->scoreDiacritics($textractText);
        $diacTesseract = $this->scoreDiacritics($tesseractText);
        if ($diacTesseract > $diacTextract) {
            $reasons[] = sprintf('better_diacritics:+%.1f%%', ($diacTesseract - $diacTextract) * 100);
        }

        // Content length (more complete extraction)
        $lenTextract = mb_strlen(trim($textractText), 'UTF-8');
        $lenTesseract = mb_strlen(trim($tesseractText), 'UTF-8');
        if ($lenTesseract > $lenTextract * 1.1) {
            $reasons[] = 'more_content';
        }

        $improvementPercent = $textractScore > 0
            ? (($tesseractScore - $textractScore) / $textractScore) * 100
            : ($tesseractScore > 0 ? 100.0 : 0.0);

        $winner = $improvementPercent >= $minImprovement ? 'tesseract' : 'textract';

        return [
            'winner' => $winner,
            'improvement_percent' => round($improvementPercent, 2),
            'textract_score' => round($textractScore, 4),
            'tesseract_score' => round($tesseractScore, 4),
            'reasons' => $reasons,
        ];
    }

    /**
     * Score text quality on a 0-1 scale.
     */
    private function scoreText(string $text): float
    {
        $text = trim($text);
        $len = mb_strlen($text, 'UTF-8');

        if ($len === 0) {
            return 0.0;
        }

        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $wordCount = count($words);

        // Factor 1: Valid word ratio
        $validWords = 0;
        foreach ($words as $word) {
            $wLen = mb_strlen($word, 'UTF-8');
            if ($wLen >= 2 && $wLen <= 45) {
                $alphaCount = mb_strlen(preg_replace('/[^a-zA-ZčćžšđČĆŽŠĐ]/u', '', $word), 'UTF-8');
                if ($wLen > 0 && ($alphaCount / $wLen) > 0.6) {
                    $validWords++;
                }
            }
        }
        $validWordRatio = $wordCount > 0 ? $validWords / $wordCount : 0.0;

        // Factor 2: Diacritic presence (important for Croatian)
        $diacriticScore = $this->scoreDiacritics($text);

        // Factor 3: Content completeness (normalized by expected document length)
        $completeness = min(1.0, $wordCount / 100);

        // Factor 4: Readability (line structure quality)
        $lineBreaks = substr_count($text, "\n");
        $avgCharsPerLine = $lineBreaks > 0 ? $len / $lineBreaks : $len;
        $lineQuality = min(1.0, $avgCharsPerLine / 40);

        return ($validWordRatio * 0.4) + ($diacriticScore * 0.3) + ($completeness * 0.15) + ($lineQuality * 0.15);
    }

    /**
     * Score diacritic preservation quality.
     */
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

        // Croatian text typically has 3-8% diacritics
        return min(1.0, ($diacriticCount / $len) * 20);
    }
}
```

**Step 4: Run tests**

```bash
./scripts/run-focused-tests.sh OcrQualityComparatorTest
```

Expected: All 6 tests PASS

**Step 5: Commit**

```bash
git add tests/Unit/Services/Ocr/OcrQualityComparatorTest.php app/Services/Ocr/OcrQualityComparator.php
git commit -m "feat: add OcrQualityComparator for Textract vs Tesseract output comparison

- Score text quality based on valid word ratio, diacritics, completeness
- Compare diacritic preservation (critical for Croatian legal text)
- Detect garbled OCR output via alpha ratio analysis
- Configurable minimum improvement threshold for engine switching
- 6 unit tests covering comparison scenarios"
```

---

## Task 5: Create TesseractOcrStep Pipeline Step (with Tests)

**Files:**
- Create: `tests/Unit/Pipelines/Textract/TesseractOcrStepTest.php`
- Create: `app/Pipelines/Textract/TesseractOcrStep.php`
- Modify: `app/Actions/Textract/ProcessDrivePdf.php`

**Step 1: Write failing tests**

Create `tests/Unit/Pipelines/Textract/TesseractOcrStepTest.php`:

```php
<?php

namespace Tests\Unit\Pipelines\Textract;

use App\Models\TextractJob;
use App\Pipelines\Textract\TesseractOcrStep;
use App\Services\Ocr\DocumentOcrRouter;
use App\Services\Ocr\OcrQualityComparator;
use App\Services\Ocr\TesseractOcrService;
use Mockery;
use Tests\TestCase;

class TesseractOcrStepTest extends TestCase
{
    /** @test */
    public function it_skips_tesseract_when_router_recommends_textract(): void
    {
        $router = Mockery::mock(DocumentOcrRouter::class);
        $router->shouldReceive('recommend')
            ->once()
            ->andReturn(['engine' => 'textract', 'reasons' => ['high_confidence'], 'confidence' => 0.95, 'analysis' => []]);

        $tesseract = Mockery::mock(TesseractOcrService::class);
        $tesseract->shouldNotReceive('extractText');

        $comparator = Mockery::mock(OcrQualityComparator::class);

        $step = new TesseractOcrStep($router, $tesseract, $comparator);

        $payload = [
            'driveFileId' => 'test-123',
            'driveFileName' => 'test.pdf',
            'blocks' => [['BlockType' => 'WORD', 'Confidence' => 98]],
            'textractText' => 'Existing good quality text.',
        ];

        $nextCalled = false;
        $step->handle($payload, function ($p) use (&$nextCalled) {
            $nextCalled = true;
            $this->assertEquals('textract', $p['ocr_engine_used']);
            return $p;
        });

        $this->assertTrue($nextCalled);
    }

    /** @test */
    public function it_runs_tesseract_when_router_recommends_it(): void
    {
        $router = Mockery::mock(DocumentOcrRouter::class);
        $router->shouldReceive('recommend')
            ->once()
            ->andReturn(['engine' => 'tesseract', 'reasons' => ['croatian_content:0.8'], 'confidence' => 0.85, 'analysis' => []]);

        $tesseract = Mockery::mock(TesseractOcrService::class);
        $tesseract->shouldReceive('extractText')
            ->once()
            ->andReturn([
                'text' => 'Općinski sud u Zagrebu donosi rješenje.',
                'status' => 'success',
                'pages' => [['page' => 1, 'char_count' => 40, 'word_count' => 6]],
                'page_count' => 1,
                'engine' => 'tesseract',
            ]);

        $comparator = Mockery::mock(OcrQualityComparator::class);
        $comparator->shouldReceive('compare')
            ->once()
            ->andReturn([
                'winner' => 'tesseract',
                'improvement_percent' => 15.0,
                'textract_score' => 0.6,
                'tesseract_score' => 0.85,
                'reasons' => ['better_diacritics'],
            ]);

        $step = new TesseractOcrStep($router, $tesseract, $comparator);

        $payload = [
            'driveFileId' => 'test-123',
            'driveFileName' => 'test.pdf',
            'localPath' => '/tmp/test.pdf',
            'blocks' => [['BlockType' => 'WORD', 'Confidence' => 65]],
            'textractText' => 'Opcinski sud u Zagrebu donosi rjesenje.',
        ];

        $step->handle($payload, function ($p) {
            $this->assertEquals('tesseract', $p['ocr_engine_used']);
            $this->assertStringContainsString('Općinski', $p['finalText']);
            return $p;
        });
    }

    /** @test */
    public function it_falls_back_to_textract_when_tesseract_fails(): void
    {
        $router = Mockery::mock(DocumentOcrRouter::class);
        $router->shouldReceive('recommend')
            ->andReturn(['engine' => 'tesseract', 'reasons' => ['croatian_content'], 'confidence' => 0.8, 'analysis' => []]);

        $tesseract = Mockery::mock(TesseractOcrService::class);
        $tesseract->shouldReceive('extractText')
            ->once()
            ->andReturn([
                'text' => '',
                'status' => 'error',
                'pages' => [],
                'page_count' => 0,
                'engine' => 'tesseract',
                'error' => 'Tesseract not available',
            ]);

        $comparator = Mockery::mock(OcrQualityComparator::class);

        $step = new TesseractOcrStep($router, $tesseract, $comparator);

        $payload = [
            'driveFileId' => 'test-123',
            'driveFileName' => 'test.pdf',
            'localPath' => '/tmp/test.pdf',
            'blocks' => [],
            'textractText' => 'Fallback text from Textract.',
        ];

        $step->handle($payload, function ($p) {
            $this->assertEquals('textract', $p['ocr_engine_used']);
            $this->assertStringContainsString('Fallback text', $p['finalText']);
            return $p;
        });
    }

    /** @test */
    public function it_stores_ocr_metadata_in_payload(): void
    {
        $router = Mockery::mock(DocumentOcrRouter::class);
        $router->shouldReceive('recommend')
            ->andReturn(['engine' => 'textract', 'reasons' => ['default'], 'confidence' => 0.9, 'analysis' => []]);

        $tesseract = Mockery::mock(TesseractOcrService::class);
        $comparator = Mockery::mock(OcrQualityComparator::class);

        $step = new TesseractOcrStep($router, $tesseract, $comparator);

        $payload = [
            'driveFileId' => 'test-123',
            'driveFileName' => 'test.pdf',
            'blocks' => [],
            'textractText' => 'Some text.',
        ];

        $step->handle($payload, function ($p) {
            $this->assertArrayHasKey('ocr_routing', $p);
            $this->assertArrayHasKey('ocr_engine_used', $p);
            return $p;
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
```

**Step 2: Run tests to verify they fail**

```bash
./scripts/run-focused-tests.sh TesseractOcrStepTest
```

**Step 3: Implement TesseractOcrStep**

Create `app/Pipelines/Textract/TesseractOcrStep.php`:

```php
<?php

namespace App\Pipelines\Textract;

use App\Models\TextractJob;
use App\Services\Ocr\DocumentOcrRouter;
use App\Services\Ocr\OcrQualityComparator;
use App\Services\Ocr\TesseractOcrService;
use Closure;
use Illuminate\Support\Facades\Log;

/**
 * TesseractOcrStep
 *
 * Pipeline step that optionally runs Tesseract OCR based on document analysis.
 * Compares Tesseract output with Textract output and picks the higher quality result.
 * Preserves original text layout and page structure.
 *
 * Inserted after CheckOcrQualityStep in the pipeline, before CreateMetadataStep.
 */
class TesseractOcrStep
{
    public function __construct(
        private DocumentOcrRouter $router,
        private TesseractOcrService $tesseract,
        private OcrQualityComparator $comparator,
    ) {}

    public function handle(array $payload, Closure $next): mixed
    {
        $driveFileId = (string) $payload['driveFileId'];
        $driveFileName = (string) $payload['driveFileName'];

        // Collect Textract text from previous pipeline steps
        $textractText = $payload['textractText'] ?? $this->extractTextFromPayload($payload);
        $blocks = $payload['blocks'] ?? [];
        $localPath = $payload['localPath'] ?? null;

        Log::info('TesseractOcrStep: analyzing document for OCR routing', [
            'driveFileId' => $driveFileId,
            'textract_text_length' => mb_strlen($textractText),
            'has_local_path' => $localPath !== null,
        ]);

        // Ask router which engine to use
        $routing = $this->router->recommend($textractText, $blocks);
        $payload['ocr_routing'] = $routing;

        if ($routing['engine'] !== 'tesseract' || $localPath === null) {
            Log::info('TesseractOcrStep: keeping Textract output', [
                'driveFileId' => $driveFileId,
                'engine' => $routing['engine'],
                'reasons' => $routing['reasons'],
            ]);

            $payload['ocr_engine_used'] = 'textract';
            $payload['finalText'] = $textractText;

            return $next($payload);
        }

        // Run Tesseract OCR
        Log::info('TesseractOcrStep: running Tesseract OCR', [
            'driveFileId' => $driveFileId,
            'reasons' => $routing['reasons'],
        ]);

        $tesseractResult = $this->tesseract->extractText($localPath);

        // If Tesseract failed, fall back to Textract
        if ($tesseractResult['status'] === 'error' || empty($tesseractResult['text'])) {
            Log::warning('TesseractOcrStep: Tesseract failed, falling back to Textract', [
                'driveFileId' => $driveFileId,
                'error' => $tesseractResult['error'] ?? 'empty output',
            ]);

            $payload['ocr_engine_used'] = 'textract';
            $payload['finalText'] = $textractText;
            $payload['tesseract_attempted'] = true;
            $payload['tesseract_error'] = $tesseractResult['error'] ?? 'empty output';

            return $next($payload);
        }

        // Compare quality of both outputs
        $comparison = $this->comparator->compare($textractText, $tesseractResult['text']);

        Log::info('TesseractOcrStep: quality comparison', [
            'driveFileId' => $driveFileId,
            'winner' => $comparison['winner'],
            'improvement' => $comparison['improvement_percent'],
            'textract_score' => $comparison['textract_score'],
            'tesseract_score' => $comparison['tesseract_score'],
        ]);

        $payload['ocr_comparison'] = $comparison;
        $payload['ocr_engine_used'] = $comparison['winner'];

        if ($comparison['winner'] === 'tesseract') {
            $payload['finalText'] = $tesseractResult['text'];
            $payload['tesseract_pages'] = $tesseractResult['pages'];
            $payload['tesseract_page_count'] = $tesseractResult['page_count'];
        } else {
            $payload['finalText'] = $textractText;
        }

        // Update TextractJob metadata with OCR routing info
        if (isset($payload['job']) && $payload['job'] instanceof TextractJob) {
            $metadata = $payload['job']->metadata ?? [];
            $metadata['ocr_routing'] = [
                'engine_used' => $payload['ocr_engine_used'],
                'routing_reasons' => $routing['reasons'],
                'comparison' => $comparison,
                'tesseract_page_count' => $tesseractResult['page_count'] ?? 0,
            ];
            $payload['job']->update(['metadata' => $metadata]);
        }

        return $next($payload);
    }

    /**
     * Extract text from payload's collected lines (from CollectLinesStep).
     */
    private function extractTextFromPayload(array $payload): string
    {
        $linesByPage = $payload['linesByPage'] ?? [];
        $text = '';
        foreach ($linesByPage as $pageLines) {
            foreach ((array) $pageLines as $line) {
                $text .= $line . "\n";
            }
            $text .= "\n";
        }
        return trim($text);
    }
}
```

**Step 4: Run tests**

```bash
./scripts/run-focused-tests.sh TesseractOcrStepTest
```

Expected: All 4 tests PASS

**Step 5: Integrate into ProcessDrivePdf pipeline**

Modify `app/Actions/Textract/ProcessDrivePdf.php` to add the new step after `CheckOcrQualityStep`:

Add import at top:
```php
use App\Pipelines\Textract\TesseractOcrStep;
```

Update the pipeline `through()` array to insert `TesseractOcrStep::class` after `CheckOcrQualityStep::class`:

```php
->through([
    EnsureJobStep::class,
    DownloadDriveFileStep::class,
    UploadInputToS3Step::class,
    StartAnalysisStep::class,
    WaitAndFetchStep::class,
    SaveResultsStep::class,
    CollectLinesStep::class,
    CheckOcrQualityStep::class,     // OCR quality analysis
    TesseractOcrStep::class,        // Tesseract OCR routing & comparison
    CreateMetadataStep::class,      // Legal metadata extraction
    ReconstructPdfStep::class,
    UploadOutputStep::class,
    PersistReconstructedStep::class,
])
```

Also update the text extraction logic after the pipeline to use `finalText` from the payload if available:

```php
// After pipeline returns, prefer finalText from TesseractOcrStep
$fullText = $payload['finalText'] ?? '';

// Fallback: extract from ocrDocument if finalText not set
if ($fullText === '' && isset($payload['ocrDocument'])) {
    $doc = $payload['ocrDocument'];
    if (isset($doc->pages)) {
        foreach ($doc->pages as $page) {
            if (! isset($page->lines)) continue;
            foreach ($page->lines as $line) {
                $txt = $line->text ?? '';
                if ($txt !== '') $fullText .= $txt . "\n";
            }
            $fullText .= "\n";
        }
    }
    $fullText = trim($fullText);
}
```

**Step 6: Run tests**

```bash
./scripts/run-focused-tests.sh TesseractOcrStepTest
./scripts/run-focused-tests.sh ProcessDrivePdfTest
```

**Step 7: Commit**

```bash
git add app/Pipelines/Textract/TesseractOcrStep.php \
  tests/Unit/Pipelines/Textract/TesseractOcrStepTest.php \
  app/Actions/Textract/ProcessDrivePdf.php
git commit -m "feat: integrate TesseractOcrStep into document processing pipeline

- Add TesseractOcrStep after CheckOcrQualityStep in pipeline
- Route to optimal OCR engine based on document analysis
- Compare Textract vs Tesseract output quality
- Fall back to Textract if Tesseract fails
- Store OCR routing metadata on TextractJob
- Use finalText from pipeline for extracted content
- 4 unit tests with mocked dependencies"
```

---

## Task 6: Add Migration for OCR Engine Tracking

**Files:**
- Create: `database/migrations/2026_01_31_000001_add_ocr_engine_to_textract_jobs_table.php`

**Step 1: Create migration**

```bash
php artisan make:migration add_ocr_engine_to_textract_jobs_table
```

**Step 2: Implement migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('textract_jobs', function (Blueprint $table) {
            $table->string('ocr_engine', 20)->default('textract')->after('status')
                ->comment('OCR engine used: textract, tesseract, or hybrid');
            $table->json('ocr_routing_metadata')->nullable()->after('performance_metrics')
                ->comment('OCR routing decision data: engine comparison, scores, reasons');
        });
    }

    public function down(): void
    {
        Schema::table('textract_jobs', function (Blueprint $table) {
            $table->dropColumn(['ocr_engine', 'ocr_routing_metadata']);
        });
    }
};
```

**Step 3: Update TextractJob model to include new fields**

Add to `$fillable` in `app/Models/TextractJob.php`:

```php
'ocr_engine',
'ocr_routing_metadata',
```

Add to `$casts`:

```php
'ocr_routing_metadata' => 'array',
```

**Step 4: Run migration**

```bash
php artisan migrate
```

**Step 5: Commit**

```bash
git add database/migrations/*add_ocr_engine_to_textract_jobs* app/Models/TextractJob.php
git commit -m "feat: add ocr_engine tracking to textract_jobs table

- Track which OCR engine was used (textract/tesseract/hybrid)
- Store OCR routing metadata (comparison scores, reasons)
- Add fields to TextractJob model fillable and casts"
```

---

## Task 7: Update CollectLinesStep to Preserve Original Formatting

**Files:**
- Modify: `app/Pipelines/Textract/CollectLinesStep.php`
- Create: `tests/Unit/Pipelines/Textract/CollectLinesStepFormattingTest.php`

**Step 1: Write tests for formatting preservation**

Create `tests/Unit/Pipelines/Textract/CollectLinesStepFormattingTest.php`:

```php
<?php

namespace Tests\Unit\Pipelines\Textract;

use App\Pipelines\Textract\CollectLinesStep;
use App\Services\TextractService;
use Mockery;
use Tests\TestCase;

class CollectLinesStepFormattingTest extends TestCase
{
    /** @test */
    public function it_preserves_page_boundaries_in_collected_text(): void
    {
        $textractService = Mockery::mock(TextractService::class);
        $textractService->shouldReceive('collectLinesByPage')
            ->andReturn([
                1 => ['Line 1 of page 1', 'Line 2 of page 1'],
                2 => ['Line 1 of page 2', 'Line 2 of page 2'],
            ]);

        $step = new CollectLinesStep($textractService);
        $payload = ['blocks' => [], 'driveFileId' => 'test', 'driveFileName' => 'test.pdf'];

        $nextPayload = null;
        $step->handle($payload, function ($p) use (&$nextPayload) {
            $nextPayload = $p;
            return $p;
        });

        $this->assertArrayHasKey('linesByPage', $nextPayload);
        $this->assertCount(2, $nextPayload['linesByPage']);

        // Build textractText from linesByPage
        $text = $this->buildTextFromLines($nextPayload['linesByPage']);
        $this->assertStringContainsString('Line 1 of page 1', $text);
        $this->assertStringContainsString('Line 1 of page 2', $text);
    }

    /** @test */
    public function it_stores_textract_text_in_payload(): void
    {
        $textractService = Mockery::mock(TextractService::class);
        $textractService->shouldReceive('collectLinesByPage')
            ->andReturn([
                1 => ['Presuda suda', 'Članak 123.'],
            ]);

        $step = new CollectLinesStep($textractService);
        $payload = ['blocks' => [], 'driveFileId' => 'test', 'driveFileName' => 'test.pdf'];

        $step->handle($payload, function ($p) {
            $this->assertArrayHasKey('textractText', $p);
            $this->assertStringContainsString('Presuda suda', $p['textractText']);
            return $p;
        });
    }

    private function buildTextFromLines(array $linesByPage): string
    {
        $text = '';
        foreach ($linesByPage as $pageLines) {
            foreach ($pageLines as $line) {
                $text .= $line . "\n";
            }
            $text .= "\n";
        }
        return trim($text);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
```

**Step 2: Update CollectLinesStep to store textractText in payload**

In `app/Pipelines/Textract/CollectLinesStep.php`, after collecting lines by page, add:

```php
// Build full textract text for downstream comparison
$textractText = '';
foreach ($linesByPage as $pageLines) {
    foreach ((array) $pageLines as $line) {
        $textractText .= $line . "\n";
    }
    $textractText .= "\n";
}
$payload['textractText'] = trim($textractText);
```

**Step 3: Run tests**

```bash
./scripts/run-focused-tests.sh CollectLinesStepFormattingTest
```

**Step 4: Commit**

```bash
git add app/Pipelines/Textract/CollectLinesStep.php \
  tests/Unit/Pipelines/Textract/CollectLinesStepFormattingTest.php
git commit -m "feat: preserve original text formatting in CollectLinesStep

- Store textractText in pipeline payload for downstream comparison
- Maintain page boundaries in collected text
- Enable TesseractOcrStep to compare against original Textract output"
```

---

## Task 8: Update PersistReconstructedStep for OCR Engine Metadata

**Files:**
- Modify: `app/Pipelines/Textract/PersistReconstructedStep.php`

**Step 1: Read current PersistReconstructedStep**

Read `app/Pipelines/Textract/PersistReconstructedStep.php` to understand current persistence logic.

**Step 2: Update to persist OCR engine info**

Add the following to the update array where the TextractJob is persisted:

```php
'ocr_engine' => $payload['ocr_engine_used'] ?? 'textract',
'ocr_routing_metadata' => $payload['ocr_routing'] ?? null,
```

And use `$payload['finalText']` if available instead of reconstructing from ocrDocument:

```php
$extractedContent = $payload['finalText'] ?? $this->extractFromOcrDocument($payload);
```

**Step 3: Run existing tests**

```bash
./scripts/run-focused-tests.sh PersistReconstructedStepTest
```

**Step 4: Commit**

```bash
git add app/Pipelines/Textract/PersistReconstructedStep.php
git commit -m "feat: persist OCR engine metadata in PersistReconstructedStep

- Store ocr_engine and ocr_routing_metadata on TextractJob
- Prefer finalText from TesseractOcrStep over manual extraction
- Backward compatible: defaults to textract engine"
```

---

## Summary

| Task | Component | Tests | Purpose |
|------|-----------|-------|---------|
| 1 | Config + Package | - | Tesseract PHP wrapper, config/ocr.php |
| 2 | DocumentOcrRouter | 9 | Intelligent engine routing |
| 3 | TesseractOcrService | 6 | Croatian OCR extraction |
| 4 | OcrQualityComparator | 6 | Output quality comparison |
| 5 | TesseractOcrStep | 4 | Pipeline integration |
| 6 | Migration | - | Database schema for tracking |
| 7 | CollectLinesStep | 2 | Text formatting preservation |
| 8 | PersistReconstructedStep | - | Persist OCR metadata |

**Total: 8 tasks, 27 new tests**

**Dependencies:** Tasks 1 must be first. Tasks 2-4 can run in parallel. Task 5 depends on 2-4. Tasks 6-8 depend on 5.

```
Task 1 (config) ─┬─> Task 2 (router)     ─┐
                  ├─> Task 3 (tesseract)   ─┼─> Task 5 (pipeline step) ─┬─> Task 6 (migration)
                  └─> Task 4 (comparator)  ─┘                           ├─> Task 7 (collect lines)
                                                                        └─> Task 8 (persist)
```
