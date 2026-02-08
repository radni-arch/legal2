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
        $without = 'cezsd CEZSD';
        $with = 'čćžšđ ČĆŽŠĐ';

        $scoreWithout = $this->comparator->scoreDiacritics($without);
        $scoreWith = $this->comparator->scoreDiacritics($with);

        $this->assertGreaterThan($scoreWithout, $scoreWith);
    }

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

    // ========================================================================
    // CROATIAN DIACRITIC SATURATION FIX TESTS
    // These tests verify that Croatian diacritics (č, ć, ž, š, đ) do not
    // unfairly penalize or saturate quality scores.
    // ========================================================================

    /** @test */
    public function diacritic_score_is_near_zero_for_zero_percent_diacritics(): void
    {
        // Text with no Croatian diacritics should score very low (near 0.0)
        // to not artificially inflate scores for non-Croatian text
        $englishText = 'This is a plain English text with no diacritics at all.';
        $asciiCroatian = 'Opcinski sud u Zagrebu donosi rjesenje u predmetu';

        $englishScore = $this->comparator->scoreDiacritics($englishText);
        $asciiScore = $this->comparator->scoreDiacritics($asciiCroatian);

        // Score for 0% diacritics should be very low (< 0.1)
        $this->assertLessThan(0.1, $englishScore,
            'Text with 0% diacritics should score near zero, not ' . $englishScore);
        $this->assertLessThan(0.1, $asciiScore,
            'ASCII Croatian text should score near zero for diacritics');
    }

    /** @test */
    public function high_diacritic_density_does_not_saturate_prematurely(): void
    {
        // Normal Croatian text (5-8% diacritics) should score well but not saturate
        $normalCroatian = 'Članak 42. stavak 3. Zakona o žigovima određuje žalbu protiv odluke';

        // High density text (simulating unusual text)
        $highDensity = 'čćžšđ čćžšđ čćžšđ čćžšđ čćžšđ čćžšđ'; // ~85% diacritics

        $normalScore = $this->comparator->scoreDiacritics($normalCroatian);
        $highScore = $this->comparator->scoreDiacritics($highDensity);

        // Normal Croatian text should score in a reasonable range (0.5-0.9)
        $this->assertGreaterThan(0.5, $normalScore,
            'Normal Croatian text (5-8% diacritics) should score > 0.5');
        $this->assertLessThan(0.95, $normalScore,
            'Normal Croatian text should not nearly saturate');

        // High density should score higher but still < 1.0 to allow differentiation
        $this->assertGreaterThan($normalScore, $highScore);
        $this->assertLessThan(1.0, $highScore,
            'Even high diacritic density should not fully saturate at 1.0');
    }

    /** @test */
    public function croatian_text_with_diacritics_gets_fair_overall_score(): void
    {
        // Rich Croatian text with proper diacritics
        $croatianWithDiacritics = 'Općinski sud u Zagrebu donosi rješenje u predmetu '
            . 'kaznenog postupka. Članak 42. stavak 3. Zakona određuje žalbu.';

        // Same text with diacritics stripped (simulating bad OCR)
        $croatianWithoutDiacritics = 'Opcinski sud u Zagrebu donosi rjesenje u predmetu '
            . 'kaznenog postupka. Clanak 42. stavak 3. Zakona odredjuje zalbu.';

        $result = $this->comparator->compare($croatianWithoutDiacritics, $croatianWithDiacritics);

        // Text with diacritics should win as it represents better OCR
        $this->assertEquals('tesseract', $result['winner'],
            'Text with preserved diacritics should be preferred');

        // The improvement should be noticeable but not extreme
        $this->assertGreaterThan(5.0, $result['improvement_percent'],
            'Diacritic preservation should show meaningful improvement');
        $this->assertLessThan(100.0, $result['improvement_percent'],
            'Improvement should not be extreme (saturation issue)');
    }

    /** @test */
    public function ascii_equivalent_text_is_fairly_scored_for_content(): void
    {
        // Both texts have similar content, just different diacritic handling
        $withDiacritics = 'Članak 42. stavak 3. Zakona o žigovima određuje žalbu.';
        $withoutDiacritics = 'Clanak 42. stavak 3. Zakona o zigovima odredjuje zalbu.';

        $result = $this->comparator->compare($withoutDiacritics, $withDiacritics);

        // Text with diacritics should win, but both should have valid word ratios
        // The difference should be primarily in the diacritic score, not word validity
        $this->assertEquals('tesseract', $result['winner']);

        // Both scores should be reasonable (content is good in both cases)
        $this->assertGreaterThan(0.3, $result['textract_score'],
            'ASCII equivalent text should still have reasonable content score');
        $this->assertGreaterThan(0.4, $result['tesseract_score'],
            'Text with diacritics should have good score');
    }

    /** @test */
    public function garbled_characters_still_get_low_overall_scores(): void
    {
        // Garbled OCR output with embedded diacritics
        // Note: Diacritic score alone measures density, not text quality.
        // The OVERALL score should penalize garbled text via valid word ratio.
        $garbledText = '###|||~~~ @@@č@@@ ###ž### ~~~š~~~';
        $cleanText = 'Članak 42. stavak 3. Zakona o žigovima određuje žalbu.';

        // Garbled text with scattered diacritics gets a high diacritic score
        // because it has high density. This is expected behavior.
        $garbledDiacriticScore = $this->comparator->scoreDiacritics($garbledText);
        $this->assertGreaterThan(0.5, $garbledDiacriticScore,
            'High diacritic density gives high diacritic score (expected)');

        // However, the OVERALL comparison should favor clean text
        // because valid word ratio heavily penalizes garbled content
        $result = $this->comparator->compare($garbledText, $cleanText);

        // Clean text should beat garbled text in overall comparison
        $this->assertEquals('tesseract', $result['winner'],
            'Clean text should beat garbled text in overall comparison');

        // Garbled text should have very low overall score due to invalid words
        $this->assertLessThan(0.4, $result['textract_score'],
            'Garbled text should have low overall score due to invalid word ratio');
    }

    /** @test */
    public function provides_normalize_diacritics_method(): void
    {
        // The comparator should provide a method to normalize Croatian diacritics
        // for fair content comparison
        $this->assertTrue(
            method_exists($this->comparator, 'normalizeDiacritics'),
            'OcrQualityComparator should have normalizeDiacritics method for fair comparison'
        );

        // Verify normalization works correctly
        $croatian = 'Članak čćžšđ ČĆŽŠĐ';
        $normalized = $this->comparator->normalizeDiacritics($croatian);

        // Normalized text should replace Croatian diacritics with ASCII equivalents
        // Input:  Članak čćžšđ ČĆŽŠĐ
        // Output: Clanak cczsdz CCZSDZ (č->c, ć->c, ž->z, š->s, đ->dz)
        $this->assertEquals('Clanak cczsdz CCZSDZ', $normalized,
            'Diacritics should be normalized to ASCII equivalents');
    }
}
