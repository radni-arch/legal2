<?php

namespace App\DTOs\Ekom\Otpravci;

use App\DTOs\Ekom\DokumentDTO;
use Carbon\Carbon;

/**
 * DTO for otpravak (dispatch) detail view.
 *
 * Status values: U_DOSTAVI, URUCEN, NEURUCEN
 * Note: These differ from the paged list statuses (PRIMLJEN, U_DOSTAVI).
 */
class OtpravakDTO
{
    /**
     * @param  DokumentDTO[]  $dokumenti
     */
    public function __construct(
        public readonly int $id,
        public readonly string $status,
        public readonly ?string $primatelj,
        public readonly ?string $datumOtpreme,
        public readonly ?string $datumUrucenja,
        public readonly ?string $zadnjiTrenutakZaPotvrduPrimitka,
        public readonly bool $primljenZbogIstekaRoka,
        public readonly array $dokumenti,
    ) {}

    public static function fromApiResponse(array $data): self
    {
        $dokumenti = [];
        if (isset($data['dokumenti']) && is_array($data['dokumenti'])) {
            foreach ($data['dokumenti'] as $dok) {
                $dokumenti[] = DokumentDTO::fromApiResponse($dok);
            }
        }

        return new self(
            id: (int) $data['id'],
            status: (string) $data['status'],
            primatelj: $data['primatelj'] ?? null,
            datumOtpreme: $data['datumOtpreme'] ?? null,
            datumUrucenja: $data['datumUrucenja'] ?? null,
            zadnjiTrenutakZaPotvrduPrimitka: $data['zadnjiTrenutakZaPotvrduPrimitka'] ?? null,
            primljenZbogIstekaRoka: (bool) ($data['primljenZbogIstekaRoka'] ?? false),
            dokumenti: $dokumenti,
        );
    }

    /**
     * Check if this dispatch is approaching its confirmation deadline.
     * Per ZPP cl. 143.c, the deadline is legally critical.
     */
    public function isDeadlineApproaching(int $hoursThreshold = 24): bool
    {
        if ($this->zadnjiTrenutakZaPotvrduPrimitka === null) {
            return false;
        }

        $deadline = Carbon::parse($this->zadnjiTrenutakZaPotvrduPrimitka);
        $hoursRemaining = Carbon::now()->diffInHours($deadline, false);

        return $hoursRemaining <= $hoursThreshold;
    }

    /**
     * Check if deadline has passed.
     */
    public function isDeadlinePassed(): bool
    {
        if ($this->zadnjiTrenutakZaPotvrduPrimitka === null) {
            return false;
        }

        return Carbon::now()->isAfter(Carbon::parse($this->zadnjiTrenutakZaPotvrduPrimitka));
    }

    /**
     * Get hours until deadline (negative if passed).
     */
    public function hoursUntilDeadline(): ?float
    {
        if ($this->zadnjiTrenutakZaPotvrduPrimitka === null) {
            return null;
        }

        $deadline = Carbon::parse($this->zadnjiTrenutakZaPotvrduPrimitka);
        $now = Carbon::now();

        $diffInSeconds = $deadline->getTimestamp() - $now->getTimestamp();

        return round($diffInSeconds / 3600, 2);
    }
}
