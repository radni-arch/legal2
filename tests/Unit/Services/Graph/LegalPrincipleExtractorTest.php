<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Graph\LegalPrincipleExtractor;
use Tests\TestCase;

/**
 * TDD Tests for LegalPrincipleExtractor
 *
 * Phase 2 - Task 2.3: Extract legal principles from court decision text
 */
class LegalPrincipleExtractorTest extends TestCase
{
    private LegalPrincipleExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new LegalPrincipleExtractor;
    }

    /**
     * Test that extractor returns proper structure
     *
     * @test
     */
    public function it_extracts_legal_principles_from_decision_text(): void
    {
        $text = 'Sud utvrđuje pravno načelo da teret dokazivanja leži na tužitelju.';

        $result = $this->extractor->extract($text);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('principles', $result);
        $this->assertArrayHasKey('count', $result);
    }

    /**
     * Test that extractor detects "pravno načelo" pattern
     *
     * @test
     */
    public function it_detects_pravno_nacelo_pattern(): void
    {
        $text = 'Sud zauzima pravno načelo da tuženik mora dokazati svoje tvrdnje.';

        $result = $this->extractor->extract($text);

        $this->assertGreaterThan(0, $result['count']);
        $this->assertStringContainsString('pravno načelo', $result['principles'][0]['pattern_matched']);
    }

    /**
     * Test that extractor detects established judicial practice pattern
     *
     * @test
     */
    public function it_detects_ustaljeno_sudska_praksa_pattern(): void
    {
        $text = 'Prema ustaljenoj sudskoj praksi, ovaj sud drži da se naknada određuje prema tržišnoj vrijednosti.';

        $result = $this->extractor->extract($text);

        $this->assertGreaterThan(0, $result['count']);
    }

    /**
     * Test that extractor captures surrounding context
     *
     * @test
     */
    public function it_captures_surrounding_context(): void
    {
        $text = 'U obrazloženju presude, Sud je istaknuo da prema pravno načelo slobodne ocjene dokaza, sud slobodno ocjenjuje svaki dokaz pojedinačno i sve dokaze zajedno.';

        $result = $this->extractor->extract($text);

        $this->assertGreaterThan(0, $result['count']);
        $this->assertNotEmpty($result['principles'][0]['context']);
    }

    /**
     * Test that extractor handles text without principles
     *
     * @test
     */
    public function it_handles_text_without_principles(): void
    {
        $text = 'Ovo je običan tekst koji ne sadrži nikakva pravna načela.';

        $result = $this->extractor->extract($text);

        $this->assertEquals(0, $result['count']);
        $this->assertEmpty($result['principles']);
    }

    /**
     * Test that extractor finds multiple principles
     *
     * @test
     */
    public function it_finds_multiple_principles(): void
    {
        $text = 'Sud se poziva na pravno načelo jednakosti stranaka. Nadalje, prema ustaljenoj sudskoj praksi, teret dokazivanja leži na onome tko tvrdi.';

        $result = $this->extractor->extract($text);

        $this->assertGreaterThanOrEqual(2, $result['count']);
    }
}
