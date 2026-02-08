<?php

namespace App\DTOs\Ekom\Predmeti;

class SudskaRadnjaDTO
{
    public function __construct(
        public readonly int $id,
        public readonly ?string $vrsta,
        public readonly ?string $status,
        public readonly ?string $datum,
        public readonly ?string $opis,
    ) {}

    public static function fromApiResponse(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            vrsta: $data['vrsta'] ?? null,
            status: $data['status'] ?? null,
            datum: $data['datum'] ?? null,
            opis: $data['opis'] ?? null,
        );
    }
}
