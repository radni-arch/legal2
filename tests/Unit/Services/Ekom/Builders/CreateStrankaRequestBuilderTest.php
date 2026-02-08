<?php

namespace Tests\Unit\Services\Ekom\Builders;

use App\Services\Ekom\Builders\CreateAdresaRequestBuilder;
use App\Services\Ekom\Builders\CreateStrankaRequestBuilder;
use PHPUnit\Framework\TestCase;

class CreateStrankaRequestBuilderTest extends TestCase
{
    // ===========================================
    // S4-8: Representing Entity Tests
    // ===========================================

    public function test_representing_entity_fields_are_included_in_build(): void
    {
        $stranka = CreateStrankaRequestBuilder::fizickaOsoba()
            ->withName('Ivan', 'Horvat')
            ->withOib('12345678901')
            ->representingEntity('Testni obrt', 'OBRTNI_REGISTAR', '123456')
            ->build();

        $this->assertArrayHasKey('predstavljaniSubjektNaziv', $stranka);
        $this->assertArrayHasKey('predstavljaniSubjektIpsIzvor', $stranka);
        $this->assertArrayHasKey('predstavljaniSubjektIps', $stranka);

        $this->assertSame('Testni obrt', $stranka['predstavljaniSubjektNaziv']);
        $this->assertSame('OBRTNI_REGISTAR', $stranka['predstavljaniSubjektIpsIzvor']);
        $this->assertSame('123456', $stranka['predstavljaniSubjektIps']);
    }

    public function test_representing_entity_is_optional(): void
    {
        $stranka = CreateStrankaRequestBuilder::fizickaOsoba()
            ->withName('Ivan', 'Horvat')
            ->withOib('12345678901')
            ->build();

        $this->assertArrayNotHasKey('predstavljaniSubjektNaziv', $stranka);
        $this->assertArrayNotHasKey('predstavljaniSubjektIpsIzvor', $stranka);
        $this->assertArrayNotHasKey('predstavljaniSubjektIps', $stranka);
    }

    public function test_representing_entity_works_with_pravna_osoba(): void
    {
        $stranka = CreateStrankaRequestBuilder::pravnaOsoba()
            ->withNaziv('Odvjetnicko drustvo')
            ->withOib('98765432109')
            ->representingEntity('Klijent d.o.o.', 'SUDSKI_REGISTAR', '080123456')
            ->build();

        $this->assertSame('Klijent d.o.o.', $stranka['predstavljaniSubjektNaziv']);
        $this->assertSame('SUDSKI_REGISTAR', $stranka['predstavljaniSubjektIpsIzvor']);
        $this->assertSame('080123456', $stranka['predstavljaniSubjektIps']);
    }

    // ===========================================
    // S4-13: Represented Party Flag Tests
    // ===========================================

    public function test_as_represented_party_sets_flag(): void
    {
        $stranka = CreateStrankaRequestBuilder::fizickaOsoba()
            ->withName('Marija', 'Kovac')
            ->withOib('11122233344')
            ->asRepresentedParty()
            ->build();

        $this->assertArrayHasKey('zastupanaStranka', $stranka);
        $this->assertTrue($stranka['zastupanaStranka']);
    }

    public function test_represented_party_flag_is_optional(): void
    {
        $stranka = CreateStrankaRequestBuilder::fizickaOsoba()
            ->withName('Marija', 'Kovac')
            ->withOib('11122233344')
            ->build();

        $this->assertArrayNotHasKey('zastupanaStranka', $stranka);
    }

    public function test_represented_party_with_representing_entity(): void
    {
        $stranka = CreateStrankaRequestBuilder::fizickaOsoba()
            ->withName('Petar', 'Babic')
            ->withOib('55566677788')
            ->representingEntity('Obrt Petar', 'OBRTNI_REGISTAR', '999888')
            ->asRepresentedParty()
            ->build();

        // Both features should work together
        $this->assertTrue($stranka['zastupanaStranka']);
        $this->assertSame('Obrt Petar', $stranka['predstavljaniSubjektNaziv']);
    }

    // ===========================================
    // Existing Builder Functionality Tests
    // ===========================================

    public function test_fizicka_osoba_requires_name_and_oib(): void
    {
        $stranka = CreateStrankaRequestBuilder::fizickaOsoba()
            ->withName('Ana', 'Maric')
            ->withOib('99988877766')
            ->build();

        $this->assertSame('FIZICKA_OSOBA', $stranka['tip']);
        $this->assertSame('Ana', $stranka['ime']);
        $this->assertSame('Maric', $stranka['prezime']);
        $this->assertSame('99988877766', $stranka['oib']);
    }

    public function test_pravna_osoba_requires_naziv_and_oib(): void
    {
        $stranka = CreateStrankaRequestBuilder::pravnaOsoba()
            ->withNaziv('Tvrtka d.o.o.')
            ->withOib('11111111111')
            ->build();

        $this->assertSame('PRAVNA_OSOBA', $stranka['tip']);
        $this->assertSame('Tvrtka d.o.o.', $stranka['naziv']);
        $this->assertSame('11111111111', $stranka['oib']);
    }

    public function test_tijelo_requires_only_naziv(): void
    {
        $stranka = CreateStrankaRequestBuilder::tijelo()
            ->withNaziv('Ministarstvo pravosuda')
            ->build();

        $this->assertSame('TIJELO', $stranka['tip']);
        $this->assertSame('Ministarstvo pravosuda', $stranka['naziv']);
        $this->assertArrayNotHasKey('oib', $stranka);
    }

    public function test_with_role_sets_uloga_id(): void
    {
        $stranka = CreateStrankaRequestBuilder::fizickaOsoba()
            ->withName('Test', 'Person')
            ->withOib('12312312312')
            ->withRole(5)
            ->build();

        $this->assertSame(5, $stranka['ulogaId']);
    }

    public function test_with_address_includes_address_data(): void
    {
        $address = (new CreateAdresaRequestBuilder())
            ->inCroatia()
            ->withPostalCode('10000')
            ->atStreet('Ilica 1');

        $stranka = CreateStrankaRequestBuilder::fizickaOsoba()
            ->withName('Test', 'Person')
            ->withOib('12312312312')
            ->withAddress($address)
            ->build();

        $this->assertArrayHasKey('adresa', $stranka);
        $this->assertSame('10000', $stranka['adresa']['postanskiBroj']);
    }
}
