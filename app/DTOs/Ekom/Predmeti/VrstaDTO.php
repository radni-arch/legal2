<?php

namespace App\DTOs\Ekom\Predmeti;

class VrstaDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $naziv,
        public readonly ?string $oznaka,
    ) {}

    public static function fromApiResponse(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            naziv: $data['naziv'] ?? '',
            oznaka: $data['oznaka'] ?? null,
        );
    }
}
