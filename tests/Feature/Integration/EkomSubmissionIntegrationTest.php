<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Services\Ekom\Builders\CreateAdresaRequestBuilder;
use App\Services\Ekom\Builders\CreateEkomPodnesakRequestBuilder;
use App\Services\Ekom\Builders\CreateStrankaRequestBuilder;
use Tests\Fixtures\EkomFixtures;
use Tests\TestCase;

/**
 * Integration tests verifying builder output matches fixture specifications.
 *
 * These tests ensure the builder APIs produce JSON structures that align
 * with the official MPU e-Komunikacija examples.
 */
class EkomSubmissionIntegrationTest extends TestCase
{
    /** @test */
    public function builder_produces_matching_structure_for_novi_postupak_bez_pristojbe(): void
    {
        $expected = EkomFixtures::noviPostupakBezPristojbe();

        // Build using the fluent API
        $builder = (new CreateEkomPodnesakRequestBuilder())
            ->forCourtByOznaka('OGs zg')
            ->asNewProceeding(1, 1) // vrstaPostupkaId, ulogaPodnositeljaId
            ->withSubmissionType(1);

        // Add first stranka (fizicka osoba)
        $stranka1 = CreateStrankaRequestBuilder::fizickaOsoba()
            ->withName('Ivan', 'Horvat')
            ->withOib('12345678901')
            ->withRole(1)
            ->withAddress(
                (new CreateAdresaRequestBuilder())
                    ->inCountryByCode('HR')
                    ->inCounty(1)
                    ->inMunicipality(100)
                    ->inSettlement(1000)
                    ->withPostalCode('10000')
                    ->atStreet('Ilica 1')
            )
            ->build();

        $builder->addStranka($stranka1);

        // Add second stranka (pravna osoba) - simplified, without predstavljaniSubjekt
        $stranka2 = CreateStrankaRequestBuilder::pravnaOsoba()
            ->withNaziv('Primjer d.o.o.')
            ->withOib('98765432109')
            ->withRole(2)
            ->withAddress(
                (new CreateAdresaRequestBuilder())
                    ->inCountryByCode('HR')
                    ->withPostalCode('10000')
                    ->atStreet('Savska 10')
            )
            ->build();

        $builder->addStranka($stranka2);

        // Add third stranka (tijelo)
        $stranka3 = CreateStrankaRequestBuilder::tijelo()
            ->withNaziv('Ministarstvo pravosuđa i uprave')
            ->withRole(3)
            ->withAddress(
                (new CreateAdresaRequestBuilder())
                    ->inCountryByCode('HR')
                    ->withPostalCode('10000')
                    ->atStreet('Ulica grada Vukovara 49')
            )
            ->build();

        $builder->addStranka($stranka3);

        // Add protustranka
        $protustranka = CreateStrankaRequestBuilder::pravnaOsoba()
            ->withNaziv('Protivnik d.d.')
            ->withOib('55566677788')
            ->withRole(4)
            ->withAddress(
                (new CreateAdresaRequestBuilder())
                    ->inCountryByCode('HR')
                    ->withPostalCode('21000')
                    ->atStreet('Vukovarska 1, Split')
            )
            ->build();

        $builder->addProtustranka($protustranka);

        // Add content
        $builder->withContent('tuzba.pdf', base64_encode('test pdf content'), 5);

        // Add prilozi
        $builder->addPrilog('Punomoć za zastupanje', 'punomoc.pdf', base64_encode('doc1'), 1, 'Ovjerena kod javnog bilježnika');
        $builder->addPrilog('Izvod iz sudskog registra', 'izvod_sudski_registar.pdf', base64_encode('doc2'), 3);
        $builder->addPrilog('Dokaz o plaćanju', 'dokaz_placanja.pdf', base64_encode('doc3'), 1, 'Potvrda banke o izvršenoj transakciji', true);

        $actual = $builder->build();

        // Verify structural match
        $this->assertEquals($expected['sudOznaka'], $actual['sudOznaka']);
        $this->assertEquals($expected['vrstaPodneskaId'], $actual['vrstaPodneskaId']);
        $this->assertEquals($expected['vrstaPostupkaId'], $actual['vrstaPostupkaId']);
        $this->assertEquals($expected['ulogaPodnositeljaId'], $actual['ulogaPodnositeljaId']);

        // Verify stranke count and types
        $this->assertCount(count($expected['stranke']), $actual['stranke']);

        // Verify protustranke
        $this->assertCount(count($expected['protustranke']), $actual['protustranke']);

        // Verify prilozi count
        $this->assertCount(count($expected['prilozi']), $actual['prilozi']);

        // Verify sadrzaj structure
        $this->assertArrayHasKey('naziv', $actual['sadrzaj']);
        $this->assertArrayHasKey('sadrzaj', $actual['sadrzaj']);
        $this->assertArrayHasKey('brojStranica', $actual['sadrzaj']);
    }

