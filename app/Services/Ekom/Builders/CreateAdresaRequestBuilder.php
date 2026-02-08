<?php

namespace App\Services\Ekom\Builders;

/**
 * Fluent builder for CreateAdresaRequest.
 *
 * Complex geo-resolution:
 * - Country can be identified by: drzavaId, drzavaNaziv, or drzavaOznaka (e.g., 'HR')
 * - County (županija) by: zupanijaId
 * - Municipality (općina) by: opcinaId
 * - Settlement (naselje) by: naseljeId
 * - Plus: postanskiBroj, ulicaIKucniBroj
 */
class CreateAdresaRequestBuilder
{
    // Country identification (one required)
    private ?int $drzavaId = null;

    private ?string $drzavaNaziv = null;

    private ?string $drzavaOznaka = null;

    // Optional region identifiers
    private ?int $zupanijaId = null;

    private ?int $opcinaId = null;

    private ?int $naseljeId = null;

    // S4-14: Ambiguous settlement name fields
    private ?string $naseljeNaziv = null;

    private ?string $opcinaNaziv = null;

    private ?string $zupanijaNaziv = null;

    // Address details
    private ?string $postanskiBroj = null;

    private ?string $ulicaIKucniBroj = null;

    /**
     * Set country by ID.
     */
    public function inCountry(int $drzavaId): self
    {
        $this->drzavaId = $drzavaId;
        $this->drzavaNaziv = null;
        $this->drzavaOznaka = null;

        return $this;
    }

    /**
     * Set country by name.
     */
    public function inCountryByName(string $drzavaNaziv): self
    {
        $this->drzavaNaziv = $drzavaNaziv;
        $this->drzavaId = null;
        $this->drzavaOznaka = null;

        return $this;
    }

    /**
     * Set country by code (e.g., 'HR').
     */
    public function inCountryByCode(string $drzavaOznaka): self
    {
        $this->drzavaOznaka = $drzavaOznaka;
        $this->drzavaId = null;
        $this->drzavaNaziv = null;

        return $this;
    }

    /**
     * Set Croatia as country (shortcut).
     */
    public function inCroatia(): self
    {
        return $this->inCountryByCode('HR');
    }

    /**
     * Set county (županija).
     */
    public function inCounty(int $zupanijaId): self
    {
        $this->zupanijaId = $zupanijaId;

        return $this;
    }

    /**
     * Set municipality (općina).
     */
    public function inMunicipality(int $opcinaId): self
    {
        $this->opcinaId = $opcinaId;

        return $this;
    }

    /**
     * Set settlement (naselje).
     */
    public function inSettlement(int $naseljeId): self
    {
        $this->naseljeId = $naseljeId;
        // Clear name-based settlement when using ID
        $this->naseljeNaziv = null;
        $this->opcinaNaziv = null;
        $this->zupanijaNaziv = null;

        return $this;
    }

    /**
     * Set ambiguous settlement by name (S4-14).
     *
     * Use this for settlements with duplicate names (e.g., Novigrad, Privlaka)
     * that require municipality and county names for disambiguation.
     *
     * @param string $naselje Settlement name
     * @param string $opcina Municipality name
     * @param string $zupanija County name
     */
    public function inSettlementAmbiguous(string $naselje, string $opcina, string $zupanija): self
    {
        $this->naseljeNaziv = $naselje;
        $this->opcinaNaziv = $opcina;
        $this->zupanijaNaziv = $zupanija;
        // Clear ID-based settlement when using names
        $this->naseljeId = null;

        return $this;
    }

    /**
     * Set postal code.
     */
    public function withPostalCode(string $postanskiBroj): self
    {
        $this->postanskiBroj = $postanskiBroj;

        return $this;
    }

    /**
     * Set street and house number.
     */
    public function atStreet(string $ulicaIKucniBroj): self
    {
        $this->ulicaIKucniBroj = $ulicaIKucniBroj;

        return $this;
    }

    /**
     * Build the address request payload.
     */
    public function build(): array
    {
        $address = [];

        // Country identification
        if ($this->drzavaId !== null) {
            $address['drzavaId'] = $this->drzavaId;
        } elseif ($this->drzavaNaziv !== null) {
            $address['drzavaNaziv'] = $this->drzavaNaziv;
        } elseif ($this->drzavaOznaka !== null) {
            $address['drzavaOznaka'] = $this->drzavaOznaka;
        }

        // Regional identifiers
        if ($this->zupanijaId !== null) {
            $address['zupanijaId'] = $this->zupanijaId;
        }

        if ($this->opcinaId !== null) {
            $address['opcinaId'] = $this->opcinaId;
        }

        if ($this->naseljeId !== null) {
            $address['naseljeId'] = $this->naseljeId;
        }

        // S4-14: Ambiguous settlement name fields
        if ($this->naseljeNaziv !== null) {
            $address['naseljeNaziv'] = $this->naseljeNaziv;
            $address['opcinaNaziv'] = $this->opcinaNaziv;
            $address['zupanijaNaziv'] = $this->zupanijaNaziv;
        }

        // Address details
        if ($this->postanskiBroj !== null) {
            $address['postanskiBroj'] = $this->postanskiBroj;
        }

        if ($this->ulicaIKucniBroj !== null) {
            $address['ulicaIKucniBroj'] = $this->ulicaIKucniBroj;
        }

        return $address;
    }
}
