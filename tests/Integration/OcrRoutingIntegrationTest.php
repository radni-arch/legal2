<?php

namespace Tests\Integration;

use App\Services\Ocr\DocumentOcrRouter;
use App\Services\Ocr\OcrQualityAnalyzer;
use App\Services\Ocr\OcrQualityComparator;
use Tests\TestCase;

class OcrRoutingIntegrationTest extends TestCase
{
    private DocumentOcrRouter $router;

    private OcrQualityComparator $comparator;

    private OcrQualityAnalyzer $qualityAnalyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new DocumentOcrRouter();
        $this->comparator = new OcrQualityComparator();
        $this->qualityAnalyzer = new OcrQualityAnalyzer();
    }

    /** @test */
    public function croatian_legal_document_with_low_confidence_routes_to_tesseract(): void
    {
        $blocks = json_decode(file_get_contents(
            base_path('tests/fixtures/ocr/sample-croatian-blocks.json')
        ), true);

        // Step 1: Quality check
        $quality = $this->qualityAnalyzer->analyzeFromBlocks($blocks);
        $this->assertLessThan(0.80, $quality['confidence'],
            'Croatian fixture should have low confidence');

        // Step 2: Extract text from LINE blocks
        $textractText = collect($blocks)
            ->where('BlockType', 'LINE')
            ->pluck('Text')
            ->implode("\n");

        // Step 3: Routing
        $routing = $this->router->recommend($textractText, $blocks);
        $this->assertEquals('tesseract', $routing['engine'],
            'Low-confidence Croatian text should route to tesseract');
        $this->assertGreaterThan(0, $routing['routing_score'],
            'Routing score should be positive (favoring tesseract)');
    }

    /** @test */
    public function english_document_with_high_confidence_and_tables_routes_to_textract(): void
    {
        $blocks = json_decode(file_get_contents(
            base_path('tests/fixtures/ocr/sample-english-blocks.json')
        ), true);

        // Step 1: Quality check
        $quality = $this->qualityAnalyzer->analyzeFromBlocks($blocks);
        $this->assertGreaterThan(0.85, $quality['confidence'],
            'English fixture should have high confidence');

        // Step 2: Extract text
        $textractText = collect($blocks)
            ->where('BlockType', 'LINE')
            ->pluck('Text')
            ->implode("\n");

        // Step 3: Routing
        $routing = $this->router->recommend($textractText, $blocks);
        $this->assertEquals('textract', $routing['engine'],
            'High-confidence English text with tables should stay with textract');
        $this->assertLessThanOrEqual(0, $routing['routing_score'],
            'Routing score should be non-positive (favoring textract)');
    }

    /** @test */
    public function quality_comparator_picks_tesseract_for_better_diacritics(): void
    {
        // Simulate Textract output with mangled diacritics
        $textractText = "Opcinski sud u Zagrebu donosi presudu u predmetu broj P-1234/2024. "
            . "Tuzitelj podnosi zalbu protiv rjesenja o odbijanju tuzbenog zahtjeva. "
            . "Presuda se temelji na clanku 123. stavak 2. Zakona o parnicnom postupku.";

        // Simulate Tesseract/ocrmypdf output with proper diacritics
        $tesseractText = "Općinski sud u Zagrebu donosi presudu u predmetu broj P-1234/2024. "
            . "Tužitelj podnosi žalbu protiv rješenja o odbijanju tužbenog zahtjeva. "
            . "Presuda se temelji na članku 123. stavak 2. Zakona o parničnom postupku.";

        $comparison = $this->comparator->compare($textractText, $tesseractText);

        $this->assertEquals('tesseract', $comparison['winner'],
            'Text with proper Croatian diacritics should win');
        $this->assertGreaterThan(0, $comparison['improvement_percent'],
            'Improvement should be positive');
    }

    /** @test */
    public function quality_comparator_keeps_textract_when_outputs_identical(): void
    {
        $text = "Općinski sud u Zagrebu donosi presudu u predmetu.";

        $comparison = $this->comparator->compare($text, $text);

        $this->assertEquals('textract', $comparison['winner'],
            'Identical outputs should keep textract');
    }

    /** @test */
    public function full_routing_flow_croatian_low_confidence_with_tables(): void
    {
        // Create blocks with low confidence Croatian text AND tables
        $blocks = [];
        $croatianWords = ['Opcinski', 'sud', 'u', 'Zagrebu', 'donosi', 'presudu', 'rjesenje', 'zahtjev', 'zalba', 'tuzitelj'];
        for ($i = 0; $i < 50; $i++) {
            $blocks[] = [
                'BlockType' => 'WORD',
                'Confidence' => 55.0 + ($i % 10),
                'Page' => 1,
                'Text' => $croatianWords[$i % count($croatianWords)],
            ];
        }
        $blocks[] = ['BlockType' => 'TABLE', 'Page' => 1, 'Confidence' => 90.0];
        $blocks[] = ['BlockType' => 'TABLE', 'Page' => 1, 'Confidence' => 88.0];

        $text = implode(' ', $croatianWords) . ' ' . implode(' ', $croatianWords);
        $text = str_repeat($text . ' ', 5);

        // Quality should be low
        $quality = $this->qualityAnalyzer->analyzeFromBlocks($blocks);
        $this->assertLessThan(0.70, $quality['confidence']);

        // Routing should favor tesseract despite tables (weighted scoring)
        $routing = $this->router->recommend($text, $blocks);
        $this->assertEquals('tesseract', $routing['engine'],
            'Low confidence + Croatian should override table presence');
    }

    /** @test */
    public function diacritic_scoring_does_not_saturate_for_realistic_text(): void
    {
        // Both texts have diacritics but at different densities
        $lowDiacritics = "Opcinski sud u Zagrebu donosi rjesenje u predmetu P-1234/2024 "
            . "Tuzitelj je podnio zalbu u roku od 15 dana.";
        $highDiacritics = "Općinski sud u Zagrebu donosi rješenje u predmetu P-1234/2024 "
            . "Tužitelj je podnio žalbu u roku od 15 dana.";

        $lowScore = $this->comparator->scoreDiacritics($lowDiacritics);
        $highScore = $this->comparator->scoreDiacritics($highDiacritics);

        $this->assertGreaterThan($lowScore, $highScore, 'Better diacritics should score higher');
        $this->assertLessThan(1.0, $highScore, 'Score should not saturate at 1.0');
        $this->assertGreaterThan(0.0, $lowScore, 'Even mangled text should have some score');
    }
}