    /** @test */
    public function existing_case_serializes_empty_arrays(): void
    {
        $fixture = EkomFixtures::postojeciPredmetBezPristojbe();

        // Verify fixture has empty arrays (not null or missing)
        // This is important for API serialization - empty arrays must be present
        $this->assertArrayHasKey('stranke', $fixture);
        $this->assertEmpty($fixture['stranke']);
        $this->assertIsArray($fixture['stranke']);

        $this->assertArrayHasKey('protustranke', $fixture);
        $this->assertEmpty($fixture['protustranke']);
        $this->assertIsArray($fixture['protustranke']);

        // Verify existing case doesn't need court (implied from predmetOznaka)
        $this->assertArrayNotHasKey('sudId', $fixture);
        $this->assertArrayNotHasKey('sudOznaka', $fixture);

        // But it must have predmetOznaka
        $this->assertArrayHasKey('predmetOznaka', $fixture);
        $this->assertEquals('P-1/2023', $fixture['predmetOznaka']);

        // Note: When using the builder for existing cases, court is required for validation
        // but the API may not require it. The builder can be used with court specified,
        // and it will output the proper structure without stranke/protustranke fields.
    }

    /** @test */
    public function fixture_with_fee_additional_data_has_correct_structure(): void
    {
        $fixture = EkomFixtures::noviPostupakSPristojbomIDodatnimPodacima();

        // Verify pristojbaDodatniPodaci structure
        $this->assertArrayHasKey('pristojbaDodatniPodaci', $fixture);
        $feeData = $fixture['pristojbaDodatniPodaci'];

        $this->assertArrayHasKey('vrijednostPredmetaSpora', $feeData);
        $this->assertArrayHasKey('valutaPredmetaSpora', $feeData);
        $this->assertIsFloat($feeData['vrijednostPredmetaSpora']);
        $this->assertEquals('EUR', $feeData['valutaPredmetaSpora']);
    }

    /** @test */
    public function fixture_with_fee_exemption_has_razlog_neplacanja(): void
    {
        $fixture = EkomFixtures::postojeciPredmetBezPristojbeNaTemeljiOpcije();

        $this->assertArrayHasKey('razlogNeplacanjaId', $fixture);
        $this->assertIsInt($fixture['razlogNeplacanjaId']);

        // Fee structure should indicate exemption
        $this->assertArrayHasKey('pristojba', $fixture);
        $this->assertEquals(0, $fixture['pristojba']['iznos']);
    }

    /** @test */
    public function fixture_with_partial_exemption_has_correct_percentage(): void
    {
        $fixture = EkomFixtures::postojeciPredmetSPristojbomIDodatnimPodacima();

        $this->assertArrayHasKey('pristojbaDodatniPodaci', $fixture);
        $feeData = $fixture['pristojbaDodatniPodaci'];

        $this->assertArrayHasKey('postotakOslobodjenja', $feeData);
        $this->assertEquals(50, $feeData['postotakOslobodjenja']);

        $this->assertArrayHasKey('osnovaOslobodjenjaId', $feeData);
        $this->assertIsInt($feeData['osnovaOslobodjenjaId']);
    }

