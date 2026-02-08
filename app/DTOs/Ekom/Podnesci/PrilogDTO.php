<?php

declare(strict_types=1);

namespace App\DTOs\Ekom\Podnesci;

class PrilogDTO
{
    public function __construct(
        public readonly ?int $id,
        public readonly ?string $naziv,
        public readonly ?string $opis,
        public readonly ?string $primjedba,
        public readonly ?int $velicinaBajtovi,
    ) {}

    public static function fromApiResponse(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int) $data['id'] : null,
            naziv: $data['naziv'] ?? null,
            opis: $data['opis'] ?? null,
            primjedba: $data['primjedba'] ?? null,
            velicinaBajtovi: isset($data['velicinaBajtovi']) ? (int) $data['velicinaBajtovi'] : null,
        );
    }
}
