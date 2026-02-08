<?php

namespace Tests\Unit\Services\Graph\Extractors;

use App\Services\Graph\Extractors\EvidenceExtractor;
use Tests\TestCase;

class EvidenceExtractorTest extends TestCase
{
    protected EvidenceExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new EvidenceExtractor();
    }

    /** @test */
    public function it_extracts_documentary_evidence()
    {
        $text = "Sud je razmatrao isprave br. 123 od 15.01.2024. Prilogom broj 5 dokazan je zahtjev. " .
                "Ugovor o kupoprodaji od 10.03.2023 priložen je kao dokaz.";

        $evidence = $this->extractor->extract($text, 'case-123');

        $this->assertNotEmpty($evidence);

        $documentary = array_filter($evidence, fn($e) => $e['evidence_type'] === 'documentary');
        $this->assertNotEmpty($documentary, 'Should find documentary evidence');

        $firstDoc = array_values($documentary)[0];
        $this->assertEquals('case-123', $firstDoc['case_id']);
        $this->assertEquals('documentary', $firstDoc['evidence_type']);
        $this->assertNotEmpty($firstDoc['description']);
        $this->assertArrayHasKey('admitted', $firstDoc);
        $this->assertArrayHasKey('weight', $firstDoc);
        $this->assertArrayHasKey('ruling', $firstDoc);
    }

    /** @test */
    public function it_extracts_testimonial_evidence()
    {
        $text = "Svjedok Ivan Horvat je izjavio da je vidio incident. " .
                "Saslušanje svjedoka provedeno je 20.02.2024. " .
                "Stranka je potvrdila činjenice.";

        $evidence = $this->extractor->extract($text, 'case-456');

        $this->assertNotEmpty($evidence);

        $testimonial = array_filter($evidence, fn($e) => $e['evidence_type'] === 'testimonial');
        $this->assertNotEmpty($testimonial, 'Should find testimonial evidence');

        $firstTest = array_values($testimonial)[0];
        $this->assertEquals('case-456', $firstTest['case_id']);
        $this->assertEquals('testimonial', $firstTest['evidence_type']);
        $this->assertNotEmpty($firstTest['description']);
    }

    /** @test */
    public function it_extracts_expert_evidence()
    {
        $text = "Vještačenje dr. Marić pokazuje tehničke nedostatke. " .
                "Nalaz i mišljenje sudskog vještaka priložen je sudu. " .
                "Stručno mišljenje potvrđuje oštećenje.";

        $evidence = $this->extractor->extract($text, 'case-789');

        $this->assertNotEmpty($evidence);

        $expert = array_filter($evidence, fn($e) => $e['evidence_type'] === 'expert');
        $this->assertNotEmpty($expert, 'Should find expert evidence');

        $firstExpert = array_values($expert)[0];
        $this->assertEquals('case-789', $firstExpert['case_id']);
        $this->assertEquals('expert', $firstExpert['evidence_type']);
        $this->assertNotEmpty($firstExpert['description']);
    }

    /** @test */
    public function it_extracts_physical_evidence()
    {
        $text = "Očevid je proveden na licu mjesta 10.01.2024. " .
                "Fotografije mjesta događaja priložene su kao dokaz. " .
                "Materijalni dokaz označen je kao predmet A.";

        $evidence = $this->extractor->extract($text, 'case-101');

        $this->assertNotEmpty($evidence);

        $physical = array_filter($evidence, fn($e) => $e['evidence_type'] === 'physical');
        $this->assertNotEmpty($physical, 'Should find physical evidence');

        $firstPhysical = array_values($physical)[0];
        $this->assertEquals('case-101', $firstPhysical['case_id']);
        $this->assertEquals('physical', $firstPhysical['evidence_type']);
        $this->assertNotEmpty($firstPhysical['description']);
    }

    /** @test */
    public function it_determines_evidence_weight_as_decisive()
    {
        $text = "Isprava broj 5 je odlučujući dokaz u ovom slučaju. " .
                "Sud se temelji na ovom ključnom dokumentu.";

        $evidence = $this->extractor->extract($text);

        $this->assertNotEmpty($evidence);

        $decisive = array_filter($evidence, fn($e) => $e['weight'] === 'decisive');
        $this->assertNotEmpty($decisive, 'Should identify decisive weight');
    }

    /** @test */
    public function it_determines_evidence_weight_as_corroborative()
    {
        $text = "Dokument broj 3 potkrepljuje tvrdnje tužitelja. " .
                "Ova isprava dodatno potvrđuje činjenice.";

        $evidence = $this->extractor->extract($text);

        $this->assertNotEmpty($evidence);

        $corroborative = array_filter($evidence, fn($e) => $e['weight'] === 'corroborative');
        $this->assertNotEmpty($corroborative, 'Should identify corroborative weight');
    }

    /** @test */
    public function it_determines_evidence_weight_as_rejected()
    {
        $text = "Sud odbija dokument broj 7 jer nema dokaznu snagu. " .
                "Ova isprava se ne prihvaća kao valjani dokaz.";

        $evidence = $this->extractor->extract($text);

        $this->assertNotEmpty($evidence);

        $rejected = array_filter($evidence, fn($e) => $e['weight'] === 'rejected');
        $this->assertNotEmpty($rejected, 'Should identify rejected weight');
    }

    /** @test */
    public function it_determines_admission_status_as_admitted()
    {
        $text = "Isprava broj 10 je prihvaćena kao dokaz. " .
                "Dokument je dopušten i izveden pred sudom.";

        $evidence = $this->extractor->extract($text);

        $this->assertNotEmpty($evidence);

        $admitted = array_filter($evidence, fn($e) => $e['admitted'] === true);
        $this->assertNotEmpty($admitted, 'Should identify admitted evidence');
    }

    /** @test */
    public function it_determines_admission_status_as_excluded()
    {
        $text = "Dokument broj 12 je odbijen kao nedopušten. " .
                "Isprava je isključena iz dokaznog postupka.";

        $evidence = $this->extractor->extract($text);

        $this->assertNotEmpty($evidence);

        $excluded = array_filter($evidence, fn($e) => $e['admitted'] === false);
        $this->assertNotEmpty($excluded, 'Should identify excluded evidence');
    }

    /** @test */
    public function it_determines_ruling_as_excluded()
    {
        $text = "Sud isključuje ispravu broj 15 iz razmatranja. " .
                "Dokument je odbijen kao nedopušten.";

        $evidence = $this->extractor->extract($text);

        $this->assertNotEmpty($evidence);

        $excluded = array_filter($evidence, fn($e) => $e['ruling'] === 'excluded');
        $this->assertNotEmpty($excluded, 'Should identify excluded ruling');
    }

    /** @test */
    public function it_determines_ruling_as_limited()
    {
        $text = "Dokument broj 20 prihvaćen je ograničeno. " .
                "Isprava se djelomično priznaje kao dokaz.";

        $evidence = $this->extractor->extract($text);

        $this->assertNotEmpty($evidence);

        $limited = array_filter($evidence, fn($e) => $e['ruling'] === 'limited');
        $this->assertNotEmpty($limited, 'Should identify limited ruling');
    }

    /** @test */
    public function it_returns_empty_array_for_empty_text()
    {
        $this->assertEmpty($this->extractor->extract(''));
        $this->assertEmpty($this->extractor->extract('   '));
        $this->assertEmpty($this->extractor->extract("\n\t"));
    }

    /** @test */
    public function it_returns_empty_array_for_text_without_evidence()
    {
        $text = "Ovo je običan tekst bez ikakvog dokaza ili svjedočenja.";

        $evidence = $this->extractor->extract($text);

        $this->assertEmpty($evidence);
    }

    /** @test */
    public function it_provides_evidence_types()
    {
        $types = $this->extractor->getEvidenceTypes();

        $this->assertIsArray($types);
        $this->assertContains('documentary', $types);
        $this->assertContains('testimonial', $types);
        $this->assertContains('expert', $types);
        $this->assertContains('physical', $types);
        $this->assertCount(4, $types);
    }

    /** @test */
    public function it_deduplicates_evidence()
    {
        $text = "Isprava broj 5 je ključna. Isprava broj 5 je odlučujući dokaz. Isprava broj 5 je prihvaćena.";

        $evidence = $this->extractor->extract($text);

        // Should not have exact duplicates based on the same match text
        $descriptions = array_map(fn($e) => $e['description'], $evidence);
        $unique = array_unique($descriptions);

        // We expect some deduplication to occur
        $this->assertLessThanOrEqual(2, count($evidence), 'Should deduplicate similar evidence mentions');
    }

    /** @test */
    public function it_generates_valid_ulid_for_evidence_id()
    {
        $text = "Isprava broj 123 je priložena.";

        $evidence = $this->extractor->extract($text);

        $this->assertNotEmpty($evidence);
        $this->assertNotEmpty($evidence[0]['id']);
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $evidence[0]['id']);
    }

    /** @test */
    public function it_handles_croatian_characters_correctly()
    {
        $text = "Svjedok Šimić je izjavio. Vještak Čović dao je mišljenje. " .
                "Račun iz Đakovice priložen je kao dokaz.";

        $evidence = $this->extractor->extract($text);

        $this->assertNotEmpty($evidence);

        // Should extract evidence with Croatian characters
        $hasTestimonial = !empty(array_filter($evidence, fn($e) => $e['evidence_type'] === 'testimonial'));
        $hasExpert = !empty(array_filter($evidence, fn($e) => $e['evidence_type'] === 'expert'));
        $hasDocumentary = !empty(array_filter($evidence, fn($e) => $e['evidence_type'] === 'documentary'));

        $this->assertTrue($hasTestimonial || $hasExpert || $hasDocumentary,
            'Should handle Croatian characters in evidence extraction');
    }

    /** @test */
    public function it_extracts_multiple_evidence_types_from_same_text()
    {
        $text = "Sud je razmatrao ispavu broj 10 i saslušao svjedoka Marić. " .
                "Vještačenje je pokazalo tehničke nedostatke. " .
                "Očevid je proveden na mjestu događaja.";

        $evidence = $this->extractor->extract($text, 'case-multi');

        $this->assertNotEmpty($evidence);

        $types = array_unique(array_map(fn($e) => $e['evidence_type'], $evidence));
        $this->assertGreaterThan(1, count($types), 'Should extract multiple evidence types');

        // All should have the same case_id
        foreach ($evidence as $e) {
            $this->assertEquals('case-multi', $e['case_id']);
        }
    }
}