    /** @test */
    public function prilog_fixture_has_correct_sadrzaj_structure(): void
    {
        $fixture = EkomFixtures::prilog();

        $this->assertArrayHasKey('opis', $fixture);
        $this->assertArrayHasKey('primjedba', $fixture);
        $this->assertArrayHasKey('sadrzaj', $fixture);

        $sadrzaj = $fixture['sadrzaj'];
        $this->assertArrayHasKey('naziv', $sadrzaj);
        $this->assertArrayHasKey('sadrzaj', $sadrzaj);
        $this->assertArrayHasKey('brojStranica', $sadrzaj);
        $this->assertArrayHasKey('ignorirajUpozorenja', $sadrzaj);

        $this->assertEquals(2, $sadrzaj['brojStranica']);
        $this->assertTrue($sadrzaj['ignorirajUpozorenja']);
    }

    /** @test */
    public function all_new_proceeding_fixtures_have_required_fields(): void
    {
        $newProceedingFixtures = [
            'bez_pristojbe' => EkomFixtures::noviPostupakBezPristojbe(),
            's_pristojbom_bez_dodatnih' => EkomFixtures::noviPostupakSPristojbomBezDodatnihPodataka(),
            's_pristojbom_i_dodatnim' => EkomFixtures::noviPostupakSPristojbomIDodatnimPodacima(),
        ];

        foreach ($newProceedingFixtures as $name => $fixture) {
            // Must have court identifier (sudId or sudOznaka)
            $this->assertTrue(
                isset($fixture['sudId']) || isset($fixture['sudOznaka']),
                "Fixture '{$name}' must have sudId or sudOznaka"
            );

            // Must have procedure type and submitter role
            $this->assertArrayHasKey('vrstaPostupkaId', $fixture, "Fixture '{$name}' missing vrstaPostupkaId");
            $this->assertArrayHasKey('ulogaPodnositeljaId', $fixture, "Fixture '{$name}' missing ulogaPodnositeljaId");

            // Must have at least one stranka
            $this->assertArrayHasKey('stranke', $fixture, "Fixture '{$name}' missing stranke");
            $this->assertNotEmpty($fixture['stranke'], "Fixture '{$name}' must have at least one stranka");

            // Must have content
            $this->assertArrayHasKey('sadrzaj', $fixture, "Fixture '{$name}' missing sadrzaj");
        }
    }

    /** @test */
    public function all_existing_case_fixtures_have_required_fields(): void
    {
        $existingCaseFixtures = [
            'bez_pristojbe' => EkomFixtures::postojeciPredmetBezPristojbe(),
            'bez_pristojbe_opcija' => EkomFixtures::postojeciPredmetBezPristojbeNaTemeljiOpcije(),
            's_pristojbom' => EkomFixtures::postojeciPredmetSPristojbomIDodatnimPodacima(),
        ];

        foreach ($existingCaseFixtures as $name => $fixture) {
            // Must have case identifier (predmetId or predmetOznaka)
            $this->assertTrue(
                isset($fixture['predmetId']) || isset($fixture['predmetOznaka']),
                "Fixture '{$name}' must have predmetId or predmetOznaka"
            );

            // Must have content
            $this->assertArrayHasKey('sadrzaj', $fixture, "Fixture '{$name}' missing sadrzaj");

            // Should NOT have vrstaPostupkaId or ulogaPodnositeljaId
            // (those are for new proceedings only)
            $this->assertArrayNotHasKey('vrstaPostupkaId', $fixture, "Fixture '{$name}' should not have vrstaPostupkaId");
            $this->assertArrayNotHasKey('ulogaPodnositeljaId', $fixture, "Fixture '{$name}' should not have ulogaPodnositeljaId");
        }
    }

    /** @test */
    public function stranka_builder_produces_correct_fizicka_osoba_structure(): void
    {
        $expected = EkomFixtures::noviPostupakBezPristojbe()['stranke'][0];

        $actual = CreateStrankaRequestBuilder::fizickaOsoba()
            ->withName('Ivan', 'Horvat')
            ->withOib('12345678901')
            ->withRole(1)
            ->withAddress(
                (new CreateAdresaRequestBuilder())
                    ->inCountryByCode('HR')
                    ->inCounty(1)
                    ->inMunicipality(100)
                    ->inSettlement(1000)
                    ->withPostalCode('10000')
                    ->atStreet('Ilica 1')
            )
            ->build();

        $this->assertEquals($expected['tip'], $actual['tip']);
        $this->assertEquals($expected['ime'], $actual['ime']);
        $this->assertEquals($expected['prezime'], $actual['prezime']);
        $this->assertEquals($expected['oib'], $actual['oib']);
        $this->assertEquals($expected['ulogaId'], $actual['ulogaId']);

        // Verify address structure
        $this->assertArrayHasKey('adresa', $actual);
        $this->assertEquals($expected['adresa']['drzavaOznaka'], $actual['adresa']['drzavaOznaka']);
    }

