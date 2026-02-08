<?php

declare(strict_types=1);

namespace App\DTOs\Ekom\Podnesci;

class PagedPodnesakDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $status,
        public readonly ?string $sudNaziv,
        public readonly ?int $sudId,
        public readonly ?string $vrstaPodneskaOznaka,
        public readonly ?string $predmetOznaka,
        public readonly ?string $vrijemeKreiranja,
        public readonly ?string $vrijemeSlanja,
    ) {}

    public static function fromApiResponse(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            status: $data['status'] ?? 'NACRT',
            sudNaziv: $data['sudNaziv'] ?? null,
            sudId: isset($data['sudId']) ? (int) $data['sudId'] : null,
            vrstaPodneskaOznaka: $data['vrstaPodneskaOznaka'] ?? null,
            predmetOznaka: $data['predmetOznaka'] ?? null,
            vrijemeKreiranja: $data['vrijemeKreiranja'] ?? null,
            vrijemeSlanja: $data['vrijemeSlanja'] ?? null,
        );
    }
}
