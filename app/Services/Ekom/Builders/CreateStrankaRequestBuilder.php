<?php

namespace App\Services\Ekom\Builders;

use InvalidArgumentException;

/**
 * Fluent builder for CreateStrankaRequest.
 *
 * Party types:
 * - FIZICKA_OSOBA (natural person): requires ime, prezime, oib
 * - PRAVNA_OSOBA (legal entity): requires naziv, oib
 * - TIJELO (body/organization): requires naziv
 */
class CreateStrankaRequestBuilder
{
    private string $tip;

    private ?string $ime = null;

    private ?string $prezime = null;

    private ?string $naziv = null;

    private ?string $oib = null;

    private ?int $ulogaId = null;

    private ?array $adresa = null;

    // S4-8: Represented entity fields
    private ?string $predstavljaniSubjektNaziv = null;

    private ?string $predstavljaniSubjektIpsIzvor = null;

    private ?string $predstavljaniSubjektIps = null;

    // S4-13: Represented party flag
    private bool $zastupanaStranka = false;

    /**
     * Create builder for natural person.
     */
    public static function fizickaOsoba(): self
    {
        $builder = new self();
        $builder->tip = 'FIZICKA_OSOBA';

        return $builder;
    }

    /**
     * Create builder for legal entity.
     */
    public static function pravnaOsoba(): self
    {
        $builder = new self();
        $builder->tip = 'PRAVNA_OSOBA';

        return $builder;
    }

    /**
     * Create builder for body/organization.
     */
    public static function tijelo(): self
    {
        $builder = new self();
        $builder->tip = 'TIJELO';

        return $builder;
    }

    /**
     * Set name for natural person.
     */
    public function withName(string $ime, string $prezime): self
    {
        $this->ime = $ime;
        $this->prezime = $prezime;

        return $this;
    }

    /**
     * Set naziv for legal entity or body.
     */
    public function withNaziv(string $naziv): self
    {
        $this->naziv = $naziv;

        return $this;
    }

    /**
     * Set OIB (Croatian personal identification number).
     */
    public function withOib(string $oib): self
    {
        $this->oib = $oib;

        return $this;
    }

    /**
     * Set participant role.
     */
    public function withRole(int $ulogaId): self
    {
        $this->ulogaId = $ulogaId;

        return $this;
    }

    /**
     * Set address using a builder.
     */
    public function withAddress(CreateAdresaRequestBuilder $addressBuilder): self
    {
        $this->adresa = $addressBuilder->build();

        return $this;
    }

    /**
     * Set address from array.
     */
    public function withAddressArray(array $adresa): self
    {
        $this->adresa = $adresa;

        return $this;
    }

    /**
     * Set represented entity details (S4-8).
     *
     * Used when the party is representing another entity (e.g., a sole proprietorship).
     *
     * @param string $naziv Entity name (e.g., "Testni obrt")
     * @param string $ipsIzvor Source registry (e.g., "OBRTNI_REGISTAR", "SUDSKI_REGISTAR")
     * @param string $ips Registry identification number
     */
    public function representingEntity(string $naziv, string $ipsIzvor, string $ips): self
    {
        $this->predstavljaniSubjektNaziv = $naziv;
        $this->predstavljaniSubjektIpsIzvor = $ipsIzvor;
        $this->predstavljaniSubjektIps = $ips;

        return $this;
    }

    /**
     * Mark this party as represented by a legal representative (S4-13).
     *
     * Required when submitter role is Odvjetnik (lawyer) in new proceedings.
     */
    public function asRepresentedParty(): self
    {
        $this->zastupanaStranka = true;

        return $this;
    }

    /**
     * Build the request payload.
     */
    public function build(): array
    {
        $this->validate();

        $payload = [
            'tip' => $this->tip,
        ];

        if ($this->tip === 'FIZICKA_OSOBA') {
            $payload['ime'] = $this->ime;
            $payload['prezime'] = $this->prezime;
            $payload['oib'] = $this->oib;
        } elseif ($this->tip === 'PRAVNA_OSOBA') {
            $payload['naziv'] = $this->naziv;
            $payload['oib'] = $this->oib;
        } else { // TIJELO
            $payload['naziv'] = $this->naziv;
        }

        if ($this->ulogaId !== null) {
            $payload['ulogaId'] = $this->ulogaId;
        }

        if ($this->adresa !== null) {
            $payload['adresa'] = $this->adresa;
        }

        // S4-8: Represented entity
        if ($this->predstavljaniSubjektNaziv !== null) {
            $payload['predstavljaniSubjektNaziv'] = $this->predstavljaniSubjektNaziv;
            $payload['predstavljaniSubjektIpsIzvor'] = $this->predstavljaniSubjektIpsIzvor;
            $payload['predstavljaniSubjektIps'] = $this->predstavljaniSubjektIps;
        }

        // S4-13: Represented party flag
        if ($this->zastupanaStranka) {
            $payload['zastupanaStranka'] = true;
        }

        return $payload;
    }

    /**
     * Validate required fields.
     */
    private function validate(): void
    {
        $errors = [];

        if ($this->tip === 'FIZICKA_OSOBA') {
            if (empty($this->ime)) {
                $errors[] = 'First name (ime) is required for FIZICKA_OSOBA';
            }
            if (empty($this->prezime)) {
                $errors[] = 'Last name (prezime) is required for FIZICKA_OSOBA';
            }
            if (empty($this->oib)) {
                $errors[] = 'OIB is required for FIZICKA_OSOBA';
            }
        } elseif ($this->tip === 'PRAVNA_OSOBA') {
            if (empty($this->naziv)) {
                $errors[] = 'Name (naziv) is required for PRAVNA_OSOBA';
            }
            if (empty($this->oib)) {
                $errors[] = 'OIB is required for PRAVNA_OSOBA';
            }
        } else { // TIJELO
            if (empty($this->naziv)) {
                $errors[] = 'Name (naziv) is required for TIJELO';
            }
        }

        if (! empty($errors)) {
            throw new InvalidArgumentException(implode('; ', $errors));
        }
    }
}
