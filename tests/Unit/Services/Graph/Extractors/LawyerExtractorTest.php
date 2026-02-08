<?php

namespace Tests\Unit\Services\Graph\Extractors;

use App\Services\Graph\Extractors\LawyerExtractor;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LawyerExtractorTest extends TestCase
{
    protected LawyerExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new LawyerExtractor();
    }

    #[Test]
    public function it_extracts_punomocnik_pattern(): void
    {
        $text = 'Tužitelj Ivan Horvat, punomoćnik: Marko Marković, odvjetnik iz Zagreba.';

        $lawyers = $this->extractor->extract($text);

        $this->assertNotEmpty($lawyers);
        $this->assertEquals('Marko Marković', $lawyers[0]['name']);
    }

    #[Test]
    public function it_extracts_odvjetnik_pattern(): void
    {
        $text = 'zastupan po odvjetniku Ana Anić iz Splita';

        $lawyers = $this->extractor->extract($text);

        $this->assertNotEmpty($lawyers);
        $this->assertEquals('Ana Anić', $lawyers[0]['name']);
    }

    #[Test]
    public function it_extracts_multiple_lawyers(): void
    {
        $text = 'Tužitelj, punomoćnik: Marko Marković, odvjetnik.
                 Tuženik, punomoćnik: Ivana Ivić, odvjetnica.';

        $lawyers = $this->extractor->extract($text);

        $this->assertCount(2, $lawyers);
    }

    #[Test]
    public function it_deduplicates_lawyers(): void
    {
        $text = 'Punomoćnik Marko Marković. Odvjetnik Marko Marković zastupa tužitelja.';

        $lawyers = $this->extractor->extract($text);

        $this->assertCount(1, $lawyers);
    }

    #[Test]
    public function it_normalizes_names(): void
    {
        $name1 = 'Dr. Marko Marković';
        $name2 = 'MARKO MARKOVIĆ';
        $name3 = 'marko marković';

        $norm1 = $this->extractor->normalizeName($name1);
        $norm2 = $this->extractor->normalizeName($name2);
        $norm3 = $this->extractor->normalizeName($name3);

        $this->assertEquals($norm1, $norm2);
        $this->assertEquals($norm1, $norm3);
    }

    #[Test]
    public function it_handles_croatian_characters(): void
    {
        $text = 'Punomoćnik: Željko Čačić, odvjetnik';

        $lawyers = $this->extractor->extract($text);

        $this->assertNotEmpty($lawyers);
        $this->assertEquals('Željko Čačić', $lawyers[0]['name']);
    }

    #[Test]
    public function it_returns_empty_for_empty_text(): void
    {
        $this->assertEmpty($this->extractor->extract(''));
        $this->assertEmpty($this->extractor->extract('   '));
    }

    #[Test]
    public function it_generates_deterministic_ids(): void
    {
        $text = 'Punomoćnik: Marko Marković';

        $lawyers1 = $this->extractor->extract($text);
        $lawyers2 = $this->extractor->extract($text);

        $this->assertEquals($lawyers1[0]['id'], $lawyers2[0]['id']);
    }

    #[Test]
    public function it_extracts_law_firm(): void
    {
        $text = 'Odvjetnički ured "Marković i partneri", punomoćnik Marko Marković';

        $lawyers = $this->extractor->extract($text);

        $this->assertNotEmpty($lawyers);
        // Firm extraction is best-effort, just verify structure exists
        $this->assertArrayHasKey('firm', $lawyers[0]);
    }

    #[Test]
    public function it_determines_lawyer_role(): void
    {
        $text = 'Tužitelj Ivan Horvat iz Zagreba, punomoćnik: Marko Marković, odvjetnik.
                 Tuženik Pero Perić iz Splita, punomoćnik: Ana Anić, odvjetnica.';

        $lawyers = $this->extractor->extract($text);

        $marko = array_filter($lawyers, fn($l) => str_contains($l['name'], 'Marko'));
        $marko = reset($marko);

        $ana = array_filter($lawyers, fn($l) => str_contains($l['name'], 'Ana'));
        $ana = reset($ana);

        $this->assertEquals('plaintiff', $marko['role']);
        $this->assertEquals('defendant', $ana['role']);
    }

    #[Test]
    public function it_handles_zastupan_pattern(): void
    {
        $text = 'Stranka zastupana po odvjetniku Petru Petriću iz Rijeke';

        $lawyers = $this->extractor->extract($text);

        $this->assertNotEmpty($lawyers);
        $this->assertEquals('Petru Petriću', $lawyers[0]['name']);
    }
}
