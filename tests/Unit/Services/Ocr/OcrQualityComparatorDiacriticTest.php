<?php

namespace Tests\Unit\Services\Ocr;

use App\Services\Ocr\OcrQualityComparator;
use Tests\TestCase;

/**
 * Dedicated test suite for Croatian diacritic handling in OcrQualityComparator.
 *
 * These tests verify that Croatian diacritics (c, c, z, s, d) do not
 * penalize quality scores and that the scoring curve produces sensible
 * values across typical Croatian diacritic densities (5-15%).
 *
 * Task 2.3: Fix OcrQualityComparator Diacritic Saturation
 */
class OcrQualityComparatorDiacriticTest extends TestCase
{
    private OcrQualityComparator $comparator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->comparator = new OcrQualityComparator();
    }

    // ========================================================================
    // Test 1: High diacritic density doesn't penalize quality score
    // ========================================================================

    /** @test */
    public function high_diacritic_density_does_not_penalize_quality_score(): void
    {
        // Two identical-structure texts: one with diacritics, one without
        $withDiacritics = 'Općinski sud u Zagrebu donosi rješenje u predmetu '
            . 'kaznenog postupka prema članku četrdeset i dva zakona o žigovima. '
            . 'Određena je žalba u skladu sa člankom sto šezdeset šest.';

        $withoutDiacritics = 'Opcinski sud u Zagrebu donosi rjesenje u predmetu '
            . 'kaznenog postupka prema clanku cetrdeset i dva zakona o zigovima. '
            . 'Odredjena je zalba u skladu sa clankom sto sezdeset sest.';

        // Use scoreText indirectly via compare: put diacritic text as textract
        // and ASCII text as tesseract. If diacritics penalize, textract score
        // would be lower than tesseract.
        $result = $this->comparator->compare($withDiacritics, $withoutDiacritics);

        // The text WITH diacritics should score >= the text WITHOUT
        // (diacritics should help, not hurt)
        $this->assertGreaterThanOrEqual(
            $result['tesseract_score'],
            $result['textract_score'],
            'Text with Croatian diacritics should score equal or higher than ASCII equivalent. '
            . "Textract (with diacritics): {$result['textract_score']}, "
            . "Tesseract (without): {$result['tesseract_score']}"
        );

        // The diacritic score component specifically should be higher
        $diacScoreWith = $this->comparator->scoreDiacritics($withDiacritics);
        $diacScoreWithout = $this->comparator->scoreDiacritics($withoutDiacritics);

        $this->assertGreaterThan(
            $diacScoreWithout,
            $diacScoreWith,
            'Diacritic score for text with diacritics must be higher'
        );

        // The diacritic score for text WITH diacritics should be positive,
        // indicating it contributes positively to quality
        $this->assertGreaterThan(0.3, $diacScoreWith,
            'Diacritic-rich Croatian text should have meaningful positive diacritic score');
    }

    // ========================================================================
    // Test 2: Croatian text with proper diacritics scores equal or higher
    //         than ascii-only version
    // ========================================================================

    /** @test */
    public function croatian_text_with_proper_diacritics_scores_equal_or_higher_than_ascii_only(): void
    {
        // Real-world Croatian legal text with proper diacritics
        $properCroatian = 'Članak 42. stavak 3. Zakona o žigovima određuje da se žalba '
            . 'podnosi u roku od trideset dana od dana dostave rješenja. '
            . 'Državno odvjetništvo može pokrenuti postupak.';

        // Same text with diacritics stripped (simulating poor OCR)
        $asciiVersion = 'Clanak 42. stavak 3. Zakona o zigovima odredjuje da se zalba '
            . 'podnosi u roku od trideset dana od dana dostave rjesenja. '
            . 'Drzavno odvjetnistvo moze pokrenuti postupak.';

        // When compared: proper Croatian as tesseract, ASCII as textract
        $result = $this->comparator->compare($asciiVersion, $properCroatian);

        // Proper Croatian text must win
        $this->assertEquals('tesseract', $result['winner'],
            'Proper Croatian text with diacritics should beat ASCII-stripped version');

        // The improvement should be meaningful (not marginal)
        $this->assertGreaterThan(5.0, $result['improvement_percent'],
            'Diacritic preservation should provide > 5% quality improvement');

        // But the improvement should not be absurdly large (saturation artifact)
        $this->assertLessThan(200.0, $result['improvement_percent'],
            'Improvement from diacritics should not be extreme (saturation issue)');
    }

    // ========================================================================
    // Test 3: Text with each specific Croatian diacritic is not considered
    //         lower quality
    // ========================================================================

    /** @test */
    public function text_with_each_croatian_diacritic_is_not_considered_lower_quality(): void
    {
        $baseText = 'Sud donosi presudu u predmetu kaznenog postupka prema zakonu';

        // Test each Croatian diacritic individually
        $diacriticTexts = [
            'c' => 'Sud donosi presudu u predmetu kaznenoč postupka prema zakonu',
            'c_acute' => 'Sud donosi presudu u predmetu kaznenog postupka prema zakoću',
            'z' => 'Sud donosi presudu u predmetu kaznenog postužka prema zakonu',
            's' => 'Sud donosi prešudu u predmetu kaznenog postupka prema zakonu',
            'd' => 'Suđ donosi presudu u predmetu kaznenog postupka prema zakonu',
        ];

        $baseScore = $this->comparator->scoreDiacritics($baseText);

        foreach ($diacriticTexts as $diacritic => $text) {
            $score = $this->comparator->scoreDiacritics($text);
            $this->assertGreaterThanOrEqual(
                $baseScore,
                $score,
                "Text containing Croatian diacritic '{$diacritic}' should not score lower "
                . "than plain text. Base: {$baseScore}, With diacritic: {$score}"
            );
        }

        // Text with ALL diacritics should score significantly higher
        $allDiacritics = 'Općinski sud donosi rješenje u predmetu čije '
            . 'određivanje žalbe nije moguće bez đačkog pristupa.';
        $allScore = $this->comparator->scoreDiacritics($allDiacritics);

        $this->assertGreaterThan(
            $baseScore + 0.2,
            $allScore,
            'Text with multiple Croatian diacritics should score significantly higher than plain text'
        );
    }

    // ========================================================================
    // Test 4: Scoring curve produces sensible values for typical Croatian
    //         diacritic densities (5-15%)
    // ========================================================================

    /** @test */
    public function scoring_curve_produces_sensible_values_for_typical_croatian_densities(): void
    {
        // Generate texts with controlled diacritic densities
        // We use a base of 100 ASCII characters and insert diacritics to control density

        // Helper: create text with approximately N% diacritic density
        $makeText = function (int $diacriticCount, int $totalLength): string {
            $diacritics = str_repeat('č', $diacriticCount);
            $filler = str_repeat('a', $totalLength - $diacriticCount);
            return $diacritics . $filler;
        };

        // 5% density (typical low end for Croatian)
        $text5pct = $makeText(5, 100);
        $score5 = $this->comparator->scoreDiacritics($text5pct);

        // 8% density (typical mid-range for Croatian)
        $text8pct = $makeText(8, 100);
        $score8 = $this->comparator->scoreDiacritics($text8pct);

        // 10% density (typical for diacritic-heavy Croatian legal text)
        $text10pct = $makeText(10, 100);
        $score10 = $this->comparator->scoreDiacritics($text10pct);

        // 15% density (very high, e.g., specialized terminology)
        $text15pct = $makeText(15, 100);
        $score15 = $this->comparator->scoreDiacritics($text15pct);

        // All scores in the 5-15% range should be meaningful (> 0.4)
        $this->assertGreaterThan(0.4, $score5,
            "5% diacritic density should produce score > 0.4, got {$score5}");
        $this->assertGreaterThan(0.6, $score8,
            "8% diacritic density should produce score > 0.6, got {$score8}");
        $this->assertGreaterThan(0.7, $score10,
            "10% diacritic density should produce score > 0.7, got {$score10}");
        $this->assertGreaterThan(0.8, $score15,
            "15% diacritic density should produce score > 0.8, got {$score15}");

        // Scores should be monotonically increasing with density
        $this->assertGreaterThan($score5, $score8,
            'Score at 8% density should be higher than at 5%');
        $this->assertGreaterThan($score8, $score10,
            'Score at 10% density should be higher than at 8%');
        $this->assertGreaterThan($score10, $score15,
            'Score at 15% density should be higher than at 10%');

        // No score should reach 1.0 (must not fully saturate)
        $this->assertLessThan(1.0, $score15,
            "Even at 15% density, score should not reach 1.0 (got {$score15})");

        // Scores should remain distinct (not saturated into the same value)
        $this->assertGreaterThan(0.05, $score15 - $score5,
            'Scores between 5% and 15% density should be distinguishable, '
            . "not collapsed by saturation. 5%: {$score5}, 15%: {$score15}");
    }

    // ========================================================================
    // Test 5: Quality comparison between two OCR outputs handles diacritics
    //         fairly
    // ========================================================================

    /** @test */
    public function quality_comparison_between_ocr_outputs_handles_diacritics_fairly(): void
    {
        // Scenario: Textract produces good OCR with diacritics,
        // Tesseract produces slightly longer text but without diacritics
        // Fair comparison should not let length alone override diacritic quality
        $textractOutput = 'Općinski sud u Zagrebu donosi rješenje u predmetu '
            . 'kaznenog postupka. Članak 42. stavak 3. Zakona određuje žalbu '
            . 'protiv odluke o novčanoj kazni.';

        $tesseractOutput = 'Opcinski sud u Zagrebu donosi rjesenje u predmetu '
            . 'kaznenog postupka. Clanak 42. stavak 3. Zakona odredjuje zalbu '
            . 'protiv odluke o novcanoj kazni. Dodatni tekst koji je duzi.';

        $result = $this->comparator->compare($textractOutput, $tesseractOutput);

        // Textract with diacritics should win even though tesseract has more text
        $this->assertEquals('textract', $result['winner'],
            'Diacritic-preserving OCR output should win over longer but diacritic-stripped output');

        // Now reverse: test that when both have diacritics, the comparison is fair
        // The shorter text only captures a fragment; the longer text captures
        // a much more complete extraction of the same document.
        $textract2 = 'Općinski sud u Zagrebu donosi rješenje.';
        $tesseract2 = 'Općinski sud u Zagrebu donosi rješenje u predmetu '
            . 'kaznenog postupka. Članak četrdeset dva stavak tri Zakona određuje '
            . 'žalbu protiv odluke o novčanoj kazni. Državno odvjetništvo može '
            . 'pokrenuti postupak po službenoj dužnosti u skladu sa zakonom. '
            . 'Rješenje je doneseno na sjednici vijeća održanoj dvadesetog '
            . 'siječnja dvije tisuće dvadeset šeste godine u prostorijama suda.';

        $result2 = $this->comparator->compare($textract2, $tesseract2);

        // The much more complete extraction should win because it has substantially
        // more content while also preserving diacritics equally well
        $this->assertEquals('tesseract', $result2['winner'],
            'When diacritics equal, substantially more complete text should win');

        // Verify both OCR scores are reasonable (not zero or negative)
        $this->assertGreaterThan(0.2, $result2['textract_score'],
            'Short Croatian text should still have reasonable score');
        $this->assertGreaterThan(0.3, $result2['tesseract_score'],
            'Longer Croatian text should have good score');
    }
}