    /** @test */
    public function stranka_builder_produces_correct_pravna_osoba_structure(): void
    {
        $actual = CreateStrankaRequestBuilder::pravnaOsoba()
            ->withNaziv('Test Company d.o.o.')
            ->withOib('12345678901')
            ->withRole(2)
            ->build();

        $this->assertEquals('PRAVNA_OSOBA', $actual['tip']);
        $this->assertEquals('Test Company d.o.o.', $actual['naziv']);
        $this->assertEquals('12345678901', $actual['oib']);
        $this->assertEquals(2, $actual['ulogaId']);

        // PRAVNA_OSOBA should not have ime/prezime
        $this->assertArrayNotHasKey('ime', $actual);
        $this->assertArrayNotHasKey('prezime', $actual);
    }

    /** @test */
    public function stranka_builder_produces_correct_tijelo_structure(): void
    {
        $actual = CreateStrankaRequestBuilder::tijelo()
            ->withNaziv('Ministarstvo pravosuđa')
            ->withRole(3)
            ->build();

        $this->assertEquals('TIJELO', $actual['tip']);
        $this->assertEquals('Ministarstvo pravosuđa', $actual['naziv']);
        $this->assertEquals(3, $actual['ulogaId']);

        // TIJELO should not have oib
        $this->assertArrayNotHasKey('oib', $actual);
    }

    /** @test */
    public function address_builder_supports_all_identification_methods(): void
    {
        // By ID
        $byId = (new CreateAdresaRequestBuilder())
            ->inCountry(1)
            ->build();
        $this->assertArrayHasKey('drzavaId', $byId);
        $this->assertArrayNotHasKey('drzavaNaziv', $byId);
        $this->assertArrayNotHasKey('drzavaOznaka', $byId);

        // By name
        $byName = (new CreateAdresaRequestBuilder())
            ->inCountryByName('Hrvatska')
            ->build();
        $this->assertArrayHasKey('drzavaNaziv', $byName);
        $this->assertArrayNotHasKey('drzavaId', $byName);
        $this->assertArrayNotHasKey('drzavaOznaka', $byName);

        // By code (oznaka)
        $byCode = (new CreateAdresaRequestBuilder())
            ->inCountryByCode('HR')
            ->build();
        $this->assertArrayHasKey('drzavaOznaka', $byCode);
        $this->assertArrayNotHasKey('drzavaId', $byCode);
        $this->assertArrayNotHasKey('drzavaNaziv', $byCode);

        // Shortcut for Croatia
        $croatia = (new CreateAdresaRequestBuilder())
            ->inCroatia()
            ->build();
        $this->assertEquals('HR', $croatia['drzavaOznaka']);
    }

    /** @test */
    public function minimal_fixture_helper_creates_valid_new_proceeding(): void
    {
        $minimal = EkomFixtures::minimalPodnesak(true);

        $this->assertArrayHasKey('sudOznaka', $minimal);
        $this->assertArrayHasKey('vrstaPostupkaId', $minimal);
        $this->assertArrayHasKey('ulogaPodnositeljaId', $minimal);
        $this->assertArrayHasKey('stranke', $minimal);
        $this->assertNotEmpty($minimal['stranke']);
        $this->assertArrayHasKey('sadrzaj', $minimal);
    }

    /** @test */
    public function minimal_fixture_helper_creates_valid_existing_case(): void
    {
        $minimal = EkomFixtures::minimalPodnesak(false);

        $this->assertArrayHasKey('predmetOznaka', $minimal);
        $this->assertArrayNotHasKey('vrstaPostupkaId', $minimal);
        $this->assertArrayNotHasKey('ulogaPodnositeljaId', $minimal);
        $this->assertEmpty($minimal['stranke']);
        $this->assertArrayHasKey('sadrzaj', $minimal);
    }
}
