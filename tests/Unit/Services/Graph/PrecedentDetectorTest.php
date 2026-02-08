<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Graph\PrecedentDetector;
use Tests\TestCase;

/**
 * TDD Tests for PrecedentDetector
 *
 * Task A.4: Automatic detection of precedent relationships from legal text
 */
class PrecedentDetectorTest extends TestCase
{
    private PrecedentDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new PrecedentDetector();
    }

    /**
     * Test that detector identifies OVERRULES relationship from "ukida" pattern
     *
     * @test
     */
    public function it_detects_overrules_relationship_from_ukida(): void
    {
        $text = 'Sud ukida prethodnu odluku Rev 123/2019 zbog pogrešne primjene zakona.';
        $sourceId = 'decision-456';

        $detected = $this->detector->detect($sourceId, $text);

        $this->assertIsArray($detected);
        $this->assertCount(1, $detected);
        $this->assertEquals('OVERRULES', $detected[0]['relationship_type']);
        $this->assertEquals($sourceId, $detected[0]['source_id']);
        $this->assertStringContainsString('Rev 123/2019', $detected[0]['target_case_number']);
        $this->assertGreaterThan(0, $detected[0]['confidence']);
    }

    /**
     * Test that detector identifies OVERRULES from "ukinuto" pattern
     *
     * @test
     */
    public function it_detects_overrules_relationship_from_ukinuto(): void
    {
        $text = 'Odlukom je ukinuto rješenje Pž 789/2020.';
        $sourceId = 'decision-999';

        $detected = $this->detector->detect($sourceId, $text);

        $this->assertIsArray($detected);
        $this->assertCount(1, $detected);
        $this->assertEquals('OVERRULES', $detected[0]['relationship_type']);
        $this->assertStringContainsString('Pž 789/2020', $detected[0]['target_case_number']);
    }

    /**
     * Test that detector identifies CONFIRMS relationship from "potvrđuje"
     *
     * @test
     */
    public function it_detects_confirms_relationship(): void
    {
        $text = 'Žalbeni sud potvrđuje prvostupanjsku odluku Rev 456/2018.';
        $sourceId = 'decision-789';

        $detected = $this->detector->detect($sourceId, $text);

        $this->assertIsArray($detected);
        $this->assertCount(1, $detected);
        $this->assertEquals('CONFIRMS', $detected[0]['relationship_type']);
        $this->assertEquals($sourceId, $detected[0]['source_id']);
        $this->assertStringContainsString('Rev 456/2018', $detected[0]['target_case_number']);
    }

    /**
     * Test that detector identifies CONFIRMS from "potvrđeno" pattern
     *
     * @test
     */
    public function it_detects_confirms_from_potvrdeno(): void
    {
        $text = 'Potvrđeno je rješenje Gž 111/2021 u cijelosti.';
        $sourceId = 'decision-222';

        $detected = $this->detector->detect($sourceId, $text);

        $this->assertCount(1, $detected);
        $this->assertEquals('CONFIRMS', $detected[0]['relationship_type']);
    }

    /**
     * Test that detector identifies MODIFIES relationship from "preinačuje"
     *
     * @test
     */
    public function it_detects_modifies_relationship(): void
    {
        $text = 'Žalbeni sud preinačuje odluku Rev 789/2019 u dijelu koji se odnosi na naknadu štete.';
        $sourceId = 'decision-333';

        $detected = $this->detector->detect($sourceId, $text);

        $this->assertIsArray($detected);
        $this->assertCount(1, $detected);
        $this->assertEquals('MODIFIES', $detected[0]['relationship_type']);
        $this->assertEquals($sourceId, $detected[0]['source_id']);
        $this->assertStringContainsString('Rev 789/2019', $detected[0]['target_case_number']);
    }

    /**
     * Test that detector identifies MODIFIES from "preinačeno" pattern
     *
     * @test
     */
    public function it_detects_modifies_from_preinaceno(): void
    {
        $text = 'Preinačeno je rješenje Pž 444/2020.';
        $sourceId = 'decision-555';

        $detected = $this->detector->detect($sourceId, $text);

        $this->assertCount(1, $detected);
        $this->assertEquals('MODIFIES', $detected[0]['relationship_type']);
    }

    /**
     * Test that detector identifies FOLLOWS relationship
     *
     * @test
     */
    public function it_detects_follows_relationship(): void
    {
        $text = 'Sud slijedi praksu utvrđenu u predmetu Rev 234/2017 i primjenjuje ista načela.';
        $sourceId = 'decision-666';

        $detected = $this->detector->detect($sourceId, $text);

        $this->assertIsArray($detected);
        $this->assertCount(1, $detected);
        $this->assertEquals('FOLLOWS', $detected[0]['relationship_type']);
        $this->assertEquals($sourceId, $detected[0]['source_id']);
        $this->assertStringContainsString('Rev 234/2017', $detected[0]['target_case_number']);
    }

    /**
     * Test that detector identifies FOLLOWS from "prema ustaljenoj praksi"
     *
     * @test
     */
    public function it_detects_follows_from_ustaljene_prakse(): void
    {
        $text = 'Prema ustaljenoj praksi Vrhovnog suda u predmetu Gž 777/2016, sud odlučuje.';
        $sourceId = 'decision-888';

        $detected = $this->detector->detect($sourceId, $text);

        $this->assertCount(1, $detected);
        $this->assertEquals('FOLLOWS', $detected[0]['relationship_type']);
    }

    /**
     * Test that detector identifies DISTINGUISHES relationship
     *
     * @test
     */
    public function it_detects_distinguishes_relationship(): void
    {
        $text = 'Ovaj slučaj se razlikuje od predmeta Rev 999/2018 zbog različitih činjenica.';
        $sourceId = 'decision-111';

        $detected = $this->detector->detect($sourceId, $text);

        $this->assertIsArray($detected);
        $this->assertCount(1, $detected);
        $this->assertEquals('DISTINGUISHES', $detected[0]['relationship_type']);
        $this->assertEquals($sourceId, $detected[0]['source_id']);
        $this->assertStringContainsString('Rev 999/2018', $detected[0]['target_case_number']);
    }

    /**
     * Test that detector identifies DISTINGUISHES from "ne primjenjuje se"
     *
     * @test
     */
    public function it_detects_distinguishes_from_ne_primjenjuje(): void
    {
        $text = 'Ne primjenjuje se pravno shvaćanje iz predmeta Pž 555/2019.';
        $sourceId = 'decision-222';

        $detected = $this->detector->detect($sourceId, $text);

        $this->assertCount(1, $detected);
        $this->assertEquals('DISTINGUISHES', $detected[0]['relationship_type']);
    }

    /**
     * Test that detector finds multiple relationships in one text
     *
     * @test
     */
    public function it_detects_multiple_relationships(): void
    {
        $text = 'Sud ukida odluku Rev 123/2019 i potvrđuje rješenje Pž 456/2020, ali se razlikuje od prakse u Gž 789/2021.';
        $sourceId = 'decision-complex';

        $detected = $this->detector->detect($sourceId, $text);

        $this->assertIsArray($detected);
        $this->assertCount(3, $detected);

        $types = array_column($detected, 'relationship_type');
        $this->assertContains('OVERRULES', $types);
        $this->assertContains('CONFIRMS', $types);
        $this->assertContains('DISTINGUISHES', $types);
    }

    /**
     * Test that detector handles empty text gracefully
     *
     * @test
     */
    public function it_handles_empty_text(): void
    {
        $detected = $this->detector->detect('decision-id', '');

        $this->assertIsArray($detected);
        $this->assertEmpty($detected);
    }

    /**
     * Test that detector handles text with no matches
     *
     * @test
     */
    public function it_handles_text_with_no_matches(): void
    {
        $text = 'Ovo je običan tekst bez reference na odluke ili predmete.';

        $detected = $this->detector->detect('decision-id', $text);

        $this->assertIsArray($detected);
        $this->assertEmpty($detected);
    }

    /**
     * Test that detector extracts various case number formats
     *
     * @test
     */
    public function it_extracts_various_case_number_formats(): void
    {
        $text = 'Sud ukida Rev 123/2019, potvrđuje Pž-456/2020, i preinačuje Gž-789-2021.';
        $sourceId = 'decision-formats';

        $detected = $this->detector->detect($sourceId, $text);

        $this->assertCount(3, $detected);

        $caseNumbers = array_column($detected, 'target_case_number');
        $this->assertContains('Rev 123/2019', $caseNumbers);
        $this->assertContains('Pž-456/2020', $caseNumbers);
        $this->assertContains('Gž-789-2021', $caseNumbers);
    }

    /**
     * Test that detector includes context snippet
     *
     * @test
     */
    public function it_includes_context_snippet(): void
    {
        $text = 'Prema ustaljenoj praksi Vrhovnog suda u predmetu Rev 123/2019, sud odlučuje.';
        $sourceId = 'decision-context';

        $detected = $this->detector->detect($sourceId, $text);

        $this->assertCount(1, $detected);
        $this->assertArrayHasKey('context', $detected[0]);
        $this->assertNotEmpty($detected[0]['context']);
    }

    /**
     * Test that detector assigns confidence scores
     *
     * @test
     */
    public function it_assigns_confidence_scores(): void
    {
        $text = 'Sud ukida odluku Rev 123/2019.';
        $sourceId = 'decision-confidence';

        $detected = $this->detector->detect($sourceId, $text);

        $this->assertCount(1, $detected);
        $this->assertArrayHasKey('confidence', $detected[0]);
        $this->assertIsFloat($detected[0]['confidence']);
        $this->assertGreaterThan(0, $detected[0]['confidence']);
        $this->assertLessThanOrEqual(1.0, $detected[0]['confidence']);
    }
}
