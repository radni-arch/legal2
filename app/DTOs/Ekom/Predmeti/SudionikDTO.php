<?php

namespace App\DTOs\Ekom\Predmeti;

class SudionikDTO
{
    public function __construct(
        public readonly int $id,
        public readonly ?string $ime,
        public readonly ?string $prezime,
        public readonly ?string $naziv,
        public readonly ?string $oib,
        public readonly ?string $uloga,
        public readonly ?string $tip,
    ) {}

    public static function fromApiResponse(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            ime: $data['ime'] ?? null,
            prezime: $data['prezime'] ?? null,
            naziv: $data['naziv'] ?? null,
            oib: $data['oib'] ?? null,
            uloga: $data['uloga'] ?? null,
            tip: $data['tip'] ?? null,
        );
    }
}
