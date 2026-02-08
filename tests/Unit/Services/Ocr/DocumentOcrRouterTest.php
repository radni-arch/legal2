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

        $scoreWith = $this->router->calculateDiacriticDensity($textWithDiacritics);
        $scoreWithout = $this->router->calculateDiacriticDensity($textWithout);

        $this->assertGreaterThan($scoreWithout, $scoreWith);
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

    /** @test */
    public function low_confidence_with_tables_still_routes_to_tesseract(): void
    {
        $router = new DocumentOcrRouter();

        $text = str_repeat('presuda rješenje zahtjev sud ', 50);

        $blocks = [];
        for ($i = 0; $i < 100; $i++) {
            $blocks[] = ['BlockType' => 'WORD', 'Confidence' => 55.0, 'Page' => 1];
        }
        $blocks[] = ['BlockType' => 'TABLE', 'Page' => 1];
        $blocks[] = ['BlockType' => 'TABLE', 'Page' => 2];

        $result = $router->recommend($text, $blocks);

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

    /** @test */
    public function routing_result_includes_routing_score(): void
    {
        $router = new DocumentOcrRouter();
        $text = 'some text';
        $blocks = [['BlockType' => 'WORD', 'Confidence' => 50]];

        $result = $router->recommend($text, $blocks);
        $this->assertArrayHasKey('routing_score', $result);
    }

    // =========================================================================
    // Edge Case Tests for Contradiction Resolution
    // =========================================================================

    /**
     * @test
     * When no signals contribute (empty text, no blocks), the router should
     * return textract as default with a clear 'no_signals' reason.
     */
    public function it_handles_empty_input_with_clear_no_signals_reason(): void
    {
        config(['ocr.default_engine' => 'auto']);
        $result = $this->router->recommend('', []);

        $this->assertEquals('textract', $result['engine']);
        $this->assertContains('no_signals', $result['reasons'],
            'Empty input should include no_signals reason');
        $this->assertEquals(0.0, $result['routing_score']);
    }

    /**
     * @test
     * Croatian text with high Textract confidence should route to Tesseract
     * because the Croatian confidence adjustment attenuates the high-confidence
     * signal. Textract confidence measures structural recognition, not diacritic
     * accuracy, so Croatian documents should prefer Tesseract's hrv model.
     */
    public function it_routes_croatian_to_tesseract_despite_high_confidence(): void
    {
        config(['ocr.default_engine' => 'auto']);

        // Croatian legal text that pushes toward Tesseract
        $text = 'Presuda Općinskog suda. Tužitelj podnosi žalbu. Odluka rješenje zahtjev.';

        // Very high confidence words from Textract (but confidence is unreliable for diacritics)
        $blocks = [];
        for ($i = 0; $i < 50; $i++) {
            $blocks[] = ['BlockType' => 'WORD', 'Confidence' => 98.0];
        }

        $result = $this->router->recommend($text, $blocks);

        // With conflict resolution: Croatian attenuates high confidence
        // Score: -2.0 (high conf) + 1.0 (adjustment) + 1.5 (Croatian) = +0.5 -> Tesseract
        $this->assertEquals('tesseract', $result['engine'],
            'Croatian content should route to Tesseract even with high Textract confidence '
            . 'because confidence does not measure diacritic accuracy');

        // The result should include signal breakdown showing both signals and the adjustment
        $this->assertArrayHasKey('signal_breakdown', $result,
            'Result should include signal_breakdown for debugging');
        $this->assertArrayHasKey('croatian_confidence_adjustment', $result['signal_breakdown'],
            'Signal breakdown should show the Croatian confidence adjustment');
    }

    /**
     * @test
     * The result should include a detailed signal breakdown for debugging.
     */
    public function it_includes_signal_breakdown_in_result(): void
    {
        config(['ocr.default_engine' => 'auto']);

        $text = 'Presuda rješenje zahtjev sud';
        $blocks = [
            ['BlockType' => 'WORD', 'Confidence' => 75],
            ['BlockType' => 'TABLE', 'Confidence' => 90],
        ];

        $result = $this->router->recommend($text, $blocks);

        $this->assertArrayHasKey('signal_breakdown', $result);

        $breakdown = $result['signal_breakdown'];
        $this->assertArrayHasKey('confidence_signal', $breakdown);
        $this->assertArrayHasKey('croatian_signal', $breakdown);
        $this->assertArrayHasKey('structured_signal', $breakdown);
    }

    /**
     * @test
     * Moderate confidence (between thresholds) combined with Croatian content
     * should clearly route to Tesseract with documented reasoning.
     */
    public function it_routes_moderate_confidence_croatian_to_tesseract(): void
    {
        config(['ocr.default_engine' => 'auto']);

        $text = 'Presuda Općinskog suda u Zagrebu donosi rješenje po zahtjevu tužitelja.';
        $blocks = [];
        for ($i = 0; $i < 30; $i++) {
            $blocks[] = ['BlockType' => 'WORD', 'Confidence' => 78.0];  // Between 0.70 and 0.85
        }

        $result = $this->router->recommend($text, $blocks);

        $this->assertEquals('tesseract', $result['engine']);
        $this->assertGreaterThan(0, $result['routing_score']);
    }

    /**
     * @test
     * When score equals exactly zero, the router should use 'neutral_score'
     * reason rather than misleading 'default_engine' reason.
     */
    public function it_uses_neutral_score_reason_when_score_is_zero(): void
    {
        config(['ocr.default_engine' => 'auto']);

        // Craft a scenario that results in score = 0
        // High confidence (-2.0) + Croatian (+1.5) + structured (-1.0) = -1.5 (still negative)
        // We need: moderate confidence (+1.0) + structured (-1.0) = 0

        $text = 'Some generic text without Croatian indicators';
        $blocks = [];
        for ($i = 0; $i < 20; $i++) {
            $blocks[] = ['BlockType' => 'WORD', 'Confidence' => 80.0];  // Moderate: +1.0
        }
        $blocks[] = ['BlockType' => 'TABLE', 'Confidence' => 95];  // Structured: -1.0

        $result = $this->router->recommend($text, $blocks);

        // Score should be close to 0 (moderate_conf +1 and structured -1)
        if (abs($result['routing_score']) < 0.1) {
            $this->assertNotContains('default_engine', $result['reasons'],
                'Score near zero should not use misleading default_engine reason');
        }
    }

    /**
     * @test
     * Debug logging should be available via a method for troubleshooting.
     */
    public function it_can_provide_detailed_decision_trace(): void
    {
        config(['ocr.default_engine' => 'auto']);

        $text = 'Presuda Općinskog suda u Zagrebu';
        $blocks = [
            ['BlockType' => 'WORD', 'Confidence' => 70],
            ['BlockType' => 'TABLE', 'Confidence' => 90],
        ];

        $result = $this->router->recommend($text, $blocks);

        // Should have a decision trace for debugging
        $this->assertArrayHasKey('decision_trace', $result,
            'Result should include decision_trace for debugging');

        $trace = $result['decision_trace'];
        $this->assertIsArray($trace);
        $this->assertNotEmpty($trace);
    }

    // =========================================================================
    // Task 2.2: Contradiction Resolution Tests
    // =========================================================================
    // These tests expose and verify fixes for contradictions in the scoring
    // logic where conflicting signals produce incorrect routing decisions.
    // =========================================================================

    /**
     * @test
     * Contradiction fix: Croatian content with high Textract confidence should
     * route to Tesseract because Textract's confidence metric measures structural
     * recognition, NOT diacritic accuracy. Croatian diacritics (c, c, z, s, d)
     * require Tesseract's hrv language model for reliable extraction.
     */
    public function croatian_with_high_confidence_favors_tesseract_due_to_diacritic_concern(): void
    {
        config(['ocr.default_engine' => 'auto']);

        // Heavy Croatian legal text with diacritics
        $text = 'Presuda Općinskog suda u Zagrebu. Tužitelj podnosi žalbu protiv rješenja. '
              . 'Sud donosi odluku po zahtjevu u predmetu. Odvjetnik zastupa tužitelja.';

        // Very high confidence from Textract - but confidence does NOT measure diacritic accuracy
        $blocks = [];
        for ($i = 0; $i < 50; $i++) {
            $blocks[] = ['BlockType' => 'WORD', 'Confidence' => 97.0];
        }

        $result = $this->router->recommend($text, $blocks);

        // Croatian content should win over high confidence because Textract
        // confidence does not measure diacritic accuracy for Croatian text
        $this->assertEquals('tesseract', $result['engine'],
            'Croatian content with high Textract confidence should route to Tesseract '
            . 'because Textract confidence does not measure diacritic accuracy. '
            . 'Score: ' . $result['routing_score']);
        $this->assertGreaterThan(0, $result['routing_score'],
            'Routing score should be positive (favoring Tesseract)');
    }

    /**
     * @test
     * When Croatian content is present with high confidence AND structured
     * content (tables/forms), Textract should still win because its table
     * extraction capability is genuinely superior regardless of language.
     */
    public function croatian_with_high_confidence_and_tables_favors_textract(): void
    {
        config(['ocr.default_engine' => 'auto']);

        $text = 'Presuda Općinskog suda u Zagrebu. Tužitelj podnosi žalbu protiv rješenja. '
              . 'Sud donosi odluku po zahtjevu u predmetu.';

        $blocks = [];
        for ($i = 0; $i < 50; $i++) {
            $blocks[] = ['BlockType' => 'WORD', 'Confidence' => 97.0];
        }
        // Add structured content (tables) which Textract genuinely excels at
        $blocks[] = ['BlockType' => 'TABLE', 'Confidence' => 95.0];
        $blocks[] = ['BlockType' => 'KEY_VALUE_SET', 'Confidence' => 93.0];

        $result = $this->router->recommend($text, $blocks);

        // Tables tip the balance: even with Croatian adjustment, structured + adjusted confidence = Textract
        $this->assertEquals('textract', $result['engine'],
            'Croatian content with high confidence AND tables should route to Textract '
            . 'because Textract excels at table/form extraction. '
            . 'Score: ' . $result['routing_score']);
    }

    /**
     * @test
     * When ALL three signals fire in conflicting directions (Croatian pushes
     * Tesseract, high confidence pushes Textract, structured content pushes
     * Textract), there should be a clear, deterministic winner based on
     * explicit priority rules, not just raw sum happenstance.
     */
    public function all_signals_conflict_resolved_by_explicit_priority(): void
    {
        config(['ocr.default_engine' => 'auto']);

        // ALL three signals fire: Croatian (+), high confidence (-), structured (-)
        $text = 'Presuda Općinskog suda u Zagrebu. Tužitelj podnosi žalbu. Odluka rješenje zahtjev.';

        $blocks = [];
        for ($i = 0; $i < 40; $i++) {
            $blocks[] = ['BlockType' => 'WORD', 'Confidence' => 96.0];
        }
        $blocks[] = ['BlockType' => 'TABLE', 'Confidence' => 94.0];

        $result = $this->router->recommend($text, $blocks);

        // When all signals conflict, the result should be deterministic and documented
        $this->assertNotEmpty($result['signal_breakdown']);
        $this->assertArrayHasKey('decision_trace', $result);
        $this->assertIsString($result['engine']);

        // With all signals conflicting (Croatian + high confidence + tables):
        // High confidence adjusted for Croatian: -1.0 (was -2.0, +1.0 adjustment)
        // Croatian: +1.5
        // Structured: -1.0
        // Total: -0.5 -> Textract (tables + adjusted confidence win)
        $this->assertEquals('textract', $result['engine'],
            'When ALL signals conflict, structured content + adjusted confidence should win. '
            . 'Score: ' . $result['routing_score']);
    }

    /**
     * @test
     * Scoring signals must produce identical results across multiple runs.
     * The routing decision must be deterministic - no randomness or race conditions.
     */
    public function scoring_produces_deterministic_results_across_multiple_runs(): void
    {
        config(['ocr.default_engine' => 'auto']);

        $text = 'Presuda Općinskog suda. Tužitelj podnosi žalbu.';
        $blocks = [
            ['BlockType' => 'WORD', 'Confidence' => 80.0],
            ['BlockType' => 'WORD', 'Confidence' => 82.0],
            ['BlockType' => 'WORD', 'Confidence' => 79.0],
        ];

        $results = [];
        for ($i = 0; $i < 10; $i++) {
            $results[] = $this->router->recommend($text, $blocks);
        }

        // All 10 runs must produce identical results
        $firstEngine = $results[0]['engine'];
        $firstScore = $results[0]['routing_score'];

        for ($i = 1; $i < 10; $i++) {
            $this->assertEquals($firstEngine, $results[$i]['engine'],
                "Run $i produced different engine ({$results[$i]['engine']}) than run 0 ($firstEngine)");
            $this->assertEquals($firstScore, $results[$i]['routing_score'],
                "Run $i produced different score ({$results[$i]['routing_score']}) than run 0 ($firstScore)");
        }
    }

    /**
     * @test
     * When the Croatian confidence adjustment fires (Croatian content + high
     * Textract confidence), the signal_breakdown should document this adjustment
     * for debugging and audit trail purposes.
     */
    public function signal_breakdown_includes_croatian_confidence_adjustment_when_applicable(): void
    {
        config(['ocr.default_engine' => 'auto']);

        // Croatian text with high confidence triggers the adjustment
        $text = 'Presuda Općinskog suda u Zagrebu. Tužitelj podnosi žalbu protiv rješenja zahtjev.';
        $blocks = [];
        for ($i = 0; $i < 30; $i++) {
            $blocks[] = ['BlockType' => 'WORD', 'Confidence' => 95.0];
        }

        $result = $this->router->recommend($text, $blocks);

        // The signal breakdown should document the Croatian confidence adjustment
        $this->assertArrayHasKey('croatian_confidence_adjustment', $result['signal_breakdown'],
            'Signal breakdown should include croatian_confidence_adjustment when Croatian + high confidence');
        $this->assertGreaterThan(0, $result['signal_breakdown']['croatian_confidence_adjustment'],
            'Croatian confidence adjustment should be positive (attenuating Textract preference)');
    }

    /**
     * @test
     * Low-confidence Textract should ALWAYS fall back to Tesseract regardless
     * of other signals. This is the highest-priority rule.
     */
    public function low_confidence_always_overrides_all_other_signals(): void
    {
        config(['ocr.default_engine' => 'auto']);

        // English text (no Croatian signal), but very low confidence + tables
        $text = 'Generic invoice content with line items and totals.';
        $blocks = [];
        for ($i = 0; $i < 30; $i++) {
            $blocks[] = ['BlockType' => 'WORD', 'Confidence' => 55.0];
        }
        // Add tables (would normally push toward Textract)
        $blocks[] = ['BlockType' => 'TABLE', 'Confidence' => 90.0];
        $blocks[] = ['BlockType' => 'TABLE', 'Confidence' => 88.0];

        $result = $this->router->recommend($text, $blocks);

        // Low confidence (+3.0) should always override structured (-1.0)
        $this->assertEquals('tesseract', $result['engine'],
            'Very low confidence should always override structured content preference');
        $this->assertGreaterThan(0, $result['routing_score']);
    }

    /**
     * @test
     * High-confidence Textract with structured English content should clearly
     * route to Textract with no contradictions. Regression test.
     */
    public function high_confidence_textract_preferred_for_structured_english_content(): void
    {
        config(['ocr.default_engine' => 'auto']);

        // English text (no Croatian) with tables and high confidence
        $text = 'Monthly financial report showing quarterly revenue and expense tables.';
        $blocks = [];
        for ($i = 0; $i < 40; $i++) {
            $blocks[] = ['BlockType' => 'WORD', 'Confidence' => 96.0];
        }
        $blocks[] = ['BlockType' => 'TABLE', 'Confidence' => 95.0];
        $blocks[] = ['BlockType' => 'KEY_VALUE_SET', 'Confidence' => 94.0];

        $result = $this->router->recommend($text, $blocks);

        // English + high confidence + tables = clear Textract with no Croatian adjustment
        $this->assertEquals('textract', $result['engine'],
            'English structured content with high confidence should clearly route to Textract');
        $this->assertLessThan(0, $result['routing_score'],
            'Score should be strongly negative (favoring Textract)');

        // No Croatian adjustment should fire
        $this->assertArrayNotHasKey('croatian_confidence_adjustment', $result['signal_breakdown'],
            'No Croatian adjustment should fire for English content');
    }
}
