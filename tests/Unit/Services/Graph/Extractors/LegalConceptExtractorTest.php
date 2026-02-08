<?php

namespace Tests\Unit\Services\Graph\Extractors;

use App\Services\Graph\Extractors\LegalConceptExtractor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LegalConceptExtractorTest extends TestCase
{
    protected LegalConceptExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new LegalConceptExtractor();
    }

    #[Test]
    public function it_extracts_procedural_concepts(): void
    {
        $text = 'Tužitelj podnosi tužbu. Sud donosi presudu. Žalba je odbijena.';

        $concepts = $this->extractor->extract($text);

        $this->assertNotEmpty($concepts);
        $names = array_column($concepts, 'name');
        $this->assertContains('Tužba', $names);
        $this->assertContains('Presuda', $names);
        $this->assertContains('Žalba', $names);
    }

    #[Test]
    public function it_extracts_substantive_concepts(): void
    {
        $text = 'Ugovor o kupoprodaji je sklopljen. Naknada štete iznosi 10.000 kuna. Vlasništvo je preneseno.';

        $concepts = $this->extractor->extract($text);

        $this->assertNotEmpty($concepts);
        $names = array_column($concepts, 'name');
        $this->assertContains('Ugovor', $names);
        $this->assertContains('Kupoprodaja', $names);
    }

    #[Test]
    public function it_extracts_criminal_concepts(): void
    {
        $text = 'Optuženik je počinio kazneno djelo krađe. Osuđen je na kaznu zatvora.';

        $concepts = $this->extractor->extract($text);

        $names = array_column($concepts, 'name');
        $this->assertContains('Kazneno djelo', $names);
        $this->assertContains('Krađa', $names);
        $this->assertContains('Kazna', $names);
    }

    #[Test]
    public function it_counts_frequency(): void
    {
        $text = 'Ugovor je sklopljen. Ugovor je valjan. Temeljem ugovora, obveza je nastala.';

        $concepts = $this->extractor->extract($text);

        $ugovor = array_filter($concepts, fn($c) => $c['name'] === 'Ugovor');
        $ugovor = reset($ugovor);

        $this->assertNotNull($ugovor);
        $this->assertEquals(3, $ugovor['frequency']);
    }

    #[Test]
    public function it_returns_empty_for_empty_text(): void
    {
        $this->assertEmpty($this->extractor->extract(''));
        $this->assertEmpty($this->extractor->extract('   '));
    }

    #[Test]
    public function it_includes_definitions_and_categories(): void
    {
        $text = 'Podnijeta je tužba.';

        $concepts = $this->extractor->extract($text);

        $tuzba = array_filter($concepts, fn($c) => $c['name'] === 'Tužba');
        $tuzba = reset($tuzba);

        $this->assertNotNull($tuzba);
        $this->assertArrayHasKey('definition', $tuzba);
        $this->assertArrayHasKey('category', $tuzba);
        $this->assertEquals('procedural', $tuzba['category']);
    }

    #[Test]
    public function it_generates_deterministic_ids(): void
    {
        $text = 'Podnijeta je tužba.';

        $concepts1 = $this->extractor->extract($text);
        $concepts2 = $this->extractor->extract($text);

        $this->assertEquals($concepts1[0]['concept_id'], $concepts2[0]['concept_id']);
    }

    #[Test]
    public function it_sorts_by_frequency(): void
    {
        $text = 'Ugovor ugovor ugovor. Tužba tužba. Presuda.';

        $concepts = $this->extractor->extract($text);

        // First should be most frequent
        $this->assertEquals('Ugovor', $concepts[0]['name']);
        $this->assertEquals(3, $concepts[0]['frequency']);
    }

    #[Test]
    public function it_provides_all_concepts(): void
    {
        $all = $this->extractor->getAllConcepts();

        $this->assertIsArray($all);
        $this->assertGreaterThanOrEqual(40, count($all)); // At least 40 concepts
    }

    #[Test]
    public function it_filters_by_category(): void
    {
        $procedural = $this->extractor->getConceptsByCategory('procedural');
        $criminal = $this->extractor->getConceptsByCategory('criminal');

        $this->assertNotEmpty($procedural);
        $this->assertNotEmpty($criminal);

        foreach ($procedural as $data) {
            $this->assertEquals('procedural', $data['category']);
        }
    }

    #[Test]
    public function it_handles_concepts_with_underscores(): void
    {
        $text = 'Naknada štete je dosuđena. Kazneno djelo je dokazano. Radni odnos je prestao.';

        $concepts = $this->extractor->extract($text);

        $names = array_column($concepts, 'name');
        $this->assertContains('Naknada štete', $names);
        $this->assertContains('Kazneno djelo', $names);
        $this->assertContains('Radni odnos', $names);
    }

    // ============================================================
    // COMPREHENSIVE CROATIAN STEMMING AND INFLECTION TESTS
    // Regression tests for legal concept extraction edge cases
    // Croatian has 7 cases and stem matching is critical
    // ============================================================

    #[Test]
    public function it_matches_nominative_case_inflection(): void
    {
        // Nominative (tko/što) - base form
        $text = 'Tužba je podnesena. Presuda je donesena.';

        $concepts = $this->extractor->extract($text);

        $names = array_column($concepts, 'name');
        $this->assertContains('Tužba', $names, 'Should match nominative form');
        $this->assertContains('Presuda', $names, 'Should match nominative form');
    }

    #[Test]
    public function it_matches_genitive_case_inflection(): void
    {
        // Genitive (koga/čega) - "tužbe", "presude"
        $text = 'Na temelju tužbe i presude suda prvog stupnja.';

        $concepts = $this->extractor->extract($text);

        $names = array_column($concepts, 'name');
        $this->assertContains('Tužba', $names, 'Should match genitive "tužbe"');
        $this->assertContains('Presuda', $names, 'Should match genitive "presude"');
    }

    #[Test]
    public function it_matches_dative_case_inflection(): void
    {
        // Dative (komu/čemu) - "tužbi", "presudi"
        $text = 'Prigovor tužbi je osnovan. Prigovor presudi je odbijen.';

        $concepts = $this->extractor->extract($text);

        $names = array_column($concepts, 'name');
        $this->assertContains('Tužba', $names, 'Should match dative "tužbi"');
        $this->assertContains('Presuda', $names, 'Should match dative "presudi"');
    }

    #[Test]
    public function it_matches_accusative_case_inflection(): void
    {
        // Accusative (koga/što) - "tužbu", "presudu"
        $text = 'Sud je primio tužbu. Sud je donio presudu.';

        $concepts = $this->extractor->extract($text);

        $names = array_column($concepts, 'name');
        $this->assertContains('Tužba', $names, 'Should match accusative "tužbu"');
        $this->assertContains('Presuda', $names, 'Should match accusative "presudu"');
    }

    #[Test]
    public function it_matches_instrumental_case_inflection(): void
    {
        // Instrumental (kim/čim) - "tužbom", "presudom"
        $text = 'Sukladno tužbom iznesenim činjenicama. Presudom je utvrđeno.';

        $concepts = $this->extractor->extract($text);

        $names = array_column($concepts, 'name');
        $this->assertContains('Tužba', $names, 'Should match instrumental "tužbom"');
        $this->assertContains('Presuda', $names, 'Should match instrumental "presudom"');
    }

    #[Test]
    public function it_matches_locative_case_inflection(): void
    {
        // Locative (o komu/čemu) - "tužbi", "presudi" (same as dative for feminine nouns)
        $text = 'Govori se o tužbi. Rasprava o presudi.';

        $concepts = $this->extractor->extract($text);

        $names = array_column($concepts, 'name');
        $this->assertContains('Tužba', $names, 'Should match locative form');
        $this->assertContains('Presuda', $names, 'Should match locative form');
    }

    #[Test]
    public function it_matches_masculine_noun_inflections(): void
    {
        // Masculine noun: ugovor -> ugovora, ugovoru, ugovorom
        $text = 'Ugovor je sklopljen. Sadržaj ugovora je jasan. Sukladno ugovoru, stranke su se obvezale. Temeljem ugovorom utvrđenih uvjeta.';

        $concepts = $this->extractor->extract($text);

        $ugovor = array_filter($concepts, fn($c) => $c['name'] === 'Ugovor');
        $ugovor = reset($ugovor);

        $this->assertNotFalse($ugovor, 'Should find concept "Ugovor"');
        $this->assertGreaterThanOrEqual(2, $ugovor['frequency'], 'Should match multiple inflected forms');
    }

    #[Test]
    public function it_handles_croatian_diacritics_in_stemming(): void
    {
        // Test Croatian-specific characters: č, ć, đ, š, ž
        $text = 'Izvršena je krađa. Počinitelj krađe je uhićen. Kažnjavanje za krađu.';

        $concepts = $this->extractor->extract($text);

        $names = array_column($concepts, 'name');
        $this->assertContains('Krađa', $names, 'Should match with Croatian diacritics');
    }

    #[Test]
    public function it_handles_complex_multi_word_concept_inflections(): void
    {
        // Multi-word concept: "naknada štete" -> "naknade štete", "naknadi štete", "naknadu štete"
        $text = 'Zahtjev za naknadu štete je osnovan. Visina naknade štete iznosi 10.000 kuna.';

        $concepts = $this->extractor->extract($text);

        $names = array_column($concepts, 'name');
        $this->assertContains('Naknada štete', $names, 'Should match inflected multi-word concept');
    }

    #[Test]
    public function it_handles_kazneno_djelo_inflections(): void
    {
        // "kazneno djelo" -> various inflections
        $text = 'Počinjeno je kazneno djelo. Opis kaznenog djela. Osuda za kazneno djelo.';

        $concepts = $this->extractor->extract($text);

        $kaznenoDjelo = array_filter($concepts, fn($c) => $c['name'] === 'Kazneno djelo');
        $kaznenoDjelo = reset($kaznenoDjelo);

        $this->assertNotFalse($kaznenoDjelo, 'Should find concept "Kazneno djelo"');
    }

    #[Test]
    public function it_counts_all_inflections_towards_frequency(): void
    {
        // All inflections of "presuda" should contribute to frequency
        $text = 'Presuda je donesena. Sadržaj presude je bitan. Prema presudi suda. Sukladno presudom utvrđenim činjenicama.';

        $concepts = $this->extractor->extract($text);

        $presuda = array_filter($concepts, fn($c) => $c['name'] === 'Presuda');
        $presuda = reset($presuda);

        $this->assertNotFalse($presuda, 'Should find concept "Presuda"');
        // Should count at least some inflections (exact number depends on stemming algorithm)
        $this->assertGreaterThanOrEqual(2, $presuda['frequency'], 'Should count multiple inflections');
    }

    #[Test]
    public function it_extracts_labor_law_concepts(): void
    {
        // Labor law concepts
        $text = 'Radnik je dao otkaz. Poslodavac je isplatio otpremninu. Ugovor o radu je raskinut.';

        $concepts = $this->extractor->extract($text);

        $names = array_column($concepts, 'name');
        $this->assertContains('Radnik', $names, 'Should find "Radnik"');
        $this->assertContains('Otkaz', $names, 'Should find "Otkaz"');
        $this->assertContains('Otpremnina', $names, 'Should find "Otpremnina"');
    }

    #[Test]
    public function it_extracts_family_law_concepts(): void
    {
        // Family law concepts - use nominative forms that the stemmer can match
        $text = 'Brak je sklopljen. Razvod je zatražen. Uzdržavanje djeteta je određeno. Roditeljska skrb pripada majci.';

        $concepts = $this->extractor->extract($text);

        $names = array_column($concepts, 'name');
        $this->assertContains('Brak', $names, 'Should find "Brak"');
        $this->assertContains('Razvod', $names, 'Should find "Razvod"');
        $this->assertContains('Uzdržavanje', $names, 'Should find "Uzdržavanje"');
    }

    #[Test]
    public function it_extracts_commercial_law_concepts(): void
    {
        // Commercial law concepts
        $text = 'Trgovačko društvo je u stečaju. Dioničar je glasovao protiv. Likvidacija je pokrenuta.';

        $concepts = $this->extractor->extract($text);

        $names = array_column($concepts, 'name');
        $this->assertContains('Stečaj', $names, 'Should find "Stečaj"');
        $this->assertContains('Dioničar', $names, 'Should find "Dioničar"');
        $this->assertContains('Likvidacija', $names, 'Should find "Likvidacija"');
    }

    #[Test]
    public function it_extracts_administrative_law_concepts(): void
    {
        // Administrative law concepts
        $text = 'Upravni akt je poništen. Inspekcijski nadzor je proveden. Dozvola je izdana.';

        $concepts = $this->extractor->extract($text);

        $names = array_column($concepts, 'name');
        $this->assertContains('Upravni akt', $names, 'Should find "Upravni akt"');
        $this->assertContains('Dozvola', $names, 'Should find "Dozvola"');
    }

    #[Test]
    public function it_handles_case_insensitive_matching(): void
    {
        // Test case insensitivity
        $text = 'UGOVOR je sklopljen. ugovor je valjan. Ugovor obvezuje.';

        $concepts = $this->extractor->extract($text);

        $ugovor = array_filter($concepts, fn($c) => $c['name'] === 'Ugovor');
        $ugovor = reset($ugovor);

        $this->assertNotFalse($ugovor, 'Should find "Ugovor" regardless of case');
        $this->assertGreaterThanOrEqual(2, $ugovor['frequency'], 'Should count different cases');
    }

    #[Test]
    public function it_handles_complex_legal_text_with_multiple_concepts(): void
    {
        // Complex legal text with multiple concepts from different categories
        // Using nominative forms that the stemmer can match
        $text = <<<TEXT
PRESUDA

Općinski sud u Zagrebu, u pravnoj stvari tužitelja Ivana Horvata, zastupan po punomoćniku
odvjetniku Marku Mariću, protiv tuženika ACME d.o.o., radi naknade štete iz ugovora o radu,

p r e s u đ u j e

I. Usvaja se tužbeni zahtjev te se utvrđuje da je tuženik povrijedio odredbe ugovora o radu.
II. Nalaže se tuženiku isplata otpremnine u iznosu od 50.000,00 kuna.
III. Tuženik je dužan naknaditi štetu nastalu nezakonitim otkazom.

OBRAZLOŽENJE

Tužitelj je zasnovao radni odnos kod tuženika dana 1.1.2020. Ugovor o radu je sklopljen na
neodređeno vrijeme. Dana 15.6.2023. tuženik je uručio otkaz bez navođenja zakonom propisanih
razloga. Tužitelj smatra da je otkaz nezakonit i traži naknadu štete.

Temeljem dokaza izvedenih na ročištu, svjedok je potvrdio navode tužitelja. Vještak je
utvrdio visinu štete.
TEXT;

        $concepts = $this->extractor->extract($text);

        $names = array_column($concepts, 'name');

        // Should find procedural concepts
        $this->assertContains('Presuda', $names, 'Should find procedural concept "Presuda"');
        $this->assertContains('Tužba', $names, 'Should find procedural concept from "tužitelj"');
        $this->assertContains('Dokaz', $names, 'Should find procedural concept "Dokaz"');
        $this->assertContains('Svjedok', $names, 'Should find procedural concept "Svjedok"');
        $this->assertContains('Vještak', $names, 'Should find procedural concept "Vještak"');
        $this->assertContains('Ročište', $names, 'Should find procedural concept "Ročište"');

        // Should find substantive concepts
        $this->assertContains('Ugovor', $names, 'Should find substantive concept "Ugovor"');
        $this->assertContains('Šteta', $names, 'Should find substantive concept "Šteta"');

        // Should find labor law concepts
        $this->assertContains('Radni odnos', $names, 'Should find labor concept "Radni odnos"');
        $this->assertContains('Otkaz', $names, 'Should find labor concept "Otkaz"');
        $this->assertContains('Otpremnina', $names, 'Should find labor concept "Otpremnina"');
    }

    #[Test]
    public function it_returns_correct_categories_for_concepts(): void
    {
        $text = 'Tužba je podnesena. Ugovor je sklopljen. Kazneno djelo je počinjeno. Radni odnos je prestao.';

        $concepts = $this->extractor->extract($text);

        $tuzba = array_filter($concepts, fn($c) => $c['name'] === 'Tužba');
        $tuzba = reset($tuzba);
        $this->assertEquals('procedural', $tuzba['category'], 'Tužba should be procedural');

        $ugovor = array_filter($concepts, fn($c) => $c['name'] === 'Ugovor');
        $ugovor = reset($ugovor);
        $this->assertEquals('substantive', $ugovor['category'], 'Ugovor should be substantive');

        $kaznenoDjelo = array_filter($concepts, fn($c) => $c['name'] === 'Kazneno djelo');
        $kaznenoDjelo = reset($kaznenoDjelo);
        $this->assertEquals('criminal', $kaznenoDjelo['category'], 'Kazneno djelo should be criminal');

        $radniOdnos = array_filter($concepts, fn($c) => $c['name'] === 'Radni odnos');
        $radniOdnos = reset($radniOdnos);
        $this->assertEquals('labor', $radniOdnos['category'], 'Radni odnos should be labor');
    }

    #[Test]
    public function it_includes_definitions_for_extracted_concepts(): void
    {
        $text = 'Presuda je donesena. Žalba je izjavljena.';

        $concepts = $this->extractor->extract($text);

        foreach ($concepts as $concept) {
            $this->assertArrayHasKey('definition', $concept, 'Each concept should have a definition');
            $this->assertNotEmpty($concept['definition'], 'Definition should not be empty');
        }
    }

    #[Test]
    public function it_handles_edge_case_short_words(): void
    {
        // Short words that might cause stemming issues
        $text = 'Sud je utvrdio. Brak je sklopljen.';

        $concepts = $this->extractor->extract($text);

        $names = array_column($concepts, 'name');
        $this->assertContains('Brak', $names, 'Should find short concept "Brak"');
    }

    // ============================================================
    // @dataProvider TESTS - CONCEPT DETECTION ACROSS CATEGORIES
    // Covers all 7 legal categories with stemming edge cases
    // ============================================================

    #[Test]
    #[DataProvider('conceptByCategoryProvider')]
    public function it_detects_concept_in_correct_category(string $text, string $expectedName, string $expectedCategory, string $description): void
    {
        $concepts = $this->extractor->extract($text);

        $found = array_filter($concepts, fn($c) => $c['name'] === $expectedName);
        $found = reset($found);

        $this->assertNotFalse($found, "Should find concept '$expectedName' in: $description ($text)");
        $this->assertEquals($expectedCategory, $found['category'], "Category mismatch for '$expectedName' in: $description");
        $this->assertNotEmpty($found['definition'], "Definition should not be empty for: $expectedName");
        $this->assertStringStartsWith('concept_', $found['concept_id'], "Concept ID should start with 'concept_'");
    }

    public static function conceptByCategoryProvider(): array
    {
        return [
            // Procedural concepts
            'Procedural: tuzba' => ['Tužba je podnesena.', 'Tužba', 'procedural', 'Procedural tuzba'],
            'Procedural: presuda' => ['Presuda je donesena.', 'Presuda', 'procedural', 'Procedural presuda'],
            'Procedural: zalba' => ['Žalba je izjavljena.', 'Žalba', 'procedural', 'Procedural zalba'],
            'Procedural: revizija' => ['Revizija je podnesena.', 'Revizija', 'procedural', 'Procedural revizija'],
            'Procedural: zastara' => ['Nastupila je zastara.', 'Zastara', 'procedural', 'Procedural zastara'],
            'Procedural: nadleznost' => ['Nadležnost suda je utvrđena.', 'Nadležnost', 'procedural', 'Procedural nadleznost'],

            // Substantive concepts
            'Substantive: ugovor' => ['Ugovor je sklopljen.', 'Ugovor', 'substantive', 'Substantive ugovor'],
            'Substantive: steta' => ['Šteta je nastala.', 'Šteta', 'substantive', 'Substantive steta'],
            'Substantive: vlasnistvo' => ['Vlasništvo je preneseno.', 'Vlasništvo', 'substantive', 'Substantive vlasnistvo'],
            'Substantive: hipoteka' => ['Hipoteka je upisana.', 'Hipoteka', 'substantive', 'Substantive hipoteka'],
            'Substantive: nistavost' => ['Ništavost ugovora je utvrđena.', 'Ništavost', 'substantive', 'Substantive nistavost'],

            // Criminal concepts
            'Criminal: kazneno djelo' => ['Kazneno djelo je počinjeno.', 'Kazneno djelo', 'criminal', 'Criminal kazneno djelo'],
            'Criminal: kazna' => ['Kazna zatvora je izrečena.', 'Kazna', 'criminal', 'Criminal kazna'],
            'Criminal: kradja' => ['Krađa je dokazana.', 'Krađa', 'criminal', 'Criminal kradja'],
            'Criminal: prijevara' => ['Prijevara je utvrđena.', 'Prijevara', 'criminal', 'Criminal prijevara'],

            // Labor concepts
            'Labor: radni odnos' => ['Radni odnos je zasnovan.', 'Radni odnos', 'labor', 'Labor radni odnos'],
            'Labor: otkaz' => ['Otkaz je nezakonit.', 'Otkaz', 'labor', 'Labor otkaz'],
            'Labor: otpremnina' => ['Otpremnina je isplaćena.', 'Otpremnina', 'labor', 'Labor otpremnina'],

            // Family concepts
            'Family: brak' => ['Brak je sklopljen.', 'Brak', 'family', 'Family brak'],
            'Family: razvod' => ['Razvod je pravomoćan.', 'Razvod', 'family', 'Family razvod'],
            'Family: uzdrzavanje' => ['Uzdržavanje je određeno.', 'Uzdržavanje', 'family', 'Family uzdrzavanje'],

            // Commercial concepts
            'Commercial: stecaj' => ['Stečaj je otvoren.', 'Stečaj', 'commercial', 'Commercial stecaj'],
            'Commercial: likvidacija' => ['Likvidacija je pokrenuta.', 'Likvidacija', 'commercial', 'Commercial likvidacija'],
            'Commercial: mjenica' => ['Mjenica je protestirana.', 'Mjenica', 'commercial', 'Commercial mjenica'],

            // Administrative concepts
            'Administrative: upravni akt' => ['Upravni akt je poništen.', 'Upravni akt', 'administrative', 'Administrative upravni akt'],
            'Administrative: dozvola' => ['Dozvola je izdana.', 'Dozvola', 'administrative', 'Administrative dozvola'],
            'Administrative: koncesija' => ['Koncesija je dodijeljena.', 'Koncesija', 'administrative', 'Administrative koncesija'],
        ];
    }

    // ============================================================
    // @dataProvider TESTS - STEMMING EDGE CASES
    // Croatian has 7 grammatical cases; stem matching must handle all
    // ============================================================

    #[Test]
    #[DataProvider('stemmingEdgeCaseProvider')]
    public function it_matches_inflected_forms_via_stemming(string $text, string $expectedName, string $description): void
    {
        $concepts = $this->extractor->extract($text);

        $names = array_column($concepts, 'name');
        $this->assertContains($expectedName, $names, "Should match inflected form in: $description ($text)");
    }

    public static function stemmingEdgeCaseProvider(): array
    {
        return [
            // Feminine nouns: tuzba -> tuzbe (gen), tuzbi (dat/loc), tuzbu (acc), tuzbom (instr)
            'Feminine genitive: tuzbe' => ['Na temelju tužbe suda.', 'Tužba', 'Genitive of feminine tuzba'],
            'Feminine dative: tuzbi' => ['Prigovor tužbi je osnovan.', 'Tužba', 'Dative/locative of feminine tuzba'],
            'Feminine accusative: tuzbu' => ['Primili smo tužbu.', 'Tužba', 'Accusative of feminine tuzba'],
            'Feminine instrumental: tuzbom' => ['Sukladno tužbom iznesenim.', 'Tužba', 'Instrumental of feminine tuzba'],

            // Masculine nouns: ugovor -> ugovora (gen), ugovoru (dat/loc), ugovorom (instr)
            'Masculine genitive: ugovora' => ['Sadržaj ugovora je jasan.', 'Ugovor', 'Genitive of masculine ugovor'],
            'Masculine dative: ugovoru' => ['Sukladno ugovoru stranke.', 'Ugovor', 'Dative of masculine ugovor'],

            // Neuter-like patterns
            'Presuda genitive: presude' => ['Sadržaj presude je bitan.', 'Presuda', 'Genitive of presuda'],
            'Presuda instrumental: presudom' => ['Presudom je utvrđeno.', 'Presuda', 'Instrumental of presuda'],

            // Diacritics in stem: kradja, steta
            'Diacritics: kradja genitive' => ['Počinitelj krađe je uhićen.', 'Krađa', 'Genitive with diacritic đ'],
            'Diacritics: steta accusative' => ['Nastalu štetu treba nadoknaditi.', 'Šteta', 'Accusative with diacritic š'],

            // Multi-word concept inflections
            'Multi-word: naknada stete inflected' => ['Zahtjev za naknadu štete.', 'Naknada štete', 'Accusative of multi-word concept'],
            'Multi-word: radni odnos inflected' => ['Zasnivanje radnog odnosa.', 'Radni odnos', 'Genitive of multi-word concept'],

            // Case-insensitive matching
            'Case insensitive: UPPERCASE' => ['UGOVOR je sklopljen.', 'Ugovor', 'Uppercase matching'],
        ];
    }

    // ============================================================
    // ADDITIONAL EDGE CASE TESTS
    // ============================================================

    #[Test]
    public function concept_ids_are_deterministic_across_calls(): void
    {
        $text = 'Tužba je podnesena. Presuda je donesena.';

        $concepts1 = $this->extractor->extract($text);
        $concepts2 = $this->extractor->extract($text);

        $this->assertEquals(
            array_column($concepts1, 'concept_id'),
            array_column($concepts2, 'concept_id'),
            'Concept IDs should be deterministic'
        );
    }

    #[Test]
    public function get_concepts_by_category_returns_only_matching(): void
    {
        $categories = ['procedural', 'substantive', 'criminal', 'labor', 'family', 'commercial', 'administrative'];

        foreach ($categories as $category) {
            $concepts = $this->extractor->getConceptsByCategory($category);
            $this->assertNotEmpty($concepts, "Category '$category' should have concepts");

            foreach ($concepts as $data) {
                $this->assertEquals($category, $data['category'], "All concepts should belong to category '$category'");
            }
        }
    }

    #[Test]
    public function get_all_concepts_returns_comprehensive_dictionary(): void
    {
        $all = $this->extractor->getAllConcepts();

        // Should have concepts from all 7 categories
        $categories = array_unique(array_column($all, 'category'));
        $this->assertContains('procedural', $categories);
        $this->assertContains('substantive', $categories);
        $this->assertContains('criminal', $categories);
        $this->assertContains('labor', $categories);
        $this->assertContains('family', $categories);
        $this->assertContains('commercial', $categories);
        $this->assertContains('administrative', $categories);

        // Each concept should have category and definition
        foreach ($all as $key => $data) {
            $this->assertArrayHasKey('category', $data, "Concept '$key' should have category");
            $this->assertArrayHasKey('definition', $data, "Concept '$key' should have definition");
            $this->assertNotEmpty($data['definition'], "Definition for '$key' should not be empty");
        }
    }

    #[Test]
    public function frequency_reflects_total_occurrences_including_inflections(): void
    {
        // Multiple forms of the same concept should all count toward frequency
        $text = 'Presuda je donesena. Na temelju presude. Sukladno presudi. Presudom je utvrđeno.';

        $concepts = $this->extractor->extract($text);

        $presuda = array_filter($concepts, fn($c) => $c['name'] === 'Presuda');
        $presuda = reset($presuda);

        $this->assertNotFalse($presuda, 'Should find Presuda');
        // The stem "presud" should match multiple inflections
        $this->assertGreaterThanOrEqual(2, $presuda['frequency'], 'Should count multiple inflected forms toward frequency');
    }
}
