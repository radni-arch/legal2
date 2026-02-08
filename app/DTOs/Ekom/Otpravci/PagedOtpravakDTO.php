<?php

namespace App\DTOs\Ekom\Otpravci;

/**
 * DTO for otpravak (dispatch) in paginated list view.
 *
 * Status values: PRIMLJEN, U_DOSTAVI
 * Note: These differ from the detail view statuses (U_DOSTAVI, URUCEN, NEURUCEN).
 */
class PagedOtpravakDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $status,
        public readonly ?string $predmetOznaka,
        public readonly ?int $predmetId,
        public readonly ?string $sudNaziv,
        public readonly ?string $datumSlanjaSaSuda,
        public readonly ?string $datumPotvrdePrimitka,
        public readonly ?string $zadnjiTrenutakZaPotvrduPrimitka,
        public readonly bool $primljenZbogIstekaRoka,
    ) {}

    public static function fromApiResponse(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            status: (string) $data['status'],
            predmetOznaka: $data['predmetOznaka'] ?? null,
            predmetId: isset($data['predmetId']) ? (int) $data['predmetId'] : null,
            sudNaziv: $data['sudNaziv'] ?? null,
            datumSlanjaSaSuda: $data['datumSlanjaSaSuda'] ?? null,
            datumPotvrdePrimitka: $data['datumPotvrdePrimitka'] ?? null,
            zadnjiTrenutakZaPotvrduPrimitka: $data['zadnjiTrenutakZaPotvrduPrimitka'] ?? null,
            primljenZbogIstekaRoka: (bool) ($data['primljenZbogIstekaRoka'] ?? false),
        );
    }
}
