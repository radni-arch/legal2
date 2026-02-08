<?php

declare(strict_types=1);

namespace App\Services\Ekom\Builders;

/**
 * Fluent, immutable builder for pristojbaDodatniPodaci.
 *
 * Patterns:
 * 1. New proceeding + fee + multiple stranke: obveznikPlacanjaRbr (ordinal of paying party)
 * 2. Existing case + fee: obveznikPlacanjaSlobodanUnos (free-text payer name)
 * 3. Fee option selection: dodatnaOpcija is a STRING (not ID!) matching option name from sifrarnici
 * 4. Partial exemption: postotakOslobodjenjaDrugaOsnova (decimal) + osnovaOslobodjenjaDrugaOsnovaId (int)
 */
class PristojbaDodatniPodaciBuilder
{
    private ?int $obveznikPlacanjaRbr = null;

    private ?string $obveznikPlacanjaSlobodanUnos = null;

    private ?string $dodatnaOpcija = null;

    private ?float $postotakOslobodjenjaDrugaOsnova = null;

    private ?int $osnovaOslobodjenjaDrugaOsnovaId = null;

    /**
     * For new proceeding with multiple stranke - set paying party by ordinal.
     *
     * This clears any previously set payer name (mutually exclusive).
     */
    public function withPayerOrdinal(int $rbr): self
    {
        $clone = clone $this;
        $clone->obveznikPlacanjaRbr = $rbr;
        $clone->obveznikPlacanjaSlobodanUnos = null;

        return $clone;
    }

    /**
     * For existing case - set paying party by free-text name.
     *
     * This clears any previously set payer ordinal (mutually exclusive).
     */
    public function withPayerName(string $name): self
    {
        $clone = clone $this;
        $clone->obveznikPlacanjaSlobodanUnos = $name;
        $clone->obveznikPlacanjaRbr = null;

        return $clone;
    }

    /**
     * Set fee option by exact string name (not ID!).
     *
     * The option name must match exactly one of the options from sifrarnici.
     */
    public function withFeeOption(string $optionName): self
    {
        $clone = clone $this;
        $clone->dodatnaOpcija = $optionName;

        return $clone;
    }

    /**
     * Set partial exemption with percentage and osnova ID.
     *
     * @param float $percentage Exemption percentage (e.g., 50.0 for 50%)
     * @param int $osnovaId ID of the exemption basis from sifrarnici
     */
    public function withPartialExemption(float $percentage, int $osnovaId): self
    {
        $clone = clone $this;
        $clone->postotakOslobodjenjaDrugaOsnova = $percentage;
        $clone->osnovaOslobodjenjaDrugaOsnovaId = $osnovaId;

        return $clone;
    }

    /**
     * Build the array payload for pristojbaDodatniPodaci.
     *
     * @return array<string, mixed> The built payload, empty if nothing set
     */
    public function build(): array
    {
        $result = [];

        if ($this->obveznikPlacanjaRbr !== null) {
            $result['obveznikPlacanjaRbr'] = $this->obveznikPlacanjaRbr;
        }

        if ($this->obveznikPlacanjaSlobodanUnos !== null) {
            $result['obveznikPlacanjaSlobodanUnos'] = $this->obveznikPlacanjaSlobodanUnos;
        }

        if ($this->dodatnaOpcija !== null) {
            $result['dodatnaOpcija'] = $this->dodatnaOpcija;
        }

        if ($this->postotakOslobodjenjaDrugaOsnova !== null) {
            $result['postotakOslobodjenjaDrugaOsnova'] = $this->postotakOslobodjenjaDrugaOsnova;
        }

        if ($this->osnovaOslobodjenjaDrugaOsnovaId !== null) {
            $result['osnovaOslobodjenjaDrugaOsnovaId'] = $this->osnovaOslobodjenjaDrugaOsnovaId;
        }

        return $result;
    }
}
