<?php

namespace App\DTOs\Ekom\Predmeti;

class PredmetDTO
{
    /**
     * @param  SudionikDTO[]  $sudionici
     * @param  SudskaRadnjaDTO[]  $sudskeRadnje
     * @param  DokumentDTO[]  $dokumenti
     * @param  VezaPredmetaDTO[]  $vezePredmeta
     */
    public function __construct(
        public readonly int $id,
        public readonly string $status,
        public readonly string $oznaka,
        public readonly ?SudDTO $sud,
        public readonly ?string $pisarnica,
        public readonly ?string $referada,
        public readonly ?VrstaDTO $vrsta,
        public readonly array $sudionici,
        public readonly array $sudskeRadnje,
        public readonly array $dokumenti,
        public readonly array $vezePredmeta,
        public readonly bool $doNotDisturb,
    ) {}

    public static function fromApiResponse(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            status: $data['status'] ?? '',
            oznaka: $data['oznaka'] ?? '',
            sud: isset($data['sud']) && is_array($data['sud'])
                ? SudDTO::fromApiResponse($data['sud'])
                : null,
            pisarnica: $data['pisarnica'] ?? null,
            referada: $data['referada'] ?? null,
            vrsta: isset($data['vrsta']) && is_array($data['vrsta'])
                ? VrstaDTO::fromApiResponse($data['vrsta'])
                : null,
            sudionici: array_map(
                fn (array $item) => SudionikDTO::fromApiResponse($item),
                $data['sudionici'] ?? []
            ),
            sudskeRadnje: array_map(
                fn (array $item) => SudskaRadnjaDTO::fromApiResponse($item),
                $data['sudskeRadnje'] ?? []
            ),
            dokumenti: array_map(
                fn (array $item) => DokumentDTO::fromApiResponse($item),
                $data['dokumenti'] ?? []
            ),
            vezePredmeta: array_map(
                fn (array $item) => VezaPredmetaDTO::fromApiResponse($item),
                $data['vezePredmeta'] ?? []
            ),
            doNotDisturb: (bool) ($data['doNotDisturb'] ?? false),
        );
    }
}
