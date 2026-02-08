<?php

declare(strict_types=1);

namespace App\DTOs\Ekom\Podnesci;

class EkomPodnesakDTO
{
    /**
     * @param  StrankaDTO[]  $stranke
     * @param  StrankaDTO[]  $protustranke
     * @param  PrilogDTO[]  $prilozi
     * @param  array  $prosljedjivanja  Array of forwarding data
     * @param  array|null  $sadrzaj  Main document content data
     * @param  string|null  $statusValidacijePotpisa  Signature validation status (PRIHVATLJIV, NEPRIHVATLJIV, etc.)
     */
    public function __construct(
        public readonly int $id,
        public readonly string $status,
        public readonly ?int $sudId,
        public readonly ?string $sudNaziv,
        public readonly ?int $predmetId,
        public readonly ?string $predmetOznaka,
        public readonly ?int $vrstaPostupkaId,
        public readonly ?int $vrstaPodneskaId,
        public readonly ?string $vrstaPodneskaOznaka,
        public readonly array $stranke,
        public readonly array $protustranke,
        public readonly array $prilozi,
        public readonly ?PristojbaDTO $pristojba,
        public readonly array $prosljedjivanja,
        public readonly ?string $vrijemeKreiranja,
        public readonly ?string $vrijemeSlanja,
        public readonly ?array $sadrzaj = null,
        public readonly ?string $statusValidacijePotpisa = null,
    ) {}

    public static function fromApiResponse(array $data): self
    {
        $stranke = array_map(
            fn (array $s) => StrankaDTO::fromApiResponse($s),
            $data['stranke'] ?? []
        );

        $protustranke = array_map(
            fn (array $s) => StrankaDTO::fromApiResponse($s),
            $data['protustranke'] ?? []
        );

        $prilozi = array_map(
            fn (array $p) => PrilogDTO::fromApiResponse($p),
            $data['prilozi'] ?? []
        );

        $pristojba = isset($data['pristojba'])
            ? PristojbaDTO::fromApiResponse($data['pristojba'])
            : null;

        return new self(
            id: (int) $data['id'],
            status: $data['status'] ?? 'NACRT',
            sudId: isset($data['sudId']) ? (int) $data['sudId'] : null,
            sudNaziv: $data['sudNaziv'] ?? null,
            predmetId: isset($data['predmetId']) ? (int) $data['predmetId'] : null,
            predmetOznaka: $data['predmetOznaka'] ?? null,
            vrstaPostupkaId: isset($data['vrstaPostupkaId']) ? (int) $data['vrstaPostupkaId'] : null,
            vrstaPodneskaId: isset($data['vrstaPodneskaId']) ? (int) $data['vrstaPodneskaId'] : null,
            vrstaPodneskaOznaka: $data['vrstaPodneskaOznaka'] ?? null,
            stranke: $stranke,
            protustranke: $protustranke,
            prilozi: $prilozi,
            pristojba: $pristojba,
            prosljedjivanja: $data['prosljedjivanja'] ?? [],
            vrijemeKreiranja: $data['vrijemeKreiranja'] ?? null,
            vrijemeSlanja: $data['vrijemeSlanja'] ?? null,
            sadrzaj: $data['sadrzaj'] ?? null,
            statusValidacijePotpisa: $data['statusValidacijePotpisa'] ?? null,
        );
    }

    public function isDraft(): bool
    {
        return $this->status === 'NACRT';
    }

    public function isSent(): bool
    {
        return $this->status === 'POSLAN';
    }

    /**
     * Check if the document has a valid digital signature.
     *
     * Signature status must be 'PRIHVATLJIV' to send to court.
     */
    public function hasValidSignature(): bool
    {
        return $this->statusValidacijePotpisa === 'PRIHVATLJIV';
    }
}
