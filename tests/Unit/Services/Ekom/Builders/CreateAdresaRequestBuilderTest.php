<?php

namespace Tests\Unit\Services\Ekom\Builders;

use App\Services\Ekom\Builders\CreateAdresaRequestBuilder;
use PHPUnit\Framework\TestCase;

class CreateAdresaRequestBuilderTest extends TestCase
{
    // ===========================================
    // S4-14: Ambiguous Settlement Tests
    // ===========================================

    public function test_ambiguous_settlement_includes_all_three_name_fields(): void
    {
        $address = (new CreateAdresaRequestBuilder())
            ->inCroatia()
            ->inSettlementAmbiguous('Novigrad', 'Novigrad', 'Istarska')
            ->withPostalCode('52466')
            ->atStreet('Prolaz Venecija 1')
            ->build();

        $this->assertArrayHasKey('naseljeNaziv', $address);
        $this->assertArrayHasKey('opcinaNaziv', $address);
        $this->assertArrayHasKey('zupanijaNaziv', $address);

        $this->assertSame('Novigrad', $address['naseljeNaziv']);
        $this->assertSame('Novigrad', $address['opcinaNaziv']);
        $this->assertSame('Istarska', $address['zupanijaNaziv']);
    }

    public function test_ambiguous_settlement_for_privlaka(): void
    {
        // Privlaka exists in both Vukovarsko-srijemska and Zadarska county
        $address = (new CreateAdresaRequestBuilder())
            ->inCroatia()
            ->inSettlementAmbiguous('Privlaka', 'Privlaka', 'Zadarska')
            ->withPostalCode('23233')
            ->build();

        $this->assertSame('Privlaka', $address['naseljeNaziv']);
        $this->assertSame('Privlaka', $address['opcinaNaziv']);
        $this->assertSame('Zadarska', $address['zupanijaNaziv']);
    }

    public function test_settlement_by_id_does_not_include_name_fields(): void
    {
        $address = (new CreateAdresaRequestBuilder())
            ->inCroatia()
            ->inSettlement(12345)
            ->withPostalCode('10000')
            ->build();

        $this->assertArrayHasKey('naseljeId', $address);
        $this->assertArrayNotHasKey('naseljeNaziv', $address);
        $this->assertArrayNotHasKey('opcinaNaziv', $address);
        $this->assertArrayNotHasKey('zupanijaNaziv', $address);
    }

    // ===========================================
    // S4-14: ISO Country Code Tests
    // ===========================================

    public function test_country_by_two_letter_iso_code(): void
    {
        $address = (new CreateAdresaRequestBuilder())
            ->inCountryByCode('HR')
            ->withPostalCode('10000')
            ->build();

        $this->assertArrayHasKey('drzavaOznaka', $address);
        $this->assertSame('HR', $address['drzavaOznaka']);
    }

    public function test_country_by_three_letter_iso_code(): void
    {
        $address = (new CreateAdresaRequestBuilder())
            ->inCountryByCode('HRV')
            ->withPostalCode('10000')
            ->build();

        $this->assertArrayHasKey('drzavaOznaka', $address);
        $this->assertSame('HRV', $address['drzavaOznaka']);
    }

    public function test_country_by_two_letter_foreign(): void
    {
        $address = (new CreateAdresaRequestBuilder())
            ->inCountryByCode('DE')
            ->withPostalCode('10115')
            ->atStreet('Unter den Linden 1')
            ->build();

        $this->assertSame('DE', $address['drzavaOznaka']);
    }

    public function test_country_by_three_letter_foreign(): void
    {
        $address = (new CreateAdresaRequestBuilder())
            ->inCountryByCode('DEU')
            ->withPostalCode('10115')
            ->build();

        $this->assertSame('DEU', $address['drzavaOznaka']);
    }

    // ===========================================
    // Existing Functionality Tests
    // ===========================================

    public function test_in_croatia_shortcut(): void
    {
        $address = (new CreateAdresaRequestBuilder())
            ->inCroatia()
            ->build();

        $this->assertSame('HR', $address['drzavaOznaka']);
    }

    public function test_country_by_id(): void
    {
        $address = (new CreateAdresaRequestBuilder())
            ->inCountry(1)
            ->build();

        $this->assertArrayHasKey('drzavaId', $address);
        $this->assertSame(1, $address['drzavaId']);
        $this->assertArrayNotHasKey('drzavaOznaka', $address);
        $this->assertArrayNotHasKey('drzavaNaziv', $address);
    }

    public function test_country_by_name(): void
    {
        $address = (new CreateAdresaRequestBuilder())
            ->inCountryByName('Hrvatska')
            ->build();

        $this->assertArrayHasKey('drzavaNaziv', $address);
        $this->assertSame('Hrvatska', $address['drzavaNaziv']);
    }

    public function test_full_address_with_all_fields(): void
    {
        $address = (new CreateAdresaRequestBuilder())
            ->inCroatia()
            ->inCounty(21)
            ->inMunicipality(42)
            ->inSettlement(100)
            ->withPostalCode('10000')
            ->atStreet('Ilica 1')
            ->build();

        $this->assertSame('HR', $address['drzavaOznaka']);
        $this->assertSame(21, $address['zupanijaId']);
        $this->assertSame(42, $address['opcinaId']);
        $this->assertSame(100, $address['naseljeId']);
        $this->assertSame('10000', $address['postanskiBroj']);
        $this->assertSame('Ilica 1', $address['ulicaIKucniBroj']);
    }

    public function test_minimal_address_only_country(): void
    {
        $address = (new CreateAdresaRequestBuilder())
            ->inCroatia()
            ->build();

        $this->assertSame(['drzavaOznaka' => 'HR'], $address);
    }

    public function test_country_methods_are_mutually_exclusive(): void
    {
        // Setting country by ID clears other country fields
        $address = (new CreateAdresaRequestBuilder())
            ->inCountryByCode('HR')
            ->inCountry(1) // This should clear drzavaOznaka
            ->build();

        $this->assertArrayHasKey('drzavaId', $address);
        $this->assertArrayNotHasKey('drzavaOznaka', $address);
    }
}
