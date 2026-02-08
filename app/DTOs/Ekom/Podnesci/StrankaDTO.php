<?php

declare(strict_types=1);

namespace App\DTOs\Ekom\Podnesci;

class StrankaDTO
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $tip,
        public readonly ?string $oib,
        public readonly ?string $ime,
        public readonly ?string $prezime,
        public readonly ?string $naziv,
        public readonly ?int $ulogaId,
        public readonly ?string $ulogaNaziv,
        public readonly ?AdresaDTO $adresa,
    ) {}

    public static function fromApiResponse(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int) $data['id'] : null,
            tip: $data['tip'] ?? 'FIZICKA_OSOBA',
            oib: $data['oib'] ?? null,
            ime: $data['ime'] ?? null,
            prezime: $data['prezime'] ?? null,
            naziv: $data['naziv'] ?? null,
            ulogaId: isset($data['ulogaId']) ? (int) $data['ulogaId'] : null,
            ulogaNaziv: $data['ulogaNaziv'] ?? null,
            adresa: isset($data['adresa']) ? AdresaDTO::fromApiResponse($data['adresa']) : null,
        );
    }

    public function isFizickaOsoba(): bool
    {
        return $this->tip === 'FIZICKA_OSOBA';
    }

    public function isPravnaOsoba(): bool
    {
        return $this->tip === 'PRAVNA_OSOBA';
    }

    public function isTijelo(): bool
    {
        return $this->tip === 'TIJELO';
    }
}
