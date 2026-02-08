<?php

declare(strict_types=1);

namespace App\DTOs\Ekom\Podnesci;

class AdresaDTO
{
    public function __construct(
        public readonly ?int $drzavaId,
        public readonly ?string $drzavaNaziv,
        public readonly ?int $zupanijaId,
        public readonly ?int $opcinaId,
        public readonly ?int $naseljeId,
        public readonly ?string $postanskiBroj,
        public readonly ?string $ulicaIKucniBroj,
    ) {}

    public static function fromApiResponse(array $data): self
    {
        return new self(
            drzavaId: isset($data['drzavaId']) ? (int) $data['drzavaId'] : null,
            drzavaNaziv: $data['drzavaNaziv'] ?? null,
            zupanijaId: isset($data['zupanijaId']) ? (int) $data['zupanijaId'] : null,
            opcinaId: isset($data['opcinaId']) ? (int) $data['opcinaId'] : null,
            naseljeId: isset($data['naseljeId']) ? (int) $data['naseljeId'] : null,
            postanskiBroj: $data['postanskiBroj'] ?? null,
            ulicaIKucniBroj: $data['ulicaIKucniBroj'] ?? null,
        );
    }
}
