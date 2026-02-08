<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ekom\Builders;

use App\Services\Ekom\Builders\PristojbaDodatniPodaciBuilder;
use PHPUnit\Framework\TestCase;

class PristojbaDodatniPodaciBuilderTest extends TestCase
{
    public function test_build_returns_empty_array_when_nothing_set(): void
    {
        $builder = new PristojbaDodatniPodaciBuilder();

        $result = $builder->build();

        $this->assertSame([], $result);
    }

    public function test_with_payer_ordinal_sets_obveznik_placanja_rbr(): void
    {
        // For new proceeding with multiple stranke - use ordinal position of paying party
        $builder = new PristojbaDodatniPodaciBuilder();

        $result = $builder->withPayerOrdinal(2)->build();

        $this->assertSame(['obveznikPlacanjaRbr' => 2], $result);
    }

    public function test_with_payer_name_sets_obveznik_placanja_slobodan_unos(): void
    {
        // For existing case - use free text payer name
        $builder = new PristojbaDodatniPodaciBuilder();

        $result = $builder->withPayerName('Ivan Horvat')->build();

        $this->assertSame(['obveznikPlacanjaSlobodanUnos' => 'Ivan Horvat'], $result);
    }

    public function test_with_fee_option_sets_dodatna_opcija_as_string(): void
    {
        // Fee option is a STRING matching option name from sifrarnici, NOT an ID!
        $builder = new PristojbaDodatniPodaciBuilder();

        $result = $builder->withFeeOption('Podnesak vezan uz uzdrzavanje')->build();

        $this->assertSame(['dodatnaOpcija' => 'Podnesak vezan uz uzdrzavanje'], $result);
    }

    public function test_with_partial_exemption_sets_both_fields(): void
    {
        // Partial exemption requires percentage and osnova ID
        $builder = new PristojbaDodatniPodaciBuilder();

        $result = $builder->withPartialExemption(50.0, 3)->build();

        $this->assertSame([
            'postotakOslobodjenjaDrugaOsnova' => 50.0,
            'osnovaOslobodjenjaDrugaOsnovaId' => 3,
        ], $result);
    }

    public function test_builder_is_chainable(): void
    {
        $builder = new PristojbaDodatniPodaciBuilder();

        $result = $builder
            ->withPayerOrdinal(1)
            ->withFeeOption('Some option')
            ->build();

        $this->assertArrayHasKey('obveznikPlacanjaRbr', $result);
        $this->assertArrayHasKey('dodatnaOpcija', $result);
    }

    public function test_builder_returns_new_instance_on_each_call(): void
    {
        $builder = new PristojbaDodatniPodaciBuilder();

        $builder1 = $builder->withPayerOrdinal(1);
        $builder2 = $builder->withPayerOrdinal(2);

        // Immutable: original should not be modified
        $this->assertNotSame($builder, $builder1);
        $this->assertNotSame($builder, $builder2);

        // Each returns different values
        $this->assertSame(['obveznikPlacanjaRbr' => 1], $builder1->build());
        $this->assertSame(['obveznikPlacanjaRbr' => 2], $builder2->build());
    }

    public function test_payer_ordinal_and_payer_name_are_mutually_exclusive(): void
    {
        // Setting one should clear the other
        $builder = new PristojbaDodatniPodaciBuilder();

        // Set ordinal first, then name - name wins
        $result = $builder
            ->withPayerOrdinal(1)
            ->withPayerName('Test Person')
            ->build();

        $this->assertArrayNotHasKey('obveznikPlacanjaRbr', $result);
        $this->assertSame('Test Person', $result['obveznikPlacanjaSlobodanUnos']);

        // Set name first, then ordinal - ordinal wins
        $result2 = $builder
            ->withPayerName('Test Person')
            ->withPayerOrdinal(3)
            ->build();

        $this->assertArrayNotHasKey('obveznikPlacanjaSlobodanUnos', $result2);
        $this->assertSame(3, $result2['obveznikPlacanjaRbr']);
    }

    public function test_combines_all_fields_correctly(): void
    {
        $builder = new PristojbaDodatniPodaciBuilder();

        $result = $builder
            ->withPayerOrdinal(2)
            ->withFeeOption('Maintenance case')
            ->withPartialExemption(25.5, 7)
            ->build();

        $this->assertSame([
            'obveznikPlacanjaRbr' => 2,
            'dodatnaOpcija' => 'Maintenance case',
            'postotakOslobodjenjaDrugaOsnova' => 25.5,
            'osnovaOslobodjenjaDrugaOsnovaId' => 7,
        ], $result);
    }
}
