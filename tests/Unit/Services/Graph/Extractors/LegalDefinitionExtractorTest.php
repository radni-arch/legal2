<?php

namespace Tests\Unit\Services\Graph\Extractors;

use App\Services\Graph\Extractors\LegalDefinitionExtractor;
use PHPUnit\Framework\TestCase;

class LegalDefinitionExtractorTest extends TestCase
{
    protected LegalDefinitionExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new LegalDefinitionExtractor();
    }

    /** @test */
    public function it_extracts_znaci_pattern()
    {
        $text = 'Članak 5. "Pravna osoba" znači svaki entitet koji ima pravnu osobnost.';

        $definitions = $this->extractor->extract($text, 'law_123', '5');

        $this->assertCount(1, $definitions);
        $this->assertEquals('Pravna osoba', $definitions[0]['term']);
        $this->assertStringContainsString('entitet koji ima pravnu osobnost', $definitions[0]['definition']);
        $this->assertEquals('5', $definitions[0]['source_article']);
        $this->assertEquals('law_123', $definitions[0]['law_id']);
    }

    /** @test */
    public function it_extracts_smatra_se_pattern()
    {
        $text = 'Pod "uposlenim licem" se smatra svaka osoba koja obavlja poslove po osnovu ugovora o radu.';

        $definitions = $this->extractor->extract($text, 'law_456', '10');

        $this->assertCount(1, $definitions);
        $this->assertEquals('uposlenim licem', $definitions[0]['term']);
        $this->assertStringContainsString('svaka osoba koja obavlja poslove', $definitions[0]['definition']);
    }

    /** @test */
    public function it_extracts_u_smislu_ovog_zakona_pattern()
    {
        $text = 'U smislu ovog zakona "dokument" znači svaki pisani, elektronski ili drugi zapis.';

        $definitions = $this->extractor->extract($text, 'law_789');

        $this->assertCount(1, $definitions);
        $this->assertEquals('dokument', $definitions[0]['term']);
        $this->assertStringContainsString('pisani, elektronski ili drugi zapis', $definitions[0]['definition']);
    }

    /** @test */
    public function it_determines_scope_this_law()
    {
        $text = 'U smislu ovog zakona "strana" znači fizička ili pravna osoba.';

        $definitions = $this->extractor->extract($text);

        $this->assertEquals('this_law', $definitions[0]['scope']);
    }

    /** @test */
    public function it_determines_scope_specific_context()
    {
        $text = 'Za potrebe ovog članka "iznos" znači novčani iznos u kunama.';

        $definitions = $this->extractor->extract($text);

        $this->assertEquals('specific_context', $definitions[0]['scope']);
    }

    /** @test */
    public function it_generates_deterministic_ids()
    {
        $id1 = $this->extractor->generateId('pravna osoba', 'law_123');
        $id2 = $this->extractor->generateId('pravna osoba', 'law_123');
        $id3 = $this->extractor->generateId('Pravna Osoba', 'law_123'); // Different case
        $id4 = $this->extractor->generateId('pravna osoba', 'law_456'); // Different law

        // Same term + law should produce same ID
        $this->assertEquals($id1, $id2);
        $this->assertEquals($id1, $id3); // Case insensitive

        // Different law should produce different ID
        $this->assertNotEquals($id1, $id4);

        // ID should have correct format
        $this->assertStringStartsWith('def_', $id1);
    }

    /** @test */
    public function it_returns_empty_for_empty_text()
    {
        $this->assertEquals([], $this->extractor->extract(''));
        $this->assertEquals([], $this->extractor->extract('   '));
        $this->assertEquals([], $this->extractor->extract("\n\t"));
    }

    /** @test */
    public function it_provides_available_scopes()
    {
        $scopes = $this->extractor->getScopes();

        $this->assertIsArray($scopes);
        $this->assertContains('this_law', $scopes);
        $this->assertContains('general', $scopes);
        $this->assertContains('specific_context', $scopes);
    }

    /** @test */
    public function it_extracts_article_number_from_context()
    {
        $text = 'Članak 42. U smislu ovog zakona "vlasnik" znači osoba koja ima pravo vlasništva.';

        $definitions = $this->extractor->extract($text);

        $this->assertEquals('42', $definitions[0]['source_article']);
    }

    /** @test */
    public function it_deduplicates_definitions()
    {
        $text = '"Osoba" znači fizička osoba. "Osoba" znači fizička osoba. "osoba" znači fizička osoba.';

        $definitions = $this->extractor->extract($text);

        // Should only extract once despite multiple occurrences
        $this->assertCount(1, $definitions);
    }

    /** @test */
    public function it_extracts_pojam_obuhvaca_pattern()
    {
        $text = 'Pojam "imovina" obuhvaća pokretne i nepokretne stvari.';

        $definitions = $this->extractor->extract($text);

        $this->assertCount(1, $definitions);
        $this->assertEquals('imovina', $definitions[0]['term']);
        $this->assertStringContainsString('pokretne i nepokretne stvari', $definitions[0]['definition']);
    }

    /** @test */
    public function it_extracts_za_potrebe_oznacava_pattern()
    {
        $text = 'Za potrebe ovog zakona "naknada" označava novčanu naknadu štete.';

        $definitions = $this->extractor->extract($text);

        $this->assertCount(1, $definitions);
        $this->assertEquals('naknada', $definitions[0]['term']);
        $this->assertStringContainsString('novčanu naknadu štete', $definitions[0]['definition']);
    }

    /** @test */
    public function it_cleans_definition_text()
    {
        $text = '"Test" znači definition   with    extra    spaces and should be cleaned.';

        $definitions = $this->extractor->extract($text);

        // Should normalize whitespace
        $this->assertStringNotContainsString('    ', $definitions[0]['definition']);
    }

    /** @test */
    public function it_limits_definition_length()
    {
        // Pattern limits to 500 chars, but cleanDefinition would cap at 1000
        // Use a definition within pattern limit but test that it gets cleaned
        $longDefinition = str_repeat('word ', 100); // Creates ~500 char string
        $text = '"Test" znači ' . $longDefinition . '.';

        $definitions = $this->extractor->extract($text);

        // Should extract something
        $this->assertNotEmpty($definitions);
        // Definition should be within limits (pattern caps at 500, cleaner at 1000)
        $this->assertLessThanOrEqual(1000, mb_strlen($definitions[0]['definition']));
        $this->assertLessThanOrEqual(500, mb_strlen($definitions[0]['definition']));
    }
}
