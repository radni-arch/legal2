<?php

namespace App\DTOs\Ekom\Predmeti;

class VezaPredmetaDTO
{
    public function __construct(
        public readonly int $id,
        public readonly ?string $oznaka,
        public readonly ?string $sud,
        public readonly ?string $vrstaVeze,
    ) {}

    public static function fromApiResponse(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            oznaka: $data['oznaka'] ?? null,
            sud: $data['sud'] ?? null,
            vrstaVeze: $data['vrstaVeze'] ?? null,
        );
    }
}
