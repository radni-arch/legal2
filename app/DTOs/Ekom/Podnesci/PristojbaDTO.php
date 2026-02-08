<?php

declare(strict_types=1);

namespace App\DTOs\Ekom\Podnesci;

class PristojbaDTO
{
    public function __construct(
        public readonly ?string $vrsta,
        public readonly ?float $iznos,
        public readonly ?float $placeno,
        public readonly ?float $ostatak,
        public readonly ?string $valuta,
        public readonly ?array $detaljiIzracuna,
        public readonly ?int $razlogNeplacanjaId,
        public readonly ?float $postotakOslobodjenja,
        public readonly ?int $osnovaOslobodjenjaId,
    ) {}

    public static function fromApiResponse(array $data): self
    {
        return new self(
            vrsta: $data['vrsta'] ?? null,
            iznos: isset($data['iznos']) ? (float) $data['iznos'] : null,
            placeno: isset($data['placeno']) ? (float) $data['placeno'] : null,
            ostatak: isset($data['ostatak']) ? (float) $data['ostatak'] : null,
            valuta: $data['valuta'] ?? null,
            detaljiIzracuna: $data['detaljiIzracuna'] ?? null,
            razlogNeplacanjaId: isset($data['razlogNeplacanjaId']) ? (int) $data['razlogNeplacanjaId'] : null,
            postotakOslobodjenja: isset($data['postotakOslobodjenja']) ? (float) $data['postotakOslobodjenja'] : null,
            osnovaOslobodjenjaId: isset($data['osnovaOslobodjenjaId']) ? (int) $data['osnovaOslobodjenjaId'] : null,
        );
    }

    /**
     * Whether there is an outstanding fee balance.
     */
    public function hasOutstandingBalance(): bool
    {
        return $this->ostatak !== null && $this->ostatak > 0;
    }

    /**
     * Whether the fee is fully exempt (100% exemption).
     */
    public function isFullyExempt(): bool
    {
        return $this->postotakOslobodjenja !== null && $this->postotakOslobodjenja >= 100.0;
    }
}
