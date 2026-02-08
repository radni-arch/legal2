<?php

namespace App\DTOs\Ekom\Predmeti;

class SudDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $naziv,
        public readonly ?string $oznaka,
        public readonly ?array $vrsta,
    ) {}

    public static function fromApiResponse(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            naziv: $data['naziv'] ?? '',
            oznaka: $data['oznaka'] ?? null,
            vrsta: $data['vrsta'] ?? null,
        );
    }
}
