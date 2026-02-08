<?php

namespace App\DTOs\Ekom;

/**
 * Represents a document attached to an otpravak or other EKOM entity.
 */
class DokumentDTO
{
    public function __construct(
        public readonly int $id,
        public readonly ?string $naziv,
        public readonly ?string $tip,
        public readonly ?string $datum,
        public readonly ?int $velicinaBajtovi,
    ) {}

    public static function fromApiResponse(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            naziv: $data['naziv'] ?? null,
            tip: $data['tip'] ?? null,
            datum: $data['datum'] ?? null,
            velicinaBajtovi: isset($data['velicinaBajtovi']) ? (int) $data['velicinaBajtovi'] : null,
        );
    }
}
