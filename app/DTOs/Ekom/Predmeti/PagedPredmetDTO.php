<?php

namespace App\DTOs\Ekom\Predmeti;

class PagedPredmetDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $oznaka,
        public readonly string $status,
        public readonly ?string $sudNaziv,
        public readonly ?int $sudId,
        public readonly bool $doNotDisturb,
    ) {}

    public static function fromApiResponse(array $data): self
    {
        $sud = $data['sud'] ?? null;

        return new self(
            id: (int) $data['id'],
            oznaka: $data['oznaka'] ?? '',
            status: $data['status'] ?? '',
            sudNaziv: is_array($sud) ? ($sud['naziv'] ?? null) : null,
            sudId: is_array($sud) ? (isset($sud['id']) ? (int) $sud['id'] : null) : null,
            doNotDisturb: (bool) ($data['doNotDisturb'] ?? false),
        );
    }
}
